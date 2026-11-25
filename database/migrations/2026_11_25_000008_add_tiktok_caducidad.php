<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
use Carbon\Carbon;
return new class extends Migration {
 public function up(): void {
  Schema::table('tiktok_exportaciones',function(Blueprint $t){$t->timestamp('expira_en')->nullable()->index();$t->timestamp('limpiado_en')->nullable();});
  DB::table('tiktok_exportaciones')->where('estado','listo')->orderBy('id')->chunkById(200,function($rs){foreach($rs as$r)DB::table('tiktok_exportaciones')->where('id',$r->id)->update(['expira_en'=>Carbon::parse($r->updated_at)->addMinutes(5)]);});
 }
 public function down(): void {Schema::table('tiktok_exportaciones',function(Blueprint $t){$t->dropIndex(['expira_en']);$t->dropColumn(['expira_en','limpiado_en']);});}
};
