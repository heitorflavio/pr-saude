<script setup lang="ts">
import PortalLayout from '@/layouts/PortalLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Pill } from 'lucide-vue-next';

defineProps<{ medicamentos: { id: number; nome: string; principio_ativo: string; dose: string; unidade: string; via: string; quando: string }[] }>();
</script>

<template>
    <Head title="Meus medicamentos" /><PortalLayout
        ><main class="mx-auto flex max-w-3xl flex-col gap-5 p-4">
            <header>
                <h1 class="flex items-center gap-2 text-2xl font-bold"><Pill aria-hidden="true" /> Medicamentos administrados</h1>
                <p class="text-sm text-muted-foreground">O que foi administrado durante seus atendimentos.</p>
            </header>
            <ul v-if="medicamentos.length" class="grid gap-3">
                <li v-for="m in medicamentos" :key="m.id" class="rounded-xl border bg-white p-4 dark:bg-neutral-900">
                    <strong>{{ m.nome }}</strong>
                    <p class="text-sm text-muted-foreground">{{ m.principio_ativo }}</p>
                    <p class="mt-1">{{ m.dose }} {{ m.unidade }} · {{ m.via }}</p>
                    <p class="text-xs text-muted-foreground">{{ m.quando }}</p>
                </li>
            </ul>
            <p v-else class="rounded-xl border border-dashed p-6 text-muted-foreground">Nenhum medicamento administrado.</p>
        </main></PortalLayout
    >
</template>
