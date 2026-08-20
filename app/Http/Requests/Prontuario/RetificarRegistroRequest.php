<?php

declare(strict_types=1);

namespace App\Http\Requests\Prontuario;

use Illuminate\Foundation\Http\FormRequest;

final class RetificarRegistroRequest extends FormRequest
{
    /** A autorização contextual (quem pode retificar *este* registro) fica na Policy. */
    public function authorize(): bool
    {
        return $this->user()?->can('retificar', $this->route('registro')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // doc §9.3: sem o motivo, o adendo é só mais uma nota — não uma retificação
            // rastreável. O CHECK ck_registro_adendo também o exige, no banco.
            'motivo' => ['required', 'string', 'min:5', 'max:2000'],

            'subjetivo' => ['nullable', 'string', 'max:10000'],
            'objetivo' => ['nullable', 'string', 'max:10000'],
            'avaliacao' => ['nullable', 'string', 'max:10000'],
            'plano' => ['nullable', 'string', 'max:10000'],
            'conteudo_livre' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Informe o motivo da retificação (doc §9.3).',
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
}
