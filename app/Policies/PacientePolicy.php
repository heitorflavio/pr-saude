<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Paciente;
use App\Models\User;

/**
 * A camada CONTEXTUAL da autorização: "este usuário pode fazer isso NESTE registro?".
 *
 * Regra de composição: a Policy checa a permission do spatie **e** o vínculo
 * assistencial. Permission sozinha nunca basta para dado clínico -- ter
 * `paciente.ler` significa poder ler prontuário em geral, não poder ler o prontuário
 * desta pessoa.
 */
final class PacientePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('paciente.ler');
    }

    public function create(User $user): bool
    {
        return $user->can('paciente.criar');
    }

    public function update(User $user, Paciente $paciente): bool
    {
        return $user->can('paciente.atualizar');
    }

    public function delete(User $user, Paciente $paciente): bool
    {
        return $user->can('paciente.excluir');
    }

    /**
     * Contexto clínico completo: exige vínculo assistencial (RN-28).
     *
     * O vínculo existe se o profissional é o responsável pelo atendimento, se o
     * paciente está ou esteve na fila dele, ou se ele já escreveu no prontuário ou
     * prescreveu. Histórico conta: quem atendeu ontem pode reler o que escreveu.
     */
    public function verContexto(User $user, Paciente $paciente): bool
    {
        if (! $user->can('paciente.ler')) {
            return false;
        }

        $profissionalId = $user->profissional?->user_id;

        if ($profissionalId === null) {
            return false;
        }

        return $paciente->atendimentos()
            ->where(function ($q) use ($profissionalId) {
                $q->where('profissional_responsavel_id', $profissionalId)
                    ->orWhereHas('filaItens', fn ($f) => $f->where('profissional_id', $profissionalId))
                    ->orWhereHas('registrosClinicos', fn ($r) => $r->where('autor_id', $profissionalId))
                    ->orWhereHas('prescricoes', fn ($p) => $p->where('prescrito_por', $profissionalId));
            })
            ->exists();
    }

    /**
     * Mínimo vital: nome e ALERGIAS, liberados a qualquer profissional em plantão
     * (doc §13.5).
     *
     * Decisão de projeto deliberada, e a mais desconfortável do sistema. Negar a lista
     * de alergias a um médico que atende uma parada cardíaca no corredor, em nome do
     * sigilo, seria uma escolha de projeto com potencial letal. O acesso é amplo; o
     * registro em auditoria é integral -- é a auditoria, não o bloqueio, que controla
     * o abuso aqui.
     */
    public function verMinimoVital(User $user, Paciente $paciente): bool
    {
        return $user->emPlantao();
    }

    /**
     * Quebra de sigilo: permitida com justificativa, sempre auditada (RN-28).
     *
     * Note que o `admin` NÃO passa por aqui via Gate::before -- é a única exceção
     * daquele atalho. Um administrador que precise ler prontuário deixa rastro como
     * qualquer outro.
     */
    public function quebrarSigilo(User $user, Paciente $paciente): bool
    {
        return $user->emPlantao() && $user->can('prontuario.quebra_sigilo');
    }

    /** RF-15, RF-16: imprimir ou reimprimir pulseira. */
    public function imprimirPulseira(User $user, Paciente $paciente): bool
    {
        return $user->can('pulseira.imprimir');
    }
}
