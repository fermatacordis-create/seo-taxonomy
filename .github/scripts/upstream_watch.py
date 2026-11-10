#!/usr/bin/env python3
"""Upstream watch for the TaxoCommerce suite.

Compares the author's staging branch (upstream) with the module versions
published in our suite channel (latest release manifest) and prints a
Markdown report. Exit code 0 always; the report's first line is
"CHANGES" or "NONE".

Usage: upstream_watch.py <upstream-ref> <manifest.json>
"""
import json
import re
import subprocess
import sys

VERSION = re.compile(r"^\s*\*?\s*Version:\s*(\S+)", re.M)


def git(*args):
    return subprocess.run(["git", *args], check=True, capture_output=True, text=True).stdout


def upstream_modules(ref):
    mods = {}
    for name in git("ls-tree", "-d", "--name-only", ref).split():
        if not name.startswith("taxocommerce-"):
            continue
        try:
            main = git("show", f"{ref}:{name}/{name}.php")
        except subprocess.CalledProcessError:
            continue  # folder without a main plugin file
        m = VERSION.search(main)
        mods[name] = m.group(1) if m else "?"
    return mods


def main():
    ref, manifest_path = sys.argv[1], sys.argv[2]
    try:
        published = json.load(open(manifest_path, encoding="utf-8")).get("modules", {})
    except (OSError, ValueError):
        published = {}
    up = upstream_modules(ref)
    head = git("log", "-1", "--format=%h · %cd · %s", "--date=short", ref).strip()

    updated, new, gone = [], [], []
    for name, version in sorted(up.items()):
        if name == "taxocommerce-core":
            continue  # Core is not in the channel; it travels with the suite branch
        ours = published.get(name, {}).get("version")
        if ours is None:
            new.append((name, version))
        elif ours != version:
            updated.append((name, ours, version))
    for name in sorted(published):
        if name not in up:
            gone.append(name)

    lines = []
    lines.append("CHANGES" if (updated or new or gone) else "NONE")
    lines.append(f"Último commit del autor (`staging`): {head}")
    lines.append("")
    if new:
        lines.append("### Módulos nuevos del autor (no están en nuestro canal)")
        lines += [f"- `{n}` {v}" for n, v in new]
        lines.append("")
    if updated:
        lines.append("### Versiones nuevas")
        lines.append("| Módulo | Nuestro canal | Autor |")
        lines.append("|---|---|---|")
        lines += [f"| `{n}` | {o} | **{v}** |" for n, o, v in updated]
        lines.append("")
    if gone:
        lines.append("### Módulos que el autor ya no tiene")
        lines += [f"- `{n}`" for n in gone]
        lines.append("")
    core = up.get("taxocommerce-core")
    if core:
        lines.append(f"TaxoCommerce Core del autor: {core}.")
        lines.append("")
    lines.append("Nada se actualiza solo: mezclar con la rama `suite`, probar en el lab, GO del owner y nueva release del canal.")
    print("\n".join(lines))


if __name__ == "__main__":
    main()
