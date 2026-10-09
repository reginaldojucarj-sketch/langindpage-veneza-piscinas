"""Offline checks: only landing files, verified transfers, no destructive mirror."""
import importlib.util
import io
import os
from contextlib import redirect_stderr, redirect_stdout
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
        self.assertEqual(deploy.REMOTE_ROOT, '/public_html/equipamentos.venezapiscinas.com.br')
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

    def test_pages_artifact_copies_only_tracked_public_files_without_apache_config(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory) / 'source'
            root.mkdir()
            names = (*deploy.PAGES, 'assets/css/styles.css', 'assets/images/logo.png',
                     'assets/images/products/README.md', 'assets/sources/secret.jpg',
                     'assets/videos/movie-original.mp4', 'assets/images/untracked.jpg')
            for name in names:
                path = root / name
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_bytes(name.encode())
            tracked = ('\0'.join(names[:-1]) + '\0').encode()
            output = Path(directory) / 'pages'
            with mock.patch.object(deploy.subprocess, 'check_output', return_value=tracked):
                self.assertEqual(deploy.stage_pages(output, root), 7)
            expected = set(deploy.PAGES) - {'.htaccess'}
            expected.update(('assets/css/styles.css', 'assets/images/logo.png'))
            actual = {path.relative_to(output).as_posix() for path in output.rglob('*') if path.is_file()}
            self.assertEqual(actual, expected)
            for name in expected:
                self.assertEqual((output / name).read_bytes(), (root / name).read_bytes())

    def test_pages_staging_refuses_to_reuse_a_directory_with_stale_files(self):
        with tempfile.TemporaryDirectory() as directory:
            output = Path(directory) / 'pages'
            output.mkdir()
            (output / 'README.md').write_bytes(b'previous artifact')
            with self.assertRaises(FileExistsError):
                deploy.stage_pages(output)
            self.assertEqual((output / 'README.md').read_bytes(), b'previous artifact')
            self.assertFalse((output / 'index.html').exists())

    def test_pages_mode_never_uses_ftp_or_https_verification(self):
        with tempfile.TemporaryDirectory() as directory:
            output = str(Path(directory) / 'pages')
            with mock.patch.object(deploy.sys, 'argv', ['deploy', '--stage-pages', output]), \
                    mock.patch.dict(deploy.os.environ, {'GITHUB_SHA': '', 'SERVHOST_FTP_USERNAME': '', 'SERVHOST_FTP_PASSWORD': ''}), \
                    mock.patch.object(deploy, 'ReusingTLS') as connection, \
                    mock.patch.object(deploy.urllib.request, 'urlopen') as request:
                deploy.main()
                connection.assert_not_called()
                request.assert_not_called()
            self.assertTrue((Path(output) / 'robots.txt').is_file())
            self.assertTrue((Path(output) / 'sitemap.xml').is_file())
            self.assertFalse((Path(output) / '.htaccess').exists())

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

    def test_failure_diagnostics_use_fixed_labels_without_server_or_secret_text(self):
        secret = 'private-user:private-password\n::notice::unsafe-server-reply'
        cases = (
            (TimeoutError(secret), 'timeout'),
            (deploy.ssl.SSLCertVerificationError(secret), 'tls-certificate'),
            (deploy.ssl.SSLError(secret), 'tls'),
            (deploy.ftplib.error_perm('530 ' + secret), 'ftp-permission'),
            (deploy.ftplib.error_temp('421 ' + secret), 'ftp-temporary'),
            (deploy.urllib.error.HTTPError(secret, 500, secret, None, None), 'https-status'),
            (deploy.urllib.error.URLError(TimeoutError(secret)), 'timeout'),
            (deploy.urllib.error.URLError(secret), 'network'),
            (OSError(secret), 'network-or-file-io'),
            (ValueError(secret), 'configuration-or-verification'),
            (RuntimeError(secret), 'configuration-or-verification'),
            (Exception(secret), 'unexpected'),
        )
        for error, category in cases:
            with self.subTest(category=category, kind=type(error).__name__):
                diagnostics = deploy.DeploymentDiagnostics()
                diagnostics.enter('upload-file')
                output = io.StringIO()
                with redirect_stderr(output):
                    diagnostics.report(error)
                self.assertIn('stopped at upload-file (' + category + ')', output.getvalue())
                self.assertNotIn(secret, output.getvalue())
                self.assertNotIn('private-password', output.getvalue())
                self.assertNotIn('unsafe-server-reply', output.getvalue())
        diagnostics = deploy.DeploymentDiagnostics()
        with self.assertRaises(ValueError):
            diagnostics.enter(secret)
        self.assertEqual(diagnostics.stage, 'preflight')

    def test_cli_reports_exact_failed_stage_without_retry_or_secret_leak(self):
        secret = 'fixture-password-never-log'
        stages = (
            ('ftps-connect', 'connect', TimeoutError(secret)),
            ('ftps-login-and-tls', 'login', deploy.ftplib.error_perm('530 ' + secret)),
            ('ftps-data-protection', 'prot_p', deploy.ssl.SSLError(secret)),
            ('ftps-passive-mode', 'set_pasv', OSError(secret)),
            ('landing-root', 'cwd', deploy.ftplib.error_perm('550 ' + secret)),
            ('landing-directories', 'mkd', deploy.ftplib.error_perm('530 ' + secret)),
            ('upload-file', 'storbinary', TimeoutError(secret)),
            ('verify-upload-size', 'size', RuntimeError(secret)),
            ('promote-file', 'rename', deploy.ftplib.error_perm('550 ' + secret)),
            ('ftps-close', '__exit__', OSError(secret)),
            ('verify-https-response', None, deploy.urllib.error.URLError(TimeoutError(secret))),
            ('verify-https-hash', None, None),
        )
        for stage, method, error in stages:
            with self.subTest(stage=stage):
                name = 'assets/css/styles.css' if stage == 'landing-directories' else 'index.html'
                source = ROOT / name
                ftp = mock.MagicMock()
                ftp.__enter__.return_value = ftp
                ftp.__exit__.return_value = False
                ftp.size.return_value = source.stat().st_size
                if method:
                    getattr(ftp, method).side_effect = error
                response = mock.MagicMock()
                response.__enter__.return_value = response
                response.status = 200
                response.url = deploy.ORIGIN + '/?release=' + RELEASE
                response.read.return_value = b'wrong commit'
                output, errors = io.StringIO(), io.StringIO()
                with mock.patch.object(deploy.sys, 'argv', ['deploy']), \
                        mock.patch.object(deploy, 'production_files', return_value=[(name, source)]), \
                        mock.patch.object(deploy.subprocess, 'check_output', return_value='a' * 40), \
                        mock.patch.dict(deploy.os.environ, {'GITHUB_SHA': 'a' * 40,
                            'SERVHOST_FTP_USERNAME': 'fixture-username-never-log',
                            'SERVHOST_FTP_PASSWORD': secret, 'SERVHOST_FTP_HOST': ''}, clear=True), \
                        mock.patch.object(deploy.uuid, 'uuid4') as unique, \
                        mock.patch.object(deploy.ssl, 'create_default_context'), \
                        mock.patch.object(deploy, 'ReusingTLS', return_value=ftp) as connection, \
                        mock.patch.object(deploy.urllib.request, 'urlopen', return_value=response) as request, \
                        redirect_stdout(output), redirect_stderr(errors):
                    unique.return_value.hex = 'b' * 32
                    if stage == 'verify-https-response':
                        request.side_effect = error
                    self.assertEqual(deploy.run_cli(), 1)
                self.assertIn('stopped at ' + stage + ' (', errors.getvalue())
                connection.assert_called_once()
                ftp.connect.assert_called_once_with(deploy.HOST, 21)
                self.assertLessEqual(request.call_count, 1)
                self.assertIn('No automatic retry', errors.getvalue())
                self.assertNotIn(secret, output.getvalue() + errors.getvalue())
                self.assertNotIn('fixture-username-never-log', output.getvalue() + errors.getvalue())
                self.assertNotIn('Traceback', errors.getvalue())

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
