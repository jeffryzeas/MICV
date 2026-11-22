<?php
use App\Http\Controllers\Postulaciones\{EmpresaController, EnvioController, GmailController};
use Illuminate\Support\Facades\Route;

// Hereda auth, cuenta activa, correo verificado y términos desde web.php.
Route::prefix('postulaciones')->name('postulaciones.')->group(function () {
    Route::get('/',[EnvioController::class,'index'])->name('index');
    Route::get('nuevo',[EnvioController::class,'create'])->name('crear');
    Route::post('redactar',[EnvioController::class,'redactar'])->name('redactar')->middleware('throttle:guardar');
    Route::post('preparar',[EnvioController::class,'preparar'])->name('preparar')->middleware('throttle:pdf');
    Route::get('gmail/{usuario?}',[GmailController::class,'edit'])->name('gmail');
    Route::put('gmail/{usuario}',[GmailController::class,'update'])->name('gmail.guardar')->middleware('throttle:guardar');
    Route::post('gmail/{usuario}/comprobar',[GmailController::class,'comprobar'])->name('gmail.comprobar')->middleware('throttle:5,1');
    Route::delete('gmail/{usuario}',[GmailController::class,'disconnect'])->name('gmail.desconectar')->middleware('throttle:guardar');
    Route::get('empresas',[EmpresaController::class,'index'])->name('empresas');
    Route::get('empresas/nueva',[EmpresaController::class,'create'])->name('empresas.crear');
    Route::post('empresas',[EmpresaController::class,'store'])->name('empresas.guardar')->middleware('throttle:guardar');
    Route::get('empresas/{empresa}/editar',[EmpresaController::class,'edit'])->name('empresas.editar');
    Route::put('empresas/{empresa}',[EmpresaController::class,'update'])->name('empresas.actualizar')->middleware('throttle:guardar');
    Route::get('envios/{id}',[EnvioController::class,'ver'])->name('ver')->whereUuid('id');
    Route::get('envios/{id}/estado',[EnvioController::class,'estado'])->name('estado')->whereUuid('id');
    Route::get('envios/{id}/cv',[EnvioController::class,'pdf'])->name('pdf')->whereUuid('id');
    Route::get('envios/{id}/correo',[EnvioController::class,'correo'])->name('correo')->whereUuid('id');
    Route::post('envios/{id}/confirmar',[EnvioController::class,'confirmar'])->name('confirmar')->whereUuid('id')->middleware('throttle:cv-correo');
    Route::delete('envios/{id}',[EnvioController::class,'eliminar'])->name('eliminar')->whereUuid('id');
});
