<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\SolicitudEliminacion;
use App\Services\Cuenta;
use App\Services\Recaptcha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Privacidad: descargar mis datos y solicitar la eliminación de la cuenta. */
class CuentaController extends Controller
{
    public function mostrar(Request $request)
    {
        $u = $request->user();
        return view('mi-cv.cuenta', [
            'user' => $u,
            'pendiente' => SolicitudEliminacion::where('user_id', $u->id)->where('estado', 'pendiente')->latest()->first(),
            'historial' => SolicitudEliminacion::where('user_id', $u->id)->latest()->limit(5)->get(),
        ]);
    }

    public function descargar(Request $request)
    {
        $u = $request->user();
        Actividad::registrar('datos_descargados');
        $json = json_encode(Cuenta::exportar($u), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return response($json, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="mis-datos-' . Str::slug($u->name) . '.json"',
        ]);
    }

    public function solicitar(Request $request)
    {
        $u = $request->user();
        $request->validate(['password' => ['required'], 'motivo' => ['nullable', 'string', 'max:1000'], 'confirmo' => ['accepted']],
            ['confirmo.accepted' => 'Marque la casilla para confirmar que entiende que se borrarán sus datos.'], ['password' => 'contraseña']);
        if (!Hash::check($request->input('password'), $u->password)) {
            return back()->withErrors(['password' => 'La contraseña no es correcta.']);
        }
        if (SolicitudEliminacion::where('user_id', $u->id)->where('estado', 'pendiente')->exists()) {
            return back()->withErrors(['general' => 'Ya tiene una solicitud pendiente.']);
        }
        SolicitudEliminacion::create([
            'user_id' => $u->id, 'email' => $u->email, 'nombre' => $u->name, 'motivo' => $request->input('motivo'),
            'origen' => 'panel', 'estado' => 'pendiente', 'ip' => $request->ip(),
        ]);
        Actividad::registrar('eliminacion_solicitada', $u->email);

        return back()->with('mensaje', 'Recibimos su solicitud. Un administrador la revisará y le avisaremos por correo.');
    }

    public function cancelar(Request $request)
    {
        $s = SolicitudEliminacion::where('user_id', $request->user()->id)->where('estado', 'pendiente')->firstOrFail();
        $s->update(['estado' => 'cancelada']);
        Actividad::registrar('eliminacion_cancelada');
        return back()->with('mensaje', 'Solicitud cancelada. Su cuenta sigue activa.');
    }

    // ── Página pública (la exige Google Play): cualquiera puede pedirla con su correo
    public function publicaMostrar()
    {
        return view('legal.eliminar');
    }
}
