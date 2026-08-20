<script setup lang="ts">
import BadgeAlergia from '@/components/sgh/BadgeAlergia.vue';
import { ShieldAlert, ShieldCheck } from 'lucide-vue-next';

/**
 * RF-11: as alergias aparecem em destaque em TODA tela do atendimento.
 *
 * Este componente existe para que essa exibição seja a mesma em todo lugar — se cada
 * tela desenhasse a sua, uma delas acabaria discreta demais, e seria justamente a que
 * alguém consulta às pressas.
 *
 * A ausência de alergia conhecida também é informação clínica, e por isso é exibida
 * explicitamente: "nenhuma alergia registrada" não é o mesmo que uma tela vazia, que
 * poderia significar apenas que ninguém perguntou.
 */
interface Alergia {
    id: number;
    substancia: string;
    principio_ativo: string;
    gravidade: string;
    reacao: string | null;
}

defineProps<{ alergias: Alergia[] }>();
</script>

<template>
    <section
        v-if="alergias.length"
        class="rounded-xl border-2 border-red-600/40 bg-red-50 p-4 dark:border-red-500/40 dark:bg-red-950/40"
        aria-labelledby="titulo-alergias"
    >
        <h2 id="titulo-alergias" class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-red-900 dark:text-red-100">
            <ShieldAlert class="h-4 w-4" aria-hidden="true" />
            Alergias — {{ alergias.length }} registrada{{ alergias.length > 1 ? 's' : '' }}
        </h2>

        <ul class="mt-3 space-y-2">
            <li v-for="alergia in alergias" :key="alergia.id" class="flex flex-wrap items-center gap-2 text-sm">
                <span class="font-semibold text-red-950 dark:text-red-50">{{ alergia.substancia }}</span>
                <BadgeAlergia :gravidade="alergia.gravidade" />
                <span v-if="alergia.reacao" class="text-red-900/80 dark:text-red-100/80">— {{ alergia.reacao }}</span>
            </li>
        </ul>

        <!-- RN-21: a verificação na administração é por princípio ativo, nunca por nome
             comercial. O rodapé lembra a equipe de que é isso que o sistema compara. -->
        <p class="mt-3 text-xs text-red-900/70 dark:text-red-100/70">
            A verificação na administração de medicamentos é feita por princípio ativo (RN-21).
        </p>
    </section>

    <section
        v-else
        class="rounded-xl border border-neutral-300 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900"
        aria-labelledby="titulo-sem-alergias"
    >
        <h2 id="titulo-sem-alergias" class="flex items-center gap-2 text-sm font-semibold text-neutral-800 dark:text-neutral-200">
            <ShieldCheck class="h-4 w-4" aria-hidden="true" />
            Nenhuma alergia registrada
        </h2>
        <p class="mt-1 text-xs text-neutral-600 dark:text-neutral-400">
            Ausência de registro não é o mesmo que ausência de alergia. Confirme com o paciente ou o acompanhante.
        </p>
    </section>
</template>
