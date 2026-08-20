<script setup lang="ts">
import BadgePrioridade from '@/components/sgh/BadgePrioridade.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage, usePoll } from '@inertiajs/vue3';
import { AlertTriangle, Clock, Users } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * RF-29: painel do profissional.
 *
 * A posição vem da view `vw_fila_ordenada` — ela não é recalculada aqui nem persistida
 * em lugar nenhum (RN-10). O que a tela faz é exibir.
 */
interface ItemFila {
    fila_item_id: number;
    posicao: number;
    atendimento_id: number;
    atendimento_numero: string;
    paciente_id: number;
    paciente_nome: string;
    idade_anos: number | null;
    prioridade: string;
    prioridade_cor: string;
    entrou_em: string;
    espera_minutos: number;
    tempo_alvo_minutos: number;
    tempo_alvo_excedido: boolean;
    situacao_rotulo: string;
    sugere_reavaliacao: boolean;
}

defineProps<{
    fila: ItemFila[];
    contexto: { geral: boolean; profissional: string | null; pode_atribuir: boolean };
    legendaEspera: { valor: string; rotulo: string; acao: string }[];
}>();

const page = usePage<SharedData>();
const status = computed(() => page.props.flash?.status);

/*
 * RF-34 / RNF-03: dez segundos, recarregando SÓ a prop `fila`.
 *
 * O `only` é o que torna o polling barato: o resto da página — legenda, contexto,
 * permissões — não trafega a cada dez segundos. É também o que a doc §7.7 aponta como
 * substituível por WebSocket depois, sem mexer no resto.
 */
usePoll(10000, { only: ['fila'] });

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Fila', href: '/fila' }];

const alternar = (geral: boolean) => router.get(route('fila.index'), geral ? { fila: 'geral' } : {}, { preserveState: false });
</script>

<template>
    <Head title="Fila" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div
                v-if="status"
                class="rounded-md bg-green-50 px-4 py-3 text-sm font-medium text-green-900 dark:bg-green-950 dark:text-green-100"
                role="status"
            >
                {{ status }}
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="flex items-center gap-2 text-2xl font-bold">
                    <Users class="h-5 w-5" aria-hidden="true" />
                    {{ contexto.geral ? 'Fila geral' : `Fila de ${contexto.profissional}` }}
                </h1>

                <div class="flex gap-2">
                    <Button type="button" variant="outline" size="sm" :disabled="!contexto.geral" @click="alternar(false)"> Minha fila </Button>
                    <Button type="button" variant="outline" size="sm" :disabled="contexto.geral" @click="alternar(true)"> Fila geral </Button>
                </div>
            </div>

            <p class="text-xs text-muted-foreground">Atualiza automaticamente a cada 10 segundos.</p>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">
                        Pacientes aguardando atendimento, em ordem de prioridade
                    </caption>
                    <thead class="bg-neutral-50 text-xs uppercase tracking-wide text-neutral-600 dark:bg-neutral-900 dark:text-neutral-400">
                        <tr>
                            <th scope="col" class="px-4 py-3">#</th>
                            <th scope="col" class="px-4 py-3">Paciente</th>
                            <th scope="col" class="px-4 py-3">Prioridade</th>
                            <th scope="col" class="px-4 py-3">Entrada</th>
                            <th scope="col" class="px-4 py-3">Espera</th>
                            <th scope="col" class="px-4 py-3">Situação</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                        <tr
                            v-for="item in fila"
                            :key="item.fila_item_id"
                            :class="item.sugere_reavaliacao ? 'bg-amber-50/60 dark:bg-amber-950/20' : ''"
                        >
                            <td class="px-4 py-3 font-mono font-bold">{{ item.posicao }}</td>
                            <td class="px-4 py-3">
                                <Link :href="`/pacientes/${item.paciente_id}`" class="font-medium underline-offset-4 hover:underline">
                                    {{ item.paciente_nome }}
                                </Link>
                                <span class="block font-mono text-xs text-muted-foreground">
                                    {{ item.atendimento_numero }}
                                    <template v-if="item.idade_anos !== null"> · {{ item.idade_anos }} anos</template>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <!-- RNF-15: cor + rótulo + ícone. -->
                                <BadgePrioridade :cor="item.prioridade_cor" :rotulo="item.prioridade" />
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">{{ item.entrou_em }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center gap-1"
                                    :class="item.tempo_alvo_excedido ? 'font-semibold text-red-700 dark:text-red-300' : ''"
                                >
                                    <Clock class="h-3.5 w-3.5" aria-hidden="true" />
                                    {{ item.espera_minutos }} / {{ item.tempo_alvo_minutos }} min
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <!-- RF-33: sinaliza e SUGERE reavaliação; nunca promove sozinho. -->
                                <span
                                    v-if="item.sugere_reavaliacao"
                                    class="inline-flex items-center gap-1 text-xs font-semibold text-amber-800 dark:text-amber-200"
                                >
                                    <AlertTriangle class="h-3.5 w-3.5" aria-hidden="true" />
                                    {{ item.situacao_rotulo }} — sugerir reavaliação
                                </span>
                                <span v-else class="text-xs text-muted-foreground">{{ item.situacao_rotulo }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="`/atendimentos/${item.atendimento_id}/triagem`" class="text-xs underline underline-offset-4">
                                    Reavaliar
                                </Link>
                                <Link
                                    v-if="contexto.pode_atribuir"
                                    :href="`/atendimentos/${item.atendimento_id}/atribuir`"
                                    class="ml-3 text-xs underline underline-offset-4"
                                >
                                    Atribuir
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!fila.length">
                            <td colspan="7" class="px-4 py-10 text-center text-neutral-500">Nenhum paciente aguardando.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <details class="text-xs text-muted-foreground">
                <summary class="cursor-pointer">Como a espera é classificada</summary>
                <dl class="mt-2 space-y-1">
                    <div v-for="nivel in legendaEspera" :key="nivel.valor">
                        <dt class="inline font-medium">{{ nivel.rotulo }}:</dt>
                        <dd class="inline">{{ nivel.acao }}</dd>
                    </div>
                </dl>
                <p class="mt-2">
                    A prioridade nunca é elevada automaticamente pelo tempo de espera: um paciente que espera muito pode ter piorado, e essa avaliação
                    é clínica.
                </p>
            </details>
        </div>
    </AppLayout>
</template>
