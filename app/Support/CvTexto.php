<?php

namespace App\Support;

/**
 * Textos fijos del CV en inglés. Se busca por el texto en español: si una
 * frase no está aquí, sale en español (nunca queda vacía).
 * Lo que escribe la persona (cargos, empresas, descripciones) no se traduce;
 * para el perfil y el oficio hay campos propios en inglés.
 */
class CvTexto
{
    public const EN = [
        // Secciones
        'Contacto' => 'Contact', 'Datos personales' => 'Personal information', 'Habilidades' => 'Skills',
        'Perfil profesional' => 'Professional profile', 'Perfil' => 'Profile', 'Sobre mí' => 'About me',
        'Experiencia laboral' => 'Work experience', 'Experiencia' => 'Experience',
        'Formación académica' => 'Education', 'Formación' => 'Education', 'Información académica' => 'Education',
        'Referencias' => 'References', 'Referencias personales' => 'References', 'Datos familiares' => 'Family information',
        'Familia' => 'Family', 'Idiomas' => 'Languages',
        // Etiquetas
        'Nombre y apellidos' => 'Full name', 'Fecha de nacimiento' => 'Date of birth', 'Nacimiento' => 'Date of birth',
        'Edad' => 'Age', 'Sexo' => 'Gender', 'Nacionalidad' => 'Nationality', 'Lugar de nacimiento' => 'Place of birth',
        'Dirección' => 'Address', 'Celular' => 'Mobile', 'Número de hijos' => 'Children', 'Hijos' => 'Children',
        'Estado civil' => 'Marital status', 'Profesión u oficio' => 'Occupation', 'Correo electrónico' => 'Email',
        'Nombre del padre' => "Father's name", 'Nombre de la madre' => "Mother's name",
        'LinkedIn' => 'LinkedIn', 'Sitio web' => 'Website', 'GitHub' => 'GitHub', 'Documento' => 'ID',
        'Tel.' => 'Phone', 'Actualidad' => 'Present', 'años' => 'years old',
        // Documentos
        'Cédula' => 'National ID', 'Pasaporte' => 'Passport', 'Cédula de residencia' => 'Residence card',
        'Código de persona' => 'Personal code', 'Cédula de extranjería' => 'Foreigner ID', 'Residencia' => 'Residence card',
        'Licencia / State ID' => 'Driver license / State ID', 'Green Card' => 'Green Card',
        'Cédula de identidad' => 'National ID', 'Carné de extranjería' => 'Foreigner ID', 'RUT' => 'RUT (national ID)',
        // Valores
        'Masculino' => 'Male', 'Femenino' => 'Female', 'Otro' => 'Other',
        'Soltero' => 'Single', 'Soltera' => 'Single', 'Soltero(a)' => 'Single',
        'Casado' => 'Married', 'Casada' => 'Married', 'Casado(a)' => 'Married',
        'Unión de hecho' => 'Common-law partner', 'Unión libre' => 'Common-law partner',
        'Divorciado' => 'Divorced', 'Divorciada' => 'Divorced', 'Divorciado(a)' => 'Divorced',
        'Viudo' => 'Widowed', 'Viuda' => 'Widowed', 'Viudo(a)' => 'Widowed',
        'Separado' => 'Separated', 'Separada' => 'Separated', 'Separado(a)' => 'Separated',
        // Niveles de estudio
        'Universidad' => 'University', 'Técnico' => 'Technical', 'Secundaria' => 'High school',
        'Primaria' => 'Primary school', 'Preescolar' => 'Preschool', 'Curso' => 'Course',
        // Países
        'Nicaragua' => 'Nicaragua', 'Costa Rica' => 'Costa Rica', 'Honduras' => 'Honduras', 'El Salvador' => 'El Salvador',
        'Guatemala' => 'Guatemala', 'México' => 'Mexico', 'Colombia' => 'Colombia', 'España' => 'Spain',
        'Estados Unidos' => 'United States', 'Panamá' => 'Panama', 'República Dominicana' => 'Dominican Republic',
        'Cuba' => 'Cuba', 'Venezuela' => 'Venezuela', 'Ecuador' => 'Ecuador', 'Perú' => 'Peru', 'Bolivia' => 'Bolivia',
        'Chile' => 'Chile', 'Argentina' => 'Argentina', 'Uruguay' => 'Uruguay', 'Paraguay' => 'Paraguay',
    ];

    /** Seguro social por país en inglés. */
    public const SEGURO_EN = [
        'NI' => 'INSS number', 'CR' => 'CCSS insurance number', 'HN' => 'IHSS number', 'SV' => 'ISSS number',
        'GT' => 'IGSS number', 'MX' => 'Social Security number (IMSS)', 'CO' => 'Health insurer (EPS)',
        'ES' => 'Social Security number', 'OT' => 'Social security number', 'PA' => 'Social Security number (CSS)',
        'DO' => 'Social Security number (NSS)', 'BO' => 'Health insurance number', 'CL' => 'Pension fund (AFP) and health plan',
        'AR' => 'CUIL', 'PY' => 'IPS number',
    ];

    public static function t(string $texto, string $lang): string
    {
        if ($lang !== 'en') {
            return $texto;
        }
        return self::EN[$texto] ?? self::EN[trim($texto)] ?? $texto;
    }

    /** Nacionalidad en inglés usando la lista de nacionalidades (si no está, se deja igual). */
    public static function nacionalidad(?string $texto, string $lang): ?string
    {
        if ($lang !== 'en' || blank($texto)) {
            return $texto;
        }
        $t = mb_strtolower(trim($texto));
        foreach (Nacionalidades::LISTA as $cod => [, $m, $f]) {
            if ($t === mb_strtolower($m) || $t === mb_strtolower($f)) {
                return Nacionalidades::EN[$cod] ?? $texto;
            }
        }
        return $texto;
    }
}
