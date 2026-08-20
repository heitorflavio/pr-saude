<?php

declare(strict_types=1);

namespace App\Http\Requests\Paciente;

use App\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * UC-01: validação do cadastro de paciente. **Toda no servidor** -- o cliente apenas
 * aplica máscara e dá feedback visual. Em sistema clínico, validação no cliente que
 * diverja da do servidor é passivo de segurança.
 */
final class CadastrarPacienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('paciente.criar') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $provisorio = $this->boolean('identificacao_provisoria');

        return [
            'nome_completo' => ['required', 'string', 'max:150'],
            'nome_social' => ['nullable', 'string', 'max:150'],

            // A2: sem CPF só quando marcado como não identificado. A4: dígito
            // verificador é regra customizada, não regex.
            'cpf' => [$provisorio ? 'nullable' : 'required', 'string', new Cpf],
            'identificacao_provisoria' => ['boolean'],

            'cns' => ['nullable', 'string', 'size:15'],

            // A5: data no futuro ou idade acima de 130 anos é recusada por domínio.
            'data_nascimento' => [
                'required',
                'date',
                'before_or_equal:today',
                'after_or_equal:'.now()->subYears(130)->toDateString(),
            ],

            'sexo' => ['nullable', 'in:FEMININO,MASCULINO,OUTRO,NAO_INFORMADO'],
            'nome_mae' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'telefone' => ['nullable', 'string', 'max:20'],

            // A3: para menor de idade, os dados do responsável legal são obrigatórios.
            'contato_emergencia_nome' => ['nullable', 'string', 'max:150'],
            'contato_emergencia_telefone' => ['nullable', 'string', 'max:20'],

            'logradouro' => ['nullable', 'string', 'max:180'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:80'],
            'bairro' => ['nullable', 'string', 'max:100'],
            'municipio' => ['nullable', 'string', 'max:100'],
            'uf' => ['nullable', 'string', 'size:2'],
            'cep' => ['nullable', 'string', 'size:8'],
            'observacoes' => ['nullable', 'string'],

            'alergias' => ['array'],
            'alergias.*.substancia' => ['required', 'string', 'max:150'],
            'alergias.*.medicamento_id' => ['nullable', 'exists:medicamento,id'],
            'alergias.*.gravidade' => ['nullable', 'in:LEVE,MODERADA,GRAVE,DESCONHECIDA'],
            'alergias.*.reacao' => ['nullable', 'string', 'max:255'],

            'condicoes' => ['array'],
            'condicoes.*.descricao' => ['required', 'string', 'max:255'],
            'condicoes.*.cid10_codigo' => ['nullable', 'exists:cid10,codigo'],
            'condicoes.*.desde' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $nascimento = $this->date('data_nascimento');

            if ($nascimento === null) {
                return;
            }

            /*
             * A3 do UC-01: menor de idade exige responsável legal.
             *
             * O documento pede também que "a credencial de acesso seja emitida no CPF do
             * responsável". Isso não foi implementado: `users.login` é único, e o
             * responsável frequentemente já é paciente da mesma unidade -- o login
             * colidiria. Além disso o `schema.sql` não tem coluna para o CPF do
             * responsável. Ver docs/DECISOES.md D-24: requer decisão.
             *
             * O que está garantido aqui é a parte inequívoca: não se cadastra menor sem
             * saber quem responde por ele.
             */
            if ($nascimento->age < 18) {
                if (blank($this->input('contato_emergencia_nome'))) {
                    $validator->errors()->add(
                        'contato_emergencia_nome',
                        'Paciente menor de idade exige o nome do responsável legal.'
                    );
                }

                if (blank($this->input('contato_emergencia_telefone'))) {
                    $validator->errors()->add(
                        'contato_emergencia_telefone',
                        'Paciente menor de idade exige o telefone do responsável legal.'
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nome_completo' => 'nome completo',
            'data_nascimento' => 'data de nascimento',
            'nome_mae' => 'nome da mãe',
            'contato_emergencia_nome' => 'nome do responsável ou contato de emergência',
            'contato_emergencia_telefone' => 'telefone do responsável ou contato de emergência',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'data_nascimento.before_or_equal' => 'A data de nascimento não pode estar no futuro.',
            'data_nascimento.after_or_equal' => 'A data de nascimento indica idade acima de 130 anos.',
            'cpf.required' => 'Informe o CPF ou marque o paciente como não identificado.',
        ];
    }
}
