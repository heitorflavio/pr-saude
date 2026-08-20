<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import BadgePrioridade from '@/components/sgh/BadgePrioridade.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

type Tempo = { minutos: number | null; amostra: number };
type Taxa = { percentual: number | null; quantidade: number; total: number };
type Indicadores = {
    periodo: { inicio: string; fim: string; unidade_id: number | null };
    tempo_porta_triagem: Tempo; tempo_porta_atendimento: Tempo; aderencia_tempo_alvo: Taxa;
    tempo_permanencia: Tempo; distribuicao_cor: { id: number; nome: string; cor: string; total: number }[];
    taxa_reclassificacao: Taxa; taxa_evasao: Taxa;
    produtividade_profissional: { profissional_id: number; nome: string; atendimentos: number; horas_plantao: number; por_hora: number | null }[];
    tempo_medio_status: { status: string; minutos: number; amostra: number }[];
};
const props = defineProps<{ indicadores: Indicadores; unidades: { id: number; nome: string }[] }>();
const inicio = ref(props.indicadores.periodo.inicio); const fim = ref(props.indicadores.periodo.fim); const unidadeId = ref(props.indicadores.periodo.unidade_id?.toString() ?? '');
const aplicar = () => router.get(route('indicadores.index'), { inicio: inicio.value, fim: fim.value, unidade_id: unidadeId.value || undefined }, { preserveState: true, replace: true });
const minutos = (valor: number | null) => valor === null ? 'Sem dados' : `${valor.toLocaleString('pt-BR')} min`;
const percentual = (valor: number | null) => valor === null ? 'Sem dados' : `${valor.toLocaleString('pt-BR')}%`;
const status = (valor: string) => valor.toLowerCase().replaceAll('_', ' ').replace(/^./, (c) => c.toUpperCase());
</script>

<template>
    <Head title="Indicadores operacionais" />
    <AppLayout :breadcrumbs="[{ title: 'Indicadores', href: '/indicadores' }]">
        <main class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4">
            <header><h1 class="text-2xl font-bold">Indicadores operacionais</h1><p class="text-sm text-muted-foreground">Os nove indicadores da jornada assistencial. Valores sem amostra são mostrados como “Sem dados”.</p></header>
            <form class="grid gap-3 rounded-xl border p-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_2fr_auto]" @submit.prevent="aplicar">
                <div><label for="inicio" class="text-sm font-medium">Início</label><input id="inicio" v-model="inicio" type="date" required class="mt-1 w-full rounded-md border bg-background px-3 py-2" /></div>
                <div><label for="fim" class="text-sm font-medium">Fim</label><input id="fim" v-model="fim" type="date" required class="mt-1 w-full rounded-md border bg-background px-3 py-2" /></div>
                <div><label for="unidade" class="text-sm font-medium">Unidade</label><select id="unidade" v-model="unidadeId" class="mt-1 w-full rounded-md border bg-background px-3 py-2"><option value="">Todas</option><option v-for="u in unidades" :key="u.id" :value="u.id">{{ u.nome }}</option></select></div>
                <button type="submit" class="self-end rounded-md bg-primary px-4 py-2 text-primary-foreground">Aplicar</button>
            </form>

            <section aria-label="Indicadores principais" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-xl border p-4"><h2 class="text-sm font-medium">Tempo porta–triagem</h2><p class="mt-2 text-2xl font-bold">{{ minutos(indicadores.tempo_porta_triagem.minutos) }}</p><p class="text-xs text-muted-foreground">{{ indicadores.tempo_porta_triagem.amostra }} triagens</p></article>
                <article class="rounded-xl border p-4"><h2 class="text-sm font-medium">Tempo porta–atendimento</h2><p class="mt-2 text-2xl font-bold">{{ minutos(indicadores.tempo_porta_atendimento.minutos) }}</p><p class="text-xs text-muted-foreground">{{ indicadores.tempo_porta_atendimento.amostra }} atendimentos</p></article>
                <article class="rounded-xl border p-4"><h2 class="text-sm font-medium">Aderência ao tempo-alvo</h2><p class="mt-2 text-2xl font-bold">{{ percentual(indicadores.aderencia_tempo_alvo.percentual) }}</p><p class="text-xs text-muted-foreground">{{ indicadores.aderencia_tempo_alvo.quantidade }} de {{ indicadores.aderencia_tempo_alvo.total }}</p></article>
                <article class="rounded-xl border p-4"><h2 class="text-sm font-medium">Tempo de permanência</h2><p class="mt-2 text-2xl font-bold">{{ minutos(indicadores.tempo_permanencia.minutos) }}</p><p class="text-xs text-muted-foreground">{{ indicadores.tempo_permanencia.amostra }} encerrados</p></article>
                <article class="rounded-xl border p-4"><h2 class="text-sm font-medium">Taxa de reclassificação</h2><p class="mt-2 text-2xl font-bold">{{ percentual(indicadores.taxa_reclassificacao.percentual) }}</p><p class="text-xs text-muted-foreground">{{ indicadores.taxa_reclassificacao.quantidade }} de {{ indicadores.taxa_reclassificacao.total }} triados</p></article>
                <article class="rounded-xl border p-4"><h2 class="text-sm font-medium">Taxa de evasão</h2><p class="mt-2 text-2xl font-bold">{{ percentual(indicadores.taxa_evasao.percentual) }}</p><p class="text-xs text-muted-foreground">{{ indicadores.taxa_evasao.quantidade }} de {{ indicadores.taxa_evasao.total }} encerrados</p></article>
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="rounded-xl border p-4"><h2 class="mb-3 text-lg font-semibold">Distribuição por prioridade</h2><ul class="space-y-2"><li v-for="item in indicadores.distribuicao_cor" :key="item.id" class="flex items-center justify-between rounded-md border p-3"><BadgePrioridade :cor="item.cor" :rotulo="item.nome" /><strong>{{ item.total }}</strong></li></ul></section>
                <section class="rounded-xl border p-4"><h2 class="mb-3 text-lg font-semibold">Tempo médio em cada status</h2><table class="w-full text-left text-sm"><thead><tr><th class="pb-2">Status</th><th class="pb-2 text-right">Média</th><th class="pb-2 text-right">Amostra</th></tr></thead><tbody><tr v-for="item in indicadores.tempo_medio_status" :key="item.status" class="border-t"><td class="py-2">{{ status(item.status) }}</td><td class="py-2 text-right">{{ minutos(item.minutos) }}</td><td class="py-2 text-right">{{ item.amostra }}</td></tr><tr v-if="!indicadores.tempo_medio_status.length"><td colspan="3" class="py-6 text-center text-muted-foreground">Sem dados</td></tr></tbody></table></section>
            </div>

            <section class="rounded-xl border p-4"><h2 class="mb-3 text-lg font-semibold">Produtividade por profissional</h2><div class="overflow-x-auto"><table class="w-full min-w-[650px] text-left text-sm"><thead><tr><th class="pb-2">Profissional</th><th class="pb-2 text-right">Atendimentos concluídos</th><th class="pb-2 text-right">Horas de plantão</th><th class="pb-2 text-right">Atendimentos/hora</th></tr></thead><tbody><tr v-for="item in indicadores.produtividade_profissional" :key="item.profissional_id" class="border-t"><td class="py-2 font-medium">{{ item.nome }}</td><td class="py-2 text-right">{{ item.atendimentos }}</td><td class="py-2 text-right">{{ item.horas_plantao.toLocaleString('pt-BR') }}</td><td class="py-2 text-right">{{ item.por_hora?.toLocaleString('pt-BR') ?? 'Sem horas registradas' }}</td></tr><tr v-if="!indicadores.produtividade_profissional.length"><td colspan="4" class="py-6 text-center text-muted-foreground">Sem dados</td></tr></tbody></table></div></section>
        </main>
    </AppLayout>
</template>
