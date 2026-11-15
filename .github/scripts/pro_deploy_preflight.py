#!/usr/bin/env python3
"""Fail-closed, monolith-independent preflight for an unchanged PRO release."""
from __future__ import annotations

import os
import sys

import select_pro_release_delta as release


def changed_since_last_pro_release(repo: str, token: str) -> bool:
    base = release.last_successful_source(repo, token)
    current = release.git("rev-parse", "HEAD").decode().strip()
    if not release.SHA.fullmatch(base) or not release.SHA.fullmatch(current):
        raise ValueError("Missing validated Git revision")
    release.git("merge-base", "--is-ancestor", base, current)
    files = release.git("diff", "--name-only", "-z", base, current, "--", "taxocommerce-*")
    return any(release.installable(name) for name in files.decode().split("\0") if name)


def main():
    repo = os.environ.get("GITHUB_REPOSITORY", "")
    if "/" not in repo:
        raise ValueError("GitHub repository not identified")
    changed = changed_since_last_pro_release(repo, os.environ.get("GH_TOKEN", ""))
    print("has_changes=" + ("true" if changed else "false"))
    print("PRO source check: " + ("changes detected" if changed else "no deployable changes"),
          file=sys.stderr)


if __name__ == "__main__":
    try:
        main()
    except (ValueError, RuntimeError, OSError) as exc:
        print("::error::PRO preflight could not verify last successful release: "
              + type(exc).__name__, file=sys.stderr)
        sys.exit(1)
