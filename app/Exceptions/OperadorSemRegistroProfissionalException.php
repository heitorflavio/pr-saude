<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Ato assistencial executado por usuário sem registro em `profissional`.
 *
 * O `schema.sql` é explícito: `pulseira_impressao.impressa_por` é `NOT NULL` com FK
 * para `profissional (user_id)`. Quem imprime uma pulseira precisa ser um profissional
 * identificável — a pulseira é o identificador que acompanha o paciente, e "quem
 * imprimiu" é parte da rastreabilidade.
 *
 * O caso que faz isso acontecer na prática: o administrador do sistema. Ele tem a
 * permission `pulseira.imprimir` pela matriz da doc §2.3, mas pode não ter registro
 * profissional nenhum — é uma conta de TI. Sem esta exceção, a tentativa morria numa
 * violação de constraint do MySQL, que não diz nada a quem está na recepção.
 */
final class OperadorSemRegistroProfissionalException extends DominioException
{
    public static function paraAcao(string $acao): self
    {
        return new self(
            "Esta ação ({$acao}) precisa ser executada por um profissional cadastrado. "
            .'A conta em uso não possui registro profissional vinculado.'
        );
    }
}
