<?php

declare(strict_types=1);

namespace App\Actions\Atendimento;

use App\Enums\StatusAtendimento;
use App\Events\AtendimentoAberto;
use App\Exceptions\AtendimentoAtivoExistenteException;
use App\Models\Atendimento;
use App\Models\Paciente;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Atendimento\GeradorNumeroAtendimentoService;
use App\Services\Auditoria\AuditoriaService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Abertura do atendimento (doc §6.2, primeira linha da tabela de transições).
 *
 * Guarda: nenhum atendimento ativo do paciente naquela unidade (RN-07). A verificação
 * **não** é o controle — o controle é o índice único `uk_atendimento_ativo` sobre a
 * coluna gerada `ativo_key`. A consulta prévia existe só para dar uma mensagem melhor
 * no caso comum; quem realmente decide é o banco, e é por isso que duas recepcionistas
 * simultâneas não conseguem duplicar.
 */
final class AbrirAtendimentoAction
{
    /**
     * Tentativas de numeração. Só entra em ação quando duas aberturas na mesma unidade
     * escapam do `lockForUpdate` e colidem no `uk_atendimento_numero`.
     */
    private const TENTATIVAS = 3;

    public function __construct(
        private readonly GeradorNumeroAtendimentoService $numeros,
        private readonly AuditoriaService $auditoria,
    ) {}

    /**
     * @param  array<int, int>  $queixaIds
     *
     * @throws AtendimentoAtivoExistenteException
     */
    public function execute(
        Paciente $paciente,
        Unidade $unidade,
        User $autor,
        string $origem = 'ESPONTANEA',
        ?string $sintomasEntrada = null,
        array $queixaIds = [],
    ): Atendimento {
        $ativo = $paciente->atendimentoAtivo($unidade->id);

        if ($ativo !== null) {
            throw new AtendimentoAtivoExistenteException($ativo);
        }

        for ($tentativa = 1; $tentativa <= self::TENTATIVAS; $tentativa++) {
            try {
                return $this->abrir($paciente, $unidade, $autor, $origem, $sintomasEntrada, $queixaIds);
            } catch (QueryException $e) {
                // RN-07: o índice único da coluna gerada disparou -- outra recepcionista
                // abriu o atendimento entre a nossa consulta e a nossa escrita. Não é
                // erro de infraestrutura, é a regra funcionando.
                if ($this->violou($e, 'uk_atendimento_ativo')) {
                    throw new AtendimentoAtivoExistenteException(
                        $paciente->fresh()?->atendimentoAtivo($unidade->id)
                    );
                }

                // Colisão de numeração: tenta de novo com o próximo sequencial.
                if ($this->violou($e, 'uk_atendimento_numero') && $tentativa < self::TENTATIVAS) {
                    continue;
                }

                throw $e;
            }
        }

        throw new AtendimentoAtivoExistenteException;
    }

    /**
     * @param  array<int, int>  $queixaIds
     */
    private function abrir(
        Paciente $paciente,
        Unidade $unidade,
        User $autor,
        string $origem,
        ?string $sintomasEntrada,
        array $queixaIds,
    ): Atendimento {
        return DB::transaction(function () use ($paciente, $unidade, $autor, $origem, $sintomasEntrada, $queixaIds) {
            $atendimento = Atendimento::create([
                'uuid' => (string) Str::uuid(),
                'numero' => $this->numeros->proximo($unidade),
                'paciente_id' => $paciente->user_id,
                'unidade_id' => $unidade->id,
                'status' => StatusAtendimento::AguardandoTriagem,
                'origem' => $origem,
                'sintomas_entrada' => $sintomasEntrada,
                // RN-29: hora do servidor, nunca do cliente.
                'admitido_em' => now(),
                'aberto_por' => $autor->id,
            ]);

            foreach ($queixaIds as $queixaId) {
                $atendimento->sintomas()->create(['queixa_id' => $queixaId]);
            }

            /*
             * RN-15: o histórico começa aqui, com `status_anterior` nulo. Sem esta
             * primeira linha, a linha do tempo do atendimento (RF-22) não teria início
             * e o cálculo de permanência do primeiro status não teria de onde partir.
             */
            $atendimento->statusHistorico()->create([
                'status_anterior' => null,
                'status_novo' => StatusAtendimento::AguardandoTriagem->value,
                'alterado_por' => $autor->id,
                'observacao' => 'Abertura do atendimento.',
                'permanencia_segundos' => null,
                'criado_em' => now(),
            ]);

            $this->auditoria->registrar(
                acao: 'atendimento.abrir',
                paciente: $paciente,
                atendimento: $atendimento,
                entidade: 'Atendimento',
                entidadeId: $atendimento->id,
                depois: $atendimento->getAttributes(),
                usuario: $autor,
            );

            AtendimentoAberto::dispatch($atendimento);

            return $atendimento;
        });
    }

    private function violou(QueryException $e, string $indice): bool
    {
        return str_contains($e->getMessage(), $indice);
    }
}
