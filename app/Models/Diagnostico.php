<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DiagnosticoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Diagnostico extends Model
{
    /** @use HasFactory<DiagnosticoFactory> */
    use HasFactory;

    protected $table = 'diagnostico';

    public $timestamps = false;

    protected $fillable = [
        'atendimento_id', 'cid10_codigo', 'natureza', 'principal',
        'observacao', 'registrado_por', 'criado_em',
    ];

    protected function casts(): array
    {
        return [
            'principal' => 'boolean',
            'criado_em' => 'datetime',
        ];
    }

    public function atendimento(): BelongsTo
    {
        return $this->belongsTo(Atendimento::class, 'atendimento_id');
    }

    public function cid10(): BelongsTo
    {
        return $this->belongsTo(Cid10::class, 'cid10_codigo', 'codigo');
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(Profissional::class, 'registrado_por', 'user_id');
    }
}
