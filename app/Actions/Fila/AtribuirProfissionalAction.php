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
 * UC-05: coloca o atendimento na fila de um profissional.
 *
 * Quando já existe item na fila geral, ele é **reaproveitado** — só ganha dono. Criar um
 * item novo reiniciaria `entrou_em` e o paciente perderia o tempo já esperado, que é
 * exatamente o que a doc §7.5 proíbe na reclassificação e vale igual aqui: atribuir não
 * é penalidade de posição.
 */
final class AtribuirProfissionalAction
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    /**
     * @throws FilaInvalidaException
     */
    public function execute(
        Atendimento $atendimento,
        Profissional $profissional,
        User $autor,
    ): FilaItem {
        if ($atendimento->status->ehTerminal()) {
            throw FilaInvalidaException::atendimentoEncerrado();
        }

        if ($atendimento->classificacao_risco_id === null) {
            throw FilaInvalidaException::semClassificacao();
        }

        return DB::transaction(function () use ($atendimento, $profissional, $autor) {
            $item = $atendimento->filaItemAtivo();

            if ($item !== null) {
                $antes = $item->getAttributes();

                // `entrou_em` intocado: só o dono muda.
                $item->profissional_id = $profissional->user_id;
                $item->classificacao_risco_id = $atendimento->classificacao_risco_id;
                $item->save();
            } else {
                $antes = null;

                $item = $atendimento->filaItens()->create([
                    'profissional_id' => $profissional->user_id,
                    'classificacao_risco_id' => $atendimento->classificacao_risco_id,
                    'situacao' => 'AGUARDANDO',
                    // RN-29: hora do servidor.
                    'entrou_em' => now(),
                    'criado_por' => $autor->id,
                ]);
            }

            // O responsável pelo atendimento acompanha a atribuição -- é ele quem RN-12
            // reconhece para alterar o status daqui em diante.
            $atendimento->profissional_responsavel_id = $profissional->user_id;
            $atendimento->save();

            $this->auditoria->registrar(
                acao: 'fila.atribuir',
                paciente: $atendimento->paciente,
                atendimento: $atendimento,
                entidade: 'FilaItem',
                entidadeId: $item->id,
                antes: $antes,
                depois: $item->getAttributes(),
                usuario: $autor,
            );

            return $item;
        });
    }
}
