"""Regression tests for the deliberately scoped Ticketing dual-site release."""
import importlib
import json
from pathlib import Path
import sys
import tempfile
import unittest
from unittest import mock

SCRIPTS = Path(__file__).resolve().parents[1] / "scripts"
sys.path.insert(0, str(SCRIPTS))
pack = importlib.import_module("publish_ticketing_package")
page = importlib.import_module("publish_focazul_ticketing_page")
deploy = importlib.import_module("deploy_staging_delta")


class TicketingDeliveryTests(unittest.TestCase):
    def test_only_two_test_installations_are_targeted(self):
        self.assertEqual(
            pack.SITES,
            ("staging.distribuidordeherramientas.es", "asociacionfocazul"),
        )
        self.assertNotIn("distribuidordeherramientas.es", pack.SITES)
        self.assertEqual(page.HOSTS, {"asociacionfocazul.com", "www.asociacionfocazul.com"})
        self.assertEqual(deploy.remote_plugin_dir("focazul", pack.SLUG),
                         "./asociacionfocazul/wp-content/plugins/taxocommerce-ticketing/")
        with self.assertRaises(ValueError):
            deploy.remote_plugin_dir("production", pack.SLUG)

    def test_zip_packaging_includes_only_plugin_files(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            plugin = root / pack.SLUG
            (plugin / "includes").mkdir(parents=True)
            (plugin / "tests").mkdir()
            (plugin / (pack.SLUG + ".php")).write_text(
                "<?php\n/**\n * Plugin Name: TaxoCommerce Ticketing\n * Version: 0.1.0\n * Requires PHP: 7.4\n */",
                encoding="utf-8",
            )
            (plugin / "includes" / "functions.php").write_text("<?php\n", encoding="utf-8")
            (plugin / "tests" / "not-for-production.php").write_text("<?php\n", encoding="utf-8")
            data, info = pack.package_ticketing(root)
            self.assertEqual(info["version"], "0.1.0")
            import zipfile
            import io
            with zipfile.ZipFile(io.BytesIO(data)) as zipped:
                names = zipped.namelist()
            self.assertIn(pack.SLUG + "/" + pack.SLUG + ".php", names)
            self.assertIn(pack.SLUG + "/includes/functions.php", names)
            self.assertFalse(any("/tests/" in name for name in names))

    def test_manifest_keeps_other_plugins_untouched(self):
        original = {"schema": 1, "plugins": {"taxocommerce-faqs": {"version": "0.1.0", "sha256": "abc", "size": 123}}}
        result = json.loads(pack.updated_manifest(json.dumps(original).encode(), {"version": "0.1.0", "sha256": "new", "size": 456}))
        self.assertEqual(original["plugins"]["taxocommerce-faqs"], result["plugins"]["taxocommerce-faqs"])
        self.assertEqual("new", result["plugins"][pack.SLUG]["sha256"])
        with self.assertRaises(ValueError):
            pack.updated_manifest(b"{}", {})

    def test_page_cannot_overwrite_another_page(self):
        client = mock.Mock()
        client.request.return_value = json.dumps([
            {"id": 10, "slug": page.SLUG, "title": {"raw": "Unrelated information"}}
        ])
        with self.assertRaises(RuntimeError):
            page.save_page(client, "validnonce", "content")
        self.assertEqual(1, client.request.call_count)

    def test_page_source_is_public_marketing_only(self):
        data = page.SOURCE.read_text(encoding="utf-8")
        self.assertIn("&#91;taxocommerce_ticket_form&#93;", data)
        self.assertIn("taxocommerce-ticketing.zip", data)
        self.assertNotIn("<script", data)
        self.assertEqual(page.SLUG, "taxocommerce-ticketing")


if __name__ == "__main__":
    unittest.main()
