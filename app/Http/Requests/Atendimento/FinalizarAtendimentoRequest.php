<?php

declare(strict_types=1);

namespace App\Http\Requests\Atendimento;

use App\Actions\Atendimento\FinalizarAtendimentoAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class FinalizarAtendimentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('atendimento.alterar_status') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // RN-14: obrigatório, não opcional.
            'desfecho' => ['required', Rule::in(FinalizarAtendimentoAction::DESFECHOS)],
            'observacao' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'desfecho.required' => 'Informe o desfecho do atendimento (RN-14).',
        ];
    }
}
