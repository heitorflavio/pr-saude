import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Leitura das permissões do usuário autenticado, compartilhadas pelo
 * HandleInertiaRequests.
 *
 * Isto serve APENAS para decidir o que aparece na tela. A autorização real acontece no
 * servidor, nas Policies — esconder um botão não protege nada, e um menu filtrado no
 * cliente é conveniência, nunca controle de acesso.
 */
export function usePermissoes() {
    const page = usePage<SharedData>();

    const permissoes = computed<string[]>(() => page.props.auth?.permissoes ?? []);
    const roles = computed<string[]>(() => page.props.auth?.roles ?? []);
    const ehAdmin = computed<boolean>(() => page.props.auth?.user?.tipo === 'ADMIN');

    /** O admin passa por cima de tudo, espelhando o Gate::before do servidor. */
    const pode = (permissao: string): boolean => ehAdmin.value || permissoes.value.includes(permissao);

    const podeAlguma = (lista: string[]): boolean => lista.some((permissao) => pode(permissao));

    const temRole = (role: string): boolean => roles.value.includes(role);

    return { permissoes, roles, ehAdmin, pode, podeAlguma, temRole };
}
