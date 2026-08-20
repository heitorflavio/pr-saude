<?php

declare(strict_types=1);

namespace App\Http\Requests\Medicamento;

use App\Enums\ViaAdministracao;
use App\Models\Prescricao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class PrescreverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Prescricao::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'observacao' => ['nullable', 'string', 'max:5000'],
            'itens' => ['required', 'array', 'min:1', 'max:30'],
            'itens.*.medicamento_id' => ['required', 'integer', 'exists:medicamento,id'],
            'itens.*.dose' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'itens.*.unidade_dose' => ['required', 'string', 'max:20'],
            'itens.*.via' => ['required', new Enum(ViaAdministracao::class)],
            'itens.*.frequencia_horas' => ['nullable', 'integer', 'min:1', 'max:720', 'required_unless:itens.*.se_necessario,true'],
            'itens.*.duracao_horas' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'itens.*.se_necessario' => ['boolean'],
            'itens.*.diluicao' => ['nullable', 'string', 'max:255'],
            'itens.*.velocidade_infusao' => ['nullable', 'string', 'max:60'],
            'itens.*.observacao' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'itens.required' => 'Inclua ao menos um medicamento.',
            'itens.*.frequencia_horas.required_unless' => 'Informe a frequência ou marque “se necessário”.',
            'itens.*.dose.gt' => 'A dose deve ser maior que zero.',
        ];
    }
}
