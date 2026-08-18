<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CorPrioridade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * D-03: tabela de dominio, nao ENUM, porque tem atributos proprios. Os cinco niveis de
 * Manchester sao carga inicial (ClassificacaoRiscoSeeder), nao migration de dados.
 */
class ClassificacaoRisco extends Model
{
    protected $table = 'classificacao_risco';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id', 'nome', 'cor_nome', 'cor_hex', 'tempo_alvo_minutos',
        'peso_ordenacao', 'exige_atendimento_imediato', 'descricao',
    ];

    protected function casts(): array
    {
        return [
            'cor_nome' => CorPrioridade::class,
            'tempo_alvo_minutos' => 'integer',
            'peso_ordenacao' => 'integer',
            'exige_atendimento_imediato' => 'boolean',
        ];
    }

    public function atendimentos(): HasMany
    {
        return $this->hasMany(Atendimento::class, 'classificacao_risco_id');
    }

    public function triagens(): HasMany
    {
        return $this->hasMany(Triagem::class, 'classificacao_risco_id');
    }

    public function filaItens(): HasMany
    {
        return $this->hasMany(FilaItem::class, 'classificacao_risco_id');
    }

    /**
     * Carga ponderada da doc 7.4: quanto menor o peso_ordenacao, maior o custo
     * assistencial. Mesma formula da view vw_carga_profissional.
     */
    public function pesoCarga(): int
    {
        return 6 - $this->peso_ordenacao;
    }
}
