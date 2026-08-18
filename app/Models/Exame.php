<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ExameFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exame extends Model
{
    /** @use HasFactory<ExameFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'exame';

    protected $fillable = [
        'codigo', 'nome', 'tipo', 'preparo', 'prazo_padrao_minutos', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'prazo_padrao_minutos' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function solicitacoes(): HasMany
    {
        return $this->hasMany(ExameSolicitacao::class, 'exame_id');
    }
}
