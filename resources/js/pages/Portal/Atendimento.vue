<script setup lang="ts">
import PortalLayout from '@/layouts/PortalLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{ atendimento: { numero: string; status: string; admitido_em: string; finalizado_em: string | null; desfecho: string | null }; evolucao: { id: number; quando: string; tipo: string; texto: string }[] }>();
</script>

<template><Head title="Evolução do atendimento" /><PortalLayout><main class="mx-auto flex max-w-3xl flex-col gap-5 p-4"><header><h1 class="text-2xl font-bold">Evolução do atendimento</h1><p>{{ atendimento.status }} · <span class="font-mono text-sm">{{ atendimento.numero }}</span></p><p class="text-sm text-muted-foreground">Início: {{ atendimento.admitido_em }}<template v-if="atendimento.finalizado_em"> · Término: {{ atendimento.finalizado_em }}</template></p></header><ol v-if="evolucao.length" class="grid gap-4"><li v-for="item in evolucao" :key="item.id" class="rounded-xl border bg-white p-4 dark:bg-neutral-900"><p class="text-xs text-muted-foreground">{{ item.quando }} · {{ item.tipo }}</p><p class="mt-2 whitespace-pre-line leading-relaxed">{{ item.texto }}</p></li></ol><p v-else class="rounded-xl border border-dashed p-6 text-muted-foreground">Ainda não há evolução disponível.</p></main></PortalLayout></template>
