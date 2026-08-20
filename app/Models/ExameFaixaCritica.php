<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ExameFaixaCritica extends Model
{
    protected $table = 'exame_faixa_critica';

    protected $fillable = ['analito', 'unidade', 'critico_min', 'critico_max', 'ativo'];

    protected function casts(): array
    {
        return [
            'critico_min' => 'decimal:4',
            'critico_max' => 'decimal:4',
            'ativo' => 'boolean',
        ];
    }
}
