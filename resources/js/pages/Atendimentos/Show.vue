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
import { ArrowRight, Clock, LoaderCircle, TriangleAlert } from 'lucide-vue-next';
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

/** Um módulo do episódio: o que ele já tem, e o ato que o alimenta. */
interface Modulo {
    rotulo: string;
    href: string;
    acao: string;
    total: number;
    resumo: string;
    alerta?: boolean;
    liberado: boolean;
}

const props = defineProps<{
    atendimento: Record<string, string | number | boolean | null | string[]>;
    paciente: { user_id: number; nome: string; data_nascimento: string | null; idade: string | null };
    alergias: Alergia[];
    modulos: Record<string, Modulo>;
    pendencia: { texto: string; acao: string; href: string } | null;
    linhaDoTempo: EventoLinhaDoTempo[];
    transicoesPermitidas: { valor: string; rotulo: string; terminal: boolean }[];
    desfechos: string[];
}>();

/*
 * Só os módulos que o usuário pode abrir. Esconder é conveniência, não segurança — a
 * autorização real está nas Policies; um card que leva a 403 gasta o clique de quem está
 * no meio de um plantão.
 */
const modulosVisiveis = computed<Modulo[]>(() => Object.values(props.modulos).filter((modulo: Modulo) => modulo.liberado));

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
                <BadgePrioridade :cor="prioridadeCor" :rotulo="prioridadeRotulo" />
            </header>

            <!--
              O que o estado atual está esperando.

              "Aguardando medicação" sem nenhuma prescrição não é defeito do sistema: é um
              passo que ninguém deu. Mudar a situação descreve onde o paciente está, não
              cria prescrição nem pedido de exame — são atos à parte, e antes deste aviso
              nada na tela dizia isso.
            -->
            <div
                v-if="pendencia"
                class="flex flex-wrap items-start justify-between gap-3 rounded-xl border-l-4 border-amber-600 bg-amber-50 px-4 py-3 dark:bg-amber-950/40"
                role="status"
            >
                <p class="flex items-start gap-2 text-sm text-amber-900 dark:text-amber-100">
                    <TriangleAlert class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                    {{ pendencia.texto }}
                </p>
                <Link :href="pendencia.href">
                    <Button type="button" size="sm">{{ pendencia.acao }}</Button>
                </Link>
            </div>

            <!--
              UC-04, UC-08, UC-09, UC-11: os módulos do episódio, cada um com o que já tem
              e o ato que o alimenta. Esta seção é a que faltava: as telas de prescrição e
              de solicitação de exame existiam sem nenhum link apontando para elas.
            -->
            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="modulo in modulosVisiveis"
                    :key="modulo.rotulo"
                    class="flex flex-col justify-between gap-3 rounded-xl border p-4"
                    :class="
                        modulo.alerta
                            ? 'border-red-600/50 bg-red-50/60 dark:border-red-500/50 dark:bg-red-950/20'
                            : 'border-sidebar-border/70 dark:border-sidebar-border'
                    "
                >
                    <div>
                        <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                            {{ modulo.rotulo }}
                            <span v-if="modulo.total > 0" class="rounded-full bg-neutral-200 px-2 py-0.5 text-xs dark:bg-neutral-700">
                                {{ modulo.total }}
                            </span>
                        </h2>
                        <p class="mt-1.5 text-sm" :class="modulo.alerta ? 'font-semibold text-red-800 dark:text-red-200' : ''">
                            {{ modulo.resumo }}
                        </p>
                    </div>

                    <Link :href="modulo.href" class="text-sm font-medium underline underline-offset-4">{{ modulo.acao }}</Link>
                </article>
            </section>

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
