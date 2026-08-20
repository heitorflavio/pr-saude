<?php

declare(strict_types=1);

namespace App\Http\Requests\Medicamento;

use App\Models\Prescricao;
use Illuminate\Foundation\Http\FormRequest;

final class SuspenderPrescricaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $prescricao = $this->route('prescricao');

        return $prescricao instanceof Prescricao
            && ($this->user()?->can('suspender', $prescricao) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['motivo' => ['required', 'string', 'min:5', 'max:2000']];
    }
}
