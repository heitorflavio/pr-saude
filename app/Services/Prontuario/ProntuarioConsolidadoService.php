<?php

declare(strict_types=1);

namespace App\Services\Prontuario;

use App\Enums\TipoRegistroClinico;
use App\Models\Atendimento;
use App\Models\Diagnostico;
use App\Models\Paciente;
use App\Models\RegistroClinico;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * RF-51 — o prontuário consolidado, atravessando **todos** os atendimentos do paciente.
 *
 * A visão por atendimento é a que o plantão usa; a consolidada é a que evita o erro mais
 * caro do pronto-socorro: tratar cada vinda como se fosse a primeira. Três passagens em
 * duas semanas pela mesma queixa são um dado clínico, e ele só aparece quando os
 * episódios são lidos juntos.
 */
final class ProntuarioConsolidadoService
{
    public function __construct(
        private readonly HashEncadeadoService $hashes,
    ) {}

    /**
     * Linha do tempo de um atendimento: registros em ordem, com o vínculo original↔adendo
     * já resolvido para a apresentação (doc §9.3).
     *
     * @return array<int, array<string, mixed>>
     */
    public function linhaDoTempo(Atendimento $atendimento, ?User $leitor = null): array
    {
        $registros = $atendimento->registrosClinicos()
            ->orderBy('criado_em')
            ->orderBy('id')
            ->get();

        $adendosPorOriginal = $registros
            ->whereNotNull('registro_retificado_id')
            ->groupBy('registro_retificado_id');

        return $registros
            ->filter(fn (RegistroClinico $r) => $this->visivelPara($r, $leitor))
            ->map(function (RegistroClinico $registro) use ($adendosPorOriginal) {
                /** @var Collection<int, RegistroClinico> $adendos */
                $adendos = $adendosPorOriginal->get($registro->id, collect());

                return $this->apresentar($registro) + [
                    /*
                     * RF-50: a tarja. O original permanece legível e explicitamente
                     * ligado ao adendo -- omiti-lo da tela seria o mesmo que apagá-lo,
                     * só que sem deixar rastro no banco.
                     */
                    'retificado' => $adendos->isNotEmpty(),
                    'retificado_por' => $adendos
                        ->map(fn (RegistroClinico $a) => [
                            'id' => $a->id,
                            'criado_em' => $a->criado_em?->format('d/m/Y H:i'),
                            'motivo' => $a->motivo_retificacao,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * RF-51: todos os episódios do paciente, do mais recente ao mais antigo, cada um com
     * seus registros e diagnósticos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function episodios(Paciente $paciente, ?User $leitor = null): array
    {
        return $paciente->atendimentos()
            ->with(['unidade', 'classificacaoRisco'])
            ->orderByDesc('admitido_em')
            ->get()
            ->map(fn (Atendimento $atendimento) => [
                'id' => $atendimento->id,
                'numero' => $atendimento->numero,
                'unidade' => $atendimento->unidade?->nome,
                'status' => $atendimento->status->value,
                'status_rotulo' => $atendimento->status->rotulo(),
                'prioridade' => $atendimento->classificacaoRisco?->nome,
                'prioridade_cor' => $atendimento->classificacaoRisco?->cor,
                'admitido_em' => $atendimento->admitido_em?->format('d/m/Y H:i'),
                'finalizado_em' => $atendimento->finalizado_em?->format('d/m/Y H:i'),
                'desfecho' => $atendimento->desfecho,
                'diagnosticos' => $this->diagnosticos($atendimento),
                'registros' => $this->linhaDoTempo($atendimento, $leitor),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function diagnosticos(Atendimento $atendimento): array
    {
        return $atendimento->diagnosticos()
            ->with('cid10')
            ->orderByDesc('principal')
            ->orderBy('criado_em')
            ->get()
            ->map(fn (Diagnostico $d) => [
                'id' => $d->id,
                'codigo' => $d->cid10_codigo,
                'descricao' => $d->cid10?->descricao,
                'natureza' => $d->natureza,
                'principal' => $d->principal,
                'observacao' => $d->observacao,
                'criado_em' => $d->criado_em?->format('d/m/Y H:i'),
            ])
            ->all();
    }

    /**
     * Integridade da cadeia deste atendimento, para exibir junto ao prontuário.
     *
     * @return array{integra: bool, quebras: array<int, array{id: int, motivo: string}>}
     */
    public function integridade(Atendimento $atendimento): array
    {
        return $this->hashes->verificarCadeia($atendimento->id);
    }

    /**
     * doc §9.6: o portal omite o registro sigiloso **sem indicar que ele existe**.
     * Exibir "1 registro oculto" seria pior que omitir — cria ansiedade sem informação.
     *
     * Sigilo é sobre a exibição no portal, não sobre o direito de acesso: a equipe
     * assistencial e o auditor continuam vendo tudo.
     */
    private function visivelPara(RegistroClinico $registro, ?User $leitor): bool
    {
        if (! $registro->sigiloso) {
            return true;
        }

        return $leitor?->can('view', $registro) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    private function apresentar(RegistroClinico $registro): array
    {
        $tipo = $registro->tipo;

        return [
            'id' => $registro->id,
            'uuid' => $registro->uuid,
            'tipo' => $tipo->value,
            'tipo_rotulo' => $tipo->rotulo(),
            'usa_soap' => $tipo->usaSoap(),
            'subjetivo' => $registro->subjetivo,
            'objetivo' => $registro->objetivo,
            'avaliacao' => $registro->avaliacao,
            'plano' => $registro->plano,
            'conteudo_livre' => $registro->conteudo_livre,
            'sigiloso' => $registro->sigiloso,
            'motivo_retificacao' => $registro->motivo_retificacao,
            'retifica' => $registro->registro_retificado_id,
            // Snapshot: quem assinou naquele momento, não quem é hoje.
            'autor_nome' => $registro->autor_nome,
            'autor_conselho' => $registro->autor_conselho,
            'criado_em' => $registro->criado_em?->format('d/m/Y H:i'),
        ];
    }

    /**
     * Tipos que o profissional pode registrar, conforme a permissão que ele tem.
     *
     * A doc §2.3 separa nota médica de evolução de enfermagem justamente porque o técnico
     * escreve uma e não a outra — oferecer o tipo na tela e negar no envio seria empurrar
     * o erro para o fim do formulário.
     *
     * @return array<int, array{valor: string, rotulo: string, usa_soap: bool}>
     */
    public function tiposDisponiveis(User $usuario): array
    {
        $tipos = [];

        foreach (TipoRegistroClinico::cases() as $tipo) {
            // Adendo não é oferecido: ele nasce da retificação de um registro concreto.
            if ($tipo === TipoRegistroClinico::Adendo) {
                continue;
            }

            $permissao = $tipo === TipoRegistroClinico::EvolucaoEnfermagem
                ? 'prontuario.criar_evolucao_enfermagem'
                : 'prontuario.criar_nota_medica';

            if (! $usuario->can($permissao)) {
                continue;
            }

            $tipos[] = [
                'valor' => $tipo->value,
                'rotulo' => $tipo->rotulo(),
                'usa_soap' => $tipo->usaSoap(),
            ];
        }

        return $tipos;
    }
}
