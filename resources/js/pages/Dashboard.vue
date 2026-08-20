<script setup lang="ts">
import BadgePrioridade from '@/components/sgh/BadgePrioridade.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { AlertTriangle, ArrowRight, ClipboardList, Clock, FlaskConical, ListOrdered, Pill, ShieldAlert, UserPlus, Users } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Painel inicial da equipe.
 *
 * Só leitura: cada número aqui é um atalho para a tela que tem a autorização contextual
 * de verdade. Os blocos que o usuário não pode ver não chegam nesta página — o servidor
 * não os monta —, por isso todo `v-if` abaixo é sobre presença de dado, não sobre papel.
 */
interface ItemFila {
    fila_item_id: number;
    posicao: number;
    atendimento_id: number;
    atendimento_numero: string;
    paciente_nome: string;
    prioridade: string;
    prioridade_cor: string;
    espera_minutos: number;
    tempo_alvo_excedido: boolean;
    situacao_rotulo: string;
    sugere_reavaliacao: boolean;
}

interface Dose {
    aprazamento_id: number;
    atendimento_id: number;
    paciente: string;
    medicamento: string;
    principio_ativo: string;
    dose: string;
    alta_vigilancia: boolean;
    horario_previsto: string;
    atrasada: boolean;
}

interface Painel {
    fila?: {
        total: number;
        sem_profissional: number;
        alem_do_alvo: number;
        distribuicao: { id: number; nome: string; cor: string; tempo_alvo_minutos: number; total: number }[];
        aguardando_atribuicao: ItemFila[];
    };
    minha_fila?: { total: number; alem_do_alvo: number; proximos: ItemFila[] };
    atendimentos?: {
        ativos: number;
        em_atendimento: number;
        por_status: { status: string; rotulo: string; total: number }[];
        admitidos_hoje: number;
        finalizados_hoje: number;
    };
    doses?: { pendentes: number; atrasadas: number; proximas: Dose[] };
    exames?: { a_coletar: number; em_execucao: number; aguardando_liberacao: number };
}

const props = defineProps<{
    painel: Painel;
    contexto: {
        nome: string;
        categoria: string | null;
        unidade: string | null;
        pode: Record<string, boolean>;
    };
}>();

/*
 * Mesmo padrão da fila (RF-34 / RNF-03), com ciclo mais folgado: o painel é panorâmico,
 * não é a tela de onde se chama paciente. O `only` é o que torna o polling barato —
 * contexto e permissões não trafegam a cada ciclo.
 */
usePoll(15000, { only: ['painel'] });

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Painel', href: '/dashboard' }];

// A composição da fila é lida como proporção; o número absoluto vai sempre ao lado da
// barra, porque uma barra sozinha não diz quantos pacientes são (RNF-15).
const totalFila = computed(() => props.painel.fila?.total ?? 0);
const proporcao = (total: number) => (totalFila.value === 0 ? 0 : Math.round((total * 100) / totalFila.value));

const barra: Record<string, string> = {
    VERMELHO: 'bg-red-600',
    LARANJA: 'bg-orange-500',
    AMARELO: 'bg-amber-400',
    VERDE: 'bg-green-600',
    AZUL: 'bg-sky-600',
};

const espera = (minutos: number) => (minutos < 60 ? `${minutos} min` : `${Math.floor(minutos / 60)} h ${minutos % 60} min`);

const statusComPaciente = computed(() => props.painel.atendimentos?.por_status.filter((s) => s.total > 0) ?? []);

// O auditor tem `auditoria.ler` e mais nada (doc §2.1): ele consulta a trilha, não
// conduz o plantão. Uma página vazia pareceria erro — dizer o porquê é melhor que sumir.
const semBlocos = computed(() => Object.keys(props.painel).length === 0);
</script>

<template>
    <Head title="Painel" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <header class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">Olá, {{ contexto.nome }}</h1>
                    <p class="text-sm text-muted-foreground">
                        Situação do plantão agora<span v-if="contexto.unidade"> — {{ contexto.unidade }}</span
                        >. Os números se atualizam a cada 15 segundos.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button v-if="contexto.pode.criar_paciente" as-child>
                        <Link :href="route('pacientes.create')"> <UserPlus class="mr-2 h-4 w-4" /> Novo paciente </Link>
                    </Button>
                    <Button v-if="contexto.pode.fila" variant="outline" as-child>
                        <Link :href="route('fila.index')"> <ListOrdered class="mr-2 h-4 w-4" /> Fila </Link>
                    </Button>
                    <Button v-if="contexto.pode.indicadores" variant="outline" as-child>
                        <Link :href="route('indicadores.index')">Indicadores</Link>
                    </Button>
                </div>
            </header>

            <Card v-if="semBlocos">
                <CardContent class="py-6">
                    <p class="text-sm text-muted-foreground">
                        Seu perfil não acompanha o plantão em tempo real. As telas disponíveis para você estão na barra lateral.
                    </p>
                </CardContent>
            </Card>

            <section aria-label="Resumo do plantão" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Card v-if="painel.fila">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Na fila agora</CardTitle>
                        <Users class="h-4 w-4 text-muted-foreground" aria-hidden="true" />
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">{{ painel.fila.total }}</p>
                        <p class="text-xs text-muted-foreground">{{ painel.fila.sem_profissional }} sem profissional atribuído</p>
                    </CardContent>
                </Card>

                <!-- RF-33: quem passou do tempo-alvo da própria cor. O painel sinaliza; a
                     reclassificação continua sendo decisão clínica (doc §7.3.1). -->
                <Card v-if="painel.fila" :class="painel.fila.alem_do_alvo > 0 ? 'border-amber-500/60' : ''">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Além do tempo-alvo</CardTitle>
                        <AlertTriangle class="h-4 w-4 text-muted-foreground" aria-hidden="true" />
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">{{ painel.fila.alem_do_alvo }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ painel.fila.alem_do_alvo > 0 ? 'Reavaliação de risco sugerida' : 'Nenhuma espera excedida' }}
                        </p>
                    </CardContent>
                </Card>

                <Card v-if="painel.minha_fila">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Minha fila</CardTitle>
                        <ClipboardList class="h-4 w-4 text-muted-foreground" aria-hidden="true" />
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">{{ painel.minha_fila.total }}</p>
                        <p class="text-xs text-muted-foreground">{{ painel.minha_fila.alem_do_alvo }} além do tempo-alvo</p>
                    </CardContent>
                </Card>

                <Card v-if="painel.atendimentos">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Atendimentos abertos</CardTitle>
                        <Clock class="h-4 w-4 text-muted-foreground" aria-hidden="true" />
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">{{ painel.atendimentos.ativos }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ painel.atendimentos.admitidos_hoje }} admitidos hoje · {{ painel.atendimentos.finalizados_hoje }} finalizados
                        </p>
                    </CardContent>
                </Card>

                <Card v-if="painel.doses" :class="painel.doses.atrasadas > 0 ? 'border-amber-500/60' : ''">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Doses pendentes</CardTitle>
                        <Pill class="h-4 w-4 text-muted-foreground" aria-hidden="true" />
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">{{ painel.doses.pendentes }}</p>
                        <p class="text-xs text-muted-foreground">{{ painel.doses.atrasadas }} com mais de 30 min de atraso</p>
                    </CardContent>
                </Card>

                <Card v-if="painel.exames">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Exames em curso</CardTitle>
                        <FlaskConical class="h-4 w-4 text-muted-foreground" aria-hidden="true" />
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">{{ painel.exames.a_coletar + painel.exames.em_execucao }}</p>
                        <!-- RN-24: concluído não é liberado. O resultado só chega ao
                             paciente depois da liberação explícita. -->
                        <p class="text-xs text-muted-foreground">{{ painel.exames.aguardando_liberacao }} aguardando liberação</p>
                    </CardContent>
                </Card>
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <Card v-if="painel.fila">
                    <CardHeader>
                        <CardTitle>Composição da fila por prioridade</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div v-for="nivel in painel.fila.distribuicao" :key="nivel.id" class="space-y-1">
                            <div class="flex items-center justify-between gap-2">
                                <BadgePrioridade :cor="nivel.cor" :rotulo="nivel.nome" />
                                <span class="text-sm">
                                    <strong>{{ nivel.total }}</strong>
                                    <span class="text-muted-foreground"> · alvo {{ nivel.tempo_alvo_minutos }} min</span>
                                </span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                                <div class="h-full rounded-full" :class="barra[nivel.cor]" :style="{ width: `${proporcao(nivel.total)}%` }" />
                            </div>
                        </div>
                        <p v-if="painel.fila.total === 0" class="text-sm text-muted-foreground">Nenhum paciente na fila neste momento.</p>
                    </CardContent>
                </Card>

                <Card v-if="painel.atendimentos">
                    <CardHeader>
                        <CardTitle>Onde estão os pacientes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="divide-y">
                            <li v-for="item in statusComPaciente" :key="item.status" class="flex items-center justify-between py-2 text-sm">
                                <span>{{ item.rotulo }}</span>
                                <strong>{{ item.total }}</strong>
                            </li>
                        </ul>
                        <p v-if="!statusComPaciente.length" class="text-sm text-muted-foreground">Nenhum atendimento aberto neste momento.</p>
                    </CardContent>
                </Card>
            </div>

            <Card v-if="painel.minha_fila && painel.minha_fila.proximos.length">
                <CardHeader class="flex flex-row items-center justify-between space-y-0">
                    <CardTitle>Próximos da minha fila</CardTitle>
                    <Link :href="route('fila.index')" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline">
                        Ver fila <ArrowRight class="h-3.5 w-3.5" aria-hidden="true" />
                    </Link>
                </CardHeader>
                <CardContent>
                    <ul class="divide-y">
                        <li
                            v-for="item in painel.minha_fila.proximos"
                            :key="item.fila_item_id"
                            class="flex flex-wrap items-center justify-between gap-2 py-2"
                        >
                            <div class="flex items-center gap-3">
                                <span class="w-6 text-sm text-muted-foreground">{{ item.posicao }}º</span>
                                <div>
                                    <Link :href="route('atendimentos.show', item.atendimento_id)" class="font-medium hover:underline">
                                        {{ item.paciente_nome }}
                                    </Link>
                                    <p class="text-xs text-muted-foreground">Atendimento {{ item.atendimento_numero }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <BadgePrioridade :cor="item.prioridade_cor" :rotulo="item.prioridade" />
                                <span class="text-sm" :class="item.tempo_alvo_excedido ? 'font-semibold text-amber-700 dark:text-amber-300' : ''">
                                    {{ espera(item.espera_minutos) }} · {{ item.situacao_rotulo }}
                                </span>
                            </div>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card v-if="painel.fila">
                <CardHeader class="flex flex-row items-center justify-between space-y-0">
                    <CardTitle>Aguardando profissional</CardTitle>
                    <Link
                        :href="route('fila.index', { fila: 'geral' })"
                        class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
                    >
                        Ver fila geral <ArrowRight class="h-3.5 w-3.5" aria-hidden="true" />
                    </Link>
                </CardHeader>
                <CardContent>
                    <ul class="divide-y">
                        <li
                            v-for="item in painel.fila.aguardando_atribuicao"
                            :key="item.fila_item_id"
                            class="flex flex-wrap items-center justify-between gap-2 py-2"
                        >
                            <div class="flex items-center gap-3">
                                <span class="w-6 text-sm text-muted-foreground">{{ item.posicao }}º</span>
                                <div>
                                    <Link :href="route('atendimentos.show', item.atendimento_id)" class="font-medium hover:underline">
                                        {{ item.paciente_nome }}
                                    </Link>
                                    <p class="text-xs text-muted-foreground">Atendimento {{ item.atendimento_numero }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <BadgePrioridade :cor="item.prioridade_cor" :rotulo="item.prioridade" />
                                <span class="text-sm" :class="item.sugere_reavaliacao ? 'font-semibold text-amber-700 dark:text-amber-300' : ''">
                                    {{ espera(item.espera_minutos) }} · {{ item.situacao_rotulo }}
                                </span>
                                <Button v-if="contexto.pode.fila" variant="outline" size="sm" as-child>
                                    <Link :href="route('fila.atribuir', item.atendimento_id)">Atribuir</Link>
                                </Button>
                            </div>
                        </li>
                    </ul>
                    <p v-if="!painel.fila.aguardando_atribuicao.length" class="text-sm text-muted-foreground">
                        Todo mundo na fila já tem profissional.
                    </p>
                </CardContent>
            </Card>

            <Card v-if="painel.doses">
                <CardHeader class="flex flex-row items-center justify-between space-y-0">
                    <CardTitle>Próximas doses</CardTitle>
                    <Link :href="route('medicamentos.index')" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline">
                        Ver checklist <ArrowRight class="h-3.5 w-3.5" aria-hidden="true" />
                    </Link>
                </CardHeader>
                <CardContent>
                    <ul class="divide-y">
                        <li
                            v-for="dose in painel.doses.proximas"
                            :key="dose.aprazamento_id"
                            class="flex flex-wrap items-center justify-between gap-2 py-2"
                        >
                            <div>
                                <p class="font-medium">{{ dose.paciente }}</p>
                                <p class="text-xs text-muted-foreground">{{ dose.medicamento }} ({{ dose.principio_ativo }}) · {{ dose.dose }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                <!-- RN-22: alta vigilância exige um segundo profissional,
                                     distinto do executor. Avisar aqui evita a ida ao leito
                                     sem ter quem confira. -->
                                <span
                                    v-if="dose.alta_vigilancia"
                                    class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-semibold text-purple-900 ring-1 ring-inset ring-purple-600/40 dark:bg-purple-950 dark:text-purple-100 dark:ring-purple-400/40"
                                >
                                    <ShieldAlert class="h-3.5 w-3.5" aria-hidden="true" /> Alta vigilância
                                </span>
                                <span :class="dose.atrasada ? 'font-semibold text-amber-700 dark:text-amber-300' : ''">
                                    {{ dose.horario_previsto }}<template v-if="dose.atrasada"> · atrasada</template>
                                </span>
                            </div>
                        </li>
                    </ul>
                    <p v-if="!painel.doses.proximas.length" class="text-sm text-muted-foreground">Nenhuma dose pendente neste momento.</p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
