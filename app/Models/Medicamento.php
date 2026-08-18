<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ViaAdministracao;
use Database\Factories\MedicamentoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Medicamento extends Model
{
    /** @use HasFactory<MedicamentoFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'medicamento';

    protected $fillable = [
        'nome_comercial', 'principio_ativo', 'concentracao', 'forma_farmaceutica',
        'classe_via', 'injetavel', 'alta_vigilancia', 'controlado',
        'unidade_dose_padrao', 'dose_maxima_diaria', 'observacao', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'classe_via' => ViaAdministracao::class,
            'injetavel' => 'boolean',
            'alta_vigilancia' => 'boolean',
            'controlado' => 'boolean',
            'dose_maxima_diaria' => 'decimal:3',
            'ativo' => 'boolean',
        ];
    }

    public function alergias(): HasMany
    {
        return $this->hasMany(PacienteAlergia::class, 'medicamento_id');
    }

    public function prescricaoItens(): HasMany
    {
        return $this->hasMany(PrescricaoItem::class, 'medicamento_id');
    }

    /**
     * RN-21: a alergia e verificada por PRINCIPIO ATIVO, nunca por nome comercial.
     * Dipirona e Novalgina sao o mesmo farmaco; comparar nome comercial deixaria o
     * alerta passar.
     */
    public function scopeDoPrincipioAtivo($query, string $principioAtivo)
    {
        return $query->whereRaw('LOWER(principio_ativo) = ?', [mb_strtolower($principioAtivo)]);
    }
}
