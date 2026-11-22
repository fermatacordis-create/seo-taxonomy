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


def exists(ftp, filename):
    """Check presence using metadata only; never download remote file contents."""
    try:
        ftp.size(filename)
        return True
    except ftplib.error_perm as exc:
        if str(exc).startswith("550"):
            return False
        raise


def find_live_plugin_root(ftp):
    """Find only an existing WordPress plugins root; never create another one.

    The previous uploader created wp-content/plugins *inside* an unrelated
    FTPS chroot and falsely reported 103 successful production updates.
    This checks two files at a fixed root, not an installed-plugin inventory.
    """
    home = ftp.pwd()
    matches = set()
    try:
        for candidate in ("", PLUGINS_DIR):
            ftp.cwd(home)
            try:
                if candidate:
                    ftp.cwd(candidate)
                if (exists(ftp, "woocommerce/woocommerce.php")
                        and exists(ftp, "taxocommerce-core/taxocommerce-core.php")):
                    matches.add(ftp.pwd())
            except ftplib.error_perm as exc:
                if not str(exc).startswith("550"):
                    raise
        if len(matches) != 1:
            raise RuntimeError(
                "Wrong production FTPS root: no unique WooCommerce and TaxoCommerce plugins directory. "
                "No files were copied; configure FTP_SERVER/FTP_USERNAME/FTP_PASSWORD for the real PRO site."
            )
        return next(iter(matches))
    finally:
        ftp.cwd(home)


def copy_changed(ftp, slug, relative, root):
    """Upload a Git-selected file once; do not compare or retrieve remote files."""
    if not valid_path(slug, relative):
        raise ValueError("Invalid plugin path")
    source = Path(slug, *PurePosixPath(relative).parts)
    if not source.is_file() or source.is_symlink() or source.stat().st_size > MAX_BYTES:
        raise ValueError("Changed file missing or invalid")

    ftp.cwd(root)
    for name in (slug, *PurePosixPath(relative).parts[:-1]):
        try:
            ftp.cwd(name)
        except ftplib.error_perm as exc:
            if not str(exc).startswith("550"):
                raise
            ftp.mkd(name)
            ftp.cwd(name)

    filename = PurePosixPath(relative).name
    temporary = ".taxocommerce-upload-" + hashlib.sha256(
        (slug + "/" + relative).encode("utf-8")
    ).hexdigest()[:20] + ".tmp"
    backup = temporary + ".previous"
    previous_exists = exists(ftp, filename)
    old_moved = False
    new_moved = False
    try:
        with source.open("rb") as stream:
            ftp.storbinary("STOR " + temporary, stream, blocksize=65536)
        # SIZE checks the uploaded byte count without RETR/downloading content.
        if ftp.size(temporary) != source.stat().st_size:
            raise RuntimeError("Uploaded file size mismatch")
        if previous_exists:
            ftp.rename(filename, backup)
            old_moved = True
        ftp.rename(temporary, filename)
        new_moved = True
        if old_moved:
            ftp.delete(backup)
            old_moved = False
        print("UPDATED " + slug + "/" + relative, flush=True)
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
        # No WordPress inventory: only confirm that the FTPS account can
        # access the existing real WooCommerce plugin root before any write.
        ftp.voidcmd('TYPE I')
        root = find_live_plugin_root(ftp)
        print("PRO WordPress plugins root verified; uploading Git-selected changed files.", flush=True)
        total = 0
        for slug, paths in sorted(delta.items()):
            for relative in sorted(paths):
                total += int(copy_changed(ftp, slug, relative, root))
        print(f"PRO: {total} Git-selected changed files uploaded.")
    finally:
        try:
            ftp.quit()
        except (ftplib.Error, OSError):
            ftp.close()


if __name__ == "__main__":
    try:
        main()
    except RuntimeError as exc:
        # A working login is not enough: it must reach the actual WP plugins
        # directory, not the old seo-taxonomy account's confined FTP home.
        if str(exc).startswith("Wrong production FTPS root:"):
            print(
                "::error::The existing FTP login cannot access the real PRO "
                "wp-content/plugins directory. No production files copied. "
                "The previous ZIP process could reach sibling plugin folders "
                "only through WordPress; direct FTP requires that destination "
                "to be accessible to this FTP account.",
                file=sys.stderr,
            )
        else:
            print("::error::PRO file copy failed: RuntimeError", file=sys.stderr)
        sys.exit(1)
    except (ftplib.Error, OSError, ValueError) as exc:
        # FTP errors may contain private paths; do not print raw server text.
        print("::error::PRO file copy failed: " + type(exc).__name__, file=sys.stderr)
        sys.exit(1)
