<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ExameResultadoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * RN-24: `visivel_ao_paciente` so pode ser TRUE com `liberado_por` e `liberado_em`
 * preenchidos -- garantido pelo CHECK `ck_result_liberacao`, nao por validacao aqui.
 * Um resultado grave chegando ao paciente antes da leitura medica e dano assistencial.
 *
 * RN-25: valor critico bloqueia a liberacao ao paciente ate a ciencia medica.
 */
class ExameResultado extends Model
{
    /** @use HasFactory<ExameResultadoFactory> */
    use HasFactory;

    protected $table = 'exame_resultado';

    public $timestamps = false;

    protected $fillable = [
        'exame_solicitacao_id', 'laudo', 'conclusao', 'possui_valor_critico',
        'executado_por', 'executado_em', 'liberado_por', 'liberado_em',
        'visivel_ao_paciente', 'criado_em',
    ];

    protected function casts(): array
    {
        return [
            'possui_valor_critico' => 'boolean',
            'visivel_ao_paciente' => 'boolean',
            'executado_em' => 'datetime',
            'liberado_em' => 'datetime',
            'criado_em' => 'datetime',
        ];
    }

    public function solicitacao(): BelongsTo
    {
        return $this->belongsTo(ExameSolicitacao::class, 'exame_solicitacao_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(Profissional::class, 'executado_por', 'user_id');
    }

    public function liberador(): BelongsTo
    {
        return $this->belongsTo(Profissional::class, 'liberado_por', 'user_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ExameResultadoItem::class, 'exame_resultado_id');
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(ExameAnexo::class, 'exame_resultado_id');
    }

    public function foiLiberado(): bool
    {
        return $this->liberado_por !== null && $this->liberado_em !== null;
    }
}
