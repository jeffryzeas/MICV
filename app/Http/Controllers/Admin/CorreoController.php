<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\Configuracion;
use App\Services\Correo;
use Illuminate\Http\Request;

/** Configurar el Gmail que envía los códigos de verificación. */
class CorreoController extends Controller
{
    public function editar()
    {
        return view('admin.correo', ['cfg' => Configuracion::actual()]);
    }

    public function actualizar(Request $request)
    {
        $d = $request->validate([
            'smtp_desde_correo' => ['required', 'email', 'max:150'],
            'smtp_desde_nombre' => ['nullable', 'string', 'max:150'],
            'smtp_password'     => ['nullable', 'string', 'max:200'],
            'smtp_host'         => ['required', 'string', 'max:150'],
            'smtp_puerto'       => ['required', 'integer', 'between:1,65535'],
            'smtp_encriptacion' => ['required', 'in:tls,ssl'],
        ], [], ['smtp_desde_correo' => 'correo', 'smtp_password' => 'contraseña de aplicación']);

        $cfg = Configuracion::actual();
        $datos = [
            'smtp_desde_correo' => mb_strtolower($d['smtp_desde_correo']),
            'smtp_usuario'      => mb_strtolower($d['smtp_desde_correo']),
            'smtp_desde_nombre' => $d['smtp_desde_nombre'] ?? null,
            'smtp_host'         => trim($d['smtp_host']),
            'smtp_puerto'       => (int) $d['smtp_puerto'],
            'smtp_encriptacion' => $d['smtp_encriptacion'],
            'smtp_activo'       => $request->boolean('smtp_activo'),
            'registro_abierto'  => $request->boolean('registro_abierto'),
        ];
        // La contraseña solo se cambia si se escribe una nueva (Gmail la muestra con espacios: se quitan).
        if (filled($d['smtp_password'] ?? null)) {
            $datos['smtp_password'] = preg_replace('/\s+/', '', $d['smtp_password']);
        }
        if ($datos['smtp_activo'] && blank($datos['smtp_password'] ?? $cfg->passwordSmtp())) {
            return back()->withInput()->withErrors(['smtp_password' => 'Falta la contraseña de aplicación de Gmail.']);
        }

        $cfg->update($datos);
        Actividad::registrar('correo_config', $datos['smtp_desde_correo']);

        return back()->with('mensaje', 'Configuración de correo guardada. Pruébela con "Enviar correo de prueba".');
    }

    public function probar(Request $request)
    {
        $request->validate(['para' => ['required', 'email']]);
        try {
            Correo::enviar($request->input('para'), 'Prueba de correo · ' . Configuracion::actual()->nombre_negocio, 'correos.prueba');
        } catch (\Throwable $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }
        Actividad::registrar('correo_prueba', $request->input('para'));
        return back()->with('mensaje', 'Correo de prueba enviado a ' . $request->input('para') . '. Revise la bandeja (y spam).');
    }
}
