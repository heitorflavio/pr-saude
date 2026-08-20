<script setup lang="ts">
import PortalLayout from '@/layouts/PortalLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { History } from 'lucide-vue-next';

defineProps<{ atendimentos: { uuid: string; numero: string; status: string; admitido_em: string; finalizado_em: string | null; desfecho: string | null }[] }>();
</script>

<template><Head title="Meu histórico" /><PortalLayout><main class="mx-auto flex max-w-3xl flex-col gap-5 p-4"><header><h1 class="flex items-center gap-2 text-2xl font-bold"><History aria-hidden="true" /> Histórico de atendimentos</h1></header><ul class="grid gap-3"><li v-for="a in atendimentos" :key="a.uuid" class="rounded-xl border bg-white p-4 dark:bg-neutral-900"><strong>{{ a.status }}</strong><p class="font-mono text-xs text-muted-foreground">{{ a.numero }}</p><p class="text-sm">Entrada: {{ a.admitido_em }}<template v-if="a.finalizado_em"> · Saída: {{ a.finalizado_em }}</template></p><p v-if="a.desfecho">{{ a.desfecho }}</p><Link :href="route('portal.atendimento', a.uuid)" class="mt-2 inline-block text-sm underline">Ver evolução</Link></li></ul></main></PortalLayout></template>
