#!/usr/bin/env python3
"""Plantillas revisables de todos los servicios de Mi CV. No instala paquetes ni envía mensajes."""
import argparse
import json
from pathlib import Path
import re

def unidades(raiz,php,usuario):
    for value in (raiz,php):
        if not re.fullmatch(r'/[A-Za-z0-9_./+-]+',value):raise ValueError('Las rutas deben ser absolutas y no contener espacios ni caracteres de control.')
    if usuario=='root' or not re.fullmatch(r'[a-z_][a-z0-9_-]*',usuario):raise ValueError('Usuario de servicio inválido.')
    servicios={
        'micv-cola':('Trabajos generales de Mi CV', 'queue:work database --queue=default --sleep=2 --tries=1 --timeout=60 --max-time=3600',90),
        'micv-postulaciones':('CV a empresas por correo','queue:work postulaciones --queue=postulaciones --sleep=2 --tries=1 --timeout=90 --max-time=3600',110),
        'micv-video':('Generación de video promocional','queue:work video-promocional --queue=video-promocional --sleep=2 --tries=1 --timeout=570',600),
        'micv-tiktok':('Captura pública de TikTok','micv:tiktok-monitor --duracion=0',45),
        'micv-tiktok-pdf':('Informes PDF de TikTok','queue:work tiktok-exportaciones --queue=tiktok-exportaciones --sleep=2 --tries=1 --timeout=570 --max-time=3600',600),
        'micv-tiktok-evidencias':('Fotos públicas de TikTok','queue:work tiktok-evidencias --queue=tiktok-evidencias --sleep=2 --tries=1 --timeout=45 --max-time=3600',60),
        'micv-tiktok-telegram':('Comentarios de TikTok al bot autorizado','micv:tiktok-telegram',30),
        'micv-telegram-cv':('CV desde Telegram del sistema','queue:work telegram-cv --queue=telegram-cv --sleep=2 --tries=1 --timeout=270',300),
        'micv-telegram-login':('Vinculación QR de Telegram del sistema','queue:work telegram-cv-login --queue=telegram-cv-login --sleep=1 --tries=1 --timeout=365',380),
    }
    result={}
    for key,(desc,cmd,timeout) in servicios.items():
        mode='control-group' if key=='micv-telegram-login' else 'mixed'
        result[key+'.service']=f'''# Gestionado por Mi CV
[Unit]
Description={desc}
After=network-online.target
Wants=network-online.target
[Service]
Type=simple
User={usuario}
WorkingDirectory={raiz}
ExecStart={php} {raiz}/artisan {cmd}
Restart=always
RestartSec=5
KillMode={mode}
TimeoutStopSec={timeout}
UMask=0077
NoNewPrivileges=true
Environment=PYTHONDONTWRITEBYTECODE=1
[Install]
WantedBy=multi-user.target
'''
    for key,cmd,seconds in [('micv-scheduler','schedule:run',60),('micv-tiktok-limpieza','micv:tiktok-limpiar-informes --quiet',15)]:
        result[key+'.service']=f'''# Gestionado por Mi CV
[Unit]
Description={key}
[Service]
Type=oneshot
User={usuario}
WorkingDirectory={raiz}
ExecStart={php} {raiz}/artisan {cmd}
TimeoutStartSec=120
UMask=0077
NoNewPrivileges=true
'''
        calendario='OnCalendar=*-*-* *:*:00\nPersistent=true' if key=='micv-scheduler' else f'OnBootSec={seconds}s\nOnUnitInactiveSec={seconds}s'
        result[key+'.timer']=f'''# Gestionado por Mi CV
[Unit]
Description=Temporizador {key}
[Timer]
{calendario}
AccuracySec=1s
Unit={key}.service
[Install]
WantedBy=timers.target
'''
    return result

def nginx(raiz,dominio,socket):
    if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]*',dominio) or not re.fullmatch(r'/[A-Za-z0-9_./+-]+',socket):raise ValueError('Dominio o socket inválido.')
    return r'''# Gestionado por Mi CV
server {
    listen 80;
    listen [::]:80;
    server_name DOMINIO;
    root RAIZ/public;
    index index.php;
    charset utf-8;
    client_max_body_size 25m;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:SOCKET;
        fastcgi_read_timeout 180s;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
'''.replace('RAIZ',raiz).replace('DOMINIO',dominio).replace('SOCKET',socket)

if __name__=='__main__':
    p=argparse.ArgumentParser();p.add_argument('--raiz',required=True);p.add_argument('--php',default='/usr/bin/php');p.add_argument('--usuario',default='www-data');p.add_argument('--destino');p.add_argument('--dominio');p.add_argument('--socket',default='/run/php/php8.3-fpm.sock');p.add_argument('--nginx');a=p.parse_args()
    try:
        items=unidades(a.raiz,a.php,a.usuario)
        if a.destino:
            d=Path(a.destino);d.mkdir(parents=True,exist_ok=True)
            for name,body in items.items():(d/name).write_text(body)
        if a.nginx:Path(a.nginx).write_text(nginx(a.raiz,a.dominio,a.socket))
        print(json.dumps({'servicios':list(items),'raiz':a.raiz},ensure_ascii=False))
    except (ValueError,OSError) as e:p.exit(1,str(e)+'\n')
