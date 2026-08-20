<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Paciente;

/**
 * A1 do UC-01: CPF já cadastrado.
 *
 * Não é erro de validação genérico -- é um desvio de fluxo com uma resposta útil. O
 * sistema exibe o cadastro existente e oferece "abrir novo atendimento" em vez de
 * duplicar. Por isso a exceção carrega o paciente encontrado: duplicar cadastro num
 * pronto-socorro significa dois prontuários para a mesma pessoa, e o alérgico cujo
 * registro de alergia ficou no outro cadastro.
 */
final class PacienteJaCadastradoException extends DominioException
{
    public function __construct(public readonly Paciente $paciente)
    {
        parent::__construct(
            "Já existe cadastro para o CPF informado: {$paciente->nomeExibicao()}."
        );
    }
}
