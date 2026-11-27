import importlib.util
from pathlib import Path
import os
import subprocess
import tempfile
import unittest

ROOT=Path(__file__).resolve().parents[2]
spec=importlib.util.spec_from_file_location('servicios',ROOT/'resources/instalacion/servicios.py');s=importlib.util.module_from_spec(spec);spec.loader.exec_module(s)

class InstalacionTest(unittest.TestCase):
    def test_todos_los_trabajadores_tienen_usuario_sin_privilegios_y_arranque_persistente(self):
        result=s.unidades('/var/www/micv','/usr/bin/php8.3','www-data')
        jobs=[body for name,body in result.items() if name.endswith('.service') and 'Type=simple' in body]
        self.assertEqual(len(jobs),9)
        for job in jobs:
            self.assertIn('User=www-data',job);self.assertIn('WorkingDirectory=/var/www/micv',job);self.assertIn('Restart=always',job);self.assertIn('WantedBy=multi-user.target',job);self.assertIn('UMask=0077',job)
        self.assertIn('OnCalendar=*-*-* *:*:00',result['micv-scheduler.timer'])
        self.assertIn('schedule:run',result['micv-scheduler.service'])
        self.assertIn('OnUnitInactiveSec=15s',result['micv-tiktok-limpieza.timer'])
        self.assertIn('KillMode=control-group',result['micv-telegram-login.service'])
    def test_parametros_que_inyectarian_configuracion_no_son_admitidos(self):
        for root,php,user in [('/var/www/micv\nExecStart=otro','/usr/bin/php','www-data'),('/var/www/micv','/usr/bin/php;id','www-data'),('/var/www/micv','/usr/bin/php','root'),('/var/www/micv%h','/usr/bin/php','www-data')]:
            with self.assertRaises(ValueError):s.unidades(root,php,user)
        with self.assertRaises(ValueError):s.nginx('/var/www/micv','micv.test; return 200','/run/php/php8.3-fpm.sock')
    def test_nginx_solo_public_y_rutas_laravel_con_consulta(self):
        text=s.nginx('/var/www/micv','micv.example.test','/run/php/php8.3-fpm.sock')
        self.assertIn('root /var/www/micv/public;',text);self.assertIn('try_files $uri $uri/ /index.php?$query_string;',text);self.assertIn('fastcgi_pass unix:/run/php/php8.3-fpm.sock;',text);self.assertIn('deny all',text)
    def test_plan_no_ejecuta_gestores_de_paquetes_ni_servicios(self):
        with tempfile.TemporaryDirectory() as d:
            for name in ['systemctl','apt-get','composer','curl']:
                p=Path(d)/name;p.write_text('#!/bin/sh\necho EJECUCION_NO_ESPERADA\nexit 42\n');p.chmod(0o755)
            env=os.environ.copy();env['PATH']=d+':'+env['PATH'];env['MICV_PHP_BIN']='/usr/bin/php';r=subprocess.run(['bash',str(ROOT/'bin/instalar-sistema.sh'),'--plan'],capture_output=True,text=True,env=env)
            self.assertEqual(r.returncode,0,r.stderr);self.assertNotIn('EJECUCION_NO_ESPERADA',r.stdout);self.assertIn('micv-video.service',r.stdout);self.assertIn('micv-postulaciones.service',r.stdout)

if __name__=='__main__':unittest.main()
