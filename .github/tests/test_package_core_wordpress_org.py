"""Regression for the WordPress.org-assigned TaxoCommerce Core slug."""
from __future__ import annotations

import importlib.util
from pathlib import Path
import shutil
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
MODULE_PATH = ROOT / ".github/scripts/package_core_wordpress_org.py"
SPEC = importlib.util.spec_from_file_location("package_core_wporg", MODULE_PATH)
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)


class CoreDistributionTests(unittest.TestCase):
    def test_slug_is_final_short_wordpress_org_identifier(self):
        self.assertEqual("taxocommerce-core", MODULE.OFFICIAL)

    def test_public_package_preserves_main_file_and_gettext_domain(self):
        original = ROOT / MODULE.OFFICIAL
        with tempfile.TemporaryDirectory() as tmp:
            package = Path(tmp) / MODULE.OFFICIAL
            shutil.copytree(original, package, ignore=shutil.ignore_patterns("tests"))
            expected = (original / (MODULE.OFFICIAL + ".php")).read_bytes()
            outcome = MODULE.prepare(package)
            self.assertGreaterEqual(outcome["legacy_catalogs_excluded"], 5)
            self.assertFalse(list((package / "languages").glob(MODULE.OLD_SLUG + "-*")))
            self.assertEqual(expected, (package / (MODULE.OFFICIAL + ".php")).read_bytes())
            self.assertFalse((package / (MODULE.OLD_SLUG + ".php")).exists())
            for locale in MODULE.LOCALES:
                for extension in (".po", ".mo"):
                    self.assertTrue((package / "languages" / (MODULE.OFFICIAL + "-" + locale + extension)).is_file())

    def test_wrong_slug_is_rejected(self):
        with tempfile.TemporaryDirectory() as tmp:
            with self.assertRaisesRegex(ValueError, "WordPress.org slug"):
                MODULE.prepare(Path(tmp) / MODULE.OLD_SLUG)

    def test_legacy_catalogs_are_removed_from_build_only(self):
        original = ROOT / MODULE.OFFICIAL
        with tempfile.TemporaryDirectory() as tmp:
            package = Path(tmp) / MODULE.OFFICIAL
            shutil.copytree(original, package, ignore=shutil.ignore_patterns("tests"))
            originals = list((original / "languages").glob(MODULE.OLD_SLUG + "-*"))
            self.assertTrue(originals)
            MODULE.prepare(package)
            self.assertFalse(list((package / "languages").glob(MODULE.OLD_SLUG + "-*")))
            self.assertTrue(all(path.is_file() for path in originals))

    def test_workflow_uses_single_official_slug(self):
        workflow = (ROOT / ".github/workflows/taxocommerce-plugin-check.yml").read_text(encoding="utf-8")
        self.assertIn('TARGET_SLUG="$PLUGIN"', workflow)
        self.assertNotIn('TARGET_SLUG="' + MODULE.OLD_SLUG + '"', workflow)
        self.assertIn("package_core_wordpress_org.py", workflow)


if __name__ == "__main__":
    unittest.main()
