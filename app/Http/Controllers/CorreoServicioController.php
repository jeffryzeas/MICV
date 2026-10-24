<?php
namespace App\Http\Controllers;
use App\Models\{CorreoServicio, Cliente, Cyber, Actividad, DocumentoBloqueado};
use App\Support\{Pais, Documentos, Archivos};
use App\Services\{CedulaValidator, CvPdf};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\Rule;
class CorreoServicioController extends Controller
{
    private function acceso(Request $r): void
    {
        abort_unless(
            $r->user()->esAdmin() ||
                ($r->user()->esCyber() &&
                    $r->user()->cyber?->activo &&
                    $r->user()->tienePermiso("correos")),
            403,
        );
    }
    private function ficha(Request $r, CorreoServicio $correo): void
    {
        $this->acceso($r);
        abort_unless(
            ($r->user()->esAdmin() ||
                (int) $correo->cyber_id === (int) $r->user()->cyber_id) &&
                $correo->cliente->esVisiblePara($r->user()),
            403,
        );
    }
    public function index(Request $r)
    {
        $this->acceso($r);
        $q = CorreoServicio::with(["cliente", "cyber"])->when(
            !$r->user()->esAdmin(),
            fn($q) => $q->where("cyber_id", $r->user()->cyber_id),
        );
        if ($s = $r->string("q")->trim()->value()) {
            $q->where(
                fn($q) => $q
                    ->where("correo", "like", "%" . $s . "%")
                    ->orWhereHas(
                        "cliente",
                        fn($q) => $q
                            ->where("primer_nombre", "like", "%" . $s . "%")
                            ->orWhere(
                                "numero_documento",
                                "like",
                                "%" . $s . "%",
                            ),
                    ),
            );
        }
        return response()
            ->view("correos.index", [
                "registros" => $q->latest()->paginate(20)->withQueryString(),
                "paises" => Pais::activos(),
                "cybers" => $r->user()->esAdmin()
                    ? Cyber::where("activo", true)->get()
                    : collect(),
            ])
            ->header("Cache-Control", "private, no-store");
    }
    public function create(Request $r)
    {
        $this->acceso($r);
        return response()->view("correos.create", ["paises" => Pais::activos(), "cybers" => $r->user()->esAdmin() ? Cyber::where("activo", true)->get() : collect()])->header("Cache-Control", "private, no-store");
    }
    public function guardar(Request $r)
    {
        $this->acceso($r);
        $d = $r->validate([
            "cyber_id" => [
                $r->user()->esAdmin() ? "required" : "nullable",
                "integer",
            ],
            "pais" => ["required", Rule::in(array_keys(Pais::activos()))],
            "tipo_documento" => "required|string|max:40",
            "numero_documento" => "required|string|max:40",
            "primer_nombre" => "required|string|max:60",
            "segundo_nombre" => "nullable|string|max:60",
            "primer_apellido" => "required|string|max:60",
            "segundo_apellido" => "nullable|string|max:60",
            "celular" => [
                "required",
                "string",
                "max:40",
                'regex:/^\+?[0-9 ()-]{7,40}$/',
            ],
            "proveedor" => [
                "required",
                Rule::in([
                    "Gmail",
                    "Outlook",
                    "Yahoo",
                    "Proton",
                    "iCloud",
                    "Otro",
                ]),
            ],
            "correo" => "required|email|max:150",
            "clave_correo" => "required|string|min:1|max:256",
            "confirmado" => "accepted",
        ]);
        $cyberId = $r->user()->esAdmin()
            ? $d["cyber_id"]
            : $r->user()->cyber_id;
        abort_unless(
            Cyber::whereKey($cyberId)->where("activo", true)->exists(),
            422,
            "Seleccione un Cyber activo.",
        );
        $v = Validator::make($d, []);
        if (
            !array_key_exists(
                $d["tipo_documento"],
                Pais::de($d["pais"])["documentos"],
            )
        ) {
            $v->errors()->add(
                "tipo_documento",
                "Seleccione un documento del país indicado.",
            );
        }
        $numero = Documentos::limpiar(
            $d["tipo_documento"],
            $d["numero_documento"],
        );
        $valid =
            $d["tipo_documento"] === "cedula"
                ? CedulaValidator::validar($numero)
                : Documentos::validar($d["tipo_documento"], $numero);
        if (!$valid["valida"] || DocumentoBloqueado::estaBloqueado($numero)) {
            $v->errors()->add(
                "numero_documento",
                "Revise el documento antes de guardar.",
            );
        }
        $correo = mb_strtolower(trim($d["correo"]));
        $dominio = substr(strrchr($correo, "@"), 1);
        $dominios = [
            "Gmail" => ["gmail.com", "googlemail.com"],
            "Outlook" => [
                "outlook.com",
                "hotmail.com",
                "live.com",
                "outlook.es",
                "hotmail.es",
            ],
            "Yahoo" => ["yahoo.com", "yahoo.es"],
            "Proton" => ["proton.me", "protonmail.com", "pm.me"],
            "iCloud" => ["icloud.com", "me.com", "mac.com"],
        ];
        if (
            isset($dominios[$d["proveedor"]]) &&
            !in_array($dominio, $dominios[$d["proveedor"]], true)
        ) {
            $v->errors()->add(
                "correo",
                "El dominio no corresponde al proveedor seleccionado. Use Otro para dominios personalizados.",
            );
        }
        if (
            CorreoServicio::where("cyber_id", $cyberId)
                ->where("correo", $correo)
                ->exists()
        ) {
            $v->errors()->add(
                "correo",
                "Este correo ya está registrado en su Cyber.",
            );
        }
        if ($v->errors()->isNotEmpty()) {
            throw new \Illuminate\Validation\ValidationException($v);
        }
        try {
            $registro = DB::transaction(function () use (
                $r,
                $d,
                $numero,
                $valid,
                $cyberId,
                $correo,
            ) {
                Cyber::whereKey($cyberId)->lockForUpdate()->firstOrFail();
                $c = Cliente::where("tipo_documento", $d["tipo_documento"])
                    ->where("numero_documento", $numero)
                    ->lockForUpdate()
                    ->first();
                abort_if(
                    $c && !$c->esVisiblePara($r->user()),
                    422,
                    "El documento ya está registrado. Solicite al administrador revisar su vinculación.",
                );
                if (!$c) {
                    $c = Cliente::create(
                        collect($d)
                            ->only([
                                "pais",
                                "tipo_documento",
                                "primer_nombre",
                                "segundo_nombre",
                                "primer_apellido",
                                "segundo_apellido",
                            ])
                            ->all() + [
                            "numero_documento" => $numero,
                            "correo" => $correo,
                            "telefono" => $d["celular"],
                            "creado_por" => $r->user()->id,
                            "activo" => true,
                            "fecha_nacimiento" =>
                                $valid["fecha_nacimiento"] ?? null,
                            "municipio_nacimiento" =>
                                $valid["municipio"] ?? null,
                            "departamento_nacimiento" =>
                                $valid["departamento"] ?? null,
                            "edad_calculada" => $valid["edad"] ?? null,
                        ],
                    );
                }
                $c->cybers()->syncWithoutDetaching([
                    $cyberId => ["registrado_por" => $r->user()->id],
                ]);
                return CorreoServicio::create([
                    "cliente_id" => $c->id,
                    "cyber_id" => $cyberId,
                    "creado_por" => $r->user()->id,
                    "pais" => $d["pais"],
                    "proveedor" => $d["proveedor"],
                    "correo" => $correo,
                    "celular" => $d["celular"],
                    "clave_correo" => $d["clave_correo"],
                ]);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                "correo" =>
                    "El correo o documento ya se registró. Revise la lista antes de repetir el guardado.",
            ]);
        }
        Actividad::registrar(
            "correo.registrado",
            "Registro #" .
                $registro->id .
                "; cliente #" .
                $registro->cliente_id,
        );
        return redirect()
            ->route("correos.ver", $registro)
            ->with(
                "exito",
                "Correo registrado. Entregue el comprobante al titular.",
            );
    }
    public function ver(Request $r, CorreoServicio $correo)
    {
        $this->ficha($r, $correo);
        return response()
            ->view("correos.ver", compact("correo"))
            ->header("Cache-Control", "private, no-store");
    }
    public function actualizar(Request $r, CorreoServicio $correo)
    {
        $this->ficha($r, $correo);
        $d = $r->validate([
            "clave_correo" => "required|string|min:1|max:256",
            "celular" => [
                "required",
                "string",
                "max:40",
                'regex:/^\+?[0-9 ()-]{7,40}$/',
            ],
        ]);
        $correo->update($d);
        Actividad::registrar("correo.actualizado", "Registro #" . $correo->id);
        return back()->with("exito", "Datos actualizados.");
    }
    public function pdf(Request $r, CorreoServicio $correo)
    {
        $this->ficha($r, $correo);
        Actividad::registrar(
            "correo.comprobante",
            "Registro #" . $correo->id . "; incluye contraseña",
        );
        $logo =
            $correo->cyber->logo && Archivos::existe($correo->cyber->logo)
                ? Archivos::dataUri($correo->cyber->logo)
                : null;
        $qr = \App\Services\Nodo::correr(resource_path('reports/correo-qr.cjs'), [], 30, $correo->contenidoQr());
        if (!str_starts_with($qr,'data:image/png;base64,')) throw new \RuntimeException('No se pudo generar el QR.');
        $html = view("correos.pdf", compact("correo", "logo", "qr"))->render();
        return response(CvPdf::pdfTamano($html, 215.9, 279.4), 200, [
            "Content-Type" => "application/pdf",
            "Content-Disposition" =>
                'attachment; filename="correo-' . $correo->id . '.pdf"',
            "Cache-Control" => "private, no-store",
            "Pragma" => "no-cache",
        ]);
    }
}
