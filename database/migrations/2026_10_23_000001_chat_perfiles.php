<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table("users", function (Blueprint $t) {
            $t->string("chat_foto")->nullable();
            $t->string("chat_info", 200)->nullable();
        });
    }
    public function down(): void
    {
        Schema::table(
            "users",
            fn(Blueprint $t) => $t->dropColumn(["chat_foto", "chat_info"]),
        );
    }
};
