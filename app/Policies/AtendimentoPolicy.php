<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StatusAtendimento;
use App\Models\Atendimento;
use App\Models\User;

final class AtendimentoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('atendimento.ler_status');
    }

    public function view(User $user, Atendimento $atendimento): bool
    {
        return $user->can('atendimento.ler_status');
    }

    public function create(User $user): bool
    {
        return $user->can('atendimento.abrir');
    }

    /**
     * RN-12: somente o profissional responsável pelo atendimento -- ou um supervisor --
     * altera o status DESTE atendimento. É a regra que impede um médico de outro setor
     * mexer no fluxo de um paciente que não é dele.
     *
     * A exceção do laboratório vem da nota ¹ da doc §2.3: ele pode mexer no status, mas
     * só nas transições relacionadas a exame. Isso não é expressável como permissão
     * estática -- depende do par (origem, destino).
     */
    public function alterarStatus(
        User $user,
        Atendimento $atendimento,
        ?StatusAtendimento $destino = null,
    ): bool {
        if (! $user->can('atendimento.alterar_status')) {
            return false;
        }

        if ($this->ehLaboratorio($user)) {
            return $this->transicaoDeExame($atendimento->status, $destino);
        }

        return $this->ehResponsavel($user, $atendimento) || $this->ehSupervisor($user);
    }

    /** RN-14: finalizar exige desfecho, e o desfecho é decisão médica. */
    public function finalizar(User $user, Atendimento $atendimento): bool
    {
        return $user->can('atendimento.alterar_status')
            && ($this->ehResponsavel($user, $atendimento) || $this->ehSupervisor($user));
    }

    /** Cancelamento exige justificativa e é ato de supervisão (doc §6.2). */
    public function cancelar(User $user, Atendimento $atendimento): bool
    {
        return $user->can('atendimento.alterar_status') && $this->ehSupervisor($user);
    }

    private function ehResponsavel(User $user, Atendimento $atendimento): bool
    {
        return $atendimento->profissional_responsavel_id !== null
            && $atendimento->profissional_responsavel_id === $user->profissional?->user_id;
    }

    /**
     * Supervisão: médico ou enfermeiro em plantão. O atendimento não pode travar porque
     * o responsável saiu do turno -- em pronto-socorro, isso seria risco assistencial.
     */
    private function ehSupervisor(User $user): bool
    {
        return in_array($user->profissional?->categoria, ['MEDICO', 'ENFERMEIRO'], strict: true)
            && $user->emPlantao();
    }

    private function ehLaboratorio(User $user): bool
    {
        return $user->profissional?->categoria === 'LABORATORIO';
    }

    /** Nota ¹ da doc §2.3: AGUARDANDO_EXAME -> EM_EXAME -> de volta ao atendimento. */
    private function transicaoDeExame(StatusAtendimento $origem, ?StatusAtendimento $destino): bool
    {
        if ($destino === null) {
            return false;
        }

        return match (true) {
            $origem === StatusAtendimento::AguardandoExame && $destino === StatusAtendimento::EmExame => true,
            $origem === StatusAtendimento::EmExame && $destino === StatusAtendimento::EmAtendimento => true,
            $origem === StatusAtendimento::EmExame && $destino === StatusAtendimento::EmObservacao => true,
            default => false,
        };
    }
}
