<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $cambiar = function () {
            Schema::table('telegram_cv_cuentas', function (Blueprint $t) {
                $t->dropForeign(['user_id']);
            });
            Schema::table('telegram_cv_cuentas', function (Blueprint $t) {
                $t->unsignedBigInteger('user_id')->nullable()->change();
                $t->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                $t->boolean('sistema')->nullable()->unique();
            });
        };
        if (DB::getDriverName() === 'sqlite') {
            Schema::withoutForeignKeyConstraints($cambiar);
        } else {
            $cambiar();
        }
        $elegida = DB::table('telegram_cv_cuentas')->orderByRaw("CASE WHEN estado = 'conectada' THEN 0 WHEN api_hash IS NOT NULL THEN 1 ELSE 2 END")->orderBy('id')->first();
        if ($elegida) {
            DB::table('telegram_cv_cuentas')->where('id', $elegida->id)->update(['sistema' => true, 'user_id' => null]);
        }
        DB::table('telegram_cv_envios')->whereIn('estado', ['borrador', 'en_cola'])->update(['estado' => 'fallido', 'detalle' => 'Telegram pasó a ser una cuenta del sistema. Prepare un nuevo envío.']);
        Schema::create('telegram_cv_accesos', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('telegram_cuenta_id')->constrained('telegram_cv_cuentas')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->uuid('revision');
            $t->string('estado', 24)->default('en_cola')->index();
            $t->text('qr_url')->nullable();
            $t->timestamp('qr_expira_en')->nullable();
            $t->timestamp('expira_en');
            $t->timestamp('iniciado_en')->nullable();
            $t->timestamp('latido_en')->nullable();
            $t->string('detalle', 300)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_cv_accesos');
        Schema::table('telegram_cv_cuentas', function (Blueprint $t) {
            $t->dropUnique(['sistema']);
            $t->dropColumn('sistema');
        });
    }
};
