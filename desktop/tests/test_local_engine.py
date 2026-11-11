import base64,json,os,tempfile,time,unittest
from pathlib import Path
from unittest.mock import patch
from micv_desktop.local_engine import LocalEngine,EngineError

class LocalEngineTests(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory();self.addCleanup(self.tmp.cleanup)
        self.root=Path(self.tmp.name);self.bundle=self.root/'bundle';self.space=self.root/'profile';self.space.mkdir()
        for name in ('artisan','public/index.php','vendor/autoload.php'):
            p=self.bundle/'laravel'/name;p.parent.mkdir(parents=True,exist_ok=True);p.touch()
        self.claims={'user_id':7,'tenant_id':3,'device':'fixture','valid_until':int(time.time())+600}
        self.manifest=dict(self.claims,version=1,state='ready',app_key='base64:'+base64.b64encode(os.urandom(32)).decode())
        (self.space/'database.sqlite').touch();self.write()
        self.engine=LocalEngine(self.bundle,self.space,lambda:self.claims);self.engine.port=12345
    def write(self):
        (self.space/'workspace.json').write_text(json.dumps(self.manifest))
    def test_no_inherited_server_secrets(self):
        with patch.dict(os.environ,{'DB_PASSWORD':'PRIVATE','DB_URL':'mysql://secret','APP_DEBUG':'true','MAIL_PASSWORD':'SECRET'}):
            app,env=self.engine.configuration()
        self.assertNotIn('DB_PASSWORD',env);self.assertNotIn('DB_URL',env);self.assertNotIn('MAIL_PASSWORD',env)
        self.assertEqual(env['APP_DEBUG'],'false');self.assertEqual(env['DB_CONNECTION'],'sqlite')
        self.assertEqual(env['DB_DATABASE'],str(self.space/'database.sqlite'))
    def test_wrong_account_or_device_is_rejected(self):
        for key in ('user_id','tenant_id','device'):
            with self.subTest(key=key):
                saved=self.manifest[key];self.manifest[key]='other';self.write()
                with self.assertRaises(EngineError):self.engine.configuration()
                self.manifest[key]=saved
    def test_expired_access_is_rejected(self):
        self.claims['valid_until']=0
        with self.assertRaises(EngineError):self.engine.configuration()
    def test_server_env_is_never_loaded(self):
        (self.bundle/'laravel/.env').write_text('DB_PASSWORD=secret')
        with self.assertRaises(EngineError):self.engine.configuration()
    def test_pending_workspace_is_not_opened(self):
        self.manifest['state']='downloading';self.write()
        with self.assertRaises(EngineError):self.engine.configuration()
    def test_incomplete_database_is_not_recreated(self):
        (self.space/'database.sqlite').unlink()
        with self.assertRaises(EngineError):self.engine.configuration()
        self.assertFalse((self.space/'database.sqlite').exists())
    def test_server_cached_configuration_is_rejected(self):
        cache=self.bundle/'laravel/bootstrap/cache';cache.mkdir(parents=True)
        (cache/'config.php').write_text('<?php return [];')
        with self.assertRaises(EngineError):self.engine.configuration()
