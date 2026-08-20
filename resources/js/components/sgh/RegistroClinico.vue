<script setup lang="ts">
import { type Registro } from '@/types/prontuario';
import { FileText, Lock, PenLine, TriangleAlert } from 'lucide-vue-next';

/**
 * Um registro do prontuário, como ele aparece na tela (doc §9.3).
 *
 * O original retificado **continua visível**, com tarja e vínculo explícito para o
 * adendo. Escondê-lo seria o mesmo que apagá-lo — só que sem deixar rastro no banco. Em
 * sindicância, o que se avalia é se a conduta foi razoável diante da informação
 * disponível naquele momento, e isso exige que a hipótese errada sobreviva na tela.
 */

defineProps<{ registro: Registro; podeRetificar?: boolean }>();
defineEmits<{ retificar: [registro: Registro] }>();
</script>

<template>
    <article
        class="rounded-xl border p-4"
        :class="
            registro.retifica
                ? 'border-l-4 border-sidebar-border/70 border-l-neutral-900 dark:border-sidebar-border dark:border-l-neutral-100'
                : 'border-sidebar-border/70 dark:border-sidebar-border'
        "
    >
        <!-- RF-50: a tarja. Cor + texto + ícone, nunca só a cor (RNF-15). -->
        <p
            v-if="registro.retificado"
            class="mb-3 flex items-start gap-2 rounded-md border-l-4 border-amber-600 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
        >
            <TriangleAlert class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
            <span>
                Registro retificado — consulte
                <template v-for="(adendo, i) in registro.retificado_por" :key="adendo.id">
                    <template v-if="i > 0">, </template>
                    o adendo de {{ adendo.criado_em }}
                </template>
            </span>
        </p>

        <header class="flex flex-wrap items-start justify-between gap-2">
            <h3 class="flex items-center gap-2 font-semibold">
                <PenLine v-if="registro.retifica" class="h-4 w-4" aria-hidden="true" />
                <FileText v-else class="h-4 w-4" aria-hidden="true" />
                {{ registro.tipo_rotulo }}
            </h3>
            <span class="flex items-center gap-2 text-xs text-muted-foreground">
                <!-- doc §9.6: para a equipe o registro sigiloso aparece normalmente, com
                     a marca. É só no portal do paciente que ele é omitido. -->
                <span v-if="registro.sigiloso" class="inline-flex items-center gap-1 font-medium">
                    <Lock class="h-3.5 w-3.5" aria-hidden="true" />
                    não exibido no portal
                </span>
                {{ registro.criado_em }}
            </span>
        </header>

        <p v-if="registro.motivo_retificacao" class="mt-2 text-sm">
            <span class="font-medium">Motivo da retificação:</span> {{ registro.motivo_retificacao }}
        </p>

        <!-- doc §9.2: os quatro componentes rotulados. É a estrutura que cobra o
             raciocínio explícito — um TEXT livre aceitaria "paciente bem, alta". -->
        <dl v-if="registro.subjetivo || registro.objetivo || registro.avaliacao || registro.plano" class="mt-3 grid gap-2 text-sm">
            <div v-if="registro.subjetivo" class="grid gap-0.5 sm:grid-cols-[7rem_1fr] sm:gap-3">
                <dt class="font-semibold text-muted-foreground">Subjetivo</dt>
                <dd class="whitespace-pre-line">{{ registro.subjetivo }}</dd>
            </div>
            <div v-if="registro.objetivo" class="grid gap-0.5 sm:grid-cols-[7rem_1fr] sm:gap-3">
                <dt class="font-semibold text-muted-foreground">Objetivo</dt>
                <dd class="whitespace-pre-line">{{ registro.objetivo }}</dd>
            </div>
            <div v-if="registro.avaliacao" class="grid gap-0.5 sm:grid-cols-[7rem_1fr] sm:gap-3">
                <dt class="font-semibold text-muted-foreground">Avaliação</dt>
                <dd class="whitespace-pre-line">{{ registro.avaliacao }}</dd>
            </div>
            <div v-if="registro.plano" class="grid gap-0.5 sm:grid-cols-[7rem_1fr] sm:gap-3">
                <dt class="font-semibold text-muted-foreground">Plano</dt>
                <dd class="whitespace-pre-line">{{ registro.plano }}</dd>
            </div>
        </dl>

        <p v-if="registro.conteudo_livre" class="mt-3 whitespace-pre-line text-sm">{{ registro.conteudo_livre }}</p>

        <footer
            class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-dashed border-sidebar-border/70 pt-2 text-xs text-muted-foreground dark:border-sidebar-border"
        >
            <!-- Snapshot: quem assinou naquele momento, não quem é hoje. -->
            <span
                >{{ registro.autor_nome }}<template v-if="registro.autor_conselho"> — {{ registro.autor_conselho }}</template></span
            >

            <!-- RN-16: não há "editar". O que há é retificar, criando um adendo. -->
            <button
                v-if="podeRetificar && !registro.retifica"
                type="button"
                class="underline underline-offset-4 hover:no-underline"
                @click="$emit('retificar', registro)"
            >
                Retificar por adendo
            </button>
        </footer>
    </article>
</template>
