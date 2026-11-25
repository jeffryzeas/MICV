<?php

namespace App\Services;

class TelegramCvError extends \RuntimeException
{
    public function __construct(public readonly string $motivo = 'servicio', public readonly int $espera = 0)
    {
        parent::__construct(self::texto($motivo, $espera));
    }

    public static function texto(string $codigo, int $segundos = 0): string
    {
        return match ($codigo) {
            'ocupada' => 'La cuenta está procesando otra operación. Espere unos segundos.',
            'credenciales' => 'Revise el api_id y api_hash de su aplicación de Telegram.',
            'codigo' => 'El código no es válido. Compruebe el código recibido en Telegram.',
            'codigo_caducado' => 'El código caducó. Solicite uno nuevo.',
            'clave' => 'La contraseña de verificación en dos pasos no es válida.',
            'sesion' => 'La sesión dejó de ser válida. Conecte su número de nuevo.',
            'cuenta' => 'La sesión no corresponde a la cuenta esperada. Conéctela de nuevo.',
            'destino' => 'Telegram no permite localizar este número. Compruebe el número y que su titular permita encontrarlo.',
            'privacidad' => 'Telegram no permite enviar a este destinatario desde su cuenta.',
            'limite' => 'Telegram limitó esta operación. '.($segundos ? 'Espere al menos '.$segundos.' segundos.' : 'Revise su cuenta antes de continuar.'),
            'archivo' => 'La copia del CV no está disponible o no coincide con su hash. Prepare un nuevo borrador.',
            default => 'No se obtuvo una confirmación del servicio de Telegram. Revise la conexión del servidor.',
        };
    }
}
