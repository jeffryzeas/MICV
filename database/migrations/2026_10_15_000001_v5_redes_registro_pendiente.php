<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versión 5: LinkedIn / sitio web / GitHub / WhatsApp en la ficha y
 * registros en espera (la cuenta solo se crea cuando se verifica el correo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('linkedin', 200)->nullable();
            $table->string('sitio_web', 200)->nullable();
            $table->string('github', 200)->nullable();
            $table->boolean('telefono_whatsapp')->default(false);
        });

        Schema::create('registros_pendientes', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('email', 150)->index();
            $table->text('datos');                       // cifrado: nombre, país y contraseña (ya con hash)
            $table->string('codigo');                    // con hash
            $table->timestamp('expira_en');
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->timestamp('enviado_en')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_pendientes');
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['linkedin', 'sitio_web', 'github', 'telefono_whatsapp']);
        });
    }
};
