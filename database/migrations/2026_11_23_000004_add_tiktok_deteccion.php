<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {Schema::table('tiktok_lives',fn(Blueprint $t)=>$t->json('deteccion_inicio')->nullable());}
 public function down(): void {Schema::table('tiktok_lives',fn(Blueprint $t)=>$t->dropColumn('deteccion_inicio'));}
};
