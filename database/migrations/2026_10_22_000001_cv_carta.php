<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('autorizacion_laboral', 255)->nullable();
            $table->string('disponibilidad', 255)->nullable();
            $table->string('cualidades', 255)->nullable();
        });
        Schema::table('cliente_estudios', function (Blueprint $table) {
            $table->string('periodo', 255)->nullable();
        });
        Schema::table('cliente_experiencias', function (Blueprint $table) {
            $table->string('ubicacion', 255)->nullable();
            $table->string('jornada', 255)->nullable();
            $table->string('duracion', 255)->nullable();
        });
        Schema::table('cliente_referencias', function (Blueprint $table) {
            $table->string('detalle', 255)->nullable();
        });
    }
    public function down(): void {
        Schema::table('cliente_referencias', function (Blueprint $table) { $table->dropColumn(['detalle']); });
        Schema::table('cliente_experiencias', function (Blueprint $table) { $table->dropColumn(['ubicacion','jornada','duracion']); });
        Schema::table('cliente_estudios', function (Blueprint $table) { $table->dropColumn(['periodo']); });
        Schema::table('clientes', function (Blueprint $table) { $table->dropColumn(['autorizacion_laboral','disponibilidad','cualidades']); });
    }
};
