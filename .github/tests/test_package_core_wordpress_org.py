"""Strict tests for the two valid Core packages (installed and WordPress.org)."""
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
    def test_translate_only_gettext_domains_not_data_or_menu_slugs(self):
        example = """<?php
        echo esc_html__( 'Manual', 'taxocommerce-core' );
        echo __( sprintf( 'Hello %s', 'Bob' ), 'taxocommerce-core' );
        add_submenu_page( 'taxocommerce-core', 'Tools', 'Tools', 'manage_options', 'taxocommerce-core' );
        $slug = 'taxocommerce-core';
        // __( 'Comment', 'taxocommerce-core' );
        $str = "__('Quoted', 'taxocommerce-core')";
        """
        result, count = MODULE.adapt_gettext_calls(example)
        self.assertEqual(count, 2)
        self.assertIn("esc_html__( 'Manual', '" + MODULE.OFFICIAL + "' )", result)
        self.assertIn("), '" + MODULE.OFFICIAL + "' )", result)
        self.assertIn("add_submenu_page( 'taxocommerce-core'", result)
        self.assertIn("$slug = 'taxocommerce-core';", result)
        self.assertIn("// __( 'Comment', 'taxocommerce-core' );", result)

    def test_inline_php_in_html_attributes_is_translated_without_changing_html(self):
        example = """<nav aria-label="<?php esc_attr_e( 'Nav', 'taxocommerce-core' ); ?>">
        <input placeholder="<?php echo esc_attr__( 'Search', 'taxocommerce-core' ); ?>">
        <?php $slug = 'taxocommerce-core'; ?>
        """
        result, count = MODULE.adapt_gettext_calls(example)
        self.assertEqual(count, 2)
        self.assertIn("esc_attr_e( 'Nav', '" + MODULE.OFFICIAL + "'", result)
        self.assertIn("esc_attr__( 'Search', '" + MODULE.OFFICIAL + "'", result)
        self.assertIn("$slug = 'taxocommerce-core';", result)
        self.assertIn('<nav aria-label="<?php', result)

    def test_complete_official_archive_can_be_derived_without_editing_source(self):
        original = ROOT / MODULE.INSTALLED
        self.assertTrue((original / (MODULE.INSTALLED + ".php")).is_file())
        with tempfile.TemporaryDirectory() as temp:
            package = Path(temp) / MODULE.OFFICIAL
            shutil.copytree(original, package, ignore=shutil.ignore_patterns("tests"))
            result = MODULE.prepare(package)
            self.assertGreaterEqual(result["gettext_calls"], 950)
            self.assertTrue((package / (MODULE.OFFICIAL + ".php")).is_file())
            self.assertFalse((package / (MODULE.INSTALLED + ".php")).exists())
            main = (package / (MODULE.OFFICIAL + ".php")).read_text(encoding="utf-8")
            native = (original / (MODULE.INSTALLED + ".php")).read_text(encoding="utf-8")
            self.assertIn("Text Domain: " + MODULE.INSTALLED, native)
            self.assertIn("Text Domain: " + MODULE.OFFICIAL, main)
            self.assertIn("Version: 0.4.78", main)
            self.assertIn("Version: 0.4.78", native)
            for locale in ("es_ES", "en_US", "fr_FR", "de_DE", "nl_NL"):
                for ext in (".po", ".mo"):
                    self.assertTrue((package / "languages" / (MODULE.OFFICIAL + "-" + locale + ext)).is_file())
                    self.assertFalse((package / "languages" / (MODULE.INSTALLED + "-" + locale + ext)).exists())
                    self.assertTrue((original / "languages" / (MODULE.INSTALLED + "-" + locale + ext)).is_file())
            official_guide = (package / "includes" / "class-taxocommerce-core-user-guide.php").read_text(encoding="utf-8")
            installed_guide = (original / "includes" / "class-taxocommerce-core-user-guide.php").read_text(encoding="utf-8")
            self.assertIn(MODULE.OFFICIAL, official_guide)
            self.assertNotIn(MODULE.OFFICIAL, installed_guide)
            self.assertIn("taxocommerce-core", installed_guide)
            self.assertEqual(native, (original / (MODULE.INSTALLED + ".php")).read_text(encoding="utf-8"))

    def test_package_rejects_wrong_directory(self):
        with tempfile.TemporaryDirectory() as temp:
            with self.assertRaisesRegex(ValueError, "distribution directory"):
                MODULE.prepare(Path(temp) / MODULE.INSTALLED)


if __name__ == "__main__":
    unittest.main()
