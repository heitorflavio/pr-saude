<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PrescricaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prescricao extends Model
{
    /** @use HasFactory<PrescricaoFactory> */
    use HasFactory;

    protected $table = 'prescricao';

    public $timestamps = false;

    protected $fillable = [
        'atendimento_id', 'prescrito_por', 'status', 'vigencia_inicio', 'vigencia_fim',
        'observacao', 'suspensa_por', 'suspensa_em', 'motivo_suspensao', 'criado_em',
    ];

    protected function casts(): array
    {
        return [
            'vigencia_inicio' => 'datetime',
            'vigencia_fim' => 'datetime',
            'suspensa_em' => 'datetime',
            'criado_em' => 'datetime',
        ];
    }

    public function atendimento(): BelongsTo
    {
        return $this->belongsTo(Atendimento::class, 'atendimento_id');
    }

    public function prescritor(): BelongsTo
    {
        return $this->belongsTo(Profissional::class, 'prescrito_por', 'user_id');
    }

    public function suspensor(): BelongsTo
    {
        return $this->belongsTo(Profissional::class, 'suspensa_por', 'user_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(PrescricaoItem::class, 'prescricao_id');
    }
}
