<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\QueixaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Queixa extends Model
{
    /** @use HasFactory<QueixaFactory> */
    use HasFactory;

    protected $table = 'queixa';

    public $timestamps = false;

    protected $fillable = ['descricao', 'fluxograma_manchester', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function sintomas(): HasMany
    {
        return $this->hasMany(AtendimentoSintoma::class, 'queixa_id');
    }
}
