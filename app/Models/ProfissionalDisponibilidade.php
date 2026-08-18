<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProfissionalDisponibilidadeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfissionalDisponibilidade extends Model
{
    /** @use HasFactory<ProfissionalDisponibilidadeFactory> */
    use HasFactory;

    protected $table = 'profissional_disponibilidade';

    public $timestamps = false;

    protected $fillable = [
        'profissional_id', 'situacao', 'inicio_em', 'fim_em', 'observacao',
    ];

    protected function casts(): array
    {
        return [
            'inicio_em' => 'datetime',
            'fim_em' => 'datetime',
        ];
    }

    public function profissional(): BelongsTo
    {
        return $this->belongsTo(Profissional::class, 'profissional_id', 'user_id');
    }

    /** `fim_em` nulo significa situacao vigente. */
    public function estaVigente(): bool
    {
        return $this->fim_em === null;
    }
}
