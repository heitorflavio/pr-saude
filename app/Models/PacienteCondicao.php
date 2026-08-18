<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PacienteCondicaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PacienteCondicao extends Model
{
    /** @use HasFactory<PacienteCondicaoFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'paciente_condicao';

    protected $fillable = [
        'paciente_id', 'descricao', 'cid10_codigo', 'desde', 'registrado_por',
    ];

    protected function casts(): array
    {
        return ['desde' => 'date'];
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id', 'user_id');
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
