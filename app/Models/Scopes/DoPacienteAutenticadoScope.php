<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Models\AdministracaoMedicamento;
use App\Models\Aprazamento;
use App\Models\Atendimento;
use App\Models\Diagnostico;
use App\Models\ExameAnexo;
use App\Models\ExameResultado;
use App\Models\ExameResultadoItem;
use App\Models\ExameSolicitacao;
use App\Models\FilaItem;
use App\Models\Prescricao;
use App\Models\PrescricaoItem;
use App\Models\RegistroClinico;
use App\Models\SinalVital;
use App\Models\Triagem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** RN-26: defesa em profundidade para toda consulta clínica feita pelo portal. */
final class DoPacienteAutenticadoScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $usuario = auth('paciente')->user();
        if ($usuario === null) {
            return;
        }

        $pacienteId = $usuario->id;

        match (true) {
            $model instanceof Atendimento => $builder->where($model->getTable().'.paciente_id', $pacienteId),
            $model instanceof RegistroClinico,
            $model instanceof Diagnostico,
            $model instanceof Prescricao,
            $model instanceof AdministracaoMedicamento,
            $model instanceof ExameSolicitacao,
            $model instanceof Triagem,
            $model instanceof SinalVital,
            $model instanceof FilaItem => $builder->whereHas('atendimento', fn (Builder $q) => $q->where('paciente_id', $pacienteId)),
            $model instanceof PrescricaoItem => $builder->whereHas('prescricao.atendimento', fn (Builder $q) => $q->where('paciente_id', $pacienteId)),
            $model instanceof Aprazamento => $builder->whereHas('prescricaoItem.prescricao.atendimento', fn (Builder $q) => $q->where('paciente_id', $pacienteId)),
            $model instanceof ExameResultado => $builder->whereHas('solicitacao.atendimento', fn (Builder $q) => $q->where('paciente_id', $pacienteId)),
            $model instanceof ExameResultadoItem,
            $model instanceof ExameAnexo => $builder->whereHas('resultado.solicitacao.atendimento', fn (Builder $q) => $q->where('paciente_id', $pacienteId)),
            default => null,
        };
    }
}
