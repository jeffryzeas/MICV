<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {Schema::table('users',fn(Blueprint $t)=>$t->json('edicion_cliente')->nullable());}
 public function down(): void {Schema::table('users',fn(Blueprint $t)=>$t->dropColumn('edicion_cliente'));}
};
