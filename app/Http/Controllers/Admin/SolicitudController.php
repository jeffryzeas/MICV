<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\SolicitudEliminacion;
use App\Models\User;
use App\Services\Correo;
use App\Services\Cuenta;
use Illuminate\Http\Request;

/** El admin aprueba o rechaza las solicitudes de eliminación de cuenta. */
class SolicitudController extends Controller
{
    public function index(Request $request)
    {
        $estado = (string) $request->query('estado', 'pendiente');
        $solicitudes = SolicitudEliminacion::with(['usuario.cliente', 'atendidaPor'])
            ->when($estado !== 'todas', fn ($q) => $q->where('estado', $estado))
            ->latest()->paginate(30)->withQueryString();
        return view('admin.solicitudes', compact('solicitudes', 'estado'));
    }

    public function aprobar(Request $request, SolicitudEliminacion $solicitud)
    {
        abort_unless($solicitud->estado === 'pendiente', 422, 'Esta solicitud ya fue atendida.');
        $user = $solicitud->usuario ?? User::where('email', $solicitud->email)->first();
        if ($user && $user->esAdmin()) {
            return back()->withErrors(['general' => 'Las cuentas de administrador no se eliminan desde aquí.']);
        }

        $nombre = $solicitud->nombre ?: $user?->name ?: 'usuario';
        $email = $solicitud->email;
        if ($user) {
            Cuenta::eliminar($user, $request->boolean('borrar_ficha', true));
        }
        $solicitud->update([
            'estado' => 'aprobada', 'atendida_por' => $request->user()->id, 'atendida_en' => now(),
            'nota_admin' => $request->input('nota') ?: ($user ? null : 'No existía una cuenta con ese correo.'),
            'motivo' => null, // no se guarda nada personal adicional
        ]);
        Actividad::registrar('eliminacion_aprobada', 'solicitud #' . $solicitud->id);

        $this->avisar($email, $nombre, [
            'Su solicitud para eliminar la cuenta fue aprobada.',
            $user ? 'Sus datos personales y su CV fueron eliminados de la plataforma.' : 'No encontramos una cuenta activa con este correo, por lo que no hay datos guardados.',
            'Gracias por haber usado nuestro servicio.',
        ]);
        return back()->with('mensaje', 'Solicitud aprobada' . ($user ? ': la cuenta fue eliminada.' : ' (no había cuenta con ese correo).'));
    }

    public function rechazar(Request $request, SolicitudEliminacion $solicitud)
    {
        abort_unless($solicitud->estado === 'pendiente', 422);
        $request->validate(['nota' => ['required', 'string', 'max:1000']], [], ['nota' => 'motivo del rechazo']);
        $solicitud->update(['estado' => 'rechazada', 'nota_admin' => $request->input('nota'), 'atendida_por' => $request->user()->id, 'atendida_en' => now()]);
        Actividad::registrar('eliminacion_rechazada', 'solicitud #' . $solicitud->id);
        $this->avisar($solicitud->email, $solicitud->nombre ?: 'usuario', [
            'Revisamos su solicitud para eliminar la cuenta y no pudimos completarla por este motivo:',
            $request->input('nota'),
            'Si tiene dudas, responda a este correo.',
        ]);
        return back()->with('mensaje', 'Solicitud rechazada y persona notificada.');
    }

    protected function avisar(string $email, string $nombre, array $parrafos): void
    {
        try {
            if (Correo::configurado()) {
                Correo::enviar($email, 'Su solicitud de eliminación de cuenta', 'correos.aviso', ['nombre' => $nombre, 'parrafos' => $parrafos]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
