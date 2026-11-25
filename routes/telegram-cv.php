<?php

use App\Http\Controllers\Admin\TelegramCv\CuentaController;
use App\Http\Controllers\Admin\TelegramCv\EnvioController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/telegram-cv')->name('admin.telegram-cv.')->middleware('rol:admin')->group(function () {
    Route::get('/', [EnvioController::class, 'index'])->name('index');
    Route::get('cuenta', [CuentaController::class, 'index'])->name('cuenta');
    Route::post('cuenta/codigo', [CuentaController::class, 'codigo'])->middleware('throttle:3,10')->name('codigo');
    Route::post('cuenta/verificar', [CuentaController::class, 'verificar'])->middleware('throttle:5,5')->name('verificar');
    Route::post('cuenta/desconectar', [CuentaController::class, 'desconectar'])->middleware('throttle:5,5')->name('desconectar');
    Route::get('nuevo', [EnvioController::class, 'create'])->name('crear');
    Route::post('preparar', [EnvioController::class, 'preparar'])->middleware('throttle:5,10')->name('preparar');
    Route::get('envios/{id}', [EnvioController::class, 'ver'])->whereUuid('id')->name('ver');
    Route::get('envios/{id}/estado', [EnvioController::class, 'estado'])->whereUuid('id')->name('estado');
    Route::get('envios/{id}/pdf', [EnvioController::class, 'pdf'])->whereUuid('id')->name('pdf');
    Route::post('envios/{id}/confirmar', [EnvioController::class, 'confirmar'])->middleware('throttle:5,10')->whereUuid('id')->name('confirmar');
    Route::delete('envios/{id}', [EnvioController::class, 'eliminar'])->whereUuid('id')->name('eliminar');
});
