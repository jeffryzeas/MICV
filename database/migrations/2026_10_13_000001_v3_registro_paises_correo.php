<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versión 3: registro público de clientes (rol "cliente"), país del
 * documento, correo Gmail (SMTP), reCAPTCHA v3 y códigos de verificación.
 * Es una migración nueva: basta con "php artisan migrate".
 */
return new class extends Migration
{
    public function up(): void
    {
        // El rol deja de ser enum para admitir "cliente".
        Schema::table('users', function (Blueprint $table) {
            $table->string('rol', 20)->default('cyber')->change();
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->unique()->after('id')->constrained('users')->nullOnDelete();
            $table->string('pais', 2)->default('NI')->after('user_id');   // NI | CR | OT
            $table->string('numero_documento', 40)->nullable()->change();  // el cliente lo llena después de registrarse
        });

        Schema::table('configuracion', function (Blueprint $table) {
            $table->string('smtp_host', 150)->nullable();
            $table->unsignedSmallInteger('smtp_puerto')->nullable();
            $table->string('smtp_usuario', 150)->nullable();
            $table->text('smtp_password')->nullable();           // cifrada
            $table->string('smtp_encriptacion', 10)->nullable(); // tls | ssl
            $table->string('smtp_desde_correo', 150)->nullable();
            $table->string('smtp_desde_nombre', 150)->nullable();
            $table->boolean('smtp_activo')->default(false);

            $table->string('recaptcha_site_key', 200)->nullable();
            $table->text('recaptcha_secret_key')->nullable();     // cifrada
            $table->decimal('recaptcha_umbral', 3, 2)->default(0.50);
            $table->boolean('recaptcha_activo')->default(false);

            $table->boolean('registro_abierto')->default(true);
        });

        Schema::create('codigos_verificacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('codigo');                 // guardado con hash
            $table->timestamp('expira_en');
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->timestamp('usado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codigos_verificacion');
        Schema::table('configuracion', function (Blueprint $table) {
            $table->dropColumn([
                'smtp_host', 'smtp_puerto', 'smtp_usuario', 'smtp_password', 'smtp_encriptacion',
                'smtp_desde_correo', 'smtp_desde_nombre', 'smtp_activo',
                'recaptcha_site_key', 'recaptcha_secret_key', 'recaptcha_umbral', 'recaptcha_activo', 'registro_abierto',
            ]);
        });
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('pais');
        });
    }
};
