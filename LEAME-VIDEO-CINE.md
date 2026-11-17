# Video Cine: CV lateral con foto y voz neuronal

Esta actualización utiliza el diseño **Lateral** (`pro`) real del sistema, con una foto ficticia incluida. Abre mostrando el resultado, explica registro y verificación, demuestra el encuadre de la foto, completa perfil, experiencia y estudios, selecciona Lateral y muestra el diálogo de descarga con **Con foto** y **Español**.

## Actualizar el VPS que ya tiene Mi CV

El ejemplo corresponde a `/var/www/micv`, con PHP y el trabajador ejecutados como `www-data`. Guarde un respaldo del código antes de extraer. La actualización contiene únicamente los archivos indicados en `ARCHIVOS-VIDEO-CINE.txt`: no incluye `.env`, bases de datos, `vendor`, cachés ni archivos de clientes.

1. Entre por SSH. Detenga el servicio para terminar de actualizar antes del siguiente trabajo:

   ```bash
   cd /var/www/micv
   sudo systemctl stop micv-video
   ```

2. Extraiga `Actualizacion-Mi-CV-Cine.zip` **dentro de `/var/www/micv`**. El ZIP contiene `app/`, `resources/`, `public/`, `config/`, `bin/` y las guías directamente, sin otra carpeta `mi-cv` por delante. Conserve su `.env`, su almacenamiento y la base de datos.

   ```bash
   unzip -o /ruta/Actualizacion-Mi-CV-Cine.zip -d /var/www/micv
   sudo apt install -y curl ffmpeg fonts-dejavu-core
   sudo bash bin/instalar-video-cine.sh
   ```

3. El instalador descarga el ejecutable oficial de Pocket TTS, verifica su SHA-256, prepara el modelo español y genera un WAV de prueba como `www-data`. Solo después publica el ejecutable y cambia las dos opciones de voz del `.env`. Guarda una copia anterior del `.env` en `storage/app/video-voz/.env-anterior`, limpia las cachés y reinicia `micv-video` si existe.
4. Compruebe el servicio y genere **un video nuevo** desde el panel. Los MP4 anteriores no cambian.

   ```bash
   sudo systemctl start micv-video
   sudo systemctl status micv-video --no-pager -l
   sudo journalctl -u micv-video -n 30 --no-pager
   ```

No hay migraciones ni paquetes nuevos de Composer o Node. Mantenga el trabajador dedicado que ya funciona en su VPS; no hace falta cambiar su conexión de cola general.

## Voz utilizada

Se incorpora **Kyutai Pocket TTS**, con el modelo español y la voz de catálogo **Lola**. Se usa el ejecutable de CPU oficial, sin servidor adicional, GPU, Python ni claves de API. La primera preparación descarga aproximadamente unos cientos de MB de modelos; las generaciones siguientes reutilizan la caché privada. Se probó el ejecutable publicado en la versión `v4.0.1`, cuyo archivo se denomina `pocket-tts-3.3.0-x86_64-unknown-linux-gnu.tar.gz`.

Requisitos de este instalador probado: Linux **x86_64 con AVX2**, acceso HTTPS a GitHub y Hugging Face, espacio para la caché y herramientas estándar `curl`, `tar`, `sha256sum`, `ffmpeg`. Si el procesador o la arquitectura no son compatibles, el instalador se detiene antes de cambiar la configuración; puede utilizar la alternativa Piper descrita en `LEAME-VIDEO-PROMOCIONAL.md`.

Opciones admitidas:

```dotenv
MICV_VIDEO_MOTOR_VOZ=auto
# auto: Pocket si está instalado; después Piper si tiene modelo; finalmente eSpeak.
# También puede elegir pocket, piper o espeak expresamente.
MICV_VIDEO_POCKET=/var/www/micv/storage/app/video-voz/bin/pocket-tts
MICV_VIDEO_MUSICA=true
```

El instalador selecciona `pocket` expresamente. Si un motor elegido falla, se informa un error y se conserva el registro; no se cambia silenciosamente a una voz básica. La naturalidad de la voz es subjetiva: escuche la muestra incluida para valorar el resultado. No se promete una voz indistinguible de una persona.

Si usa otro usuario de PHP o una ruta de PHP diferente, indique ambas al ejecutar el instalador:

```bash
sudo MICV_VIDEO_USUARIO=su_usuario MICV_PHP_BIN=/ruta/absoluta/php bash bin/instalar-video-cine.sh
```

La narración del tutorial es texto fijo y ficticio. Pocket descarga sus modelos oficiales y sintetiza el texto localmente; no envía datos de clientes a un servicio de voz. No se usa ni se clona la voz de un usuario. La prueba sin credenciales utilizó el modelo de catálogo de `kyutai/pocket-tts-without-voice-cloning`, publicado con licencia CC BY 4.0; se conserva la atribución a Kyutai y el video incluye un crédito final.

## Montaje y movimiento

- Trece escenas con introducción del CV terminado y cierre con llamada a la acción.
- Foto ficticia generada para la demostración; no pertenece a una cliente real. La copia temporal privada se elimina al preparar las vistas.
- Composición de fondo, documento/interfaz, títulos, cursor y subtítulos en capas separadas. El movimiento y las entradas se calculan en cada cuadro a **30 fps**.
- Documento flotante, parallax suave, rotación leve, entrada con desaceleración, títulos animados y halos de enfoque.
- Escritura progresiva y encuadre de foto sobre la interfaz real, sin enviar formularios, crear cuentas, descargar documentos reales ni mandar correos.
- Fundidos y deslizamientos de **0,4 segundos** entre escenas. Se renderizan de dos en dos para limitar el uso de memoria del VPS.
- La voz se coloca en una única línea de tiempo a partir de la duración de los WAV, con pausas de entrada y salida que preservan las palabras durante las transiciones.
- Subtítulos por frases y palabras destacadas. El reparto temporal de las frases es estimado por su longitud; no es alineación fonética ni reconocimiento de voz.
- Ambiente musical original sintetizado localmente, audio AAC estéreo de 192 kbps y H.264 CRF 18 con `faststart`. `MICV_VIDEO_MUSICA=false` deja solo la narración.
- El progreso incluye capturas, narración, composición, montaje de transiciones y exportación. El 100 % aparece después de publicar el MP4.

## Comprobar cambios

```bash
php artisan test --filter=VideoPromocionalTest
npm run test:video
```

Se comprueban privacidad, cola única, estados y progreso, CV lateral con foto sin crear cuentas, limpieza de la imagen temporal, duración WAV, construcción de subtítulos, selección de motor y línea de tiempo de las transiciones.

## Referencias de la voz

- Proyecto oficial y requisitos de CPU: https://github.com/kyutai-labs/pocket-tts
- Opciones oficiales de generación y modelo español: https://github.com/kyutai-labs/pocket-tts/blob/main/docs/CLI%20Commands/generate.md
- Versiones oficiales: https://github.com/kyutai-labs/pocket-tts/releases/tag/v4.0.1
- Modelo de catálogo y licencia CC BY 4.0: https://huggingface.co/kyutai/pocket-tts-without-voice-cloning
- Modelos y ficha de voz: https://huggingface.co/kyutai/pocket-tts
- Alternativa Piper: https://github.com/OHF-Voice/piper1-gpl

SHA-256 del archivo ejecutable probado: `9abff0aa138a804e44052952d8c3bdaae247fb267ca8f4b83fe178897d1a0559`.
