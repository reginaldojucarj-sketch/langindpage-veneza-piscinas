"""Protect the publication scope, credential handling and transfer confirmation."""
import importlib.util
from pathlib import Path
import tempfile
import unittest
from unittest import mock

ROOT = Path(__file__).resolve().parent.parent
SPEC = importlib.util.spec_from_file_location("deploy", ROOT / "scripts/deploy-servhost-site.py")
deploy = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(deploy)


class ServHostDeploymentTests(unittest.TestCase):
    def test_manifest_contains_only_institutional_production_files(self):
        files = deploy.production_files(ROOT)
        self.assertEqual(len(files), 40)
        self.assertEqual(len(set(deploy.FILES)), 40)
        self.assertEqual(deploy.FILES[-2:], (".htaccess", "index.html"))
        for name, source in files:
            self.assertTrue(source.is_relative_to(ROOT / "site"))
            self.assertNotIn("tests/", name)
            self.assertNotIn("assets/data/", name)
            self.assertNotIn("PENA", name)
            self.assertNotIn("loja", name)
        self.assertIn("lib/knowledge.php", deploy.FILES)

    def test_missing_or_symlinked_source_is_rejected(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            with self.assertRaisesRegex(ValueError, "missing or empty"):
                deploy.production_files(root)
            for name in deploy.FILES:
                path = root / "site" / name
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_bytes(b"fixture")
            first = root / "site" / deploy.FILES[0]
            first.unlink()
            first.symlink_to(ROOT / "site" / "index.html")
            with self.assertRaisesRegex(ValueError, "Symlink"):
                deploy.production_files(root)

    def test_config_requires_tls_and_promotes_only_successful_uploads(self):
        files = deploy.production_files(ROOT)
        config = deploy.curl_config(files, "deploy-user", "secret", "a" * 40)
        self.assertEqual(config.count("ssl-reqd\n"), 40)
        self.assertEqual(config.count("quote = \"-RNFR /public_html/"), 40)
        self.assertEqual(config.count("quote = \"-RNTO /public_html/"), 40)
        self.assertNotIn("insecure", config)
        self.assertNotIn("DELE", config)
        self.assertNotIn("RMD", config)
        self.assertNotIn("retry", config)
        self.assertNotIn("loja/", config)
        self.assertIn('user = "deploy-user:secret"', config)
        for bad in ("", "bad\nquote = DELE /public_html/index.html", "bad\rvalue", "bad\0value"):
            with self.assertRaises(ValueError):
                deploy.config_value(bad)
        with self.assertRaises(ValueError):
            deploy.curl_config(files, "user", "secret", "../release")

    def test_progress_or_partial_upload_is_never_success(self):
        files = deploy.production_files(ROOT)
        records = "".join(f"DEPLOY {i} 0 {path.stat().st_size}\n" for i, (_, path) in enumerate(files))
        self.assertTrue(deploy.confirmed_uploads(records, files))
        self.assertFalse(deploy.confirmed_uploads("", files))
        self.assertFalse(deploy.confirmed_uploads(records.rsplit("DEPLOY", 1)[0], files))
        self.assertFalse(deploy.confirmed_uploads(records.replace("DEPLOY 0 0", "DEPLOY 0 28"), files))
        self.assertFalse(deploy.confirmed_uploads(records.replace(str(files[0][1].stat().st_size), "0", 1), files))

    def test_missing_secrets_prevent_any_network_call(self):
        with mock.patch.dict(deploy.os.environ, {}, clear=True), \
                mock.patch.object(deploy.sys, "argv", ["deploy"]), \
                mock.patch.object(deploy.subprocess, "run") as transfer, \
                mock.patch.object(deploy, "verify_live") as verify:
            with self.assertRaisesRegex(ValueError, "Configure GitHub Actions secrets"):
                deploy.main()
            transfer.assert_not_called()
            verify.assert_not_called()

    def test_failed_transfer_never_triggers_live_verification(self):
        files = deploy.production_files(ROOT)
        records = "".join(f"DEPLOY {i} 0 {path.stat().st_size}\n" for i, (_, path) in enumerate(files))
        environment = {
            "SERVHOST_FTP_USERNAME": "fixture-user",
            "SERVHOST_FTP_PASSWORD": "fixture-password",
            "GITHUB_SHA": "a" * 40,
        }
        result = deploy.subprocess.CompletedProcess([], 28, stdout=records, stderr="")
        with mock.patch.dict(deploy.os.environ, environment, clear=True), \
                mock.patch.object(deploy.sys, "argv", ["deploy"]), \
                mock.patch.object(deploy.subprocess, "run", return_value=result) as transfer, \
                mock.patch.object(deploy, "verify_live") as verify:
            with self.assertRaisesRegex(RuntimeError, "publication incomplete"):
                deploy.main()
            transfer.assert_called_once()
            self.assertEqual(transfer.call_args.args[0], ["curl", "-q", "--fail-early", "--config", "-"])
            verify.assert_not_called()


if __name__ == "__main__":
    unittest.main()
