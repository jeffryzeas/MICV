<?php

namespace App\Support;

use App\Models\Actividad;
use App\Models\CodigoVerificacion;
use App\Models\RegistroPendiente;

/** Borra lo que la Política de privacidad dice que no se guarda por más tiempo. */
class Limpieza
{
    public static function ejecutar(): array
    {
        return [
            'auditoria'  => Actividad::where('created_at', '<', now()->subMonths(12))->delete(),
            'codigos'    => CodigoVerificacion::where('expira_en', '<', now()->subDay())->delete(),
            'pendientes' => RegistroPendiente::where('created_at', '<', now()->subHours(RegistroPendiente::HORAS_VIGENCIA))->delete(),
        ];
    }
}
