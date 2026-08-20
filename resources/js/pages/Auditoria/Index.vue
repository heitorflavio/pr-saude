<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

type Log = {
    id: number;
    quando: string;
    usuario: string;
    perfis: string | null;
    acao: string;
    paciente: string | null;
    paciente_id: number | null;
    justificativa: string | null;
    ip: string | null;
};
type Pagina = { data: Log[]; links: { url: string | null; label: string; active: boolean }[]; total: number };

const props = defineProps<{
    logs: Pagina;
    filtros: { paciente_id?: number; acao?: string };
    pacienteSelecionado: { id: number; nome: string } | null;
    desde: string;
}>();
const pacienteId = ref(props.filtros.paciente_id?.toString() ?? '');
const acao = ref(props.filtros.acao ?? '');
const filtrar = () =>
    router.get(
        route('auditoria.index'),
        { paciente_id: pacienteId.value || undefined, acao: acao.value || undefined },
        { preserveState: true, replace: true },
    );
const rotuloPagina = (label: string) => (label.includes('Previous') ? 'Anterior' : label.includes('Next') ? 'Próxima' : label);
</script>

<template>
    <Head title="Auditoria" />
    <AppLayout :breadcrumbs="[{ title: 'Auditoria', href: '/auditoria' }]">
        <main class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4">
            <header>
                <h1 class="text-2xl font-bold">Trilha de auditoria</h1>
                <p class="text-sm text-muted-foreground">Acessos e operações dos últimos 90 dias, desde {{ desde }}.</p>
            </header>

            <form class="grid gap-3 rounded-xl border p-4 md:grid-cols-[1fr_2fr_auto]" @submit.prevent="filtrar">
                <div>
                    <label for="paciente_id" class="text-sm font-medium">Código do paciente</label>
                    <input
                        id="paciente_id"
                        v-model="pacienteId"
                        type="number"
                        min="1"
                        class="mt-1 w-full rounded-md border bg-background px-3 py-2"
                    />
                </div>
                <div>
                    <label for="acao" class="text-sm font-medium">Ação contém</label>
                    <input
                        id="acao"
                        v-model="acao"
                        type="search"
                        class="mt-1 w-full rounded-md border bg-background px-3 py-2"
                        placeholder="Ex.: prontuario.ler"
                    />
                </div>
                <button type="submit" class="self-end rounded-md bg-primary px-4 py-2 text-primary-foreground">Filtrar</button>
            </form>

            <p v-if="pacienteSelecionado" class="rounded-md bg-muted p-3 text-sm">
                Respondendo por: <strong>{{ pacienteSelecionado.nome }}</strong> (paciente {{ pacienteSelecionado.id }}).
            </p>

            <p class="text-xs text-muted-foreground md:hidden">Deslize horizontalmente para consultar todos os dados da auditoria.</p>
            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <caption class="sr-only">
                        Eventos de acesso e alteração sobre dados clínicos
                    </caption>
                    <thead class="bg-muted">
                        <tr>
                            <th class="p-3">Quando</th>
                            <th class="p-3">Quem</th>
                            <th class="p-3">Ação</th>
                            <th class="p-3">Paciente</th>
                            <th class="p-3">Origem</th>
                            <th class="p-3">Justificativa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in logs.data" :key="log.id" class="border-t align-top">
                            <td class="whitespace-nowrap p-3">{{ log.quando }}</td>
                            <td class="p-3">
                                <strong>{{ log.usuario }}</strong
                                ><br /><span class="text-xs text-muted-foreground">{{ log.perfis || 'sem perfil' }}</span>
                            </td>
                            <td class="p-3 font-mono text-xs">{{ log.acao }}</td>
                            <td class="p-3">
                                {{ log.paciente || '—' }}<br v-if="log.paciente_id" /><span v-if="log.paciente_id" class="text-xs"
                                    >#{{ log.paciente_id }}</span
                                >
                            </td>
                            <td class="p-3 font-mono text-xs">{{ log.ip || '—' }}</td>
                            <td class="max-w-sm p-3">{{ log.justificativa || '—' }}</td>
                        </tr>
                        <tr v-if="!logs.data.length">
                            <td colspan="6" class="p-8 text-center text-muted-foreground">Nenhum evento encontrado.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="logs.links.length > 3" aria-label="Paginação" class="flex flex-wrap gap-1">
                <Link
                    v-for="link in logs.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    :aria-current="link.active ? 'page' : undefined"
                    :class="[
                        'rounded border px-3 py-1.5 text-sm',
                        { 'bg-primary text-primary-foreground': link.active, 'pointer-events-none opacity-40': !link.url },
                    ]"
                    >{{ rotuloPagina(link.label) }}</Link
                >
            </nav>
        </main>
    </AppLayout>
</template>
