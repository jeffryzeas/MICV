<?php

namespace App\Models;

use App\Support\TarjetaDisenos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tarjeta de presentación (aparte del CV): una por persona. */
class Tarjeta extends Model
{
    protected $table = 'tarjetas';

    protected $fillable = ['cliente_id', 'diseno', 'color', 'imagen', 'logo', 'qr', 'datos'];

    protected function casts(): array
    {
        return ['datos' => 'array'];
    }

    public const CAMPOS = ['nombre', 'cargo', 'telefono', 'correo', 'direccion', 'web', 'redes', 'frase'];

    /** Largo máximo de cada dato (mismo número en el formulario y en la validación). */
    public const LIMITES = ['nombre' => 60, 'cargo' => 80, 'telefono' => 30, 'correo' => 100, 'direccion' => 100, 'web' => 200, 'redes' => 60, 'frase' => 90];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /** La tarjeta de la persona; si no existe, una nueva con sus datos del CV. */
    public static function de(Cliente $c): self
    {
        return static::firstWhere('cliente_id', $c->id) ?? new static([
            'cliente_id' => $c->id,
            'diseno'     => TarjetaDisenos::sugerido($c->profesion),
            'imagen'     => $c->tieneFoto() ? 'foto' : 'ninguna',
            'qr'         => $c->telefono ? 'whatsapp' : ($c->correo ? 'contacto' : 'ninguno'),
            'datos'      => self::datosDelCv($c),
        ]);
    }

    public static function datosDelCv(Cliente $c): array
    {
        $tel = $c->telefono ? trim(($c->telefono_codigo_pais ? $c->telefono_codigo_pais . ' ' : '') . $c->telefono) : '';
        $d = [
            'nombre'    => trim($c->primer_nombre . ' ' . $c->primer_apellido),
            'cargo'     => (string) $c->profesion,
            'telefono'  => $tel,
            'correo'    => (string) $c->correo,
            'direccion' => trim(implode(', ', array_filter([$c->municipio_nacimiento, $c->departamento_nacimiento]))) ?: '',
            'web'       => (string) ($c->sitio_web ?: $c->linkedin),
            'redes'     => '',
            'frase'     => '',
        ];
        // Lo que viene del CV puede ser más largo de lo que cabe: se recorta para que nunca falle.
        foreach ($d as $k => $v) {
            $d[$k] = mb_substr($v, 0, self::LIMITES[$k]);
        }
        if (!filter_var($d['correo'], FILTER_VALIDATE_EMAIL)) {
            $d['correo'] = '';
        }
        return $d;
    }

    public function d(string $k): string
    {
        return trim((string) (($this->datos ?? [])[$k] ?? ''));
    }

    /** Solo los dígitos del teléfono (para wa.me). */
    public function telefonoWhatsapp(): string
    {
        return preg_replace('/\D/', '', $this->d('telefono'));
    }

    /** Lo que guarda el código QR. */
    public function contenidoQr(): ?string
    {
        return match ($this->qr) {
            'whatsapp' => $this->telefonoWhatsapp() ? 'https://wa.me/' . $this->telefonoWhatsapp() . '?text=' . rawurlencode('Hola, vi su tarjeta.') : null,
            'web'      => $this->d('web') ? (preg_match('#^https?://#i', $this->d('web')) ? $this->d('web') : 'https://' . $this->d('web')) : null,
            'contacto' => $this->vcard(),
            default    => null,
        };
    }

    public function vcard(): string
    {
        $esc = fn ($v) => str_replace([',', ';', "\n"], ['\,', '\;', ' '], $v);
        $partes = preg_split('/\s+/', $this->d('nombre'), 2);
        $l = ['BEGIN:VCARD', 'VERSION:3.0', 'N:' . $esc($partes[1] ?? '') . ';' . $esc($partes[0] ?? '') . ';;;', 'FN:' . $esc($this->d('nombre'))];
        if ($this->d('cargo')) $l[] = 'TITLE:' . $esc($this->d('cargo'));
        if ($this->d('telefono')) $l[] = 'TEL;TYPE=CELL:' . preg_replace('/[^\d+]/', '', $this->d('telefono'));
        if ($this->d('correo')) $l[] = 'EMAIL:' . $this->d('correo');
        if ($this->d('web')) $l[] = 'URL:' . $this->d('web');
        if ($this->d('direccion')) $l[] = 'ADR;TYPE=WORK:;;' . $esc($this->d('direccion')) . ';;;;';
        $l[] = 'END:VCARD';
        return implode("\n", $l);
    }

    public function disenoDatos(): array
    {
        return TarjetaDisenos::CATALOGO[$this->diseno] ?? TarjetaDisenos::CATALOGO['corporativo'];
    }
}
