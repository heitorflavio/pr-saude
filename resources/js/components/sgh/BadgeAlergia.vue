<script setup lang="ts">
import { CircleAlert, CircleHelp, OctagonAlert, TriangleAlert } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * RNF-15: a cor NUNCA é o único indicador.
 *
 * Toda gravidade aparece como cor + rótulo textual + ícone. Cerca de 8% dos homens têm
 * alguma deficiência de visão de cores; num pronto-socorro isso deixa de ser detalhe de
 * acessibilidade e vira risco assistencial — quem não distingue vermelho de verde
 * precisa ler "GRAVE" e ver o ícone de alerta.
 *
 * O contraste dos pares escolhidos atende AA em tema claro e escuro.
 */
const props = defineProps<{ gravidade: string }>();

const estilos: Record<string, { rotulo: string; classe: string; icone: typeof CircleAlert }> = {
    GRAVE: {
        rotulo: 'Grave',
        classe: 'bg-red-100 text-red-900 ring-red-600/30 dark:bg-red-950 dark:text-red-100 dark:ring-red-400/40',
        icone: OctagonAlert,
    },
    MODERADA: {
        rotulo: 'Moderada',
        classe: 'bg-amber-100 text-amber-900 ring-amber-600/30 dark:bg-amber-950 dark:text-amber-100 dark:ring-amber-400/40',
        icone: TriangleAlert,
    },
    LEVE: {
        rotulo: 'Leve',
        classe: 'bg-sky-100 text-sky-900 ring-sky-600/30 dark:bg-sky-950 dark:text-sky-100 dark:ring-sky-400/40',
        icone: CircleAlert,
    },
    DESCONHECIDA: {
        rotulo: 'Gravidade desconhecida',
        classe: 'bg-neutral-100 text-neutral-900 ring-neutral-500/30 dark:bg-neutral-800 dark:text-neutral-100 dark:ring-neutral-400/40',
        icone: CircleHelp,
    },
};

const estilo = computed(() => estilos[props.gravidade] ?? estilos.DESCONHECIDA);
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
        :class="estilo.classe"
    >
        <component :is="estilo.icone" class="h-3.5 w-3.5" aria-hidden="true" />
        {{ estilo.rotulo }}
    </span>
</template>
