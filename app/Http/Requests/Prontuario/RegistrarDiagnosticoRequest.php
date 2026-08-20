<?php

declare(strict_types=1);

namespace App\Http\Requests\Prontuario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RegistrarDiagnosticoRequest extends FormRequest
{
    /**
     * RF-46 é ato médico: a doc §2.3 concede a criação de nota médica só ao `medico`, e
     * diagnóstico pertence ao mesmo caso de uso (UC-08).
     */
    public function authorize(): bool
    {
        return $this->user()?->can('prontuario.criar_nota_medica') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cid10_codigo' => ['required', 'string', 'exists:cid10,codigo'],
            'natureza' => ['required', Rule::in(['SUSPEITA', 'DEFINITIVO', 'DIFERENCIAL'])],
            'principal' => ['boolean'],
            'observacao' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cid10_codigo.exists' => 'Código CID-10 não encontrado no catálogo.',
        ];
    }
}
