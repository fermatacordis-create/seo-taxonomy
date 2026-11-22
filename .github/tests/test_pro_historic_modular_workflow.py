"""Offline regression checks for GitHub STAGING -> PRO direct FTPS copy.

No FTP or WordPress access is used here.
"""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
MAIN = ROOT / ".github/workflows/despliege_de_main_a_pro.yml"
ALTERNATE = ROOT / ".github/workflows/staging-a-pro-manual.yml"
COPIER = ROOT / ".github/scripts/deploy_pro_ftps_native.py"
SELECTOR = ROOT / ".github/scripts/select_pro_release_delta.py"


class ProDirectFTPWorkflowTests(unittest.TestCase):
    def test_both_workflows_are_manual_and_only_copy_directly(self):
        for workflow in (MAIN, ALTERNATE):
            yaml = workflow.read_text(encoding="utf-8")
            self.assertIn("workflow_dispatch:", yaml)
            self.assertNotIn("\n  push:", yaml)
            self.assertIn("ref: staging", yaml)
            self.assertIn("select_pro_release_delta.py --all-changed", yaml)
            self.assertIn("deploy_pro_ftps_native.py pro-delta.json", yaml)
            for key in ("FTP_SERVER", "FTP_USERNAME", "FTP_PASSWORD"):
                self.assertIn("secrets." + key, yaml)
            for indirect in (
                "deploy_pro_simple.py", "fetch_pro_installed_modules.py",
                "INSTALLED_MODULES_JSON", "deploy-active-modules", "seo-taxonomy/",
                ".taxocommerce-deploy/", "wpcom_plugin_upload", "zip -",
            ):
                self.assertNotIn(indirect, yaml)

    def test_main_remains_callable_with_exact_staging_revision(self):
        yaml = MAIN.read_text(encoding="utf-8")
        self.assertIn("name: STAGING a PRODUCCION", yaml)
        self.assertIn("workflow_call:", yaml)
        self.assertIn('test "$GITHUB_REF_NAME" = "staging"', yaml)
        self.assertIn('ACTUAL_SHA="$(git rev-parse HEAD)"', yaml)
        self.assertIn('if [ "$ACTUAL_SHA" != "$EXPECTED_SHA" ]; then', yaml)

    def test_copy_only_changed_files_and_refuse_wrong_ftp_location(self):
        copier = COPIER.read_text(encoding="utf-8")
        selector = SELECTOR.read_text(encoding="utf-8")
        self.assertIn("find_live_plugin_root(ftp)", copier)
        self.assertIn("Wrong production FTPS root:", copier)
        self.assertIn("previous_exists = exists(ftp, filename)", copier)
        self.assertIn("ftp.size(temporary) != source.stat().st_size", copier)
        self.assertNotIn("retrbinary(", copier)
        self.assertIn('ftp.storbinary("STOR " + temporary', copier)
        self.assertIn('ftp.rename(temporary, filename)', copier)
        self.assertIn("Uploaded file size mismatch", copier)
        self.assertIn('SLUG = re.compile(r"taxocommerce-', selector)
        self.assertIn('git("diff",', selector)
        self.assertIn("--diff-filter=ACMT", selector)
        self.assertIn("if args.all_changed:", selector)


if __name__ == "__main__":
    unittest.main()
