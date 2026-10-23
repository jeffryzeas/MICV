<?php
namespace App\Http\Controllers;
use App\Models\{MunicipioNicaragua, Actividad};
use App\Support\MunicipiosNicaragua;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MunicipioNicaraguaController extends Controller
{
    private function consultar(Request $r): void
    {
        $u = $r->user();
        abort_unless(
            $u->esAdmin() ||
                ($u->esCyber() &&
                    ($u->tienePermiso("clientes.ver") ||
                        $u->tienePermiso("correos"))),
            403,
        );
    }
    private function administrar(Request $r): void
    {
        abort_unless($r->user()->esAdmin(), 403);
    }
    public function index(Request $r)
    {
        $this->consultar($r);
        $d = $r->validate([
            "q" => "nullable|string|max:80",
            "departamento" => [
                "nullable",
                Rule::in([
                    ...MunicipiosNicaragua::DEPARTAMENTOS,
                    "Registro especial",
                ]),
            ],
            "estado" => "nullable|in:todos,activos,inactivos",
        ]);
        $base = collect(MunicipiosNicaragua::todos());
        $q = mb_strtolower(trim($d["q"] ?? ""));
        $normal = fn($s) => mb_strtolower(\Illuminate\Support\Str::ascii($s));
        $filas = $base
            ->filter(function ($m) use ($d, $q, $normal) {
                if (
                    !empty($d["departamento"]) &&
                    $m["departamento"] !== $d["departamento"]
                ) {
                    return false;
                }
                if (($d["estado"] ?? "todos") === "activos" && !$m["activo"]) {
                    return false;
                }
                if (($d["estado"] ?? "todos") === "inactivos" && $m["activo"]) {
                    return false;
                }
                return $q === "" ||
                    str_contains(
                        $normal(
                            $m["codigo"] .
                                " " .
                                $m["municipio"] .
                                " " .
                                $m["departamento"] .
                                " " .
                                ($m["alias"] ?? ""),
                        ),
                        $normal($q),
                    );
            })
            ->sortBy("municipio")
            ->groupBy("departamento")
            ->sortKeys();
        return view("municipios.index", [
            "grupos" => $filas,
            "total" => $base->where("especial", false)->count(),
            "especiales" => $base->where("especial", true)->count(),
            "activos" => $base->where("activo", true)->count(),
            "mostrados" => $filas->sum(fn($g) => $g->count()),
            "departamentos" => MunicipiosNicaragua::DEPARTAMENTOS,
            "editar" => $r->user()->esAdmin(),
        ]);
    }
    public function crear(Request $r)
    {
        $this->administrar($r);
        return view("municipios.form", [
            "fila" => new MunicipioNicaragua([
                "activo" => true,
                "especial" => false,
                "version" => 1,
            ]),
            "departamentos" => MunicipiosNicaragua::DEPARTAMENTOS,
        ]);
    }
    public function editar(Request $r, MunicipioNicaragua $municipio)
    {
        $this->administrar($r);
        return view("municipios.form", [
            "fila" => $municipio,
            "departamentos" => MunicipiosNicaragua::DEPARTAMENTOS,
        ]);
    }
    private function datos(Request $r, bool $nuevo): array
    {
        return $r->validate(
            [
                "codigo" => $nuevo
                    ? [
                        "required",
                        'regex:/^[0-9]{3}$/D',
                        Rule::notIn(["000"]),
                        Rule::unique("municipios_nicaragua", "codigo"),
                    ]
                    : ["prohibited"],
                "municipio" => [
                    "required",
                    "string",
                    "max:120",
                    "regex:/^[\\pL\\pM0-9 .()'’\\-]+$/u",
                ],
                "departamento" => [
                    "required",
                    Rule::in([
                        ...MunicipiosNicaragua::DEPARTAMENTOS,
                        "Registro especial",
                    ]),
                ],
                "alias" => [
                    "nullable",
                    "string",
                    "max:200",
                    "regex:/^[\\pL\\pM0-9 .(),;'’\\-]+$/u",
                ],
                "fuente" => "required|string|max:500",
                "notas" => "nullable|string|max:1000",
                "activo" => "required|boolean",
                "especial" => "required|boolean",
                "version" => $nuevo ? "prohibited" : "required|integer|min:1",
            ],
            [
                "codigo.regex" =>
                    "El código debe tener exactamente tres dígitos, incluidos los ceros iniciales.",
                "codigo.unique" =>
                    "El código ya existe; edítelo desde la lista.",
            ],
            [
                "municipio" => "municipio o descripción",
                "fuente" => "fuente o respaldo del código",
            ],
        );
    }
    private function coherencia(array $d): void
    {
        if (
            (bool) $d["especial"] !==
            ($d["departamento"] === "Registro especial")
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                "departamento" =>
                    "Los códigos especiales deben agruparse en Registro especial; los municipios deben tener un departamento o región.",
            ]);
        }
    }
    public function guardar(Request $r)
    {
        $this->administrar($r);
        $d = $this->datos($r, true);
        $this->coherencia($d);
        try {
            DB::transaction(function () use ($r, $d) {
                $fila = MunicipioNicaragua::create(
                    $d + ["modificado_por" => $r->user()->id],
                );
                Actividad::registrar(
                    "municipio_ni_crear",
                    $fila->codigo .
                        " · " .
                        $fila->municipio .
                        " · " .
                        $fila->departamento,
                );
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                "codigo" =>
                    "Otro usuario ya registró este código. Revise la lista.",
            ]);
        }
        return redirect()
            ->route("municipios.index", ["q" => $d["codigo"]])
            ->with(
                "mensaje",
                "Código creado. Ya se utiliza en la validación de cédulas.",
            );
    }
    public function actualizar(Request $r, MunicipioNicaragua $municipio)
    {
        $this->administrar($r);
        $d = $this->datos($r, false);
        $this->coherencia($d);
        DB::transaction(function () use ($r, $municipio, $d) {
            $fila = MunicipioNicaragua::whereKey($municipio->codigo)
                ->lockForUpdate()
                ->firstOrFail();
            abort_if(
                $fila->version !== (int) $d["version"],
                409,
                "Este registro cambió mientras lo editaba. Vuelva a abrirlo para evitar sobrescribir cambios.",
            );
            $antes = $fila->only([
                "municipio",
                "departamento",
                "activo",
                "especial",
            ]);
            $fila->fill(collect($d)->except("version")->all());
            $fila->version++;
            $fila->modificado_por = $r->user()->id;
            $fila->save();
            Actividad::registrar(
                "municipio_ni_editar",
                $fila->codigo .
                    "; antes: " .
                    json_encode($antes, JSON_UNESCAPED_UNICODE) .
                    "; ahora: " .
                    json_encode(
                        $fila->only([
                            "municipio",
                            "departamento",
                            "activo",
                            "especial",
                        ]),
                        JSON_UNESCAPED_UNICODE,
                    ),
            );
        });
        return redirect()
            ->route("municipios.index", ["q" => $municipio->codigo])
            ->with(
                "mensaje",
                "Catálogo actualizado. El cambio aplica a nuevas consultas; las fichas existentes no se reescriben.",
            );
    }
}
