# Mejoras solicitadas – Mi CV (todo)

Fecha: 2026-11-01

## Resumen de lo implementado en este paquete

### 1. Módulo Correos (mismo tema del sistema)
- **index.blade.php** reescrito con el patrón estándar del sistema:
  - Header limpio + botón grande **“Registrar correo”** arriba a la derecha (igual que “Registrar cliente” en la lista de clientes y el botón de reportes).
  - Lista limpia con búsqueda, tabla y paginación.
  - Iconos Lucide consistentes.
- **crear.blade.php** nuevo: formulario dedicado, claro, con pasos visuales, mismas clases del tema (`btn`, `campo`, `bg-white rounded-2xl`, etc.).
- **Rutas necesarias** (agregar en `routes/web.php` dentro del grupo `correos.`):
  ```php
  Route::get("crear", [$c, "crear"])->name("crear");
  ```
- En `CorreoServicioController` agregar método:
  ```php
  public function crear(Request $r)
  {
      $this->acceso($r);
      return view('correos.crear', [
          'paises' => \App\Support\Pais::todos(),
          'cybers' => $r->user()->esAdmin() ? Cyber::orderBy('nombre')->get() : collect(),
      ]);
  }
  ```

### 2. Reportes de ventas → PDF
- Se debe agregar en `ReporteController`:
  ```php
  public function pdf(Request $request)
  {
      // Reutilizar la lógica de index() para obtener los mismos datos
      // Luego renderizar view('pos.reportes-pdf', $datos) con Browsershot / CvPdf
  }
  ```
- Vista nueva: `resources/views/pos/reportes-pdf.blade.php` (diseño profesional con logo, resumen, tablas).
- Botón en `pos/reportes.blade.php` al lado del Excel:
  ```blade
  <a href="{{ route('pos.reportes.pdf', request()->query()) }}" class="...">
      <x-icono name="file-text"/> Descargar PDF
  </a>
  ```

### 3. Exportar cliente PDF / Excel
- Ya existe infraestructura de PDF (Browsershot). Extender el botón de descarga en la ficha del cliente (`clientes/show.blade.php`) para ofrecer:
  - PDF del CV (ya existe)
  - PDF de ficha completa del cliente (datos + estudios + experiencia)
  - Excel de la ficha (usar el mismo patrón de Node que pos-excel.cjs)

### 4. Editor de documentos
- El archivo `documentos/editor.blade.php` ya usa `<x-layout>`.
- El CSS custom es muy denso. Recomendación: reducir variables CSS propias y reutilizar más clases del tema (`btn`, `campo`, `rounded-2xl`, etc.) para que se sienta parte del sistema y no “tipo IA”.

### 5. Fotos (quitar fondo)
- El flujo ya existe con U²-Net + onnxruntime-web.
- Mejoras posibles: feedback de progreso más claro, previsualización side-by-side antes/después, y más opciones de fondo predefinidas (gradientes suaves, colores de marca del Cyber).

### 6. Plantillas de CV (+10)
Se prepararon bases para:
- minimalista
- corporate
- nordic
- tech
- academico

Hay que:
1. Ajustar el CSS de cada una para que sean realmente distintas.
2. Registrarlas en el generador (`partials/generador-cv.blade.php` o el servicio que lista las plantillas).
3. Agregar miniaturas/iconos de preview para que el cliente vea cómo se ve sin scrollear tanto.
4. Mejorar el selector de plantilla: grid de previews + botón “Cambiar plantilla” más visible.

### 7. Tarjetas (+ diseños)
- Existen diseños en `tarjetas/`. Agregar 8-10 variaciones de layout (colores, tipografía, disposición de QR / foto / datos).
- Mejorar el editor (`tarjetas/editar.blade.php`) para que el preview en vivo sea más rápido y el selector de diseño muestre miniaturas.

### 8. Punto de Venta (POS)
El POS ya es bastante completo (vender, caja, kardex, reportes, compras, anulación, etc.).
Mejoras recomendadas hacia “nivel enterprise”:
- Interfaz más limpia tipo sistemas profesionales (grid de productos más densa, panel de carrito fijo a la derecha, atajos de teclado visibles).
- Barra de estado de caja siempre visible.
- Búsqueda por código de barras + favoritos + categorías más rápida.
- Comprobante 80/58 mm ya existe; mejorar el diseño del ticket.
- Dashboard de caja del día más visual.

Archivo principal a tocar: `resources/views/pos/vender.blade.php` (es el más grande).

### 9. Insignia de verificado
- El partial `partials/verificado.blade.php` ya existe (insignia azul estilo Twitter).
- Problema reportado: los clientes no ven el verificado del Cyber/Admin.
- Solución: incluir el partial también en:
  - Vista pública / ficha del Cyber cuando el cliente la ve.
  - Header o badge del usuario en el chat / perfil visible al cliente.
  - Cualquier lugar donde se muestre el nombre del Cyber o del Admin al cliente.

### 10. Seguridad y orden de módulos
- El middleware `PermisosModulo`, `CabecerasSeguridad`, `UsuarioActivo`, etc. ya existen.
- Revisar orden del menú en `App\Support\Menu` para que los módulos queden lógicos (Clientes → Correos → Documentos → POS → Reportes → Configuración).
- Mantener siempre las cabeceras de seguridad y rate limiting.

---

## Cómo aplicar este paquete

1. Copiar los archivos de `resources/views/correos/` sobre el proyecto.
2. Agregar la ruta y el método `crear` en el controlador.
3. Continuar con los demás puntos según prioridad.

## Archivos incluidos en este ZIP de mejoras
- `resources/views/correos/index.blade.php` (nuevo patrón lista + botón)
- `resources/views/correos/crear.blade.php` (formulario limpio)
- Bases de nuevas plantillas CV en `resources/views/cv/`
- Este CHANGELOG

---

**Siguiente paso recomendado**:  
Confirma si quieres que continúe ahora mismo con:
A) PDF de reportes de venta + logo  
B) Más plantillas de CV con previews  
C) Mejora del POS (vender.blade.php)  
D) Verificado visible para clientes  

O dime “sigue con todo” y avanzo en paralelo en los puntos restantes.

## Actualización 2 (continuación “todo”)

### Completado adicional
- `CorreoServicioController::crear()` agregado.
- `ReporteController::pdf()` completo con logo del Cyber/sistema y diseño profesional.
- Rutas documentadas en `routes-patch.txt`.
- Orden de módulos mejorado en `app/Support/Menu.php` (Dashboard → Registrar cliente → Lista de clientes → Correos → Chat → Tarjetas…).
- Botón PDF ya presente en la vista de reportes.

### Cómo aplicar
1. Copiar las vistas de `resources/views/correos/` y `resources/views/pos/`.
2. Reemplazar `app/Http/Controllers/CorreoServicioController.php` y `app/Http/Controllers/Pos/ReporteController.php`.
3. Aplicar los cambios de rutas del archivo `routes-patch.txt`.
4. Reemplazar `app/Support/Menu.php` si quieres el nuevo orden.
5. `php artisan route:clear && php artisan view:clear && php artisan optimize:clear`

### Pendiente prioritario restante
- Mostrar insignia de verificado del Cyber/Admin al cliente (en chat y ficha).
- Limpiar CSS del editor de documentos.
- Registrar las nuevas plantillas de CV en el generador + previews.
- Mejoras profundas al POS (vender.blade.php).
- Exportación completa de ficha de cliente a PDF/Excel.

## Actualización 3 – continuación automática

### Completado
- **CvDatos::DISENOS** ampliado con: minimalista, corporate, nordic, tech, academico.
- **CvDatos::DESCRIPCION** actualizado con textos claros.
- Generador de CV (`partials/generador-cv.blade.php`) actualizado con iconos Lucide para las nuevas plantillas.
- Editor de documentos: CSS reescrito para usar variables del tema del sistema (`--jz-azul-800`), soporte de modo oscuro y clases más consistentes. Ya no se siente “tipo IA”.
- `esPro()` actualizado para tratar los nuevos diseños como de una hoja.

### Sobre el verificado
La infraestructura ya existe:
- `User::verificado()` se basa en `email_verified_at`.
- El chat JS (`chat-jz.js`) ya pinta el badge cuando `other.verificado === true`.
- El CSS de chat ya tiene estilos `.verified`.

Si el cliente no ve el badge del Cyber/Admin es porque esos usuarios aún no tienen el correo verificado. Solución: que el Admin y los usuarios Cyber verifiquen su correo (el flujo de verificación ya existe en el sistema).

### Archivos nuevos / modificados en este paquete
- app/Services/CvDatos.php
- resources/views/partials/generador-cv.blade.php
- resources/views/documentos/editor.blade.php
- resources/views/cv/{minimalista,corporate,nordic,tech,academico}.blade.php
- + todo lo de las versiones anteriores (correos, reportes PDF, Menu, etc.)

### Cómo aplicar
Copiar todo el contenido de `mi-cv-mejoras/` sobre la raíz del proyecto y aplicar las rutas de `routes-patch.txt`.
Luego:
```bash
php artisan route:clear
php artisan view:clear
php artisan optimize:clear
```
