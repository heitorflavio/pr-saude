<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TriagemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * doc 7.5: a reclassificacao e ENCADEADA por `triagem_anterior_id`. A triagem anterior
 * permanece intacta e legivel -- nada e sobrescrito, porque a sequencia de
 * classificacoes de um paciente e o melhor termometro da qualidade da propria triagem
 * (indicador de taxa de reclassificacao, doc 7.6).
 */
class Triagem extends Model
{
    /** @use HasFactory<TriagemFactory> */
    use HasFactory;

    protected $table = 'triagem';

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
        'atendimento_id', 'classificacao_risco_id', 'sinal_vital_id', 'realizada_por',
        'queixa_principal', 'justificativa_classificacao', 'reclassificacao',
        'triagem_anterior_id', 'criado_em',
    ];

    protected function casts(): array
    {
        return [
            'reclassificacao' => 'boolean',
            'criado_em' => 'datetime',
        ];
    }

    public function atendimento(): BelongsTo
    {
        return $this->belongsTo(Atendimento::class, 'atendimento_id');
    }

    public function classificacaoRisco(): BelongsTo
    {
        return $this->belongsTo(ClassificacaoRisco::class, 'classificacao_risco_id');
    }

    public function sinalVital(): BelongsTo
    {
        return $this->belongsTo(SinalVital::class, 'sinal_vital_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(Profissional::class, 'realizada_por', 'user_id');
    }

    public function triagemAnterior(): BelongsTo
    {
        return $this->belongsTo(self::class, 'triagem_anterior_id');
    }

    public function triagemPosterior(): HasOne
    {
        return $this->hasOne(self::class, 'triagem_anterior_id');
    }
}
