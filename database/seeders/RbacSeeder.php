<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Matriz de permissões da doc §2.3, célula por célula.
 *
 * Esta é a camada ESTÁTICA da autorização: responde "este papel pode, em princípio,
 * fazer isso?". Ela é dado, e o administrador precisa poder ajustá-la sem deploy.
 *
 * As regras que realmente protegem o paciente são CONTEXTUAIS e vivem nas Policies:
 * RN-12 (só o responsável altera o status deste atendimento), RN-28 (acesso sem vínculo
 * exige justificativa), RN-26 (o paciente acessa só os próprios dados). Permission
 * sozinha nunca basta para dado clínico.
 *
 * Duas ausências deliberadas nesta matriz:
 *
 * 1. Não existe role `paciente`. RN-27: o guard `paciente` fica sem nenhuma role e sem
 *    nenhuma permission, de propósito -- qualquer can() nele nega por construção. As
 *    células "R (só o seu)" da doc §2.3 são garantidas por ausência de rota de escrita
 *    e por global scope, não por permissão.
 *
 * 2. `prontuario.quebra_sigilo` não é concedida ao `admin`. O Gate::before libera o
 *    admin para tudo, menos para ela -- quebra de sigilo permanece auditada mesmo para
 *    administrador, senão o controle de RN-28 teria um buraco do tamanho de uma conta.
 */
class RbacSeeder extends Seeder
{
    private const GUARD = 'web';

    // Apelidos das colunas da matriz, para a tabela abaixo caber na tela.
    private const REC = 'recepcao';

    private const ENF_T = 'enfermeiro_triagem';

    private const ENF_A = 'enfermeiro_assistencial';

    private const TEC = 'tecnico_enfermagem';

    private const MED = 'medico';

    private const LAB = 'laboratorio';

    private const ADM = 'admin';

    private const AUD = 'auditor';

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->roles() as $role) {
            Role::findOrCreate($role, self::GUARD);
        }

        foreach ($this->matriz() as $permissao => $roles) {
            Permission::findOrCreate($permissao, self::GUARD);

            foreach ($roles as $role) {
                Role::findByName($role, self::GUARD)->givePermissionTo($permissao);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return array<int, string> As 8 roles da doc §2.1. */
    public function roles(): array
    {
        return [
            self::REC, self::ENF_T, self::ENF_A, self::TEC,
            self::MED, self::LAB, self::ADM, self::AUD,
        ];
    }

    /**
     * A matriz da doc §2.3 transcrita. Chave = permissão, valor = roles que a possuem.
     *
     * @return array<string, array<int, string>>
     */
    public function matriz(): array
    {
        return [
            // Cadastro de paciente ......................... C R U | R | R | R | R U | R | C R U D
            'paciente.criar' => [self::REC, self::ADM],
            'paciente.ler' => [self::REC, self::ENF_T, self::ENF_A, self::TEC, self::MED, self::LAB, self::ADM],
            'paciente.atualizar' => [self::REC, self::MED, self::ADM],
            'paciente.excluir' => [self::ADM],

            // Reset de senha do paciente ................... U | — | — | — | — | — | U
            'paciente.resetar_senha' => [self::REC, self::ADM],

            // Impressão de pulseira ........................ C | C | C | — | C | — | C
            'pulseira.imprimir' => [self::REC, self::ENF_T, self::ENF_A, self::MED, self::ADM],

            // Abertura de atendimento ...................... C | C | — | — | C | — | C
            'atendimento.abrir' => [self::REC, self::ENF_T, self::MED, self::ADM],

            // Triagem / classificação de risco ............. — | C R U | R | R | R U | — | R
            'triagem.classificar' => [self::ENF_T],
            'triagem.ler' => [self::ENF_T, self::ENF_A, self::TEC, self::MED, self::ADM],
            'triagem.atualizar' => [self::ENF_T, self::MED],

            // Reclassificação de risco ..................... — | C | C | — | C | — | R
            'triagem.reclassificar' => [self::ENF_T, self::ENF_A, self::MED],

            // Fila — visualizar todas ...................... R | R | R | R | R | — | R
            'fila.ler' => [self::REC, self::ENF_T, self::ENF_A, self::TEC, self::MED, self::ADM],

            // Fila — atribuir / remanejar .................. U | U | — | — | U | — | U
            'fila.atribuir' => [self::REC, self::ENF_T, self::MED, self::ADM],

            // Status do atendimento ........................ R | U | U | U | U | U¹ | R
            // ¹ O laboratório só pode as transições de exame -- restrição contextual,
            //   verificada na AtendimentoPolicy, não expressável como permissão.
            'atendimento.ler_status' => [self::REC, self::ENF_T, self::ENF_A, self::TEC, self::MED, self::LAB, self::ADM],
            'atendimento.alterar_status' => [self::ENF_T, self::ENF_A, self::TEC, self::MED, self::LAB],

            // Prontuário — nota médica ..................... — | — | R | — | C R | — | R
            'prontuario.criar_nota_medica' => [self::MED],
            'prontuario.ler_nota_medica' => [self::ENF_A, self::MED, self::ADM],

            // Prontuário — evolução de enfermagem .......... — | C R | C R | C R | R | — | R
            'prontuario.criar_evolucao_enfermagem' => [self::ENF_T, self::ENF_A, self::TEC],
            'prontuario.ler_evolucao_enfermagem' => [self::ENF_T, self::ENF_A, self::TEC, self::MED, self::ADM],

            // Prontuário — retificação ..................... — | U² | U² | — | U² | — | R
            // ² Retificação não sobrescreve: cria adendo apontando para o retificado.
            'prontuario.retificar' => [self::ENF_T, self::ENF_A, self::MED],

            // Regra transversal de RN-28 (break the glass). Sem `admin`, de propósito.
            'prontuario.quebra_sigilo' => [self::ENF_T, self::ENF_A, self::TEC, self::MED],

            // Prescrição de medicamento .................... — | — | R | R | C R U | — | R
            'prescricao.criar' => [self::MED],
            'prescricao.ler' => [self::ENF_A, self::TEC, self::MED, self::ADM],
            'prescricao.atualizar' => [self::MED],

            // Administração de medicamento ................. — | C R | C R | C R | R | — | R
            'medicamento.administrar' => [self::ENF_T, self::ENF_A, self::TEC],
            'medicamento.ler_administracao' => [self::ENF_T, self::ENF_A, self::TEC, self::MED, self::ADM],

            // Catálogo de medicamentos ..................... R | R | R | R | R | — | C R U D
            'catalogo_medicamento.ler' => [self::REC, self::ENF_T, self::ENF_A, self::TEC, self::MED, self::ADM],
            'catalogo_medicamento.criar' => [self::ADM],
            'catalogo_medicamento.atualizar' => [self::ADM],
            'catalogo_medicamento.excluir' => [self::ADM],

            // Solicitação de exame ......................... — | — | R | — | C R | R | R
            'exame.solicitar' => [self::MED],
            'exame.ler_solicitacao' => [self::ENF_A, self::MED, self::LAB, self::ADM],

            // Execução / laudo de exame .................... — | — | R | — | R | C R U | R
            'exame.executar' => [self::LAB],
            'exame.ler_resultado' => [self::ENF_A, self::MED, self::LAB, self::ADM],
            'exame.atualizar_resultado' => [self::LAB],

            // Liberação de resultado ....................... — | — | — | — | U | U | R
            'exame.liberar_resultado' => [self::MED, self::LAB],

            // Catálogo de exames ........................... R | R | R | R | R | R | C R U D
            'catalogo_exame.ler' => [self::REC, self::ENF_T, self::ENF_A, self::TEC, self::MED, self::LAB, self::ADM],
            'catalogo_exame.criar' => [self::ADM],
            'catalogo_exame.atualizar' => [self::ADM],
            'catalogo_exame.excluir' => [self::ADM],

            // Trilha de auditoria .......................... — | — | — | — | — | — | R
            // O auditor (doc §2.1) consulta a trilha e não edita dado clínico: esta é a
            // única permissão que ele possui.
            'auditoria.ler' => [self::ADM, self::AUD],

            // Gestão de usuários e perfis .................. — | — | — | — | — | — | C R U D
            'usuario.gerenciar' => [self::ADM],
        ];
    }
}
