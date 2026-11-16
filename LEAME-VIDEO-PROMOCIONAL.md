# Video promocional de Mi CV

El administrador puede abrir **Video promocional**, pulsar **Generar video** y previsualizar o descargar el MP4. El registro público debe estar abierto. Cada video reproduce las vistas Blade y los recursos vigentes, con la marca del sitio y un perfil ficticio de La Dalia, Matagalpa. La demostración funciona en memoria y no crea usuarios ni envía correos.

## Activarlo en el VPS

Conserve su `.env`, base de datos y almacenamiento al actualizar. Confirme que `APP_URL` sea `https://micv.sistemajz.com` para que aparezca el dominio correcto en el video.

1. Instale FFmpeg y eSpeak NG en Ubuntu o Debian.

   ```bash
   sudo apt install ffmpeg espeak-ng fonts-dejavu-core
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


## Mejoras del video y seguimiento

- Composición vertical con capítulos, contador de pasos, navegador enmarcado y cierre con llamada a la acción.
- Escritura progresiva sobre los formularios reales; la demostración no envía los formularios ni crea cuentas.
- Movimiento de cámara suave a 30 fps, cursor animado, pulsación de enfoque y transiciones de entrada y salida.
- Subtítulos grandes por frases, calculados a partir de la duración real de cada WAV. El tiempo de cada frase dentro del audio es estimado por su longitud; no se usa reconocimiento ni alineación fonética.
- Voz con nivel normalizado, filtro de ruido grave, pausas de lectura y audio AAC estéreo de 192 kbps. La música es un ambiente original sintetizado localmente. Use `MICV_VIDEO_MUSICA=false` para quitarla.
- H.264 de alta calidad, CRF 18, 1080 × 1920, 30 fps y `faststart` para reproducción web.
- El panel consulta el estado cada 2,5 segundos: preparación, pantallas, narración, animación y exportación. Muestra escena, motor de voz, porcentaje y tiempo transcurrido. Las etapas tienen pesos; durante la codificación el avance se calcula con el tiempo de video procesado por FFmpeg. El 100 % se reserva para el MP4 publicado.
- Al recargar o volver al panel, el estado se recupera del servidor. La consulta pausa en pestañas ocultas y reintenta después de fallos de conexión. Los archivos siguen siendo privados para el administrador que los generó.

No necesita nuevas tablas, paquetes de Node ni servicios de pago. Después de actualizar, ejecute `php artisan optimize:clear` y `php artisan queue:restart`. Mantenga activo el trabajador descrito arriba. No reemplace su `.env`, su base de datos ni su almacenamiento con archivos de ejemplo.

### Voz neuronal recomendada

Para obtener una narración más natural que la voz eSpeak de reserva, instale Piper en un entorno virtual y configure un modelo español. Ejemplo para el modelo mexicano `ald/medium` (CPU; sin clave de API):

```bash
cd /var/www/micv
python3 -m venv storage/app/piper
storage/app/piper/bin/pip install piper-tts==1.8.0
mkdir -p storage/app/modelos/voz
curl -fL https://huggingface.co/rhasspy/piper-voices/resolve/main/es/es_MX/ald/medium/es_MX-ald-medium.onnx -o storage/app/modelos/voz/es_MX-ald-medium.onnx
curl -fL https://huggingface.co/rhasspy/piper-voices/resolve/main/es/es_MX/ald/medium/es_MX-ald-medium.onnx.json -o storage/app/modelos/voz/es_MX-ald-medium.onnx.json
```

Configure las rutas absolutas en su `.env`:

```dotenv
MICV_VIDEO_PIPER=/var/www/micv/storage/app/piper/bin/piper
MICV_VIDEO_MODELO_VOZ=/var/www/micv/storage/app/modelos/voz/es_MX-ald-medium.onnx
MICV_VIDEO_MUSICA=true
```

El usuario de la cola debe poder ejecutar Piper y leer ambos archivos del modelo. Cambie `/var/www/micv` si su instalación usa otra ruta. El panel identifica si la generación usa Piper o eSpeak; si un modelo configurado falla, la generación marca error para permitir revisar la instalación.

Modelo: [ficha de ald/medium](https://huggingface.co/rhasspy/piper-voices/blob/main/es/es_MX/ald/medium/MODEL_CARD), conjunto de voz con licencia Unlicense. El ONNX no se incluye en el ZIP. SHA-256 del modelo probado: `019b3803293c93e34a206dd2e53a3889209a514e786fd7144f7b70196c579b63`.

### Comprobaciones

```bash
php artisan test --filter=VideoPromocionalTest
npm run test:video
```

Las pruebas cubren privacidad, cola única, progreso persistente, límite del 99 % antes de publicar, estados de error, lectura WAV y construcción segura de subtítulos.
