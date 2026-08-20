<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import BadgePrioridade from '@/components/sgh/BadgePrioridade.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Activity, CheckCircle2, LoaderCircle, Plus } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * RF-18: os atendimentos do paciente, com os **em andamento separados dos finalizados**.
 *
 * A separação é o ponto do requisito. Numa lista só cronológica, o episódio em curso —
 * o único sobre o qual alguém pode agir agora — ficaria misturado com os de dois anos
 * atrás.
 */
interface Atendimento {
    id: number;
    numero: string;
    status_rotulo: string;
    unidade: string | null;
    admitido_em: string | null;
    finalizado_em: string | null;
    desfecho: string | null;
    responsavel: string | null;
    prioridade: string | null;
    prioridade_cor: string | null;
}

const props = defineProps<{
    paciente: { user_id: number; nome: string };
    emAndamento: Atendimento[];
    finalizados: Atendimento[];
    unidades: { id: number; nome: string }[];
    podeAbrir: boolean;
}>();

const page = usePage<SharedData>();
const alerta = computed(() => page.props.flash?.alerta);

// RN-07: um atendimento ativo por unidade. O formulário só aparece quando não há
// nenhum aberto -- e mesmo se aparecesse, o índice único do banco recusaria.
const form = useForm({
    unidade_id: '',
    origem: 'ESPONTANEA',
    sintomas_entrada: '',
});

const abrir = () => form.post(route('atendimentos.store', props.paciente.user_id));

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pacientes', href: '/pacientes' },
    { title: props.paciente.nome, href: `/pacientes/${props.paciente.user_id}` },
    { title: 'Atendimentos', href: '#' },
];
</script>

<template>
    <Head :title="`Atendimentos — ${paciente.nome}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-4xl flex-col gap-8 p-4">
            <h1 class="text-2xl font-bold">Atendimentos de {{ paciente.nome }}</h1>

            <div
                v-if="alerta"
                class="rounded-md bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900 dark:bg-amber-950 dark:text-amber-100"
                role="alert"
            >
                {{ alerta }}
            </div>

            <section v-if="podeAbrir && !emAndamento.length" class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                    <Plus class="h-4 w-4" aria-hidden="true" />
                    Abrir atendimento
                </h2>

                <form class="mt-3 grid gap-3 sm:grid-cols-[14rem_12rem_1fr_auto] sm:items-end" @submit.prevent="abrir">
                    <div class="grid gap-2">
                        <Label for="unidade">Unidade</Label>
                        <select
                            id="unidade"
                            v-model="form.unidade_id"
                            class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                            required
                        >
                            <option value="" disabled>Selecione</option>
                            <option v-for="unidade in unidades" :key="unidade.id" :value="unidade.id">{{ unidade.nome }}</option>
                        </select>
                        <InputError :message="form.errors.unidade_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="origem">Origem</Label>
                        <select
                            id="origem"
                            v-model="form.origem"
                            class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                        >
                            <option value="ESPONTANEA">Demanda espontânea</option>
                            <option value="SAMU">SAMU</option>
                            <option value="ENCAMINHADO">Encaminhado</option>
                            <option value="TRANSFERENCIA">Transferência</option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="sintomas">Sintomas na entrada</Label>
                        <Input id="sintomas" v-model="form.sintomas_entrada" />
                    </div>

                    <Button type="submit" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" aria-hidden="true" />
                        Abrir
                    </Button>
                </form>
            </section>

            <section>
                <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                    <Activity class="h-4 w-4" aria-hidden="true" />
                    Em andamento
                </h2>

                <p v-if="!emAndamento.length" class="mt-3 text-sm text-muted-foreground">Nenhum atendimento em andamento.</p>

                <ul v-else class="mt-3 space-y-2">
                    <li
                        v-for="atendimento in emAndamento"
                        :key="atendimento.id"
                        class="rounded-xl border-2 border-sidebar-border/70 p-4 dark:border-sidebar-border"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <Link :href="`/atendimentos/${atendimento.id}`" class="font-mono font-semibold underline-offset-4 hover:underline">
                                {{ atendimento.numero }}
                            </Link>
                            <BadgePrioridade :cor="atendimento.prioridade_cor" :rotulo="atendimento.prioridade" />
                        </div>
                        <dl class="mt-2 grid gap-1 text-sm text-muted-foreground sm:grid-cols-2">
                            <div>
                                Situação: <strong class="text-foreground">{{ atendimento.status_rotulo }}</strong>
                            </div>
                            <div>Admissão: {{ atendimento.admitido_em }}</div>
                            <div>Unidade: {{ atendimento.unidade }}</div>
                            <div>Responsável: {{ atendimento.responsavel ?? '—' }}</div>
                        </dl>
                    </li>
                </ul>
            </section>

            <section>
                <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                    <CheckCircle2 class="h-4 w-4" aria-hidden="true" />
                    Finalizados
                </h2>

                <p v-if="!finalizados.length" class="mt-3 text-sm text-muted-foreground">Nenhum atendimento anterior.</p>

                <ul v-else class="mt-3 space-y-2">
                    <li
                        v-for="atendimento in finalizados"
                        :key="atendimento.id"
                        class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <Link :href="`/atendimentos/${atendimento.id}`" class="font-mono underline-offset-4 hover:underline">
                                {{ atendimento.numero }}
                            </Link>
                            <span class="text-xs font-semibold text-muted-foreground">
                                {{ atendimento.desfecho ?? atendimento.status_rotulo }}
                            </span>
                        </div>
                        <dl class="mt-2 grid gap-1 text-sm text-muted-foreground sm:grid-cols-2">
                            <div>Admissão: {{ atendimento.admitido_em }}</div>
                            <div>Encerrado: {{ atendimento.finalizado_em ?? '—' }}</div>
                        </dl>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
