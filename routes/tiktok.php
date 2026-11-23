<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\TikTok\MonitorController as T;
Route::prefix('admin/tiktok')->name('admin.tiktok.')->middleware('rol:admin')->group(function(){
    Route::get('/',[T::class,'index'])->name('index');
    Route::post('cuentas',[T::class,'store'])->name('guardar')->middleware('throttle:guardar');
    Route::delete('cuentas/{cuenta}',[T::class,'eliminar'])->name('eliminar')->middleware('throttle:guardar');
    Route::get('adjuntos/{adjunto}',[T::class,'adjunto'])->name('adjunto')->whereUuid('adjunto')->middleware('throttle:1200,1');
    Route::post('cuentas/{cuenta}/lives/{live}/exportar',[T::class,'exportarLive'])->name('exportar-live')->middleware('throttle:10,1');
    Route::get('cuentas/{cuenta}/avatar',[T::class,'avatar'])->name('avatar')->middleware('throttle:60,1');
    Route::get('cuentas/{cuenta}',[T::class,'cuenta'])->name('cuenta');
    Route::post('cuentas/{cuenta}/captura',[T::class,'toggle'])->name('captura')->middleware('throttle:guardar');
    Route::get('cuentas/{cuenta}/eventos',[T::class,'eventos'])->name('eventos')->middleware('throttle:chat-leer');
    Route::get('cuentas/{cuenta}/lives/{live}/video',[T::class,'video'])->name('video')->middleware('throttle:240,1');
    Route::get('cuentas/{cuenta}/lives/{live}/video/recurso',[T::class,'videoRecurso'])->name('video.recurso')->middleware('throttle:240,1');
    Route::post('cuentas/{cuenta}/publicaciones',[T::class,'publicacion'])->name('publicacion')->middleware('throttle:guardar');
    Route::post('cuentas/{cuenta}/publicaciones/{publicacion}/importar',[T::class,'importar'])->name('importar')->middleware('throttle:guardar');
    Route::post('cuentas/{cuenta}/exportar',[T::class,'exportar'])->name('exportar')->middleware('throttle:10,1');
    Route::get('exportaciones/{exportacion}',[T::class,'exportacion'])->name('exportacion')->whereUuid('exportacion');
    Route::get('exportaciones/{exportacion}/paquete',[T::class,'paquete'])->name('paquete')->whereUuid('exportacion');
    Route::get('exportaciones/{exportacion}/descargar',[T::class,'descargar'])->name('descargar')->whereUuid('exportacion');
});
