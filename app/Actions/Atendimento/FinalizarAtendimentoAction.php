<?php

declare(strict_types=1);

namespace App\Actions\Atendimento;

use App\Enums\StatusAtendimento;
use App\Exceptions\DesfechoObrigatorioException;
use App\Models\Atendimento;
use App\Models\User;

/**
 * RN-14: finalização com desfecho obrigatório.
 *
 * Delega à `AlterarStatusAction` de propósito — se a finalização tivesse caminho próprio
 * de escrita, ela escaparia das garantias de RN-13 e RN-15, e o histórico do encerramento
 * (o momento mais importante do episódio) seria o único sem registro de transição.
 *
 * O que esta Action acrescenta é a validação do desfecho contra o domínio: o `ENUM` da
 * coluna já recusaria valor inválido, mas com mensagem de banco.
 */
final class FinalizarAtendimentoAction
{
    /** Espelha o ENUM `atendimento.desfecho` do schema.sql. */
    public const DESFECHOS = [
        'ALTA',
        'ENCAMINHAMENTO',
        'INTERNACAO',
        'EVASAO',
        'OBITO',
        'TRANSFERENCIA',
    ];

    public function __construct(private readonly AlterarStatusAction $alterarStatus) {}

    /**
     * @throws DesfechoObrigatorioException
     */
    public function execute(
        Atendimento $atendimento,
        string $desfecho,
        User $autor,
        ?string $observacao = null,
    ): Atendimento {
        if (! in_array($desfecho, self::DESFECHOS, strict: true)) {
            throw DesfechoObrigatorioException::paraFinalizar();
        }

        return $this->alterarStatus->execute(
            atendimento: $atendimento,
            novoStatus: StatusAtendimento::Finalizado,
            autor: $autor,
            observacao: $observacao,
            desfecho: $desfecho,
            desfechoObservacao: $observacao,
        );
    }
}
