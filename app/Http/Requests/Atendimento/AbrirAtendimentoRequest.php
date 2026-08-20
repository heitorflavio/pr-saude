<?php

declare(strict_types=1);

namespace App\Http\Requests\Atendimento;

use Illuminate\Foundation\Http\FormRequest;

final class AbrirAtendimentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('atendimento.abrir') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'unidade_id' => ['required', 'exists:unidade,id'],
            'origem' => ['nullable', 'in:ESPONTANEA,SAMU,ENCAMINHADO,TRANSFERENCIA'],
            'sintomas_entrada' => ['nullable', 'string'],
            'queixas' => ['array'],
            'queixas.*' => ['exists:queixa,id'],
        ];
    }
}
