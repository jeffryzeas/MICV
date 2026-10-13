<?php

namespace App\Support;

use App\Models\Configuracion;
use Illuminate\Support\Str;

/** Textos legales: el admin los edita (Markdown) o se usan los de fábrica. */
class Legal
{
    public const DOCUMENTOS = [
        'terminos'   => ['Términos y condiciones', 'legal_terminos', 'file-check'],
        'privacidad' => ['Política de privacidad', 'legal_privacidad', 'shield'],
        'contrato'   => ['Contrato de servicio (Cybers)', 'legal_contrato', 'file-signature'],
        'cookies'    => ['Política de cookies', 'legal_cookies', 'cookie'],
    ];

    public static function base(string $doc): string
    {
        $f = resource_path("legal/$doc.md");
        return is_file($f) ? file_get_contents($f) : '';
    }

    public static function texto(string $doc, ?Configuracion $cfg = null): string
    {
        $cfg ??= Configuracion::actual();
        $campo = self::DOCUMENTOS[$doc][1] ?? null;
        $t = $campo ? $cfg->{$campo} : null;
        return filled($t) ? $t : self::base($doc);
    }

    public static function html(string $doc, ?Configuracion $cfg = null): string
    {
        $cfg ??= Configuracion::actual();
        $md = strtr(self::texto($doc, $cfg), self::variables($cfg));
        return Str::markdown($md, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }

    public static function variables(Configuracion $cfg): array
    {
        $e = fn ($v) => str_replace(['*', '_', '[', ']', '<', '>'], ['\\*', '\\_', '\\[', '\\]', '&lt;', '&gt;'], (string) $v);
        $pais = Pais::de($cfg->legal_pais ?: 'CR')['nombre'];
        return [
            '{{marca}}'               => $e($cfg->nombre_negocio ?: 'Mi CV'),
            '{{responsable}}'         => $e($cfg->legal_responsable ?: ($cfg->nombre_negocio ?: 'el titular de la plataforma')),
            '{{identificacion_texto}}'=> $cfg->legal_identificacion ? ', identificación ' . $e($cfg->legal_identificacion) : '',
            '{{direccion}}'           => $e($cfg->marca_direccion ?: $pais),
            '{{correo}}'              => $e($cfg->marca_correo ?: ($cfg->smtp_desde_correo ?: 'el correo de contacto del sitio')),
            '{{telefono}}'            => $e($cfg->marca_telefono ?: '—'),
            '{{pais}}'                => $pais === 'Otro país' ? 'el país del responsable' : 'la República de ' . $pais,
            '{{version}}'             => $e($cfg->legal_version ?: '1.0'),
            '{{fecha}}'               => ($cfg->legal_actualizado_en ?? $cfg->created_at ?? now())->format('d/m/Y'),
            '{{url_privacidad}}'      => route('legal.ver', 'privacidad'),
            '{{url_terminos}}'        => route('legal.ver', 'terminos'),
            '{{url_contrato}}'        => route('legal.ver', 'contrato'),
            '{{url_cookies}}'         => route('legal.ver', 'cookies'),
            '{{url_eliminar}}'        => route('eliminar.mostrar'),
        ];
    }
}
