<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AtendimentoSintomaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtendimentoSintoma extends Model
{
    /** @use HasFactory<AtendimentoSintomaFactory> */
    use HasFactory;

    protected $table = 'atendimento_sintoma';

    public $timestamps = false;

    protected $fillable = ['atendimento_id', 'queixa_id', 'descricao_livre'];

    public function atendimento(): BelongsTo
    {
        return $this->belongsTo(Atendimento::class, 'atendimento_id');
    }

    public function queixa(): BelongsTo
    {
        return $this->belongsTo(Queixa::class, 'queixa_id');
    }
}
