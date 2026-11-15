#!/usr/bin/env python3
"""Deploy changed files of already installed TaxoCommerce plugins to both permitted WordPress sites.

Baseline: last successful staging workflow, so a failed run cannot lose its changes.
Deletion of runtime files is blocked until a separately reviewed migration.
"""
import argparse
import ftplib
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import time
import urllib.request

SLUG = re.compile(r"^taxocommerce-[a-z0-9-]+$")
SHA = re.compile(r"^[0-9a-f]{40}$")
EXCLUDE_PARTS = {"tests", "node_modules", "uploads", "__pycache__"}
EXCLUDE_SUFFIXES = (".bak", ".md", ".pot", ".log", ".pyc")

# Both WordPress installations are siblings beneath the FTP account's
# new /home/hosting160568eu/public_html root. Never deploy to the root itself.
SITE_FTP_ROOTS = {
    "staging": "staging.distribuidordeherramientas.es",
    "focazul": "asociacionfocazul",
}


def remote_plugin_dir(target, slug):
    if target not in SITE_FTP_ROOTS:
        raise ValueError(f"Unrecognized FTP destination: {target}")
    if not SLUG.fullmatch(slug):
        raise ValueError(f"Invalid plugin slug: {slug}")
    return "./" + SITE_FTP_ROOTS[target] + "/wp-content/plugins/" + slug + "/"



def git(*args):
    return subprocess.check_output(["git", *args])


def distributable(path):
    parts = Path(path).parts
    if len(parts) < 2 or not SLUG.fullmatch(parts[0]):
        return None
    relative = parts[1:]
    if any(part.startswith(".") or part in EXCLUDE_PARTS for part in relative):
        return None
    # Ship the root changelog used by the Tasks admin header.
    # Other Markdown/development documentation remains excluded.
    if relative[-1].lower().endswith(EXCLUDE_SUFFIXES) and relative != ("CHANGELOG.md",):
        return None
    return parts[0], "/".join(relative)


def changed_files(base, head, selected_slug=None):
    if not SHA.fullmatch(base) or not SHA.fullmatch(head):
        raise ValueError("Invalid baseline or HEAD commit")
    subprocess.run(["git", "merge-base", "--is-ancestor", base, head], check=True)
    values = git("diff", "--name-status", "-z", "--no-renames", base, head, "--").decode("utf-8").split("\0")
    changes = {}
    for status, path in zip(values[0::2], values[1::2]):
        parsed = distributable(path)
        if parsed is None:
            continue
        slug, relative = parsed
        if slug == "taxocommerce-connections":
            # Retired bridge has no deployable files, even for a deletion commit.
            continue
        if status.startswith("D"):
            changes.setdefault(slug, set())
            if selected_slug == slug:
                raise ValueError(f"Deletion requires a separately reviewed migration: {path}")
            continue
        if not status.startswith(("A", "C", "M", "T")):
            raise ValueError(f"Unexpected Git file status: {status} {path}")
        source = Path(path)
        if not source.is_file() or source.is_symlink():
            if selected_slug == slug:
                raise ValueError(f"Missing or unsafe source file: {path}")
            continue
        if not (Path(slug) / (slug + ".php")).is_file():
            if selected_slug == slug:
                raise ValueError(f"Not a valid plugin directory: {slug}")
            continue
        changes.setdefault(slug, set()).add(relative)
    return {slug: sorted(names) for slug, names in sorted(changes.items())}


def last_successful_base(repo, head, token):
    if not token:
        raise ValueError("GITHUB_TOKEN is required to find a verified baseline")
    url = f"https://api.github.com/repos/{repo}/actions/workflows/deploy-staging.yml/runs?branch=staging&status=success&per_page=100"
    request = urllib.request.Request(url, headers={
        "Authorization": "Bearer " + token,
        "Accept": "application/vnd.github+json",
        "User-Agent": "TaxoCommerce-Delta-Deploy/1.0",
    })
    with urllib.request.urlopen(request, timeout=25) as response:
        payload = json.load(response)
    for run in payload.get("workflow_runs", []):
        candidate = run.get("head_sha", "")
        if SHA.fullmatch(candidate) and subprocess.run(
            ["git", "merge-base", "--is-ancestor", candidate, head], check=False
        ).returncode == 0:
            return candidate
    raise ValueError("No verified successful STAGING baseline; refusing broad FTP synchronization")


def scoped_push_plugins():
    """Select only Core/Tasks files named in the trusted staging push event.

    A workflow change alone reconciles Core to repair previously skipped Core
    deliveries, without installing absent optional plugins such as Tasks.
    """
    event_path = os.environ.get("GITHUB_EVENT_PATH", "")
    if not event_path:
        raise ValueError("A direct scoped push requires GITHUB_EVENT_PATH")
    with open(event_path, encoding="utf-8") as source:
        event = json.load(source)
    if event.get("ref") != "refs/heads/staging":
        raise ValueError("Scoped deliveries are restricted to staging pushes")
    commits = event.get("commits", [])
    if not isinstance(commits, list) or not commits:
        raise ValueError("Scoped push contains no audited changed file list")
    paths = set()
    for commit in commits:
        for kind in ("added", "modified", "removed"):
            files = commit.get(kind, [])
            if not isinstance(files, list):
                raise ValueError("Invalid changed-file list in push event")
            paths.update(path for path in files if isinstance(path, str))
    slugs = [
        slug for slug in ("taxocommerce-core", "taxocommerce-tasks")
        if any(path.startswith(slug + "/") for path in paths)
    ]
    if not slugs and paths.intersection({
        ".github/workflows/deploy-staging.yml",
        ".github/scripts/deploy_staging_delta.py",
    }):
        slugs = ["taxocommerce-core"]
    return slugs



def remote_plugin_entrypoint_drift():
    """Find installed modules stale on either authorised WordPress installation.

    GitHub workflow success only proves the job passed, not that a partial
    direct-push job copied the other modules. Never inspect or write PRO.
    """
    host = os.environ["STAGING_FTP_SERVER"]
    user = os.environ["STAGING_FTP_USERNAME"]
    password = os.environ["STAGING_FTP_PASSWORD"]
    if not host or not user or not password:
        raise ValueError("STAGING/Focazul FTPS credentials required")
    candidates = sorted(
        d.name for d in Path(".").glob("taxocommerce-*")
        if d.is_dir() and SLUG.fullmatch(d.name)
        and d.name != "taxocommerce-connections"  # Retired bridge lives in Insights.
        and (d / (d.name + ".php")).is_file()
    )
    local_hashes = {
        slug: hashlib.sha256((Path(slug) / (slug + ".php")).read_bytes()).hexdigest()
        for slug in candidates
    }
    for attempt in range(3):
        ftp = ftplib.FTP_TLS(timeout=45)
        stale = set()
        try:
            ftp.connect(host, 21)
            ftp.login(user, password)
            ftp.prot_p()
            home = ftp.pwd()
            for target in ("staging", "focazul"):
                for slug in candidates:
                    ftp.cwd(home)
                    try:
                        ftp.cwd(remote_plugin_dir(target, slug))
                    except ftplib.error_perm as exc:
                        if str(exc).startswith("550") and slug != "taxocommerce-core":
                            continue  # Never create an absent optional plugin.
                        raise
                    actual = existing_file_hash(ftp, slug + ".php")
                    if actual is None:
                        if slug == "taxocommerce-core":
                            raise RuntimeError(f"{target}: required Core entrypoint is missing")
                        continue
                    if actual != local_hashes[slug]:
                        stale.add(slug)
                        print(f"STALE {target}/{slug}: remote entrypoint differs")
            ftp.quit()
            return sorted(stale)
        except (OSError, EOFError, ftplib.error_temp) as exc:
            if attempt == 2:
                raise
            print(f"::warning::FTPS drift inspection retry {attempt + 2}/3: {exc}")
            time.sleep(2 ** attempt)
        finally:
            try:
                ftp.close()
            except OSError:
                pass


def plan():
    head = git("rev-parse", "HEAD").decode().strip()
    base = last_successful_base(os.environ["GITHUB_REPOSITORY"], head, os.environ.get("GITHUB_TOKEN", ""))
    changes = changed_files(base, head)
    # An ordinary push must deploy only plugin runtime files changed in Git.
    # Updating a workflow, CI test or deployment script must NEVER initiate a
    # family-wide reconciliation without an explicit manual operator request.
    sync = os.environ.get("TAXOCOMMERCE_FULL_SYNC", "0") == "1"
    target = os.environ.get("TAXOCOMMERCE_TARGET_PLUGIN", "")
    # Remote entrypoint parity is independent from GitHub's success marker.
    drift = []
    if os.environ.get("TAXOCOMMERCE_REMOTE_VERIFY", "0") == "1" and not target and not sync:
        drift = remote_plugin_entrypoint_drift()
    if target:
        # Scoped pushes are limited to the two reviewed plugins. The uploader
        # compares SHA-256 and writes only changed files on authorized sites.
        if target not in ("taxocommerce-tasks", "taxocommerce-core", "auto") or sync:
            raise ValueError("Targeted push deployment permits only Core or Tasks")
        slugs = scoped_push_plugins() if target == "auto" else [target]
    elif sync:
        slugs = sorted(
            d.name for d in Path(".").glob("taxocommerce-*")
            if d.is_dir() and SLUG.fullmatch(d.name)
            and d.name != "taxocommerce-connections"
            and (d / (d.name + ".php")).is_file()
        )
    else:
        slugs = sorted(set(changes) | set(drift))
        if drift:
            # Reconcile all installable files for stale modules, but upload_plugin
            # transfers only files whose remote SHA-256 does not match.
            sync = True
            print(f"Recovering {len(drift)} remote module(s) skipped by partial deployment")
    core = "taxocommerce-core" in slugs
    with open(os.environ["GITHUB_OUTPUT"], "a", encoding="utf-8") as output:
        output.write(f"base={base}\n")
        output.write("slugs=" + json.dumps(slugs, separators=(",", ":")) + "\n")
        output.write("count=" + str(len(slugs)) + "\n")
        output.write("sync=" + str(sync).lower() + "\n")
        output.write("core=" + str(core).lower() + "\n")
    print(f"Verified baseline {base}, target {head}")
    for slug, files in changes.items():
        print(f"{slug}: {len(files)} changed distributable file(s)")
    if sync:
        print(f"SHA-256 synchronization: reconcile {len(slugs)} installed-plugin candidates on both sites")
    if not slugs:
        print("No plugin runtime files changed; no FTPS connection needed")


def existing_file_hash(ftp, name):
    digest = hashlib.sha256()
    try:
        ftp.retrbinary("RETR " + name, digest.update)
    except ftplib.error_perm as exc:
        if str(exc).startswith("550"):
            return None
        raise
    return digest.hexdigest()


def upload_plugin(ftp, target, slug, files, required):
    # Issues #2290, #2644 and #2651 authorize first installation only to their named sites.
    # Accounting is allowed solely on STAGING, never Focazul or PRO.
    new_plugin_install = False
    try:
        ftp.cwd(remote_plugin_dir(target, slug))
    except ftplib.error_perm as exc:
        if not str(exc).startswith("550"):
            raise
        if (slug in ("taxocommerce-tasks", "taxocommerce-customers") and target in ("staging", "focazul")) or (
            slug == "taxocommerce-accounting" and target == "staging"
        ):
            plugin_parent = remote_plugin_dir(target, slug).rsplit(slug + "/", 1)[0]
            ftp.cwd(plugin_parent)
            ftp.mkd(slug)
            ftp.cwd(slug)
            new_plugin_install = True
            issue = {"taxocommerce-accounting": "#2644", "taxocommerce-tasks": "#2290", "taxocommerce-customers": "#2651"}[slug]
            print(f"INSTALL {target}/{slug}: explicitly authorized by Issue {issue}")
        elif not required:
            print(f"SKIP {slug}: plugin directory absent on this WordPress")
            return
        else:
            raise RuntimeError(f"{slug}: installed plugin directory is inaccessible") from exc
    root = ftp.pwd()
    if new_plugin_install:
        # A new directory must receive the COMPLETE installable plugin, not
        # only the files changed since the last successful workflow baseline.
        files = plugin_source_files(slug)
        if slug + ".php" not in files:
            raise RuntimeError(f"{slug}: first-install entrypoint missing from tracked sources")
    # Preexisting plugins must have the entrypoint; never repair unknown installs.
    if not new_plugin_install and existing_file_hash(ftp, slug + ".php") is None:
        if not required:
            print(f"SKIP {slug}: plugin entrypoint absent on this WordPress")
            return
        raise RuntimeError(f"{slug}: expected installed plugin entrypoint is missing")
    for relative in files:
        source = Path(slug) / relative
        data_hash = hashlib.sha256(source.read_bytes()).hexdigest()
        ftp.cwd(root)
        parts = relative.split("/")
        for directory in parts[:-1]:
            try:
                ftp.cwd(directory)
            except ftplib.error_perm as exc:
                if not str(exc).startswith("550"):
                    raise
                ftp.mkd(directory)
                ftp.cwd(directory)
        filename = parts[-1]
        if existing_file_hash(ftp, filename) == data_hash:
            print(f"UNCHANGED {slug}/{relative}")
            continue
        tempname = ".taxocommerce-upload-" + hashlib.sha256(relative.encode()).hexdigest()[:16] + ".tmp"
        try:
            with source.open("rb") as stream:
                ftp.storbinary("STOR " + tempname, stream)
            ftp.rename(tempname, filename)
            if existing_file_hash(ftp, filename) != data_hash:
                raise RuntimeError(f"Remote SHA-256 verification failed: {slug}/{relative}")
            print(f"UPDATED {slug}/{relative} sha256:{data_hash[:12]}")
        finally:
            try:
                ftp.delete(tempname)
            except ftplib.all_errors:
                pass


def plugin_source_files(slug):
    """All tracked, installable files of a standalone plugin. Never delete."""
    if not SLUG.fullmatch(slug):
        raise ValueError("Invalid plugin slug")
    names = git("ls-files", "-z", "--", slug).decode("utf-8").split("\0")
    files = set()
    for name in names:
        parsed = distributable(name)
        if not parsed or parsed[0] != slug:
            continue
        source = Path(name)
        if not source.is_file() or source.is_symlink():
            raise ValueError(f"Missing or unsafe tracked plugin source: {name}")
        files.add(parsed[1])
    if slug + ".php" not in files:
        raise ValueError(f"Tracked plugin entrypoint missing: {slug}")
    return sorted(files)


def core_source_files():
    """All tracked, installable Core files from this one shared source tree.

    Only a checksum mismatch triggers an upload. This also heals an old
    installation missing a file that a previous incremental run did not touch.
    """
    names = git("ls-files", "-z", "--", "taxocommerce-core").decode("utf-8").split("\0")
    files = set()
    for name in names:
        parsed = distributable(name)
        if not parsed or parsed[0] != "taxocommerce-core":
            continue
        source = Path(name)
        if not source.is_file() or source.is_symlink():
            raise ValueError(f"Missing or unsafe tracked Core source: {name}")
        files.add(parsed[1])
    if "taxocommerce-core.php" not in files:
        raise ValueError("Tracked Core entrypoint missing; refusing cross-site reconciliation")
    return sorted(files)


def reconcile_core():
    """Enforce identical tracked Core bytes on STAGING and Focazul only.

    Both sites are sourced from this checkout, never copied from one live
    WordPress to another. The existing uploader skips identical files and
    atomically transfers only the differing bytes with SHA-256 readback.
    """
    files = core_source_files()
    server = os.environ["STAGING_FTP_SERVER"]
    user = os.environ["STAGING_FTP_USERNAME"]
    password = os.environ["STAGING_FTP_PASSWORD"]
    if not server or not user or not password:
        raise ValueError("Missing existing shared FTPS credentials")
    ftp = ftplib.FTP_TLS(timeout=50)
    try:
        ftp.connect(server, 21)
        ftp.login(user, password)
        ftp.prot_p()
        home = ftp.pwd()
        for target in ("staging", "focazul"):
            ftp.cwd(home)
            upload_plugin(ftp, target, "taxocommerce-core", files, required=True)
            print(f"{target}: all {len(files)} tracked installable Core files match the same Git commit")
        ftp.quit()
    finally:
        try:
            ftp.close()
        except OSError:
            pass


def selected_deploy_files(base, head, slug, full=False):
    """Select Core or Tasks for a verified scoped dual-site reconciliation.

    The normal gated family workflow remains incremental. Scoped deployments
    compare every tracked installable file against its remote SHA-256 because
    successful validation-only runs do not imply file delivery.
    """
    target = os.environ.get("TAXOCOMMERCE_TARGET_PLUGIN", "")
    if target:
        if target == "auto":
            if slug not in ("taxocommerce-tasks", "taxocommerce-core") or full:
                raise ValueError("Scoped reconciliation is restricted to Core or Tasks")
        elif target not in ("taxocommerce-tasks", "taxocommerce-core") or slug != target or full:
            raise ValueError("Targeted reconciliation is restricted to Core or Tasks")
        return plugin_source_files(slug)
    if full:
        return plugin_source_files(slug)
    changes = changed_files(base, head, selected_slug=slug)
    if slug not in changes:
        raise ValueError(f"No distributable changes selected for {slug}")
    return changes[slug]


def deploy(base, target, slug, full=False):
    head = git("rev-parse", "HEAD").decode().strip()
    files = selected_deploy_files(base, head, slug, full)
    # One FTPS login shared by both clients; each site has an isolated path.
    # Validate the destination before connecting to the server.
    remote_plugin_dir(target, slug)
    env_prefix = "STAGING"
    host = os.environ[env_prefix + "_FTP_SERVER"]
    user = os.environ[env_prefix + "_FTP_USERNAME"]
    password = os.environ[env_prefix + "_FTP_PASSWORD"]
    if not host or not user or not password:
        raise ValueError("Missing " + env_prefix + " FTPS credentials")
    for attempt in range(3):
        ftp = ftplib.FTP_TLS(timeout=60)
        phase = "connect"
        try:
            ftp.connect(host, 21)
            phase = "authentication"
            ftp.login(user, password)
            phase = "secure data channel"
            ftp.prot_p()
            phase = "upload/checksum"
            upload_plugin(ftp, target, slug, files, required=(slug == "taxocommerce-core"))
            phase = "close"
            ftp.quit()
            return
        except (OSError, EOFError, ftplib.error_temp) as exc:
            # Only retry transient transport errors; never print credentials.
            if attempt == 2:
                raise RuntimeError(
                    f"{target}/{slug}: FTPS {phase} failed after 3 attempts: {exc}"
                ) from exc
            print(
                f"::warning::{target}/{slug}: FTPS {phase} retry {attempt + 2}/3: {exc}",
                flush=True,
            )
            time.sleep(2 ** attempt)
        finally:
            try:
                ftp.close()
            except OSError:
                pass


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("command", choices=["plan", "deploy", "reconcile-core"])
    parser.add_argument("--base")
    parser.add_argument("--target", choices=["staging", "focazul"])
    parser.add_argument("--slug")
    parser.add_argument("--full", choices=("true", "false"), default="false")
    args = parser.parse_args()
    if args.command == "plan":
        plan()
    elif args.command == "reconcile-core":
        reconcile_core()
    elif not args.base or not args.target or not args.slug:
        parser.error("deploy requires --base, --target and --slug")
    else:
        deploy(args.base, args.target, args.slug, full=args.full == "true")


if __name__ == "__main__":
    try:
        main()
    except (ValueError, RuntimeError, OSError, subprocess.CalledProcessError, *ftplib.all_errors) as exc:
        print("::error::Staging delta deployment failed: " + str(exc), file=sys.stderr)
        sys.exit(1)
