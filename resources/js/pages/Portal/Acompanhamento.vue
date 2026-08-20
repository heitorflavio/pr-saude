<script setup lang="ts">
import BadgePrioridade from '@/components/sgh/BadgePrioridade.vue';
import PortalLayout from '@/layouts/PortalLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Clock3 } from 'lucide-vue-next';

defineProps<{
    paciente: { nome: string };
    atendimento: null | {
        uuid: string;
        numero: string;
        status: string;
        admitido_em: string;
        tempo_decorrido_minutos: number;
        posicao_fila: number | null;
        prioridade: string | null;
        prioridade_cor: string | null;
    };
    resumo: { medicamentos: number; exames: number; atendimentos: number };
}>();
const tempo = (min: number) => (min < 60 ? `${min} min` : `${Math.floor(min / 60)} h ${min % 60} min`);
</script>

<template>
    <Head title="Meu atendimento" /><PortalLayout
        ><main class="mx-auto flex max-w-5xl flex-col gap-5 p-4">
            <header>
                <p class="text-sm text-muted-foreground">Olá,</p>
                <h1 class="text-2xl font-bold">{{ paciente.nome }}</h1>
            </header>
            <section v-if="atendimento" class="grid gap-4 rounded-2xl border bg-white p-5 dark:bg-neutral-900">
                <div class="flex flex-wrap justify-between gap-3">
                    <div>
                        <p class="text-sm text-muted-foreground">Situação atual</p>
                        <h2 class="text-xl font-bold">{{ atendimento.status }}</h2>
                        <p class="font-mono text-xs text-muted-foreground">{{ atendimento.numero }}</p>
                    </div>
                    <BadgePrioridade :cor="atendimento.prioridade_cor" :rotulo="atendimento.prioridade" />
                </div>
                <p class="flex items-center gap-2">
                    <Clock3 class="h-5 w-5" aria-hidden="true" /> Tempo desde a chegada:
                    <strong>{{ tempo(atendimento.tempo_decorrido_minutos) }}</strong>
                </p>
                <p v-if="atendimento.posicao_fila">
                    Sua posição atual na fila: <strong>{{ atendimento.posicao_fila }}</strong>
                </p>
                <p class="text-sm text-muted-foreground">
                    A posição pode mudar conforme a gravidade clínica de quem chega. Por segurança, não exibimos previsão de horário.
                </p>
                <Link :href="route('portal.atendimento', atendimento.uuid)" class="justify-self-start rounded-md border px-3 py-2 text-sm font-medium"
                    >Ver evolução do atendimento</Link
                >
            </section>
            <section v-else class="rounded-2xl border border-dashed p-6">
                <h2 class="font-semibold">Nenhum atendimento em andamento</h2>
                <p class="text-sm text-muted-foreground">Seu histórico recente continua disponível abaixo.</p>
            </section>
            <div class="grid gap-3 sm:grid-cols-3">
                <Link href="/portal/medicamentos" class="rounded-xl border bg-white p-4 dark:bg-neutral-900"
                    ><strong>{{ resumo.medicamentos }}</strong
                    ><span class="block text-sm">medicamentos administrados</span></Link
                ><Link href="/portal/exames" class="rounded-xl border bg-white p-4 dark:bg-neutral-900"
                    ><strong>{{ resumo.exames }}</strong
                    ><span class="block text-sm">exames solicitados</span></Link
                ><Link href="/portal/historico" class="rounded-xl border bg-white p-4 dark:bg-neutral-900"
                    ><strong>{{ resumo.atendimentos }}</strong
                    ><span class="block text-sm">atendimentos no histórico</span></Link
                >
            </div>
        </main></PortalLayout
    >
</template>
