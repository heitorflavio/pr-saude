<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UnidadeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unidade extends Model
{
    /** @use HasFactory<UnidadeFactory> */
    use HasFactory;

    protected $table = 'unidade';

    protected $fillable = ['nome', 'cnes', 'fuso_horario', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function profissionais(): HasMany
    {
        return $this->hasMany(Profissional::class, 'unidade_id');
    }

    public function atendimentos(): HasMany
    {
        return $this->hasMany(Atendimento::class, 'unidade_id');
    }
}
