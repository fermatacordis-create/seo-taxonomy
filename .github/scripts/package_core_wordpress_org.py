#!/usr/bin/env python3
"""Build Core's assigned WordPress.org slug from the *same* installed Core sources.

The installed plugin at taxocommerce-core/ keeps its native domain and main-file
basename. Only the WordPress.org distribution copy is adapted; never edit source
files or mutate plugin data. Both distributions run official strict Plugin Check.
"""
from __future__ import annotations

import argparse
import re
from pathlib import Path

INSTALLED = "taxocommerce-core"
OFFICIAL = "taxocommerce-core-catalog-content-management-for-woocommerce"
I18N_FUNCTIONS = frozenset({
    "__", "_e", "_x", "_ex", "_n", "_nx", "_n_noop", "_nx_noop",
    "esc_html__", "esc_html_e", "esc_html_x",
    "esc_attr__", "esc_attr_e", "esc_attr_x",
    "translate", "translate_with_gettext_context",
})


def adapt_gettext_calls(source: str) -> tuple[str, int]:
    """Change only gettext domain arguments; preserve other 'taxocommerce-core' IDs.

    This scans code/comment/string boundaries and nested function parentheses so
    administration slugs and data identifiers are not renamed by accident.
    """
    frames: list[str] = []
    changes: list[tuple[int, int]] = []
    index = 0
    length = len(source)
    in_php = False
    while index < length:
        if not in_php:
            start = source.find("<?", index)
            if start < 0:
                break
            index = start + 2
            if source[index:index + 3].lower() == "php":
                index += 3
            frames.clear()
            in_php = True
            continue
        if source.startswith("?>", index):
            in_php = False
            frames.clear()
            index += 2
            continue
        char = source[index]
        nxt = source[index + 1] if index + 1 < length else ""
        if char == "/" and nxt == "/":
            offset = source.find("\n", index + 2)
            index = length if offset < 0 else offset
            continue
        if char == "/" and nxt == "*":
            offset = source.find("*/", index + 2)
            index = length if offset < 0 else offset + 2
            continue
        if char == "#":
            offset = source.find("\n", index + 1)
            index = length if offset < 0 else offset
            continue
        if char in ("'", '"'):
            begin = index
            quote = char
            index += 1
            while index < length:
                if source[index] == "\\":
                    index += 2
                    continue
                if source[index] == quote:
                    index += 1
                    break
                index += 1
            if (
                source[begin + 1:index - 1] == INSTALLED
                and frames and frames[-1] in I18N_FUNCTIONS
            ):
                changes.append((begin + 1, index - 1))
            continue
        if char == "(":
            match = re.search(r"([a-zA-Z_][a-zA-Z0-9_]*)\s*$", source[max(0, index - 110):index])
            frames.append(match.group(1) if match else "")
        elif char == ")":
            if frames:
                frames.pop()
        index += 1

    for start, end in reversed(changes):
        source = source[:start] + OFFICIAL + source[end:]
    return source, len(changes)


def prepare(folder: Path) -> dict[str, int]:
    if folder.name != OFFICIAL:
        raise ValueError("The distribution directory must equal the assigned WordPress.org slug")
    original_main = folder / (INSTALLED + ".php")
    translated_main = folder / (OFFICIAL + ".php")
    if not original_main.is_file() or translated_main.exists():
        raise ValueError("Expected exactly one unconverted Core main PHP file")
    if not (folder / "readme.txt").is_file():
        raise ValueError("Missing readme.txt")
    total = 0
    altered = 0
    for path in sorted(folder.rglob("*.php")):
        if "tests" in path.parts:
            continue
        before = path.read_text(encoding="utf-8")
        after, count = adapt_gettext_calls(before)
        total += count
        if path == original_main:
            old_header = "Text Domain: " + INSTALLED
            if after.count(old_header) != 1:
                raise ValueError("Expected one installed textdomain header")
            after = after.replace(old_header, "Text Domain: " + OFFICIAL, 1)
            loader = "$domain = '" + INSTALLED + "';"
            if after.count(loader) != 1:
                raise ValueError("Missing native Core gettext loader")
            after = after.replace(loader, "$domain = '" + OFFICIAL + "';", 1)
        # All file references must follow the renamed WordPress.org entrypoint.
        after = after.replace("'" + INSTALLED + ".php'", "'" + OFFICIAL + ".php'")
        after = after.replace("'/" + INSTALLED + ".php'", "'/" + OFFICIAL + ".php'")
        if "class-taxocommerce-autotest-code.php" == path.name:
            regex = "Text Domain:\\s*" + INSTALLED + "\\s*"
            if regex not in after:
                raise ValueError("Code checker must validate the official textdomain")
            after = after.replace(regex, "Text Domain:\\s*" + OFFICIAL + "\\s*")
        if after != before:
            path.write_text(after, encoding="utf-8")
            altered += 1

    if total < 950:
        raise ValueError("Too few gettext calls rewritten; inspect Core source before publishing")
    original_main.rename(translated_main)
    lang = folder / "languages"
    required = ("es_ES", "en_US", "fr_FR", "de_DE", "nl_NL")
    for locale in required:
        for suffix in (".po", ".mo"):
            if not (lang / (OFFICIAL + "-" + locale + suffix)).is_file():
                raise ValueError("Missing official language catalog: " + locale + suffix)
    # Never ship both competing gettext domains in the official package.
    for f in lang.iterdir():
        if f.is_file() and f.suffix in (".po", ".mo") and f.name.startswith(INSTALLED + "-") \
           and not f.name.startswith(OFFICIAL + "-"):
            f.unlink()
    if (folder / (INSTALLED + ".php")).exists():
        raise ValueError("Short main entrypoint unexpectedly left in distribution")
    return {"gettext_calls": total, "php_files_modified": altered}


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("build_directory", type=Path)
    args = parser.parse_args()
    report = prepare(args.build_directory)
    print(f"Official Core package: {report['gettext_calls']} gettext domains adapted in "
          f"{report['php_files_modified']} PHP files; directory and main filename: {OFFICIAL}.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
