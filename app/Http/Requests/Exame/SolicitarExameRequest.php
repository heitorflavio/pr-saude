<?php

declare(strict_types=1);

namespace App\Http\Requests\Exame;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SolicitarExameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('exame.solicitar') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'exame_id' => ['required', 'integer', 'exists:exame,id'],
            'carater' => ['required', Rule::in(['ROTINA', 'URGENTE'])],
            'indicacao_clinica' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
