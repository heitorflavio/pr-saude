<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, ShieldAlert } from 'lucide-vue-next';

defineProps<{
    doses: {
        aprazamento_id: number;
        horario_previsto: string;
        atendimento_id: number;
        atendimento_numero: string;
        paciente_nome: string;
        nome_comercial: string;
        principio_ativo: string;
        alta_vigilancia: boolean | number;
        dose: string;
        unidade_dose: string;
        via: string;
        atrasada: boolean | number;
    }[];
}>();

// RF-60: checklist vivo do turno; a view decide pendência e atraso.
usePoll(10000, { only: ['doses'] });
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Medicamentos', href: '/medicamentos' }];
</script>

<template>
    <Head title="Doses do turno" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="mx-auto flex w-full max-w-6xl flex-col gap-5 p-4">
            <header>
                <h1 class="text-2xl font-bold">Doses pendentes do turno</h1>
                <p class="text-sm text-muted-foreground">Checklist atualizado a cada 10 segundos. Atraso não muda prioridade clínica.</p>
            </header>

            <ul v-if="doses.length" class="grid gap-3" aria-label="Doses pendentes">
                <li v-for="dose in doses" :key="dose.aprazamento_id" class="grid gap-3 rounded-xl border p-4 md:grid-cols-[9rem_1fr_auto]">
                    <div>
                        <p class="font-mono text-sm font-semibold">{{ dose.horario_previsto }}</p>
                        <span
                            v-if="dose.atrasada"
                            class="mt-2 inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-900 dark:bg-red-950 dark:text-red-100"
                        >
                            <AlertTriangle class="h-3 w-3" aria-hidden="true" /> Atrasada
                        </span>
                        <span
                            v-else
                            class="mt-2 inline-flex items-center gap-1 rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-semibold text-neutral-800 dark:bg-neutral-800 dark:text-neutral-100"
                        >
                            <CheckCircle2 class="h-3 w-3" aria-hidden="true" /> No horário
                        </span>
                    </div>
                    <div>
                        <p class="font-semibold">
                            {{ dose.paciente_nome }} <span class="font-mono text-xs text-muted-foreground">{{ dose.atendimento_numero }}</span>
                        </p>
                        <p class="mt-1">
                            {{ dose.nome_comercial }} <span class="text-sm text-muted-foreground">({{ dose.principio_ativo }})</span>
                        </p>
                        <p class="text-sm">{{ dose.dose }} {{ dose.unidade_dose }} · {{ dose.via }}</p>
                        <p v-if="dose.alta_vigilancia" class="mt-1 flex items-center gap-1 text-sm font-semibold text-amber-800 dark:text-amber-300">
                            <ShieldAlert class="h-4 w-4" aria-hidden="true" /> Alta vigilância — dupla checagem
                        </p>
                    </div>
                    <Button as-child><Link :href="route('medicamentos.conferir', dose.aprazamento_id)">Conferir dose</Link></Button>
                </li>
            </ul>
            <p v-else class="rounded-xl border border-dashed p-8 text-center text-muted-foreground">Nenhuma dose pendente.</p>
        </main>
    </AppLayout>
</template>
