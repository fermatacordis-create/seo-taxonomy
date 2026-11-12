"""Regression tests for restored STAGING -> PRO modular ZIP/REST workflow.

This is a read-only test: it never connects to FTP or writes to WordPress.
Historical reference: successful run 41243555528, source 03cb678b.
The legacy monolith sync and obsolete-file deletion steps are deliberately
not restored. The installed REST receiver is only used for the ZIP handoff.
"""
from pathlib import Path
import re
import unittest


ROOT = Path(__file__).resolve().parents[2]
WORKFLOW = ROOT / ".github/workflows/despliege_de_main_a_pro.yml"
INVENTORY = ROOT / ".github/scripts/fetch_pro_installed_modules.py"
MODULE = re.compile(r"^taxocommerce-[a-z0-9-]+$")


class HistoricModularDeployTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.workflow = WORKFLOW.read_text(encoding="utf-8")
        cls.inventory = INVENTORY.read_text(encoding="utf-8")

    def test_manual_staging_source_and_installed_wordpress_inventory(self):
        text = self.workflow
        self.assertIn("workflow_dispatch:", text)
        self.assertIn("ref: staging", text)
        self.assertIn('test "$GITHUB_REF_NAME" = "staging"', text)
        self.assertIn("PRO_SITE_URL: https://www.distribuidordeherramientas.es", text)
        self.assertIn(".github/scripts/fetch_pro_installed_modules.py", text)
        self.assertIn("INSTALLED_MODULES_JSON: ${{ steps.installed.outputs.modules }}", text)
        self.assertIn("ALL_MODULES_JSON: ${{ steps.family.outputs.modules }}", text)
        self.assertIn("seo_taxocommerce_installed_deploy_modules", (
            ROOT / "includes/seo-deploy-status.php").read_text(encoding="utf-8"))
        self.assertIn("get_plugins()", (ROOT / "includes/seo-deploy-status.php").read_text(encoding="utf-8"))

    def test_no_monolith_sync_no_repo_sync_no_deletion(self):
        text = self.workflow
        for forbidden in (
            "Subir TaxoCommerce SEO antiguo",
            "Subir SEO Taxonomy a producción",
            "FTP-Deploy-Action",
            "local-dir: ./",
            "server-dir: ./",
            ".github/scripts/deploy_pro_all.py",
            ".github/scripts/reconcile_taxocommerce_pro.py",
            "TC_APPROVED_CLEANUP_SHA256",
            "ftp.cwd",
            "PRO_FTP_PLUGINS_DIR",
            "wp-content/plugins/seo-taxonomy",
        ):
            self.assertNotIn(forbidden, text)
        self.assertNotIn("rm -rf \"includes/", text)
        self.assertIn('handoff=".taxocommerce-deploy/$GITHUB_RUN_ID"', text)
        self.assertIn("put \"$package\" -o \"$handoff/$module.zip\"", text)
        self.assertIn('rm -r "$handoff"', text)

    def test_only_existing_modules_and_sha256_verified_by_wordpress(self):
        text = self.workflow
        self.assertIn("find . -maxdepth 1 -mindepth 1 -type d -name 'taxocommerce-*'", text)
        self.assertIn('test -f "$module/$module.php"', text)
        self.assertIn('for module in "${selected_modules[@]}"; do', text)
        self.assertIn('zip -q "$package" "${changed_files[@]}"', text)
        self.assertIn("unzip -t \"$package\"", text)
        self.assertIn("unzip -Z1 \"$package\"", text)
        self.assertIn("manifest_sha256", text)
        self.assertIn("verified_files", text)
        self.assertIn("wp-json/taxocommerce/v1/deploy-active-modules", text)
        self.assertIn("readme.txt", text)
        self.assertIn("zipfile.ZipFile(package)", text)
        self.assertIn("trap cleanup_handoff EXIT", text)

    def test_only_deployable_changed_files_are_transferred(self):
        text = self.workflow
        selector = (ROOT / '.github/scripts/select_pro_release_delta.py').read_text(encoding='utf-8')
        self.assertIn('select_pro_release_delta.py', text)
        self.assertIn('fetch-depth: 0', text)
        self.assertIn("selected_count != '0'", text)
        self.assertIn('for module in "${selected_modules[@]}"; do', text)
        self.assertIn('zip -q "$package" "${changed_files[@]}"', text)
        self.assertIn('modules=$SELECTED_MODULES_JSON', text)
        self.assertIn('if set(updated_by_slug) != set(selected):', text)
        self.assertIn('manifest_sha256', text)
        self.assertIn('verified_files', text)
        self.assertIn('last_successful_source', selector)
        self.assertNotIn('actions/jobs/', selector)
        self.assertIn('branch=staging&status=success', selector)
        self.assertIn('merge-base', selector)
        for excluded in ('tests', 'uploads', 'node_modules', '.bak', '.log', '.pot'):
            self.assertIn(excluded, selector)

    def test_readme_and_changelog_remain_on_disk_but_only_changed_files_are_packed(self):
        text = self.workflow
        self.assertIn('test -f "$module/readme.txt"', text)
        self.assertNotIn('zip -qr "$package" .', text)
        self.assertNotIn('for module in "${installed_modules[@]}"; do', text)
        self.assertIn("DELTA_FILE: ${{ runner.temp }}/taxocommerce-pro-changed.json", text)
        modules = sorted(d for d in ROOT.glob('taxocommerce-*')
                         if d.is_dir() and MODULE.fullmatch(d.name))
        for module in modules:
            self.assertTrue((module / 'CHANGELOG.md').is_file(), module.name)
    def test_every_source_module_has_version_and_stable_tag(self):
        modules = sorted(
            d for d in ROOT.glob("taxocommerce-*")
            if d.is_dir() and MODULE.fullmatch(d.name)
        )
        errors = []
        for module in modules:
            main = (module / (module.name + ".php")).read_text(
                encoding="utf-8", errors="replace"
            )
            readme = (module / "readme.txt").read_text(
                encoding="utf-8", errors="replace"
            )
            version = re.search(r"(?mi)^\s*\*\s*Version:\s*(\S+)", main)
            stable = re.search(r"(?mi)^Stable tag:\s*(\S+)", readme)
            if not version or not stable:
                errors.append(module.name + ": missing Version or Stable tag")
        self.assertEqual(errors, [])

    def test_does_not_scan_every_plugin_or_deploy_when_nothing_changed(self):
        workflow = self.workflow
        self.assertNotIn('Validate all TaxoCommerce version headers and readmes', workflow)
        self.assertIn('if: steps.delta.outputs.selected_count != \'0\'', workflow)
        self.assertIn('Sincronizar SOLO archivos modificados', workflow)
        self.assertIn('actions: read', workflow)
    def test_source_inventory_is_a_valid_modular_family(self):
        modules = sorted(
            d for d in ROOT.glob("taxocommerce-*")
            if d.is_dir() and MODULE.fullmatch(d.name)
        )
        self.assertGreaterEqual(len(modules), 19)
        self.assertNotIn("seo-taxonomy", {p.name for p in modules})
        for directory in modules:
            self.assertTrue((directory / (directory.name + ".php")).is_file(), directory.name)
            self.assertTrue((directory / "readme.txt").is_file(), directory.name)


if __name__ == "__main__":
    unittest.main()
