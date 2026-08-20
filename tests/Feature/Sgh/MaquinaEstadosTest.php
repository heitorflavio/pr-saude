<?php

declare(strict_types=1);

use App\Enums\StatusAtendimento;

/**
 * A máquina de estados sem banco (doc §6.2).
 *
 * Colocar as transições no enum tem exatamente este objetivo: uma transição ilegal vira
 * erro detectável em teste unitário, sem infraestrutura. Estes testes rodam em
 * milissegundos e cobrem as 81 combinações possíveis.
 */

/** A tabela da doc §6.2, transcrita. É a fonte contra a qual o enum é conferido. */
function transicoesEsperadas(): array
{
    return [
        'AGUARDANDO_TRIAGEM' => ['AGUARDANDO_ATENDIMENTO', 'EM_ATENDIMENTO', 'CANCELADO'],
        'AGUARDANDO_ATENDIMENTO' => ['EM_ATENDIMENTO', 'AGUARDANDO_ATENDIMENTO', 'CANCELADO'],
        'EM_ATENDIMENTO' => ['AGUARDANDO_EXAME', 'AGUARDANDO_MEDICACAO', 'EM_OBSERVACAO', 'FINALIZADO', 'CANCELADO'],
        'AGUARDANDO_EXAME' => ['EM_EXAME', 'EM_ATENDIMENTO', 'CANCELADO'],
        'EM_EXAME' => ['EM_ATENDIMENTO', 'EM_OBSERVACAO', 'CANCELADO'],
        'AGUARDANDO_MEDICACAO' => ['EM_OBSERVACAO', 'EM_ATENDIMENTO', 'FINALIZADO', 'CANCELADO'],
        'EM_OBSERVACAO' => ['EM_ATENDIMENTO', 'AGUARDANDO_EXAME', 'AGUARDANDO_MEDICACAO', 'FINALIZADO', 'CANCELADO'],
        'FINALIZADO' => [],
        'CANCELADO' => [],
    ];
}

it('reproduz exatamente a tabela de transicoes da doc 6.2', function () {
    foreach (transicoesEsperadas() as $origem => $destinos) {
        $status = StatusAtendimento::from($origem);
        $obtidos = array_map(fn (StatusAtendimento $s) => $s->value, $status->transicoesPermitidas());

        sort($obtidos);
        sort($destinos);

        expect($obtidos)->toBe($destinos, "Transições de {$origem} divergem da doc 6.2.");
    }
});

it('percorre as 81 combinacoes: as permitidas passam, as demais nao', function () {
    // O teste que importa e o negativo: sao 81 pares, e so 26 sao legais. As outras 55
    // precisam ser recusadas -- inclusive as que "parecem" razoaveis, como ir de
    // AGUARDANDO_TRIAGEM direto para EM_EXAME.
    $esperadas = transicoesEsperadas();
    $legais = 0;
    $ilegais = 0;

    foreach (StatusAtendimento::cases() as $origem) {
        foreach (StatusAtendimento::cases() as $destino) {
            $deveriaPassar = in_array($destino->value, $esperadas[$origem->value], strict: true);

            expect($origem->podeTransitarPara($destino))->toBe(
                $deveriaPassar,
                "{$origem->value} -> {$destino->value} deveria ".($deveriaPassar ? 'passar' : 'ser recusada')
            );

            $deveriaPassar ? $legais++ : $ilegais++;
        }
    }

    expect($legais)->toBe(26)
        ->and($ilegais)->toBe(55);
});

it('mantem FINALIZADO e CANCELADO terminais', function () {
    // RN-14. Reabrir um atendimento encerrado apagaria a fronteira do episodio.
    foreach ([StatusAtendimento::Finalizado, StatusAtendimento::Cancelado] as $terminal) {
        expect($terminal->ehTerminal())->toBeTrue()
            ->and($terminal->transicoesPermitidas())->toBe([]);

        foreach (StatusAtendimento::cases() as $destino) {
            expect($terminal->podeTransitarPara($destino))->toBeFalse();
        }
    }
});

it('nao tem deadlock: todo estado nao terminal alcanca FINALIZADO', function () {
    // Um estado do qual nao se sai e um paciente que o sistema nunca deixa ir embora.
    // A busca em largura prova que nao existe nenhum.
    foreach (StatusAtendimento::cases() as $inicial) {
        if ($inicial->ehTerminal()) {
            continue;
        }

        $visitados = [$inicial->value => true];
        $fila = [$inicial];
        $alcancou = false;
        $caminho = [$inicial->value];

        while ($fila !== []) {
            $atual = array_shift($fila);

            if ($atual === StatusAtendimento::Finalizado) {
                $alcancou = true;
                break;
            }

            foreach ($atual->transicoesPermitidas() as $proximo) {
                if (! isset($visitados[$proximo->value])) {
                    $visitados[$proximo->value] = true;
                    $fila[] = $proximo;
                    $caminho[] = $proximo->value;
                }
            }
        }

        expect($alcancou)->toBeTrue(
            "De {$inicial->value} nao se alcanca FINALIZADO. Visitados: ".implode(' -> ', $caminho)
        );
    }
});

it('permite cancelar a partir de qualquer estado nao terminal', function () {
    // doc 6.2, ultima linha: "qualquer nao terminal -> CANCELADO". O paciente que evade
    // precisa poder sair do sistema de qualquer ponto do fluxo.
    foreach (StatusAtendimento::cases() as $status) {
        if ($status->ehTerminal()) {
            continue;
        }

        expect($status->podeTransitarPara(StatusAtendimento::Cancelado))->toBeTrue(
            "{$status->value} deveria admitir cancelamento."
        );
    }
});

it('admite reclassificacao como auto-transicao de AGUARDANDO_ATENDIMENTO', function () {
    // doc 7.5: a reclassificacao reordena a fila sem tirar o paciente do estado em que
    // ele esta -- e sem devolve-lo ao fim da fila.
    expect(StatusAtendimento::AguardandoAtendimento->podeTransitarPara(StatusAtendimento::AguardandoAtendimento))
        ->toBeTrue();

    // E e a UNICA auto-transicao do sistema.
    foreach (StatusAtendimento::cases() as $status) {
        if ($status === StatusAtendimento::AguardandoAtendimento) {
            continue;
        }

        expect($status->podeTransitarPara($status))->toBeFalse(
            "{$status->value} nao deveria transitar para si mesmo."
        );
    }
});

it('traduz todo status para a equipe e para o paciente', function () {
    // doc 12.3: o paciente nunca ve AGUARDANDO_EXAME; ve "Aguardando realizacao de
    // exame". Nenhum rotulo pode ficar vazio ou repetir o valor cru do enum.
    foreach (StatusAtendimento::cases() as $status) {
        expect($status->rotulo())->not->toBe('')
            ->and($status->rotulo())->not->toBe($status->value)
            ->and($status->rotuloPaciente())->not->toBe('')
            ->and($status->rotuloPaciente())->not->toBe($status->value);
    }
});
