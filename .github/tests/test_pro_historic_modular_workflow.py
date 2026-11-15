"""Offline contract: staging Git diff then production FTP copy without inventory."""
from pathlib import Path
import unittest
ROOT = Path(__file__).resolve().parents[2]

class PRODeployTests(unittest.TestCase):
    def setUp(self):
        self.workflow = (ROOT / ".github/workflows/despliege_de_main_a_pro.yml").read_text()
        self.copier = (ROOT / ".github/scripts/deploy_pro_ftps_native.py").read_text()
        self.selector = (ROOT / ".github/scripts/select_pro_release_delta.py").read_text()

    def test_github_to_pro_without_inventory(self):
        self.assertIn("select_pro_release_delta.py --all-changed", self.workflow)
        self.assertIn("deploy_pro_ftps_native.py", self.workflow)
        for forbidden in ("fetch_pro_installed_modules.py", "INSTALLED_MODULES_JSON",
                          "PRO_CORE_EXPECTED_VERSION", "PRO_WOO_EXPECTED_VERSION",
                          "deploy-active-modules", "pro_ftps_root_probe.py", "PRO_FTPS_HOST"):
            self.assertNotIn(forbidden, self.workflow)
        self.assertIn("installed = None", self.selector)
        self.assertIn("allowed = None", self.selector)
        self.assertNotIn("installed_modules(", self.copier)
        self.assertNotIn("find_verified_root(", self.copier)

    def test_existing_ftp_secrets_and_fixed_plugin_directory(self):
        for name in ("FTP_SERVER", "FTP_USERNAME", "FTP_PASSWORD"):
            self.assertIn("secrets." + name, self.workflow)
        self.assertNotIn("secrets.STAGING_FTP_", self.workflow)
        self.assertIn("PLUGINS_DIR = \"wp-content/plugins\"", self.copier)
        self.assertIn("ftp.cwd(PLUGINS_DIR)", self.copier)

    def test_changed_files_only_and_copy_sha(self):
        self.assertIn("changed_count != '0'", self.workflow)
        self.assertIn("changed_count == '0'", self.workflow)
        self.assertIn("read_hash(ftp, filename) == digest", self.copier)
        self.assertIn("ftp.rename(filename, backup)", self.copier)
        self.assertIn("ftp.rename(temporary, filename)", self.copier)

    def test_staging_only_and_no_manual_plugin_input(self):
        self.assertIn("ref: staging", self.workflow)
        self.assertIn('test "$GITHUB_REF_NAME" = "staging"', self.workflow)
        self.assertIn("EXPECTED_SHA: " + "$" + "{{ inputs.source_sha }}", self.workflow)
        manual = self.workflow.split("  workflow_dispatch:", 1)[1].split("\npermissions:", 1)[0]
        self.assertNotIn("inputs:", manual)
        self.assertNotIn("target_plugin", manual)

if __name__ == "__main__":
    unittest.main()
