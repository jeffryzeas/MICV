<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up(): void {
  Schema::table('cybers',function(Blueprint $t){$t->string('tipo_negocio',30)->default('cyber');$t->string('naturaleza',15)->default('natural');$t->string('pais',2)->nullable();$t->string('identificacion_fiscal',50)->nullable();$t->string('perfil_uso',30)->default('integral');$t->json('modulos')->nullable();$t->boolean('contabilidad_pos')->default(false);});
  // Existing country and currency configuration remains authoritative.
  if(Schema::hasTable('pos_config')) foreach(DB::table('pos_config')->get(['cyber_id','pais']) as $cfg) DB::table('cybers')->where('id',$cfg->cyber_id)->update(['pais'=>$cfg->pais]);
  Schema::create('contable_movimientos',function(Blueprint $t){$t->id();$t->foreignId('cyber_id')->constrained('cybers')->restrictOnDelete();$t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();$t->uuid('clave_operacion');$t->date('fecha');$t->string('tipo',10);$t->string('categoria',80);$t->string('concepto',250);$t->decimal('monto',16,2);$t->string('moneda',3);$t->string('referencia',100)->nullable();$t->string('origen',15)->default('manual');$t->foreignId('venta_id')->nullable()->constrained('pos_ventas')->restrictOnDelete();$t->string('estado',12)->default('activo');$t->text('motivo_anulacion')->nullable();$t->foreignId('anulada_por')->nullable()->constrained('users')->nullOnDelete();$t->timestamp('anulada_en')->nullable();$t->timestamps();$t->unique(['cyber_id','clave_operacion']);$t->unique('venta_id');$t->index(['cyber_id','fecha','estado']);});
  if(Schema::hasTable('configuracion')) { $cfg=DB::table('configuracion')->first(); if($cfg)DB::table('configuracion')->where('id',$cfg->id)->update(['legal_version'=>((int)explode('.', $cfg->legal_version ?: '1.0')[0]+1).'.0','legal_actualizado_en'=>now()]); }
 }
 public function down(): void {Schema::dropIfExists('contable_movimientos');Schema::table('cybers',fn(Blueprint $t)=>$t->dropColumn(['tipo_negocio','naturaleza','pais','identificacion_fiscal','perfil_uso','modulos','contabilidad_pos']));}
};
