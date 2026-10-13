<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\ClienteEstudio;
use App\Models\Cyber;
use App\Support\Archivos;
use App\Support\Pais;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Registra / actualiza un cliente con todo su CV.
 *
 * Igual que el Sistema JZ: la base de clientes es general. Si un Cyber
 * registra un documento que ya existe (por ejemplo lo registró otro Cyber),
 * NO se duplica: se vincula también a este Cyber y se completan sus datos.
 */
class ClienteGuardar
{
    /** Campos personales (todo lo que se le pide al cliente). */
    public const CAMPOS = [
        'inss_nss', 'primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido',
        'fecha_nacimiento', 'municipio_nacimiento', 'departamento_nacimiento',
        'sexo', 'nacionalidad', 'numero_hijos', 'estado_civil', 'profesion', 'perfil_profesional',
        'correo', 'telefono_codigo_pais', 'telefono', 'telefono_red',
        'telefono_secundario', 'telefono_secundario_red', 'direccion',
        'nombre_padre', 'nombre_madre', 'notas',
        'linkedin', 'sitio_web', 'github', 'telefono_whatsapp', 'profesion_en', 'perfil_en',
    ];

    /**
     * @return array{cliente: Cliente, ya_existia: bool}
     */
    public function registrar(Request $request): array
    {
        $request->merge(['numero_documento' => \App\Support\Documentos::limpiar($request->input('tipo_documento'), $request->input('numero_documento'))]);
        $datos = $request->validate($this->reglas(true), $this->mensajes(), $this->atributos());
        $datos = $this->completarSegunDocumento($datos);
        $cyberId = $this->cyberDestino($request);

        return DB::transaction(function () use ($request, $datos, $cyberId) {
            $existente = Cliente::where('tipo_documento', $datos['tipo_documento'])
                ->where('numero_documento', $datos['numero_documento'])->first();

            if ($existente) {
                // Ya estaba en la base general: solo se completan los datos que vienen llenos.
                $nuevos = collect($datos)->only(self::CAMPOS)->filter(fn ($v) => filled($v))->all();
                $nuevos['edad_calculada'] = $datos['edad_calculada'] ?? $existente->edad_calculada;
                $nuevos['pais'] = $datos['pais'];
                $existente->update($nuevos);
                $cliente = $existente;
                $this->sincronizarCv($cliente, $datos, soloSiVienenDatos: true);
            } else {
                $campos = collect($datos)->only(self::CAMPOS)->all();
                $campos += [
                    'pais'             => $datos['pais'],
                    'tipo_documento'   => $datos['tipo_documento'],
                    'numero_documento' => $datos['numero_documento'],
                    'edad_calculada'   => $datos['edad_calculada'] ?? null,
                    'activo'           => true,
                    'creado_por'       => $request->user()?->id,
                ];
                $cliente = Cliente::create($campos);
                $this->sincronizarCv($cliente, $datos, soloSiVienenDatos: false);
            }

            $this->guardarFoto($request, $cliente);
            self::sincronizarNombreCuenta($cliente);

            if ($cyberId) {
                $cliente->cybers()->syncWithoutDetaching([$cyberId => ['registrado_por' => $request->user()?->id]]);
            }

            return ['cliente' => $cliente->fresh(), 'ya_existia' => (bool) $existente];
        });
    }

    /** Editar desde el panel (admin o cyber). El documento y el país también se pueden cambiar. */
    public function actualizar(Request $request, Cliente $cliente): Cliente
    {
        $c = $this->actualizarPropio($request, $cliente, true);

        // Solo el admin puede cambiar a qué cyber pertenece la persona.
        if ($request->user()?->esAdmin() && $request->has('cybers_enviados')) {
            $ids = Cyber::whereIn('id', (array) $request->input('cybers', []))->pluck('id')->all();
            $c->cybers()->sync(collect($ids)->mapWithKeys(fn ($id) => [$id => ['registrado_por' => $request->user()->id]])->all());
        }
        return $c->fresh();
    }

    /** Edición completa de la ficha, incluidos el documento y el país. */
    public function actualizarPropio(Request $request, Cliente $cliente, bool $desdePanel = false): Cliente
    {
        $request->merge(['numero_documento' => \App\Support\Documentos::limpiar($request->input('tipo_documento'), $request->input('numero_documento'))]);
        $datos = $request->validate($this->reglas(true), $this->mensajes(), $this->atributos());
        $datos = $this->completarSegunDocumento($datos);

        $otro = Cliente::where('tipo_documento', $datos['tipo_documento'])
            ->where('numero_documento', $datos['numero_documento'])
            ->where('id', '!=', $cliente->id)->exists();
        if ($otro) {
            throw ValidationException::withMessages(['numero_documento' => 'Ese documento ya está registrado para otra persona en el sistema. Si es suyo, comuníquese con el administrador.']);
        }

        return DB::transaction(function () use ($request, $datos, $cliente, $desdePanel) {
            $campos = collect($datos)->only(self::CAMPOS)->all();
            $campos += [
                'pais'             => $datos['pais'],
                'tipo_documento'   => $datos['tipo_documento'],
                'numero_documento' => $datos['numero_documento'],
                'edad_calculada'   => $datos['edad_calculada'] ?? null,
            ];
            if ($desdePanel) {
                $campos['activo'] = $request->boolean('activo');
            }
            $cliente->update($campos);
            $this->sincronizarCv($cliente, $datos, soloSiVienenDatos: false);
            $this->guardarFoto($request, $cliente);
            self::sincronizarNombreCuenta($cliente);
            return $cliente->fresh();
        });
    }

    /** Cédula: fecha, municipio, departamento y edad salen de la cédula. Otros: son obligatorios. */
    protected function completarSegunDocumento(array $d): array
    {
        $pais = Pais::de($d['pais'] ?? 'NI');
        if (!array_key_exists($d['tipo_documento'], $pais['documentos'])) {
            throw ValidationException::withMessages(['tipo_documento' => 'Ese tipo de documento no corresponde a ' . $pais['nombre'] . '.']);
        }
        foreach (['linkedin', 'github', 'sitio_web'] as $red) {
            $d[$red] = Cliente::limpiarEnlace($d[$red] ?? null, $red);
        }
        $d['telefono_whatsapp'] = (bool) ($d['telefono_whatsapp'] ?? false);
        if (blank($d['nacionalidad'] ?? null) && $pais['gentilicio']) {
            $d['nacionalidad'] = \App\Support\Nacionalidades::de($d['pais'], $d['sexo'] ?? null) ?: $pais['gentilicio'];
        }

        if ($d['tipo_documento'] !== 'cedula') {
            // Reglas del documento de cada país; lo que se puede leer del número se completa solo.
            $v = \App\Support\Documentos::validar($d['tipo_documento'], $d['numero_documento']);
            if (!$v['valida']) {
                throw ValidationException::withMessages(['numero_documento' => $v['errores'][0]]);
            }
            foreach (['departamento' => 'departamento_nacimiento', 'fecha_nacimiento' => 'fecha_nacimiento', 'sexo' => 'sexo'] as $de => $a) {
                if ($v[$de] && blank($d[$a] ?? null)) {
                    $d[$a] = $v[$de];
                }
            }
        }

        if ($d['tipo_documento'] === 'cedula') {
            $r = CedulaValidator::validar($d['numero_documento']);
            if (!$r['valida']) {
                throw ValidationException::withMessages(['numero_documento' => 'Cédula inválida: ' . implode(' | ', $r['errores'])]);
            }
            $catalogado = $r['municipio'] && !str_contains($r['municipio'], 'no catalogado');
            $d['fecha_nacimiento'] = $r['fecha_nacimiento'];
            $d['departamento_nacimiento'] = $r['departamento'];
            $d['municipio_nacimiento'] = $catalogado ? $r['municipio'] : (($d['municipio_nacimiento'] ?? null) ?: null);
            $d['edad_calculada'] = $r['edad'];
        } else {
            $faltan = [];
            foreach (['fecha_nacimiento' => 'Fecha de nacimiento', 'municipio_nacimiento' => $pais['municipio'] . ' de nacimiento', 'departamento_nacimiento' => $pais['departamento'] . ' de nacimiento'] as $k => $t) {
                if (blank($d[$k] ?? null)) {
                    $faltan[$k] = "Falta: $t.";
                }
            }
            if ($faltan) {
                throw ValidationException::withMessages($faltan);
            }
            $d['edad_calculada'] = Carbon::parse($d['fecha_nacimiento'])->age;
        }
        return $d;
    }

    protected function cyberDestino(Request $request): ?int
    {
        $u = $request->user();
        if ($u && !$u->esAdmin()) {
            if (!$u->cyber_id) {
                throw ValidationException::withMessages(['general' => 'Su usuario no tiene un Cyber asignado. Hable con el administrador.']);
            }
            return $u->cyber_id;
        }
        $id = $request->input('cyber_id');
        return $id && Cyber::whereKey($id)->exists() ? (int) $id : null;
    }

    protected function guardarFoto(Request $request, Cliente $cliente): void
    {
        if ($request->hasFile('foto')) {
            Archivos::borrar($cliente->foto);
            $cliente->update(['foto' => Archivos::guardarImagen($request->file('foto'), 'fotos', 900)]);
        } elseif ($request->boolean('quitar_foto')) {
            Archivos::borrar($cliente->foto);
            $cliente->update(['foto' => null]);
        }
    }

    /** Reemplaza estudios, experiencia, idiomas, habilidades y referencias. */
    protected function sincronizarCv(Cliente $c, array $d, bool $soloSiVienenDatos): void
    {
        $estudios = array_values(array_filter($d['estudios'] ?? [], fn ($e) => filled($e['nivel'] ?? null) || filled($e['titulo'] ?? null) || filled($e['institucion'] ?? null)));
        $experiencias = array_values(array_filter($d['experiencias'] ?? [], fn ($e) => filled($e['cargo'] ?? null) || filled($e['empresa'] ?? null) || filled($e['descripcion'] ?? null)));
        $habilidades = array_values(array_filter(array_map(fn ($h) => trim((string) $h), $d['habilidades'] ?? []), fn ($h) => $h !== ''));
        $referencias = array_values(array_filter($d['referencias'] ?? [], fn ($r) => filled($r['nombre'] ?? null)));

        if (!$soloSiVienenDatos || $estudios) {
            $c->estudios()->delete();
            foreach ($estudios as $i => $e) {
                $c->estudios()->create([
                    'nivel' => ($e['nivel'] ?? null) ?: 'curso', 'titulo' => $e['titulo'] ?? null,
                    'institucion' => $e['institucion'] ?? null, 'anio' => $e['anio'] ?? null,
                    'detalle' => $e['detalle'] ?? null, 'orden' => $i,
                ]);
            }
        }
        if (!$soloSiVienenDatos || $experiencias) {
            $c->experiencias()->delete();
            foreach ($experiencias as $i => $e) {
                $c->experiencias()->create([
                    'cargo' => $e['cargo'] ?? null, 'empresa' => $e['empresa'] ?? null,
                    'descripcion' => $e['descripcion'] ?? null,
                    'fecha_inicio' => ($e['fecha_inicio'] ?? null) ?: null, 'fecha_fin' => ($e['fecha_fin'] ?? null) ?: null,
                    'orden' => $i,
                ]);
            }
        }
        $idiomas = array_values(array_filter($d['idiomas'] ?? [], fn ($x) => filled(trim((string) ($x['idioma'] ?? '')))));
        if (!$soloSiVienenDatos || $idiomas) {
            $c->idiomas()->delete();
            foreach ($idiomas as $i => $x) {
                $c->idiomas()->create(['idioma' => mb_substr(trim($x['idioma']), 0, 60), 'nivel' => ($x['nivel'] ?? null) ?: 'intermedio', 'orden' => $i]);
            }
        }

        if (!$soloSiVienenDatos || $habilidades) {
            $c->habilidades()->delete();
            foreach ($habilidades as $i => $h) {
                $c->habilidades()->create(['habilidad' => mb_substr($h, 0, 150), 'orden' => $i]);
            }
        }
        if (!$soloSiVienenDatos || $referencias) {
            $c->referencias()->delete();
            foreach ($referencias as $i => $r) {
                $c->referencias()->create([
                    'tratamiento' => $r['tratamiento'] ?? null, 'nombre' => $r['nombre'],
                    'telefono' => $r['telefono'] ?? null, 'correo' => $r['correo'] ?? null, 'orden' => $i,
                ]);
            }
        }
    }

    /** La cuenta del usuario registrado lleva siempre el mismo nombre que su CV. */
    public static function sincronizarNombreCuenta(Cliente $c): void
    {
        if (!$c->user_id) {
            return;
        }
        $nombre = mb_substr($c->fresh()->nombreCompleto(), 0, 150);
        if ($nombre !== '') {
            \App\Models\User::whereKey($c->user_id)->where('rol', 'cliente')->update(['name' => $nombre]);
        }
    }

    public static function limpiar($numero): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $numero));
    }

    protected function reglas(bool $nuevo): array
    {
        return [
            'pais'             => ['required', Rule::in(Pais::codigos())],
            'tipo_documento'   => ['required', Rule::in(array_keys(Pais::todosLosDocumentos()))],
            'numero_documento' => ['required', 'string', 'max:40'],
            'cyber_id'         => ['nullable', 'integer'],
            'inss_nss'         => ['nullable', 'string', 'max:40'],
            'primer_nombre'    => ['required', 'string', 'max:60'],
            'segundo_nombre'   => ['nullable', 'string', 'max:60'],
            'primer_apellido'  => ['required', 'string', 'max:60'],
            'segundo_apellido' => ['nullable', 'string', 'max:60'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'municipio_nacimiento'    => ['nullable', 'string', 'max:100'],
            'departamento_nacimiento' => ['nullable', 'string', 'max:100'],
            'sexo'             => ['nullable', Rule::in(['M', 'F', 'O'])],
            'nacionalidad'     => ['nullable', 'string', 'max:60'],
            'numero_hijos'     => ['nullable', 'integer', 'between:0,30'],
            'estado_civil'     => ['nullable', 'string', 'max:40'],
            'profesion'        => ['nullable', 'string', 'max:120'],
            'perfil_profesional' => ['nullable', 'string', 'max:1500'],
            'correo'           => ['nullable', 'email', 'max:150'],
            'telefono_codigo_pais' => ['nullable', 'string', 'max:8'],
            'telefono'         => ['nullable', 'string', 'max:40'],
            'telefono_red'     => ['nullable', 'string', 'max:30'],
            'telefono_secundario'     => ['nullable', 'string', 'max:40'],
            'telefono_secundario_red' => ['nullable', 'string', 'max:30'],
            'direccion'        => ['nullable', 'string', 'max:255'],
            'linkedin'         => ['nullable', 'string', 'max:200'],
            'sitio_web'        => ['nullable', 'string', 'max:200'],
            'github'           => ['nullable', 'string', 'max:200'],
            'telefono_whatsapp'=> ['nullable', 'boolean'],
            'nombre_padre'     => ['nullable', 'string', 'max:150'],
            'nombre_madre'     => ['nullable', 'string', 'max:150'],
            'notas'            => ['nullable', 'string', 'max:2000'],
            'foto'             => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192'],

            'estudios'                 => ['nullable', 'array', 'max:30'],
            'estudios.*.nivel'         => ['nullable', Rule::in(array_keys(ClienteEstudio::NIVELES))],
            'estudios.*.titulo'        => ['nullable', 'string', 'max:200'],
            'estudios.*.institucion'   => ['nullable', 'string', 'max:200'],
            'estudios.*.anio'          => ['nullable', 'integer', 'between:1940,2100'],
            'estudios.*.detalle'       => ['nullable', 'string', 'max:500'],
            'experiencias'                => ['nullable', 'array', 'max:30'],
            'experiencias.*.cargo'        => ['nullable', 'string', 'max:150'],
            'experiencias.*.empresa'      => ['nullable', 'string', 'max:200'],
            'experiencias.*.descripcion'  => ['nullable', 'string', 'max:1000'],
            'experiencias.*.fecha_inicio' => ['nullable', 'date'],
            'experiencias.*.fecha_fin'    => ['nullable', 'date'],
            'profesion_en'     => ['nullable', 'string', 'max:120'],
            'perfil_en'        => ['nullable', 'string', 'max:1500'],
            'idiomas'          => ['nullable', 'array', 'max:12'],
            'idiomas.*.idioma' => ['nullable', 'string', 'max:60'],
            'idiomas.*.nivel'  => ['nullable', Rule::in(array_keys(\App\Models\ClienteIdioma::NIVELES))],
            'habilidades'   => ['nullable', 'array', 'max:40'],
            'habilidades.*' => ['nullable', 'string', 'max:150'],
            'referencias'               => ['nullable', 'array', 'max:15'],
            'referencias.*.tratamiento' => ['nullable', 'string', 'max:20'],
            'referencias.*.nombre'      => ['nullable', 'string', 'max:150'],
            'referencias.*.telefono'    => ['nullable', 'string', 'max:40'],
            'referencias.*.correo'      => ['nullable', 'email', 'max:150'],
        ];
    }

    protected function mensajes(): array
    {
        return [
            'foto.image' => 'La foto debe ser una imagen (JPG, PNG o WEBP).',
            'foto.max'   => 'La foto no puede pesar más de 8 MB.',
        ];
    }

    protected function atributos(): array
    {
        return [
            'pais' => 'nacionalidad', 'numero_documento' => 'número de documento', 'primer_nombre' => 'primer nombre',
            'primer_apellido' => 'primer apellido', 'fecha_nacimiento' => 'fecha de nacimiento',
            'estudios.*.anio' => 'año', 'experiencias.*.fecha_inicio' => 'fecha de inicio',
            'experiencias.*.fecha_fin' => 'fecha de fin', 'referencias.*.correo' => 'correo de la referencia',
        ];
    }
}
