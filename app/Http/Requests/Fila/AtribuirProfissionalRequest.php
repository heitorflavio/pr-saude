<?php

declare(strict_types=1);

namespace App\Http\Requests\Fila;

use Illuminate\Foundation\Http\FormRequest;

final class AtribuirProfissionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('fila.atribuir') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'profissional_id' => ['required', 'exists:profissional,user_id'],
        ];
    }
}
