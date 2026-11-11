"""Isolated Laravel process for the next desktop runtime.

Only starts an already provisioned, licensed workspace. Never imports a live
server .env, signs licenses, or creates an admin.
"""
from __future__ import annotations
import base64
import hmac
import json
import os
from pathlib import Path
import secrets
import socket
import sqlite3
from contextlib import closing
import subprocess
import tempfile
import time
from urllib.request import Request, urlopen
from .security import Vault

class EngineError(RuntimeError):
    pass

class LocalEngine:
    def __init__(self, bundle: Path, workspace: Path, authorize):
        self.bundle=Path(bundle).resolve()
        self.workspace=Path(workspace).resolve()
        self.authorize=authorize
        self.process=None
        self.log=None
        self.token=secrets.token_urlsafe(48)
        self.port=0
        self.validity_file = self.workspace / 'cache' / ('lease-' + secrets.token_hex(16))
        self.published_until = None

    @property
    def origin(self):
        return f'http://127.0.0.1:{self.port}'

    def request_headers(self):
        return {b'X-MiCV-Local': self.token.encode('ascii')}

    def configuration(self):
        claims=self.authorize()
        if not isinstance(claims,dict) or claims.get('valid_until',0)<=time.time():
            raise EngineError('Conecte el equipo para renovar su acceso.')
        try:
            manifest=json.loads((self.workspace/'workspace.json').read_text(encoding='utf-8'))
        except (OSError, ValueError):
            raise EngineError('El espacio Laravel local todavía no está preparado. Sus datos de la versión anterior se conservan.') from None
        if manifest.get('version')!=1 or manifest.get('state')!='ready':
            raise EngineError('El espacio local no ha terminado de prepararse.')
        for key in ('user_id','tenant_id','device'):
            if manifest.get(key)!=claims.get(key):
                raise EngineError('El espacio local pertenece a otra cuenta o empresa.')
        if 'role' in manifest and manifest['role'] != claims.get('role'):
            raise EngineError('El rol de la cuenta cambió. Prepare una copia actualizada.')
        permissions = manifest.get('permissions', [])
        if not set(permissions).issubset(claims.get('permissions', [])):
            raise EngineError('Los permisos cambiaron. Actualice los datos locales.')
        try:
            protected=self.workspace/'workspace.secret'
            if protected.is_file():
                key=Vault(self.workspace).unpack('laravel-app-key',protected.read_bytes())
            else:
                key=manifest.get('app_key','')
            if not key.startswith('base64:') or len(base64.b64decode(key[7:],validate=True))!=32:raise ValueError()
        except Exception:
            raise EngineError('La clave de cifrado local no es válida.') from None
        db=self.workspace/'database.sqlite'
        if not db.is_file() or db.is_symlink():
            raise EngineError('No se encontró la base local preparada.')
        app, env = self.environment(key, claims, manifest.get('timezone','America/Managua'))
        env['MICV_LOCAL_USER'] = str(claims['user_id'])
        env['MICV_LOCAL_TENANT'] = str(claims['tenant_id'])
        env['MICV_LOCAL_ROLE'] = claims.get('role', '')
        env['MICV_LOCAL_PERMISSIONS'] = json.dumps(manifest.get('permissions', []))
        return app, env

    def environment(self, key, claims, timezone='America/Managua'):
        app=self.bundle/'laravel'
        for name in ('artisan','public/index.php','vendor/autoload.php'):
            if not (app/name).is_file():raise EngineError('El instalador está incompleto: falta Laravel o sus dependencias.')
        if (app/'.env').exists() or (app/'bootstrap/cache/config.php').exists():
            raise EngineError('El paquete contiene configuración de servidor. No se puede iniciar de forma segura.')
        # Allow only operating-system essentials, never inherited DB, APP, SMTP,
        # proxy or other server configuration from a developer shell.
        allowed={'SYSTEMROOT','WINDIR','TEMP','TMP','LOCALAPPDATA','APPDATA','USERPROFILE','PATH','LANG','LC_ALL','HOME'}
        env={k:v for k,v in os.environ.items() if k.upper() in allowed}
        storage=self.workspace/'storage'
        for sub in ('app/private','app/public','framework/cache/data','framework/sessions','framework/views','logs'):
            (storage/sub).mkdir(parents=True,exist_ok=True)
        cache=self.workspace/'cache';cache.mkdir(exist_ok=True)
        env.update({
            'APP_ENV':'production','APP_DEBUG':'false','APP_KEY':key,
            'MICV_PHP_EXT':str(self.bundle/'php/ext'),
            'APP_URL':self.origin,'ASSET_URL':self.origin,'APP_TIMEZONE':timezone,
            'DB_CONNECTION':'sqlite','DB_DATABASE':str(self.workspace/'database.sqlite'),'DB_FOREIGN_KEYS':'true',
            'SESSION_DRIVER':'file','SESSION_SECURE_COOKIE':'false','SESSION_SAME_SITE':'strict',
            'SESSION_COOKIE':'micv_local_'+str(claims['user_id']),
            'CACHE_STORE':'file','QUEUE_CONNECTION':'database','MAIL_MAILER':'log',
            'LARAVEL_STORAGE_PATH':str(storage),'VIEW_COMPILED_PATH':str(storage/'framework/views'),
            'APP_CONFIG_CACHE':str(cache/'config.php'),'APP_ROUTES_CACHE':str(cache/'routes.php'),
            'APP_SERVICES_CACHE':str(cache/'services.php'),'APP_PACKAGES_CACHE':str(cache/'packages.php'),
            'MICV_LOCAL_TOKEN':self.token,'MICV_LOCAL_PORT':str(self.port),
            'MICV_LOCAL_VALID_UNTIL':str(int(claims['valid_until'])),
            'MICV_LOCAL_PUBLIC':str(app/'public'),'MICV_CHROME_NO_SANDBOX':'false',
            'MICV_NODE_MODULES':str(app/'node_modules'),
            'MICV_MODELO_FONDO':str(self.bundle/'models/u2net_human_seg.onnx'),
        })
        for variable,path in [('MICV_NODE_BINARY','node/node.exe'),('MICV_CHROME_PATH','chrome/chrome.exe')]:
            target=self.bundle/path
            if target.exists():env[variable]=str(target)
        return app,env

    def publish_expiry(self, expires):
        if expires == self.published_until:
            return
        message = str(expires).encode('ascii')
        signature = hmac.digest(self.token.encode('ascii'), message, 'sha256').hex().encode('ascii')
        with tempfile.NamedTemporaryFile(dir=self.validity_file.parent, delete=False) as handle:
            path = Path(handle.name)
            handle.write(message + b':' + signature)
        try:
            os.replace(path, self.validity_file)
            self.published_until = expires
        finally:
            path.unlink(missing_ok=True)

    def refresh_authorization(self):
        _, env = self.configuration()
        if self.process is None or self.process.poll() is not None:
            raise EngineError('El motor local se detuvo. Vuelva a abrir el espacio local.')
        self.publish_expiry(int(env['MICV_LOCAL_VALID_UNTIL']))

    def upgrade_pos(self, app, env, php):
        # Apply only the additive POS migration to a previously prepared workspace.
        db = self.workspace / 'database.sqlite'
        try:
            with closing(sqlite3.connect(db.as_uri() + '?mode=ro', uri=True)) as conn:
                installed = conn.execute(
                    "SELECT 1 FROM sqlite_master WHERE type='table' AND name='pos_pendientes'"
                ).fetchone()
            if installed:
                return
            migration = 'database/migrations/2026_11_11_000002_create_pos_pendientes.php'
            subprocess.run(
                [str(php), 'artisan', 'migrate', '--force', '--path=' + migration],
                cwd=app, env=env, stdin=subprocess.DEVNULL, stdout=self.log, stderr=self.log,
                timeout=30, check=True, creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0),
            )
        except (OSError, sqlite3.Error, subprocess.SubprocessError) as exc:
            raise EngineError('No se pudo actualizar la base local para el POS. Revise el registro del motor.') from exc

    def start(self,timeout=20):
        if self.process is not None:raise EngineError('El motor local ya está iniciado.')
        php=self.bundle/'php'/('php.exe' if os.name=='nt' else 'php')
        router=self.bundle/'router.php'
        if not php.is_file() or not router.is_file():raise EngineError('Falta el motor PHP del instalador.')
        with socket.socket() as sock:
            sock.bind(('127.0.0.1',0));self.port=sock.getsockname()[1]
        app,env=self.configuration()
        try:
            self.publish_expiry(int(env['MICV_LOCAL_VALID_UNTIL']))
            env['MICV_LOCAL_LEASE_FILE'] = str(self.validity_file)
            self.log=(self.workspace/'storage/logs/engine.log').open('ab')
            self.upgrade_pos(app, env, php)
            self.process=subprocess.Popen([str(php),'-d','display_errors=0','-d','expose_php=0',
                '-S',f'127.0.0.1:{self.port}','-t',str(app/'public'),str(router)],
                cwd=app,env=env,stdin=subprocess.DEVNULL,stdout=self.log,stderr=self.log,
                creationflags=getattr(subprocess,'CREATE_NO_WINDOW',0))
            deadline=time.monotonic()+timeout
            while time.monotonic()<deadline:
                if self.process.poll() is not None:raise EngineError('PHP no pudo iniciar. Revise el registro local del motor.')
                try:
                    request=Request(self.origin+'/_desktop/health',headers={'X-MiCV-Local':self.token})
                    with urlopen(request,timeout=.5) as response:
                        if json.load(response).get('status')=='ready':return self.origin
                except (OSError,ValueError):pass
                time.sleep(.08)
            raise EngineError('El motor local no respondió a tiempo.')
        except BaseException:
            self.stop();raise

    def stop(self):
        if self.process is not None:
            try:
                self.process.terminate()
                try:self.process.wait(timeout=4)
                except subprocess.TimeoutExpired:self.process.kill();self.process.wait(timeout=2)
            finally:self.process=None
        if self.log is not None:self.log.close();self.log=None
        self.validity_file.unlink(missing_ok=True)
        self.published_until = None

    def __enter__(self):self.start();return self
    def __exit__(self,*exc):self.stop()
