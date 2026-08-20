<?php

declare(strict_types=1);

namespace App\Http\Requests\Triagem;

use Illuminate\Foundation\Http\FormRequest;

/**
 * As faixas dos sinais vitais NÃO são validadas aqui como regra de negócio — quem as
 * garante são os `CHECK` do banco (`ck_sinal_dor`, `ck_sinal_spo2`, `ck_sinal_temp`).
 * As regras abaixo existem para dar mensagem em pt-BR antes de o banco recusar; se as
 * duas divergirem algum dia, quem vale é o banco.
 */
final class RealizarTriagemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('triagem.classificar') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'classificacao_risco_id' => ['required', 'exists:classificacao_risco,id'],
            'queixa_principal' => ['required', 'string', 'max:2000'],
            'justificativa_classificacao' => ['nullable', 'string', 'max:2000'],

            'sinais_vitais' => ['array'],
            'sinais_vitais.pressao_sistolica' => ['nullable', 'integer', 'between:0,300'],
            'sinais_vitais.pressao_diastolica' => ['nullable', 'integer', 'between:0,200'],
            'sinais_vitais.frequencia_cardiaca' => ['nullable', 'integer', 'between:0,300'],
            'sinais_vitais.frequencia_respiratoria' => ['nullable', 'integer', 'between:0,100'],
            'sinais_vitais.saturacao_o2' => ['nullable', 'numeric', 'between:0,100'],
            'sinais_vitais.temperatura' => ['nullable', 'numeric', 'between:25,45'],
            'sinais_vitais.glicemia' => ['nullable', 'numeric', 'between:0,1000'],
            'sinais_vitais.peso_kg' => ['nullable', 'numeric', 'between:0,500'],
            'sinais_vitais.altura_cm' => ['nullable', 'integer', 'between:0,300'],
            'sinais_vitais.escala_dor' => ['nullable', 'integer', 'between:0,10'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sinais_vitais.temperatura.between' => 'A temperatura precisa estar entre 25 e 45 °C.',
            'sinais_vitais.saturacao_o2.between' => 'A saturação de O₂ precisa estar entre 0 e 100%.',
            'sinais_vitais.escala_dor.between' => 'A escala de dor vai de 0 a 10.',
        ];
    }
}
