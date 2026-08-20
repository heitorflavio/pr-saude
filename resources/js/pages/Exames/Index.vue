<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { AlertTriangle, Clock3, FlaskConical } from 'lucide-vue-next';

defineProps<{
    fila: {
        id: number;
        carater: string;
        situacao: string;
        situacao_rotulo: string;
        solicitado_em: string;
        exame: string;
        preparo: string | null;
        atendimento: string;
        paciente: string;
    }[];
}>();

usePoll(10000, { only: ['fila'] });
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Exames', href: '/exames' }];
</script>

<template>
    <Head title="Fila do laboratório" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="mx-auto flex w-full max-w-6xl flex-col gap-5 p-4">
            <header>
                <h1 class="flex items-center gap-2 text-2xl font-bold"><FlaskConical aria-hidden="true" /> Fila do laboratório</h1>
                <p class="text-sm text-muted-foreground">Urgentes primeiro; dentro do mesmo caráter, por ordem de solicitação.</p>
            </header>
            <ul v-if="fila.length" class="grid gap-3">
                <li v-for="item in fila" :key="item.id" class="grid gap-3 rounded-xl border p-4 md:grid-cols-[8rem_1fr_auto]">
                    <div>
                        <span
                            v-if="item.carater === 'URGENTE'"
                            class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-1 text-xs font-bold text-red-900 dark:bg-red-950 dark:text-red-100"
                            ><AlertTriangle class="h-3.5 w-3.5" aria-hidden="true" /> Urgente</span
                        >
                        <span
                            v-else
                            class="inline-flex items-center gap-1 rounded-full bg-neutral-100 px-2 py-1 text-xs font-semibold dark:bg-neutral-800"
                        >
                            <Clock3 class="h-3.5 w-3.5" aria-hidden="true" /> Rotina
                        </span>
                    </div>
                    <div>
                        <strong>{{ item.exame }}</strong>
                        <p>
                            {{ item.paciente }} · <span class="font-mono text-sm">{{ item.atendimento }}</span>
                        </p>
                        <p class="text-xs text-muted-foreground">{{ item.situacao_rotulo }} · solicitado em {{ item.solicitado_em }}</p>
                        <p v-if="item.preparo" class="mt-1 text-xs">Preparo: {{ item.preparo }}</p>
                    </div>
                    <Link :href="route('exames.show', item.id)" class="self-center rounded-md border px-3 py-2 text-sm font-medium">Abrir</Link>
                </li>
            </ul>
            <p v-else class="rounded-xl border border-dashed p-8 text-center text-muted-foreground">Fila vazia.</p>
        </main>
    </AppLayout>
</template>
