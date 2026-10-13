# Mi CV — sistema de currículums (Laravel 12)

Basado en el **Sistema JZ**, sin la parte de empresa, boletas (despachos,
órdenes, certificados) ni ventas. Las "sucursales" ahora se llaman **Cybers**:
cada Cyber tiene sus usuarios y ve solo a sus clientes. El admin lo ve todo.

Los PDF se hacen con **Chromium + Node** (Browsershot + Puppeteer). El PDF sale
igual a la vista previa del navegador: fuentes, colores, foto, marco, todo.

## Novedades de la versión 4

- **Nacionalidad por lista:** se elige de una lista (se adapta a Masculino/Femenino). Solo si es de otro país se elige "Otra" y se escribe.
- **Cambiar de país o de documento:** en "Mis datos" y en "Editar cliente" se puede cambiar el país, el tipo y el número de documento (el sistema revisa que no esté repetido).
- **Formularios por secciones** (Documento, Nombre, Nacimiento, Contacto, Trabajo, Familia…) con un índice arriba, y pensados para el celular.
- **Cartas:** el contacto (celular y correo) sale **una sola vez**, debajo de la firma, y "Atentamente" sale una sola vez. Se usa "la suscrita/el suscrito", "portadora/portador" y "candidata/candidato" según el sexo.
- **Palabras correctas:** en un Cyber se habla de **clientes**; quien se registra solo es un **usuario**.
- **Marca del sistema** (Admin → Marca del sistema): nombre, eslogan, logo, colores, teléfono, WhatsApp, correo de soporte, dirección, redes, pie de página y datos de la app Android.
- **Textos legales** (Admin → Textos legales): Términos y condiciones, Política de privacidad (Ley 8968 de Costa Rica y Ley 787 de Nicaragua, derechos de acceso, rectificación, cancelación y oposición), Contrato de servicio para Cybers y Política de cookies. Son editables, tienen versión y se ven en `/legal/terminos`, `/legal/privacidad`, `/legal/contrato` y `/legal/cookies`.
  - Al registrarse hay que aceptar los términos y la privacidad.
  - Si publica una **nueva versión**, todos deben aceptarla al entrar. Los Cybers aceptan también el contrato.
  - **Importante:** son plantillas, no asesoría legal. Pídale a un abogado que las revise antes de publicar.
- **Eliminar cuenta:** el usuario la pide desde "Privacidad y cuenta" (con su contraseña) o desde la página pública `/eliminar-cuenta`. El **admin aprueba o rechaza** en "Solicitudes de eliminación" y la persona recibe un correo. El usuario también puede **descargar todos sus datos** (JSON).
- **Recuperar contraseña** con un código por correo (`/recuperar`).
- **Lista para app (Google Play):** manifiesto (`/manifest.webmanifest`), íconos, modo sin internet, aviso de cookies y verificación de dominio (`/.well-known/assetlinks.json`).

### Actualizar desde la v3

```bash
php artisan migrate --force     # agrega las tablas nuevas, no borra datos
php artisan optimize:clear
```

Después entre como admin y llene **Marca del sistema** (sobre todo el correo de soporte) y **Textos legales** (responsable y cédula). Los Cybers tendrán que aceptar los términos y el contrato la próxima vez que entren.

### Publicar en Google Play (TWA)

1. El sitio debe estar en **HTTPS** con su dominio (por ejemplo con Certbot, ver el paso 7).
2. Abra **https://www.pwabuilder.com**, ponga su dominio y elija **Android → Generate**. Use un nombre de paquete como `com.micv.app`.
3. PWABuilder le da la **huella SHA-256** de la llave. Póngala junto con el paquete en **Admin → Marca del sistema → App Android**. Revise `https://su-dominio/.well-known/assetlinks.json`.
4. Suba el `.aab` a Google Play Console. En la ficha de la app ponga:
   - Política de privacidad: `https://su-dominio/legal/privacidad`
   - Eliminación de la cuenta: `https://su-dominio/eliminar-cuenta`
   - En "Seguridad de los datos" declare: nombre, correo, teléfono, documento de identidad, foto y datos de empleo. Se recogen para crear el CV, van cifrados (HTTPS), no se venden y la persona puede pedir que se borren.
5. Google revisa la app. Si pide una cuenta de prueba, cree un usuario normal desde `/registro`.

## Novedades de la versión 3

- **Registro abierto en `/registro`:** cualquier persona crea su cuenta. Lo primero que elige es su **nacionalidad**. Después verifica su correo con un **código de 6 dígitos** que le llega por Gmail y entra a **su propio panel**, donde:
  - llena sus datos y su foto;
  - descarga su CV en 4 diseños;
  - hace sus cartas.

  Estos clientes quedan en la **base general**, no en ningún Cyber. Solo el admin los ve, con la etiqueta *Registro propio*.
- **Correo Gmail** (Admin → *Correo Gmail y registro*): se pone el correo y la **contraseña de aplicación**, con un botón de *Enviar prueba*. La contraseña se guarda **cifrada**. Ahí también se abre o cierra el registro público.
- **Todo se adapta al país:**

| | Nicaragua | Costa Rica | Otro país |
|---|---|---|---|
| Documentos | Cédula (se valida la letra y se llenan fecha y lugar), pasaporte, residencia, código de persona | Cédula de 9 dígitos (sale la provincia), DIMEX de 11-12 dígitos, pasaporte | Pasaporte o documento |
| Seguro social | Número INSS | N.º de asegurado CCSS | N.º de seguro social |
| Lugar | Municipio / Departamento | Cantón / Provincia | Ciudad / Estado |
| Teléfono | +505 · Claro, Tigo | +506 · Kölbi, Claro, Liberty | — |
| Cartas | Código del Trabajo de Nicaragua (15 días) | Código de Trabajo de CR (preaviso, art. 28) | Ley laboral general |
| CV Clásico | Escudo de Nicaragua | Escudo de Costa Rica | Escudo que suba el admin |

- **Cartas en PDF**, cada una en su propio módulo y con el mismo formato del Sistema JZ: renuncia inmediata, renuncia con preaviso, solicitud de empleo, recomendación laboral y recomendación personal. Cada una trae 4 modelos de redacción, vista previa en vivo y lenguaje según el sexo de la persona.
- **CV:** el **Clásico** (formal, con escudo) y **3 diseños Pro**: *Lateral*, *Moderno* y *Elegante*, con 7 paletas de colores. Los Pro **siempre salen en una sola hoja**: si el contenido es largo, se reduce solo hasta que quepa.
- **Google reCAPTCHA v3** (Admin → *Google reCAPTCHA v3*): protege el login y el registro, y muestra la insignia de Google. Si Google no responde, el sistema deja pasar y lo anota.
- **Errores del sistema**: lee `storage/logs/laravel.log` con pistas en español.
- **Auditoría** con filtros por acción, usuario y fechas, más el contador de logins fallidos.

> **Actualizar desde la versión 2:** copie los archivos nuevos y ejecute `php artisan migrate`. No se pierde ningún dato.

### Configurar Gmail (para que lleguen los códigos)
1. Use una cuenta de Gmail para el sistema, por ejemplo *micv.notificaciones@gmail.com*.
2. En **myaccount.google.com → Seguridad** active la **Verificación en 2 pasos**.
3. Abra **myaccount.google.com/apppasswords**, cree una contraseña llamada *Mi CV* y copie las **16 letras**.
4. En el sistema vaya a **Admin → Correo Gmail y registro**:
   - **Correo:** el Gmail del paso 1.
   - **Contraseña de aplicación:** las 16 letras.
   - **Servidor:** `smtp.gmail.com`.
   - **Puerto:** `587`, con seguridad **TLS**.
   - Marque **Usar este correo** y guarde.
5. Toque **Enviar prueba**. Si dice *connection could not be established*, su VPS bloquea el puerto 587: pruebe con **465 / SSL** o pídale al proveedor que lo abra.

> En su PC, con `APP_DEBUG=true` y sin correo configurado, la pantalla de verificación muestra el código para que pueda probar igual. En producción (`APP_DEBUG=false`) eso no sale nunca.

## Qué incluye

**Tipos de usuario**
- **Administrador general:** ve **todos** los Cybers y **todos** los clientes. Registra Cybers, crea usuarios, configura el CV y ve la auditoría.
- **Usuario de Cyber:** pertenece a **un** Cyber. Solo ve, registra y edita los clientes de **su** Cyber, y cambia su logo y sus colores en **Mi marca**.

**Módulos**
| Módulo | Admin | Cyber |
|---|:-:|:-:|
| **Cybers**: registrar, editar, logo, colores, poner en mantenimiento | ✔ | — |
| **Usuarios**: crear admin o usuario de Cyber, activar o desactivar, cambiar contraseña | ✔ | — |
| **Registrar cliente**: consulta del documento en vivo y todos los datos del CV | ✔ | ✔ |
| **Lista de clientes** con filtros (sexo, tipo de documento y, el admin, por Cyber) | todos | los suyos |
| **Ficha del cliente** y **CV en PDF**: Clásico o Pro estilo Canva, con o sin foto | ✔ | ✔ |
| Eliminar clientes | ✔ | — |
| **Mi marca** (logo y colores del Cyber) | — | ✔ |
| Config. y medidas del CV, Auditoría | ✔ | — |

**Datos del cliente** (los mismos del Sistema JZ): tipo y número de documento
(cédula nicaragüense, pasaporte, residencia o código de persona), INSS, nombres
y apellidos, fecha, municipio y departamento de nacimiento (con cédula se llenan
solos), edad, sexo, nacionalidad, estado civil (según el sexo), profesión,
número de hijos, celular con código de país y compañía, celular secundario,
correo, dirección, padre, madre, notas y foto. Para el CV: perfil profesional,
estudios, experiencia, habilidades y referencias.

**Base general de clientes:** si un Cyber registra un documento que ya existe
(porque lo registró otro Cyber), no se duplica. El sistema carga sus datos y,
al guardar, el cliente queda **también** en la lista de ese Cyber. El admin
puede marcar en "Editar" qué Cybers ven a cada cliente.

**Tema:** el mismo del Sistema JZ (menú azul con buscador, tarjetas, iconos
lucide, SweetAlert, modo oscuro). Los estilos ya vienen **compilados** en
`public/css/tema.css` y las librerías en `public/vendor/`. **No hace falta
`npm run build`** ni internet para el diseño.

**Primer administrador:** pantalla **/instalar** (aparece sola la primera vez)
o el comando `php artisan micv:crear-admin`.

### Orden de uso
1. Entra como admin y ve a **Cybers → Registrar Cyber**.
2. En **Usuarios → Crear usuario** elige *Usuario de Cyber* y su Cyber.
3. Ese usuario entra y registra a sus clientes. Solo ve los suyos.

> **Si ya habías instalado la versión anterior en tu PC**, corre
> `php artisan migrate:fresh` (borra los datos de prueba) y vuelve a crear el admin.

---

## Instalación en el VPS (Ubuntu 22.04 / 24.04)

### 1. Paquetes del sistema

```bash
sudo apt update
sudo apt install -y nginx mariadb-server unzip git curl \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath php8.3-exif

# Composer
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer

# Node 20 o superior
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

> Si tu Ubuntu solo trae PHP 8.2, cambia `php8.3` por `php8.2` (funciona igual).

### 2. Base de datos

```bash
sudo mysql -e "CREATE DATABASE mi_cv CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'mi_cv'@'localhost' IDENTIFIED BY 'CAMBIA_ESTA_CLAVE';
GRANT ALL ON mi_cv.* TO 'mi_cv'@'localhost'; FLUSH PRIVILEGES;"
```

### 3. Subir el proyecto

Sube el zip a `/var/www/` y descomprímelo:

```bash
cd /var/www
sudo unzip mi-cv.zip        # queda en /var/www/mi-cv
cd mi-cv
composer install --no-dev --optimize-autoloader
cp .env.example .env
nano .env                   # pon DB_PASSWORD, APP_URL, etc.
php artisan key:generate
php artisan migrate --force
```

### 4. Chromium para los PDF

```bash
cd /var/www/mi-cv
npm install                                        # instala puppeteer (y descarga Chrome dentro de .cache/)
npx puppeteer browsers install chrome --install-deps   # instala las librerías de Linux que Chrome necesita
```

El archivo `.puppeteerrc.cjs` hace que Chrome se guarde **dentro del proyecto**
(`.cache/puppeteer`), así el usuario `www-data` lo encuentra sin problemas.

**Alternativa:** usar el Chromium del sistema. Instálalo y pon su ruta en el `.env`:

```bash
sudo apt install -y chromium-browser   # o google-chrome-stable
which chromium-browser                 # copia la ruta
# en .env:  MICV_CHROME_PATH=/usr/bin/chromium-browser
```

### 5. Permisos

```bash
sudo chown -R www-data:www-data /var/www/mi-cv
sudo chmod -R 775 /var/www/mi-cv/storage /var/www/mi-cv/bootstrap/cache
```

### 6. Probar el PDF (muy recomendado)

```bash
sudo -u www-data php artisan micv:probar-pdf
```

Debe decir `PDF generado correctamente`. Si falla, el mensaje dice qué falta
(Node, puppeteer o librerías de Chrome).

### 7. Nginx

`/etc/nginx/sites-available/mi-cv`:

```nginx
server {
    listen 80;
    server_name tu-dominio.com;
    root /var/www/mi-cv/public;
    index index.php;
    client_max_body_size 12M;

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known) { deny all; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/mi-cv /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
# HTTPS gratis:
sudo apt install -y certbot python3-certbot-nginx && sudo certbot --nginx -d tu-dominio.com
```

### 8. Optimizar (producción)

```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

> Cada vez que cambies el `.env`, vuelve a correr `php artisan config:cache`.

---

## Crear el administrador

**Opción A, desde el navegador:** entra a tu dominio. La primera vez te manda
a **/instalar**. Ahí pones el nombre del negocio y tu usuario admin. Cuando
ya existe un usuario, esa pantalla se bloquea sola.

**Opción B, desde la terminal:**

```bash
php artisan micv:crear-admin
# o sin preguntas:
php artisan micv:crear-admin --usuario=JEFFRY --email=tu@correo.com --nombre="Jeffry Zeas" --password="TuClave123"
```

Si el usuario ya existe, el comando le pone la nueva contraseña, lo vuelve
admin y lo activa. Sirve también para recuperar el acceso si se te olvida la clave.

Después, desde **Administración → Usuarios** creas los demás usuarios (admin o cyber).
El sistema no deja desactivar ni borrar al último administrador activo.

---

## Prueba rápida en tu PC (sin VPS)

```bash
composer install
npm install
cp .env.example .env
# en .env: DB_CONNECTION=sqlite, APP_DEBUG=true, APP_ENV=local
touch database/database.sqlite
php artisan key:generate
php artisan migrate
php artisan db:seed            # crea 2 Cybers de ejemplo y una clienta con el CV completo
php artisan serve
```

Abre http://127.0.0.1:8000, crea el admin y entra a **Clientes y CV**.

---

### En Windows (prueba local)
- En el `.env` deja vacíos `MICV_CHROME_PATH`, `MICV_NODE_BINARY` y `MICV_NPM_BINARY`.
- Corre `npx puppeteer browsers install chrome` y luego `php artisan config:clear` y `php artisan micv:probar-pdf`.
- `sudo -u www-data …` es solo para el VPS (Linux).
- Si quieres cambiar estilos o agregar clases nuevas, recompila el CSS con `npm run css`. No hace falta para usar el sistema.

## Variables del `.env` para el PDF

| Variable | Para qué sirve |
|---|---|
| `MICV_CHROME_PATH` | Ruta de Chromium/Chrome. Vacío = el que descargó Puppeteer. |
| `MICV_NODE_BINARY` | Ruta de `node`. Vacío = automático (en el VPS busca `/usr/bin/node`). Usa `which node` si está en otro lado. |
| `MICV_NPM_BINARY` | Ruta de `npm`. |
| `MICV_CHROME_NO_SANDBOX` | `true` en VPS (necesario si corre como root o en contenedor). |
| `MICV_PDF_TIMEOUT` | Segundos máximos por PDF (60). |

## Estructura principal

```
app/Http/Controllers/ClienteController.php   clientes
app/Http/Controllers/CvController.php        vista previa + PDF
app/Services/CvDatos.php                     arma los datos del CV
app/Services/CvPdf.php                       Chromium (Browsershot)
app/Services/ClienteGuardar.php              validación y guardado del CV
app/Services/CedulaValidator.php             cédula de Nicaragua
app/Console/Commands/CrearAdmin.php          php artisan micv:crear-admin
app/Console/Commands/ProbarPdf.php           php artisan micv:probar-pdf
resources/views/cv/clasico.blade.php         diseño Clásico
resources/views/cv/pro.blade.php             diseño Pro (estilo Canva)
resources/fonts/                             fuentes incrustadas en el PDF
```

## Si algo falla

- **Pantalla "No se pudo conectar a la base de datos":** revisa `DB_*` en el `.env` y corre `php artisan migrate --force`.
- **"No se pudo generar el PDF":** corre `sudo -u www-data php artisan micv:probar-pdf` y sigue lo que diga.
- **Error 500 sin detalle:** mira `storage/logs/laravel.log`.
- **La foto no sube:** sube `client_max_body_size` en Nginx y `upload_max_filesize` / `post_max_size` en `php.ini` (12M).

Las fuentes Montserrat y Playfair Display tienen licencia libre (OFL), y la
licencia va en `resources/fonts/`.
