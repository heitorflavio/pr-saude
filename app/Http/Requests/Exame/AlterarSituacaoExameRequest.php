<?php

declare(strict_types=1);

namespace App\Http\Requests\Exame;

use App\Enums\SituacaoExame;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class AlterarSituacaoExameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('exame.executar') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'situacao' => ['required', new Enum(SituacaoExame::class)],
            'motivo' => ['nullable', 'required_if:situacao,CANCELADO', 'string', 'min:5', 'max:2000'],
        ];
    }
}
