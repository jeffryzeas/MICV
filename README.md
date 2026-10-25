> Registro sencillo y editor de documentos ampliado: consulte **LEAME-EDITOR-DOCUMENTOS.txt** para esta entrega.

> Galería de CV con desplazamiento continuo: **31 diseños**, incluido ATS en PDF y Word. Consulte **LEAME-ACTUALIZACION.txt** para esta entrega.

> Corrección de tema y permisos del portal: consulte **LEAME-ACTUALIZACION.txt**. Incluye CSS compilado y requiere `php artisan migrate --force`.

> Actualización de negocios, países y libro de ingresos/gastos: consulte **LEAME-ACTUALIZACION.txt** antes de instalar. Esta actualización requiere migración y `npm ci`.

# Mi CV — sistema de currículums (Laravel 12)

Basado en el **Sistema JZ**, sin la parte de empresa, boletas (despachos,
órdenes, certificados) ni ventas. Las "sucursales" ahora se llaman **Cybers**:
cada Cyber tiene sus usuarios y ve solo a sus clientes. El admin lo ve todo.

Los PDF se hacen con **Chromium + Node** (Browsershot + Puppeteer). El PDF sale
igual a la vista previa del navegador: fuentes, colores, foto, marco, todo.

## Novedades de la versión 10

### Punto de venta (menú "Punto de venta")
Cada Cyber tiene su propio inventario, caja y ventas. El admin elige en qué Cyber trabajar con el selector de arriba.

- **Vender:**
  - Cuadrícula de productos con búsqueda, categorías y favoritos.
  - Lector de código de barras: se escanea en la búsqueda y se agrega solo.
  - Artículo libre: "Otro", para cobrar algo que no está en la lista.
  - Descuento por línea o a toda la venta.
  - Cliente de la lista o nombre escrito.
- **Cobrar:**
  - Efectivo con botones de billetes del país y cálculo del cambio.
  - Tarjeta y transferencia, con el nombre de cada país: SINPE Móvil, Yape/Plin, Nequi, Bizum…
  - Pagos combinados.
  - Teclado: F2 busca, F9 cobra, Enter confirma, Esc empieza otra venta.
- **Comprobante:**
  - Para impresora térmica de 80 o 58 mm.
  - Imagen PNG para enviar por WhatsApp y envío por correo.
  - Reimpresión desde "Ventas".
- **Caja:**
  - Abrir con fondo inicial y registrar entradas o salidas de efectivo.
  - Cerrar con arqueo por billetes y monedas: dice si cuadra, sobra o falta.
  - Reporte del turno para imprimir e historial de cierres.
- **Productos e inventario:**
  - Productos y servicios, categorías con color, código de barras, costo y ganancia.
  - Impuesto general, exento u otra tasa.
  - Existencias con aviso de "por agotarse", ajustes con motivo e historial de movimientos (kardex).
  - Botón para cargar de una vez los servicios típicos de un Cyber.
- **Proveedores y compras:** cada compra suma al inventario y actualiza el costo promedio. Se puede anular.
- **Anular ventas:**
  - Con motivo, y el inventario se devuelve.
  - Con la caja ya cerrada, solo el admin puede anular.
- **Reportes:**
  - Hoy, semana, mes o rango de fechas.
  - Ticket promedio, ganancia bruta, ventas por día, por forma de pago y por usuario.
  - Lo más vendido y productos por agotarse.
  - Descarga para Excel (CSV).
- **Configuración por país:**
  - Moneda y símbolo, decimales e impuesto (nombre y tasa).
  - Identificación fiscal (RUC, NIT, RTN, cédula jurídica…), datos del negocio, ancho del tiquete y numeración.
  - **Revise la tasa con su contador:** las leyes cambian.
- **Importante:**
  - El comprobante es **interno** y no sustituye la **factura electrónica**.
  - Donde la ley la pide (por ejemplo Costa Rica, México o Guatemala), use su sistema autorizado.

### Fotos carné y pasaporte (menú "Fotos carné y pasaporte")
- Se toma la foto con la cámara web o se sube.
- El sistema **quita el fondo** (unos 4 segundos) y lo pone blanco, celeste, gris o del color que quiera.
- Recorte con guía de la cara, brillo y contraste.
- Medidas:
  - 2,5×3 cm, 3×4 cm, pasaporte 3,5×4,5 cm, 4×5 cm, EE. UU. 2×2" o la que quiera.
- Papel:
  - Hoja carta, A4 o papel fotográfico 10×15. El sistema calcula cuántas caben, con líneas de corte.
- Desde la ficha del cliente puede poner la foto directo en su CV.
- **Instalación (una sola vez):**

  ```bash
  php artisan micv:modelo-fondo
  ```

  Descarga unos 170 MB del modelo U²-Net (proyecto rembg, licencia Apache-2.0).
- **La cámara del navegador solo funciona con https.**

### CV en Word (.docx)
Botón "Descargar en Word" junto al PDF. Sale en español o inglés, con o sin foto, en un diseño sencillo para editar cualquier texto.

### Autoservicio por QR (menú "Autoservicio (QR)")
- Imprima el afiche y póngalo en el mostrador. La persona escanea el QR, llena sus datos desde su celular y recibe un **número de turno**.
- En la pantalla del Cyber aparecen al instante, con sonido y número en el menú.
- Al tocar **Atender**, se abre "Registrar cliente" ya lleno. Se revisa y se guarda con todas las validaciones de siempre.
- Se puede pausar.
- Los datos que nadie atiende se borran solos a los 7 días.
- Los documentos de la lista privada no se reciben.

### Otros cambios
- Menú con grupos plegables. El admin tiene "Configuración" y "Seguridad".
- Los mensajes de "guardado" ahora son un aviso pequeño que se va solo.
- Al imprimir solo sale el contenido, sin el menú.

### Librerías nuevas (se instalan con `npm install`)
- `docx` (MIT): CV en Word.
- `onnxruntime-web` (MIT): corre el modelo para quitar el fondo, sin GPU.
- `sharp` (Apache-2.0): procesa las imágenes.

### Actualizar desde la v9
```bash
npm install
php artisan migrate --force
php artisan micv:modelo-fondo
php artisan optimize:clear && php artisan optimize
```

## Novedades de la versión 9

### Para la persona (usuario)
- **Tarjeta de presentación**, aparte del CV, en el menú "Mi tarjeta de presentación":
  - **20 diseños** en 5 categorías (Oficina y negocios, Transporte y mandados, Profesionales, Oficios y servicios, Belleza, comida y eventos). Cada diseño trae los íconos de su oficio: moto y paquete para mandados, taxi, balanza para abogado, estetoscopio para salud, etc.
  - Puede usar su **foto**, su **logo** o el ícono del oficio, y cambiar el color.
  - **Código QR** que abre WhatsApp con su número, guarda su contacto o abre su sitio web. Lleva su logo (o el de WhatsApp) en el centro. Se probaron los 20 diseños con un lector de QR: todos se leen.
  - Descargas: **PNG en alta calidad** (1296 × 816 px, unos 380 ppp), **PDF de una tarjeta** (8,56 × 5,4 cm) y **hoja carta con 10 tarjetas** y marcas de corte.
  - Botón **WhatsApp**: en el celular abre el menú de compartir con la imagen lista. En computadora la descarga y ofrece abrir WhatsApp.
  - **Enviar a mi correo** con la imagen y la hoja de 10 adjuntas.
  - El Cyber y el admin también pueden hacerla desde la ficha del cliente.
- **Ícono de verificado** junto al nombre, en el panel y en la barra de arriba.
- **Aviso al salir sin guardar** con el diseño del sistema (Guardar ahora / Salir sin guardar / Seguir editando).
  - Al cerrar la pestaña, el navegador muestra su propio mensaje. Ese no se puede cambiar: ningún navegador lo permite.
- **Textos legales dentro del sistema:** con la sesión abierta se ven con el mismo menú, logo y diseño.

### Para el Cyber
- **Panel nuevo:**
  - Saludo y búsqueda rápida.
  - Accesos directos.
  - Números del mes: clientes, CV, cartas, tarjetas y correos.
  - Gráfica de 6 meses.
  - Últimos clientes con foto, avance de la ficha y botones de CV y tarjeta.
  - Cumpleaños del día y fichas por completar.

### Para el admin
- **Lista privada:**
  - Bloquea un número de documento: ningún Cyber puede registrarlo, buscarlo ni ver esa ficha.
  - Opcional: desactivar también la cuenta de esa persona.
- **Avisos por correo:**
  - Recordatorios para que la gente termine su CV: a quienes lo tienen incompleto, a quienes no entran hace tiempo, a todos o a una sola persona.
  - Se puede usar `{nombre}` y `{avance}`.
  - Máximo 100 por envío.
- **Países y documentos:**
  - Crear países nuevos con su escudo, gentilicio, código de teléfono y tipos de documento (solo números o letras y números, con largo mínimo y máximo).
  - En los países que ya vienen: cambiar el escudo, agregar documentos o desactivarlos.
- **Borrar un cliente borra todo:**
  - Su usuario, sesiones, códigos, tarjeta, foto, logo y su historial de actividad.
  - En las solicitudes de eliminación, el correo queda cifrado.
- **Eliminación de cuenta solo dentro de la app:**
  - Ya no hay formulario público.
  - La página `/eliminar-cuenta` sigue existiendo y explica los pasos. Google Play pide esa página, y explicar los pasos dentro de la app cumple el requisito.

### Para que los códigos no caigan en spam
- Los correos salen con versión en texto además de HTML, con "Responder a" y cabeceras de correo automático. Eso ayuda, pero lo que más pesa es lo siguiente:
  1. Use un **dominio propio** (ej. `no-responder@sudominio.com`), no una cuenta @gmail.com.
  2. En el DNS del dominio configure **SPF**, **DKIM** y **DMARC**. Su proveedor de correo (Google Workspace, Zoho, Brevo, Amazon SES…) le da los registros.
  3. Que `MAIL_FROM_ADDRESS` sea del mismo dominio con el que se envía.
  4. No mande muchos avisos de golpe al principio. Suba poco a poco.

### Librerías nuevas (ya incluidas, sin internet)
- `public/vendor/qr-code-styling.min.js`: QR con diseño. Licencia MIT.
- `resources/tarjetas/iconos/*.svg`: íconos Lucide. Licencia ISC.

### Actualizar desde la v8
```bash
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
```

## Novedades de la versión 8

- **Registro:** el país se elige en una lista desplegable con bandera, agrupada por región. Ya no ocupa media pantalla.
- **Textos legales reescritos.** Son plantillas: **no son asesoría legal**. Antes de publicar, que los revise un abogado del país donde está registrado el negocio.
  - **Términos:**
    - Edad mínima (15 años con permiso de la madre o el padre; 18 sin permiso).
    - Prohibición expresa de falsificar documentos y de hacer recomendaciones a nombre de alguien sin su permiso.
    - Aviso de que las cartas no son asesoría legal.
    - Uso de los escudos nacionales.
    - Límite de responsabilidad que respeta los derechos del consumidor.
    - Solución de conflictos.
  - **Privacidad:**
    - Tabla de datos, finalidades y base legal.
    - Transferencias a otros países y plazos de conservación.
    - Seguridad y aviso de incidentes.
    - Derechos de la persona (respuesta en máximo 10 días hábiles).
    - Menores de edad.
    - **Tabla de leyes y autoridades de los 20 países.**
  - **Contrato para Cybers:**
    - El Cyber es "encargado del tratamiento", con obligaciones de consentimiento, confidencialidad del personal y seguridad en el local.
    - Aviso de incidentes en 24 horas y de solicitudes de clientes en 2 días hábiles.
    - Suspensión y terminación con aviso de 15 días.
  - **Cookies:** solo las necesarias, con una tabla de cada una.
  - "Las leyes de…" ahora sale con el nombre oficial de cada país (por ejemplo, "los Estados Unidos Mexicanos" o "el Reino de España").
  - Si usa los textos de fábrica, la actualización publica una **versión nueva** y todos la aceptan al entrar. Si los personalizó, no se toca nada.
- **Seguridad:**
  - **Límites de intentos por tipo de acción:**
    - Login: 5 por minuto por usuario e IP, y 30 por hora por IP.
    - Registro: 3 por minuto y 20 por día por IP.
    - Códigos: además del máximo de 5 intentos por código.
    - Recuperar contraseña: también 5 por hora por correo, para que no le llenen el buzón a alguien.
    - Eliminar cuenta, consultar documentos, PDF y envío por correo: cada uno con su propio límite.
    - Tope general: 240 páginas por minuto.
    - Al pasarse, se muestra una página clara en español que dice cuánto esperar.
  - **Trampa para robots** (campo invisible y tiempo mínimo) en el registro, en recuperar contraseña y en eliminar cuenta. Funciona aunque reCAPTCHA no esté activo.
  - **Login sin pistas:** el tiempo de respuesta es el mismo exista o no el usuario.
  - **Consulta de documentos sin fugas:**
    - Un usuario registrado solo sabe si el número ya está en uso.
    - Un Cyber solo ve el teléfono, el correo y la dirección de sus propios clientes.
  - **Cabeceras de seguridad:** CSP, protección contra clickjacking, nosniff, Referrer-Policy, Permissions-Policy y HSTS con HTTPS.
  - **Código de prueba en pantalla:** solo sale con `APP_ENV=local`, nunca en producción.
  - **Páginas de error propias** (403, 404, 419, 429, 500, 503), en español y con su marca.
  - **Limpieza automática:**
    - Auditoría de más de 12 meses.
    - Códigos vencidos.
    - Registros que nunca se verificaron.

### Lista de seguridad para el VPS

1. En `.env`: `APP_ENV=production`, `APP_DEBUG=false` y `APP_URL=https://su-dominio`.
2. HTTPS con Certbot y, ya con HTTPS, `SESSION_SECURE_COOKIE=true` en `.env`.
3. Cron para la limpieza diaria: `* * * * * cd /var/www/mi-cv && php artisan schedule:run >> /dev/null 2>&1`.
4. Si usa Cloudflare u otro proxy, ponga `TRUSTED_PROXIES=*` en `.env`, para que los límites vean la IP real de cada persona.
5. Active reCAPTCHA v3 en Admin → Google reCAPTCHA.
6. Haga copias de seguridad de la base de datos y de `storage/app/private`.
7. Permisos: solo `storage/` y `bootstrap/cache/` con escritura para `www-data`.

## Novedades de la versión 7

- **21 opciones de país:** Nicaragua, Costa Rica, Honduras, El Salvador, Guatemala, Panamá, México, República Dominicana, Cuba, Colombia, Venezuela, Ecuador, Perú, Bolivia, Chile, Argentina, Uruguay, Paraguay, España, Estados Unidos y "Otro país".
- **Verificación nueva por país:**
  - Chile: RUT con dígito verificador.
  - Ecuador: cédula con provincia y dígito verificador.
  - Argentina: DNI, y CUIL con dígito verificador.
  - Uruguay: cédula con dígito verificador.
  - Panamá: cédula con provincia, con guiones; acepta PE-, E- y N-.
  - República Dominicana: cédula con dígito verificador (si no cuadra, solo avisa).
  - Cuba: carné de identidad; de él salen la fecha de nacimiento y el sexo.
  - Venezuela, Perú, Bolivia y Paraguay: formato del número.
- **Escudos en el CV Clásico** para 19 países. Salen del paquete npm `coat-of-arms` (licencia MIT), pasados a PNG; el detalle está en `resources/cv/ESCUDOS-FUENTE.txt`. Cuba no viene en ese paquete, así que su CV sale sin escudo (limpio). En "Otro país" se usa el escudo que suba el admin, si sube uno.
- **Registro mejorado:**
  - Países en cuadritos con bandera.
  - Ver / ocultar la contraseña, medidor de seguridad de la contraseña y aviso "No coinciden".
  - El botón no se puede tocar dos veces.
  - El código de 6 dígitos se envía solo al terminar de escribirlo.
- **Correos rediseñados:**
  - Encabezado con logo y eslogan, texto de vista previa en la bandeja de entrada, botones que funcionan en Gmail, Outlook y celular, y pie con contacto, redes y enlaces legales.
  - Correos nuevos: **bienvenida** (al crear la cuenta) y **"su CV"** (con el PDF adjunto y consejos para enviarlo).

Para actualizar: no hay migraciones nuevas. Basta con subir los archivos y correr `php artisan optimize:clear`.

## Novedades de la versión 6

- **Más países:** Nicaragua, Costa Rica, Honduras, El Salvador, Guatemala, México, Colombia, España, Estados Unidos y "Otro país". Cada uno tiene sus propios documentos, su seguro social, sus nombres de municipio/departamento (o estado, provincia, cantón), su código de teléfono y sus compañías de celular.
- **El número del documento se revisa con las reglas públicas de cada país** (no se consulta ningún registro oficial en línea):
  - **México:** CURP con dígito verificador. Llena sola la fecha de nacimiento, el sexo y el estado.
  - **España:** DNI y NIE con su letra de control.
  - **Honduras:** DNI de 13 dígitos. Saca el departamento.
  - **Guatemala:** DPI/CUI con departamento y municipio. Si el dígito verificador no cuadra, solo avisa.
  - **El Salvador:** DUI con dígito verificador. Si no cuadra, solo avisa.
  - **Colombia:** cédula de 6 a 10 dígitos.
  - **Estados Unidos:** licencia/State ID o Green Card. A propósito no se pide el número de Seguro Social (SSN), porque no debe ir en un CV.
- **Idiomas:** sección nueva en "Mis datos" con nivel (nativo, avanzado, intermedio, básico). Sale en los 6 diseños, con puntos de nivel en los diseños Pro.
- **CV en inglés:** los títulos, los datos personales, la nacionalidad, el estado civil y "Present" se traducen solos. El perfil y el oficio salen en inglés si se escriben en "Mis datos → Perfil → Para el CV en inglés".
- **Al descargar se pregunta** si lleva foto y en qué idioma sale.
- **Enviar el CV por correo:** el usuario se lo manda a su correo y el Cyber al correo del cliente. Llega con el PDF adjunto.
- **Panel nuevo:** saludo con el avance en círculo, el "siguiente paso" que le falta, la lista de lo que tiene y lo que falta (cada punto lleva a su paso) y accesos rápidos.
- **Carta de renuncia:** fuera de Nicaragua y Costa Rica ya no pone "15 días" fijos; dice "el preaviso que establece" la ley de ese país.

Para actualizar: `php artisan migrate --force` (agrega los idiomas y los campos en inglés, sin borrar datos).

## Novedades de la versión 5

- **"Mis datos" por pasos:** Foto → Documento → Nombre → … → Referencias. Los pasos se pueden saltar tocando cualquier chip de arriba, y los que ya tienen datos llevan un ✓. Con "Ver todo" se ve el formulario completo (queda recordado en ese aparato).
- **Barra de abajo siempre visible** con Anterior, Siguiente, el avance y **Guardar**. Si falta un dato obligatorio en otro paso, al guardar se abre ese paso. Si la persona intenta salir sin guardar, se le avisa.
- **Foto:** si no hay foto, la pantalla muestra un ícono de persona en blanco (en el CV no sale nada). Al elegir una foto se abre un recuadro para **encuadrar la cara**, girarla y acercarla antes de guardarla.
- **Ayudas para escribir:** el botón "Ideas para el perfil" da frases listas según el oficio (cajera, bodeguero, enfermera, chofer… 24 oficios). En Habilidades salen sugerencias del oficio para tocar y agregar.
- **LinkedIn, sitio web y GitHub**, y la casilla "este número tiene WhatsApp". Salen con su ícono en los CV.
- **CV Lateral:** el nombre completo va en negrita (antes solo los apellidos). Lo mismo en el Moderno.
- **Dos plantillas Pro nuevas:** **Pro Ejecutivo** (títulos a la izquierda, sobrio) y **Pro Creativo** (columna derecha, etiquetas y encabezado inclinado). Las dos salen siempre en una hoja.
- **Carta de presentación** que se arma sola con la experiencia, los estudios y las habilidades del CV.
- **La cuenta solo se crea cuando se verifica el correo.** Mientras tanto queda "en espera" y, si no se verifica en 24 horas, se borra sola.
- **Mismo nombre en la cuenta y en el CV:** al cambiar el nombre en "Mis datos", cambia también el de la cuenta.
- **"Página expirada" resuelto:** el formulario renueva solo su código de seguridad. Si aun así vence, vuelve a la misma página con un aviso y sin perder lo escrito.
- En el login se quitó el texto "Protegido por reCAPTCHA" (la etiqueta de Google ya lo indica).

Para actualizar: `php artisan migrate --force` (agrega los campos nuevos y la tabla de registros en espera, sin borrar datos).

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
