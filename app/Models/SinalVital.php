<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SinalVitalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * D-06: tabela propria, nao colunas dentro de `triagem`. Sinais vitais sao aferidos na
 * triagem E repetidamente durante o atendimento -- guarda-los na triagem impediria a
 * serie temporal.
 *
 * As faixas (dor 0-10, SpO2 0-100, temperatura 25-45) sao garantidas por CHECK no
 * banco, nao aqui: um erro de digitacao que vira decisao clinica precisa falhar na
 * escrita, nao depender de validacao ter sido chamada.
 */
class SinalVital extends Model
{
    /** @use HasFactory<SinalVitalFactory> */
    use HasFactory;

    protected $table = 'sinal_vital';

    public $timestamps = false;

    protected $fillable = [
        'atendimento_id', 'pressao_sistolica', 'pressao_diastolica',
        'frequencia_cardiaca', 'frequencia_respiratoria', 'saturacao_o2',
        'temperatura', 'glicemia', 'peso_kg', 'altura_cm', 'escala_dor',
        'aferido_por', 'aferido_em',
    ];

    protected function casts(): array
    {
        return [
            'pressao_sistolica' => 'integer',
            'pressao_diastolica' => 'integer',
            'frequencia_cardiaca' => 'integer',
            'frequencia_respiratoria' => 'integer',
            'saturacao_o2' => 'decimal:1',
            'temperatura' => 'decimal:1',
            'glicemia' => 'decimal:1',
            'peso_kg' => 'decimal:2',
            'altura_cm' => 'integer',
            'escala_dor' => 'integer',
            'aferido_em' => 'datetime',
        ];
    }

    public function atendimento(): BelongsTo
    {
        return $this->belongsTo(Atendimento::class, 'atendimento_id');
    }

    public function aferidor(): BelongsTo
    {
        return $this->belongsTo(Profissional::class, 'aferido_por', 'user_id');
    }
}
