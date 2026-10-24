<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Una sola fila con la configuración del negocio y de los CV. */
class Configuracion extends Model
{
    protected $table = 'configuracion';

    protected $fillable = [
        'nombre_negocio', 'logo', 'escudo', 'color_primario', 'color_secundario', 'cv_config',
        'smtp_host', 'smtp_puerto', 'smtp_usuario', 'smtp_password', 'smtp_encriptacion',
        'smtp_desde_correo', 'smtp_desde_nombre', 'smtp_activo',
        'recaptcha_site_key', 'recaptcha_secret_key', 'recaptcha_umbral', 'recaptcha_activo',
        'registro_abierto',
        'eslogan', 'marca_telefono', 'marca_whatsapp', 'marca_correo', 'marca_direccion', 'marca_sitio_web',
        'marca_facebook', 'marca_instagram', 'marca_tiktok', 'pie_pagina',
        'legal_responsable', 'legal_identificacion', 'legal_pais', 'legal_terminos', 'legal_privacidad',
        'legal_contrato', 'legal_cookies', 'legal_version', 'legal_actualizado_en', 'android_paquete', 'android_sha256',
    ];

    protected $hidden = ['smtp_password', 'recaptcha_secret_key'];

    protected function casts(): array
    {
        return [
            'cv_config'            => 'array',
            'smtp_activo'          => 'boolean',
            'recaptcha_activo'     => 'boolean',
            'recaptcha_umbral'     => 'float',
            'registro_abierto'     => 'boolean',
            'legal_actualizado_en' => 'datetime',
            // Se guardan cifradas con la APP_KEY: quien lea la tabla no las ve.
            'smtp_password'        => 'encrypted',
            'recaptcha_secret_key' => 'encrypted',
        ];
    }

    // ── Correo (Gmail / SMTP)
    public function correoListo(): bool
    {
        return $this->smtp_activo && filled($this->smtp_host) && filled($this->smtp_desde_correo);
    }

    // ── reCAPTCHA v3
    public function recaptchaListo(): bool
    {
        return $this->recaptcha_activo && filled($this->recaptcha_site_key) && filled($this->secretoRecaptcha());
    }

    /** No revienta si la APP_KEY cambió después de guardar la llave. */
    public function secretoRecaptcha(): ?string
    {
        try {
            return $this->recaptcha_secret_key;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function passwordSmtp(): ?string
    {
        try {
            return $this->smtp_password;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Valores por defecto del CV (todas las medidas en pt). */
    public const CV_DEFECTOS = [
        // ── Diseño Clásico
        'fuente_titulo_tam'     => 44,
        'fuente_seccion_tam'    => 16,
        'fuente_texto_tam'      => 11.5,
        'fuente_vineta_tam'     => 11.5,
        'etiqueta_ancho_pct'    => 36,
        'nombre_formato'        => 'mayusculas', // mayusculas | capital | tal_cual
        'color_titulo'          => '#0B3C74',
        'color_seccion'         => '#000000',
        'color_texto'           => '#000000',
        'seccion_negrita'       => true,
        'seccion_subrayado'     => true,
        'etiqueta_negrita'      => true,
        'foto_ancho'            => 96,
        'foto_alto'             => 122,
        'foto_borde'            => 2,
        'mostrar_escudo'        => true,
        'escudo_tam'            => 46,
        'marca_agua_activa'     => true,
        'marca_agua_ancho_pct'  => 60,
        'marca_agua_opacidad'   => 8,
        'marco_activo'          => true,
        'marco_margen'          => 14,
        'marco_externo_grosor'  => 3.5,
        'marco_externo_color'   => '#0B3C74',
        'marco_separacion'      => 3,
        'marco_interno_grosor'  => 1.2,
        'marco_interno_color'   => '#C9A227',

        // ── Diseño Pro (estilo Canva)
        'pro_paleta'            => 'cyber',

        // ── General
        'diseno_defecto'        => 'esencial',  // Los diseños sencillos se presentan primero
    ];

    /** Paletas del diseño Pro: [barra lateral, acento, fondo suave del acento]. */
    public const PALETAS_PRO = [
        'cyber'     => ['nombre' => 'Colores de la marca', 'lateral' => '#0B3C74', 'acento' => '#C9971F', 'suave' => '#F6EEDC'],
        'marino'    => ['nombre' => 'Azul marino',  'lateral' => '#14284B', 'acento' => '#E0A526', 'suave' => '#F6EEDC'],
        'esmeralda' => ['nombre' => 'Esmeralda',    'lateral' => '#0F3D3E', 'acento' => '#2BB58F', 'suave' => '#E2F5EF'],
        'vino'      => ['nombre' => 'Vino',         'lateral' => '#4A1426', 'acento' => '#D98E73', 'suave' => '#F8E9E3'],
        'carbon'    => ['nombre' => 'Carbón',       'lateral' => '#23272F', 'acento' => '#4F8EF7', 'suave' => '#E5EEFE'],
        'lavanda'   => ['nombre' => 'Lavanda',      'lateral' => '#3B2F63', 'acento' => '#B79CED', 'suave' => '#EFE9FB'],
        'terracota' => ['nombre' => 'Terracota',    'lateral' => '#5B3A29', 'acento' => '#E07A47', 'suave' => '#FBEADF'],
    ];

    public static function actual(): self
    {
        return static::query()->firstOrCreate([], ['nombre_negocio' => config('app.name', 'Mi CV'), 'registro_abierto' => true]);
    }

    /** No revienta si la BD todavía no está lista (pantallas públicas). */
    public static function actualSegura(): ?self
    {
        try {
            return static::actual();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function cv(): array
    {
        $g = $this->cv_config;
        if (is_string($g)) {
            $g = json_decode($g, true);
        }
        return array_merge(self::CV_DEFECTOS, is_array($g) ? $g : []);
    }

    public static function paleta(?string $clave): array
    {
        return self::PALETAS_PRO[$clave] ?? self::PALETAS_PRO['marino'];
    }
}
