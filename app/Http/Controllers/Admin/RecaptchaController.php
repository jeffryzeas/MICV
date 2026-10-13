<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\Configuracion;
use Illuminate\Http\Request;

class RecaptchaController extends Controller
{
    public function editar()
    {
        return view('admin.recaptcha', ['cfg' => Configuracion::actual()]);
    }

    public function actualizar(Request $request)
    {
        $d = $request->validate([
            'recaptcha_site_key'   => ['nullable', 'string', 'max:200'],
            'recaptcha_secret_key' => ['nullable', 'string', 'max:200'],
            'recaptcha_umbral'     => ['required', 'numeric', 'between:0.1,0.9'],
        ], [], ['recaptcha_site_key' => 'llave del sitio', 'recaptcha_secret_key' => 'llave secreta', 'recaptcha_umbral' => 'umbral']);

        $cfg = Configuracion::actual();
        $datos = [
            'recaptcha_site_key' => trim((string) ($d['recaptcha_site_key'] ?? '')) ?: null,
            'recaptcha_umbral'   => (float) $d['recaptcha_umbral'],
            'recaptcha_activo'   => $request->boolean('recaptcha_activo'),
        ];
        if (filled($d['recaptcha_secret_key'] ?? null)) {
            $datos['recaptcha_secret_key'] = trim($d['recaptcha_secret_key']);
        }
        if ($datos['recaptcha_activo'] && (blank($datos['recaptcha_site_key']) || blank($datos['recaptcha_secret_key'] ?? $cfg->secretoRecaptcha()))) {
            return back()->withInput()->withErrors(['recaptcha_site_key' => 'Para activarlo necesita las dos llaves (sitio y secreta).']);
        }
        $cfg->update($datos);
        Actividad::registrar('recaptcha_config', $datos['recaptcha_activo'] ? 'activado' : 'apagado');

        return back()->with('mensaje', $datos['recaptcha_activo'] ? 'reCAPTCHA v3 activado. Ya protege el login y el registro.' : 'reCAPTCHA guardado (apagado).');
    }
}
