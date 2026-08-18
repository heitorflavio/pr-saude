<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AuditoriaLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RNF-11: imutavel. Registra LEITURA, nao so escrita (doc 14.3) -- em dado de saude o
 * dano tipico e bisbilhotagem, nao alteracao, e um log que so registra escrita nao
 * detecta o dano tipico.
 *
 * `perfis_no_momento` e snapshot das roles no instante do evento: se as roles mudarem
 * depois, o log continua dizendo com que papel a pessoa agiu.
 *
 * O mascaramento de `password`, `token_pulseira`, `cpf` e `cns` acontece no
 * AuditoriaService (Fase 2), antes de chegar em `dados_antes`/`dados_depois`.
 */
class AuditoriaLog extends Model
{
    /** @use HasFactory<AuditoriaLogFactory> */
    use HasFactory;

    protected $table = 'auditoria_log';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'perfis_no_momento', 'acao', 'entidade', 'entidade_id',
        'paciente_id', 'atendimento_id', 'justificativa',
        'dados_antes', 'dados_depois', 'ip', 'user_agent', 'criado_em',
    ];

    protected function casts(): array
    {
        return [
            'dados_antes' => 'array',
            'dados_depois' => 'array',
            'criado_em' => 'datetime',
        ];
    }

    /**
     * Sem FK no banco (o log sobrevive a remocao logica do que referencia), mas a
     * relacao existe para leitura na tela de auditoria.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id', 'user_id');
    }

    public function atendimento(): BelongsTo
    {
        return $this->belongsTo(Atendimento::class, 'atendimento_id');
    }
}
