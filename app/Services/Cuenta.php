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
        $c = $u->cliente?->load(['estudios', 'experiencias', 'idiomas', 'habilidades', 'referencias', 'cybers']);
        $limpiar = fn ($m) => collect($m?->toArray() ?? [])->except(['id', 'user_id', 'cliente_id', 'creado_por', 'foto', 'pivot'])->all();

        return [
            'generado_en' => now()->toIso8601String(),
            'cuenta' => [
                'usuario' => $u->usuario, 'nombre' => $u->name, 'correo' => $u->email,
                'creada_en' => $u->created_at?->toIso8601String(), 'correo_verificado_en' => $u->email_verified_at?->toIso8601String(),
                'terminos_version' => $u->terminos_version, 'terminos_aceptados_en' => $u->terminos_aceptados_en?->toIso8601String(),
            ],
            'datos_personales' => $c ? collect($limpiar($c))->except(['estudios', 'experiencias', 'idiomas', 'habilidades', 'referencias', 'cybers'])->all() : null,
            'estudios'     => $c?->estudios->map($limpiar)->all() ?? [],
            'experiencias' => $c?->experiencias->map($limpiar)->all() ?? [],
            'habilidades'  => $c?->habilidades->pluck('habilidad')->all() ?? [],
            'idiomas'      => $c?->idiomas->map(fn ($x) => ['idioma' => $x->idioma, 'nivel' => $x->nivelTexto()])->all() ?? [],
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
                    self::borrarFicha($c);
                } else {
                    $c->update(['user_id' => null]);
                }
            }
            self::borrarUsuario($u);
        });
    }

    /**
     * Borra a una persona por completo, sin dejar rastro: su ficha, su foto,
     * su tarjeta, su usuario del sistema, sus códigos, sus sesiones y su
     * historial en la auditoría.
     */
    public static function borrarTodo(\App\Models\Cliente $c): void
    {
        DB::transaction(function () use ($c) {
            $u = $c->user_id ? User::find($c->user_id) : null;
            self::borrarFicha($c);
            if ($u && !$u->esAdmin() && !$u->esCyber()) {
                self::borrarUsuario($u);
            }
        });
    }

    protected static function borrarFicha(\App\Models\Cliente $c): void
    {
        Archivos::borrar($c->foto);
        if (class_exists(\App\Models\Tarjeta::class) && \Illuminate\Support\Facades\Schema::hasTable('tarjetas')) {
            foreach (\App\Models\Tarjeta::where('cliente_id', $c->id)->get() as $t) {
                Archivos::borrar($t->logo);
                $t->delete();
            }
        }
        $c->delete();
    }

    protected static function borrarUsuario(User $u): void
    {
        $email = $u->email;
        CodigoVerificacion::where('user_id', $u->id)->delete();
        DB::table('sessions')->where('user_id', $u->id)->delete();
        \App\Models\RegistroPendiente::where('email', $email)->delete();
        // Auditoría: se borra lo que hizo la persona y lo que la menciona.
        \App\Models\Actividad::where('user_id', $u->id)->delete();
        if ($email) {
            \App\Models\Actividad::where('detalle', 'like', '%' . $email . '%')->delete();
            // Las solicitudes de eliminación se conservan sin datos personales (solo como prueba).
            \App\Models\SolicitudEliminacion::where('email', $email)->update([
                'email' => 'borrado-' . substr(hash('sha256', $email), 0, 10), 'nombre' => null, 'motivo' => null, 'user_id' => null, 'ip' => null,
            ]);
        }
        $u->delete();
    }
}
