<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulaciones', fn (Blueprint $t) => $t->timestamp('cv_eliminado_en')->nullable()->index());
    }

    public function down(): void
    {
        Schema::table('postulaciones', function (Blueprint $t) {
            $t->dropIndex(['cv_eliminado_en']);
            $t->dropColumn('cv_eliminado_en');
        });
    }
};
