<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $table = 'clientes';

    /** Todos los tipos de documento (los de cada país están en App\Support\Pais). */
    public const TIPOS_DOCUMENTO = [
        'cedula'             => 'Cédula nicaragüense',
        'pasaporte'          => 'Pasaporte',
        'residencia'         => 'Cédula de residencia (NI)',
        'partida_nacimiento' => 'Código de persona (NI)',
        'cedula_cr'          => 'Cédula de identidad (CR)',
        'dimex'              => 'DIMEX (CR)',
        'otro'               => 'Documento de identidad',
    ];

    protected $fillable = [
        'user_id', 'pais', 'tipo_documento', 'numero_documento', 'inss_nss',
        'primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido',
        'fecha_nacimiento', 'edad_calculada', 'municipio_nacimiento', 'departamento_nacimiento',
        'sexo', 'nacionalidad', 'numero_hijos', 'estado_civil', 'profesion', 'perfil_profesional',
        'correo', 'telefono_codigo_pais', 'telefono', 'telefono_red',
        'telefono_secundario', 'telefono_secundario_red', 'direccion',
        'nombre_padre', 'nombre_madre', 'notas', 'foto', 'activo', 'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'numero_hijos'     => 'integer',
            'edad_calculada'   => 'integer',
            'activo'           => 'boolean',
        ];
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Datos del país de la persona (documentos, INSS/CCSS, etiquetas…). */
    public function datosPais(): array
    {
        return \App\Support\Pais::de($this->pais ?: 'NI');
    }

    public function cybers(): BelongsToMany
    {
        return $this->belongsToMany(Cyber::class, 'cliente_cyber')->withPivot('registrado_por')->withTimestamps();
    }

    /** El admin ve todos; un usuario de cyber solo los clientes de su cyber. */
    public function scopeVisiblesPara(Builder $q, ?User $u): Builder
    {
        if ($u && $u->esAdmin()) {
            return $q;
        }
        if ($u && $u->esCliente()) {
            return $q->where('user_id', $u->id);
        }
        $cyberId = $u?->cyber_id ?? 0;
        return $q->whereHas('cybers', fn ($w) => $w->where('cybers.id', $cyberId));
    }

    public function esVisiblePara(?User $u): bool
    {
        if (!$u) {
            return false;
        }
        if ($u->esAdmin()) {
            return true;
        }
        if ($u->esCliente()) {
            return (int) $this->user_id === (int) $u->id;
        }
        return $u->cyber_id && $this->cybers()->where('cybers.id', $u->cyber_id)->exists();
    }

    public function estudios(): HasMany
    {
        return $this->hasMany(ClienteEstudio::class)->orderBy('orden');
    }

    public function experiencias(): HasMany
    {
        return $this->hasMany(ClienteExperiencia::class)->orderBy('orden');
    }

    public function habilidades(): HasMany
    {
        return $this->hasMany(ClienteHabilidad::class)->orderBy('orden');
    }

    public function referencias(): HasMany
    {
        return $this->hasMany(ClienteReferencia::class)->orderBy('orden');
    }

    public function nombreCompleto(): string
    {
        return trim(collect([
            $this->primer_nombre, $this->segundo_nombre,
            $this->primer_apellido, $this->segundo_apellido,
        ])->filter()->implode(' '));
    }

    public function nombreCorto(): string
    {
        return trim($this->primer_nombre . ' ' . $this->primer_apellido);
    }

    public function iniciales(): string
    {
        return mb_strtoupper(mb_substr((string) $this->primer_nombre, 0, 1) . mb_substr((string) $this->primer_apellido, 0, 1));
    }

    /** Edad actual (se recalcula siempre a partir de la fecha de nacimiento). */
    public function edad(): ?int
    {
        return $this->fecha_nacimiento?->age ?? $this->edad_calculada;
    }

    public function tipoDocumentoLegible(): string
    {
        return match ($this->tipo_documento) {
            'cedula', 'cedula_cr' => 'Cédula',
            'pasaporte'          => 'Pasaporte',
            'residencia'         => 'Cédula de residencia',
            'partida_nacimiento' => 'Código de persona',
            'dimex'              => 'DIMEX',
            default              => 'Documento',
        };
    }

    /** Documento con guiones solo para mostrar (en BD va sin guiones). */
    public function documentoFormateado(): string
    {
        $n = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $this->numero_documento));
        if ($this->tipo_documento === 'cedula' && strlen($n) === 14) {
            return substr($n, 0, 3) . '-' . substr($n, 3, 6) . '-' . substr($n, 9, 5);
        }
        if ($this->tipo_documento === 'cedula_cr' && strlen($n) === 9) {
            return $n[0] . '-' . substr($n, 1, 4) . '-' . substr($n, 5, 4);
        }
        return $n;
    }

    public function sexoLegible(): ?string
    {
        return match ($this->sexo) {
            'M' => 'Masculino', 'F' => 'Femenino', 'O' => 'Otro', default => null,
        };
    }

    public function lugarNacimiento(): ?string
    {
        $p = array_filter([$this->municipio_nacimiento, $this->departamento_nacimiento]);
        return $p ? implode(', ', $p) : null;
    }

    /** Los dos celulares en una sola línea: "+505 8888-8888 (Claro) / +505 7777-7777". */
    public function celulares(): ?string
    {
        $codigo = trim((string) $this->telefono_codigo_pais);
        $armar = function (?string $num, ?string $red) use ($codigo): ?string {
            if (blank($num)) {
                return null;
            }
            return trim(($codigo !== '' ? $codigo . ' ' : '') . trim($num) . (filled($red) ? " ($red)" : ''));
        };

        $lista = array_filter([
            $armar($this->telefono, $this->telefono_red),
            $armar($this->telefono_secundario, $this->telefono_secundario_red),
        ]);

        return $lista ? implode(' / ', $lista) : null;
    }

    /** Qué tan completo está el CV (0-100), para el panel del cliente. */
    public function porcentajeCompleto(): int
    {
        $checks = [
            filled($this->numero_documento), filled($this->fecha_nacimiento), filled($this->telefono),
            filled($this->correo), filled($this->direccion), filled($this->profesion),
            filled($this->perfil_profesional), $this->tieneFoto(),
            $this->estudios()->exists(), $this->experiencias()->exists(),
            $this->habilidades()->exists(), $this->referencias()->exists(),
        ];
        return (int) round(count(array_filter($checks)) / count($checks) * 100);
    }

    public function tieneFoto(): bool
    {
        return filled($this->foto) && \App\Support\Archivos::existe($this->foto);
    }
}
