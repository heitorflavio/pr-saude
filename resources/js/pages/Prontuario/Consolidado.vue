<script setup lang="ts">
import BadgePrioridade from '@/components/sgh/BadgePrioridade.vue';
import PainelAlergias from '@/components/sgh/PainelAlergias.vue';
import RegistroClinicoCard from '@/components/sgh/RegistroClinico.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { type Alergia, type Episodio } from '@/types/prontuario';
import { Head, Link } from '@inertiajs/vue3';
import { FileDown, History } from 'lucide-vue-next';

/**
 * RF-51 — prontuário consolidado, atravessando todos os atendimentos.
 *
 * A visão por atendimento é a que o plantão usa; esta é a que evita o erro mais caro do
 * pronto-socorro: tratar cada vinda como se fosse a primeira. Três passagens em duas
 * semanas pela mesma queixa são um dado clínico — e ele só aparece quando os episódios
 * são lidos juntos.
 *
 * Por isso os episódios vêm expandidos por padrão, do mais recente ao mais antigo: um
 * acordeão fechado transformaria o histórico em algo que só quem já desconfia vai abrir.
 */
const props = defineProps<{
    paciente: { user_id: number; nome: string; idade: string | null; data_nascimento: string | null };
    alergias: Alergia[];
    episodios: Episodio[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pacientes', href: '/pacientes' },
    { title: props.paciente.nome, href: `/pacientes/${props.paciente.user_id}` },
    { title: 'Prontuário consolidado', href: '#' },
];

const rotuloNatureza: Record<string, string> = {
    SUSPEITA: 'Suspeita',
    DIFERENCIAL: 'Diferencial',
    DEFINITIVO: 'Definitivo',
};
</script>

<template>
    <Head :title="`Prontuário consolidado — ${paciente.nome}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4">
            <header>
                <h1 class="flex items-center gap-2 text-2xl font-bold">
                    <History class="h-5 w-5" aria-hidden="true" />
                    {{ paciente.nome }}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ paciente.data_nascimento }}
                    <template v-if="paciente.idade"> · {{ paciente.idade }}</template>
                    · {{ episodios.length }} {{ episodios.length === 1 ? 'atendimento' : 'atendimentos' }}
                </p>
            </header>

            <!-- RF-11: a alergia é do paciente, não do episódio — no consolidado ela
                 encabeça tudo. -->
            <PainelAlergias :alergias="alergias" />

            <section v-for="episodio in episodios" :key="episodio.id" class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <header class="flex flex-wrap items-start justify-between gap-3 border-b border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div>
                        <h2 class="font-mono text-lg font-semibold">{{ episodio.numero }}</h2>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            {{ episodio.admitido_em }}
                            <template v-if="episodio.finalizado_em"> — {{ episodio.finalizado_em }}</template>
                            <template v-if="episodio.unidade"> · {{ episodio.unidade }}</template>
                        </p>
                        <p class="mt-0.5 text-sm">
                            {{ episodio.status_rotulo }}
                            <template v-if="episodio.desfecho"> · desfecho: {{ episodio.desfecho }}</template>
                        </p>
                    </div>
                    <div class="flex flex-col items-start gap-2 sm:items-end">
                        <BadgePrioridade :cor="episodio.prioridade_cor" :rotulo="episodio.prioridade" />
                        <div class="flex flex-wrap gap-3 text-xs">
                            <Link :href="route('prontuario.show', episodio.id)" class="underline underline-offset-4">Abrir</Link>
                            <a
                                :href="route('prontuario.pdf', episodio.id)"
                                target="_blank"
                                class="inline-flex items-center gap-1 underline underline-offset-4"
                            >
                                <FileDown class="h-3.5 w-3.5" aria-hidden="true" />
                                PDF
                            </a>
                        </div>
                    </div>
                </header>

                <div class="flex flex-col gap-3 p-4">
                    <ul v-if="episodio.diagnosticos.length" class="flex flex-wrap gap-2">
                        <li
                            v-for="d in episodio.diagnosticos"
                            :key="d.id"
                            class="rounded-md border border-sidebar-border/70 px-2 py-1 text-xs dark:border-sidebar-border"
                        >
                            <span class="font-mono font-semibold">{{ d.codigo }}</span>
                            <span v-if="d.descricao"> — {{ d.descricao }}</span>
                            <span class="ml-1 text-muted-foreground"
                                >({{ rotuloNatureza[d.natureza] }}<template v-if="d.principal">, principal</template>)</span
                            >
                        </li>
                    </ul>

                    <RegistroClinicoCard v-for="registro in episodio.registros" :key="registro.id" :registro="registro" />

                    <p v-if="!episodio.registros.length" class="text-sm text-muted-foreground">Nenhum registro clínico neste atendimento.</p>
                </div>
            </section>

            <p
                v-if="!episodios.length"
                class="rounded-xl border border-dashed border-sidebar-border/70 p-10 text-center text-neutral-500 dark:border-sidebar-border"
            >
                Este paciente ainda não tem atendimentos registrados.
            </p>
        </div>
    </AppLayout>
</template>
