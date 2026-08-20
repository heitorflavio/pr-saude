<?php

declare(strict_types=1);

namespace App\Actions\Fila;

use App\Exceptions\FilaInvalidaException;
use App\Models\Atendimento;
use App\Models\FilaItem;
use App\Models\Profissional;
use App\Models\User;
use App\Services\Auditoria\AuditoriaService;
use Illuminate\Support\Facades\DB;

/**
 * Transferência entre filas de profissionais.
 *
 * Ao contrário da atribuição, aqui um item **novo** é criado, apontando para o anterior
 * por `transferido_de_id` — o histórico precisa mostrar que houve remanejamento e por
 * quê. Mas `entrou_em` é **copiado**, não recarimbado: o paciente não volta ao fim da
 * fila porque a instituição decidiu trocar quem o atende.
 *
 * É a mesma lógica da reclassificação (doc §7.5): tempo esperado é do paciente, não do
 * profissional.
 */
final class TransferirFilaAction
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    /**
     * @throws FilaInvalidaException
     */
    public function execute(
        Atendimento $atendimento,
        Profissional $destino,
        User $autor,
        string $justificativa,
    ): FilaItem {
        if ($atendimento->status->ehTerminal()) {
            throw FilaInvalidaException::atendimentoEncerrado();
        }

        if (trim($justificativa) === '') {
            throw FilaInvalidaException::justificativaObrigatoria();
        }

        $origem = $atendimento->filaItemAtivo();

        if ($origem === null) {
            throw FilaInvalidaException::semItemAtivo();
        }

        if ($origem->profissional_id === $destino->user_id) {
            throw FilaInvalidaException::mesmoProfissional();
        }

        return DB::transaction(function () use ($atendimento, $origem, $destino, $autor, $justificativa) {
            $origem->update([
                'situacao' => 'TRANSFERIDO',
                'saiu_em' => now(),
            ]);

            $novo = $atendimento->filaItens()->create([
                'profissional_id' => $destino->user_id,
                'classificacao_risco_id' => $origem->classificacao_risco_id,
                'situacao' => 'AGUARDANDO',
                // O ponto: `entrou_em` COPIADO, não recarimbado.
                'entrou_em' => $origem->entrou_em,
                'transferido_de_id' => $origem->id,
                'justificativa_transferencia' => $justificativa,
                'criado_por' => $autor->id,
            ]);

            $atendimento->profissional_responsavel_id = $destino->user_id;
            $atendimento->save();

            $this->auditoria->registrar(
                acao: 'fila.transferir',
                paciente: $atendimento->paciente,
                atendimento: $atendimento,
                entidade: 'FilaItem',
                entidadeId: $novo->id,
                antes: ['profissional_id' => $origem->profissional_id],
                depois: ['profissional_id' => $destino->user_id],
                justificativa: $justificativa,
                usuario: $autor,
            );

            return $novo;
        });
    }
}
