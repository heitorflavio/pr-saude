<?php

declare(strict_types=1);

namespace App\Http\Requests\Paciente;

use App\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;

/**
 * RN-30: vinculação do CPF real a um cadastro provisório.
 */
final class RegularizarIdentificacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('paciente.atualizar') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cpf' => ['required', 'string', new Cpf],
            // O nome frequentemente muda na regularização: quem chegou como
            // "Não identificado 042" ganha o nome do documento.
            'nome_completo' => ['nullable', 'string', 'max:150'],
            'cns' => ['nullable', 'string', 'size:15'],
        ];
    }
}
