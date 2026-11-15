import importlib.util
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest import mock

SCRIPT = Path(__file__).resolve().parents[1] / "scripts" / "deploy_staging_delta.py"
spec = importlib.util.spec_from_file_location("deploy_staging_delta", SCRIPT)
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class DeltaTests(unittest.TestCase):
    def test_distributable_whitelist(self):
        self.assertEqual(module.distributable("taxocommerce-core/includes/example.php"), ("taxocommerce-core", "includes/example.php"))
        self.assertEqual(
            module.distributable("taxocommerce-tasks/CHANGELOG.md"),
            ("taxocommerce-tasks", "CHANGELOG.md"),
        )
        self.assertEqual(
            module.distributable("taxocommerce-core/CHANGELOG.md"),
            ("taxocommerce-core", "CHANGELOG.md"),
        )
        for path in ("taxocommerce-core/tests/smoke.php", "taxocommerce-core/docs/CHANGELOG.md",
                     "taxocommerce-core/README.md", "taxocommerce-tasks/docs/internal.md",
                     "taxocommerce-core/.secrets", "taxocommerce-core/languages/.hidden.po",
                     "taxocommerce-core/uploads/payload.txt", "random/file.php"):
            self.assertIsNone(module.distributable(path), path)

    def test_shared_ftp_site_roots_are_isolated(self):
        self.assertEqual(
            module.remote_plugin_dir("staging", "taxocommerce-core"),
            "./staging.distribuidordeherramientas.es/wp-content/plugins/taxocommerce-core/",
        )
        self.assertEqual(
            module.remote_plugin_dir("focazul", "taxocommerce-core"),
            "./asociacionfocazul/wp-content/plugins/taxocommerce-core/",
        )
        self.assertEqual(
            module.remote_plugin_dir("staging", "taxocommerce-vocabulary"),
            "./staging.distribuidordeherramientas.es/wp-content/plugins/taxocommerce-vocabulary/",
        )
        self.assertEqual(
            module.remote_plugin_dir("focazul", "taxocommerce-templates"),
            "./asociacionfocazul/wp-content/plugins/taxocommerce-templates/",
        )
        for target, slug in (
            ("production", "taxocommerce-core"),
            ("staging", "../wp-config.php"),
        ):
            with self.assertRaises(ValueError):
                module.remote_plugin_dir(target, slug)

    def test_full_reconciliation_selects_existing_module_sources(self):
        with tempfile.TemporaryDirectory() as root:
            import os
            old = os.getcwd()
            os.chdir(root)
            try:
                Path("taxocommerce-templates/includes").mkdir(parents=True)
                Path("taxocommerce-templates/tests").mkdir()
                Path("taxocommerce-templates/taxocommerce-templates.php").write_text("<?php")
                Path("taxocommerce-templates/includes/class-tct-manual.php").write_text("<?php")
                Path("taxocommerce-templates/tests/smoke.php").write_text("<?php")
                with mock.patch.object(module, "git", return_value=(
                    b"taxocommerce-templates/taxocommerce-templates.php\\0"
                    b"taxocommerce-templates/includes/class-tct-manual.php\\0"
                    b"taxocommerce-templates/tests/smoke.php\\0"
                ).replace(b"\\0", b"\x00")):
                    self.assertEqual(
                        module.plugin_source_files("taxocommerce-templates"),
                        ["includes/class-tct-manual.php", "taxocommerce-templates.php"],
                    )
            finally:
                os.chdir(old)

    def test_optional_focazul_plugin_absent_is_not_created(self):
        ftp = mock.Mock()
        ftp.cwd.side_effect = module.ftplib.error_perm("550 no such directory")
        module.upload_plugin(ftp, "focazul", "taxocommerce-templates", ["taxocommerce-templates.php"], required=False)
        ftp.storbinary.assert_not_called()


    def test_customers_first_install_is_limited_to_staging_and_focazul_and_complete(self):
        import os
        with tempfile.TemporaryDirectory() as temp:
            old = os.getcwd()
            os.chdir(temp)
            try:
                directory = Path("taxocommerce-customers")
                directory.mkdir()
                (directory / "taxocommerce-customers.php").write_text("<?php")
                (directory / "readme.txt").write_text("Customers plugin")
                for target in ("staging", "focazul"):
                    ftp = mock.Mock()
                    ftp.pwd.return_value = "/site/wp-content/plugins/taxocommerce-customers"
                    calls = [0]
                    def cwd(path):
                        calls[0] += 1
                        if calls[0] == 1:
                            raise module.ftplib.error_perm("550 absent")
                    ftp.cwd.side_effect = cwd
                    import hashlib
                    digests = {
                        "taxocommerce-customers.php": hashlib.sha256(b"<?php").hexdigest(),
                        "readme.txt": hashlib.sha256(b"Customers plugin").hexdigest(),
                    }
                    probes = {}
                    def remote_hash(ftp_instance, filename):
                        probes[filename] = probes.get(filename, 0) + 1
                        return None if probes[filename] == 1 else digests[filename]
                    with mock.patch.object(module, "plugin_source_files", return_value=[
                        "taxocommerce-customers.php", "readme.txt"
                    ]) as manifest:
                        with mock.patch.object(module, "existing_file_hash", side_effect=remote_hash):
                            module.upload_plugin(ftp, target, "taxocommerce-customers", ["readme.txt"], required=False)
                    ftp.mkd.assert_called_once_with("taxocommerce-customers")
                    manifest.assert_called_once_with("taxocommerce-customers")
                    self.assertEqual(ftp.storbinary.call_count, 2)
                with self.assertRaises(ValueError):
                    module.remote_plugin_dir("production", "taxocommerce-customers")
            finally:
                os.chdir(old)

    def test_upload_core_checks_correct_site_without_writing_other_site(self):
        ftp = mock.Mock()
        ftp.pwd.return_value = "/asociacionfocazul/wp-content/plugins/taxocommerce-core"
        with mock.patch.object(module, "existing_file_hash", return_value="present"):
            module.upload_plugin(ftp, "focazul", "taxocommerce-core", [], required=True)
        ftp.cwd.assert_called_once_with(
            "./asociacionfocazul/wp-content/plugins/taxocommerce-core/"
        )
        ftp.storbinary.assert_not_called()

    def test_reconcile_core_uses_one_source_tree_for_both_sites(self):
        ftp = mock.Mock()
        ftp.pwd.return_value = "/"
        files = ["taxocommerce-core.php", "includes/menu.php", "languages/core.mo"]
        with mock.patch.dict("os.environ", {
            "STAGING_FTP_SERVER": "ftps.example.test",
            "STAGING_FTP_USERNAME": "test-user",
            "STAGING_FTP_PASSWORD": "test-password",
        }):
            with mock.patch.object(module, "core_source_files", return_value=files):
                with mock.patch.object(module.ftplib, "FTP_TLS", return_value=ftp):
                    with mock.patch.object(module, "upload_plugin") as upload:
                        module.reconcile_core()
        self.assertEqual([x.args[1:] for x in upload.call_args_list], [
            ("staging", "taxocommerce-core", files),
            ("focazul", "taxocommerce-core", files),
        ])
        self.assertTrue(all(x.kwargs["required"] for x in upload.call_args_list))
        self.assertEqual(ftp.cwd.call_args_list, [mock.call("/"), mock.call("/")])
        ftp.prot_p.assert_called_once()

    def test_core_source_manifest_excludes_tests_and_hidden_files(self):
        with tempfile.TemporaryDirectory() as root:
            import os
            old = os.getcwd()
            os.chdir(root)
            try:
                Path("taxocommerce-core/includes").mkdir(parents=True)
                Path("taxocommerce-core/tests").mkdir()
                Path("taxocommerce-core/taxocommerce-core.php").write_text("<?php")
                Path("taxocommerce-core/includes/menu.php").write_text("<?php")
                Path("taxocommerce-core/CHANGELOG.md").write_text("# note")
                Path("taxocommerce-core/tests/test.php").write_text("<?php")
                with mock.patch.object(module, "git", return_value=(
                    b"taxocommerce-core/taxocommerce-core.php\\0"
                    b"taxocommerce-core/includes/menu.php\\0"
                    b"taxocommerce-core/tests/test.php\\0"
                    b"taxocommerce-core/CHANGELOG.md\\0"
                ).replace(b"\\0", b"\x00")):
                    self.assertEqual(module.core_source_files(), [
                        "CHANGELOG.md", "includes/menu.php", "taxocommerce-core.php"
                    ])
            finally:
                os.chdir(old)

    def test_remote_stale_versions_cannot_be_hidden_by_successful_git_baseline(self):
        import json
        import os
        with tempfile.TemporaryDirectory() as root:
            output = Path(root) / "outputs"
            with mock.patch.dict(os.environ, {
                "GITHUB_REPOSITORY": "shop/test",
                "GITHUB_TOKEN": "fake",
                "GITHUB_OUTPUT": str(output),
                "TAXOCOMMERCE_FULL_SYNC": "0",
                "TAXOCOMMERCE_REMOTE_VERIFY": "1",
                "TAXOCOMMERCE_TARGET_PLUGIN": "",
            }):
                with mock.patch.object(module, "git", return_value=b"a" * 40):
                    with mock.patch.object(module, "last_successful_base", return_value="a" * 40):
                        with mock.patch.object(module, "changed_files", return_value={}):
                            with mock.patch.object(module, "remote_plugin_entrypoint_drift",
                                                   return_value=["taxocommerce-auditor", "taxocommerce-vocabulary"]):
                                module.plan()
            data = dict(line.split("=", 1) for line in output.read_text().splitlines())
            self.assertEqual(json.loads(data["slugs"]), ["taxocommerce-auditor", "taxocommerce-vocabulary"])
            self.assertEqual(data["count"], "2")
            self.assertEqual(data["sync"], "true")
            self.assertEqual(data["core"], "false")

    def test_remote_scan_never_probes_pro_or_creates_missing_plugins(self):
        import hashlib
        import os
        with tempfile.TemporaryDirectory() as root:
            original = os.getcwd()
            os.chdir(root)
            try:
                for slug in ("taxocommerce-core", "taxocommerce-vocabulary", "taxocommerce-connections"):
                    Path(slug).mkdir()
                    (Path(slug) / (slug + ".php")).write_bytes(slug.encode())
                ftp = mock.Mock()
                ftp.pwd.return_value = "/ftp-home"
                observed = []
                def enter_directory(path):
                    observed.append(path)
                    if "asociacionfocazul" in path and "taxocommerce-vocabulary" in path:
                        raise module.ftplib.error_perm("550 plugin absent")
                ftp.cwd.side_effect = enter_directory
                def remote_hash(ftp_arg, name):
                    if name == "taxocommerce-vocabulary.php":
                        return "stale"
                    slug = name[:-4]
                    return hashlib.sha256((Path(slug) / name).read_bytes()).hexdigest()

                with mock.patch.dict(os.environ, {
                    "STAGING_FTP_SERVER": "example.test",
                    "STAGING_FTP_USERNAME": "test",
                    "STAGING_FTP_PASSWORD": "fake",
                }):
                    with mock.patch.object(module.ftplib, "FTP_TLS", return_value=ftp):
                        with mock.patch.object(module, "existing_file_hash", side_effect=remote_hash):
                            result = module.remote_plugin_entrypoint_drift()
                self.assertEqual(result, ["taxocommerce-vocabulary"])
                self.assertFalse(any("production" in path for path in observed))
                self.assertFalse(any("taxocommerce-connections" in path for path in observed))
                ftp.storbinary.assert_not_called()
                ftp.mkd.assert_not_called()
            finally:
                os.chdir(original)

    def test_targeted_push_deploys_only_tasks_and_never_other_plugins(self):
        import json
        import os
        with tempfile.TemporaryDirectory() as root:
            output = Path(root) / "output"
            with mock.patch.dict(os.environ, {
                "GITHUB_REPOSITORY": "shop/test",
                "GITHUB_TOKEN": "fake",
                "GITHUB_OUTPUT": str(output),
                "TAXOCOMMERCE_FULL_SYNC": "0",
                "TAXOCOMMERCE_TARGET_PLUGIN": "taxocommerce-tasks",
            }):
                with mock.patch.object(module, "git", return_value=b"a" * 40):
                    with mock.patch.object(module, "last_successful_base", return_value="b" * 40):
                        with mock.patch.object(module, "changed_files", return_value={
                            "taxocommerce-tasks": ["taxocommerce-tasks.php"],
                            "taxocommerce-core": ["taxocommerce-core.php"],
                            "taxocommerce-templates": ["taxocommerce-templates.php"],
                        }):
                            module.plan()
            data = dict(line.split("=", 1) for line in output.read_text().strip().splitlines())
            self.assertEqual(json.loads(data["slugs"]), ["taxocommerce-tasks"])
            self.assertEqual(data["count"], "1")
            self.assertEqual(data["sync"], "false")
            self.assertEqual(data["core"], "false")

    def test_targeted_push_cannot_skip_tasks_when_git_baseline_equals_head(self):
        import json
        import os

        with tempfile.TemporaryDirectory() as directory:
            output = Path(directory) / "output"
            with mock.patch.dict(os.environ, {
                "GITHUB_REPOSITORY": "shop/test",
                "GITHUB_TOKEN": "fake",
                "GITHUB_OUTPUT": str(output),
                "TAXOCOMMERCE_FULL_SYNC": "0",
                "TAXOCOMMERCE_TARGET_PLUGIN": "taxocommerce-tasks",
            }):
                with mock.patch.object(module, "git", return_value=b"a" * 40):
                    with mock.patch.object(module, "last_successful_base", return_value="a" * 40):
                        with mock.patch.object(module, "changed_files", return_value={}):
                            module.plan()
            result = dict(row.split("=", 1) for row in output.read_text().splitlines())
            self.assertEqual(json.loads(result["slugs"]), ["taxocommerce-tasks"])
            self.assertEqual(result["count"], "1")
            self.assertEqual(result["sync"], "false")
            self.assertEqual(result["core"], "false")

    def test_targeted_delivery_checks_all_tracked_tasks_files_and_only_tasks(self):
        import os

        files = [
            "taxocommerce-tasks.php",
            "includes/class-tctasks-manuals.php",
            "languages/taxocommerce-tasks-es_ES.po",
            "languages/taxocommerce-tasks-es_ES.mo",
        ]
        with mock.patch.dict(os.environ, {"TAXOCOMMERCE_TARGET_PLUGIN": "taxocommerce-tasks"}):
            with mock.patch.object(module, "plugin_source_files", return_value=files) as manifest:
                with mock.patch.object(module, "changed_files", side_effect=AssertionError("git baseline must not be used")):
                    self.assertEqual(
                        module.selected_deploy_files("a" * 40, "a" * 40, "taxocommerce-tasks"),
                        files,
                    )
            manifest.assert_called_once_with("taxocommerce-tasks")
            with self.assertRaises(ValueError):
                module.selected_deploy_files("a" * 40, "b" * 40, "taxocommerce-core")
            with self.assertRaises(ValueError):
                module.selected_deploy_files("a" * 40, "b" * 40, "taxocommerce-tasks", full=True)

    def test_scoped_core_delivery_isolated_even_when_git_baseline_equals_head(self):
        import json
        import os

        with tempfile.TemporaryDirectory() as directory:
            output = Path(directory) / "output"
            with mock.patch.dict(os.environ, {
                "GITHUB_REPOSITORY": "shop/test",
                "GITHUB_TOKEN": "fake",
                "GITHUB_OUTPUT": str(output),
                "TAXOCOMMERCE_FULL_SYNC": "0",
                "TAXOCOMMERCE_TARGET_PLUGIN": "taxocommerce-core",
            }):
                with mock.patch.object(module, "git", return_value=b"a" * 40):
                    with mock.patch.object(module, "last_successful_base", return_value="a" * 40):
                        with mock.patch.object(module, "changed_files", return_value={}):
                            module.plan()
            data = dict(line.split("=", 1) for line in output.read_text().splitlines())
            self.assertEqual(json.loads(data["slugs"]), ["taxocommerce-core"])
            self.assertEqual(data["core"], "true")
            self.assertEqual(data["count"], "1")
            self.assertEqual(data["sync"], "false")

        files = [
            "taxocommerce-core.php",
            "includes/class-taxocommerce-core.php",
            "languages/taxocommerce-core-es_ES.mo",
        ]
        with mock.patch.dict(os.environ, {"TAXOCOMMERCE_TARGET_PLUGIN": "taxocommerce-core"}):
            with mock.patch.object(module, "plugin_source_files", return_value=files) as source:
                with mock.patch.object(module, "changed_files", side_effect=AssertionError("Git baseline not allowed")):
                    self.assertEqual(
                        module.selected_deploy_files("a" * 40, "a" * 40, "taxocommerce-core"),
                        files,
                    )
            source.assert_called_once_with("taxocommerce-core")
            with self.assertRaises(ValueError):
                module.selected_deploy_files("a" * 40, "b" * 40, "taxocommerce-core", full=True)
            with self.assertRaises(ValueError):
                module.selected_deploy_files("a" * 40, "b" * 40, "taxocommerce-suppliers")

    def test_gated_pipeline_owns_all_automatic_staging_pushes(self):
        workflow = (Path(__file__).resolve().parents[2] / ".github/workflows/deploy-staging.yml").read_text()
        self.assertNotIn("  push:", workflow)
        self.assertIn("TAXOCOMMERCE_REMOTE_VERIFY: '1'", workflow)
        self.assertIn("target_plugin:", workflow)
        self.assertFalse((Path(__file__).resolve().parents[2] / ".github/workflows/deploy-core-staging.yml").exists())

    def test_scoped_push_uses_actual_event_changes_only(self):
        import json
        import os
        import tempfile

        with tempfile.TemporaryDirectory() as directory:
            event_path = Path(directory) / "event.json"
            examples = [
                (["taxocommerce-core/includes/class-taxocommerce-core.php"], ["taxocommerce-core"]),
                (["taxocommerce-tasks/taxocommerce-tasks.php"], ["taxocommerce-tasks"]),
                (["taxocommerce-core/taxocommerce-core.php", "taxocommerce-tasks/readme.txt"], ["taxocommerce-core", "taxocommerce-tasks"]),
                ([".github/workflows/deploy-staging.yml"], ["taxocommerce-core"]),
                (["taxocommerce-templates/taxocommerce-templates.php"], []),
            ]
            for paths, expected in examples:
                event_path.write_text(json.dumps({
                    "ref": "refs/heads/staging",
                    "commits": [{"added": paths, "modified": [], "removed": []}],
                }))
                with mock.patch.dict(os.environ, {"GITHUB_EVENT_PATH": str(event_path)}):
                    self.assertEqual(module.scoped_push_plugins(), expected)
            event_path.write_text(json.dumps({
                "ref": "refs/heads/main",
                "commits": [{"added": ["taxocommerce-core/taxocommerce-core.php"]}],
            }))
            with mock.patch.dict(os.environ, {"GITHUB_EVENT_PATH": str(event_path)}):
                with self.assertRaises(ValueError):
                    module.scoped_push_plugins()

    def test_scoped_push_reconciles_core_when_baseline_already_equals_head(self):
        import json
        import os
        import tempfile

        with tempfile.TemporaryDirectory() as directory:
            event = Path(directory) / "event.json"
            output = Path(directory) / "output"
            event.write_text(json.dumps({
                "ref": "refs/heads/staging",
                "commits": [{"modified": [".github/scripts/deploy_staging_delta.py"]}],
            }))
            with mock.patch.dict(os.environ, {
                "GITHUB_EVENT_PATH": str(event),
                "GITHUB_REPOSITORY": "shop/test",
                "GITHUB_TOKEN": "fake",
                "GITHUB_OUTPUT": str(output),
                "TAXOCOMMERCE_FULL_SYNC": "0",
                "TAXOCOMMERCE_TARGET_PLUGIN": "auto",
            }):
                with mock.patch.object(module, "git", return_value=b"a" * 40):
                    with mock.patch.object(module, "last_successful_base", return_value="a" * 40):
                        with mock.patch.object(module, "changed_files", return_value={}):
                            module.plan()
            recorded = dict(line.split("=", 1) for line in output.read_text().splitlines())
            self.assertEqual(json.loads(recorded["slugs"]), ["taxocommerce-core"])
            self.assertEqual(recorded["core"], "true")
            self.assertEqual(recorded["count"], "1")

    def test_targeted_push_rejects_other_plugins_and_manual_full_sync(self):
        import os
        with tempfile.TemporaryDirectory() as root:
            env = {
                "GITHUB_REPOSITORY": "shop/test",
                "GITHUB_TOKEN": "fake",
                "GITHUB_OUTPUT": str(Path(root) / "out"),
            }
            with mock.patch.object(module, "git", return_value=b"a" * 40):
                with mock.patch.object(module, "last_successful_base", return_value="b" * 40):
                    with mock.patch.object(module, "changed_files", return_value={
                        "taxocommerce-tasks": ["taxocommerce-tasks.php"]
                    }):
                        with mock.patch.dict(os.environ, dict(env,
                            TAXOCOMMERCE_TARGET_PLUGIN="taxocommerce-templates",
                            TAXOCOMMERCE_FULL_SYNC="0")):
                            with self.assertRaises(ValueError):
                                module.plan()
                        with mock.patch.dict(os.environ, dict(env,
                            TAXOCOMMERCE_TARGET_PLUGIN="taxocommerce-tasks",
                            TAXOCOMMERCE_FULL_SYNC="1")):
                            with self.assertRaises(ValueError):
                                module.plan()

    def test_workflow_updates_do_not_trigger_unrequested_full_sync(self):
        import json
        import os
        with tempfile.TemporaryDirectory() as root:
            output = Path(root) / "output"
            with mock.patch.dict(os.environ, {
                "GITHUB_REPOSITORY": "shop/test",
                "GITHUB_TOKEN": "fake",
                "GITHUB_OUTPUT": str(output),
                "TAXOCOMMERCE_FULL_SYNC": "0",
            }):
                with mock.patch.object(module, "git", return_value=b"a" * 40):
                    with mock.patch.object(module, "last_successful_base", return_value="b" * 40):
                        with mock.patch.object(module, "changed_files",
                                               return_value={"taxocommerce-templates": ["taxocommerce-templates.php"]}):
                            module.plan()
            data = dict(line.split("=", 1) for line in output.read_text().strip().splitlines())
            self.assertEqual(json.loads(data["slugs"]), ["taxocommerce-templates"])
            self.assertEqual(data["sync"], "false")
            self.assertEqual(data["core"], "false")

    def test_manual_full_sync_requires_explicit_opt_in(self):
        import json
        import os
        with tempfile.TemporaryDirectory() as root:
            output = Path(root) / "output"
            original = os.getcwd()
            os.chdir(root)
            try:
                Path("taxocommerce-templates").mkdir()
                Path("taxocommerce-templates/taxocommerce-templates.php").write_text("<?php")
                Path("taxocommerce-core").mkdir()
                Path("taxocommerce-core/taxocommerce-core.php").write_text("<?php")
                with mock.patch.dict(os.environ, {
                    "GITHUB_REPOSITORY": "shop/test",
                    "GITHUB_TOKEN": "fake",
                    "GITHUB_OUTPUT": str(output),
                    "TAXOCOMMERCE_FULL_SYNC": "1",
                }):
                    with mock.patch.object(module, "git", return_value=b"a" * 40):
                        with mock.patch.object(module, "last_successful_base", return_value="b" * 40):
                            with mock.patch.object(module, "changed_files", return_value={}):
                                module.plan()
                data = dict(line.split("=", 1) for line in output.read_text().strip().splitlines())
                self.assertEqual(json.loads(data["slugs"]), ["taxocommerce-core", "taxocommerce-templates"])
                self.assertEqual(data["sync"], "true")
            finally:
                os.chdir(original)

    def test_changes_include_only_real_changed_runtime_files(self):
        with tempfile.TemporaryDirectory() as temp:
            import os
            original = os.getcwd()
            os.chdir(temp)
            try:
                def git(*args):
                    subprocess.run(["git", *args], check=True, capture_output=True)
                git("init", "-q")
                git("config", "user.name", "Test")
                git("config", "user.email", "test@example.com")
                for slug in ("taxocommerce-core", "taxocommerce-vocabulary"):
                    Path(slug).mkdir()
                    (Path(slug) / (slug + ".php")).write_text("<?php\n")
                git("add", ".")
                git("commit", "-qm", "initial")
                base = subprocess.check_output(["git", "rev-parse", "HEAD"], text=True).strip()
                (Path("taxocommerce-core") / "taxocommerce-core.php").write_text("<?php // updated\n")
                (Path("taxocommerce-core") / "tests").mkdir()
                (Path("taxocommerce-core") / "tests" / "smoke.php").write_text("<?php\n")
                git("add", ".")
                git("commit", "-qm", "core update")
                head = subprocess.check_output(["git", "rev-parse", "HEAD"], text=True).strip()
                self.assertEqual(module.changed_files(base, head), {"taxocommerce-core": ["taxocommerce-core.php"]})
                (Path("taxocommerce-core") / "taxocommerce-core.php").unlink()
                git("add", ".")
                git("commit", "-qm", "delete core")
                deleted_head = subprocess.check_output(["git", "rev-parse", "HEAD"], text=True).strip()
                self.assertIn("taxocommerce-core", module.changed_files(head, deleted_head))
                with self.assertRaises(ValueError):
                    module.changed_files(head, deleted_head, selected_slug="taxocommerce-core")
            finally:
                os.chdir(original)


if __name__ == "__main__":
    unittest.main()
