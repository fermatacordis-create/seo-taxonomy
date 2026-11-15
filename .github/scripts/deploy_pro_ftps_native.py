#!/usr/bin/env python3
"""Copy changed TaxoCommerce files from GitHub STAGING to production over FTPS.

GitHub selects the delta. No WordPress REST requests, plugin inventories,
installed-plugin checks, monolith, or separate plugin activation steps.
"""
from __future__ import annotations

import ftplib
import hashlib
import io
import json
import os
from pathlib import Path, PurePosixPath
import re
import ssl
import sys

SLUG = re.compile(r"taxocommerce-[a-z0-9-]+\Z")
SEGMENT = re.compile(r"[a-zA-Z0-9_.+-]+\Z")
# Existing FTP credentials connect to the production account, not STAGING.
PLUGINS_DIR = "wp-content/plugins"
MAX_BYTES = 32 * 1024 * 1024


def valid_path(slug, relative):
    if not isinstance(slug, str) or not SLUG.fullmatch(slug):
        return False
    if not isinstance(relative, str) or not relative or relative.startswith("/") or "\\" in relative:
        return False
    parts = PurePosixPath(relative).parts
    if not parts or any(not SEGMENT.fullmatch(p) or p.startswith(".") or p in (".", "..") for p in parts):
        return False
    if any(p in ("tests", "uploads", "node_modules", ".git") for p in parts):
        return False
    return True


def read_hash(ftp, filename):
    digest = hashlib.sha256()
    try:
        ftp.retrbinary("RETR " + filename, digest.update)
    except ftplib.error_perm as exc:
        if str(exc).startswith("550"):
            return None
        raise
    return digest.hexdigest()


def copy_changed(ftp, slug, relative, root):
    """Copy just one modified file; skip identical content and verify SHA-256."""
    if not valid_path(slug, relative):
        raise ValueError("Invalid plugin path")
    source = Path(slug, *PurePosixPath(relative).parts)
    if not source.is_file() or source.is_symlink() or source.stat().st_size > MAX_BYTES:
        raise ValueError("Changed file missing or invalid")
    content = source.read_bytes()
    digest = hashlib.sha256(content).hexdigest()

    ftp.cwd(root)
    # This creates destination folders if a new TaxoCommerce plugin was added
    # to GitHub; it does not activate the plugin in WordPress.
    for name in (slug, *PurePosixPath(relative).parts[:-1]):
        try:
            ftp.cwd(name)
        except ftplib.error_perm as exc:
            if not str(exc).startswith("550"):
                raise
            ftp.mkd(name)
            ftp.cwd(name)

    filename = PurePosixPath(relative).name
    if read_hash(ftp, filename) == digest:
        print("UNCHANGED " + slug + "/" + relative)
        return False

    # Use the same simple upload/rename approach as STAGING, with a local
    # rollback copy of the previous file in case the rename/check fails.
    temporary = ".taxocommerce-upload-" + hashlib.sha256(
        (slug + "/" + relative).encode("utf-8")
    ).hexdigest()[:20] + ".tmp"
    backup = temporary + ".previous"
    previous_exists = read_hash(ftp, filename) is not None
    old_moved = False
    new_moved = False
    try:
        ftp.storbinary("STOR " + temporary, io.BytesIO(content), blocksize=65536)
        if read_hash(ftp, temporary) != digest:
            raise RuntimeError("Uploaded file checksum mismatch")
        if previous_exists:
            ftp.rename(filename, backup)
            old_moved = True
        ftp.rename(temporary, filename)
        new_moved = True
        if read_hash(ftp, filename) != digest:
            raise RuntimeError("Destination checksum mismatch")
        if old_moved:
            ftp.delete(backup)
            old_moved = False
        print("UPDATED " + slug + "/" + relative)
        return True
    except Exception:
        if new_moved:
            ftp.delete(filename)
        if old_moved:
            try:
                ftp.rename(backup, filename)
            except ftplib.Error:
                raise RuntimeError("Could not restore previous file; abort deployment") from None
        raise
    finally:
        try:
            ftp.delete(temporary)
        except ftplib.Error:
            pass


def main():
    if len(sys.argv) != 2:
        raise ValueError("Usage: deploy_pro_ftps_native.py delta.json")
    credentials = ("FTP_SERVER", "FTP_USERNAME", "FTP_PASSWORD")
    if any(not os.environ.get(name) for name in credentials):
        raise ValueError("Existing FTP_SERVER, FTP_USERNAME or FTP_PASSWORD secret missing")

    delta = json.loads(Path(sys.argv[1]).read_text(encoding="utf-8"))
    if not isinstance(delta, dict):
        raise ValueError("Invalid changed-file list")
    if not delta:
        print("No changed plugin files. Nothing to copy.")
        return

    for slug, paths in delta.items():
        if not isinstance(paths, list) or not paths or len(paths) != len(set(paths)):
            raise ValueError("Invalid changed-file list")
        if any(not valid_path(slug, path) for path in paths):
            raise ValueError("Unsafe changed-file path")

    ftp = ftplib.FTP_TLS(context=ssl.create_default_context(), timeout=45)
    try:
        ftp.connect(os.environ["FTP_SERVER"], 21)
        ftp.login(os.environ["FTP_USERNAME"], os.environ["FTP_PASSWORD"])
        ftp.prot_p()
        # A single fixed WordPress plugins directory, not a scan or lookup.
        ftp.cwd(PLUGINS_DIR)
        root = ftp.pwd()
        total = 0
        for slug, paths in sorted(delta.items()):
            for relative in sorted(paths):
                total += int(copy_changed(ftp, slug, relative, root))
        print(f"PRO: {total} changed files copied and verified.")
    finally:
        try:
            ftp.quit()
        except (ftplib.Error, OSError):
            ftp.close()


if __name__ == "__main__":
    try:
        main()
    except (ftplib.Error, OSError, ValueError, RuntimeError) as exc:
        # FTP server errors sometimes include account paths, so avoid printing
        # untrusted exception text or credentials.
        print("::error::PRO file copy failed: " + type(exc).__name__, file=sys.stderr)
        sys.exit(1)
