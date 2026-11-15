"""Offline tests: compare changed files and copy without WordPress inventory."""
import importlib.util
import os
from pathlib import Path
import sys
import tempfile
import unittest

SCRIPTS = Path(__file__).resolve().parents[1] / "scripts"
spec = importlib.util.spec_from_file_location("pro_copy", SCRIPTS / "deploy_pro_ftps_native.py")
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class FakeFTP:
    def __init__(self):
        self.folder = "/plugins"
        self.dirs = {"/plugins"}
        self.files = {}
        self.uploads = 0
        self.fail_commit = False

    def cwd(self, name):
        p = name if name.startswith("/") else self.folder + "/" + name
        if p not in self.dirs:
            raise module.ftplib.error_perm("550 Not found")
        self.folder = p

    def mkd(self, name):
        self.dirs.add(self.folder + "/" + name)

    def retrbinary(self, command, callback, blocksize=8192):
        path = self.folder + "/" + command.split(" ", 1)[1]
        if path not in self.files:
            raise module.ftplib.error_perm("550 Not found")
        callback(self.files[path])

    def storbinary(self, command, stream, blocksize=8192):
        self.uploads += 1
        self.files[self.folder + "/" + command.split(" ", 1)[1]] = stream.read()

    def rename(self, old, new):
        if self.fail_commit and old.endswith(".tmp"):
            raise module.ftplib.error_perm("550 Rename refused")
        src, dst = self.folder + "/" + old, self.folder + "/" + new
        if src not in self.files:
            raise module.ftplib.error_perm("550 Not found")
        if dst in self.files:
            raise module.ftplib.error_perm("550 Destination exists")
        self.files[dst] = self.files.pop(src)

    def delete(self, name):
        self.files.pop(self.folder + "/" + name, None)


class CopyTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.orig = Path.cwd()
        os.chdir(self.tmp.name)
        self.addCleanup(os.chdir, self.orig)
        Path("taxocommerce-social/includes").mkdir(parents=True)
        Path("taxocommerce-social/includes/core.php").write_bytes(b"new")
        self.ftp = FakeFTP()
        self.dest = "/plugins/taxocommerce-social/includes/core.php"

    def test_reject_bad_names_without_ftp(self):
        for path in ("../secret.php", "/wrong.php", ".env", "tests/test.php",
                     "uploads/data.csv", "includes/../x.php", "includes\\x.php"):
            self.assertFalse(module.valid_path("taxocommerce-social", path))
        self.assertFalse(module.valid_path("seo-taxonomy", "seo-taxonomy.php"))

    def test_new_plugin_or_file_can_be_copied_without_install_inventory(self):
        changed = module.copy_changed(self.ftp, "taxocommerce-social", "includes/core.php", "/plugins")
        self.assertTrue(changed)
        self.assertEqual(b"new", self.ftp.files[self.dest])
        self.assertEqual(1, self.ftp.uploads)

    def test_identical_file_is_not_uploaded(self):
        self.ftp.dirs.update({"/plugins/taxocommerce-social", "/plugins/taxocommerce-social/includes"})
        self.ftp.files[self.dest] = b"new"
        changed = module.copy_changed(self.ftp, "taxocommerce-social", "includes/core.php", "/plugins")
        self.assertFalse(changed)
        self.assertEqual(0, self.ftp.uploads)

    def test_changed_file_replaces_previous_and_cleans_backup(self):
        self.ftp.dirs.update({"/plugins/taxocommerce-social", "/plugins/taxocommerce-social/includes"})
        self.ftp.files[self.dest] = b"old"
        changed = module.copy_changed(self.ftp, "taxocommerce-social", "includes/core.php", "/plugins")
        self.assertTrue(changed)
        self.assertEqual(b"new", self.ftp.files[self.dest])
        self.assertEqual({self.dest}, set(self.ftp.files))

    def test_failed_commit_restores_original_file(self):
        self.ftp.dirs.update({"/plugins/taxocommerce-social", "/plugins/taxocommerce-social/includes"})
        self.ftp.files[self.dest] = b"old"
        self.ftp.fail_commit = True
        with self.assertRaises(module.ftplib.error_perm):
            module.copy_changed(self.ftp, "taxocommerce-social", "includes/core.php", "/plugins")
        self.assertEqual(b"old", self.ftp.files[self.dest])
        self.assertEqual({self.dest}, set(self.ftp.files))


if __name__ == "__main__":
    unittest.main()
