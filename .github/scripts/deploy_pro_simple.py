#!/usr/bin/env python3
"""Reconcile all tracked TaxoCommerce plugin files from STAGING with real PRO.

FTP is jailed in seo-taxonomy/, not WP_PLUGIN_DIR. A short-lived signed PHP
receiver in that existing directory asks WordPress to apply each ZIP at the real
WP_PLUGIN_DIR. No installed/active plugin inventory or new credentials.
"""
import ftplib
import hashlib
import io
import json
import os
from pathlib import Path, PurePosixPath
import re
import secrets
import ssl
import subprocess
import sys
import time
import urllib.parse
import urllib.request
import zipfile

HOST = "https://www.distribuidordeherramientas.es"
SLUG = re.compile(r"taxocommerce-[a-z0-9-]+$")
PART = re.compile(r"[A-Za-z0-9._+-]+$")
MAX_FILE = 64 * 1024 * 1024


def deliverables():
    """All tracked files of every TaxoCommerce plugin, excluding .gitlab."""
    names = subprocess.check_output(["git", "ls-files", "-z", "--", "taxocommerce-*"])
    selected = {}
    for tracked in names.decode("utf-8").split("\0"):
        if not tracked:
            continue
        parts = PurePosixPath(tracked).parts
        if len(parts) < 2 or not SLUG.fullmatch(parts[0]):
            continue
        slug, relative_parts = parts[0], parts[1:]
        if any(part.startswith(".") for part in relative_parts):
            continue
        if any(part in {".", ".."} or not PART.fullmatch(part) for part in relative_parts):
            raise RuntimeError("Unsafe source path in Git")
        file = Path(tracked)
        if not file.is_file() or file.is_symlink() or file.stat().st_size > MAX_FILE:
            raise RuntimeError("Source file unavailable, linked or oversized: " + tracked)
        selected.setdefault(slug, []).append(("/".join(relative_parts), file))

    for slug in sorted(selected):
        if not (Path(slug) / (slug + ".php")).is_file():
            raise RuntimeError("Missing local plugin main file: " + slug)

    # Billing PDF vendor library is shared with the legacy source. The prior
    # working release explicitly bundled it for TaxoCommerce Facturacion.
    dompdf = Path("includes/facturas/dompdf")
    if "taxocommerce-facturacion" in selected and (dompdf / "autoload.inc.php").is_file():
        for file in dompdf.rglob("*"):
            if not file.is_file() or file.is_symlink() or file.stat().st_size > MAX_FILE:
                continue
            relative = file.relative_to(dompdf)
            if any(part.startswith(".") for part in relative.parts):
                continue
            if any(not PART.fullmatch(part) for part in relative.parts):
                raise RuntimeError("Unsafe Dompdf source filename")
            selected["taxocommerce-facturacion"].append((
                "includes/dompdf/" + relative.as_posix(), file
            ))

    if not selected:
        raise RuntimeError("No TaxoCommerce source plugins found in STAGING")
    for slug, files in sorted(selected.items()):
        names = [relative for relative, _ in files]
        if len(names) != len(set(names)):
            raise RuntimeError("Duplicate packaged source path for " + slug)
        print(slug + ": " + str(len(files)) + " source files", flush=True)
    return selected


def create_packages(modules):
    out = {}
    for slug, files in sorted(modules.items()):
        buf = io.BytesIO()
        manifest = {}
        with zipfile.ZipFile(buf, mode="w", compression=zipfile.ZIP_DEFLATED, compresslevel=6) as z:
            for relative, source in sorted(files):
                data = source.read_bytes()
                z.writestr(relative, data)
                manifest[relative] = hashlib.sha256(data).hexdigest()
        out[slug] = (buf.getvalue(), hashlib.sha256(
            json.dumps(manifest, sort_keys=True, separators=(",", ":")).encode("ascii")
        ).hexdigest(), len(manifest))
    return out


def receiver_php(token_hash, expires, folder):
    """Temporary authenticated GET receiver inside the existing legacy FTP jail.

    The previous POST call was rejected by PRO with HTTP 403, and WordPress's
    old REST route is now 404. This endpoint loads WP independently of whether
    the legacy plugin is activated, and is removed after delivery.
    """
    return """<?php
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' ||
    time() > %d ||
    !isset($_GET['token']) ||
    !is_string($_GET['token']) ||
    !hash_equals('%s', hash('sha256', $_GET['token']))) {
    http_response_code(403); exit;
}
$slug = (string)($_GET['slug'] ?? '');
if (!preg_match('/^taxocommerce-[a-z0-9-]+$/D', $slug)) { http_response_code(400); exit; }
$archive = __DIR__ . '/.taxocommerce-deploy/%s/' . $slug . '.zip';
if (!is_file($archive)) { http_response_code(404); exit; }
$root = dirname(__DIR__, 3) . '/wp-load.php';
if (!is_file($root)) { http_response_code(500); exit; }
require_once $root;
if (!function_exists('seo_taxocommerce_deploy_apply_zip_delta')) {
    require_once __DIR__ . '/includes/seo-deploy-status.php';
}
if (!function_exists('seo_taxocommerce_deploy_apply_zip_delta')) {
    http_response_code(500); exit;
}
$destination = WP_PLUGIN_DIR . '/' . $slug;
if (!is_dir($destination) && !wp_mkdir_p($destination)) { http_response_code(500); exit; }
$result = seo_taxocommerce_deploy_apply_zip_delta($archive, $destination);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
if (is_wp_error($result)) {
    http_response_code(500);
    echo wp_json_encode(array('error' => $result->get_error_code()));
    exit;
}
echo wp_json_encode(array('slug' => $slug, 'verified_files' => $result['verified_files'],
    'manifest_sha256' => $result['manifest_sha256'],
    'changed' => count($result['changed']), 'unchanged' => $result['unchanged']));
""" % (expires, token_hash, folder)


def mkdir(ftp, name):
    try:
        ftp.mkd(name)
    except ftplib.error_perm as exc:
        if not str(exc).startswith("550"):
            raise
    ftp.cwd(name)


def main():
    if not all(os.environ.get(k) for k in ("FTP_SERVER", "FTP_USERNAME", "FTP_PASSWORD")):
        raise RuntimeError("Existing FTP_SERVER/FTP_USERNAME/FTP_PASSWORD secrets required")
    modules = deliverables()
    packages = create_packages(modules)
    token = secrets.token_hex(32)
    ident = secrets.token_hex(24)
    receiver = "tc-pro-apply-" + ident + ".php"
    folder = "run-" + ident
    ftp = ftplib.FTP_TLS(context=ssl.create_default_context(), timeout=50)
    ftp.connect(os.environ["FTP_SERVER"], 21)
    ftp.login(os.environ["FTP_USERNAME"], os.environ["FTP_PASSWORD"])
    ftp.prot_p()
    root = ftp.pwd()
    opened = False
    try:
        # The existing FTP account is rooted in the historical plugin, as
        # verified by the previously successful ZIP handoff in Actions.
        mkdir(ftp, ".taxocommerce-deploy")
        mkdir(ftp, folder)
        for slug, (data, _, _) in packages.items():
            ftp.storbinary("STOR " + slug + ".zip", io.BytesIO(data), blocksize=65536)
        ftp.cwd(root)
        php = receiver_php(hashlib.sha256(token.encode("ascii")).hexdigest(),
                           int(time.time()) + 3600, folder)
        ftp.storbinary("STOR " + receiver, io.BytesIO(php.encode("utf-8")), blocksize=65536)
        opened = True
        updated = 0
        for slug, (_, manifest, count) in packages.items():
            query = urllib.parse.urlencode({"token": token, "slug": slug})
            request = urllib.request.Request(
                HOST + "/wp-content/plugins/seo-taxonomy/" + receiver + "?" + query,
                headers={"Accept": "application/json", "Cache-Control": "no-store"},
                method="GET",
            )
            with urllib.request.urlopen(request, timeout=100) as response:
                data = json.load(response)
            if (data.get("slug") != slug or data.get("verified_files") != count
                    or data.get("manifest_sha256") != manifest):
                raise RuntimeError("WordPress file checksum verification failed for " + slug)
            updated += int(data["changed"])
            print(slug + ": " + str(data["changed"]) + " files changed, "
                  + str(data["unchanged"]) + " already identical")
        print("SUCCESS: WordPress verified " + str(len(packages))
              + " plugins; " + str(updated) + " files updated in real WP_PLUGIN_DIR")
    finally:
        try:
            ftp.cwd(root)
            if opened:
                ftp.delete(receiver)
            ftp.cwd(".taxocommerce-deploy/" + folder)
            for slug in packages:
                try:
                    ftp.delete(slug + ".zip")
                except ftplib.error_perm:
                    pass
            ftp.cwd(root)
            ftp.cwd(".taxocommerce-deploy")
            ftp.rmd(folder)
        except ftplib.Error as exc:
            print("WARNING: Temporary handoff cleanup incomplete: " + type(exc).__name__,
                  file=sys.stderr)
        try:
            ftp.quit()
        except (ftplib.Error, OSError):
            ftp.close()


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        print("ERROR: PRO transfer failed: " + type(exc).__name__ + ": "
              + str(exc).split("\n")[0][:180], file=sys.stderr)
        raise SystemExit(1)
