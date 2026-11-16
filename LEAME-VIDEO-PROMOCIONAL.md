# Video promocional de Mi CV

El administrador puede abrir **Video promocional**, pulsar **Generar video** y previsualizar o descargar el MP4. El registro público debe estar abierto. Cada video reproduce las vistas Blade y los recursos vigentes, con la marca del sitio y un perfil ficticio de La Dalia, Matagalpa. La demostración funciona en memoria y no crea usuarios ni envía correos.

## Activarlo en el VPS

Conserve su `.env`, base de datos y almacenamiento al actualizar. Confirme que `APP_URL` sea `https://micv.sistemajz.com` para que aparezca el dominio correcto en el video.

1. Instale FFmpeg y eSpeak NG en Ubuntu o Debian.

   ```bash
   sudo apt install ffmpeg espeak-ng
   ```

2. Use el mismo Node, Puppeteer y Chromium que usa Mi CV para PDF. Si es una instalación nueva, instale las dependencias con `npm ci --omit=dev` y siga la guía de Chromium del README. Si el navegador se instaló en otra ruta, use el valor existente `MICV_CHROME_PATH`. El usuario de PHP y el usuario de la cola requieren acceso de escritura a `storage` y deben compartir ese directorio.
3. Limpie las cachés tras actualizar y confirme que existan las tablas estándar de Laravel, incluidas `jobs` y `failed_jobs`. Esta actualización no agrega migraciones.

   ```bash
   cd /var/www/micv
   php artisan optimize:clear
   php artisan migrate:status
   ```

4. Inicie el trabajador de la cola del video. Puede probarlo en SSH con la siguiente instrucción mientras genera un video desde el panel.

   ```bash
   cd /var/www/micv
   sudo -u www-data /usr/bin/php artisan queue:work video-promocional --queue=video-promocional --tries=1 --timeout=570
   ```

Para dejarlo activo después de salir de SSH, ejecute ese trabajador como servicio supervisado. En un VPS donde PHP sirve Mi CV como `www-data`, puede usar este contenido en `/etc/systemd/system/micv-video.service`. Ajuste la ruta de PHP y el usuario si su instalación usa otros.

```ini
[Unit]
Description=Generador de videos de Mi CV
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/micv
ExecStart=/usr/bin/php /var/www/micv/artisan queue:work video-promocional --queue=video-promocional --tries=1 --timeout=570
Restart=always
RestartSec=5
TimeoutStopSec=600

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now micv-video
```

La cola de video usa la conexión dedicada `video-promocional`. Puede mantener `QUEUE_CONNECTION=sync` para el resto del sistema. La limpieza programada de Laravel retira los videos con más de dos días.

## Voz

El motor incluido usa síntesis en español latinoamericano con eSpeak NG. Para una voz neuronal local, configure un ejecutable Piper y un modelo en español compatible, con su archivo de configuración junto al ONNX.

```dotenv
MICV_VIDEO_PIPER=/usr/local/bin/piper
MICV_VIDEO_MODELO_VOZ=/ruta/al/modelo-es.onnx
```

Tras cambiar `.env`, ejecute `php artisan optimize:clear` y reinicie el trabajador. También puede generar un archivo directamente desde la consola, sin pasar por la cola.

```bash
php artisan micv:video-promocional --salida=/ruta/privada/Mi-CV-Tutorial.mp4
```

El resultado es MP4 con H.264/AAC, 1080 × 1920 y 30 cuadros por segundo. La duración depende de la voz.
