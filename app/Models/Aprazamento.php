<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AprazamentoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * D-04, a AGENDA. Ancorado em horarios redondos (6/12/18/00), nao no minuto do clique
 * (doc 10.5) -- uma prescricao feita as 14h37 de 6 em 6 horas apraza 18h, 00h, 06h,
 * porque a enfermagem trabalha por turno, nao pelo relogio do prescritor.
 */
class Aprazamento extends Model
{
    /** @use HasFactory<AprazamentoFactory> */
    use HasFactory;

    protected $table = 'aprazamento';

    public $timestamps = false;

    protected $fillable = [
        'prescricao_item_id', 'sequencia', 'horario_previsto', 'situacao',
    ];

    protected function casts(): array
    {
        return [
            'sequencia' => 'integer',
            'horario_previsto' => 'datetime',
        ];
    }

    public function prescricaoItem(): BelongsTo
    {
        return $this->belongsTo(PrescricaoItem::class, 'prescricao_item_id');
    }

    /**
     * RN-20: relacao 1-1 garantida pelo indice unico `uk_adm_aprazamento`. A mesma dose
     * aprazada nao pode ser administrada duas vezes.
     */
    public function administracao(): HasOne
    {
        return $this->hasOne(AdministracaoMedicamento::class, 'aprazamento_id');
    }

    /** Atraso relevante para o checklist do turno (vw_doses_pendentes usa 30 min). */
    public function estaAtrasada(int $toleranciaMinutos = 30): bool
    {
        return $this->situacao === 'PENDENTE'
            && $this->horario_previsto->addMinutes($toleranciaMinutos)->isPast();
    }
}
