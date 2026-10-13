<?php

namespace App\Services;

use App\Models\CodigoVerificacion;
use App\Models\User;
use App\Support\Archivos;
use Illuminate\Support\Facades\DB;

/** Exportar y eliminar los datos de una persona (derechos de acceso y cancelación). */
class Cuenta
{
    /** Todos los datos de la persona en un arreglo (para descargarlos en JSON). */
    public static function exportar(User $u): array
    {
        $c = $u->cliente?->load(['estudios', 'experiencias', 'habilidades', 'referencias', 'cybers']);
        $limpiar = fn ($m) => collect($m?->toArray() ?? [])->except(['id', 'user_id', 'cliente_id', 'creado_por', 'foto', 'pivot'])->all();

        return [
            'generado_en' => now()->toIso8601String(),
            'cuenta' => [
                'usuario' => $u->usuario, 'nombre' => $u->name, 'correo' => $u->email,
                'creada_en' => $u->created_at?->toIso8601String(), 'correo_verificado_en' => $u->email_verified_at?->toIso8601String(),
                'terminos_version' => $u->terminos_version, 'terminos_aceptados_en' => $u->terminos_aceptados_en?->toIso8601String(),
            ],
            'datos_personales' => $c ? collect($limpiar($c))->except(['estudios', 'experiencias', 'habilidades', 'referencias', 'cybers'])->all() : null,
            'estudios'     => $c?->estudios->map($limpiar)->all() ?? [],
            'experiencias' => $c?->experiencias->map($limpiar)->all() ?? [],
            'habilidades'  => $c?->habilidades->pluck('habilidad')->all() ?? [],
            'referencias'  => $c?->referencias->map($limpiar)->all() ?? [],
            'cybers'       => $c?->cybers->pluck('nombre')->all() ?? [],
            'tiene_foto'   => (bool) $c?->tieneFoto(),
        ];
    }

    /**
     * Elimina la cuenta. Si $borrarFicha es true también borra la ficha y el CV;
     * si la ficha la usa un Cyber y $borrarFicha es false, solo se desvincula.
     */
    public static function eliminar(User $u, bool $borrarFicha = true): void
    {
        DB::transaction(function () use ($u, $borrarFicha) {
            $c = $u->cliente;
            if ($c) {
                if ($borrarFicha || !$c->cybers()->exists()) {
                    Archivos::borrar($c->foto);
                    $c->delete();
                } else {
                    $c->update(['user_id' => null]);
                }
            }
            CodigoVerificacion::where('user_id', $u->id)->delete();
            DB::table('sessions')->where('user_id', $u->id)->delete();
            $u->delete();
        });
    }
}
