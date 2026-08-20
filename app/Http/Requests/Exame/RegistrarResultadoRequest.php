<?php

declare(strict_types=1);

namespace App\Http\Requests\Exame;

use App\Models\ExameResultado;
use Illuminate\Foundation\Http\FormRequest;

final class RegistrarResultadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ExameResultado::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'laudo' => ['nullable', 'string', 'max:30000'],
            'conclusao' => ['nullable', 'string', 'max:10000'],
            'itens' => ['nullable', 'array', 'max:100'],
            'itens.*.analito' => ['required_with:itens', 'string', 'max:120'],
            'itens.*.valor' => ['required_with:itens', 'string', 'max:60'],
            'itens.*.unidade' => ['nullable', 'string', 'max:30'],
            'itens.*.referencia_min' => ['nullable', 'numeric'],
            'itens.*.referencia_max' => ['nullable', 'numeric', 'gte:itens.*.referencia_min'],
            'itens.*.referencia_texto' => ['nullable', 'string', 'max:120'],
            'anexos' => ['nullable', 'array', 'max:10'],
            'anexos.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }
}
