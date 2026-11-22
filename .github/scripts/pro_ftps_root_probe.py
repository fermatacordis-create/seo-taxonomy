#!/usr/bin/env python3
"""Read-only FTPS proof that the configured account reaches the actual PRO plugin root.

This probe cannot deploy, install, delete or edit any file. A location is accepted
only when TWO independent installed plugin headers match live WordPress inventory.
"""
from __future__ import annotations

import ftplib
import io
import os
import re
import ssl
import sys

CANDIDATES = (
    ".",
    "./wp-content/plugins",
    "./public/wp-content/plugins",
    "./public/distribuidordeherramientas.es/wp-content/plugins",
    "./public_html/wp-content/plugins",
    "./httpdocs/wp-content/plugins",
    "./distribuidordeherramientas.es/wp-content/plugins",
    "./public_html/distribuidordeherramientas.es/wp-content/plugins",
    "./www/wp-content/plugins",
    "./domains/distribuidordeherramientas.es/public_html/wp-content/plugins",
    "./web/wp-content/plugins",
    "..",
)
TARGETS = (
    ("taxocommerce-core/taxocommerce-core.php", "PRO_CORE_EXPECTED_VERSION"),
    ("woocommerce/woocommerce.php", "PRO_WOO_EXPECTED_VERSION"),
)
MAX_HEADER = 65536


def read_plugin_version(ftp: ftplib.FTP_TLS, relative: str) -> str:
    received = bytearray()

    def append(chunk: bytes) -> None:
        if len(received) + len(chunk) > MAX_HEADER:
            raise ValueError("plugin header exceeds read limit")
        received.extend(chunk)

    ftp.retrbinary("RETR " + relative, append)
    header = received.decode("utf-8", errors="replace")[:8192]
    match = re.search(r"(?mi)^\s*\*\s*Version:\s*(\d+\.\d+(?:\.\d+){0,2})\s*$", header)
    if not match:
        raise ValueError("main plugin file has no parseable version header")
    return match.group(1)


def discover_candidates(ftp: ftplib.FTP_TLS, login_root: str):
    """Enumerate a bounded set of directories without reading private files.

    Some hosts place the WordPress installation under a domain-specific folder;
    the previous static probe tested only five hard-coded paths.
    """
    allowed = re.compile(r"^[a-zA-Z0-9._-]{1,90}$")
    ignored = frozenset((
        "uploads", "upload", "images", "media", "assets", "node_modules",
        "backup", "backups", "logs", "log", "cache", "caches", "tmp",
        "temp", "vendor", ".git", "mail", "email", "ssl", "cgi-bin",
    ))
    queue = [(login_root, 0)]
    seen = set()
    candidates = []
    visited = 0
    listing_supported = False
    while queue and visited < 90 and len(candidates) < 15:
        path, depth = queue.pop(0)
        try:
            ftp.cwd(path)
            current = ftp.pwd()
            if current in seen:
                continue
            seen.add(current)
            visited += 1
            if current.rstrip("/").endswith("/wp-content/plugins"):
                candidates.append(current)
                continue
            if depth >= 5:
                continue
            entries = list(ftp.mlsd())
            listing_supported = True
        except (ftplib.Error, OSError, ValueError, AttributeError):
            continue
        for name, facts in entries[:250]:
            if (facts.get("type") or "").lower() != "dir":
                continue
            if not allowed.fullmatch(name) or name.lower() in ignored:
                continue
            if name.startswith(".") or name in (".", ".."):
                continue
            # Plugin roots are only considered inside wp-content. All other
            # directory names may be hosting account / domain layouts.
            if name == "plugins" and not current.rstrip("/").endswith("/wp-content"):
                continue
            queue.append((current.rstrip("/") + "/" + name, depth + 1))
    ftp.cwd(login_root)
    return candidates, visited, listing_supported


def verify_pro_site_at_shared_account_root(ftp, expected):
    """Verify ONLY the production WordPress at the shared Webempresa FTP root.

    The existing STAGING account already uses ./staging.distribuidordeherramientas.es
    and ./asociacionfocazul. Production is checked at ./wp-content/plugins,
    never at either staging or Focazul. No MLSD traversal and no writes.
    """
    login_root = ftp.pwd()
    try:
        ftp.cwd(login_root)
        ftp.cwd("./wp-content/plugins")
        canonical = ftp.pwd()
        for relative, key in TARGETS:
            value = read_plugin_version(ftp, relative)
            expected_values = expected[key] if isinstance(expected[key], (list, tuple, set)) else (expected[key],)
            if value not in expected_values:
                raise RuntimeError("Core/WooCommerce version mismatch at the designated PRO root")
        return "./wp-content/plugins", canonical
    except (ftplib.Error, OSError, ValueError) as exc:
        raise RuntimeError("Shared FTPS account cannot verify the designated PRO plugin directory") from exc
    finally:
        ftp.cwd(login_root)


def find_verified_root(ftp: ftplib.FTP_TLS, expected: dict[str, str]):
    """Read-only proof for a unique FTP plugin folder matching live WP versions."""
    login_root = ftp.pwd()
    matches = {}
    inspected = 0
    permission_denied = 0
    version_mismatch = 0
    static_paths = list(CANDIDATES)
    # MLSD locates domain-specific WordPress installs, not just public_html.
    found, visited, listed = discover_candidates(ftp, login_root)
    static_paths.extend(found)
    for candidate in static_paths:
        try:
            ftp.cwd(login_root)
            ftp.cwd(candidate)
            canonical = ftp.pwd()
            versions = {}
            for relative, var in TARGETS:
                versions[var] = read_plugin_version(ftp, relative)
            inspected += 1
            if all(
                versions[key] in (expected[key] if isinstance(expected[key], (list, tuple, set))
                                  else (expected[key],))
                for key in versions
            ):
                matches[canonical] = candidate
            else:
                version_mismatch += 1
        except (ftplib.Error, OSError, ValueError, UnicodeError):
            permission_denied += 1
        finally:
            ftp.cwd(login_root)
    if len(matches) != 1:
        # Do not print any secret, user, server, path or unexpected plugin data.
        raise RuntimeError(
            "No unique verified PRO plugins directory; "
            f"directories_scanned={visited}, mlsd_available={listed}, "
            f"candidate_headers_read={inspected}, "
            f"version_mismatch={version_mismatch}, "
            f"inaccessible_candidates={permission_denied}, "
            f"matching_roots={len(matches)}. No files written."
        )
    return next(iter(matches.values())), next(iter(matches.keys()))


def diagnose_known_roots(ftp):
    """Bounded read-only diagnostics. No site inventory, FTP writes or credentials."""
    login_root = ftp.pwd()
    # Print only plausible public WordPress site directory names, not private
    # paths, credentials or arbitrary files. Useful for FTP-account chroots.
    try:
        candidates = sorted(
            name for name, facts in ftp.mlsd()
            if (facts.get("type") or "").lower() == "dir"
            and name.lower().startswith(("distrib", "www", "public", "http", "pro", "web", "dom", "site", "wordpress"))
        )[:25]
        print("SITE ROOT FOLDERS: " + (", ".join(candidates) if candidates else "(none)"), flush=True)
    except (ftplib.Error, OSError):
        print("SITE ROOT FOLDERS: unavailable", flush=True)

    for relative in CANDIDATES:
        try:
            ftp.cwd(login_root)
            ftp.cwd(relative)
            values = []
            for file, label in (
                ("taxocommerce-core/taxocommerce-core.php", "Core"),
                ("woocommerce/woocommerce.php", "WooCommerce"),
                ("taxocommerce-google-bing/taxocommerce-google-bing.php", "Insights"),
            ):
                try:
                    values.append(label + "=" + read_plugin_version(ftp, file))
                except (ftplib.Error, OSError, ValueError):
                    values.append(label + "=absent")
            print("CANDIDATE " + relative + " : " + " ".join(values), flush=True)
        except (ftplib.Error, OSError, ValueError):
            print("CANDIDATE " + relative + " : inaccessible", flush=True)
        finally:
            ftp.cwd(login_root)


def diagnose_legacy_bridge(ftp):
    """Read two fixed, public-source files only. Never print source or credentials."""
    def read_limited(path):
        chunks = bytearray()
        try:
            def append(block):
                if len(chunks) + len(block) > 1048576:
                    raise RuntimeError("Legacy source exceeds size limit")
                chunks.extend(block)
            ftp.retrbinary("RETR " + path, append)
        except ftplib.error_perm as exc:
            if str(exc).startswith("550"):
                return None
            raise
        return bytes(chunks)

    entry = read_limited("seo-taxonomy.php")
    bridge = read_limited("includes/seo-deploy-status.php")
    print("Legacy monolith entrypoint present: " + str(entry is not None), flush=True)
    print("Legacy entrypoint loads deploy bridge: " +
          str(entry is not None and b"includes/seo-deploy-status.php" in entry), flush=True)
    print("Legacy REST deploy bridge source present: " + str(bridge is not None), flush=True)
    print("Legacy REST route source registered: " +
          str(bridge is not None and b"deploy-active-modules" in bridge and
              b"register_rest_route" in bridge), flush=True)


def main() -> int:
    required = ("FTP_SERVER", "FTP_USERNAME", "FTP_PASSWORD",
                "PRO_CORE_EXPECTED_VERSION", "PRO_WOO_EXPECTED_VERSION")
    if any(not os.environ.get(key, "").strip() for key in required):
        raise RuntimeError("FTPS credentials or expected site versions missing; read-only probe aborted")

    expected = {
        "PRO_CORE_EXPECTED_VERSION": os.environ["PRO_CORE_EXPECTED_VERSION"],
        "PRO_WOO_EXPECTED_VERSION": os.environ["PRO_WOO_EXPECTED_VERSION"],
    }
    for v in expected.values():
        if not re.fullmatch(r"\d+\.\d+(?:\.\d+){0,2}", v):
            raise RuntimeError("Invalid expected plugin version")

    context = ssl.create_default_context()
    with ftplib.FTP_TLS(context=context, timeout=25) as ftp:
        ftp.connect(os.environ["FTP_SERVER"], 21)
        ftp.login(os.environ["FTP_USERNAME"], os.environ["FTP_PASSWORD"])
        ftp.prot_p()
        # This is a read-only diagnostic. No FTP upload/remove/mkdir is used.
        if len(sys.argv) == 2 and sys.argv[1] == "--legacy-route-check":
            diagnose_legacy_bridge(ftp)
            return 0
        if len(sys.argv) == 2 and sys.argv[1] == "--diagnose-known-roots":
            diagnose_known_roots(ftp)
            return 0
        if len(sys.argv) == 2 and sys.argv[1] == "--shared-account-pro-root":
            _candidate, _canonical = verify_pro_site_at_shared_account_root(ftp, expected)
        elif len(sys.argv) == 1:
            _candidate, _canonical = find_verified_root(ftp, expected)
        else:
            raise RuntimeError("Unknown read-only probe mode")
        print("PRO FTPS read-only root verified using installed Core "
              + expected["PRO_CORE_EXPECTED_VERSION"] + " and WooCommerce "
              + expected["PRO_WOO_EXPECTED_VERSION"] + ".")
        print("No production file was modified. Exact FTP paths deliberately suppressed.")
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except (ftplib.Error, OSError, RuntimeError, ValueError) as exc:
        # Never expose server names, FTP usernames, passwords or filesystem paths.
        print("::error::PRO FTPS read-only verification failed: "
              + (str(exc) if isinstance(exc, (RuntimeError, ValueError)) else type(exc).__name__),
              file=sys.stderr)
        sys.exit(1)
