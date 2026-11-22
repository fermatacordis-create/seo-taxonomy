"""Regression checks for the manual WordPress.org SVN publisher.

These tests inspect the release contract without contacting SVN or reading secrets.
"""
from pathlib import Path
import re
import unittest


WORKFLOW = Path(__file__).resolve().parents[1] / "workflows" / "publicar-taxocommerce-wordpress-org.yml"


class WordPressOrgSVNWorkflowTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.yaml = WORKFLOW.read_text(encoding="utf-8")

    def test_publication_is_manual_only(self):
        self.assertIn("  workflow_dispatch:", self.yaml)
        for trigger in ("  push:", "  schedule:", "  pull_request:", "  workflow_call:"):
            self.assertNotIn(trigger, self.yaml)
        self.assertIn("confirm_publication:", self.yaml)
        self.assertIn("default: false", self.yaml)
        self.assertIn("refs/heads/staging", self.yaml)

    def test_core_is_only_approved_plugin(self):
        options = re.search(
            r"(?m)^        options:\s*\n((?:          - .+\n)+)", self.yaml
        )
        self.assertIsNotNone(options)
        self.assertEqual(options.group(1).strip(), "- taxocommerce-core")
        self.assertIn("test \"$PLUGIN\" = taxocommerce-core", self.yaml)

    def test_release_is_gated(self):
        self.assertIn("needs: [authorize, official-plugin-check]", self.yaml)
        self.assertIn("uses: ./.github/workflows/taxocommerce-plugin-check.yml", self.yaml)
        self.assertIn("test_package_core_wordpress_org.py", self.yaml)
        self.assertIn("package_core_wordpress_org.py", self.yaml)
        self.assertIn("taxocommerce_family_readme_check.py", self.yaml)
        self.assertIn("Plugin Version and Stable tag do not match", self.yaml)

    def test_svn_client_is_installed_and_verified_before_publish(self):
        install = self.yaml.index("name: Instalar y verificar el cliente Subversion")
        publish = self.yaml.index("name: Publicar trunk y etiqueta de version")
        self.assertLess(install, publish)
        self.assertIn("sudo apt-get install -y --no-install-recommends subversion", self.yaml)
        self.assertIn("svn --version --quiet", self.yaml)
        self.assertIn("if ! command -v svn >/dev/null 2>&1; then", self.yaml)

    def test_distribution_is_scoped_and_tag_immutable(self):
        self.assertIn("https://plugins.svn.wordpress.org/$PLUGIN", self.yaml)
        self.assertIn('if [ -e "$SVN_WORK/tags/$VERSION" ]; then', self.yaml)
        self.assertIn('rsync -a --delete --exclude=\'.svn\'', self.yaml)
        self.assertIn('svn copy "$SVN_WORK/trunk" "$SVN_WORK/tags/$VERSION"', self.yaml)
        self.assertIn('svn commit "$SVN_WORK"', self.yaml)
        self.assertIn("secrets.SVN_USERNAME", self.yaml)
        self.assertIn("secrets.SVN_PASSWORD", self.yaml)
        self.assertNotIn("deploy_pro", self.yaml)


if __name__ == "__main__":
    unittest.main()
