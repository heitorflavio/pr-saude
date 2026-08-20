<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Atendimento\AbrirAtendimentoAction;
use App\Actions\Atendimento\AlterarStatusAction;
use App\Actions\Fila\AtribuirProfissionalAction;
use App\Actions\Paciente\CadastrarPacienteAction;
use App\Actions\Triagem\RealizarTriagemAction;
use App\Enums\StatusAtendimento;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\ProfissionalDisponibilidade;
use App\Models\Unidade;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/** Ambiente navegável de demonstração pedido na Fase 13. */
final class DemonstracaoSeeder extends Seeder
{
    private const SENHA = 'password';

    public function run(): void
    {
        $unidade = Unidade::query()->updateOrCreate(
            ['cnes' => '0000001'],
            ['nome' => 'Pronto Atendimento Central', 'fuso_horario' => 'America/Sao_Paulo', 'ativo' => true],
        );
        $equipe = $this->equipe($unidade);

        // Torna o seeder seguro para uma segunda execução manual.
        if (Paciente::query()->where('observacoes', 'Paciente de demonstração.')->count() >= 30) {
            $this->command?->warn('Dados de demonstração já existem; nenhuma duplicação foi criada.');

            return;
        }

        $pacientes = $this->pacientes($equipe['recepcao']->user);
        $this->atendimentos($pacientes, $unidade, $equipe);

        $this->command?->info('Demonstração: 1 unidade, 8 profissionais, 30 pacientes e 15 atendimentos.');
        $this->command?->warn('Senha de todas as contas demo: ' . self::SENHA);
    }

    /** @return array<string, Profissional> */
    private function equipe(Unidade $unidade): array
    {
        $definicoes = [
            'recepcao' => ['recepcao.demo', 'Carla Recepção', 'RECEPCAO', null, null],
            'enfermeiro_triagem' => ['triagem.demo', 'Bruna Triagem', 'ENFERMEIRO', 'COREN', '100001'],
            'enfermeiro_assistencial' => ['enfermagem.demo', 'Diego Enfermagem', 'ENFERMEIRO', 'COREN', '100002'],
            'tecnico_enfermagem' => ['tecnico.demo', 'Elisa Técnica', 'TECNICO_ENFERMAGEM', 'COREN', '200001'],
            'medico' => ['medico.demo', 'Dr. Marcos Lima', 'MEDICO', 'CRM', '300001'],
            'laboratorio' => ['laboratorio.demo', 'Fernanda Laboratório', 'LABORATORIO', 'CRBM', '400001'],
            'admin' => [(string) env('ADMIN_LOGIN', 'admin'), 'Administrador do sistema', 'ADMIN', null, null],
            'auditor' => ['auditor.demo', 'Rafael Auditor', 'ADMIN', null, null],
        ];

        $equipe = [];
        foreach ($definicoes as $role => [$login, $nome, $categoria, $conselho, $numero]) {
            $usuario = User::query()->updateOrCreate(['login' => $login], [
                'name' => $nome,
                'email' => "{$login}@prsaude.com",
                'password' => self::SENHA,
                'tipo' => $role === 'admin' ? 'ADMIN' : 'PROFISSIONAL',
                'senha_provisoria' => false,
                'senha_alterada_em' => now(),
                'ativo' => true,
                'deleted_at' => null,
            ]);
            $usuario->syncRoles([$role]);

            $equipe[$role] = Profissional::withTrashed()->updateOrCreate(['user_id' => $usuario->id], [
                'unidade_id' => $unidade->id,
                'nome_completo' => $nome,
                'matricula' => 'DEMO-' . str_pad((string) (count($equipe) + 1), 2, '0', STR_PAD_LEFT),
                'categoria' => $categoria,
                'conselho_tipo' => $conselho,
                'conselho_numero' => $numero,
                'conselho_uf' => $conselho ? 'PR' : null,
                'especialidade' => $categoria === 'MEDICO' ? 'Clínica médica' : null,
                'capacidade_fila' => 20,
                'ativo' => true,
                'deleted_at' => null,
            ]);

            ProfissionalDisponibilidade::query()->updateOrCreate(
                ['profissional_id' => $usuario->id, 'fim_em' => null],
                ['situacao' => 'DISPONIVEL', 'inicio_em' => now()->startOfDay(), 'observacao' => 'Plantão de demonstração.'],
            );
        }

        return $equipe;
    }

    /** @return array<int, Paciente> */
    private function pacientes(User $recepcao): array
    {
        $nomes = [
            'Ana Paula Ribeiro',
            'Bruno Martins',
            'Camila Souza',
            'Daniel Oliveira',
            'Eduarda Santos',
            'Felipe Costa',
            'Gabriela Almeida',
            'Henrique Rocha',
            'Isabela Ferreira',
            'João Pedro Lima',
            'Karen Nascimento',
            'Lucas Barbosa',
            'Mariana Cardoso',
            'Nicolas Pereira',
            'Olívia Gomes',
            'Paulo Henrique Silva',
            'Queila Moreira',
            'Renato Alves',
            'Sofia Carvalho',
            'Tiago Monteiro',
            'Úrsula Freitas',
            'Victor Hugo Araújo',
            'Wagner Mendes',
            'Yasmin Fernandes',
            'Zilda Correia',
            'Alice Moraes',
            'Bento Teixeira',
            'Cecília Castro',
            'Davi Ramos',
            'Estela Pires',
        ];
        $cadastrar = app(CadastrarPacienteAction::class);
        $pacientes = [];

        foreach ($nomes as $indice => $nome) {
            $pacientes[] = $cadastrar->execute([
                'nome_completo' => $nome,
                'cpf' => $this->cpf(310000000 + $indice),
                'data_nascimento' => CarbonImmutable::parse('1950-01-15')->addMonths($indice * 11)->toDateString(),
                'sexo' => $indice % 2 === 0 ? 'FEMININO' : 'MASCULINO',
                'nome_mae' => 'Responsável de ' . $nome,
                'telefone' => '4199' . str_pad((string) $indice, 7, '0', STR_PAD_LEFT),
                'municipio' => 'Curitiba',
                'uf' => 'PR',
                'observacoes' => 'Paciente de demonstração.',
                'alergias' => $indice % 7 === 0 ? [[
                    'substancia' => 'Dipirona sódica',
                    'gravidade' => 'GRAVE',
                    'reacao' => 'Urticária e falta de ar.',
                ]] : [],
            ], $recepcao);
        }

        return $pacientes;
    }

    /** @param array<int, Paciente> $pacientes @param array<string, Profissional> $equipe */
    private function atendimentos(array $pacientes, Unidade $unidade, array $equipe): void
    {
        $abrir = app(AbrirAtendimentoAction::class);
        $triar = app(RealizarTriagemAction::class);
        $atribuir = app(AtribuirProfissionalAction::class);
        $alterar = app(AlterarStatusAction::class);
        $relogioOriginal = CarbonImmutable::getTestNow();
        $base = CarbonImmutable::now()->subHours(5)->startOfMinute();

        try {
            foreach (array_slice($pacientes, 0, 15) as $indice => $paciente) {
                CarbonImmutable::setTestNow($base->addMinutes($indice * 12));
                $atendimento = $abrir->execute(
                    $paciente,
                    $unidade,
                    $equipe['recepcao']->user,
                    sintomasEntrada: ['Dor abdominal', 'Febre e mal-estar', 'Tontura', 'Queda da própria altura'][$indice % 4],
                );

                if ($indice < 3) {
                    continue;
                }

                CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(4));
                $triar->execute(
                    $atendimento,
                    2 + ($indice % 4),
                    $equipe['enfermeiro_triagem']->user,
                    'Queixa principal registrada para demonstração.',
                    'Classificação conforme protocolo institucional.',
                    ['temperatura' => 36.5 + ($indice % 4) / 10, 'frequencia_cardiaca' => 72 + $indice, 'saturacao_o2' => 96],
                );

                if ($indice < 5) {
                    continue;
                }

                CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(6));
                $atribuir->execute($atendimento->fresh(), $equipe['medico'], $equipe['enfermeiro_triagem']->user);
                $atendimento = $alterar->execute($atendimento->fresh(), StatusAtendimento::EmAtendimento, $equipe['medico']->user);

                $destinos = [
                    7 => [StatusAtendimento::AguardandoExame],
                    8 => [StatusAtendimento::AguardandoExame],
                    9 => [StatusAtendimento::AguardandoExame, StatusAtendimento::EmExame],
                    10 => [StatusAtendimento::AguardandoExame, StatusAtendimento::EmExame],
                    11 => [StatusAtendimento::AguardandoMedicacao],
                    12 => [StatusAtendimento::AguardandoMedicacao],
                    13 => [StatusAtendimento::EmObservacao],
                    14 => [StatusAtendimento::Finalizado],
                ];

                foreach ($destinos[$indice] ?? [] as $destino) {
                    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(5));
                    $atendimento = $alterar->execute(
                        $atendimento->fresh(),
                        $destino,
                        $equipe['medico']->user,
                        observacao: 'Evolução de demonstração.',
                        desfecho: $destino === StatusAtendimento::Finalizado ? 'ALTA' : null,
                    );
                }
            }
        } finally {
            CarbonImmutable::setTestNow($relogioOriginal);
        }
    }

    private function cpf(int $base): string
    {
        $nove = str_pad((string) $base, 9, '0', STR_PAD_LEFT);
        $digito = function (string $parcial, int $peso): int {
            $soma = 0;
            foreach (str_split($parcial) as $algarismo) {
                $soma += (int) $algarismo * $peso--;
            }
            $resto = ($soma * 10) % 11;

            return $resto === 10 ? 0 : $resto;
        };
        $dez = $nove . $digito($nove, 10);

        return $dez . $digito($dez, 11);
    }
}
