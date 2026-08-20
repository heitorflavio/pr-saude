<?php

declare(strict_types=1);

namespace App\Http\Requests\Pulseira;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RF-15: a impressão exige motivo — é o que torna a reimpressão auditável.
 */
final class ImprimirPulseiraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pulseira.imprimir') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['nullable', 'in:PRIMEIRA,REIMPRESSAO,RECLASSIFICACAO,DANIFICADA,OUTRO'],
            'observacao' => ['nullable', 'string', 'max:255'],
        ];
    }
}
