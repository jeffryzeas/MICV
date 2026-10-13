<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class ChatArchivo
{
    public static function guardar(UploadedFile $f): array
    {
        $ext = strtolower($f->getClientOriginalExtension());
        $mime = $f->getMimeType();
        $permitidos = [
            "jpg" => ["image/jpeg"],
            "jpeg" => ["image/jpeg"],
            "png" => ["image/png"],
            "webp" => ["image/webp"],
            "pdf" => ["application/pdf"],
            "docx" => [
                "application/zip",
                "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            ],
            "xlsx" => [
                "application/zip",
                "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            ],
            "pptx" => [
                "application/zip",
                "application/vnd.openxmlformats-officedocument.presentationml.presentation",
            ],
        ];
        $fallar = fn($m) => throw ValidationException::withMessages([
            "archivo" => $m,
        ]);
        if (
            !isset($permitidos[$ext]) ||
            !in_array($mime, $permitidos[$ext], true)
        ) {
            $fallar(
                "Use JPG, PNG, WebP, PDF, DOCX, XLSX o PPTX. No se aceptan macros, ejecutables ni archivos de código.",
            );
        }
        if (in_array($ext, ["docx", "xlsx", "pptx"], true)) {
            $z = new \ZipArchive();
            if ($z->open($f->getRealPath()) !== true) {
                $fallar("Documento Office inválido.");
            }
            try {
                $prefix = [
                    "docx" => "word/",
                    "xlsx" => "xl/",
                    "pptx" => "ppt/",
                ][$ext];
                if ($z->locateName("[Content_Types].xml") === false) {
                    $fallar("Documento Office inválido.");
                }
                $total = 0;
                $root = false;
                if ($z->numFiles > 3000) {
                    $fallar("Documento demasiado complejo.");
                }
                for ($i = 0; $i < $z->numFiles; $i++) {
                    $st = $z->statIndex($i);
                    $n = $st["name"];
                    $total += $st["size"];
                    $root = $root || str_starts_with($n, $prefix);
                    if (
                        $total > 100 * 1024 * 1024 ||
                        preg_match(
                            '~(vbaProject|activeX|embeddings/|\.\./|\.(exe|dll|js|ps1|bat|com|vbs|html|htm)$)~i',
                            $n,
                        )
                    ) {
                        $fallar(
                            "El documento contiene contenido activo o incrustado no permitido.",
                        );
                    }
                    if (
                        str_ends_with($n, ".rels") &&
                        preg_match(
                            '/TargetMode\s*=\s*[\'"]External/i',
                            (string) $z->getFromIndex($i),
                        )
                    ) {
                        $fallar(
                            "Quite los vínculos externos del documento antes de enviarlo.",
                        );
                    }
                }
                if (!$root) {
                    $fallar("El contenido no corresponde a la extensión.");
                }
            } finally {
                $z->close();
            }
        }
        if (
            $ext === "pdf" &&
            preg_match(
                "~/((JavaScript|JS|Launch|EmbeddedFile|RichMedia|OpenAction|AA)\b)~i",
                (string) file_get_contents($f->getRealPath()),
            )
        ) {
            $fallar(
                "No se aceptan PDF con acciones, scripts o archivos incrustados.",
            );
        }
        // Fallo cerrado: ningún archivo se publica si el antivirus falla o está ausente.
        try {
            $scan = new Process(
                [
                    config("chat.antivirus"),
                    "--no-summary",
                    "--infected",
                    $f->getRealPath(),
                ],
                null,
                null,
                null,
                60,
            );
            $scan->run();
            if (!$scan->isSuccessful()) {
                $fallar(
                    "Archivo rechazado o antivirus no disponible. Consulte al administrador.",
                );
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            $fallar("El antivirus no está disponible. Intente más tarde.");
        }
        if (str_starts_with($mime, "image/")) {
            $info = @getimagesize($f->getRealPath());
            if (!$info || $info[0] * $info[1] > 80000000) {
                $fallar("La foto no se puede abrir o supera 80 megapíxeles.");
            }
        }
        $path = "chat/" . Str::uuid() . "." . $ext;
        $disk = Storage::disk("local");
        if (!$disk->put($path, file_get_contents($f->getRealPath()))) {
            throw new \RuntimeException("No se pudo guardar el archivo.");
        }
        $extra = [];
        if (str_starts_with($mime, "image/")) {
            $dir = storage_path("app/tmp-chat");
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $tmp = $dir . "/" . Str::random(32) . ".jpg";
            try {
                Nodo::correr(
                    resource_path("images/chat-miniatura.cjs"),
                    [$f->getRealPath(), $tmp],
                    30,
                );
                $mini = "chat/" . Str::uuid() . ".jpg";
                if (!$disk->put($mini, file_get_contents($tmp))) {
                    throw new \RuntimeException(
                        "No se pudo guardar la miniatura.",
                    );
                }
                $extra["miniatura"] = $mini;
            } catch (\Throwable $e) {
                $disk->delete($path);
                throw $e;
            } finally {
                @unlink($tmp);
            }
        }

        return $extra + [
            "archivo" => $path,
            "nombre" => mb_substr(
                preg_replace(
                    '/[\x00-\x1f\x7f\/\\\\]/u',
                    "_",
                    basename($f->getClientOriginalName()),
                ),
                0,
                180,
            ),
            "mime" => $mime,
            "bytes" => $f->getSize(),
        ];
    }
}
