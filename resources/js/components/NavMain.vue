<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

/**
 * O tipo vem de `@/types` — não é redeclarado aqui.
 *
 * A declaração local anterior chamava o campo de `url` enquanto `AppSidebar` monta os
 * itens com `href`: o `<Link>` recebia `undefined` e o menu inteiro virava âncora sem
 * destino. Duas definições do mesmo contrato é um erro que o TypeScript não pega,
 * porque cada lado está internamente consistente.
 */
defineProps<{ items: NavItem[] }>();

const page = usePage<SharedData>();

/**
 * Ativo também nas telas filhas: quem está em `/pacientes/12` continua dentro de
 * "Pacientes". Comparar por igualdade exata apagaria o destaque assim que o usuário
 * navegasse para dentro da seção — justamente quando ele mais precisa saber onde está.
 *
 * O `split('?')` descarta a query string: `/fila?fila=geral` é a mesma seção que `/fila`.
 */
const ehAtivo = (href: string): boolean => {
    const atual = page.url.split('?')[0];

    return atual === href || atual.startsWith(`${href}/`);
};
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Navegação</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton as-child :is-active="ehAtivo(item.href)">
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
