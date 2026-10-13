<?php

namespace App\Support;

/** Nacionalidades para elegir de una lista (masculino / femenino). */
class Nacionalidades
{
    public const LISTA = [
        'NI' => ['Nicaragua', 'Nicaragüense', 'Nicaragüense'],
        'CR' => ['Costa Rica', 'Costarricense', 'Costarricense'],
        'HN' => ['Honduras', 'Hondureño', 'Hondureña'],
        'SV' => ['El Salvador', 'Salvadoreño', 'Salvadoreña'],
        'GT' => ['Guatemala', 'Guatemalteco', 'Guatemalteca'],
        'PA' => ['Panamá', 'Panameño', 'Panameña'],
        'BZ' => ['Belice', 'Beliceño', 'Beliceña'],
        'MX' => ['México', 'Mexicano', 'Mexicana'],
        'CU' => ['Cuba', 'Cubano', 'Cubana'],
        'DO' => ['República Dominicana', 'Dominicano', 'Dominicana'],
        'PR' => ['Puerto Rico', 'Puertorriqueño', 'Puertorriqueña'],
        'HT' => ['Haití', 'Haitiano', 'Haitiana'],
        'CO' => ['Colombia', 'Colombiano', 'Colombiana'],
        'VE' => ['Venezuela', 'Venezolano', 'Venezolana'],
        'EC' => ['Ecuador', 'Ecuatoriano', 'Ecuatoriana'],
        'PE' => ['Perú', 'Peruano', 'Peruana'],
        'BO' => ['Bolivia', 'Boliviano', 'Boliviana'],
        'CL' => ['Chile', 'Chileno', 'Chilena'],
        'AR' => ['Argentina', 'Argentino', 'Argentina'],
        'UY' => ['Uruguay', 'Uruguayo', 'Uruguaya'],
        'PY' => ['Paraguay', 'Paraguayo', 'Paraguaya'],
        'BR' => ['Brasil', 'Brasileño', 'Brasileña'],
        'US' => ['Estados Unidos', 'Estadounidense', 'Estadounidense'],
        'CA' => ['Canadá', 'Canadiense', 'Canadiense'],
        'ES' => ['España', 'Español', 'Española'],
        'IT' => ['Italia', 'Italiano', 'Italiana'],
        'FR' => ['Francia', 'Francés', 'Francesa'],
        'DE' => ['Alemania', 'Alemán', 'Alemana'],
        'PT' => ['Portugal', 'Portugués', 'Portuguesa'],
        'GB' => ['Reino Unido', 'Británico', 'Británica'],
        'CN' => ['China', 'Chino', 'China'],
        'TW' => ['Taiwán', 'Taiwanés', 'Taiwanesa'],
        'JP' => ['Japón', 'Japonés', 'Japonesa'],
        'KR' => ['Corea del Sur', 'Surcoreano', 'Surcoreana'],
        'IN' => ['India', 'Indio', 'India'],
    ];

    /** Gentilicio en inglés (para el CV en inglés). */
    public const EN = [
        'NI' => 'Nicaraguan', 'CR' => 'Costa Rican', 'HN' => 'Honduran', 'SV' => 'Salvadoran', 'GT' => 'Guatemalan',
        'PA' => 'Panamanian', 'BZ' => 'Belizean', 'MX' => 'Mexican', 'CU' => 'Cuban', 'DO' => 'Dominican',
        'PR' => 'Puerto Rican', 'HT' => 'Haitian', 'CO' => 'Colombian', 'VE' => 'Venezuelan', 'EC' => 'Ecuadorian',
        'PE' => 'Peruvian', 'BO' => 'Bolivian', 'CL' => 'Chilean', 'AR' => 'Argentine', 'UY' => 'Uruguayan',
        'PY' => 'Paraguayan', 'BR' => 'Brazilian', 'US' => 'American', 'CA' => 'Canadian', 'ES' => 'Spanish',
        'IT' => 'Italian', 'FR' => 'French', 'DE' => 'German', 'PT' => 'Portuguese', 'GB' => 'British',
        'CN' => 'Chinese', 'TW' => 'Taiwanese', 'JP' => 'Japanese', 'KR' => 'South Korean', 'IN' => 'Indian',
    ];

    /** Para el JS: [{c, m, f}] */
    public static function paraJs(): array
    {
        $r = [];
        foreach (self::LISTA as $c => [$pais, $m, $f]) {
            $r[] = ['c' => $c, 'p' => $pais, 'm' => $m, 'f' => $f];
        }
        return $r;
    }

    /** ¿El texto es una nacionalidad de la lista? (en cualquier género) */
    public static function esDeLista(?string $texto): bool
    {
        if (blank($texto)) {
            return true;
        }
        $t = mb_strtolower(trim($texto));
        foreach (self::LISTA as [, $m, $f]) {
            if ($t === mb_strtolower($m) || $t === mb_strtolower($f)) {
                return true;
            }
        }
        return false;
    }

    /** Gentilicio correcto según el sexo, a partir del código de país. */
    public static function de(string $codigo, ?string $sexo = null): ?string
    {
        $n = self::LISTA[$codigo] ?? null;
        return $n ? ($sexo === 'F' ? $n[2] : $n[1]) : null;
    }
}
