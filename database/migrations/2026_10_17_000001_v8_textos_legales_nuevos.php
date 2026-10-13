<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Versión 8: textos legales nuevos. Si el administrador NO personalizó los
 * textos (usa los de fábrica), se publica una versión nueva para que todos
 * la acepten al entrar, porque el cambio es importante. Si los personalizó,
 * no se toca nada: él decide cuándo publicar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('configuracion')) {
            return;
        }
        $cfg = DB::table('configuracion')->first();
        if (!$cfg) {
            return;
        }
        $personalizado = filled($cfg->legal_terminos ?? null) || filled($cfg->legal_privacidad ?? null)
            || filled($cfg->legal_contrato ?? null) || filled($cfg->legal_cookies ?? null);
        if ($personalizado) {
            return;
        }
        $partes = explode('.', $cfg->legal_version ?: '1.0');
        $nueva = ((int) ($partes[0] ?? 1) + 1) . '.0';
        DB::table('configuracion')->where('id', $cfg->id)->update(['legal_version' => $nueva, 'legal_actualizado_en' => now()]);
    }

    public function down(): void
    {
        //
    }
};
