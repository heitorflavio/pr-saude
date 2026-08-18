<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProfissionalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * D-02: especializacao de `users` com FK-PK compartilhada.
 */
class Profissional extends Model
{
    /** @use HasFactory<ProfissionalFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'profissional';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'user_id', 'unidade_id', 'nome_completo', 'matricula', 'categoria',
        'conselho_tipo', 'conselho_numero', 'conselho_uf', 'especialidade',
        'capacidade_fila', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'capacidade_fila' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class, 'unidade_id');
    }

    public function disponibilidades(): HasMany
    {
        return $this->hasMany(ProfissionalDisponibilidade::class, 'profissional_id', 'user_id');
    }

    public function disponibilidadeVigente(): HasOne
    {
        return $this->hasOne(ProfissionalDisponibilidade::class, 'profissional_id', 'user_id')
            ->whereNull('fim_em');
    }

    public function atendimentosResponsavel(): HasMany
    {
        return $this->hasMany(Atendimento::class, 'profissional_responsavel_id', 'user_id');
    }

    public function filaItens(): HasMany
    {
        return $this->hasMany(FilaItem::class, 'profissional_id', 'user_id');
    }

    /**
     * Snapshot gravado em `registro_clinico.autor_conselho`: se o cadastro mudar
     * depois, o registro continua dizendo quem assinou naquele momento.
     */
    public function conselhoFormatado(): ?string
    {
        if ($this->conselho_tipo === null || $this->conselho_numero === null) {
            return null;
        }

        return $this->conselho_uf
            ? "{$this->conselho_tipo}/{$this->conselho_uf} {$this->conselho_numero}"
            : "{$this->conselho_tipo} {$this->conselho_numero}";
    }
}
