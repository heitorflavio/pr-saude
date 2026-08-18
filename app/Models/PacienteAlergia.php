<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PacienteAlergiaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * RF-11: exibida em destaque em toda tela do atendimento.
 * doc 13.5: a lista de alergias faz parte do "minimo vital" -- e liberada a qualquer
 * profissional em plantao, mesmo sem vinculo assistencial, porque negar alergias a quem
 * atende uma parada no corredor seria decisao de projeto com potencial letal.
 */
class PacienteAlergia extends Model
{
    /** @use HasFactory<PacienteAlergiaFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'paciente_alergia';

    protected $fillable = [
        'paciente_id', 'substancia', 'medicamento_id',
        'gravidade', 'reacao', 'registrado_por',
    ];

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id', 'user_id');
    }

    public function medicamento(): BelongsTo
    {
        return $this->belongsTo(Medicamento::class, 'medicamento_id');
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(Profissional::class, 'registrado_por', 'user_id');
    }

    /**
     * RN-21: o principio ativo e o que importa na verificacao. Quando a alergia esta
     * vinculada ao catalogo, usa o principio do medicamento; senao, a substancia
     * registrada em texto livre.
     */
    public function principioAtivo(): string
    {
        return $this->medicamento?->principio_ativo ?? $this->substancia;
    }
}
