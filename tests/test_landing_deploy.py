"""Offline checks: only landing files, verified transfers, no destructive mirror."""
import importlib.util
import io
import os
from pathlib import Path
import tempfile
import unittest
from unittest import mock

ROOT = Path(__file__).resolve().parent.parent
SPEC = importlib.util.spec_from_file_location('landing_deploy', ROOT / 'scripts/deploy-servhost-landing.py')
deploy = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(deploy)
RELEASE = 'a' * 40 + '-' + 'b' * 32


class FakeFTP:
    def __init__(self):
        self.files = {}
        self.commands = []

    def cwd(self, path):
        self.commands.append(('cwd', path))

    def mkd(self, path):
        self.commands.append(('mkd', path))

    def storbinary(self, command, stream):
        self.commands.append(command)
        self.files[command.removeprefix('STOR ')] = stream.read()

    def voidcmd(self, command):
        self.commands.append(command)

    def size(self, path):
        return len(self.files[path])

    def rename(self, old, new):
        self.commands.append(('rename', old, new))
        self.files[new] = self.files.pop(old)


class LandingDeploymentTests(unittest.TestCase):
    def test_tracked_public_manifest_and_exact_root(self):
        files = deploy.production_files(ROOT)
        self.assertEqual(deploy.REMOTE_ROOT, '/public_html/orcamento.venezapiscinas.com.br')
        self.assertEqual(files[-1][0], 'index.html')
        names = dict(files)
        self.assertIn('.htaccess', names)
        self.assertIn('assets/videos/piscina-aquecida-pingo.webm', names)
        self.assertIn('assets/data/posts-data.js', names)
        self.assertTrue(all(deploy.approved(name) for name in names))
        self.assertFalse(any(name.endswith('.md') or 'sources/' in name for name in names))

    def test_paths_never_include_other_sites_or_private_files(self):
        for name in ('../index.html', '/index.html', 'assets/images/../secret.jpg',
                     'assets/images/.env', 'assets/images/test.php', 'assets/images/.git/photo.jpg',
                     'assets/videos/movie-original.mp4', 'assets/sources/image.jpg',
                     'assets/images/README.md', 'PENA/index.php', 'site/index.html',
                     'loja/index.html', 'assets/images/bad\nSTOR secret.jpg'):
            self.assertFalse(deploy.approved(name), name)

    def test_untracked_files_are_never_staged_and_missing_required_files_fail(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            for name in (*deploy.PAGES, 'assets/images/untracked.jpg'):
                path = root / name
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_bytes(b'fixture')
            tracked = ('\0'.join(deploy.PAGES) + '\0').encode()
            with mock.patch.object(deploy.subprocess, 'check_output', return_value=tracked):
                self.assertNotIn('assets/images/untracked.jpg', dict(deploy.production_files(root)))
                (root / 'index.html').unlink()
                with self.assertRaises(ValueError):
                    deploy.production_files(root)
            with mock.patch.object(deploy.subprocess, 'check_output', return_value=b'index.html\0'):
                with self.assertRaises(ValueError):
                    deploy.production_files(root)

    def test_symlink_is_not_a_public_asset(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            for name in deploy.PAGES:
                (root / name).write_bytes(b'fixture')
            if os.name == 'nt':
                # Windows symlink creation may require privileges. Exercise the same
                # rejection without changing machine policy; Linux CI uses a real link.
                with mock.patch.object(Path, 'is_symlink', autospec=True, side_effect=lambda p: p == root / 'index.html'), \
                        mock.patch.object(deploy.subprocess, 'check_output', return_value=('\0'.join(deploy.PAGES)+'\0').encode()):
                    with self.assertRaises(ValueError):
                        deploy.production_files(root)
                return
            (root / 'index.html').unlink()
            (root / 'index.html').symlink_to(ROOT / 'index.html')
            with mock.patch.object(deploy.subprocess, 'check_output', return_value=('\0'.join(deploy.PAGES)+'\0').encode()):
                with self.assertRaises(ValueError):
                    deploy.production_files(root)

    def test_upload_stays_in_its_root_and_checks_size_before_rename(self):
        ftp = FakeFTP()
        files = [('assets/css/styles.css', ROOT / 'assets/css/styles.css')]
        deploy.upload(ftp, files, RELEASE)
        target = deploy.REMOTE_ROOT + '/assets/css/styles.css'
        self.assertEqual(ftp.files[target], files[0][1].read_bytes())
        self.assertEqual(ftp.commands[0], ('cwd', deploy.REMOTE_ROOT))
        self.assertFalse(any(str(c).startswith(('DELE', 'RMD')) for c in ftp.commands))
        for name in ('index.php', '../index.html', 'loja/index.html'):
            with self.assertRaises(ValueError):
                deploy.upload(ftp, [(name, ROOT / 'index.html')], RELEASE)
        ftp.files[target] = b'previous'
        with mock.patch.object(ftp, 'size', return_value=0):
            with self.assertRaises(RuntimeError):
                deploy.upload(ftp, files, RELEASE)
        self.assertEqual(ftp.files[target], b'previous')

    def test_missing_secrets_never_connect(self):
        with mock.patch.object(deploy, 'production_files', return_value=[]), \
                mock.patch.object(deploy.subprocess, 'check_output', return_value='a'*40), \
                mock.patch.dict(deploy.os.environ, {'GITHUB_SHA': 'a'*40}, clear=True), \
                mock.patch.object(deploy.sys, 'argv', ['deploy']), \
                mock.patch.object(deploy, 'ReusingTLS') as connection:
            with self.assertRaises(ValueError):
                deploy.main()
            connection.assert_not_called()

    def test_live_hash_failure_is_not_success(self):
        response = mock.MagicMock()
        response.__enter__.return_value = response
        response.status = 200
        response.url = deploy.ORIGIN + '/?release=' + RELEASE
        response.read.return_value = b'old website'
        with mock.patch.object(deploy.urllib.request, 'urlopen', return_value=response):
            with self.assertRaises(RuntimeError):
                deploy.verify_live(deploy.production_files(ROOT), RELEASE)

    def test_workflow_publishes_only_main_and_requires_tls(self):
        workflow = (ROOT / '.github/workflows/deploy-servhost.yml').read_text()
        self.assertIn('branches: [main]', workflow)
        self.assertIn("github.ref == 'refs/heads/main'", workflow)
        self.assertIn('cancel-in-progress: false', workflow)
        self.assertIn('scripts/deploy-servhost-landing.py', workflow)
        self.assertIn('SERVHOST_FTP_USERNAME', workflow)
        self.assertNotIn('PENA_REPOSITORY_TOKEN', workflow)
        self.assertNotIn('submodules:', workflow)
        source = (ROOT / 'scripts/deploy-servhost-landing.py').read_text()
        self.assertIn('ssl.create_default_context()', source)
        self.assertIn('ftp.prot_p()', source)
        self.assertNotIn('CERT_NONE', source)
        self.assertNotIn('ftp.delete(', source)


if __name__ == '__main__':
    unittest.main()
