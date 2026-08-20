<?php

declare(strict_types=1);

namespace App\Http\Requests\Triagem;

use Illuminate\Foundation\Http\FormRequest;

final class ReclassificarRiscoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('triagem.reclassificar') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'classificacao_risco_id' => ['required', 'exists:classificacao_risco,id'],
            // Obrigatória: é ela que permite reconstruir o raciocínio clínico numa
            // auditoria de evento adverso (doc §7.5).
            'justificativa' => ['required', 'string', 'min:5', 'max:2000'],

            'sinais_vitais' => ['array'],
            'sinais_vitais.pressao_sistolica' => ['nullable', 'integer', 'between:0,300'],
            'sinais_vitais.pressao_diastolica' => ['nullable', 'integer', 'between:0,200'],
            'sinais_vitais.frequencia_cardiaca' => ['nullable', 'integer', 'between:0,300'],
            'sinais_vitais.frequencia_respiratoria' => ['nullable', 'integer', 'between:0,100'],
            'sinais_vitais.saturacao_o2' => ['nullable', 'numeric', 'between:0,100'],
            'sinais_vitais.temperatura' => ['nullable', 'numeric', 'between:25,45'],
            'sinais_vitais.glicemia' => ['nullable', 'numeric', 'between:0,1000'],
            'sinais_vitais.escala_dor' => ['nullable', 'integer', 'between:0,10'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'justificativa.required' => 'Informe o motivo da reclassificação (doc §7.5).',
        ];
    }
}
