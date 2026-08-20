<?php

declare(strict_types=1);

namespace App\Http\Requests\Medicamento;

use App\Enums\ViaAdministracao;
use App\Models\AdministracaoMedicamento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class RegistrarAdministracaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AdministracaoMedicamento::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'resultado' => ['required', Rule::in(['ADMINISTRADA', 'NAO_ADMINISTRADA'])],
            'dose_administrada' => ['nullable', 'required_if:resultado,ADMINISTRADA', 'numeric', 'gt:0'],
            'via' => ['nullable', 'required_if:resultado,ADMINISTRADA', new Enum(ViaAdministracao::class)],
            'motivo_nao_administracao' => [
                'nullable', 'required_if:resultado,NAO_ADMINISTRADA',
                Rule::in(['RECUSA_PACIENTE', 'INDISPONIVEL', 'JEJUM', 'SUSPENSA_MEDICO', 'INTERCORRENCIA', 'ACESSO_INDISPONIVEL', 'OUTRO']),
            ],
            'conferente_id' => ['nullable', 'integer', 'exists:profissional,user_id'],
            'justificativa_alergia' => ['nullable', 'string', 'min:5', 'max:2000'],
            'observacao' => ['nullable', 'string', 'max:5000'],
            // Os campos 6 e 7 dos nove certos são conferências explícitas, validadas no
            // servidor e incorporadas ao registro em texto por falta de colunas próprias.
            'lote' => ['nullable', 'string', 'max:80'],
            'validade' => ['nullable', 'date_format:m/Y'],
            'validade_conferida' => ['accepted_if:resultado,ADMINISTRADA'],
            'orientacao_prestada' => ['accepted_if:resultado,ADMINISTRADA'],
        ];
    }

    public function observacaoRegistrada(): ?string
    {
        $partes = array_filter([
            filled($this->input('lote')) ? 'Lote conferido: '.trim((string) $this->input('lote')) : null,
            filled($this->input('validade')) ? 'Validade conferida: '.$this->input('validade') : null,
            $this->boolean('orientacao_prestada') ? 'Orientação ao paciente registrada.' : null,
            filled($this->input('observacao')) ? trim((string) $this->input('observacao')) : null,
        ]);

        return $partes === [] ? null : implode("\n", $partes);
    }
}
