<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_cv_envios', fn (Blueprint $t) => $t->timestamp('cv_eliminado_en')->nullable()->index());
    }

    public function down(): void
    {
        Schema::table('telegram_cv_envios', function (Blueprint $t) {
            $t->dropIndex(['cv_eliminado_en']);
            $t->dropColumn('cv_eliminado_en');
        });
    }
};
