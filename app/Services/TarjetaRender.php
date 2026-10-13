<?php

namespace App\Services;

use App\Models\Tarjeta;
use App\Support\Archivos;
use App\Support\Color;
use App\Support\Iconos;
use App\Support\TarjetaDisenos;

/** Arma el HTML de la tarjeta para la pantalla, el PNG, el PDF o la hoja de 10. */
class TarjetaRender
{
    public const ANCHO_MM = 85.6; // tamaño carné (CR80)
    public const ALTO_MM = 54.0;

    /** @param string $modo pantalla | png | pdf | hoja */
    public static function html(Tarjeta $t, string $modo = 'pantalla', ?string $logoTemporal = null): string
    {
        [$nombreDis, $categoria, $layout, $col, $iconos] = $t->disenoDatos();
        if ($t->color && preg_match('/^#[0-9a-fA-F]{6}$/', $t->color)) {
            $col['p'] = $t->color;
            if (in_array($layout, ['elegante'], true)) {
                $col['f'] = $t->color;
            }
        }
        $c = $t->cliente;
        $logo = $logoTemporal ?: ($t->logo ? Archivos::dataUri($t->logo) : null);
        $foto = $c?->tieneFoto() ? Archivos::dataUri($c->foto) : null;
        $imagen = match ($t->imagen) {
            'logo'  => $logo,
            'foto'  => $foto,
            default => null,
        };

        // Color del QR: el más oscuro del diseño, para que cualquier celular lo lea.
        $qrColor = '#111827';
        foreach ([$col['p'], $col['s'], $col['t']] as $cand) {
            if (Color::luminancia($cand) < 0.12) {
                $qrColor = $cand;
                break;
            }
        }
        $centro = $logo ?: ($t->qr === 'whatsapp' ? self::iconoWhatsapp() : null);

        $contactos = array_values(array_filter([
            $t->d('telefono') ? [$t->qr === 'whatsapp' ? 'whatsapp' : 'tel', $t->d('telefono')] : null,
            $t->d('correo') ? ['mail', $t->d('correo')] : null,
            $t->d('web') ? ['web', preg_replace('#^https?://(www\.)?#i', '', $t->d('web'))] : null,
            $t->d('redes') ? ['instagram', $t->d('redes')] : null,
            $t->d('direccion') ? ['casa', $t->d('direccion')] : null,
        ]));

        return view('tarjetas.render', [
            't' => $t, 'modo' => $modo, 'layout' => $layout, 'col' => $col,
            'textoP' => Color::textoSobre($col['p']), 'textoS' => Color::textoSobre($col['s']),
            'iconos' => array_map([TarjetaDisenos::class, 'icono'], $iconos),
            'imagen' => $imagen,
            'contactos' => array_slice($contactos, 0, 4),
            'qrTexto' => $t->contenidoQr(), 'qrColor' => $qrColor, 'qrColor2' => Color::oscurecerHex($qrColor, 0.65),
            'qrCentro' => $centro, 'I' => fn ($k) => $k === 'instagram' ? self::iconoInstagram() : Iconos::svg($k),
            'fuentesCss' => CvDatos::fuentesCss('pro'),
            'qrJs' => @file_get_contents(public_path('vendor/qr-code-styling.min.js')) ?: '',
        ])->render();
    }

    public static function iconoWhatsapp(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><circle cx="32" cy="32" r="32" fill="#25D366"/>'
            . '<path fill="#fff" d="M32 13c-10.5 0-19 8.3-19 18.6 0 3.5 1 6.8 2.8 9.6L13 51l10.2-2.7c2.7 1.4 5.7 2.2 8.8 2.2 10.5 0 19-8.3 19-18.6S42.5 13 32 13zm0 34c-2.8 0-5.6-.8-8-2.2l-.6-.3-6 1.6 1.6-5.8-.4-.6c-1.6-2.4-2.4-5.2-2.4-8.1 0-8.6 7.2-15.6 15.9-15.6s15.9 7 15.9 15.6S40.8 47 32 47z"/>'
            . '<path fill="#fff" d="M40.7 36.2c-.5-.2-2.8-1.4-3.2-1.5-.4-.2-.7-.2-1 .2-.3.5-1.2 1.5-1.5 1.8-.3.3-.5.4-1 .1-.5-.2-2-.7-3.8-2.3-1.4-1.2-2.4-2.8-2.6-3.2-.3-.5 0-.7.2-1 .2-.2.5-.5.7-.8.2-.3.3-.5.5-.8.2-.3.1-.6 0-.8-.1-.2-1-2.5-1.4-3.4-.4-.9-.7-.8-1-.8h-.9c-.3 0-.8.1-1.2.6-.4.5-1.6 1.5-1.6 3.7s1.6 4.3 1.9 4.6c.2.3 3.2 4.9 7.8 6.8 1.1.5 1.9.7 2.6.9 1.1.3 2.1.3 2.9.2.9-.1 2.8-1.1 3.2-2.2.4-1.1.4-2 .3-2.2-.1-.2-.4-.3-.9-.5z"/></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public static function iconoInstagram(): string
    {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".6" fill="currentColor"/></svg>';
    }
}
