#!/usr/bin/env python3
"""Select only installable files changed since the last VERIFIED successful PRO release.

No WordPress, FTP, production or repository writes occur here.
A missing/unverifiable baseline fails closed; never redeploy all by default.
"""
from __future__ import annotations

import argparse
import json
import os
from pathlib import Path, PurePosixPath
import re
import subprocess
import sys
import urllib.error
import urllib.request

SLUG = re.compile(r"taxocommerce-[a-z0-9-]+\Z")
SHA = re.compile(r"[0-9a-f]{40}\Z")


def api_json(repo: str, path: str, token: str):
    """Read public release metadata; retry anonymous only on token HTTP 401."""
    url = f"https://api.github.com/repos/{repo}/{path}"
    headers = {
        "Accept": "application/vnd.github+json",
        "X-GitHub-Api-Version": "2022-11-28",
        "User-Agent": "TaxoCommerce-Pro-Delta/1.0",
    }

    def request(authorized: bool):
        safe_headers = dict(headers)
        if authorized:
            safe_headers["Authorization"] = f"Bearer {token}"
        with urllib.request.urlopen(
            urllib.request.Request(url, headers=safe_headers), timeout=20
        ) as response:
            return json.load(response)

    try:
        return request(bool(token))
    except urllib.error.HTTPError as exc:
        if exc.code != 401 or not token:
            raise
        # Repository releases are publicly readable. Never require privileged
        # job logs or substitute a guessed SHA after a failed API lookup.
        return request(False)


def last_successful_source(repo: str, token: str) -> str:
    """Read source revision from a completed, successful PRO workflow run."""
    payload = api_json(
        repo,
        "actions/workflows/despliege_de_main_a_pro.yml/runs?"
        "branch=staging&status=success&per_page=15",
        token,
    )
    runs = payload.get("workflow_runs", [])
    if not isinstance(runs, list):
        raise ValueError("Respuesta de GitHub Actions invalida")
    for run in runs:
        if not isinstance(run, dict):
            continue
        candidate = run.get("head_sha", "")
        if (
            run.get("status") == "completed"
            and run.get("conclusion") == "success"
            and run.get("head_branch") == "staging"
            and isinstance(candidate, str)
            and SHA.fullmatch(candidate)
        ):
            return candidate
    raise ValueError(
        "No existe un SHA verificable del ultimo release PRO correcto en staging. "
        "Se cancela sin transferir archivos."
    )


def git(*args: str) -> bytes:
    return subprocess.check_output(["git", *args], stderr=subprocess.PIPE)


def installable(path: str) -> bool:
    parts = PurePosixPath(path).parts
    if len(parts) < 2 or not SLUG.fullmatch(parts[0]):
        return False
    inner = parts[1:]
    if any(item in {"tests", "uploads", "node_modules"} or item.startswith(".") for item in inner):
        return False
    filename = inner[-1]
    if filename.endswith((".bak", ".log", ".pyc", ".pot")):
        return False
    if filename.endswith(".md") and filename != "CHANGELOG.md":
        return False
    return True


def selected_delta(base: str, head: str, installed: list[str], allowed: list[str],
                   target_plugin: str = "", root: Path = Path(".")) -> dict[str, list[str]]:
    if not SHA.fullmatch(base) or not SHA.fullmatch(head):
        raise ValueError("SHA de origen o destino no valido")
    if git("merge-base", "--is-ancestor", base, head) != b"":
        raise ValueError("Unexpected ancestor result")
    accepted = set(installed) & set(allowed)
    if target_plugin:
        if not SLUG.fullmatch(target_plugin) or target_plugin not in accepted:
            raise ValueError("Plugin seleccionado ausente o no autorizado en PRO")
        accepted &= {target_plugin}
    # Never allow deletions (or renamed-away files) to silently leave drift on PRO.
    deleted = git("diff", "--no-renames", "--name-only", "-z", "--diff-filter=D",
                  base, head, "--", "taxocommerce-*").decode().split("\0")
    for path in deleted:
        if path and installable(path) and path.split("/", 1)[0] in accepted:
            raise ValueError("Archivo eliminado sin migracion segura: " + path)
    changed = git("diff", "--no-renames", "--name-only", "-z", "--diff-filter=ACMT",
                  base, head, "--", "taxocommerce-*").decode().split("\0")
    result = {}
    for path in changed:
        if not path or not installable(path):
            continue
        slug, relative = path.split("/", 1)
        if slug not in accepted:
            continue
        source = root / slug / relative
        if source.is_symlink() or not source.is_file():
            raise ValueError("Archivo cambiado ausente o symlink: " + path)
        if not (root / slug / (slug + ".php")).is_file():
            raise ValueError("No existe el archivo principal instalado: " + slug)
        if not (root / slug / "readme.txt").is_file():
            raise ValueError("No existe el readme.txt: " + slug)
        result.setdefault(slug, []).append(relative)
    return {slug: sorted(set(paths)) for slug, paths in sorted(result.items())}


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", required=True)
    parser.add_argument("--installed-json", required=True)
    parser.add_argument("--allowed-json", required=True)
    parser.add_argument("--target-plugin", default="")
    parser.add_argument("--base-sha", default="")
    args = parser.parse_args()
    installed = json.loads(args.installed_json)
    allowed = json.loads(args.allowed_json)
    if not isinstance(installed, list) or not isinstance(allowed, list):
        raise ValueError("Inventario incorrecto")
    if len(installed) != len(set(installed)) or any(not SLUG.fullmatch(x) for x in installed):
        raise ValueError("Inventario PRO inseguro")
    head = git("rev-parse", "HEAD").decode().strip()
    base = args.base_sha or last_successful_source(
        os.environ["GITHUB_REPOSITORY"], os.environ["GH_TOKEN"]
    )
    # Ensure baseline exists in checkout history, not merely a string from logs.
    git("cat-file", "-e", base + "^{commit}")
    result = selected_delta(base, head, installed, allowed, args.target_plugin)
    Path(args.output).write_text(json.dumps(result, sort_keys=True), encoding="utf-8")
    print(f"Release PRO desde {base} hasta {head}. "
          f"Plugins modificados: {len(result)}; "
          f"archivos: {sum(map(len, result.values()))}.")
    for slug, files in result.items():
        print(f"  {slug}: {len(files)} archivo(s)")
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except (ValueError, OSError, subprocess.CalledProcessError, KeyError) as exc:
        print(f"::error::Seleccion de delta PRO fallida: {exc}", file=sys.stderr)
        sys.exit(1)
