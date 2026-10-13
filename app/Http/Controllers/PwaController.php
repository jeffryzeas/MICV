<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;

/** Instalación como app (PWA) y verificación para Google Play (Trusted Web Activity). */
class PwaController extends Controller
{
    public function manifest()
    {
        $cfg = Configuracion::actualSegura();
        $nombre = $cfg?->nombre_negocio ?: 'Mi CV';
        return response()->json([
            'name' => $nombre,
            'short_name' => mb_substr($nombre, 0, 12),
            'description' => $cfg?->eslogan ?: 'Cree su currículum y sus cartas de empleo en minutos.',
            'lang' => 'es',
            'start_url' => '/?origen=app',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#ffffff',
            'theme_color' => $cfg?->color_primario ?: '#0B3C74',
            'categories' => ['productivity', 'business'],
            'icons' => [
                ['src' => asset('icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('icons/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }

    /** /.well-known/assetlinks.json — lo pide Google Play para abrir la web como app. */
    public function assetlinks()
    {
        $cfg = Configuracion::actualSegura();
        if (!$cfg?->android_paquete || !$cfg?->android_sha256) {
            return response()->json([]);
        }
        $huellas = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', strtoupper($cfg->android_sha256)))));
        return response()->json([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => ['namespace' => 'android_app', 'package_name' => $cfg->android_paquete, 'sha256_cert_fingerprints' => $huellas],
        ]]);
    }
}
