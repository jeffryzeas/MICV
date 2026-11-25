<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('tiktok_telegram_config',function(Blueprint $t){$t->id();$t->text('bot_token')->nullable();$t->string('chat_id',24)->nullable();$t->string('bot_usuario',64)->nullable();$t->string('chat_tipo',20)->nullable();$t->uuid('revision');$t->boolean('activo')->default(false);$t->string('alcance',20)->default('todas');$t->json('cuentas')->nullable();$t->timestamp('verificada_en')->nullable();$t->timestamp('no_antes_de')->nullable();$t->string('ultimo_error',250)->nullable();$t->timestamps();});
  Schema::create('tiktok_telegram_envios',function(Blueprint $t){$t->id();$t->foreignId('comentario_id')->constrained('tiktok_comentarios')->cascadeOnDelete();$t->foreignId('cuenta_id')->constrained('tiktok_cuentas')->cascadeOnDelete();$t->char('clave',64)->unique();$t->uuid('revision');$t->string('destino',24);$t->string('estado',24)->default('pendiente');$t->unsignedInteger('intentos')->default(0);$t->string('mensaje_id',40)->nullable();$t->string('detalle',250)->nullable();$t->timestamp('iniciado_en')->nullable();$t->timestamp('enviado_en')->nullable();$t->timestamps();$t->index(['estado','id']);});
 }
 public function down(): void {Schema::dropIfExists('tiktok_telegram_envios');Schema::dropIfExists('tiktok_telegram_config');}
};
