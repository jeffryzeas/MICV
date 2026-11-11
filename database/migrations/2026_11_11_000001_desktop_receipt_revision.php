<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('desktop_receipts', function (Blueprint $table) {
            $table->string('entity_revision', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('desktop_receipts', function (Blueprint $table) {
            $table->dropColumn('entity_revision');
        });
    }
};
