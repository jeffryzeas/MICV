<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Services\ClienteGuardar;
use Illuminate\Http\Request;

/** Panel del cliente que se registró solo: sus datos, su CV y sus cartas. */
class MiCvController extends Controller
{
    protected function miCliente(Request $request): Cliente
    {
        $u = $request->user();
        $c = $u->cliente;
        if (!$c) {
            // Por si acaso (cuenta vieja): se crea su ficha vacía.
            $c = Cliente::create([
                'user_id' => $u->id, 'pais' => 'NI', 'tipo_documento' => 'cedula',
                'primer_nombre' => strtok($u->name, ' ') ?: $u->name, 'primer_apellido' => trim(strstr($u->name, ' ') ?: '-'),
                'correo' => $u->email, 'activo' => true, 'creado_por' => $u->id,
            ]);
        }
        return $c;
    }

    public function panel(Request $request)
    {
        $c = $this->miCliente($request)->load(['estudios', 'experiencias', 'habilidades', 'referencias']);
        $cfg = Configuracion::actual();

        return view('mi-cv.panel', [
            'cliente'  => $c,
            'avance'   => $c->porcentajeCompleto(),
            'cv'       => $cfg->cv(),
            'paletas'  => Configuracion::PALETAS_PRO,
        ]);
    }

    public function editar(Request $request)
    {
        $c = $this->miCliente($request)->load(['estudios', 'experiencias', 'habilidades', 'referencias']);
        return view('mi-cv.datos', ['cliente' => $c]);
    }

    public function actualizar(Request $request, ClienteGuardar $guardar)
    {
        $c = $this->miCliente($request);
        $guardar->actualizarPropio($request, $c);
        Actividad::registrar('mi_cv_actualizar', "#{$c->id}");

        return redirect()->route('mi-cv.panel')->with('mensaje', 'Sus datos se guardaron. Ya puede descargar su CV.');
    }
}
