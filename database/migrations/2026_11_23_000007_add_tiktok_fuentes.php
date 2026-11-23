<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
 public function up(): void {
  Schema::create('tiktok_fuentes',function(Blueprint $t){$t->id();$t->foreignId('cuenta_id')->constrained('tiktok_cuentas')->cascadeOnDelete();$t->string('usuario',24);$t->unsignedSmallInteger('orden')->default(0);$t->string('estado',24)->default('pendiente');$t->string('detalle',300)->nullable();$t->unsignedBigInteger('latido_ms')->nullable();$t->unsignedBigInteger('reintentar_ms')->nullable();$t->string('sala',40)->nullable();$t->timestamps();$t->unique(['cuenta_id','usuario']);$t->index(['estado','reintentar_ms']);});
  Schema::table('tiktok_sesiones',function(Blueprint $t){$t->foreignId('fuente_id')->nullable()->constrained('tiktok_fuentes')->nullOnDelete();$t->string('usuario_sala',24)->nullable();});
  DB::table('tiktok_cuentas')->whereNotNull('sala_publica_usuario')->orderBy('id')->chunkById(200,function($cs){foreach($cs as$c){if(!preg_match('/^[a-z0-9_.]{2,24}$/D',$c->sala_publica_usuario))continue;DB::table('tiktok_fuentes')->insert(['cuenta_id'=>$c->id,'usuario'=>$c->sala_publica_usuario,'orden'=>0,'estado'=>$c->monitorear?'pendiente':'inactivo','reintentar_ms'=>$c->reintentar_ms,'created_at'=>now(),'updated_at'=>now()]);}});
 }
 public function down(): void {Schema::table('tiktok_sesiones',function(Blueprint $t){$t->dropConstrainedForeignId('fuente_id');$t->dropColumn('usuario_sala');});Schema::dropIfExists('tiktok_fuentes');}
};
