<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\Cliente;
use App\Models\PaisAdmin;
use App\Support\Archivos;
use App\Support\Pais;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Países y documentos: el admin puede crear países nuevos (con su escudo y
 * sus tipos de documento) y, en los de fábrica, cambiar el escudo, agregar
 * documentos o desactivarlos.
 */
class PaisController extends Controller
{
    public function index()
    {
        $filas = PaisAdmin::all()->keyBy('codigo');
        $uso = Cliente::selectRaw('pais, count(*) as n')->groupBy('pais')->pluck('n', 'pais');
        return view('admin.paises.index', ['paises' => Pais::todos(), 'filas' => $filas, 'uso' => $uso]);
    }

    public function crear()
    {
        return view('admin.paises.form', ['codigo' => null, 'pais' => null, 'fila' => new PaisAdmin(['nuevo' => true, 'activo' => true]), 'deFabrica' => false]);
    }

    public function editar(string $codigo)
    {
        $codigo = strtoupper($codigo);
        $todos = Pais::todos();
        abort_unless(isset($todos[$codigo]) && $codigo !== 'OT', 404);
        $fila = PaisAdmin::find($codigo) ?? new PaisAdmin(['codigo' => $codigo, 'nuevo' => false, 'activo' => true]);
        return view('admin.paises.form', ['codigo' => $codigo, 'pais' => $todos[$codigo], 'fila' => $fila, 'deFabrica' => isset(Pais::DATOS[$codigo])]);
    }

    public function guardar(Request $request)
    {
        $request->merge(['codigo' => strtoupper(trim((string) $request->input('codigo')))]);
        $request->validate(['codigo' => ['required', 'regex:/^[A-Z]{2}$/', Rule::notIn(array_keys(Pais::DATOS)), Rule::unique('paises', 'codigo')]],
            ['codigo.regex' => 'El código son 2 letras (código ISO del país, por ejemplo BR para Brasil).', 'codigo.not_in' => 'Ese país ya viene en el sistema. Edítelo en la lista.', 'codigo.unique' => 'Ese país ya fue creado.'],
            ['codigo' => 'código']);
        $fila = new PaisAdmin(['codigo' => $request->input('codigo'), 'nuevo' => true]);
        $this->llenar($request, $fila, true);
        Actividad::registrar('pais_crear', $fila->codigo);
        return redirect()->route('admin.paises.index')->with('mensaje', 'País ' . ($fila->datos['nombre'] ?? $fila->codigo) . ' creado. Ya aparece en el registro y en los formularios.');
    }

    public function actualizar(Request $request, string $codigo)
    {
        $codigo = strtoupper($codigo);
        $deFabrica = isset(Pais::DATOS[$codigo]);
        $fila = PaisAdmin::find($codigo);
        abort_unless($fila || $deFabrica, 404);
        $fila ??= new PaisAdmin(['codigo' => $codigo, 'nuevo' => false]);
        $this->llenar($request, $fila, !$deFabrica);
        Actividad::registrar('pais_editar', $codigo);
        return redirect()->route('admin.paises.index')->with('mensaje', 'Cambios guardados.');
    }

    /** Imagen del escudo (subido o de fábrica) para la vista del admin. */
    public function escudo(string $codigo)
    {
        $p = Pais::todos()[strtoupper($codigo)] ?? abort(404);
        $uri = !empty($p['escudo_subido']) ? Archivos::dataUri($p['escudo_subido'])
            : (!empty($p['escudo']) ? Archivos::dataUriLocal(resource_path('cv/' . $p['escudo'])) : null);
        abort_unless($uri, 404);
        [$meta, $b64] = explode(',', $uri, 2);
        return response(base64_decode($b64), 200, ['Content-Type' => Str::between($meta, 'data:', ';'), 'Cache-Control' => 'private, max-age=600']);
    }

    public function borrar(string $codigo)
    {
        $codigo = strtoupper($codigo);
        $fila = PaisAdmin::findOrFail($codigo);
        if (Cliente::where('pais', $codigo)->exists()) {
            return back()->withErrors(['general' => 'Hay personas registradas con ese país. Desactívelo en lugar de borrarlo.']);
        }
        Archivos::borrar($fila->escudo);
        if ($fila->nuevo) {
            $fila->delete();
            $msg = 'País borrado.';
        } else {
            $fila->delete(); // vuelve a como viene de fábrica
            $msg = 'Se quitaron los cambios: el país vuelve a como viene de fábrica.';
        }
        Actividad::registrar('pais_borrar', $codigo);
        return redirect()->route('admin.paises.index')->with('mensaje', $msg);
    }

    protected function llenar(Request $request, PaisAdmin $fila, bool $datosCompletos): void
    {
        $reglas = [
            'activo'               => ['nullable', 'boolean'],
            'escudo'               => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'docs'                 => ['nullable', 'array', 'max:10'],
            'docs.*.nombre'        => ['nullable', 'string', 'max:80'],
            'docs.*.clave'         => ['nullable', 'string', 'max:40'],
            'docs.*.formato'       => ['nullable', 'in:numeros,alfanumerico'],
            'docs.*.min'           => ['nullable', 'integer', 'between:1,30'],
            'docs.*.max'           => ['nullable', 'integer', 'between:1,30'],
            'docs.*.ayuda'         => ['nullable', 'string', 'max:120'],
        ];
        if ($datosCompletos) {
            $reglas += [
                'nombre'       => ['required', 'string', 'max:60'],
                'oficial'      => ['nullable', 'string', 'max:100'],
                'gentilicio'   => ['required', 'string', 'max:40'],
                'gentilicio_f' => ['nullable', 'string', 'max:40'],
                'telefono'     => ['nullable', 'regex:/^\+\d{1,4}$/'],
                'seguro_largo' => ['nullable', 'string', 'max:60'],
                'municipio'    => ['required', 'string', 'max:30'],
                'departamento' => ['required', 'string', 'max:30'],
                'companias'    => ['nullable', 'string', 'max:150'],
                'ley'          => ['nullable', 'string', 'max:150'],
                'ciudad'       => ['nullable', 'string', 'max:60'],
            ];
        }
        $d = $request->validate($reglas, ['telefono.regex' => 'El código de teléfono es + y números, por ejemplo +55.'], ['gentilicio' => 'nacionalidad (masculino)']);

        if ($datosCompletos) {
            $fila->datos = collect($d)->only(['nombre', 'oficial', 'gentilicio', 'gentilicio_f', 'telefono', 'seguro_largo', 'municipio', 'departamento', 'companias', 'ley', 'ciudad'])->all();
        }
        $docs = [];
        $usadas = array_keys(Pais::DATOS[$fila->codigo]['documentos'] ?? []);
        foreach ($d['docs'] ?? [] as $doc) {
            $nombre = trim((string) ($doc['nombre'] ?? ''));
            if ($nombre === '') {
                continue;
            }
            $clave = $doc['clave'] ?? '';
            if (!preg_match('/^x_[a-z]{2}_[a-z0-9_]+$/', $clave)) {
                $clave = 'x_' . strtolower($fila->codigo) . '_' . Str::limit(Str::slug($nombre, '_'), 24, '');
            }
            while (in_array($clave, $usadas, true)) {
                $clave .= '_2';
            }
            $usadas[] = $clave;
            $min = (int) ($doc['min'] ?? 4); $max = max($min, (int) ($doc['max'] ?? 20));
            $docs[] = ['clave' => $clave, 'nombre' => $nombre, 'formato' => ($doc['formato'] ?? '') ?: 'alfanumerico', 'min' => $min, 'max' => $max, 'ayuda' => trim((string) ($doc['ayuda'] ?? ''))];
        }
        if ($datosCompletos && !$docs) {
            throw \Illuminate\Validation\ValidationException::withMessages(['docs' => 'Agregue al menos un tipo de documento (por ejemplo, la cédula o el DNI de ese país).']);
        }
        $fila->documentos = $docs;
        $fila->activo = $request->boolean('activo');
        if ($request->hasFile('escudo')) {
            Archivos::borrar($fila->escudo);
            $fila->escudo = Archivos::guardarImagen($request->file('escudo'), 'escudos', 600, true);
        } elseif ($request->boolean('quitar_escudo')) {
            Archivos::borrar($fila->escudo);
            $fila->escudo = null;
        }
        $fila->save();
    }
}
