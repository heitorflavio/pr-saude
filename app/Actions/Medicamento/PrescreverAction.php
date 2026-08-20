<?php

declare(strict_types=1);

namespace App\Actions\Medicamento;

use App\Events\PrescricaoCriada;
use App\Exceptions\AdministracaoInvalidaException;
use App\Models\Atendimento;
use App\Models\Medicamento;
use App\Models\Prescricao;
use App\Models\User;
use App\Services\Auditoria\AuditoriaService;
use App\Services\Medicamento\AprazamentoService;
use Illuminate\Support\Facades\DB;

/** UC-09 / RF-55 e RF-56 — ordem médica e sua agenda são criadas atomicamente. */
final class PrescreverAction
{
    public function __construct(
        private readonly AprazamentoService $aprazamento,
        private readonly AuditoriaService $auditoria,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $itens
     */
    public function execute(Atendimento $atendimento, User $autor, array $itens, ?string $observacao = null): Prescricao
    {
        if ($atendimento->status->ehTerminal()) {
            throw AdministracaoInvalidaException::atendimentoEncerrado();
        }

        $medico = $autor->profissional;
        // RN-18: permissão estática sozinha não transforma uma conta em médico habilitado.
        if ($medico === null || ! $medico->ativo || $medico->categoria !== 'MEDICO'
            || blank($medico->conselho_tipo) || blank($medico->conselho_numero)) {
            throw AdministracaoInvalidaException::prescritorInvalido();
        }

        if ($itens === []) {
            throw new AdministracaoInvalidaException('Inclua ao menos um medicamento na prescrição.');
        }

        return DB::transaction(function () use ($atendimento, $autor, $medico, $itens, $observacao) {
            $agora = now();
            $prescricao = Prescricao::create([
                'atendimento_id' => $atendimento->id,
                'prescrito_por' => $medico->user_id,
                'status' => 'VIGENTE',
                'vigencia_inicio' => $agora,
                'vigencia_fim' => null,
                'observacao' => filled($observacao) ? trim((string) $observacao) : null,
                // RN-29: evento clínico usa relógio do servidor.
                'criado_em' => $agora,
            ]);

            $maiorDuracao = null;

            foreach ($itens as $dados) {
                $medicamento = Medicamento::query()->where('ativo', true)->findOrFail((int) $dados['medicamento_id']);
                $sos = (bool) ($dados['se_necessario'] ?? false);
                $duracao = isset($dados['duracao_horas']) ? (int) $dados['duracao_horas'] : null;

                $item = $prescricao->itens()->create([
                    'medicamento_id' => $medicamento->id,
                    'dose' => $dados['dose'],
                    'unidade_dose' => $dados['unidade_dose'],
                    'via' => $dados['via'],
                    'frequencia_horas' => $sos ? null : $dados['frequencia_horas'],
                    'duracao_horas' => $duracao,
                    'se_necessario' => $sos,
                    'diluicao' => $dados['diluicao'] ?? null,
                    'velocidade_infusao' => $dados['velocidade_infusao'] ?? null,
                    'observacao' => $dados['observacao'] ?? null,
                    'status' => 'VIGENTE',
                ]);

                $this->aprazamento->gerar($item, $agora);
                $maiorDuracao = max($maiorDuracao ?? 0, $duracao ?? 24);
            }

            if ($maiorDuracao !== null) {
                $prescricao->vigencia_fim = $agora->copy()->addHours($maiorDuracao);
                $prescricao->save();
            }

            $this->auditoria->registrar(
                acao: 'prescricao.criar',
                paciente: $atendimento->paciente,
                atendimento: $atendimento,
                entidade: 'Prescricao',
                entidadeId: $prescricao->id,
                depois: ['itens' => count($itens), 'status' => 'VIGENTE'],
                usuario: $autor,
            );

            PrescricaoCriada::dispatch($prescricao);

            return $prescricao->load('itens.aprazamentos');
        });
    }
}
