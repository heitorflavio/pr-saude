<?php

declare(strict_types=1);

namespace App\Http\Requests\Atendimento;

use App\Actions\Atendimento\FinalizarAtendimentoAction;
use App\Enums\StatusAtendimento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A validação aqui é de **forma**, não de regra: se a transição é legal quem decide é
 * `StatusAtendimento::podeTransitarPara()`, dentro da Action. Duplicar a máquina de
 * estados no FormRequest criaria duas fontes de verdade que divergiriam na primeira
 * mudança.
 */
final class AlterarStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        // A autorização contextual (RN-12) é da AtendimentoPolicy, chamada no controller
        // com o destino em mãos -- ela depende do par (origem, destino) no caso do
        // laboratório.
        return $this->user()?->can('atendimento.alterar_status') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(StatusAtendimento::class)],
            'observacao' => ['nullable', 'string', 'max:2000'],
            'desfecho' => ['nullable', Rule::in(FinalizarAtendimentoAction::DESFECHOS)],
        ];
    }
}
