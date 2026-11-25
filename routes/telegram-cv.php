<?php

use App\Http\Controllers\Admin\TelegramCv\CuentaController;
use App\Http\Controllers\Admin\TelegramCv\EnvioController;
use App\Http\Controllers\Admin\TelegramCv\QrController;
use Illuminate\Support\Facades\Route;

Route::prefix('telegram-cv')->name('admin.telegram-cv.')->group(function () {
    Route::get('/', [EnvioController::class, 'index'])->name('index');
    Route::get('cuenta', [CuentaController::class, 'index'])->middleware('rol:admin')->name('cuenta');
    Route::post('cuenta/qr', [QrController::class, 'crear'])->middleware(['rol:admin', 'throttle:5,5'])->name('qr.crear');
    Route::get('cuenta/qr/{id}', [QrController::class, 'estado'])->whereUuid('id')->middleware(['rol:admin', 'throttle:60,1'])->name('qr.estado');
    Route::post('cuenta/qr/{id}/cancelar', [QrController::class, 'cancelar'])->whereUuid('id')->middleware('rol:admin')->name('qr.cancelar');
    Route::post('cuenta/codigo', [CuentaController::class, 'codigo'])->middleware('throttle:3,10')->middleware('rol:admin')->name('codigo');
    Route::post('cuenta/verificar', [CuentaController::class, 'verificar'])->middleware('throttle:5,5')->middleware('rol:admin')->name('verificar');
    Route::post('cuenta/desconectar', [CuentaController::class, 'desconectar'])->middleware('throttle:5,5')->middleware('rol:admin')->name('desconectar');
    Route::get('nuevo', [EnvioController::class, 'create'])->name('crear');
    Route::post('preparar', [EnvioController::class, 'preparar'])->middleware('throttle:5,10')->name('preparar');
    Route::get('envios/{id}', [EnvioController::class, 'ver'])->whereUuid('id')->name('ver');
    Route::get('envios/{id}/estado', [EnvioController::class, 'estado'])->whereUuid('id')->name('estado');
    Route::get('envios/{id}/pdf', [EnvioController::class, 'pdf'])->whereUuid('id')->name('pdf');
    Route::post('envios/{id}/confirmar', [EnvioController::class, 'confirmar'])->middleware('throttle:5,10')->whereUuid('id')->name('confirmar');
    Route::delete('envios/{id}', [EnvioController::class, 'eliminar'])->whereUuid('id')->name('eliminar');
});

Route::get('admin/telegram-cv/{resto?}', fn (?string $resto = null) => redirect('/telegram-cv'.($resto ? '/'.$resto : '')))->where('resto', '.*')->middleware('rol:admin');
