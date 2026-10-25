<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DesktopApiController as API;
Route::prefix('desktop/v1')->group(function(){
 Route::post('activate',[API::class,'activate'])->middleware('throttle:8,1');
 Route::middleware([\App\Http\Middleware\DesktopAuth::class,'throttle:120,1'])->group(function(){
  Route::post('renew',[API::class,'renew']);Route::get('catalog',[API::class,'catalog']);Route::post('push',[API::class,'push']);
 });
});
