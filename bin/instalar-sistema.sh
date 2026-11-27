#!/usr/bin/env bash
# Instalación y actualización general de Mi CV. --plan solo muestra los servicios.
set -Eeuo pipefail
micv_raiz="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
micv_usuario="${MICV_WEB_USUARIO:-www-data}"
micv_php="${MICV_PHP_BIN:-$(command -v php || true)}"
micv_tmp=''; micv_dominio=''; micv_https_correo=''; micv_paquetes=1; micv_plan=0; micv_voz=auto; micv_generar_clave=0
while [[ $# -gt 0 ]]; do
    case "$1" in
        --dominio) micv_dominio="${2:?Falta el dominio}"; shift 2;;
        --https-correo) micv_https_correo="${2:?Falta el correo para el certificado}"; shift 2;;
        --sin-paquetes) micv_paquetes=0; shift;;
        --sin-voz-neuronal) micv_voz=no; shift;;
        --generar-clave) micv_generar_clave=1; shift;;
        --plan) micv_plan=1; shift;;
        *) echo "Opción desconocida: $1" >&2; exit 1;;
    esac
done
[[ "$micv_raiz" =~ ^/[A-Za-z0-9_./+-]+$ ]] || { echo 'Use una ruta absoluta sin espacios.' >&2; exit 1; }
if (( micv_plan )); then
    python3 "$micv_raiz/resources/instalacion/servicios.py" --raiz "$micv_raiz" --php "${micv_php:-/usr/bin/php}" --usuario "$micv_usuario"
    echo 'Plan: dependencias PHP/Node/Python, Chromium, FFmpeg/eSpeak, migraciones, permisos, todos los trabajadores y limpieza programada.'
    echo 'No se han instalado paquetes, cambiado archivos ni iniciado servicios.'
    exit 0
fi
[[ "$micv_usuario" != root ]] || { echo "El usuario web debe ser una cuenta sin privilegios, por ejemplo www-data." >&2; exit 1; }
[[ $EUID -eq 0 ]] || { echo 'Ejecute: sudo bash bin/instalar-sistema.sh' >&2; exit 1; }
[[ -z "$micv_dominio" || "$micv_dominio" =~ ^[A-Za-z0-9][A-Za-z0-9.-]*$ ]] || { echo 'Dominio inválido.' >&2; exit 1; }
[[ -z "$micv_https_correo" || -n "$micv_dominio" ]] || { echo '--https-correo requiere --dominio.' >&2; exit 1; }
cd "$micv_raiz"
[[ -f composer.lock && -f package-lock.json && -f .env.example ]] || { echo 'Falta el proyecto completo con sus lockfiles.' >&2; exit 1; }
micv_nuevo=0
if [[ ! -f .env ]]; then
    micv_nuevo=1; micv_generar_clave=1
    [[ -n "$micv_dominio" ]] || { echo 'VPS nuevo: use --dominio su-dominio.com, o prepare antes su .env para MySQL.' >&2; exit 1; }
fi
if [[ -n "$micv_dominio" && -z "$micv_https_correo" && -f "/etc/nginx/sites-available/micv-$micv_dominio" ]] && grep -q 'ssl_certificate' "/etc/nginx/sites-available/micv-$micv_dominio"; then
    echo 'Se conserva el sitio HTTPS existente. No se reescribe Nginx.'
    micv_dominio=''
fi
command -v systemctl >/dev/null
command -v runuser >/dev/null
exec 9>"/run/lock/micv-$(printf '%s' "$micv_raiz" | sha256sum | cut -c1-12).lock"
flock -n 9 || { echo 'Ya hay una instalación de Mi CV en ejecución.' >&2; exit 1; }
trap '[[ -z "${micv_tmp:-}" || ! -d "$micv_tmp" ]] || rm -rf -- "$micv_tmp"' EXIT
trap 'echo "La instalación se detuvo en la línea $LINENO. Corrija el error indicado y vuelva a ejecutarla; no se borra la base de datos." >&2' ERR
if (( micv_paquetes )); then
    command -v apt-get >/dev/null || { echo 'Instalador de paquetes compatible con Ubuntu/Debian. Use --sin-paquetes en otro sistema ya preparado.' >&2; exit 1; }
    echo '[1/9] Dependencias del sistema…'
    apt-get update
    micv_apt=(nginx composer git curl ca-certificates unzip xz-utils php-cli php-fpm php-mysql php-sqlite3 php-mbstring php-xml php-curl php-zip php-gd php-intl php-bcmath python3 python3-venv ffmpeg espeak-ng fonts-dejavu-core fonts-liberation fontconfig libnss3 libx11-6 libxcomposite1 libxdamage1 libxext6 libxfixes3 libxrandr2 libgbm1 libxcb1 libdrm2 libpango-1.0-0 libcairo2)
    for micv_alternativas in 'libasound2t64 libasound2' 'libatk1.0-0t64 libatk1.0-0' 'libatk-bridge2.0-0t64 libatk-bridge2.0-0' 'libcups2t64 libcups2' 'libgtk-3-0t64 libgtk-3-0'; do
        for micv_paquete in $micv_alternativas; do
            if apt-cache show "$micv_paquete" >/dev/null 2>&1; then micv_apt+=("$micv_paquete"); break; fi
        done
    done
    [[ -z "$micv_https_correo" ]] || micv_apt+=(certbot python3-certbot-nginx)
    DEBIAN_FRONTEND=noninteractive apt-get install -y "${micv_apt[@]}"
fi
micv_php="${MICV_PHP_BIN:-$(command -v php)}"
[[ "$micv_php" =~ ^/[A-Za-z0-9_./+-]+$ ]] || { echo 'Ruta PHP inválida.' >&2; exit 1; }
"$micv_php" -r 'if(PHP_VERSION_ID<80200)exit(1);foreach(["curl","mbstring","dom","zip","fileinfo","openssl","pdo"]as$e)if(!extension_loaded($e)){fwrite(STDERR,"Falta extensión PHP: $e\n");exit(1);}'
id "$micv_usuario" >/dev/null
mkdir -p storage/app/private storage/framework/{sessions,views,cache/data} storage/logs bootstrap/cache .cache/puppeteer
echo '[2/9] Deteniendo trabajadores para actualizar…'
micv_unidades=(micv-cola micv-postulaciones micv-video micv-tiktok micv-tiktok-pdf micv-tiktok-evidencias micv-tiktok-telegram micv-telegram-cv micv-telegram-login micv-scheduler.timer micv-tiktok-limpieza.timer)
systemctl stop "${micv_unidades[@]}" 2>/dev/null || true
echo '[3/9] Dependencias PHP y Node…'
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
micv_node="$(command -v node || true)"
if [[ -z "$micv_node" ]] || ! "$micv_node" -e 'if(Number(process.versions.node.split(".")[0])<22)process.exit(1)'; then
    case "$(uname -m)" in x86_64) micv_arch=x64;; aarch64) micv_arch=arm64;; *) echo 'Arquitectura Node no admitida.' >&2; exit 1;; esac
    micv_tmp="$(mktemp -d)"
    curl --fail --location --retry 2 --proto '=https' https://nodejs.org/dist/latest-v22.x/SHASUMS256.txt -o "$micv_tmp/SHASUMS256.txt"
    micv_archivo="$(awk -v arch="$micv_arch" '$2 ~ ("^node-v22\\.[0-9]+\\.[0-9]+-linux-" arch "\\.tar\\.xz$") {print $2}' "$micv_tmp/SHASUMS256.txt")"
    [[ "$micv_archivo" =~ ^node-v22\.[0-9]+\.[0-9]+-linux-(x64|arm64)\.tar\.xz$ ]] || { echo 'No se pudo identificar Node LTS.' >&2; exit 1; }
    curl --fail --location --retry 2 --proto '=https' "https://nodejs.org/dist/latest-v22.x/$micv_archivo" -o "$micv_tmp/$micv_archivo"
    (cd "$micv_tmp"; awk -v file="$micv_archivo" '$2==file' SHASUMS256.txt | sha256sum --check --status)
    mkdir -p /opt/micv/node22
    tar -xJf "$micv_tmp/$micv_archivo" -C /opt/micv/node22 --strip-components=1 --no-same-owner
    rm -rf -- "$micv_tmp"
    micv_node=/opt/micv/node22/bin/node
fi
micv_node_dir="$(dirname "$micv_node")"
micv_npm="$micv_node_dir/npm"
[[ -x "$micv_npm" ]] || micv_npm="$(command -v npm)"
env PATH="$micv_node_dir:$PATH" PUPPETEER_SKIP_DOWNLOAD=true "$micv_npm" ci --omit=dev --ignore-scripts
micv_chrome="${MICV_CHROME_PATH:-$("$micv_php" resources/instalacion/configurar.php --chrome)}"
if [[ -z "$micv_chrome" || ! -x "$micv_chrome" ]]; then
    echo '[4/9] Instalando Chromium de Puppeteer…'
    env PATH="$micv_node_dir:$PATH" PUPPETEER_CACHE_DIR="$micv_raiz/.cache/puppeteer" "$micv_npm" exec -- puppeteer browsers install chrome
    micv_chrome="$(env PUPPETEER_CACHE_DIR="$micv_raiz/.cache/puppeteer" "$micv_node" -e 'process.stdout.write(require("puppeteer").executablePath())')"
fi
[[ -x "$micv_chrome" ]] || { echo 'No se encontró Chromium ejecutable.' >&2; exit 1; }
echo '[5/9] Configuración y base de datos…'
python3 - "$micv_nuevo" "$micv_dominio" "$micv_node" "$micv_npm" "$micv_chrome" <<'PY' | "$micv_php" resources/instalacion/configurar.php
import json,sys
print(json.dumps(dict(nuevo=sys.argv[1]=='1',dominio=sys.argv[2],node=sys.argv[3],npm=sys.argv[4],chrome=sys.argv[5])))
PY
if [[ "$("$micv_php" resources/instalacion/configurar.php --mysql-local)" == si ]]; then
    if (( micv_paquetes )); then DEBIAN_FRONTEND=noninteractive apt-get install -y mariadb-server; systemctl enable --now mariadb; fi
    "$micv_php" resources/instalacion/mysql.php
fi
chown root:"$micv_usuario" .env
chmod 640 .env
chgrp "$micv_usuario" "$micv_raiz"
chmod g+rx "$micv_raiz"
for micv_fuente in app bootstrap config database lang public resources routes vendor node_modules bin artisan composer.json package.json .puppeteerrc.cjs; do
    [[ ! -e "$micv_fuente" ]] || { chgrp -hR "$micv_usuario" "$micv_fuente"; chmod -R g+rX "$micv_fuente"; }
done
chown -hR "$micv_usuario:$micv_usuario" storage bootstrap/cache .cache
chmod 700 storage/app/private
if [[ "$("$micv_php" resources/instalacion/configurar.php --clave-vacia)" == si ]]; then
    (( micv_generar_clave )) || { echo "APP_KEY está vacío en una configuración existente. Restaure la clave original; para una base nueva use --generar-clave." >&2; exit 1; }
    runuser -u "$micv_usuario" -- "$micv_php" artisan key:generate --force
fi
runuser -u "$micv_usuario" -- "$micv_php" artisan optimize:clear
runuser -u "$micv_usuario" -- "$micv_php" artisan migrate --force
runuser -u "$micv_usuario" -- "$micv_php" artisan micv:cv-limpiar-temporales
runuser -u "$micv_usuario" -- "$micv_php" artisan micv:tiktok-limpiar-informes
echo '[6/9] Capturador y cuenta de Telegram…'
for micv_modulo in tiktok telegram-cv; do
    micv_venv="$micv_raiz/storage/app/$micv_modulo-venv"
    python3 -m venv "$micv_venv"
    "$micv_venv/bin/python" -m pip install --disable-pip-version-check --no-cache-dir -r "resources/$micv_modulo/requirements.txt"
    if [[ "$micv_modulo" == tiktok ]]; then "$micv_venv/bin/python" -c 'from TikTokLive import TikTokLiveClient'; else "$micv_venv/bin/python" -c 'from telethon import TelegramClient'; fi
done
chown -hR "$micv_usuario:$micv_usuario" storage
echo '[7/9] Servicios generales, programación y limpieza…'
python3 resources/instalacion/servicios.py --raiz "$micv_raiz" --php "$micv_php" --usuario "$micv_usuario" --destino /etc/systemd/system
micv_cron=0
for micv_cron_usuario in root "$micv_usuario" "${SUDO_USER:-root}"; do
    if crontab -u "$micv_cron_usuario" -l 2>/dev/null | grep -F "$micv_raiz" | grep -q 'schedule:run'; then micv_cron=1; fi
done
systemd-analyze verify /etc/systemd/system/micv-*.service /etc/systemd/system/micv-*.timer
systemctl daemon-reload
micv_activar=(micv-cola micv-postulaciones micv-video micv-tiktok micv-tiktok-pdf micv-tiktok-evidencias micv-tiktok-telegram micv-telegram-cv micv-telegram-login micv-tiktok-limpieza.timer)
if (( ! micv_cron )); then micv_activar+=(micv-scheduler.timer); else systemctl disable --now micv-scheduler.timer 2>/dev/null || true; echo 'Se conserva el cron existente de schedule:run.'; fi
systemctl enable "${micv_activar[@]}"
systemctl restart "${micv_activar[@]}"
runuser -u "$micv_usuario" -- "$micv_php" artisan micv:comprobar-servidor --pdf
echo '[8/9] Cuenta administrativa y acceso web…'
if ! runuser -u "$micv_usuario" -- "$micv_php" artisan micv:comprobar-servidor --admin; then
    [[ -t 0 ]] || { echo 'Cree el administrador con: sudo -u www-data php artisan micv:crear-admin y vuelva a ejecutar el instalador.' >&2; exit 1; }
    runuser -u "$micv_usuario" -- "$micv_php" artisan micv:crear-admin
fi
if [[ -n "$micv_dominio" ]]; then
    micv_sitio="/etc/nginx/sites-available/micv-$micv_dominio"
    if [[ -f "$micv_sitio" ]] && ! head -1 "$micv_sitio" | grep -q 'Gestionado por Mi CV'; then echo 'El sitio Nginx existente no pertenece al instalador. Consérvelo y configure el dominio manualmente.' >&2; exit 1; fi
    micv_version="$("$micv_php" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
    systemctl enable --now "php$micv_version-fpm" nginx
    micv_sitio_anterior=0
    if [[ -f "$micv_sitio" ]]; then cp -p "$micv_sitio" "$micv_sitio.bak"; micv_sitio_anterior=1; fi
    python3 resources/instalacion/servicios.py --raiz "$micv_raiz" --php "$micv_php" --usuario "$micv_usuario" --dominio "$micv_dominio" --socket "/run/php/php$micv_version-fpm.sock" --nginx "$micv_sitio"
    micv_enlace="/etc/nginx/sites-enabled/micv-$micv_dominio"
    if [[ -e "$micv_enlace" && ! -L "$micv_enlace" ]]; then echo 'El archivo Nginx habilitado ya existe y no se reemplazará.' >&2; exit 1; fi
    if [[ -L "$micv_enlace" && "$(readlink -f "$micv_enlace")" != "$micv_sitio" ]]; then echo 'El enlace Nginx apunta a otra configuración. Consérvelo y configure el sitio manualmente.' >&2; exit 1; fi
    ln -sfn "$micv_sitio" "$micv_enlace"
    if ! nginx -t; then
        if (( micv_sitio_anterior )); then cp -p "$micv_sitio.bak" "$micv_sitio"; else rm -f -- "$micv_sitio" "$micv_enlace"; fi
        echo 'La configuración web no pasó la comprobación. Se restauró el archivo anterior de Mi CV.' >&2
        exit 1
    fi
    systemctl reload nginx
    if [[ -n "$micv_https_correo" ]]; then
        if ! certbot --nginx -d "$micv_dominio" --email "$micv_https_correo" --agree-tos --no-eff-email --non-interactive --redirect; then
            if (( micv_sitio_anterior )); then cp -p "$micv_sitio.bak" "$micv_sitio"; if nginx -t; then systemctl reload nginx; fi; fi
            echo 'No se pudo completar HTTPS. Revise DNS, correo y puertos antes de reintentar.' >&2
            exit 1
        fi
        systemctl enable --now certbot.timer
        python3 - "$micv_dominio" "$micv_node" "$micv_npm" "$micv_chrome" <<'PY' | "$micv_php" resources/instalacion/configurar.php
import json,sys
print(json.dumps(dict(nuevo=False,dominio=sys.argv[1],node=sys.argv[2],npm=sys.argv[3],chrome=sys.argv[4],https=True)))
PY
        chown root:"$micv_usuario" .env; chmod 640 .env
        runuser -u "$micv_usuario" -- "$micv_php" artisan config:clear
    fi
fi
echo '[9/9] Voz y modelos complementarios…'
if [[ "$micv_voz" == auto && ! -x storage/app/video-voz/bin/pocket-tts && "$(uname -m)" == x86_64 ]] && grep -qw avx2 /proc/cpuinfo; then
    if ! MICV_PHP_BIN="$micv_php" MICV_VIDEO_USUARIO="$micv_usuario" bash bin/instalar-video-cine.sh; then echo 'Voz neuronal pendiente: el video puede usar eSpeak instalado. Puede repetir bin/instalar-video-cine.sh.' >&2; fi
fi
if [[ ! -f storage/app/modelos/u2net_human_seg.onnx ]]; then
    if ! runuser -u "$micv_usuario" -- "$micv_php" artisan micv:modelo-fondo --no-interaction; then echo 'Modelo de fondo pendiente. Puede repetir php artisan micv:modelo-fondo.' >&2; fi
fi
systemctl --no-pager --full status "${micv_activar[@]}"
echo 'Mi CV instalado/actualizado. Los servicios arrancarán también al reiniciar el VPS.'
echo 'Configure Gmail y vincule Telegram desde el panel; las credenciales existentes se conservan.'
