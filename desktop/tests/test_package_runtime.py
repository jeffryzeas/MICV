import tempfile
import unittest
from pathlib import Path
from micv_desktop.package_runtime import build_runtime, PROJECT_DIRECTORIES

class RuntimePackageTests(unittest.TestCase):
    def fixture(self, folder):
        project, engines = folder/'project', folder/'engines'
        for name in PROJECT_DIRECTORIES:
            (project/name).mkdir(parents=True)
        for name in ('artisan','composer.json','vendor/autoload.php','public/index.php','lang/es/validation.php','node_modules/puppeteer/package.json','node_modules/sharp/package.json','desktop/runtime/router.php'):
            target=project/name;target.parent.mkdir(parents=True,exist_ok=True);target.write_text('fixture')
        for name in ('php/php.exe','node/node.exe','chrome/chrome.exe'):
            target=engines/name;target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(b'MZfixture')
        return project,engines

    def test_bundle_copies_dependencies_and_excludes_server_state(self):
        with tempfile.TemporaryDirectory() as directory:
            folder=Path(directory);project,engines=self.fixture(folder)
            for name in ('.env','public/.env','database/production.sqlite','bootstrap/cache/config.php','node_modules/.npmrc'):
                target=project/name;target.parent.mkdir(parents=True,exist_ok=True);target.write_text('SECRET')
            output=build_runtime(project,engines,folder/'output')
            self.assertTrue((output/'laravel/vendor/autoload.php').is_file())
            self.assertTrue((output/'router.php').is_file())
            self.assertTrue((output/'laravel/lang/es/validation.php').is_file())
            self.assertFalse((output/'laravel/bootstrap/cache/config.php').exists())
            for path in output.rglob('*'):
                if path.is_file():self.assertNotIn(b'SECRET',path.read_bytes())

    def test_failure_leaves_output_unpublished(self):
        with tempfile.TemporaryDirectory() as directory:
            folder=Path(directory);project,engines=self.fixture(folder)
            try:
                (project/'resources/escape').symlink_to(project/'artisan')
            except OSError:
                self.skipTest('El sistema no permite crear enlaces simbólicos en este perfil.')
            with self.assertRaises(ValueError):build_runtime(project,engines,folder/'output')
            self.assertFalse((folder/'output').exists())

    def test_existing_output_and_invalid_engines_are_rejected(self):
        with tempfile.TemporaryDirectory() as directory:
            folder=Path(directory);project,engines=self.fixture(folder)
            output=folder/'output';output.mkdir();(output/'keep').write_text('keep')
            with self.assertRaises(ValueError):build_runtime(project,engines,output)
            self.assertEqual((output/'keep').read_text(),'keep')
            (engines/'php/php.exe').write_bytes(b'linux')
            with self.assertRaises(ValueError):build_runtime(project,engines,folder/'new')
