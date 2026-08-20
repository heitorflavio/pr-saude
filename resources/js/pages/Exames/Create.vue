<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import Button from '@/components/ui/button/Button.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    atendimento: { id: number; numero: string };
    paciente: { nome: string };
    catalogo: { id: number; codigo: string; nome: string; tipo: string; preparo: string | null; prazo: number | null }[];
}>();

const form = useForm({ exame_id: '', carater: 'ROTINA', indicacao_clinica: '' });
const escolhido = computed(() => props.catalogo.find((e) => e.id === Number(form.exame_id)));
const enviar = () => form.post(route('exames.solicitar', props.atendimento.id));
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Exames', href: '/exames' },
    { title: 'Solicitar', href: '#' },
];
</script>

<template>
    <Head title="Solicitar exame" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="mx-auto w-full max-w-2xl p-4">
            <h1 class="text-2xl font-bold">Solicitar exame</h1>
            <p class="text-sm text-muted-foreground">{{ paciente.nome }} · {{ atendimento.numero }}</p>
            <form class="mt-5 grid gap-4 rounded-xl border p-4" @submit.prevent="enviar">
                <label class="grid gap-1 text-sm font-medium"
                    >Exame
                    <select v-model="form.exame_id" class="h-10 rounded-md border bg-transparent px-2" required>
                        <option value="">Selecione</option>
                        <option v-for="e in catalogo" :key="e.id" :value="e.id">{{ e.codigo }} — {{ e.nome }} ({{ e.tipo }})</option>
                    </select>
                </label>
                <InputError :message="form.errors.exame_id" />
                <aside v-if="escolhido?.preparo" class="rounded-md bg-amber-50 p-3 text-sm text-amber-950 dark:bg-amber-950 dark:text-amber-50">
                    <strong>Preparo:</strong> {{ escolhido.preparo }}
                </aside>
                <label class="grid gap-1 text-sm font-medium"
                    >Caráter
                    <select v-model="form.carater" class="h-10 rounded-md border bg-transparent px-2">
                        <option value="ROTINA">Rotina</option>
                        <option value="URGENTE">Urgente</option>
                    </select>
                </label>
                <label class="grid gap-1 text-sm font-medium"
                    >Indicação clínica
                    <textarea v-model="form.indicacao_clinica" rows="4" class="rounded-md border bg-transparent p-2" />
                </label>
                <div class="flex justify-end"><Button type="submit" :disabled="form.processing">Solicitar</Button></div>
            </form>
        </main>
    </AppLayout>
</template>
