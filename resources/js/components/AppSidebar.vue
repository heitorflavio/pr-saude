<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { usePermissoes } from '@/composables/usePermissoes';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { ChartNoAxesCombined, FlaskConical, LayoutGrid, ListOrdered, Pill, ShieldCheck, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const { podeAlguma } = usePermissoes();

/**
 * Navegação por perfil: cada usuário vê só o que pode acessar.
 *
 * Esconder o item é conveniência, não segurança — a autorização real está nas Policies
 * do servidor. Um menu limpo evita que a recepcionista passe o turno clicando em telas
 * que vão devolver 403.
 *
 * **Só entra aqui o que tem rota de índice.** Triagem, prontuário e medicamentos de um
 * paciente são sempre de um atendimento concreto: chega-se a eles pela fila ou pela
 * ficha, não por um item de menu. Um link que devolve 404 é pior que a ausência dele —
 * ensina o usuário a desconfiar do menu inteiro.
 */
const itensNavegacao: NavItem[] = [
    { title: 'Painel', href: '/dashboard', icon: LayoutGrid },
    { title: 'Pacientes', href: '/pacientes', icon: Users, permissoes: ['paciente.ler'] },
    { title: 'Fila', href: '/fila', icon: ListOrdered, permissoes: ['fila.ler'] },
    { title: 'Medicamentos', href: '/medicamentos', icon: Pill, permissoes: ['prescricao.ler', 'medicamento.administrar'] },
    { title: 'Exames', href: '/exames', icon: FlaskConical, permissoes: ['exame.ler_solicitacao', 'exame.executar'] },
    { title: 'Auditoria', href: '/auditoria', icon: ShieldCheck, permissoes: ['auditoria.ler'] },
    { title: 'Indicadores', href: '/indicadores', icon: ChartNoAxesCombined, permissoes: ['auditoria.ler'] },
];

const itensVisiveis = computed(() => itensNavegacao.filter((item) => !item.permissoes || podeAlguma(item.permissoes)));
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="itensVisiveis" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
