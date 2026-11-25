<?php

namespace App\Services;

class TelegramCvError extends \RuntimeException
{
    public const DIAGNOSTICOS = ['CONEXION', 'CENTRO_DATOS', 'TELEGRAM_TEMPORAL', 'ADAPTADOR_INTERNO', 'SIN_CLASIFICAR', 'PYTHON_NO_DISPONIBLE', 'PYTHON_DEPENDENCIA', 'PHP_PROC_OPEN', 'PROCESO_TIEMPO', 'PROCESO_SALIDA', 'RESPUESTA_INVALIDA'];

    public function __construct(public readonly string $motivo = 'servicio', public readonly int $espera = 0, public readonly string $diagnostico = '')
    {
        parent::__construct(self::texto($motivo, $espera).(in_array($diagnostico, self::DIAGNOSTICOS, true) ? ' Código de diagnóstico: '.$diagnostico.'.' : ''));
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
            'conexion' => 'El VPS no pudo conectar con los servidores de Telegram. Revise su salida de red.',
            'telegram_temporal' => 'Telegram no completó la solicitud de acceso. Espere un minuto y vuelva a intentarlo.',
            'python' => 'El servidor no pudo ejecutar el cliente de Telegram. Revise la instalación de Python y Telethon.',
            'php_proceso' => 'PHP tiene desactivada la ejecución de procesos. La administración debe habilitar proc_open para este módulo.',
            'qr_caducado'=>'La vinculación QR caducó. Genere un nuevo QR.',
            'qr_cancelado'=>'La vinculación QR fue cancelada.',
            default => 'No se obtuvo una confirmación del servicio de Telegram. Revise la conexión del servidor.',
        };
    }
}
