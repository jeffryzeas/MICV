<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {Schema::table('tiktok_cuentas',fn(Blueprint $t)=>$t->string('sala_publica_usuario',24)->nullable());}
 public function down(): void {Schema::table('tiktok_cuentas',fn(Blueprint $t)=>$t->dropColumn('sala_publica_usuario'));}
};
