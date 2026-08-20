<?php

declare(strict_types=1);

namespace App\Http\Requests\Prontuario;

use App\Enums\TipoRegistroClinico;
use App\Models\RegistroClinico;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class RegistrarNotaClinicaRequest extends FormRequest
{
    /**
     * A permissão depende do tipo: a doc §2.3 dá ao técnico a evolução de enfermagem e
     * não a nota médica. Autorizar por "pode escrever no prontuário" apagaria a distinção.
     */
    public function authorize(): bool
    {
        $usuario = $this->user();

        if ($usuario === null) {
            return false;
        }

        $tipo = TipoRegistroClinico::tryFrom((string) $this->input('tipo'));

        if ($tipo === null) {
            // Deixa a mensagem de tipo inválido para a validação, que explica o problema;
            // um 403 aqui diria "sem permissão" para o que é erro de preenchimento.
            return true;
        }

        return $usuario->can('create', [RegistroClinico::class, $tipo]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipo' => [
                'required',
                new Enum(TipoRegistroClinico::class),
                // RN-16: adendo nasce da retificação, com original e motivo.
                Rule::notIn([TipoRegistroClinico::Adendo->value]),
            ],

            // doc §9.2: os quatro componentes do SOAP em colunas separadas.
            'subjetivo' => ['nullable', 'string', 'max:10000'],
            'objetivo' => ['nullable', 'string', 'max:10000'],
            'avaliacao' => ['nullable', 'string', 'max:10000'],
            'plano' => ['nullable', 'string', 'max:10000'],
            'conteudo_livre' => ['nullable', 'string', 'max:20000'],

            // doc §9.6: sigilo é sobre a exibição no portal, não sobre o direito de
            // acesso. Marcar é ato médico e fica auditado.
            'sigiloso' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.required' => 'Escolha o tipo do registro.',
            'tipo.not_in' => 'Adendo não é criado como nota avulsa: use a retificação a partir do registro original.',
        ];
    }

    /**
     * @return array{subjetivo: ?string, objetivo: ?string, avaliacao: ?string, plano: ?string, conteudo_livre: ?string}
     */
    public function conteudo(): array
    {
        return [
            'subjetivo' => $this->input('subjetivo'),
            'objetivo' => $this->input('objetivo'),
            'avaliacao' => $this->input('avaliacao'),
            'plano' => $this->input('plano'),
            'conteudo_livre' => $this->input('conteudo_livre'),
        ];
    }

    public function tipo(): TipoRegistroClinico
    {
        return TipoRegistroClinico::from((string) $this->input('tipo'));
    }
}
