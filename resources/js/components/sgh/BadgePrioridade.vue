<script setup lang="ts">
import { CircleAlert, CircleCheck, CircleDot, CircleHelp, OctagonAlert, TriangleAlert } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * RNF-15: cor + rótulo textual + ícone. Nunca só a cor.
 *
 * Cerca de 8% dos homens têm alguma deficiência de visão de cores. Num pronto-socorro
 * isso deixa de ser detalhe de acessibilidade: quem não distingue vermelho de verde
 * precisa conseguir ler o nível e reconhecer a forma.
 */
const props = defineProps<{ cor: string | null; rotulo: string | null }>();

const estilos: Record<string, { classe: string; icone: typeof CircleAlert }> = {
    VERMELHO: {
        classe: 'bg-red-100 text-red-900 ring-red-600/40 dark:bg-red-950 dark:text-red-100 dark:ring-red-400/40',
        icone: OctagonAlert,
    },
    LARANJA: {
        classe: 'bg-orange-100 text-orange-900 ring-orange-600/40 dark:bg-orange-950 dark:text-orange-100 dark:ring-orange-400/40',
        icone: TriangleAlert,
    },
    AMARELO: {
        classe: 'bg-amber-100 text-amber-900 ring-amber-600/40 dark:bg-amber-950 dark:text-amber-100 dark:ring-amber-400/40',
        icone: CircleAlert,
    },
    VERDE: {
        classe: 'bg-green-100 text-green-900 ring-green-600/40 dark:bg-green-950 dark:text-green-100 dark:ring-green-400/40',
        icone: CircleCheck,
    },
    AZUL: {
        classe: 'bg-sky-100 text-sky-900 ring-sky-600/40 dark:bg-sky-950 dark:text-sky-100 dark:ring-sky-400/40',
        icone: CircleDot,
    },
};

const estilo = computed(
    () =>
        estilos[props.cor ?? ''] ?? {
            classe: 'bg-neutral-100 text-neutral-800 ring-neutral-400/40 dark:bg-neutral-800 dark:text-neutral-100 dark:ring-neutral-500/40',
            icone: CircleHelp,
        },
);

const texto = computed(() => props.rotulo ?? 'Não classificado');
</script>

<template>
    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset" :class="estilo.classe">
        <component :is="estilo.icone" class="h-3.5 w-3.5" aria-hidden="true" />
        {{ texto }}
    </span>
</template>
