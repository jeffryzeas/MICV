<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versión 4: marca del sistema, textos legales versionados, aceptación de
 * términos, solicitudes de eliminación de cuenta, recuperación de
 * contraseña por código y datos para publicar la app en Google Play.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion', function (Blueprint $table) {
            // Marca
            $table->string('eslogan', 160)->nullable();
            $table->string('marca_telefono', 40)->nullable();
            $table->string('marca_whatsapp', 40)->nullable();
            $table->string('marca_correo', 150)->nullable();
            $table->string('marca_direccion')->nullable();
            $table->string('marca_sitio_web', 200)->nullable();
            $table->string('marca_facebook', 200)->nullable();
            $table->string('marca_instagram', 200)->nullable();
            $table->string('marca_tiktok', 200)->nullable();
            $table->string('pie_pagina', 255)->nullable();
            // Legal
            $table->string('legal_responsable', 160)->nullable();   // nombre o razón social
            $table->string('legal_identificacion', 60)->nullable(); // cédula física/jurídica o RUC
            $table->string('legal_pais', 2)->default('CR');
            $table->longText('legal_terminos')->nullable();
            $table->longText('legal_privacidad')->nullable();
            $table->longText('legal_contrato')->nullable();
            $table->longText('legal_cookies')->nullable();
            $table->string('legal_version', 20)->default('1.0');
            $table->timestamp('legal_actualizado_en')->nullable();
            // App Android (Google Play / TWA)
            $table->string('android_paquete', 150)->nullable();
            $table->text('android_sha256')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('terminos_version', 20)->nullable();
            $table->timestamp('terminos_aceptados_en')->nullable();
        });

        Schema::table('codigos_verificacion', function (Blueprint $table) {
            $table->string('tipo', 20)->default('verificacion')->after('user_id'); // verificacion | recuperar
        });

        Schema::create('solicitudes_eliminacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email', 150);
            $table->string('nombre', 150)->nullable();
            $table->text('motivo')->nullable();
            $table->string('origen', 20)->default('panel'); // panel | publica
            $table->string('estado', 20)->default('pendiente'); // pendiente | aprobada | rechazada | cancelada
            $table->text('nota_admin')->nullable();
            $table->foreignId('atendida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('atendida_en')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['estado', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_eliminacion');
        Schema::table('codigos_verificacion', fn (Blueprint $t) => $t->dropColumn('tipo'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['terminos_version', 'terminos_aceptados_en']));
        Schema::table('configuracion', function (Blueprint $table) {
            $table->dropColumn([
                'eslogan', 'marca_telefono', 'marca_whatsapp', 'marca_correo', 'marca_direccion', 'marca_sitio_web',
                'marca_facebook', 'marca_instagram', 'marca_tiktok', 'pie_pagina',
                'legal_responsable', 'legal_identificacion', 'legal_pais', 'legal_terminos', 'legal_privacidad',
                'legal_contrato', 'legal_cookies', 'legal_version', 'legal_actualizado_en', 'android_paquete', 'android_sha256',
            ]);
        });
    }
};
