<?php

declare(strict_types=1);

namespace App\Services\Atendimento;

use App\Enums\SituacaoExame;
use App\Enums\StatusAtendimento;
use App\Models\Atendimento;
use App\Models\ExameSolicitacao;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * O que já existe neste episódio, por módulo — e o que falta fazer.
 *
 * A tela do atendimento mostrava só status e linha do tempo. Quem mudava a situação para
 * `AGUARDANDO_MEDICACAO` via a linha do tempo registrar a transição e **nada mais
 * acontecer**, porque prescrever é outro ato, em outra tela, sem link nenhum apontando
 * para ela. O status descreve onde o paciente está; ele não cria prescrição nem
 * solicitação — e um sistema que não diz isso deixa o usuário concluir que está quebrado.
 *
 * Este serviço existe para que a tela responda duas perguntas de uma vez: **o que já foi
 * feito** e **o que o estado atual está esperando**.
 */
final class PainelAtendimentoService
{
    /**
     * Situações em que a solicitação de exame ainda não produziu resultado — é o que
     * conta como pendência aberta no laboratório.
     */
    private const EXAME_EM_CURSO = [
        SituacaoExame::Solicitado,
        SituacaoExame::Coletado,
        SituacaoExame::EmExecucao,
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function modulos(Atendimento $atendimento, ?User $usuario = null): array
    {
        /*
         * `liberado` decide se o card aparece; `acao_liberada`, se ele vira link.
         *
         * Os dois existem porque não são a mesma pergunta, e tratá-los como uma só
         * produziu um 403: o card de exames era exibido a quem tem `exame.ler_solicitacao`
         * — enfermeiro assistencial e laboratório — mas apontava para o formulário de
         * solicitação, que a doc §2.3 reserva ao médico. O usuário via o botão, clicava,
         * e batia num 403 que não explicava nada.
         *
         * A regra geral: o rótulo e o link seguem o que a pessoa pode FAZER, não o que
         * ela pode ver.
         */
        $pode = fn (string $permissao): bool => $usuario?->can($permissao) ?? false;

        $podeTriar = $pode('triagem.classificar');
        $podeEscreverProntuario = $pode('prontuario.criar_nota_medica') || $pode('prontuario.criar_evolucao_enfermagem');
        $podeLerProntuario = $pode('prontuario.ler_nota_medica') || $pode('prontuario.ler_evolucao_enfermagem');
        $podePrescrever = $pode('prescricao.criar');
        $podeSolicitarExame = $pode('exame.solicitar');

        $filaItem = $atendimento->filaItemAtivo();

        $dosesPendentes = DB::table('vw_doses_pendentes')
            ->where('atendimento_id', $atendimento->id)
            ->get();

        $solicitacoes = $atendimento->exameSolicitacoes()->get();

        return [
            'triagem' => [
                'rotulo' => 'Triagem',
                'href' => route('triagem.edit', $atendimento->id),
                'acao' => $podeTriar
                    ? ($atendimento->classificacao_risco_id === null ? 'Classificar risco' : 'Reavaliar')
                    : 'Ver triagem',
                'total' => $atendimento->triagens()->count(),
                'resumo' => $atendimento->classificacaoRisco?->nome ?? 'Sem classificação de risco',
                // A tela de triagem autoriza por `view` no atendimento: quem lê o status,
                // lê a triagem. Classificar é que exige `triagem.classificar`.
                'liberado' => $pode('atendimento.ler_status'),
                'acao_liberada' => $pode('atendimento.ler_status'),
            ],
            'fila' => [
                'rotulo' => 'Fila',
                'href' => route('fila.index'),
                'acao' => 'Abrir a fila',
                'total' => $filaItem !== null ? 1 : 0,
                'resumo' => $this->resumoDaFila($atendimento, $filaItem?->situacao),
                'liberado' => $pode('fila.ler'),
                'acao_liberada' => $pode('fila.ler'),
            ],
            'prontuario' => [
                'rotulo' => 'Prontuário',
                'href' => route('prontuario.show', $atendimento->id),
                'acao' => $podeEscreverProntuario ? 'Registrar evolução' : 'Abrir prontuário',
                'total' => $atendimento->registrosClinicos()->count(),
                'resumo' => $this->pluralizar($atendimento->registrosClinicos()->count(), 'registro', 'registros'),
                'liberado' => $podeLerProntuario,
                // A tela é de leitura; o formulário dentro dela é que depende da escrita.
                'acao_liberada' => $podeLerProntuario,
            ],
            'medicamentos' => [
                'rotulo' => 'Medicamentos',
                'href' => route('medicamentos.show', $atendimento->id),
                'acao' => $podePrescrever ? 'Prescrever' : 'Ver prescrições',
                'total' => $atendimento->prescricoes()->where('status', 'VIGENTE')->count(),
                'resumo' => $this->resumoDeMedicamentos(
                    $atendimento->prescricoes()->where('status', 'VIGENTE')->count(),
                    $dosesPendentes->count(),
                    $dosesPendentes->where('atrasada', 1)->count(),
                ),
                'alerta' => $dosesPendentes->where('atrasada', 1)->count() > 0,
                'liberado' => $pode('prescricao.ler'),
                // `medicamentos.show` é a lista do atendimento, legível por quem lê
                // prescrição; prescrever é um formulário condicional dentro dela.
                'acao_liberada' => $pode('prescricao.ler'),
            ],
            'exames' => [
                'rotulo' => 'Exames',
                'href' => route('exames.create', $atendimento->id),
                'acao' => 'Solicitar exame',
                'total' => $solicitacoes->count(),
                'resumo' => $this->resumoDeExames($solicitacoes),
                'liberado' => $pode('exame.ler_solicitacao'),
                /*
                 * O único módulo cujo destino é um formulário de escrita, e não uma tela
                 * de leitura: não existe listagem de exames por atendimento. Por isso o
                 * card informa a todos e só vira link para quem pode solicitar.
                 */
                'acao_liberada' => $podeSolicitarExame,
            ],
        ];
    }

    /**
     * A pendência que o estado atual está esperando, se houver.
     *
     * É o coração da correção de usabilidade: "aguardando medicação" sem nenhuma dose
     * prescrita não é um erro do sistema, é um passo que ninguém deu ainda. Dizer isso na
     * tela, com o link do passo que falta, é a diferença entre um fluxo e um beco.
     *
     * @param  array<string, array<string, mixed>>  $modulos
     * @return array{texto: string, acao: string, href: string, acao_liberada: bool}|null
     */
    public function pendencia(Atendimento $atendimento, array $modulos): ?array
    {
        if ($atendimento->status->ehTerminal()) {
            return null;
        }

        return match ($atendimento->status) {
            StatusAtendimento::AguardandoTriagem => $atendimento->classificacao_risco_id === null
                ? $this->aviso(
                    'Este atendimento aguarda triagem. É a classificação de risco que coloca o paciente na fila.',
                    $modulos['triagem'],
                )
                : null,

            StatusAtendimento::AguardandoMedicacao => $modulos['medicamentos']['total'] === 0
                ? $this->aviso(
                    'Situação "aguardando medicação", mas não há prescrição vigente neste atendimento. '
                    .'Mudar a situação não cria prescrição — ela é um ato médico à parte.',
                    $modulos['medicamentos'],
                )
                : null,

            StatusAtendimento::AguardandoExame, StatusAtendimento::EmExame => $modulos['exames']['total'] === 0
                ? $this->aviso(
                    'Situação de exame, mas nenhum exame foi solicitado neste atendimento. '
                    .'A solicitação é o que faz o pedido chegar ao laboratório.',
                    $modulos['exames'],
                )
                : null,

            default => null,
        };
    }

    /**
     * A explicação é para todo mundo; o botão, só para quem pode executar o ato.
     *
     * O enfermeiro precisa entender por que "aguardando exame" não gerou pedido nenhum
     * — ele é quem vai ouvir a pergunta do paciente. Oferecer-lhe um botão que devolve
     * 403 troca uma confusão por outra.
     *
     * @param  array<string, mixed>  $modulo
     * @return array{texto: string, acao: string, href: string, acao_liberada: bool}
     */
    private function aviso(string $texto, array $modulo): array
    {
        return [
            'texto' => $texto,
            'acao' => (string) $modulo['acao'],
            'href' => (string) $modulo['href'],
            'acao_liberada' => (bool) ($modulo['acao_liberada'] ?? false),
        ];
    }

    private function resumoDaFila(Atendimento $atendimento, ?string $situacao): string
    {
        if ($atendimento->status->ehTerminal()) {
            return 'Atendimento encerrado';
        }

        return match ($situacao) {
            'AGUARDANDO' => 'Aguardando na fila',
            'CHAMADO' => 'Paciente chamado',
            'EM_ATENDIMENTO' => 'Em atendimento — fora da espera',
            null => $atendimento->classificacao_risco_id === null
                ? 'Ainda não entrou na fila: falta triagem'
                : 'Fora da fila',
            default => 'Fora da fila',
        };
    }

    private function resumoDeMedicamentos(int $prescricoes, int $pendentes, int $atrasadas): string
    {
        if ($prescricoes === 0) {
            return 'Sem prescrição vigente';
        }

        $texto = $this->pluralizar($prescricoes, 'prescrição vigente', 'prescrições vigentes')
            .' · '.$this->pluralizar($pendentes, 'dose pendente', 'doses pendentes');

        // RN-20 e a segurança da administração: o atraso precisa saltar aos olhos.
        return $atrasadas > 0 ? $texto." · {$atrasadas} em atraso" : $texto;
    }

    /**
     * @param  Collection<int, ExameSolicitacao>  $solicitacoes
     */
    private function resumoDeExames(Collection $solicitacoes): string
    {
        if ($solicitacoes->isEmpty()) {
            return 'Nenhum exame solicitado';
        }

        $emCurso = $solicitacoes
            ->filter(fn ($s) => in_array($s->situacao, self::EXAME_EM_CURSO, strict: true))
            ->count();

        $liberados = $solicitacoes->where('situacao', SituacaoExame::Liberado)->count();

        return $this->pluralizar($solicitacoes->count(), 'exame solicitado', 'exames solicitados')
            ." · {$emCurso} em curso · {$liberados} com resultado liberado";
    }

    private function pluralizar(int $quantidade, string $singular, string $plural): string
    {
        return $quantidade.' '.($quantidade === 1 ? $singular : $plural);
    }
}
