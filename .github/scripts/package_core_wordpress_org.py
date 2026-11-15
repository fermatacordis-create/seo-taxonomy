#!/usr/bin/env python3
"""Validate the WordPress.org Core package under its confirmed short slug.

The Plugins Team assigned taxocommerce-core on 12 November 2026.
Use the identical installable Core source for staging and WordPress.org,
without rewriting gettext domains, main filenames or WordPress identifiers.
This script acts only on the temporary build directory.
"""
from __future__ import annotations

import argparse
import re
from pathlib import Path

OFFICIAL = "taxocommerce-core"
INSTALLED = OFFICIAL
OLD_SLUG = "taxocommerce-core-catalog-content-management-for-woocommerce"
LOCALES = ("es_ES", "en_US", "fr_FR", "de_DE", "nl_NL")


def prepare(folder: Path) -> dict[str, int]:
    if folder.name != OFFICIAL:
        raise ValueError("The distribution directory must match the confirmed WordPress.org slug")
    main = folder / (OFFICIAL + ".php")
    readme = folder / "readme.txt"
    if not main.is_file() or not readme.is_file():
        raise ValueError("Missing canonical Core entrypoint or readme")
    header = main.read_text(encoding="utf-8").split("*/", 1)[0]
    if not re.search(r"(?m)^\s*\*\s*Text Domain:\s*taxocommerce-core\s*$", header):
        raise ValueError("Core WordPress.org domain must match taxocommerce-core")
    version = re.search(r"(?m)^\s*\*\s*Version:\s*([0-9.]+)\s*$", header)
    if not version or "Stable tag: " + version.group(1) not in readme.read_text(encoding="utf-8"):
        raise ValueError("Core version and stable tag must match")
    if (folder / (OLD_SLUG + ".php")).exists():
        raise ValueError("Obsolete long-slug Core main file must not be published")
    lang = folder / "languages"
    for locale in LOCALES:
        for suffix in (".po", ".mo"):
            if not (lang / (OFFICIAL + "-" + locale + suffix)).is_file():
                raise ValueError("Missing Core locale catalog: " + locale + suffix)
    # Legacy long-slug catalogs are not part of the approved short-slug ZIP.
    # The installed plugin sources, including any historical catalogs, are untouched.
    legacy_removed = 0
    for old in lang.glob(OLD_SLUG + "-*"):
        if old.is_file() and old.suffix in (".po", ".mo"):
            old.unlink()
            legacy_removed += 1
    return {"legacy_catalogs_excluded": legacy_removed}


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("build_directory", type=Path)
    args = parser.parse_args()
    result = prepare(args.build_directory)
    print("WordPress.org package verified: taxocommerce-core; excluded "
          f"{result['legacy_catalogs_excluded']} obsolete-domain catalogs.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
