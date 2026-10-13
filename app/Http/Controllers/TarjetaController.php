<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\Cliente;
use App\Models\Tarjeta;
use App\Services\Correo;
use App\Services\CvPdf;
use App\Services\TarjetaRender;
use App\Support\Archivos;
use App\Support\TarjetaDisenos;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Tarjetas de presentación (aparte del CV): 20 diseños por oficio, QR a
 * WhatsApp / contacto / sitio web con logo al centro, y exportación en PNG
 * de alta calidad, PDF de una tarjeta o una hoja carta con 10 para recortar.
 */
class TarjetaController extends Controller
{
    public function index(Request $request)
    {
        $q = mb_substr(trim((string) $request->query("q")), 0, 80);
        $clientes = Cliente::visiblesPara($request->user())
            ->when(
                $q,
                fn($w) => $w->where(
                    fn($x) => $x
                        ->where("primer_nombre", "like", "%" . $q . "%")
                        ->orWhere("primer_apellido", "like", "%" . $q . "%")
                        ->orWhere("numero_documento", "like", "%" . $q . "%"),
                ),
            )
            ->orderBy("primer_nombre")
            ->paginate(20)
            ->withQueryString();

        return view("tarjetas.index", compact("clientes", "q"));
    }

    public function mia(Request $request)
    {
        $c = $request->user()->cliente;
        abort_unless($c, 404);

        return redirect()->route("tarjetas.editar", $c);
    }

    public function editar(Request $request, Cliente $cliente)
    {
        $this->autorizar($request, $cliente);
        $t = Tarjeta::de($cliente);

        return view("tarjetas.editar", [
            "cliente" => $cliente,
            "t" => $t,
            "esUsuario" => $request->user()->esCliente(),
        ]);
    }

    /** Vista previa en vivo (sin guardar). */
    public function vista(Request $request, Cliente $cliente)
    {
        $this->autorizar($request, $cliente);
        $t = $this->llenar($request, $cliente, false);
        $logoTemporal = $request->input("logo_temporal");
        if (
            $logoTemporal &&
            !preg_match("#^data:image/(png|jpe?g|webp);base64,#", $logoTemporal)
        ) {
            $logoTemporal = null;
        }

        return response(
            TarjetaRender::html($t, "pantalla", $logoTemporal),
        )->header("Cache-Control", "no-store");
    }

    public function guardar(Request $request, Cliente $cliente)
    {
        $this->autorizar($request, $cliente);
        $t = $this->llenar($request, $cliente, true);
        $t->save();
        Actividad::registrar("tarjeta_guardar", "#" . $cliente->id);

        return $request->expectsJson()
            ? response()->json(["ok" => true, "mensaje" => "Tarjeta guardada."])
            : back()->with("mensaje", "Tarjeta guardada.");
    }

    /** Guarda y descarga: png | pdf | hoja. */
    public function exportar(
        Request $request,
        Cliente $cliente,
        string $formato,
    ) {
        $this->autorizar($request, $cliente);
        abort_unless(in_array($formato, ["png", "pdf", "hoja"], true), 404);
        $t = $this->llenar($request, $cliente, true);
        $t->save();
        [$contenido, $tipo, $nombre] = $this->archivo($t, $formato);
        Actividad::registrar("tarjeta_" . $formato, "#" . $cliente->id);

        return response($contenido, 200, [
            "Content-Type" => $tipo,
            // Si lo pide la página (fetch), va "inline": la página misma lo guarda con su nombre,
            // y así los gestores de descarga no lo interceptan a medias.
            "Content-Disposition" =>
                ($request->boolean("ver") || $request->ajax()
                    ? "inline"
                    : "attachment") .
                '; filename="' .
                $nombre .
                '"',
            "Cache-Control" => "no-store",
        ]);
    }

    /** Envía la tarjeta (PNG + hoja para imprimir) al correo. */
    public function enviar(Request $request, Cliente $cliente)
    {
        $this->autorizar($request, $cliente);
        $u = $request->user();
        $para = $u->esCliente() ? $u->email : $cliente->correo;
        if (!$para) {
            return response()->json(
                ["ok" => false, "mensaje" => "No hay correo registrado."],
                422,
            );
        }
        if (!Correo::configurado()) {
            return response()->json(
                [
                    "ok" => false,
                    "mensaje" =>
                        "El correo del sistema todavía no está configurado.",
                ],
                422,
            );
        }
        $t = $this->llenar($request, $cliente, true);
        $t->save();
        try {
            $png = $this->archivo($t, "png");
            $hoja = $this->archivo($t, "hoja");
            Correo::enviar(
                $para,
                "Su tarjeta de presentación — " .
                    ($t->d("nombre") ?: $cliente->nombreCompleto()),
                "correos.aviso",
                [
                    "nombre" =>
                        $cliente->primer_nombre ?: $cliente->nombreCompleto(),
                    "parrafos" => [
                        "Aquí tiene su tarjeta de presentación, lista para compartir y para imprimir.",
                        "Va adjunta en imagen (para enviarla por WhatsApp o redes) y en PDF con 10 tarjetas en una hoja carta, con las líneas para recortar.",
                        'Consejo: imprímala en cartulina, a tamaño real (100 %), sin "ajustar a la página".',
                    ],
                    "boton" => [
                        "Abrir mi tarjeta",
                        route("tarjetas.editar", $cliente),
                    ],
                ],
                null,
                [
                    [$png[0], $png[2], "image/png"],
                    [$hoja[0], $hoja[2], "application/pdf"],
                ],
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json(
                ["ok" => false, "mensaje" => $e->getMessage()],
                500,
            );
        }
        Actividad::registrar("tarjeta_correo", "#" . $cliente->id);

        return response()->json([
            "ok" => true,
            "mensaje" =>
                "Listo: le enviamos la tarjeta a " .
                Str::mask($para, "•", 2, max(1, strpos($para, "@") - 2)) .
                ".",
        ]);
    }

    /** @return array{0: string, 1: string, 2: string} [contenido, tipo, nombre] */
    protected function archivo(Tarjeta $t, string $formato): array
    {
        $base = "Tarjeta_" . Str::slug($t->d("nombre") ?: "presentacion", "_");

        return match ($formato) {
            "png" => [
                CvPdf::png(TarjetaRender::html($t, "png"), ".tj", 4),
                "image/png",
                $base . ".png",
            ],
            "pdf" => [
                CvPdf::pdfTamano(
                    TarjetaRender::html($t, "pdf"),
                    TarjetaRender::ANCHO_MM,
                    TarjetaRender::ALTO_MM,
                ),
                "application/pdf",
                $base . ".pdf",
            ],
            "hoja" => [
                CvPdf::pdfTamano(TarjetaRender::html($t, "hoja"), 215.9, 279.4),
                "application/pdf",
                $base . "_hoja_10.pdf",
            ],
        };
    }

    protected function llenar(
        Request $request,
        Cliente $cliente,
        bool $guardarArchivos,
    ): Tarjeta {
        $d = $request->validate(
            [
                "diseno" => [
                    "nullable",
                    Rule::in(array_keys(TarjetaDisenos::CATALOGO)),
                ],
                "color" => ["nullable", 'regex:/^#[0-9a-fA-F]{6}$/'],
                "imagen" => ["nullable", "in:foto,logo,ninguna"],
                "qr" => ["nullable", "in:whatsapp,contacto,web,ninguno"],
                "nombre" => [
                    "nullable",
                    "string",
                    "max:" . Tarjeta::LIMITES["nombre"],
                ],
                "cargo" => [
                    "nullable",
                    "string",
                    "max:" . Tarjeta::LIMITES["cargo"],
                ],
                "telefono" => [
                    "nullable",
                    "string",
                    "max:" . Tarjeta::LIMITES["telefono"],
                ],
                "correo" => [
                    "nullable",
                    "email",
                    "max:" . Tarjeta::LIMITES["correo"],
                ],
                "direccion" => [
                    "nullable",
                    "string",
                    "max:" . Tarjeta::LIMITES["direccion"],
                ],
                "web" => [
                    "nullable",
                    "string",
                    "max:" . Tarjeta::LIMITES["web"],
                ],
                "redes" => [
                    "nullable",
                    "string",
                    "max:" . Tarjeta::LIMITES["redes"],
                ],
                "frase" => [
                    "nullable",
                    "string",
                    "max:" . Tarjeta::LIMITES["frase"],
                ],
                "logo" => [
                    "nullable",
                    "image",
                    "mimes:png,jpg,jpeg,webp",
                    "max:51200",
                ],
            ],
            [],
            [
                "diseno" => "diseño",
                "cargo" => "oficio o cargo",
                "direccion" => "zona o dirección",
                "web" => "sitio web",
                "redes" => "red social",
                "frase" => "frase",
                "telefono" => "celular",
            ],
        );

        $t = Tarjeta::de($cliente);
        if (!$request->has("diseno")) {
            return $t; // exportar sin cambios
        }
        $t->fill([
            "diseno" => $d["diseno"] ?? $t->diseno,
            "color" => $request->boolean("color_propio")
                ? $d["color"] ?? null
                : null,
            "imagen" => $d["imagen"] ?? $t->imagen,
            "qr" => $d["qr"] ?? $t->qr,
            "datos" => collect(Tarjeta::CAMPOS)
                ->mapWithKeys(fn($k) => [$k => trim((string) ($d[$k] ?? ""))])
                ->all(),
        ]);
        if ($guardarArchivos) {
            if ($request->hasFile("logo")) {
                Archivos::borrar($t->logo);
                $t->logo = Archivos::guardarImagen(
                    $request->file("logo"),
                    "tarjetas",
                    700,
                    true,
                );
            } elseif ($request->boolean("quitar_logo")) {
                Archivos::borrar($t->logo);
                $t->logo = null;
            }
        }
        if (
            $t->imagen === "logo" &&
            !$t->logo &&
            !$request->hasFile("logo") &&
            !$request->input("logo_temporal")
        ) {
            $t->imagen = $cliente->tieneFoto() ? "foto" : "ninguna";
        }
        $t->setRelation("cliente", $cliente);

        return $t;
    }

    protected function autorizar(Request $request, Cliente $cliente): void
    {
        abort_unless(
            $cliente->esVisiblePara($request->user()),
            403,
            "No tiene acceso a esta tarjeta.",
        );
    }
}
