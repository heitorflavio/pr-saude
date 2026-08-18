<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PulseiraImpressaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RF-15: toda impressao e registrada, com motivo.
 * RF-16 / RN-03: a reimpressao usa o MESMO token -- o token e permanente. Esta tabela
 * registra eventos de impressao, nunca tokens novos.
 */
class PulseiraImpressao extends Model
{
    /** @use HasFactory<PulseiraImpressaoFactory> */
    use HasFactory;

    protected $table = 'pulseira_impressao';

    public $timestamps = false;

    protected $fillable = [
        'paciente_id', 'atendimento_id', 'classificacao_risco_id',
        'motivo', 'observacao', 'impressa_por', 'criado_em',
    ];

    protected function casts(): array
    {
        return ['criado_em' => 'datetime'];
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id', 'user_id');
    }

    public function atendimento(): BelongsTo
    {
        return $this->belongsTo(Atendimento::class, 'atendimento_id');
    }

    public function classificacaoRisco(): BelongsTo
    {
        return $this->belongsTo(ClassificacaoRisco::class, 'classificacao_risco_id');
    }

    public function impressor(): BelongsTo
    {
        return $this->belongsTo(Profissional::class, 'impressa_por', 'user_id');
    }
}
