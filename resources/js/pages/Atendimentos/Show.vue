<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import BadgePrioridade from '@/components/sgh/BadgePrioridade.vue';
import PainelAlergias from '@/components/sgh/PainelAlergias.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowRight, Clock, LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

interface Alergia {
    id: number;
    substancia: string;
    principio_ativo: string;
    gravidade: string;
    reacao: string | null;
}

interface EventoLinhaDoTempo {
    id: number;
    de: string | null;
    para: string;
    em: string | null;
    por: string | null;
    observacao: string | null;
    permanencia: string | null;
}

const props = defineProps<{
    atendimento: Record<string, string | number | boolean | null | string[]>;
    paciente: { user_id: number; nome: string; data_nascimento: string | null; idade: string | null };
    alergias: Alergia[];
    linhaDoTempo: EventoLinhaDoTempo[];
    transicoesPermitidas: { valor: string; rotulo: string; terminal: boolean }[];
    desfechos: string[];
}>();

const page = usePage<SharedData>();
const status = computed(() => page.props.flash?.status);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Atendimentos', href: '/atendimentos' },
    { title: props.paciente.nome, href: `/pacientes/${props.paciente.user_id}` },
    { title: String(props.atendimento.numero), href: '#' },
];

// Só as transições legais aparecem: a máquina de estados é a fonte, e o formulário não
// oferece o que a Action recusaria (RN-13).
const transicoesNaoTerminais = computed(() => props.transicoesPermitidas.filter((t) => !t.terminal));
const podeFinalizar = computed(() => props.transicoesPermitidas.some((t) => t.valor === 'FINALIZADO'));

// O cast fica aqui e nao no template: no template o `|` do union type e lido como
// filtro do Vue 2 pelo eslint-plugin-vue.
const prioridadeCor = computed(() => (props.atendimento.prioridade_cor as string | null) ?? null);
const prioridadeRotulo = computed(() => (props.atendimento.prioridade as string | null) ?? null);

const formStatus = useForm({ status: '', observacao: '' });
const formFinalizar = useForm({ desfecho: '', observacao: '' });

const alterar = (destino: string) => {
    formStatus.status = destino;
    formStatus.put(route('atendimentos.status', props.atendimento.id), { preserveScroll: true });
};

const finalizar = () => formFinalizar.post(route('atendimentos.finalizar', props.atendimento.id), { preserveScroll: true });
</script>

<template>
    <Head :title="`Atendimento ${atendimento.numero}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4">
            <div
                v-if="status"
                class="rounded-md bg-green-50 px-4 py-3 text-sm font-medium text-green-900 dark:bg-green-950 dark:text-green-100"
                role="status"
            >
                {{ status }}
            </div>

            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="font-mono text-2xl font-bold">{{ atendimento.numero }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">{{ paciente.nome }} · {{ paciente.data_nascimento }} · {{ paciente.idade }}</p>
                    <p class="mt-1 text-sm">
                        Situação: <strong>{{ atendimento.status_rotulo }}</strong>
                    </p>
                </div>
                <div class="flex flex-col items-start gap-2 sm:items-end">
                    <BadgePrioridade :cor="prioridadeCor" :rotulo="prioridadeRotulo" />
                    <div class="flex flex-wrap gap-3 text-xs">
                        <!--
                          UC-04: é a triagem que coloca o paciente na fila — não existe
                          "enviar para a fila" como ação separada, porque a posição deriva
                          da classificação de risco (RN-10). Sem este link, a única porta
                          para a triagem era a fila, onde o paciente só chega depois de
                          triado: um ciclo fechado.
                        -->
                        <Link :href="route('triagem.edit', atendimento.id)" class="underline underline-offset-4">
                            {{ prioridadeRotulo ? 'Triagem' : 'Classificar risco' }}
                        </Link>
                        <!-- UC-08: a linha do tempo clínica a um clique da administrativa. -->
                        <Link :href="route('prontuario.show', atendimento.id)" class="underline underline-offset-4">Prontuário</Link>
                    </div>
                </div>
            </header>

            <!-- RF-11: alergias em destaque em TODA tela do atendimento. -->
            <PainelAlergias :alergias="alergias" />

            <section v-if="transicoesNaoTerminais.length" class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Mudar situação</h2>
                <p class="mt-1 text-xs text-muted-foreground">
                    Apenas as transições permitidas a partir de "{{ atendimento.status_rotulo }}" são oferecidas (RN-13).
                </p>

                <div class="mt-3 flex flex-wrap gap-2">
                    <Button
                        v-for="transicao in transicoesNaoTerminais"
                        :key="transicao.valor"
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="formStatus.processing"
                        @click="alterar(transicao.valor)"
                    >
                        <ArrowRight class="h-4 w-4" aria-hidden="true" />
                        {{ transicao.rotulo }}
                    </Button>
                </div>

                <InputError :message="formStatus.errors.status" class="mt-2" />
            </section>

            <!-- RN-14: finalizar exige desfecho, e por isso tem formulário próprio. -->
            <section v-if="podeFinalizar" class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Finalizar atendimento</h2>

                <form class="mt-3 grid gap-3 sm:grid-cols-[14rem_1fr_auto] sm:items-end" @submit.prevent="finalizar">
                    <div class="grid gap-2">
                        <Label for="desfecho">Desfecho</Label>
                        <select
                            id="desfecho"
                            v-model="formFinalizar.desfecho"
                            class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                            required
                        >
                            <option value="" disabled>Selecione</option>
                            <option v-for="desfecho in desfechos" :key="desfecho" :value="desfecho">{{ desfecho }}</option>
                        </select>
                        <InputError :message="formFinalizar.errors.desfecho" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="observacao-final">Observação</Label>
                        <Input id="observacao-final" v-model="formFinalizar.observacao" />
                    </div>

                    <Button type="submit" :disabled="formFinalizar.processing">
                        <LoaderCircle v-if="formFinalizar.processing" class="h-4 w-4 animate-spin" aria-hidden="true" />
                        Finalizar
                    </Button>
                </form>
            </section>

            <!-- RF-22: linha do tempo consolidada, lida direto do histórico append-only. -->
            <section class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                    <Clock class="h-4 w-4" aria-hidden="true" />
                    Linha do tempo
                </h2>

                <ol class="mt-4 space-y-4 border-l border-sidebar-border/70 pl-4 dark:border-sidebar-border">
                    <li v-for="evento in linhaDoTempo" :key="evento.id" class="relative">
                        <span class="absolute -left-[21px] top-1.5 h-2.5 w-2.5 rounded-full bg-neutral-400 dark:bg-neutral-500" aria-hidden="true" />
                        <p class="text-sm font-medium">
                            <template v-if="evento.de">{{ evento.de }} → </template>{{ evento.para }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ evento.em }}
                            <template v-if="evento.por"> · {{ evento.por }}</template>
                            <!-- RF-39: quanto tempo ficou no status anterior. -->
                            <template v-if="evento.permanencia"> · permaneceu {{ evento.permanencia }}</template>
                        </p>
                        <p v-if="evento.observacao" class="mt-1 text-xs italic text-muted-foreground">{{ evento.observacao }}</p>
                    </li>
                </ol>
            </section>
        </div>
    </AppLayout>
</template>
