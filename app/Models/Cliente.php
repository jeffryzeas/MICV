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
        // Los demás países (HN, SV, GT, MX, CO, ES, US) están en App\Support\Pais.
    ];

    protected $fillable = [
        'user_id', 'pais', 'tipo_documento', 'numero_documento', 'inss_nss',
        'primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido',
        'fecha_nacimiento', 'edad_calculada', 'municipio_nacimiento', 'departamento_nacimiento',
        'sexo', 'nacionalidad', 'numero_hijos', 'estado_civil', 'profesion', 'perfil_profesional',
        'correo', 'telefono_codigo_pais', 'telefono', 'telefono_red',
        'telefono_secundario', 'telefono_secundario_red', 'direccion',
        'nombre_padre', 'nombre_madre', 'notas', 'foto', 'activo', 'creado_por',
        'linkedin', 'sitio_web', 'github', 'telefono_whatsapp', 'profesion_en', 'perfil_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'numero_hijos'     => 'integer',
            'edad_calculada'   => 'integer',
            'activo'           => 'boolean',
            'telefono_whatsapp' => 'boolean',
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
        $bloqueados = DocumentoBloqueado::lista();
        return $q->whereHas('cybers', fn ($w) => $w->where('cybers.id', $cyberId))
            // Lista privada: los Cybers no ven a esas personas.
            ->when($bloqueados, fn ($w) => $w->where(fn ($x) => $x->whereNull('numero_documento')
                ->orWhereNotIn(\Illuminate\Support\Facades\DB::raw("UPPER(REPLACE(numero_documento, '-', ''))"), $bloqueados)));
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
        if (DocumentoBloqueado::estaBloqueado($this->numero_documento)) {
            return false; // lista privada
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

    public function idiomas(): HasMany
    {
        return $this->hasMany(ClienteIdioma::class)->orderBy('orden');
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
            'cedula', 'cedula_cr', 'cedula_co' => 'Cédula',
            'pasaporte'          => 'Pasaporte',
            'residencia'         => 'Cédula de residencia',
            'partida_nacimiento' => 'Código de persona',
            'dimex'              => 'DIMEX',
            'dni_hn', 'dni_es'   => 'DNI',
            'dui_sv'             => 'DUI',
            'dpi_gt'             => 'DPI',
            'curp_mx'            => 'CURP',
            'ine_mx'             => 'INE',
            'ce_co'              => 'Cédula de extranjería',
            'ppt_co'             => 'PPT',
            'nie_es'             => 'NIE',
            'licencia_us'        => 'Licencia / State ID',
            'green_us'           => 'Green Card',
            'cedula_pa', 'cedula_ec' => 'Cédula',
            'cedula_do'          => 'Cédula',
            'ci_cu', 'ci_ve', 'ci_bo', 'ci_uy', 'ci_py' => 'Cédula de identidad',
            'dni_pe', 'dni_ar'   => 'DNI',
            'ce_pe'              => 'Carné de extranjería',
            'rut_cl'             => 'RUT',
            'cuil_ar'            => 'CUIL',
            'residencia_pa', 'residencia_do' => 'Residencia',
            'residencia_hn', 'residencia_sv', 'residencia_gt', 'residencia_mx' => 'Residencia',
            default              => \App\Support\Pais::todosLosDocumentos()[$this->tipo_documento] ?? 'Documento',
        };
    }

    /** Documento con guiones solo para mostrar (en BD va sin guiones). */
    public function documentoFormateado(): string
    {
        if ($this->tipo_documento === 'cedula_pa') {
            return (string) $this->numero_documento;
        }
        $n = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $this->numero_documento));
        if ($this->tipo_documento === 'cedula' && strlen($n) === 14) {
            return substr($n, 0, 3) . '-' . substr($n, 3, 6) . '-' . substr($n, 9, 5);
        }
        if ($this->tipo_documento && $n !== '' && $this->tipo_documento !== 'cedula') {
            $v = \App\Support\Documentos::validar($this->tipo_documento, $n);
            return $v['valida'] ? $v['formateado'] : $n;
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
    /** Lo que tiene y lo que le falta al CV: [clave => [texto, hecho, ancla en "Mis datos"]]. */
    public function pendientesCv(): array
    {
        return [
            'foto'        => ['Foto', $this->tieneFoto(), 'sec-foto'],
            'documento'   => ['Documento', filled($this->numero_documento), 'sec-documento'],
            'nacimiento'  => ['Fecha de nacimiento', filled($this->fecha_nacimiento), 'sec-nacimiento'],
            'contacto'    => ['Celular y dirección', filled($this->telefono) && filled($this->direccion), 'sec-contacto'],
            'profesion'   => ['Profesión u oficio', filled($this->profesion), 'sec-trabajo'],
            'perfil'      => ['Perfil profesional', filled($this->perfil_profesional), 'sec-perfil'],
            'estudios'    => ['Estudios', $this->estudios()->exists(), 'bloque-estudios'],
            'experiencia' => ['Experiencia', $this->experiencias()->exists(), 'bloque-experiencias'],
            'idiomas'     => ['Idiomas', $this->idiomas()->exists(), 'bloque-idiomas'],
            'habilidades' => ['Habilidades', $this->habilidades()->exists(), 'bloque-habilidades'],
            'referencias' => ['Referencias', $this->referencias()->exists(), 'bloque-referencias'],
        ];
    }

    public function porcentajeCompleto(): int
    {
        $p = $this->pendientesCv();
        $p['correo'] = ['Correo', filled($this->correo), 'sec-contacto'];
        return (int) round(count(array_filter($p, fn ($x) => $x[1])) / count($p) * 100);
    }

    /**
     * Enlaces profesionales para el CV: [ícono, texto que se ve].
     * Se guardan ya limpios (sin https:// ni www.).
     */
    public function enlaces(): array
    {
        return array_values(array_filter([
            $this->linkedin ? ['linkedin', $this->linkedin] : null,
            $this->github ? ['github', $this->github] : null,
            $this->sitio_web ? ['web', $this->sitio_web] : null,
        ]));
    }

    /** Deja "linkedin.com/in/usuario", "github.com/usuario" o "misitio.com". */
    public static function limpiarEnlace(?string $valor, string $tipo): ?string
    {
        $v = trim((string) $valor);
        if ($v === '') {
            return null;
        }
        $v = preg_replace('#^https?://#i', '', $v);
        $v = preg_replace('#^www\.#i', '', $v);
        $v = rtrim($v, '/');
        if ($tipo === 'linkedin') {
            if (!preg_match('#^([a-z]{2,3}\.)?linkedin\.com/#i', $v)) {
                $v = 'linkedin.com/in/' . ltrim(preg_replace('#^(in/|@)#i', '', $v), '/');
            }
            $v = preg_replace('#^[a-z]{2,3}\.linkedin#i', 'linkedin', $v);
        } elseif ($tipo === 'github') {
            if (!preg_match('#^github\.com/#i', $v)) {
                $v = 'github.com/' . ltrim($v, '@/');
            }
        }
        return mb_substr($v, 0, 200);
    }

    public function tieneFoto(): bool
    {
        return filled($this->foto) && \App\Support\Archivos::existe($this->foto);
    }
}
