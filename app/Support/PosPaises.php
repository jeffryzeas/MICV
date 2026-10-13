<?php

namespace App\Support;

/**
 * Valores iniciales del punto de venta según el país: moneda, impuesto,
 * identificación fiscal, billetes/monedas para el arqueo y nombre del pago
 * por transferencia. Todo se puede cambiar en "Configuración del punto de venta":
 * las tasas cambian con las leyes, verifíquelas con su contador.
 */
class PosPaises
{
    /** país => [moneda, símbolo, decimales, impuesto, tasa %, id fiscal, locale, transferencia] */
    public const PAISES = [
        'NI' => ['NIO', 'C$', 2, 'IVA', 15, 'RUC', 'es-NI', 'Transferencia'],
        'CR' => ['CRC', '₡', 0, 'IVA', 13, 'Cédula jurídica / física', 'es-CR', 'SINPE Móvil'],
        'HN' => ['HNL', 'L', 2, 'ISV', 15, 'RTN', 'es-HN', 'Transferencia'],
        'SV' => ['USD', '$', 2, 'IVA', 13, 'NIT / NRC', 'es-SV', 'Transferencia'],
        'GT' => ['GTQ', 'Q', 2, 'IVA', 12, 'NIT', 'es-GT', 'Transferencia'],
        'MX' => ['MXN', '$', 2, 'IVA', 16, 'RFC', 'es-MX', 'Transferencia SPEI'],
        'CO' => ['COP', '$', 0, 'IVA', 19, 'NIT', 'es-CO', 'Nequi / transferencia'],
        'PA' => ['PAB', 'B/.', 2, 'ITBMS', 7, 'RUC', 'es-PA', 'Yappy / transferencia'],
        'DO' => ['DOP', 'RD$', 2, 'ITBIS', 18, 'RNC', 'es-DO', 'Transferencia'],
        'CU' => ['CUP', '$', 2, 'Impuesto', 0, 'NIT', 'es-CU', 'Transfermóvil'],
        'VE' => ['VES', 'Bs.', 2, 'IVA', 16, 'RIF', 'es-VE', 'Pago móvil'],
        'EC' => ['USD', '$', 2, 'IVA', 15, 'RUC', 'es-EC', 'Transferencia'],
        'PE' => ['PEN', 'S/', 2, 'IGV', 18, 'RUC', 'es-PE', 'Yape / Plin'],
        'BO' => ['BOB', 'Bs', 2, 'IVA', 13, 'NIT', 'es-BO', 'QR / transferencia'],
        'CL' => ['CLP', '$', 0, 'IVA', 19, 'RUT', 'es-CL', 'Transferencia'],
        'AR' => ['ARS', '$', 2, 'IVA', 21, 'CUIT', 'es-AR', 'Transferencia / QR'],
        'UY' => ['UYU', '$U', 2, 'IVA', 22, 'RUT', 'es-UY', 'Transferencia'],
        'PY' => ['PYG', '₲', 0, 'IVA', 10, 'RUC', 'es-PY', 'Transferencia'],
        'ES' => ['EUR', '€', 2, 'IVA', 21, 'NIF / CIF', 'es-ES', 'Bizum'],
        'US' => ['USD', '$', 2, 'Sales tax', 0, 'EIN', 'en-US', 'Zelle'],
    ];

    /** Billetes y monedas por moneda (para el arqueo y los botones de pago rápido). */
    public const DENOMINACIONES = [
        'NIO' => [1000, 500, 200, 100, 50, 20, 10, 5, 1],
        'CRC' => [20000, 10000, 5000, 2000, 1000, 500, 100, 50, 25, 10, 5],
        'HNL' => [500, 200, 100, 50, 20, 10, 5, 2, 1],
        'USD' => [100, 50, 20, 10, 5, 1, 0.25, 0.10, 0.05, 0.01],
        'PAB' => [100, 50, 20, 10, 5, 1, 0.50, 0.25, 0.10, 0.05, 0.01],
        'GTQ' => [200, 100, 50, 20, 10, 5, 1, 0.50, 0.25],
        'MXN' => [1000, 500, 200, 100, 50, 20, 10, 5, 2, 1, 0.50],
        'COP' => [100000, 50000, 20000, 10000, 5000, 2000, 1000, 500, 200, 100],
        'DOP' => [2000, 1000, 500, 200, 100, 50, 25, 10, 5, 1],
        'CUP' => [1000, 500, 200, 100, 50, 20, 10, 5, 3, 1],
        'VES' => [200, 100, 50, 20, 10, 5, 1],
        'PEN' => [200, 100, 50, 20, 10, 5, 2, 1, 0.50, 0.20, 0.10],
        'BOB' => [200, 100, 50, 20, 10, 5, 2, 1, 0.50],
        'CLP' => [20000, 10000, 5000, 2000, 1000, 500, 100, 50, 10],
        'ARS' => [20000, 10000, 2000, 1000, 500, 200, 100],
        'UYU' => [2000, 1000, 500, 200, 100, 50, 20, 10, 5, 2, 1],
        'PYG' => [100000, 50000, 20000, 10000, 5000, 2000, 1000, 500, 100],
        'EUR' => [500, 200, 100, 50, 20, 10, 5, 2, 1, 0.50, 0.20, 0.10, 0.05],
    ];

    public const AVISO_FISCAL = 'Comprobante interno de venta. No sustituye la factura electrónica.';

    /** Servicios típicos de un Cyber para empezar (el precio se pone después). */
    public const SERVICIOS_SUGERIDOS = [
        ['CV impreso', 'Servicios de CV'], ['Carta impresa', 'Servicios de CV'], ['Fotos carné (hoja)', 'Servicios de CV'],
        ['Tarjetas de presentación (hoja de 10)', 'Servicios de CV'], ['Impresión blanco y negro (página)', 'Impresiones'],
        ['Impresión a color (página)', 'Impresiones'], ['Fotocopia (página)', 'Impresiones'], ['Escaneo (página)', 'Impresiones'],
        ['Enmicado / plastificado', 'Impresiones'], ['Uso de computadora (hora)', 'Internet'], ['Trámite en línea', 'Internet'],
    ];

    public static function de(?string $pais): array
    {
        [$moneda, $simbolo, $dec, $imp, $tasa, $idf, $locale, $transf] = self::PAISES[$pais] ?? ['USD', '$', 2, 'Impuesto', 0, 'ID fiscal', 'es', 'Transferencia'];
        return [
            'pais' => $pais, 'moneda' => $moneda, 'simbolo' => $simbolo, 'decimales' => $dec, 'impuesto_nombre' => $imp,
            'impuesto_tasa' => $tasa, 'id_fiscal_tipo' => $idf, 'locale' => $locale,
            'metodos' => self::metodos($transf),
        ];
    }

    public static function metodos(string $transferencia = 'Transferencia'): array
    {
        return [
            ['clave' => 'efectivo', 'nombre' => 'Efectivo', 'activo' => true],
            ['clave' => 'tarjeta', 'nombre' => 'Tarjeta', 'activo' => true],
            ['clave' => 'transferencia', 'nombre' => $transferencia, 'activo' => true],
            ['clave' => 'otro', 'nombre' => 'Otro', 'activo' => false],
        ];
    }

    public static function denominaciones(string $moneda): array
    {
        return self::DENOMINACIONES[$moneda] ?? [100, 50, 20, 10, 5, 1];
    }
}
