<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DesktopApiController as API;
Route::prefix('desktop/v1')->group(function(){
 Route::get('connection',[API::class,'connection'])->middleware('throttle:30,1');
 Route::post('access',[API::class,'access'])->middleware('throttle:8,1');
 Route::post('activate',[API::class,'activate'])->middleware('throttle:8,1');
 Route::middleware([\App\Http\Middleware\DesktopAuth::class,'throttle:120,1'])->group(function(){
  Route::get('workspace/file',[\App\Http\Controllers\DesktopWorkspaceController::class,'file']);
  Route::get('workspace',[\App\Http\Controllers\DesktopWorkspaceController::class,'index'])->middleware('throttle:6,1');
  Route::get('dossiers',[\App\Http\Controllers\DesktopDossierController::class,'index']);
  Route::post('renew',[API::class,'renew']);Route::get('catalog',[API::class,'catalog']);Route::post('push',[API::class,'push']);
 });
});
