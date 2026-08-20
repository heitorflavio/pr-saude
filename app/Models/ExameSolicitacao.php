<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SituacaoExame;
use Database\Factories\ExameSolicitacaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Ciclo de vida: SOLICITADO -> COLETADO -> EM_EXECUCAO -> CONCLUIDO -> LIBERADO.
 */
class ExameSolicitacao extends Model
{
    /** @use HasFactory<ExameSolicitacaoFactory> */
    use HasFactory;

    protected $table = 'exame_solicitacao';

    public $timestamps = false;

    /**
     * O schema.sql declara `solicitado_em` como DATETIME(6) de proposito: e a precisao de
     * microssegundo que desempata registros criados dentro do mesmo segundo -- ordem da
     * linha do tempo, desempate por ordem de chegada na fila (RN-10), sequencia de
     * reclassificacoes.
     *
     * O `$dateFormat` padrao do Laravel e 'Y-m-d H:i:s', que TRUNCA os microssegundos na
     * escrita e anula a precisao que a coluna oferece. Sem esta linha, dois registros do
     * mesmo segundo ficam com timestamp identico e a ordenacao vira indefinida.
     */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'atendimento_id', 'exame_id', 'solicitado_por', 'carater', 'indicacao_clinica',
        'situacao', 'solicitado_em', 'coletado_em', 'coletado_por',
        'cancelado_em', 'cancelado_por', 'motivo_cancelamento',
    ];

    protected function casts(): array
    {
        return [
            'situacao' => SituacaoExame::class,
            'solicitado_em' => 'datetime',
            'coletado_em' => 'datetime',
            'cancelado_em' => 'datetime',
        ];
    }

    public function atendimento(): BelongsTo
    {
        return $this->belongsTo(Atendimento::class, 'atendimento_id');
    }

    public function exame(): BelongsTo
    {
        return $this->belongsTo(Exame::class, 'exame_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(Profissional::class, 'solicitado_por', 'user_id');
    }

    public function coletor(): BelongsTo
    {
        return $this->belongsTo(Profissional::class, 'coletado_por', 'user_id');
    }

    public function cancelador(): BelongsTo
    {
        return $this->belongsTo(Profissional::class, 'cancelado_por', 'user_id');
    }

    public function resultado(): HasOne
    {
        return $this->hasOne(ExameResultado::class, 'exame_solicitacao_id');
    }

    /** Fila do laboratorio: urgentes primeiro, depois ordem de solicitacao. */
    public function scopeFilaLaboratorio($query)
    {
        return $query->whereIn('situacao', ['SOLICITADO', 'COLETADO', 'EM_EXECUCAO'])
            ->orderByRaw("FIELD(carater, 'URGENTE', 'ROTINA')")
            ->orderBy('solicitado_em');
    }
}
