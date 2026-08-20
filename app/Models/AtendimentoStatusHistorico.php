<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AtendimentoStatusHistoricoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RN-15: append-only. O historico e acrescentado, nunca sobrescrito -- e o que permite
 * reconstruir a linha do tempo do atendimento (RF-22) e medir permanencia por status
 * (RF-39).
 */
class AtendimentoStatusHistorico extends Model
{
    /** @use HasFactory<AtendimentoStatusHistoricoFactory> */
    use HasFactory;

    protected $table = 'atendimento_status_historico';

    public $timestamps = false;

    /**
     * O schema.sql declara `criado_em` como DATETIME(6) de proposito: e a precisao de
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
        'atendimento_id', 'status_anterior', 'status_novo', 'alterado_por',
        'observacao', 'permanencia_segundos', 'criado_em',
    ];

    protected function casts(): array
    {
        return [
            'permanencia_segundos' => 'integer',
            'criado_em' => 'datetime',
        ];
    }

    public function atendimento(): BelongsTo
    {
        return $this->belongsTo(Atendimento::class, 'atendimento_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'alterado_por');
    }
}
