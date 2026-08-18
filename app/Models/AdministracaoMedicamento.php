<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ViaAdministracao;
use Database\Factories\AdministracaoMedicamentoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * D-04, o FATO: "dose das 12h aplicada as 12h37 por Joao, COREN 123456".
 *
 * Note a assimetria deliberada entre RN-21 e RN-23, e o motivo dela (doc 10.4):
 * alergia BLOQUEIA (liberavel so com justificativa registrada); divergencia de dose
 * apenas SINALIZA. Bloquear tudo produz fadiga de alerta -- e um profissional que
 * clica "ok" em todo aviso e um profissional sem nenhum aviso.
 */
class AdministracaoMedicamento extends Model
{
    /** @use HasFactory<AdministracaoMedicamentoFactory> */
    use HasFactory;

    protected $table = 'administracao_medicamento';

    public $timestamps = false;

    protected $fillable = [
        'aprazamento_id', 'prescricao_item_id', 'atendimento_id',
        'dose_administrada', 'unidade_dose', 'via', 'administrado_em',
        'administrado_por', 'checado_por', 'resultado', 'motivo_nao_administracao',
        'alerta_alergia_sobreposto', 'justificativa', 'observacao',
    ];

    protected function casts(): array
    {
        return [
            'dose_administrada' => 'decimal:3',
            'via' => ViaAdministracao::class,
            'administrado_em' => 'datetime',
            'alerta_alergia_sobreposto' => 'boolean',
        ];
    }

    public function aprazamento(): BelongsTo
    {
        return $this->belongsTo(Aprazamento::class, 'aprazamento_id');
    }

    public function prescricaoItem(): BelongsTo
    {
        return $this->belongsTo(PrescricaoItem::class, 'prescricao_item_id');
    }

    public function atendimento(): BelongsTo
    {
        return $this->belongsTo(Atendimento::class, 'atendimento_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(Profissional::class, 'administrado_por', 'user_id');
    }

    /** RN-22: conferente da dupla checagem, obrigatoriamente distinto do executor. */
    public function conferente(): BelongsTo
    {
        return $this->belongsTo(Profissional::class, 'checado_por', 'user_id');
    }

    public function foiAdministrada(): bool
    {
        return $this->resultado === 'ADMINISTRADA';
    }
}
