<?php

declare(strict_types=1);

namespace App\Actions\Medicamento;

use App\Enums\ViaAdministracao;
use App\Events\AlertaAlergiaSobreposto;
use App\Events\DoseDivergente;
use App\Events\MedicamentoAdministrado;
use App\Exceptions\AdministracaoInvalidaException;
use App\Exceptions\AlergiaBloqueanteException;
use App\Exceptions\DoseJaAdministradaException;
use App\Exceptions\DuplaChecagemObrigatoriaException;
use App\Exceptions\PrescricaoNaoVigenteException;
use App\Models\AdministracaoMedicamento;
use App\Models\Aprazamento;
use App\Models\PacienteAlergia;
use App\Models\Profissional;
use App\Services\Auditoria\AuditoriaService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** UC-10 — registro do fato e baixa da agenda dentro da mesma transação. */
final class RegistrarAdministracaoAction
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    public function execute(
        Aprazamento $dose,
        Profissional $executor,
        ?float $doseAdministrada,
        ViaAdministracao|string|null $via,
        string $resultado = 'ADMINISTRADA',
        ?string $motivoNaoAdministracao = null,
        ?Profissional $conferente = null,
        ?string $justificativaAlergia = null,
        ?string $observacao = null,
    ): AdministracaoMedicamento {
        try {
            return DB::transaction(function () use (
                $dose, $executor, $doseAdministrada, $via, $resultado,
                $motivoNaoAdministracao, $conferente, $justificativaAlergia, $observacao
            ) {
                // RN-20: a trava serializa duas confirmações simultâneas; o UNIQUE é a
                // garantia final caso uma escrita venha de outro caminho.
                $dose = Aprazamento::query()->lockForUpdate()->findOrFail($dose->id);
                $dose->load('administracao.executor', 'prescricaoItem.prescricao.atendimento.paciente.alergias.medicamento', 'prescricaoItem.medicamento');

                if ($dose->situacao !== 'PENDENTE') {
                    throw DoseJaAdministradaException::paraSituacao($dose->situacao, $dose->administracao);
                }

                $item = $dose->prescricaoItem;
                $prescricao = $item->prescricao;
                $atendimento = $prescricao->atendimento;

                if ($atendimento->status->ehTerminal()) {
                    throw AdministracaoInvalidaException::atendimentoEncerrado();
                }

                if ($prescricao->status !== 'VIGENTE' || $item->status !== 'VIGENTE'
                    // `vigencia_inicio` é DATETIME(0): o MySQL pode arredondar a fração
                    // do `now()` ao segundo seguinte. Um segundo de tolerância evita
                    // classificar a ordem recém-criada como futura.
                    || $prescricao->vigencia_inicio->greaterThan(now()->addSecond())
                    || ($prescricao->vigencia_fim !== null && $prescricao->vigencia_fim->isPast())) {
                    throw PrescricaoNaoVigenteException::ordemInvalida();
                }

                $administrada = $resultado === 'ADMINISTRADA';
                if (! $administrada && blank($motivoNaoAdministracao)) {
                    throw AdministracaoInvalidaException::motivoObrigatorio();
                }

                $alergia = $administrada ? $this->alergiaCorrespondente($atendimento->paciente->alergias, $item->medicamento->principio_ativo, $item->medicamento_id) : null;
                if ($alergia !== null && blank($justificativaAlergia)) {
                    throw new AlergiaBloqueanteException($alergia);
                }

                if ($administrada && $item->medicamento->alta_vigilancia) {
                    if ($conferente === null) {
                        throw DuplaChecagemObrigatoriaException::ausente($item->medicamento->nome_comercial);
                    }
                    if ($conferente->user_id === $executor->user_id) {
                        throw DuplaChecagemObrigatoriaException::mesmoExecutor();
                    }
                }

                $divergente = $administrada && abs((float) $doseAdministrada - (float) $item->dose) > 0.001;
                if ($divergente && blank($observacao)) {
                    throw AdministracaoInvalidaException::divergenciaSemObservacao();
                }

                $viaValor = $via instanceof ViaAdministracao ? $via->value : $via;
                $registro = AdministracaoMedicamento::create([
                    'aprazamento_id' => $dose->id,
                    'prescricao_item_id' => $item->id,
                    'atendimento_id' => $atendimento->id,
                    'dose_administrada' => $administrada ? $doseAdministrada : null,
                    'unidade_dose' => $administrada ? $item->unidade_dose : null,
                    'via' => $administrada ? $viaValor : null,
                    // RN-29: a hora real vem do servidor, não do formulário.
                    'administrado_em' => now(),
                    'administrado_por' => $executor->user_id,
                    'checado_por' => $administrada ? $conferente?->user_id : null,
                    'resultado' => $resultado,
                    'motivo_nao_administracao' => $administrada ? null : $motivoNaoAdministracao,
                    'alerta_alergia_sobreposto' => $alergia !== null,
                    'justificativa' => $alergia !== null ? trim((string) $justificativaAlergia) : null,
                    'observacao' => filled($observacao) ? trim((string) $observacao) : null,
                ]);

                $dose->update(['situacao' => $administrada ? 'ADMINISTRADA' : 'NAO_ADMINISTRADA']);

                $this->auditoria->registrar(
                    acao: $administrada ? 'medicamento.administrar' : 'medicamento.nao_administrar',
                    paciente: $atendimento->paciente,
                    atendimento: $atendimento,
                    entidade: 'AdministracaoMedicamento',
                    entidadeId: $registro->id,
                    depois: [
                        'aprazamento_id' => $dose->id,
                        'resultado' => $resultado,
                        'alerta_alergia_sobreposto' => $alergia !== null,
                        'dose_divergente' => $divergente,
                    ],
                    justificativa: $alergia !== null ? $justificativaAlergia : $motivoNaoAdministracao,
                    usuario: $executor->user,
                );

                if ($alergia !== null) {
                    AlertaAlergiaSobreposto::dispatch($registro, $alergia);
                }
                if ($divergente) {
                    DoseDivergente::dispatch($registro, $item);
                }
                MedicamentoAdministrado::dispatch($registro);

                return $registro;
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'uk_adm_aprazamento')) {
                throw DoseJaAdministradaException::paraSituacao('já executada');
            }

            throw $e;
        }
    }

    /** @param Collection<int, PacienteAlergia> $alergias */
    private function alergiaCorrespondente($alergias, string $principioAtivo, int $medicamentoId): ?PacienteAlergia
    {
        $esperado = Str::lower(Str::ascii(trim($principioAtivo)));

        return $alergias->first(function (PacienteAlergia $alergia) use ($esperado, $medicamentoId) {
            return $alergia->medicamento_id === $medicamentoId
                || Str::lower(Str::ascii(trim($alergia->principioAtivo()))) === $esperado;
        });
    }
}
