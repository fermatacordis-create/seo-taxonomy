#!/usr/bin/env python3
"""Preflight check for standalone TaxoCommerce WordPress.org ZIPs.

This check intentionally does not include or inspect the legacy monolith.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path


def header_values(text: str) -> dict[str, str]:
    values = {}
    for line in text.splitlines():
        match = re.match(r"\s*(?:\*\s*)?([\w ]+):\s*(.+?)\s*$", line)
        if match:
            values[match.group(1).lower()] = match.group(2)
    return values


def check(slug: str, folder: Path) -> int:
    errors = []
    warnings = []
    entry = folder / (folder.name + '.php')
    readme = folder / 'readme.txt'
    if not entry.is_file():
        errors.append(f'Missing plugin entry file: {entry}')
    if not readme.is_file():
        errors.append(f'Missing WordPress.org readme.txt: {readme}')
    if errors:
        for error in errors:
            print(f'::error::{error}')
        return 1

    php_headers = header_values(entry.read_text(encoding='utf-8-sig').split('*/', 1)[0])
    readme_text = readme.read_text(encoding='utf-8-sig')
    readme_headers = header_values(readme_text.split('== Description ==')[0])
    version = php_headers.get('version', '')
    stable_tag = readme_headers.get('stable tag', '')
    if not php_headers.get('plugin name'):
        errors.append('Missing Plugin Name in PHP header')
    if not re.fullmatch(r'\d+(?:\.\d+){1,3}', version):
        errors.append(f'Plugin version is empty or invalid: {version!r}')
    if stable_tag != version:
        errors.append(f'Stable tag {stable_tag!r} does not match Plugin Version {version!r}')
    if not php_headers.get('license'):
        errors.append('Missing License in PHP header')
    if not readme_headers.get('license'):
        errors.append('Missing License in readme.txt')
    if not php_headers.get('text domain'):
        errors.append('Missing Text Domain in PHP header')
    elif php_headers['text domain'] != folder.name:
        errors.append(f'Text Domain {php_headers["text domain"]!r} does not match folder {folder.name!r}')
    tags = [s.strip() for s in readme_headers.get('tags', '').split(',') if s.strip()]
    if len(tags) > 5:
        errors.append(f'WordPress.org allows at most 5 readme tags; found {len(tags)}')
    php_requires = php_headers.get('requires plugins', '')
    readme_requires = readme_headers.get('requires plugins', '')
    if php_requires and readme_requires and php_requires != readme_requires:
        errors.append('Requires Plugins differs between PHP and readme')
    if '== Description ==' not in readme_text:
        warnings.append('Use the standard == Description == readme section')
    if '== Installation ==' not in readme_text:
        warnings.append('Add a standard == Installation == readme section')
    if not readme_headers.get('tested up to'):
        warnings.append('Missing Tested up to; add only after operational verification')
    if not readme_headers.get('contributors'):
        warnings.append('Missing Contributors')

    for error in errors:
        print(f'::error title={slug} metadata::{error}')
    for warning in warnings:
        print(f'::warning title={slug} readme::{warning}')
    print(f'{slug}: {len(errors)} errors, {len(warnings)} readme observations; version={version}')
    return int(bool(errors))


def main() -> int:
    if len(sys.argv) != 3:
        print('Usage: check.py <slug> <package_dir>', file=sys.stderr)
        return 2
    return check(sys.argv[1], Path(sys.argv[2]))


if __name__ == '__main__':
    raise SystemExit(main())
