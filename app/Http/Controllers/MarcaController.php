<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Support\Archivos;
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

    public function actualizar(Request $request)
    {
        $cyber = $request->user()->cyber;
        abort_unless($cyber, 403);
        $hex = ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        $d = $request->validate([
            'color_primario'   => $hex,
            'color_secundario' => $hex,
            'telefono'         => ['nullable', 'string', 'max:40'],
            'direccion'        => ['nullable', 'string', 'max:255'],
            'correo'           => ['nullable', 'email', 'max:150'],
            'logo'             => ['nullable', 'image', 'max:4096'],
        ]);
        unset($d['logo']);
        $cyber->update($d);

        if ($request->hasFile('logo')) {
            Archivos::borrar($cyber->logo);
            $cyber->update(['logo' => Archivos::guardarImagen($request->file('logo'), 'logos', 600, true)]);
        } elseif ($request->boolean('quitar_logo')) {
            Archivos::borrar($cyber->logo);
            $cyber->update(['logo' => null]);
        }
        Actividad::registrar('marca', $cyber->nombre);

        return back()->with('mensaje', 'Marca actualizada.');
    }
}
