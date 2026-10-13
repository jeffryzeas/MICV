<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteIdioma extends Model
{
    protected $table = 'cliente_idiomas';

    /** nivel => [español, inglés, puntos de 1 a 5 para las barras del CV] */
    public const NIVELES = [
        'nativo'     => ['Nativo', 'Native', 5],
        'avanzado'   => ['Avanzado', 'Advanced', 4],
        'intermedio' => ['Intermedio', 'Intermediate', 3],
        'basico'     => ['Básico', 'Basic', 2],
    ];

    /** Idiomas comunes (se pueden escribir otros). español => inglés */
    public const COMUNES = [
        'Español' => 'Spanish', 'Inglés' => 'English', 'Francés' => 'French', 'Portugués' => 'Portuguese',
        'Italiano' => 'Italian', 'Alemán' => 'German', 'Chino mandarín' => 'Mandarin Chinese', 'Japonés' => 'Japanese',
        'Coreano' => 'Korean', 'Árabe' => 'Arabic', 'Ruso' => 'Russian', 'Neerlandés' => 'Dutch',
        'Miskito' => 'Miskito', 'Garífuna' => 'Garifuna', 'Náhuatl' => 'Nahuatl', 'Maya' => 'Mayan',
        'Quechua' => 'Quechua', 'Lengua de señas' => 'Sign language', 'Criollo inglés' => 'English Creole',
    ];

    protected $fillable = ['cliente_id', 'idioma', 'nivel', 'orden'];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function nivelTexto(string $lang = 'es'): string
    {
        $n = self::NIVELES[$this->nivel] ?? self::NIVELES['intermedio'];
        return $lang === 'en' ? $n[1] : $n[0];
    }

    public function puntos(): int
    {
        return (self::NIVELES[$this->nivel] ?? self::NIVELES['intermedio'])[2];
    }

    public function nombre(string $lang = 'es'): string
    {
        if ($lang !== 'en') {
            return $this->idioma;
        }
        foreach (self::COMUNES as $es => $en) {
            if (mb_strtolower($es) === mb_strtolower(trim($this->idioma))) {
                return $en;
            }
        }
        return $this->idioma;
    }
}
