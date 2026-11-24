"""Pro edition: isolated package, independent account storage and safe IMAP semantics."""
import importlib
import io
import json
from pathlib import Path
import sys
import tempfile
import unittest
import zipfile

SCRIPTS = Path(__file__).resolve().parents[1] / "scripts"
sys.path.insert(0, str(SCRIPTS))
pack = importlib.import_module("publish_mail_pro_package")
ROOT = Path(__file__).resolve().parents[2]

class ProPackageTests(unittest.TestCase):
    def test_test_sites_only(self):
        self.assertEqual(pack.SLUG, "taxocommerce-mail-pro")
        self.assertEqual(pack.SITES, ("staging.distribuidordeherramientas.es", "asociacionfocazul"))
        source = (ROOT / ".github/scripts/deploy_staging_delta.py").read_text()
        self.assertIn('"taxocommerce-mail-pro": "#2944"', source)

    def test_zip_is_standalone_and_excludes_tests(self):
        data, info = pack.package_mail_pro(ROOT)
        self.assertEqual(info["version"], "0.1.0")
        with zipfile.ZipFile(io.BytesIO(data)) as z:
            names = set(z.namelist())
        self.assertIn("taxocommerce-mail-pro/taxocommerce-mail-pro.php", names)
        self.assertIn("taxocommerce-mail-pro/includes/class-tcmailpro-mailbox.php", names)
        self.assertIn("taxocommerce-mail-pro/CHANGELOG.md", names)
        self.assertFalse(any("/tests/" in name for name in names))
        self.assertFalse(any(name.startswith("taxocommerce-mail/") for name in names))

    def test_manifest_modifies_only_pro(self):
        original = {"schema": 1, "plugins": {
            "taxocommerce-mail": {"version": "0.1.3", "sha256": "unchanged"},
            "taxocommerce-ticketing": {"version": "0.1.1", "sha256": "unchanged"}
        }}
        updated = json.loads(pack.updated_manifest(json.dumps(original).encode(), {
            "version": "0.1.0", "sha256": "pro-only", "size": 1
        }))
        self.assertEqual(original["plugins"]["taxocommerce-mail"], updated["plugins"]["taxocommerce-mail"])
        self.assertEqual(original["plugins"]["taxocommerce-ticketing"], updated["plugins"]["taxocommerce-ticketing"])
        self.assertEqual("pro-only", updated["plugins"]["taxocommerce-mail-pro"]["sha256"])

    def test_pro_manual_truthful_and_localizations_parse(self):
        import gettext
        manual = (ROOT / "taxocommerce-mail-pro/includes/class-tcmailpro-autocheck.php").read_text(encoding="utf-8")
        self.assertIn("UID MOVE (RFC 6851)", manual)
        self.assertIn("Mail Pro > Accounts", manual)
        self.assertNotIn("12 newest inbox messages", manual)
        for locale in ("es_ES", "en_US", "fr_FR", "de_DE", "nl_NL"):
            with self.subTest(locale=locale):
                with (ROOT / "taxocommerce-mail-pro/languages" / f"taxocommerce-mail-pro-{locale}.mo").open("rb") as handle:
                    catalog = gettext.GNUTranslations(handle)
                self.assertEqual(catalog.gettext("TaxoCommerce Mail Pro"), "TaxoCommerce Mail Pro")
                self.assertNotEqual(catalog.gettext("Folders"), "")
                if locale != "en_US":
                    self.assertNotEqual(catalog.gettext("Folders"), "Folders")

    def test_independent_admin_and_credentials(self):
        main = (ROOT / "taxocommerce-mail-pro/taxocommerce-mail-pro.php").read_text()
        accounts = (ROOT / "taxocommerce-mail-pro/includes/class-tcmailpro-accounts.php").read_text()
        mailbox = (ROOT / "taxocommerce-mail-pro/includes/class-tcmailpro-mailbox.php").read_text()
        admin = (ROOT / "taxocommerce-mail-pro/includes/class-tcmailpro-admin.php").read_text()
        self.assertIn("Plugin Name: TaxoCommerce Mail Pro", main)
        self.assertIn("Text Domain: taxocommerce-mail-pro", main)
        self.assertIn("tcmailpro_accounts", accounts)
        self.assertNotIn("'tcmail_accounts'", accounts)
        self.assertIn("class TCMAILPRO_Mailbox", mailbox)
        self.assertIn("tcmailpro_bulk", mailbox)
        self.assertIn("check_admin_referer", mailbox)
        self.assertIn("TCMAILPRO_Mailbox::render(", admin)
        self.assertNotIn("TCMAIL_Admin::", main)

if __name__ == "__main__":
    unittest.main()
