"""Regression: Mail-only package is installable from Core, never affects PRO or other plugins."""
import importlib
import gettext
import io
import json
from pathlib import Path
import sys
import tempfile
import unittest
import zipfile

SCRIPTS = Path(__file__).resolve().parents[1] / "scripts"
sys.path.insert(0, str(SCRIPTS))
pack = importlib.import_module("publish_mail_package")


class MailPackageTests(unittest.TestCase):
    def test_restricted_to_two_test_sites(self):
        self.assertEqual(
            pack.SITES,
            ("staging.distribuidordeherramientas.es", "asociacionfocazul"),
        )
        self.assertEqual(pack.SLUG, "taxocommerce-mail")

    def test_only_installable_mail_files_in_zip(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            plugin = root / pack.SLUG
            (plugin / "includes").mkdir(parents=True)
            (plugin / "tests").mkdir()
            (plugin / (pack.SLUG + ".php")).write_text(
                "<?php\n/**\n * Plugin Name: TaxoCommerce Mail\n * Version: 0.1.1\n * Requires PHP: 7.4\n */",
                encoding="utf-8",
            )
            (plugin / "includes" / "file.php").write_text("<?php\n", encoding="utf-8")
            (plugin / "tests" / "not-distributed.php").write_text("<?php\n", encoding="utf-8")
            data, info = pack.package_mail(root)
            self.assertEqual(info["version"], "0.1.1")
            with zipfile.ZipFile(io.BytesIO(data)) as bundle:
                paths = bundle.namelist()
            self.assertIn("taxocommerce-mail/taxocommerce-mail.php", paths)
            self.assertIn("taxocommerce-mail/includes/file.php", paths)
            self.assertFalse(any("/tests/" in path for path in paths))

    def test_lite_branding_and_stable_identity(self):
        root = Path(__file__).resolve().parents[2]
        entry = (root / "taxocommerce-mail/taxocommerce-mail.php").read_text(encoding="utf-8")
        admin = (root / "taxocommerce-mail/includes/class-tcmail-admin.php").read_text(encoding="utf-8")
        readme = (root / "taxocommerce-mail/readme.txt").read_text(encoding="utf-8")
        self.assertIn("Plugin Name: TaxoCommerce Mail Lite", entry)
        self.assertIn("Version: 0.1.3", entry)
        self.assertIn("Stable tag: 0.1.3", readme)
        self.assertIn("Text Domain: taxocommerce-mail", entry)
        self.assertIn("includes/class-tcmail-accounts.php", entry)
        self.assertIn("taxocommerce-mail/taxocommerce-mail.php", entry)
        self.assertIn("TaxoCommerce Mail Lite", entry)
        self.assertIn("TaxoCommerce Mail Pro", admin)
        self.assertIn("self::pro_information();", admin)
        self.assertNotIn("'<div class=\"wrap\"><h1>'", admin)

    def test_translated_lite_and_pro_catalogs(self):
        root = Path(__file__).resolve().parents[2] / "taxocommerce-mail/languages"
        source = (
            "The Pro edition is planned as an optional separate add-on. "
            "Lite is free and fully functional as described in its manuals; "
            "no purchase or activation is required."
        )
        for locale in ("es_ES", "en_US", "fr_FR", "de_DE", "nl_NL"):
            with self.subTest(locale=locale):
                with (root / ("taxocommerce-mail-" + locale + ".mo")).open("rb") as handle:
                    catalog = gettext.GNUTranslations(handle)
                value = catalog.gettext(source)
                self.assertTrue(value)
                if locale != "en_US":
                    self.assertNotEqual(source, value)
                self.assertEqual(
                    "TaxoCommerce Mail Lite",
                    catalog.gettext("TaxoCommerce Mail Lite"),
                )

    def test_manifest_update_only_mail(self):
        existing = {
            "schema": 1,
            "plugins": {"taxocommerce-ticketing": {"sha256": "previous", "version": "0.1.2"}}
        }
        updated = json.loads(pack.updated_manifest(json.dumps(existing).encode(), {
            "version": "0.1.1", "sha256": "mail-only", "size": 123
        }))
        self.assertEqual(existing["plugins"]["taxocommerce-ticketing"], updated["plugins"]["taxocommerce-ticketing"])
        self.assertEqual("mail-only", updated["plugins"]["taxocommerce-mail"]["sha256"])
        with self.assertRaises(ValueError):
            pack.updated_manifest(b"{}", {})


if __name__ == "__main__":
    unittest.main()
