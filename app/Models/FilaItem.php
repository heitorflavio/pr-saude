<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FilaItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RN-10: NAO existe atributo `posicao` aqui. A posicao e calculada na leitura por
 * ROW_NUMBER() na view `vw_fila_ordenada`.
 *
 * `entrou_em` e preservado na reclassificacao (doc 7.5) e na transferencia entre filas:
 * o paciente nao volta ao fim da fila por ter sido reavaliado ou remanejado.
 */
class FilaItem extends Model
{
    /** @use HasFactory<FilaItemFactory> */
    use HasFactory;

    protected $table = 'fila_item';

    public $timestamps = false;

    protected $fillable = [
        'atendimento_id', 'profissional_id', 'classificacao_risco_id', 'situacao',
        'entrou_em', 'chamado_em', 'saiu_em', 'transferido_de_id',
        'justificativa_transferencia', 'criado_por',
    ];

    protected function casts(): array
    {
        return [
            'entrou_em' => 'datetime',
            'chamado_em' => 'datetime',
            'saiu_em' => 'datetime',
        ];
    }

    public function atendimento(): BelongsTo
    {
        return $this->belongsTo(Atendimento::class, 'atendimento_id');
    }

    public function profissional(): BelongsTo
    {
        return $this->belongsTo(Profissional::class, 'profissional_id', 'user_id');
    }

    public function classificacaoRisco(): BelongsTo
    {
        return $this->belongsTo(ClassificacaoRisco::class, 'classificacao_risco_id');
    }

    public function origem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'transferido_de_id');
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    /** Itens que efetivamente ocupam a fila (mesmo filtro da vw_fila_ordenada). */
    public function scopeNaFila($query)
    {
        return $query->whereIn('situacao', ['AGUARDANDO', 'CHAMADO']);
    }
}
