<script setup lang="ts">
import PainelAlergias from '@/components/sgh/PainelAlergias.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { CircleAlert, ScanLine } from 'lucide-vue-next';

/**
 * Etapa 2 da confirmação de identidade (RF-44): a tela que o profissional lê em voz
 * alta antes de qualquer ação crítica.
 *
 * Nome e data de nascimento são os dois identificadores que os protocolos de segurança
 * do paciente exigem conferir — por isso aparecem juntos e em destaque, não espalhados.
 */
interface Alergia {
    id: number;
    substancia: string;
    principio_ativo: string;
    gravidade: string;
    reacao: string | null;
}

defineProps<{
    paciente: { user_id: number; nome: string; data_nascimento: string | null; idade: string | null; sexo: string | null };
    alergias: Alergia[];
    temVinculo: boolean;
    atendimento: {
        id: number;
        numero: string;
        status: string;
        admitido_em: string | null;
        prioridade: string | null;
        prioridade_cor: string | null;
    } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Leitura de pulseira', href: '#' }];
</script>

<template>
    <Head title="Conferência de identidade" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
            <p class="flex items-center gap-2 text-sm text-muted-foreground">
                <ScanLine class="h-4 w-4" aria-hidden="true" />
                Confira os dois identificadores em voz alta com o paciente antes de qualquer procedimento.
            </p>

            <!-- Os dois identificadores, juntos e grandes. -->
            <header class="rounded-xl border-2 border-sidebar-border/70 p-5 dark:border-sidebar-border">
                <h1 class="text-3xl font-bold tracking-tight">{{ paciente.nome }}</h1>
                <p class="mt-2 text-lg">
                    Nascimento <strong>{{ paciente.data_nascimento }}</strong>
                    <span class="text-muted-foreground"> · {{ paciente.idade }} · {{ paciente.sexo }}</span>
                </p>
            </header>

            <!-- RF-11 / doc §13.5: alergias saem sempre, com ou sem vínculo. -->
            <PainelAlergias :alergias="alergias" />

            <section v-if="temVinculo && atendimento" class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Atendimento em curso</h2>
                <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Número</dt>
                        <dd class="font-mono">{{ atendimento.numero }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Situação</dt>
                        <dd>{{ atendimento.status }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Admissão</dt>
                        <dd>{{ atendimento.admitido_em }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Prioridade</dt>
                        <!-- RNF-15: rótulo textual, nunca só a cor. -->
                        <dd>{{ atendimento.prioridade ?? 'Não classificado' }}</dd>
                    </div>
                </dl>
            </section>

            <!--
                Sem vínculo assistencial, o profissional recebe nome e alergias — e só.
                RN-28: o resto exige justificativa registrada.
            -->
            <section
                v-if="!temVinculo"
                class="rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950/40"
                role="note"
            >
                <h2 class="flex items-center gap-2 text-sm font-semibold text-amber-900 dark:text-amber-100">
                    <CircleAlert class="h-4 w-4" aria-hidden="true" />
                    Acesso mínimo de segurança
                </h2>
                <p class="mt-1 text-sm text-amber-900 dark:text-amber-100">
                    Você não possui vínculo assistencial com este paciente. Nome e alergias são liberados para segurança imediata; o contexto clínico
                    completo exige justificativa registrada (RN-28).
                </p>
                <Button as-child variant="outline" size="sm" class="mt-3">
                    <Link :href="`/pacientes/${paciente.user_id}`">Abrir ficha e justificar acesso</Link>
                </Button>
            </section>
        </div>
    </AppLayout>
</template>
