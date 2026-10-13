<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versión 9: lista privada de documentos, países creados por el admin
 * y tarjetas de presentación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_bloqueados', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 40)->unique();          // solo letras y números, en mayúscula
            $table->string('motivo', 255)->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('paises', function (Blueprint $table) {
            $table->string('codigo', 2)->primary();
            $table->boolean('nuevo')->default(true);         // false = país de fábrica con ajustes
            $table->boolean('activo')->default(true);
            $table->json('datos')->nullable();
            $table->json('documentos')->nullable();          // tipos de documento extra
            $table->string('escudo')->nullable();            // imagen subida por el admin
            $table->timestamps();
        });

        Schema::create('tarjetas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->unique()->constrained('clientes')->cascadeOnDelete();
            $table->string('diseno', 40)->default('corporativo');
            $table->string('color', 7)->nullable();
            $table->string('imagen', 10)->default('foto');   // foto | logo | ninguna
            $table->string('logo')->nullable();
            $table->string('qr', 10)->default('whatsapp');   // whatsapp | contacto | web | ninguno
            $table->json('datos')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarjetas');
        Schema::dropIfExists('paises');
        Schema::dropIfExists('documentos_bloqueados');
    }
};
