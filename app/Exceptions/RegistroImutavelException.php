<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * RN-16, RN-17, D-05: `registro_clinico` nao admite UPDATE nem DELETE.
 *
 * A correcao de um registro cria um registro NOVO do tipo ADENDO, com
 * `registro_retificado_id` apontando para o original -- que permanece visivel e
 * marcado. Um prontuario que pode ser editado depois do fato nao serve como prova
 * assistencial nem juridica.
 */
final class RegistroImutavelException extends DominioException
{
    public static function aoAtualizar(): self
    {
        return new self(
            'Registro clinico nao pode ser alterado (RN-16). '
            .'Para corrigir, crie um adendo apontando para o registro original.'
        );
    }

    public static function aoExcluir(): self
    {
        return new self(
            'Registro clinico nao pode ser excluido (RN-17, D-05). '
            .'Nenhum dado clinico e removido do prontuario.'
        );
    }
}
