<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusAtendimento;
use Database\Factories\AtendimentoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * RN-07 / D-07: a unicidade do atendimento ativo e garantida pela coluna gerada
 * `ativo_key` + indice unico `uk_atendimento_ativo`, NAO por verificacao aqui. Duas
 * recepcionistas clicando ao mesmo tempo passariam por qualquer if().
 *
 * `ativo_key` e coluna gerada pelo banco: nunca aparece em $fillable.
 */
class Atendimento extends Model
{
    /** @use HasFactory<AtendimentoFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'atendimento';

    protected $fillable = [
        'uuid', 'numero', 'paciente_id', 'unidade_id', 'profissional_responsavel_id',
        'classificacao_risco_id', 'status', 'origem', 'sintomas_entrada',
        'admitido_em', 'primeiro_atendimento_em', 'finalizado_em',
        'desfecho', 'desfecho_observacao', 'aberto_por',
    ];

    // `ativo_key` fica deliberadamente fora de $fillable: e coluna gerada pelo banco
    // (RN-07/D-07) e o MySQL recusa INSERT/UPDATE que a mencione.

    protected function casts(): array
    {
        return [
            'status' => StatusAtendimento::class,
            'admitido_em' => 'datetime',
            'primeiro_atendimento_em' => 'datetime',
            'finalizado_em' => 'datetime',
        ];
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id', 'user_id');
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class, 'unidade_id');
    }

    public function profissionalResponsavel(): BelongsTo
    {
        return $this->belongsTo(Profissional::class, 'profissional_responsavel_id', 'user_id');
    }

    public function classificacaoRisco(): BelongsTo
    {
        return $this->belongsTo(ClassificacaoRisco::class, 'classificacao_risco_id');
    }

    public function abertoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aberto_por');
    }

    public function statusHistorico(): HasMany
    {
        return $this->hasMany(AtendimentoStatusHistorico::class, 'atendimento_id');
    }

    public function sintomas(): HasMany
    {
        return $this->hasMany(AtendimentoSintoma::class, 'atendimento_id');
    }

    public function sinaisVitais(): HasMany
    {
        return $this->hasMany(SinalVital::class, 'atendimento_id');
    }

    public function triagens(): HasMany
    {
        return $this->hasMany(Triagem::class, 'atendimento_id');
    }

    public function filaItens(): HasMany
    {
        return $this->hasMany(FilaItem::class, 'atendimento_id');
    }

    public function registrosClinicos(): HasMany
    {
        return $this->hasMany(RegistroClinico::class, 'atendimento_id');
    }

    public function diagnosticos(): HasMany
    {
        return $this->hasMany(Diagnostico::class, 'atendimento_id');
    }

    public function prescricoes(): HasMany
    {
        return $this->hasMany(Prescricao::class, 'atendimento_id');
    }

    public function administracoes(): HasMany
    {
        return $this->hasMany(AdministracaoMedicamento::class, 'atendimento_id');
    }

    public function exameSolicitacoes(): HasMany
    {
        return $this->hasMany(ExameSolicitacao::class, 'atendimento_id');
    }

    public function pulseiraImpressoes(): HasMany
    {
        return $this->hasMany(PulseiraImpressao::class, 'atendimento_id');
    }

    /** Triagem vigente: a mais recente da cadeia de reclassificacoes (doc 7.5). */
    public function triagemVigente(): ?Triagem
    {
        return $this->triagens()->latest('criado_em')->first();
    }

    public function estaAtivo(): bool
    {
        return ! $this->status->ehTerminal();
    }
}
