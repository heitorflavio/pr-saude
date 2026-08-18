<?php

declare(strict_types=1);

use App\Models\Atendimento;
use App\Models\AuditoriaLog;
use App\Models\Paciente;
use App\Models\User;
use App\Services\Auditoria\AuditoriaService;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RbacSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->auditoria = app(AuditoriaService::class);
});

it('mascara password, token_pulseira, cpf e cns no log', function () {
    // doc 14.3: o log nao deve replicar o dado sensivel integralmente -- isso criaria
    // uma segunda base com o mesmo risco e sem os mesmos controles.
    $log = $this->auditoria->registrar(
        acao: 'paciente.atualizar',
        antes: [
            'nome_completo' => 'Maria Silva',
            'cpf' => '12345678901',
            'cns' => '123456789012345',
            'token_pulseira' => 'abcDEF123456789012345678',
            'password' => '$argon2id$v=19$m=65536,t=4,p=1$abc',
        ],
        depois: [
            'nome_completo' => 'Maria Silva Souza',
            'cpf' => '12345678901',
        ],
    );

    $antes = $log->fresh()->dados_antes;
    $depois = $log->fresh()->dados_depois;

    expect($antes['cpf'])->toBe('[REMOVIDO]')
        ->and($antes['cns'])->toBe('[REMOVIDO]')
        ->and($antes['token_pulseira'])->toBe('[REMOVIDO]')
        ->and($antes['password'])->toBe('[REMOVIDO]')
        ->and($depois['cpf'])->toBe('[REMOVIDO]')
        // O que nao e sensivel continua legivel: sem isso o log nao serviria para nada.
        ->and($antes['nome_completo'])->toBe('Maria Silva')
        ->and($depois['nome_completo'])->toBe('Maria Silva Souza');
});

it('mascara tambem em estruturas aninhadas', function () {
    $log = $this->auditoria->registrar(
        acao: 'paciente.criar',
        depois: [
            'paciente' => ['nome_completo' => 'Joao', 'cpf' => '98765432100'],
            'credencial' => ['login' => '98765432100', 'password' => 'texto-claro'],
        ],
    );

    $dados = $log->fresh()->dados_depois;

    expect($dados['paciente']['cpf'])->toBe('[REMOVIDO]')
        ->and($dados['credencial']['password'])->toBe('[REMOVIDO]')
        ->and($dados['paciente']['nome_completo'])->toBe('Joao');
});

it('grava o snapshot dos perfis no momento do evento', function () {
    // Se as roles do usuario mudarem depois, o log continua dizendo com que papel ele
    // agiu -- senao a trilha mentiria retroativamente a cada troca de funcao.
    $user = User::factory()->profissional()->create();
    $user->assignRole('medico');
    Auth::login($user->fresh());

    $log = $this->auditoria->registrar(acao: 'prontuario.ler');

    expect($log->perfis_no_momento)->toBe('medico');

    $user->syncRoles(['recepcao']);

    expect($log->fresh()->perfis_no_momento)->toBe('medico');
});

it('registra leitura, nao apenas escrita', function () {
    // doc 14.3: em dado de saude o dano tipico e bisbilhotagem, nao alteracao. Um log
    // que so registra escrita nao detecta o dano tipico.
    $user = User::factory()->profissional()->create();
    Auth::login($user);

    $paciente = Paciente::factory()->create();

    $this->auditoria->registrarLeitura(
        acao: 'prontuario.ler',
        paciente: $paciente,
        entidade: 'Paciente',
        entidadeId: $paciente->user_id,
    );

    $this->assertDatabaseHas('auditoria_log', [
        'user_id' => $user->id,
        'acao' => 'prontuario.ler',
        'paciente_id' => $paciente->user_id,
        'entidade' => 'Paciente',
    ]);
});

it('deriva o paciente a partir do atendimento quando so ele e informado', function () {
    $atendimento = Atendimento::factory()->create();

    $log = $this->auditoria->registrar(
        acao: 'atendimento.ler_status',
        atendimento: $atendimento,
    );

    expect($log->paciente_id)->toBe($atendimento->paciente_id)
        ->and($log->atendimento_id)->toBe($atendimento->id);
});

it('registra justificativa na quebra de sigilo', function () {
    // RN-28: o registro do motivo e o que torna o break the glass auditavel.
    $paciente = Paciente::factory()->create();

    $log = $this->auditoria->registrar(
        acao: 'prontuario.quebra_sigilo',
        paciente: $paciente,
        justificativa: 'Paciente inconsciente, sem acompanhante, admissao por SAMU.',
    );

    expect($log->justificativa)->toContain('SAMU');

    $this->assertDatabaseHas('auditoria_log', [
        'acao' => 'prontuario.quebra_sigilo',
        'paciente_id' => $paciente->user_id,
    ]);
});

it('responde quem acessou os dados deste paciente pelo indice ix_audit_paciente', function () {
    // A consulta que a instituicao precisa saber responder em minutos (doc 14.3,
    // LGPD art. 18).
    $paciente = Paciente::factory()->create();
    $outro = Paciente::factory()->create();

    $medico = User::factory()->profissional()->create();
    Auth::login($medico);

    $this->auditoria->registrarLeitura(acao: 'prontuario.ler', paciente: $paciente);
    $this->auditoria->registrarLeitura(acao: 'paciente.ler', paciente: $paciente);
    $this->auditoria->registrarLeitura(acao: 'prontuario.ler', paciente: $outro);

    $acessos = AuditoriaLog::where('paciente_id', $paciente->user_id)
        ->where('criado_em', '>=', now()->subDays(90))
        ->get();

    expect($acessos)->toHaveCount(2)
        ->and($acessos->pluck('user_id')->unique()->all())->toBe([$medico->id]);
});

it('registra evento sem usuario autenticado sem quebrar', function () {
    // Job agendado e comando de console tambem escrevem na trilha.
    $log = $this->auditoria->registrar(acao: 'sistema.verificacao_integridade');

    expect($log->user_id)->toBeNull()
        ->and($log->perfis_no_momento)->toBeNull()
        ->and($log->acao)->toBe('sistema.verificacao_integridade');
});
