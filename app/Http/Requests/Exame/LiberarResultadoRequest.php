<?php

declare(strict_types=1);

namespace App\Http\Requests\Exame;

use App\Models\ExameResultado;
use Illuminate\Foundation\Http\FormRequest;

final class LiberarResultadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $resultado = $this->route('resultado');

        return $resultado instanceof ExameResultado
            && ($this->user()?->can('liberar', $resultado) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
