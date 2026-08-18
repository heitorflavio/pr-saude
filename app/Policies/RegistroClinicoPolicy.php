<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\TipoRegistroClinico;
use App\Models\RegistroClinico;
use App\Models\User;

final class RegistroClinicoPolicy
{
    /**
     * A doc §2.3 separa "Prontuário — nota médica" de "Prontuário — evolução de
     * enfermagem" com acessos diferentes: o técnico escreve evolução mas não nota
     * médica. Por isso a permissão depende do TIPO do registro.
     */
    public function create(User $user, TipoRegistroClinico $tipo): bool
    {
        return match ($tipo) {
            TipoRegistroClinico::EvolucaoEnfermagem => $user->can('prontuario.criar_evolucao_enfermagem'),
            TipoRegistroClinico::Adendo => $user->can('prontuario.retificar'),
            default => $user->can('prontuario.criar_nota_medica'),
        };
    }

    public function view(User $user, RegistroClinico $registro): bool
    {
        // RF-77: registro sigiloso só é visível a quem pode criar nota médica.
        if ($registro->sigiloso && ! $user->can('prontuario.ler_nota_medica')) {
            return false;
        }

        return match ($registro->tipo) {
            TipoRegistroClinico::EvolucaoEnfermagem => $user->can('prontuario.ler_evolucao_enfermagem'),
            default => $user->can('prontuario.ler_nota_medica'),
        };
    }

    /**
     * RN-16: "atualizar" um registro clínico não existe. O que existe é criar um adendo
     * apontando para ele. Esta Policy autoriza a RETIFICAÇÃO, nunca a edição -- o model
     * `RegistroClinico` lança `RegistroImutavelException` em qualquer save() de
     * registro já persistido, e o banco fecha a porta com REVOKE UPDATE.
     */
    public function retificar(User $user, RegistroClinico $registro): bool
    {
        if (! $user->can('prontuario.retificar')) {
            return false;
        }

        // Quem retifica precisa poder escrever no mesmo domínio do registro original:
        // um técnico não retifica nota médica.
        return $this->create($user, $registro->tipo);
    }

    /**
     * RN-17 / D-05: nenhum registro clínico é excluído, por ninguém, nunca. A Policy
     * existe para que um `$this->authorize('delete', ...)` esquecido em algum controller
     * futuro negue, em vez de cair no comportamento padrão.
     */
    public function delete(User $user, RegistroClinico $registro): bool
    {
        return false;
    }

    public function forceDelete(User $user, RegistroClinico $registro): bool
    {
        return false;
    }

    /** doc §9.6: marcar um registro como sigiloso é ato médico e fica auditado. */
    public function marcarSigiloso(User $user, RegistroClinico $registro): bool
    {
        return $user->can('prontuario.criar_nota_medica');
    }
}
