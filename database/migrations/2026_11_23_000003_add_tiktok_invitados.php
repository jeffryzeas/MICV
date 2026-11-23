<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {Schema::table('tiktok_cuentas',function(Blueprint $t){$t->foreignId('anfitrion_id')->nullable()->constrained('tiktok_cuentas')->nullOnDelete();});}
 public function down(): void {Schema::table('tiktok_cuentas',fn(Blueprint $t)=>$t->dropConstrainedForeignId('anfitrion_id'));}
};
