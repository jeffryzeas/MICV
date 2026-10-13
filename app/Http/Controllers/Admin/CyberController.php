<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\Cyber;
use App\Support\Archivos;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** El admin registra los Cyber (negocios). Cada uno tiene sus usuarios y sus clientes. */
class CyberController extends Controller
{
    public function index()
    {
        $cybers = Cyber::withCount(['usuarios', 'clientes'])->orderBy('nombre')->get();
        return view('admin.cybers.index', compact('cybers'));
    }

    public function create()
    {
        $siguiente = 'CY' . str_pad((string) (Cyber::max('id') + 1), 3, '0', STR_PAD_LEFT);
        return view('admin.cybers.form', ['cyber' => new Cyber(['codigo' => $siguiente, 'activo' => true, 'color_primario' => '#0B3C74', 'color_secundario' => '#C9971F'])]);
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);
        $cyber = Cyber::create($datos);
        $this->logo($request, $cyber);
        Actividad::registrar('cyber_crear', $cyber->nombre);

        return redirect()->route('admin.cybers.index')->with('mensaje', "Cyber «{$cyber->nombre}» registrado. Ahora créele sus usuarios.");
    }

    public function edit(Cyber $cyber)
    {
        return view('admin.cybers.form', compact('cyber'));
    }

    public function update(Request $request, Cyber $cyber)
    {
        $cyber->update($this->validar($request, $cyber));
        $this->logo($request, $cyber);
        Actividad::registrar('cyber_editar', $cyber->nombre);

        return redirect()->route('admin.cybers.index')->with('mensaje', 'Cyber actualizado.');
    }

    public function toggle(Cyber $cyber)
    {
        $cyber->update(['activo' => !$cyber->activo]);
        Actividad::registrar('cyber_toggle', $cyber->nombre . ' → ' . ($cyber->activo ? 'activo' : 'mantenimiento'));

        return back()->with('mensaje', $cyber->activo ? 'Cyber reactivado.' : 'Cyber en mantenimiento: sus usuarios no podrán entrar.');
    }

    public function destroy(Cyber $cyber)
    {
        if ($cyber->usuarios()->exists()) {
            return back()->withErrors(['general' => 'Este Cyber tiene usuarios. Elimínelos o muévalos a otro Cyber primero (o póngalo en mantenimiento).']);
        }
        $nombre = $cyber->nombre;
        Archivos::borrar($cyber->logo);
        $cyber->delete(); // los clientes NO se borran: solo se quitan de este Cyber
        Actividad::registrar('cyber_eliminar', $nombre);

        return redirect()->route('admin.cybers.index')->with('mensaje', "Cyber «{$nombre}» eliminado.");
    }

    protected function validar(Request $request, ?Cyber $c = null): array
    {
        $request->merge(['codigo' => mb_strtoupper(trim((string) $request->input('codigo')))]);
        $hex = ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        $d = $request->validate([
            'codigo'           => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('cybers', 'codigo')->ignore($c?->id)],
            'nombre'           => ['required', 'string', 'max:120'],
            'direccion'        => ['nullable', 'string', 'max:255'],
            'telefono'         => ['nullable', 'string', 'max:40'],
            'correo'           => ['nullable', 'email', 'max:150'],
            'color_primario'   => $hex,
            'color_secundario' => $hex,
            'logo'             => ['nullable', 'image', 'max:51200'],
        ], ['codigo.regex' => 'El código solo puede tener letras, números, guion y guion bajo.'], ['codigo' => 'código']);
        unset($d['logo']);
        $d['activo'] = $request->boolean('activo', true);
        return $d;
    }

    protected function logo(Request $request, Cyber $cyber): void
    {
        if ($request->hasFile('logo')) {
            Archivos::borrar($cyber->logo);
            $cyber->update(['logo' => Archivos::guardarImagen($request->file('logo'), 'logos', 600, true)]);
        } elseif ($request->boolean('quitar_logo')) {
            Archivos::borrar($cyber->logo);
            $cyber->update(['logo' => null]);
        }
    }
}
