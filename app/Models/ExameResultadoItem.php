<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ExameResultadoItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * doc 11.3: a faixa de referencia e gravada NO RESULTADO, nao apenas no catalogo. O
 * laboratorio pode trocar o metodo amanha; o resultado de hoje precisa continuar
 * interpretavel com a faixa que valia hoje.
 */
class ExameResultadoItem extends Model
{
    /** @use HasFactory<ExameResultadoItemFactory> */
    use HasFactory;

    protected $table = 'exame_resultado_item';

    public $timestamps = false;

    protected $fillable = [
        'exame_resultado_id', 'analito', 'valor', 'unidade',
        'referencia_min', 'referencia_max', 'referencia_texto', 'sinalizacao',
    ];

    protected function casts(): array
    {
        return [
            'referencia_min' => 'decimal:4',
            'referencia_max' => 'decimal:4',
        ];
    }

    public function resultado(): BelongsTo
    {
        return $this->belongsTo(ExameResultado::class, 'exame_resultado_id');
    }

    /** RN-25: analito critico bloqueia liberacao ao paciente antes da ciencia medica. */
    public function ehCritico(): bool
    {
        return $this->sinalizacao === 'CRITICO';
    }
}
