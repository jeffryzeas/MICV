<?php

namespace App\Models\Pos;

use App\Models\Cyber;
use App\Support\PosPaises;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Configuración del punto de venta de cada Cyber: moneda, impuesto, datos del negocio y comprobante. */
class PosConfig extends Model
{
    protected $table = 'pos_config';

    protected $fillable = [
        'cyber_id', 'pais', 'moneda', 'simbolo', 'decimales', 'locale', 'impuesto_nombre', 'impuesto_tasa', 'precios_con_impuesto',
        'nombre_comercial', 'razon_social', 'id_fiscal_tipo', 'id_fiscal', 'telefono', 'correo', 'direccion',
        'pie_tiquete', 'aviso_fiscal', 'ancho_tiquete', 'prefijo', 'siguiente_numero', 'metodos',
    ];

    protected function casts(): array
    {
        return ['decimales' => 'integer', 'impuesto_tasa' => 'decimal:2', 'precios_con_impuesto' => 'boolean', 'metodos' => 'array', 'siguiente_numero' => 'integer', 'ancho_tiquete' => 'integer'];
    }

    public function cyber(): BelongsTo
    {
        return $this->belongsTo(Cyber::class);
    }

    /** La configuración del Cyber; la primera vez se arma con los valores de su país. */
    public static function para(Cyber $cyber): self
    {
        $cfg = static::firstWhere('cyber_id', $cyber->id);
        if ($cfg) {
            return $cfg;
        }
        $pais = $cyber->pais ?: \App\Models\Cliente::query()->whereHas('cybers', fn ($q) => $q->where('cybers.id', $cyber->id))
            ->selectRaw('pais, count(*) as n')->groupBy('pais')->orderByDesc('n')->value('pais') ?: 'NI';
        $d = PosPaises::de($pais);
        return static::create($d + [
            'cyber_id' => $cyber->id,
            'nombre_comercial' => $cyber->nombre,
            'telefono' => $cyber->telefono,
            'correo' => $cyber->correo,
            'direccion' => $cyber->direccion,
            'pie_tiquete' => '¡Gracias por su compra!',
            'aviso_fiscal' => PosPaises::AVISO_FISCAL,
            'precios_con_impuesto' => true,
            'ancho_tiquete' => 80,
            'prefijo' => '',
            'siguiente_numero' => 1,
        ])->fresh();
    }

    /** Monto con el símbolo y los separadores del país. */
    public function dinero($monto): string
    {
        $monto = (float) $monto;
        $dec = (int) $this->decimales;
        if (class_exists(\NumberFormatter::class)) {
            $f = new \NumberFormatter($this->locale ?: 'es', \NumberFormatter::DECIMAL);
            $f->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $dec);
            $f->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $dec);
            $txt = $f->format(abs($monto));
        } else {
            $txt = number_format(abs($monto), $dec, '.', ',');
        }
        return ($monto < 0 ? '-' : '') . $this->simbolo . $txt;
    }

    public function metodosActivos(): array
    {
        return array_values(array_filter($this->metodos ?: PosPaises::metodos(), fn ($m) => !empty($m['activo'])));
    }

    public function nombreMetodo(string $clave): string
    {
        foreach ($this->metodos ?: PosPaises::metodos() as $m) {
            if ($m['clave'] === $clave) {
                return $m['nombre'];
            }
        }
        return ucfirst($clave);
    }

    /** Siguiente consecutivo (llamar dentro de una transacción). */
    public function tomarNumero(): array
    {
        $fila = static::whereKey($this->id)->lockForUpdate()->first();
        $ultimo = (int) PosVenta::where('cyber_id', $this->cyber_id)->max('consecutivo');
        $n = max((int) $fila->siguiente_numero, $ultimo + 1);
        $fila->update(['siguiente_numero' => $n + 1]);
        return [$n, ($this->prefijo ?? '') . str_pad((string) $n, 6, '0', STR_PAD_LEFT)];
    }

    /** Lo que necesita el JavaScript de la caja para mostrar montos igual que el servidor. */
    public function paraJs(): array
    {
        return [
            'simbolo' => $this->simbolo, 'decimales' => (int) $this->decimales, 'locale' => $this->locale ?: 'es',
            'tasa' => (float) $this->impuesto_tasa, 'impuesto' => $this->impuesto_nombre, 'incluido' => (bool) $this->precios_con_impuesto,
            'metodos' => $this->metodosActivos(), 'billetes' => PosPaises::denominaciones($this->moneda),
        ];
    }
}
