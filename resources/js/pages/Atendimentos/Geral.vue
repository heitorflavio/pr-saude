<script setup lang="ts">
import BadgePrioridade from '@/components/sgh/BadgePrioridade.vue';
import { Button } from '@/components/ui/button';
import { usePermissoes } from '@/composables/usePermissoes';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ClipboardList, ListOrdered, Search, Stethoscope } from 'lucide-vue-next';

interface Atendimento {
    id: number;
    numero: string;
    status: string;
    status_rotulo: string;
    paciente_id: number;
    paciente_nome: string;
    unidade: string | null;
    admitido_em: string | null;
    finalizado_em: string | null;
    desfecho: string | null;
    responsavel: string | null;
    prioridade: string | null;
    prioridade_cor: string | null;
}

defineProps<{
    emAndamento: Atendimento[];
    recentes: Atendimento[];
}>();

const { pode } = usePermissoes();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Atendimentos', href: '/atendimentos' }];
</script>

<template>
    <Head title="Atendimentos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold">
                        <ClipboardList class="h-6 w-6" aria-hidden="true" />
                        Atendimentos
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">Acompanhe os casos em curso e acesse diretamente a próxima etapa.</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button v-if="pode('fila.ler')" variant="outline" as-child>
                        <Link :href="route('fila.index')">
                            <ListOrdered class="h-4 w-4" aria-hidden="true" />
                            Ver fila
                        </Link>
                    </Button>
                    <Button v-if="pode('atendimento.abrir')" as-child>
                        <Link :href="route('pacientes.index')">
                            <Search class="h-4 w-4" aria-hidden="true" />
                            Buscar paciente para abrir
                        </Link>
                    </Button>
                </div>
            </header>

            <section>
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Em andamento</h2>
                    <span class="rounded-full bg-muted px-2.5 py-1 text-xs font-semibold">{{ emAndamento.length }}</span>
                </div>

                <div class="mt-3 overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table class="w-full text-left text-sm">
                        <caption class="sr-only">
                            Atendimentos em andamento, do mais antigo para o mais recente
                        </caption>
                        <thead class="bg-neutral-50 text-xs uppercase tracking-wide text-neutral-600 dark:bg-neutral-900 dark:text-neutral-400">
                            <tr>
                                <th scope="col" class="px-4 py-3">Paciente</th>
                                <th scope="col" class="px-4 py-3">Situação</th>
                                <th scope="col" class="px-4 py-3">Prioridade</th>
                                <th scope="col" class="px-4 py-3">Admissão</th>
                                <th scope="col" class="px-4 py-3">Responsável</th>
                                <th scope="col" class="px-4 py-3"><span class="sr-only">Ações</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                            <tr v-for="atendimento in emAndamento" :key="atendimento.id">
                                <td class="px-4 py-3">
                                    <Link :href="route('atendimentos.show', atendimento.id)" class="font-medium underline-offset-4 hover:underline">
                                        {{ atendimento.paciente_nome }}
                                    </Link>
                                    <span class="block font-mono text-xs text-muted-foreground">
                                        {{ atendimento.numero }} · {{ atendimento.unidade ?? 'Unidade não informada' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-medium">{{ atendimento.status_rotulo }}</td>
                                <td class="px-4 py-3">
                                    <BadgePrioridade :cor="atendimento.prioridade_cor" :rotulo="atendimento.prioridade" />
                                </td>
                                <td class="px-4 py-3 text-xs text-muted-foreground">{{ atendimento.admitido_em }}</td>
                                <td class="px-4 py-3 text-xs text-muted-foreground">{{ atendimento.responsavel ?? 'Não atribuído' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Button size="sm" as-child>
                                        <Link :href="route('atendimentos.show', atendimento.id)">
                                            <Stethoscope class="h-4 w-4" aria-hidden="true" />
                                            Abrir
                                        </Link>
                                    </Button>
                                    <Link
                                        v-if="atendimento.status === 'AGUARDANDO_TRIAGEM' && pode('triagem.classificar')"
                                        :href="route('triagem.edit', atendimento.id)"
                                        class="ml-3 text-xs underline underline-offset-4"
                                    >
                                        Realizar triagem
                                    </Link>
                                    <Link
                                        v-else-if="atendimento.prioridade && pode('fila.atribuir')"
                                        :href="route('fila.atribuir', atendimento.id)"
                                        class="ml-3 text-xs underline underline-offset-4"
                                    >
                                        Atribuir
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!emAndamento.length">
                                <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">Nenhum atendimento em andamento.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Encerrados recentemente</h2>
                <div class="mt-3 overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table class="w-full text-left text-sm">
                        <caption class="sr-only">
                            Vinte atendimentos encerrados mais recentes
                        </caption>
                        <thead class="bg-neutral-50 text-xs uppercase tracking-wide text-neutral-600 dark:bg-neutral-900 dark:text-neutral-400">
                            <tr>
                                <th scope="col" class="px-4 py-3">Paciente</th>
                                <th scope="col" class="px-4 py-3">Atendimento</th>
                                <th scope="col" class="px-4 py-3">Desfecho</th>
                                <th scope="col" class="px-4 py-3">Encerrado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                            <tr v-for="atendimento in recentes" :key="atendimento.id">
                                <td class="px-4 py-3">
                                    <Link :href="route('atendimentos.show', atendimento.id)" class="font-medium underline-offset-4 hover:underline">
                                        {{ atendimento.paciente_nome }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs">{{ atendimento.numero }}</td>
                                <td class="px-4 py-3">{{ atendimento.desfecho ?? atendimento.status_rotulo }}</td>
                                <td class="px-4 py-3 text-xs text-muted-foreground">{{ atendimento.finalizado_em ?? '—' }}</td>
                            </tr>
                            <tr v-if="!recentes.length">
                                <td colspan="4" class="px-4 py-8 text-center text-muted-foreground">Nenhum atendimento encerrado.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
