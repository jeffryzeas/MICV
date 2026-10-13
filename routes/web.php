<?php

use App\Http\Controllers\Admin\ActividadController;
use App\Http\Controllers\Admin\ConfiguracionController;
use App\Http\Controllers\Admin\CorreoController;
use App\Http\Controllers\Admin\LegalAdminController;
use App\Http\Controllers\Admin\MarcaSistemaController;
use App\Http\Controllers\Admin\SolicitudController;
use App\Http\Controllers\Auth\RecuperarController;
use App\Http\Controllers\CuentaController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\Admin\ErrorController;
use App\Http\Controllers\Admin\RecaptchaController;
use App\Http\Controllers\Admin\CyberController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\ArchivoController;
use App\Http\Controllers\Auth\InstaladorController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegistroController;
use App\Http\Controllers\CartaController;
use App\Http\Controllers\MiCvController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CvController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\PerfilController;
use Illuminate\Support\Facades\Route;

// Primera vez: crear el administrador (se bloquea solo cuando ya hay usuarios)
Route::get('instalar', [InstaladorController::class, 'mostrar'])->name('instalar.mostrar');
Route::post('instalar', [InstaladorController::class, 'guardar'])->name('instalar.guardar')->middleware('throttle:10,1,instalar.guardar');

// Logos: públicos a propósito (se ven en el login y en el menú)
Route::get('marca/logo', [ArchivoController::class, 'logo'])->name('marca.logo');
Route::get('cybers/{cyber}/logo', [ArchivoController::class, 'logoCyber'])->name('cybers.logo');

// Páginas legales y app (públicas)
Route::get('legal/{doc}', [LegalController::class, 'ver'])->name('legal.ver');
Route::get('eliminar-cuenta', [CuentaController::class, 'publicaMostrar'])->name('eliminar.mostrar');
Route::post('eliminar-cuenta', [CuentaController::class, 'publicaGuardar'])->name('eliminar.guardar')->middleware('throttle:5,1,eliminar.guardar');
Route::get('manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('.well-known/assetlinks.json', [PwaController::class, 'assetlinks'])->name('pwa.assetlinks');

Route::middleware('guest')->group(function () {
    Route::get('recuperar', [RecuperarController::class, 'mostrar'])->name('recuperar.mostrar');
    Route::post('recuperar', [RecuperarController::class, 'enviar'])->name('recuperar.enviar')->middleware('throttle:5,1,recuperar.enviar');
    Route::get('recuperar/codigo', [RecuperarController::class, 'codigo'])->name('recuperar.codigo');
    Route::post('recuperar/codigo', [RecuperarController::class, 'restablecer'])->name('recuperar.restablecer')->middleware('throttle:10,1,recuperar.restablecer');
    Route::get('/', [LoginController::class, 'mostrar'])->name('login');
    Route::post('login', [LoginController::class, 'iniciar'])->name('login.iniciar')->middleware('throttle:10,1,login.iniciar');
    // Registro abierto para que cualquier persona haga su CV
    Route::get('registro', [RegistroController::class, 'mostrar'])->name('registro.mostrar');
    Route::post('registro', [RegistroController::class, 'guardar'])->name('registro.guardar')->middleware('throttle:6,1,registro.guardar');
});

// Verificación del correo con código (después de registrarse)
Route::middleware(['auth', 'activo'])->group(function () {
    Route::get('verificar', [RegistroController::class, 'verificarMostrar'])->name('verificar.mostrar');
    Route::post('verificar', [RegistroController::class, 'verificarGuardar'])->name('verificar.guardar')->middleware('throttle:10,1,verificar.guardar');
    Route::post('verificar/reenviar', [RegistroController::class, 'reenviar'])->name('verificar.reenviar')->middleware('throttle:5,1,verificar.reenviar');
    Route::get('aceptar-terminos', [LegalController::class, 'aceptarMostrar'])->name('legal.aceptar');
    Route::post('aceptar-terminos', [LegalController::class, 'aceptarGuardar'])->name('legal.aceptar.guardar');
});

Route::middleware(['auth', 'activo', 'verificado', 'terminos'])->group(function () {
    Route::post('salir', [LoginController::class, 'salir'])->name('logout')->withoutMiddleware('verificado');
    Route::get('inicio', [DashboardController::class, 'index'])->name('dashboard');

    // ── Panel del cliente que se registró solo
    Route::middleware('rol:cliente')->prefix('mi-cv')->name('mi-cv.')->group(function () {
        Route::get('/', [MiCvController::class, 'panel'])->name('panel');
        Route::get('datos', [MiCvController::class, 'editar'])->name('editar');
        Route::put('datos', [MiCvController::class, 'actualizar'])->name('actualizar')->middleware('throttle:30,1,actualizar');
    });
    Route::middleware('rol:cliente')->prefix('mi-cuenta')->name('mi-cuenta.')->group(function () {
        Route::get('/', [CuentaController::class, 'mostrar'])->name('mostrar');
        Route::get('descargar-datos', [CuentaController::class, 'descargar'])->name('descargar')->middleware('throttle:10,1,descargar');
        Route::post('eliminar', [CuentaController::class, 'solicitar'])->name('solicitar')->middleware('throttle:5,1,solicitar');
        Route::post('eliminar/cancelar', [CuentaController::class, 'cancelar'])->name('cancelar');
    });

    // ── Cartas en PDF (admin, cyber y cliente)
    Route::get('cartas', [CartaController::class, 'index'])->name('cartas.index');
    Route::get('cartas/buscar-clientes', [CartaController::class, 'buscarClientes'])->name('cartas.buscar');
    Route::get('cartas/{tipo}', [CartaController::class, 'create'])->name('cartas.create');
    Route::post('cartas/{tipo}/vista', [CartaController::class, 'previsualizar'])->name('cartas.previsualizar')->middleware('throttle:90,1,cartas.previsualizar');
    Route::post('cartas/{tipo}', [CartaController::class, 'generar'])->name('cartas.generar')->middleware('throttle:20,1,cartas.generar');

    // ── CV (cada quien solo los clientes que puede ver) y foto
    Route::get('clientes/{cliente}/foto', [ArchivoController::class, 'fotoCliente'])->name('clientes.foto');
    Route::get('clientes/{cliente}/cv/vista', [CvController::class, 'vista'])->name('cv.vista');
    Route::get('clientes/{cliente}/cv/pdf', [CvController::class, 'pdf'])->name('cv.pdf')->middleware('throttle:30,1,cv.pdf');
    Route::post('api/consultar-documento', [DocumentoController::class, 'consultar'])->name('api.consultar-documento')->middleware('throttle:90,1,api.consultar-documento');

    // ── Mi perfil
    Route::get('perfil', [PerfilController::class, 'editar'])->name('perfil.edit');
    Route::put('perfil', [PerfilController::class, 'actualizar'])->name('perfil.update');
    Route::put('perfil/password', [PerfilController::class, 'password'])->name('perfil.password');

  Route::middleware('rol:admin,cyber')->group(function () {
    // ── Clientes (admin ve todos; cada cyber ve solo los suyos)
    Route::get('clientes', [ClienteController::class, 'index'])->name('clientes.index');
    Route::get('clientes/nuevo', [ClienteController::class, 'create'])->name('clientes.create');
    Route::post('clientes', [ClienteController::class, 'store'])->name('clientes.store')->middleware('throttle:40,1,clientes.store');
    Route::get('clientes/{cliente}', [ClienteController::class, 'show'])->name('clientes.show');
    Route::get('clientes/{cliente}/editar', [ClienteController::class, 'edit'])->name('clientes.edit');
    Route::put('clientes/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');
    Route::delete('clientes/{cliente}', [ClienteController::class, 'destroy'])->name('clientes.destroy');
  });

    // ── Solo usuarios de Cyber
    Route::middleware('rol:cyber')->group(function () {
        Route::get('mi-marca', [MarcaController::class, 'editar'])->name('marca.edit');
        Route::put('mi-marca', [MarcaController::class, 'actualizar'])->name('marca.update');
    });

    // ── Solo administrador general
    Route::middleware('rol:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('cybers', [CyberController::class, 'index'])->name('cybers.index');
        Route::get('cybers/nuevo', [CyberController::class, 'create'])->name('cybers.create');
        Route::post('cybers', [CyberController::class, 'store'])->name('cybers.store');
        Route::get('cybers/{cyber}/editar', [CyberController::class, 'edit'])->name('cybers.edit');
        Route::put('cybers/{cyber}', [CyberController::class, 'update'])->name('cybers.update');
        Route::post('cybers/{cyber}/toggle', [CyberController::class, 'toggle'])->name('cybers.toggle');
        Route::delete('cybers/{cyber}', [CyberController::class, 'destroy'])->name('cybers.destroy');

        Route::get('usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::get('usuarios/nuevo', [UsuarioController::class, 'create'])->name('usuarios.create');
        Route::post('usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
        Route::get('usuarios/{usuario}/editar', [UsuarioController::class, 'edit'])->name('usuarios.edit');
        Route::put('usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
        Route::post('usuarios/{usuario}/toggle', [UsuarioController::class, 'toggle'])->name('usuarios.toggle');
        Route::delete('usuarios/{usuario}', [UsuarioController::class, 'destroy'])->name('usuarios.destroy');

        Route::get('configuracion', [ConfiguracionController::class, 'editar'])->name('configuracion.edit');
        Route::put('configuracion', [ConfiguracionController::class, 'actualizar'])->name('configuracion.update');
        Route::post('configuracion/restablecer-cv', [ConfiguracionController::class, 'restablecerCv'])->name('configuracion.restablecer');

        Route::get('auditoria', [ActividadController::class, 'index'])->name('actividad');
        Route::post('usuarios/{usuario}/verificar', [UsuarioController::class, 'verificar'])->name('usuarios.verificar');

        Route::get('correo', [CorreoController::class, 'editar'])->name('correo.edit');
        Route::put('correo', [CorreoController::class, 'actualizar'])->name('correo.update');
        Route::post('correo/probar', [CorreoController::class, 'probar'])->name('correo.probar')->middleware('throttle:10,1,correo.probar');

        Route::get('recaptcha', [RecaptchaController::class, 'editar'])->name('recaptcha.edit');
        Route::put('recaptcha', [RecaptchaController::class, 'actualizar'])->name('recaptcha.update');

        Route::get('marca', [MarcaSistemaController::class, 'editar'])->name('marca.edit');
        Route::put('marca', [MarcaSistemaController::class, 'actualizar'])->name('marca.update');
        Route::get('legal', [LegalAdminController::class, 'editar'])->name('legal.edit');
        Route::put('legal', [LegalAdminController::class, 'actualizar'])->name('legal.update');
        Route::get('solicitudes', [SolicitudController::class, 'index'])->name('solicitudes.index');
        Route::post('solicitudes/{solicitud}/aprobar', [SolicitudController::class, 'aprobar'])->name('solicitudes.aprobar');
        Route::post('solicitudes/{solicitud}/rechazar', [SolicitudController::class, 'rechazar'])->name('solicitudes.rechazar');

        Route::get('errores', [ErrorController::class, 'index'])->name('errores.index');
        Route::post('errores/limpiar', [ErrorController::class, 'limpiar'])->name('errores.limpiar');
    });
});
