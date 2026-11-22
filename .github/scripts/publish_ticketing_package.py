#!/usr/bin/env python3
"""Publish only the Ticketing ZIP to the two explicitly authorized test sites.

No installation on PRO and no changes to other plugin files. Uses the existing
Core local-package directory, so Core can discover/download the ZIP.
"""
import datetime as dt
import ftplib
import hashlib
import io
import json
import os
from pathlib import Path
import sys
import zipfile

from build_taxocommerce_updates import include
from package_staging_plugins_for_focazul import (
    ftp_root, read_file, verify_zip, write_atomic,
)

SLUG = "taxocommerce-ticketing"
SITES = ("staging.distribuidordeherramientas.es", "asociacionfocazul")
ROOT = Path(__file__).resolve().parents[2]


def package_ticketing(root=ROOT):
    folder = root / SLUG
    entry = folder / (SLUG + ".php")
    if not entry.is_file():
        raise ValueError("Ticketing entrypoint absent")
    stream = io.BytesIO()
    with zipfile.ZipFile(stream, mode="w", compression=zipfile.ZIP_DEFLATED) as bundle:
        for path in sorted(folder.rglob("*")):
            if include(path, folder):
                bundle.write(path, (Path(SLUG) / path.relative_to(folder)).as_posix())
    data = stream.getvalue()
    info = verify_zip(SLUG, data)
    return data, info


def updated_manifest(original, info):
    """Preserve any other valid plugin entries; change Ticketing alone."""
    try:
        manifest = json.loads(original)
    except (ValueError, UnicodeDecodeError) as exc:
        raise ValueError("Existing package manifest is invalid") from exc
    if manifest.get("schema") != 1 or not isinstance(manifest.get("plugins"), dict):
        raise ValueError("Existing package manifest has an unexpected schema")
    manifest["plugins"][SLUG] = info
    manifest["generated_utc"] = dt.datetime.now(dt.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")
    return (json.dumps(manifest, indent=2, sort_keys=True) + "\n").encode("utf-8")


def publish(ftp, home, data, info):
    results = {}
    for site in SITES:
        location = ftp_root(ftp, home, site, create=True)
        # Verify the ZIP after upload and before publishing the updated manifest.
        status = write_atomic(ftp, location, SLUG + ".zip", data, info["sha256"])
        ftp.cwd(location)
        try:
            raw = read_file(ftp, location + "/manifest.json", 512000)
        except ftplib.error_perm as exc:
            if not str(exc).startswith("550"):
                raise
            raw = None
        manifest_status = "ABSENT (local ZIP discovery remains available)"
        if raw is not None:
            updated = updated_manifest(raw, info)
            manifest_status = write_atomic(
                ftp, location, "manifest.json", updated, hashlib.sha256(updated).hexdigest()
            )
        results[site] = {"zip": status, "manifest": manifest_status, "sha256": info["sha256"]}
        print(f"{site}: verified Ticketing {status} sha256:{info['sha256'][:12]}; manifest {manifest_status}", flush=True)
    return results


def main():
    data, info = package_ticketing()
    host = os.environ["STAGING_FTP_SERVER"]
    user = os.environ["STAGING_FTP_USERNAME"]
    password = os.environ["STAGING_FTP_PASSWORD"]
    if not host or not user or not password:
        raise RuntimeError("Missing test-site FTPS credentials")
    ftp = ftplib.FTP_TLS(timeout=60)
    try:
        ftp.connect(host, 21)
        ftp.login(user, password)
        ftp.prot_p()
        publish(ftp, ftp.pwd(), data, info)
        ftp.quit()
    finally:
        try:
            ftp.close()
        except OSError:
            pass


if __name__ == "__main__":
    try:
        main()
    except (ValueError, OSError, RuntimeError, *ftplib.all_errors) as error:
        print("::error::Ticketing package distribution FAILED: " + str(error), file=sys.stderr)
        sys.exit(1)
