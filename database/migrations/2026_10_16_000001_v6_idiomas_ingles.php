<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Versión 6: idiomas que habla la persona y textos en inglés para el CV en inglés. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_idiomas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('idioma', 60);
            $table->string('nivel', 20)->default('intermedio');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('profesion_en', 120)->nullable();
            $table->text('perfil_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_idiomas');
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['profesion_en', 'perfil_en']);
        });
    }
};
