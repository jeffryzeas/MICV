<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\AutoservicioSolicitud;
use App\Models\Cliente;
use App\Models\ClienteEstudio;
use App\Models\ClienteIdioma;
use App\Models\Cyber;
use App\Models\DocumentoBloqueado;
use App\Services\CvDatos;
use App\Services\CvPdf;
use App\Services\Recaptcha;
use App\Support\AntiBot;
use App\Support\Archivos;
use App\Support\Documentos;
use App\Support\Pais;
use App\Support\SituacionLaboral;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Autoservicio: el Cyber pone un QR en el mostrador; la persona lo escanea y
 * llena sus datos desde su celular. El Cyber los revisa, crea la ficha e imprime.
 */
class AutoservicioController extends Controller
{
    // ───────────── Parte pública (celular del cliente)

    public function formulario(string $codigo)
    {
        $cyber = $this->cyberPublico($codigo);

        return view("autoservicio.formulario", [
            "cyber" => $cyber,
            "paisDefecto" => $this->paisDe($cyber),
        ]);
    }

    public function guardar(Request $request, string $codigo)
    {
        $cyber = $this->cyberPublico($codigo);
        if (AntiBot::esRobot($request)) {
            return back()
                ->withInput()
                ->withErrors([
                    "general" => "Revise los datos e intente de nuevo.",
                ]);
        }
        if (
            $error = Recaptcha::verificar(
                $request->input("recaptcha_token"),
                "autoservicio",
                true,
            )
        ) {
            return back()
                ->withInput()
                ->withErrors(["general" => $error]);
        }
        $paises = array_keys(Pais::activos());
        $d = $request->validate(
            [
                "pais" => ["required", Rule::in($paises)],
                "tipo_documento" => ["required", "string", "max:40"],
                "numero_documento" => ["required", "string", "max:40"],
                "primer_nombre" => ["required", "string", "max:60"],
                "segundo_nombre" => ["nullable", "string", "max:60"],
                "primer_apellido" => ["required", "string", "max:60"],
                "segundo_apellido" => ["nullable", "string", "max:60"],
                "fecha_nacimiento" => [
                    "nullable",
                    "date",
                    "before:today",
                    "after:1920-01-01",
                ],
                "sexo" => ["nullable", "in:M,F,O"],
                "estado_civil" => ["nullable", "string", "max:30"],
                "municipio_nacimiento" => ["nullable", "string", "max:80"],
                "departamento_nacimiento" => ["nullable", "string", "max:80"],
                "telefono_codigo_pais" => ["nullable", "string", "max:8"],
                "telefono" => ["required", "string", "max:30"],
                "telefono_whatsapp" => ["nullable", "boolean"],
                "correo" => ["nullable", "email", "max:150"],
                "direccion" => ["nullable", "string", "max:200"],
                "profesion" => ["nullable", "string", "max:120"],
                "pais_residencia" => [
                    "required",
                    Rule::in(array_diff(Pais::codigos(), ["OT"])),
                ],
                "nacionalidad" => ["required", "string", "max:60"],
                "autorizacion_laboral" => ["nullable", "string", "max:255"],
                "disponibilidad" => ["nullable", "string", "max:255"],
                "cualidades" => ["nullable", "string", "max:255"],
                "estudios.*.periodo" => ["nullable", "string", "max:255"],
                "estudios.*.detalle" => ["nullable", "string", "max:255"],
                "experiencias.*.ubicacion" => ["nullable", "string", "max:255"],
                "experiencias.*.jornada" => ["nullable", "string", "max:255"],
                "experiencias.*.duracion" => ["nullable", "string", "max:255"],
                "referencias.*.detalle" => ["nullable", "string", "max:255"],
                "perfil_profesional" => ["nullable", "string", "max:1500"],
                "estudios" => ["nullable", "array", "max:30"],
                "estudios.*.nivel" => [
                    "nullable",
                    Rule::in(array_keys(ClienteEstudio::NIVELES)),
                ],
                "estudios.*.titulo" => ["nullable", "string", "max:150"],
                "estudios.*.institucion" => ["nullable", "string", "max:150"],
                "estudios.*.anio" => [
                    "nullable",
                    "integer",
                    "min:1940",
                    "max:" . (date("Y") + 6),
                ],
                "experiencias" => ["nullable", "array", "max:5"],
                "experiencias.*.cargo" => ["nullable", "string", "max:120"],
                "experiencias.*.empresa" => ["nullable", "string", "max:150"],
                "experiencias.*.fecha_inicio" => ["nullable", "date"],
                "experiencias.*.fecha_fin" => ["nullable", "date"],
                "experiencias.*.descripcion" => [
                    "nullable",
                    "string",
                    "max:600",
                ],
                "habilidades_texto" => ["nullable", "string", "max:600"],
                "idiomas" => ["nullable", "array", "max:4"],
                "idiomas.*.idioma" => ["nullable", "string", "max:40"],
                "idiomas.*.nivel" => [
                    "nullable",
                    Rule::in(array_keys(ClienteIdioma::NIVELES)),
                ],
                "referencias" => ["nullable", "array", "max:3"],
                "referencias.*.nombre" => ["nullable", "string", "max:120"],
                "referencias.*.telefono" => ["nullable", "string", "max:30"],
                "acepto" => ["accepted"],
            ],
            [
                "acepto.accepted" =>
                    "Debe aceptar el aviso de privacidad para enviar sus datos.",
            ],
            [
                "numero_documento" => "número de documento",
                "primer_nombre" => "primer nombre",
                "primer_apellido" => "primer apellido",
                "telefono" => "celular",
                "fecha_nacimiento" => "fecha de nacimiento",
            ],
        );

        $d = SituacionLaboral::validar($d);

        // El documento se revisa con las mismas reglas del país que usa el Cyber.
        $tipos = Pais::de($d["pais"])["documentos"] ?? [];
        if (!array_key_exists($d["tipo_documento"], $tipos)) {
            return back()
                ->withInput()
                ->withErrors([
                    "tipo_documento" => "Elija el tipo de documento.",
                ]);
        }
        $numero = Documentos::limpiar(
            $d["tipo_documento"],
            $d["numero_documento"],
        );
        $rev = Documentos::validar($d["tipo_documento"], $numero);
        if (!$rev["valida"]) {
            return back()
                ->withInput()
                ->withErrors([
                    "numero_documento" => implode(" ", $rev["errores"]),
                ]);
        }
        if (DocumentoBloqueado::estaBloqueado($numero)) {
            // Mensaje neutro: no se le dice a nadie que el número está en la lista privada.
            return back()
                ->withInput()
                ->withErrors([
                    "numero_documento" =>
                        "No pudimos recibir estos datos. Por favor acérquese al mostrador.",
                ]);
        }

        $datos = collect($d)
            ->except(["acepto", "habilidades_texto"])
            ->all();
        $datos["numero_documento"] = $numero;
        $datos["telefono_whatsapp"] = $request->boolean("telefono_whatsapp")
            ? "1"
            : "0";
        $datos["habilidades"] = array_values(
            array_filter(
                array_map(
                    "trim",
                    preg_split(
                        '/[,\n;]+/',
                        (string) ($d["habilidades_texto"] ?? ""),
                    ),
                ),
                fn($h) => $h !== "" && mb_strlen($h) <= 80,
            ),
        );
        foreach (
            ["estudios", "experiencias", "idiomas", "referencias"]
            as $lista
        ) {
            $datos[$lista] = array_values(
                array_filter(
                    $datos[$lista] ?? [],
                    fn($fila) => count(
                        array_filter(
                            (array) $fila,
                            fn($v) => filled($v) &&
                                !in_array($v, ["intermedio"], true),
                        ),
                    ),
                ),
            );
        }

        $s = \Illuminate\Support\Facades\DB::transaction(function () use (
            $cyber,
            $datos,
            $d,
            $numero,
        ) {
            $actual = Cyber::whereKey($cyber->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless(
                $actual->qrAbierto(),
                503,
                "El QR cerró mientras completaba el formulario. Acérquese al mostrador.",
            );
            return AutoservicioSolicitud::create([
                "cyber_id" => $actual->id,
                "fecha" => today(),
                "turno" => AutoservicioSolicitud::siguienteTurno($actual->id),
                "nombre" => trim(
                    $d["primer_nombre"] . " " . $d["primer_apellido"],
                ),
                "numero_documento" => $numero,
                "datos" => $datos,
            ]);
        });

        return redirect()
            ->route("autoservicio.listo", $cyber->codigo)
            ->with("turno", $s->turno)
            ->with("nombre", $d["primer_nombre"]);
    }

    public function listo(string $codigo)
    {
        $cyber = $this->cyberPublico($codigo, false);
        if (!session("turno")) {
            return redirect()->route("autoservicio.formulario", $codigo);
        }

        return view("autoservicio.listo", [
            "cyber" => $cyber,
            "turno" => session("turno"),
            "nombre" => session("nombre"),
        ]);
    }

    // ───────────── Parte del Cyber

    public function panel(Request $request)
    {
        $cyber = $this->cyberDelUsuario($request);

        return view("autoservicio.panel", [
            "cyber" => $cyber,
            "cybers" => $request->user()->esAdmin()
                ? Cyber::orderBy("nombre")->get()
                : collect(),
            "enlace" => $cyber
                ? route("autoservicio.formulario", $cyber->codigo)
                : null,
        ]);
    }

    /** Lista para la pantalla del Cyber (se refresca sola). */
    public function lista(Request $request)
    {
        $u = $request->user();
        $q = AutoservicioSolicitud::query()
            ->de($u)
            ->with("cyber:id,nombre")
            ->whereDate("fecha", ">=", today()->subDays(1));
        if ($u->esAdmin() && $request->filled("cyber")) {
            $q->where("cyber_id", $request->integer("cyber"));
        }
        $filas = $q
            ->orderByRaw("CASE WHEN estado = 'pendiente' THEN 0 ELSE 1 END")
            ->orderBy("created_at")
            ->limit(60)
            ->get();

        return response()->json([
            "qr_abierto" => $this->cyberDelUsuario($request)?->qrAbierto(),
            "pendientes" => $filas->where("estado", "pendiente")->count(),
            "filas" => $filas
                ->map(
                    fn($s) => [
                        "id" => $s->id,
                        "turno" => $s->turno,
                        "nombre" => $s->nombre,
                        "documento" => $s->numero_documento,
                        "estado" => $s->estado,
                        "hace" => $s->created_at->locale("es")->diffForHumans(),
                        "cyber" => $s->cyber?->nombre,
                        "telefono" => $s->datos["telefono"] ?? null,
                        "atender" => route("clientes.create", [
                            "autoservicio" => $s->id,
                        ]),
                        "ficha" => $s->cliente_id
                            ? route("clientes.show", $s->cliente_id)
                            : null,
                    ],
                )
                ->values(),
        ]);
    }

    public function descartar(
        Request $request,
        AutoservicioSolicitud $solicitud,
    ) {
        $this->autorizar($request, $solicitud);
        $solicitud->update([
            "estado" => "descartada",
            "atendida_por" => $request->user()->id,
            "atendida_en" => now(),
        ]);

        return response()->json(["ok" => true]);
    }

    public function alternar(Request $request)
    {
        $cyber = $this->cyberDelUsuario($request);
        abort_unless($cyber, 404);
        $d = $request->validate([
            "activar" => "required|boolean",
            "horas" => "nullable|integer|in:1,2,4,8",
        ]);
        $activo = $request->boolean("activar");
        $cyber->update([
            "autoservicio" => $activo,
            "autoservicio_hasta" => $activo
                ? now()->addHours((int) ($d["horas"] ?? 4))
                : null,
        ]);
        Actividad::registrar(
            "autoservicio_" . ($cyber->autoservicio ? "activar" : "pausar"),
            $cyber->nombre,
        );

        return back()->with(
            "mensaje",
            $cyber->autoservicio
                ? "Autoservicio activado: el QR ya recibe datos."
                : "Autoservicio en pausa: el QR muestra un aviso y no recibe datos.",
        );
    }

    /** Afiche para el mostrador (PDF carta) con el QR grande. */
    public function afiche(Request $request)
    {
        $cyber = $this->cyberDelUsuario($request);
        abort_unless($cyber, 404);
        $html = view("autoservicio.afiche", [
            "cyber" => $cyber,
            "enlace" => route("autoservicio.formulario", $cyber->codigo),
            "logo" => Archivos::dataUri($cyber->logo),
            "qrJs" =>
                @file_get_contents(
                    public_path("vendor/qr-code-styling.min.js"),
                ) ?:
                "",
            "fuentesCss" => CvDatos::fuentesCss("pro"),
        ])->render();
        $pdf = CvPdf::pdfTamano($html, 215.9, 279.4);

        return response($pdf, 200, [
            "Content-Type" => "application/pdf",
            "Content-Disposition" =>
                'inline; filename="Afiche_autoservicio.pdf"',
        ]);
    }

    // ───────────── Ayudantes

    protected function cyberPublico(
        string $codigo,
        bool $exigirActivo = true,
    ): Cyber {
        $cyber = Cyber::where("codigo", $codigo)
            ->where("activo", true)
            ->first();
        abort_unless($cyber, 404);
        if ($exigirActivo && !$cyber->qrAbierto()) {
            abort(
                response()->view(
                    "autoservicio.pausa",
                    ["cyber" => $cyber],
                    503,
                ),
            );
        }

        return $cyber;
    }

    protected function cyberDelUsuario(Request $request): ?Cyber
    {
        $u = $request->user();
        if ($u->esAdmin()) {
            return $request->filled("cyber")
                ? Cyber::find($request->integer("cyber"))
                : Cyber::orderBy("nombre")->first();
        }

        return $u->cyber;
    }

    protected function autorizar(
        Request $request,
        AutoservicioSolicitud $s,
    ): void {
        $u = $request->user();
        abort_unless($u->esAdmin() || $s->cyber_id === $u->cyber_id, 403);
    }

    /** País que más usa el Cyber (para dejarlo elegido en el formulario). */
    protected function paisDe(Cyber $cyber): string
    {
        $pais = Cliente::query()
            ->whereHas("cybers", fn($q) => $q->where("cybers.id", $cyber->id))
            ->selectRaw("pais, count(*) as n")
            ->groupBy("pais")
            ->orderByDesc("n")
            ->value("pais");

        return $pais && isset(Pais::activos()[$pais])
            ? $pais
            : array_key_first(Pais::activos());
    }
}
