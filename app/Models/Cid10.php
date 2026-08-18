<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cid10 extends Model
{
    protected $table = 'cid10';

    protected $primaryKey = 'codigo';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = ['codigo', 'descricao'];

    public function diagnosticos(): HasMany
    {
        return $this->hasMany(Diagnostico::class, 'cid10_codigo', 'codigo');
    }

    public function condicoes(): HasMany
    {
        return $this->hasMany(PacienteCondicao::class, 'cid10_codigo', 'codigo');
    }
}
