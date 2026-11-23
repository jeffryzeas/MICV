<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('tiktok_sesiones',function(Blueprint $t){$t->uuid('id')->primary();$t->foreignId('cuenta_id')->constrained('tiktok_cuentas')->cascadeOnDelete();$t->foreignId('live_id')->constrained('tiktok_lives')->cascadeOnDelete();$t->unsignedBigInteger('inicio_ms');$t->unsignedBigInteger('ultimo_ms');$t->unsignedBigInteger('fin_ms')->nullable();$t->string('motivo',40)->nullable();$t->index(['live_id','inicio_ms']);});
  Schema::create('tiktok_adjuntos',function(Blueprint $t){
   $t->uuid('id')->primary();$t->foreignId('cuenta_id')->constrained('tiktok_cuentas')->cascadeOnDelete();$t->foreignId('live_id')->constrained('tiktok_lives')->cascadeOnDelete();
   $t->char('url_sha256',64);$t->text('origen_url');$t->string('estado',20)->default('pendiente');$t->string('archivo')->nullable();$t->string('miniatura')->nullable();$t->char('sha256',64)->nullable();$t->string('mime',40)->nullable();$t->unsignedInteger('bytes')->nullable();$t->unsignedBigInteger('observado_ms');$t->unsignedBigInteger('descargado_ms')->nullable();$t->string('error',180)->nullable();$t->timestamps();$t->unique(['live_id','url_sha256']);$t->index(['cuenta_id','estado']);
  });
  Schema::table('tiktok_comentarios',function(Blueprint $t){$t->string('autor_id',80)->nullable();$t->char('autor_clave',64)->nullable()->index();$t->foreignUuid('avatar_id')->nullable()->constrained('tiktok_adjuntos')->nullOnDelete();$t->json('datos_publicos')->nullable();$t->char('hash_registro',64)->nullable();});
  Schema::table('tiktok_lives',fn(Blueprint $t)=>$t->json('perfil_inicio')->nullable());
  Schema::table('tiktok_exportaciones',function(Blueprint $t){$t->json('metadatos')->nullable();$t->string('paquete')->nullable();$t->char('sha256_datos',64)->nullable();});
 }
 public function down(): void {
  Schema::table('tiktok_exportaciones',fn(Blueprint $t)=>$t->dropColumn(['metadatos','paquete','sha256_datos']));
  Schema::table('tiktok_lives',fn(Blueprint $t)=>$t->dropColumn('perfil_inicio'));
  Schema::table('tiktok_comentarios',function(Blueprint $t){$t->dropForeign(['avatar_id']);$t->dropIndex(['autor_clave']);$t->dropColumn(['autor_id','autor_clave','avatar_id','datos_publicos','hash_registro']);});Schema::dropIfExists('tiktok_adjuntos');Schema::dropIfExists('tiktok_sesiones');
 }
};
