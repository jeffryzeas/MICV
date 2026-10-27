<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up(): void {
  Schema::table('desktop_devices',function(Blueprint $t){$t->foreignId('license_id')->nullable()->change();$t->foreignId('business_id')->nullable()->constrained('cybers')->restrictOnDelete();$t->string('access_role',20)->nullable();});
 }
 public function down(): void {
  if(DB::table('desktop_devices')->whereNull('license_id')->exists())throw new \RuntimeException('Hay accesos sin licencia. Respalde y gestione esos accesos antes de revertir la migración.');
  Schema::table('desktop_devices',function(Blueprint $t){$t->dropConstrainedForeignId('business_id');$t->dropColumn('access_role');$t->foreignId('license_id')->nullable(false)->change();});
 }
};
