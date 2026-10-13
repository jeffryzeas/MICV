<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad en todas las respuestas: evitan que el sitio se
 * meta dentro de páginas ajenas (clickjacking), que el navegador "adivine"
 * tipos de archivo, y limitan de dónde se pueden cargar scripts.
 */
class CabecerasSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        $r = $next($request);

        $h = $r->headers;
        $h->set("X-Content-Type-Options", "nosniff");
        $h->set("X-Frame-Options", "SAMEORIGIN");
        if (!$h->has("Referrer-Policy")) {
            $h->set("Referrer-Policy", "strict-origin-when-cross-origin");
        }
        $h->set(
            "Permissions-Policy",
            "camera=(self), microphone=(self), geolocation=(), payment=(), usb=()",
        );
        $h->set("Cross-Origin-Opener-Policy", "same-origin");
        if ($request->isSecure()) {
            $h->set(
                "Strict-Transport-Security",
                "max-age=31536000; includeSubDomains",
            );
        }
        // Política de contenido: solo recursos propios, más Google reCAPTCHA cuando está activo.
        if (
            !$h->has("Content-Security-Policy") &&
            str_contains((string) $h->get("Content-Type"), "text/html")
        ) {
            $h->set(
                "Content-Security-Policy",
                implode("; ", [
                    "default-src 'self'",
                    "script-src 'self' 'unsafe-inline' https://www.google.com https://www.gstatic.com https://www.recaptcha.net",
                    "style-src 'self' 'unsafe-inline'",
                    "img-src 'self' data: blob:",
                    "font-src 'self' data:",
                    "media-src 'self' blob:",
                    "connect-src 'self' data: blob: https://www.google.com",
                    "frame-src 'self' blob: https://www.google.com https://www.recaptcha.net",
                    "frame-ancestors 'self'",
                    "object-src 'none'",
                    "base-uri 'self'",
                    "form-action 'self'",
                    "manifest-src 'self'",
                    "worker-src 'self'",
                ]),
            );
        }
        return $r;
    }
}
