<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('pais_residencia', 2)->nullable();
        });
    }
    public function down(): void {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('pais_residencia');
        });
    }
};
