<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Services\MarcaNegocio;
use Illuminate\Http\Request;

/** "Mi marca": cada Cyber pone su logo y colores (se ven en su menú y en sus CV). */
class MarcaController extends Controller
{
    public function editar(Request $request)
    {
        $cyber = $request->user()->cyber;
        abort_unless($cyber, 403, 'Su usuario no tiene un Cyber asignado.');
        return view('cyber.marca', compact('cyber'));
    }

    public function actualizar(Request $request, MarcaNegocio $marca)
    {
        $cyber = $request->user()->cyber;
        abort_unless($cyber, 403);
        $marca->guardar($request, $cyber);
        Actividad::registrar('marca', $cyber->nombre);

        return back()->with('mensaje', 'Marca actualizada.');
    }
}
