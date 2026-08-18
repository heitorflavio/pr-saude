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
