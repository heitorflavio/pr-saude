<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ViaAdministracao;
use Database\Factories\PrescricaoItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * D-04, a ORDEM medica. O aprazamento e a agenda; a administracao e o fato.
 */
class PrescricaoItem extends Model
{
    /** @use HasFactory<PrescricaoItemFactory> */
    use HasFactory;

    protected $table = 'prescricao_item';

    public $timestamps = false;

    protected $fillable = [
        'prescricao_id', 'medicamento_id', 'dose', 'unidade_dose', 'via',
        'frequencia_horas', 'duracao_horas', 'se_necessario', 'diluicao',
        'velocidade_infusao', 'observacao', 'status',
    ];

    protected function casts(): array
    {
        return [
            'dose' => 'decimal:3',
            'via' => ViaAdministracao::class,
            'frequencia_horas' => 'integer',
            'duracao_horas' => 'integer',
            'se_necessario' => 'boolean',
        ];
    }

    public function prescricao(): BelongsTo
    {
        return $this->belongsTo(Prescricao::class, 'prescricao_id');
    }

    public function medicamento(): BelongsTo
    {
        return $this->belongsTo(Medicamento::class, 'medicamento_id');
    }

    public function aprazamentos(): HasMany
    {
        return $this->hasMany(Aprazamento::class, 'prescricao_item_id');
    }

    public function administracoes(): HasMany
    {
        return $this->hasMany(AdministracaoMedicamento::class, 'prescricao_item_id');
    }

    /** doc 10.5: medicacao "se necessario" (SOS/PRN) nao e aprazada. */
    public function ehAprazavel(): bool
    {
        return ! $this->se_necessario;
    }
}
