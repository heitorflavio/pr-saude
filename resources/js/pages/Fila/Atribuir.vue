<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import BadgePrioridade from '@/components/sgh/BadgePrioridade.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { CircleDot, LoaderCircle, Star } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * UC-05 — tela de atribuição, reproduzindo o mockup da doc §7.4.
 *
 * Contar cabeças é métrica ruim: cinco pacientes azuis não equivalem a cinco laranjas
 * (RF-27). Por isso cada profissional aparece com a **composição da fila por cor** e a
 * **carga ponderada**, não só com o total.
 */
interface Carga {
    profissional_id: number;
    nome: string;
    categoria: string;
    especialidade: string | null;
    situacao: string;
    disponivel: boolean;
    pacientes_aguardando: number;
    composicao: Record<string, number>;
    carga_ponderada: number;
    espera_estimada_minutos: number;
}

const props = defineProps<{
    atendimento: {
        id: number;
        numero: string;
        paciente: string;
        prioridade: string | null;
        prioridade_cor: string | null;
        responsavel_atual: number | null;
    };
    cargas: Carga[];
    sugerido: number | null;
    preteridos: Record<number, number>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Fila', href: '/fila' },
    { title: `Atribuir ${props.atendimento.numero}`, href: '#' },
];

const jaAtribuido = computed(() => props.atendimento.responsavel_atual !== null);

// Um formulario so: o que muda entre atribuir e transferir e a rota e a
// obrigatoriedade da justificativa, nao os campos.
const form = useForm({
    profissional_id: props.sugerido ?? '',
    justificativa: '',
});

const enviar = () => form.post(jaAtribuido.value ? route('fila.transferir', props.atendimento.id) : route('fila.store', props.atendimento.id));

// As cores em ordem de gravidade, para a composição sair sempre na mesma sequência.
const ordemCores = ['VERMELHO', 'LARANJA', 'AMARELO', 'VERDE', 'AZUL'];
const rotuloCor: Record<string, string> = {
    VERMELHO: 'vermelho',
    LARANJA: 'laranja',
    AMARELO: 'amarelo',
    VERDE: 'verde',
    AZUL: 'azul',
};
const classeCor: Record<string, string> = {
    VERMELHO: 'bg-red-600',
    LARANJA: 'bg-orange-500',
    AMARELO: 'bg-amber-400',
    VERDE: 'bg-green-600',
    AZUL: 'bg-sky-600',
};
</script>

<template>
    <Head :title="`Atribuir — ${atendimento.numero}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold">{{ atendimento.paciente }}</h1>
                    <p class="mt-1 font-mono text-sm text-muted-foreground">{{ atendimento.numero }}</p>
                </div>
                <BadgePrioridade :cor="atendimento.prioridade_cor" :rotulo="atendimento.prioridade" />
            </header>

            <form @submit.prevent="enviar">
                <fieldset class="grid gap-3">
                    <legend class="mb-2 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                        {{ jaAtribuido ? 'Transferir para' : 'Atribuir a' }}
                    </legend>

                    <label
                        v-for="carga in cargas"
                        :key="carga.profissional_id"
                        class="rounded-xl border p-4"
                        :class="[
                            carga.disponivel
                                ? 'cursor-pointer border-sidebar-border/70 dark:border-sidebar-border'
                                : 'border-dashed border-neutral-300 opacity-60 dark:border-neutral-700',
                            carga.profissional_id === sugerido ? 'ring-2 ring-neutral-900 dark:ring-neutral-100' : '',
                        ]"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <span class="flex items-start gap-3">
                                <input
                                    v-model="form.profissional_id"
                                    type="radio"
                                    :value="carga.profissional_id"
                                    name="profissional"
                                    class="mt-1 h-4 w-4"
                                    :disabled="!carga.disponivel"
                                />
                                <span>
                                    <strong>{{ carga.nome }}</strong>
                                    <span class="text-muted-foreground">
                                        · {{ carga.categoria }}<template v-if="carga.especialidade"> · {{ carga.especialidade }}</template>
                                    </span>
                                    <span v-if="carga.profissional_id === sugerido" class="ml-2 inline-flex items-center gap-1 text-xs font-semibold">
                                        <Star class="h-3.5 w-3.5" aria-hidden="true" />
                                        sugerido — menor carga
                                    </span>
                                </span>
                            </span>

                            <span class="inline-flex items-center gap-1.5 text-xs font-medium">
                                <CircleDot class="h-3.5 w-3.5" aria-hidden="true" />
                                {{ carga.disponivel ? carga.situacao : `${carga.situacao} — indisponível` }}
                            </span>
                        </div>

                        <dl class="mt-3 grid gap-1 text-sm sm:grid-cols-3">
                            <div>
                                <dt class="inline text-muted-foreground">Aguardando:</dt>
                                <dd class="inline">{{ carga.pacientes_aguardando }}</dd>
                            </div>
                            <div>
                                <dt class="inline text-muted-foreground">Carga ponderada:</dt>
                                <dd class="inline font-semibold">{{ carga.carga_ponderada }}</dd>
                            </div>
                            <div>
                                <dt class="inline text-muted-foreground">Espera estimada:</dt>
                                <dd class="inline">~{{ carga.espera_estimada_minutos }} min</dd>
                            </div>
                        </dl>

                        <!-- RF-27: a composição por cor, porque o total esconde a gravidade. -->
                        <p class="mt-2 flex flex-wrap gap-3 text-xs">
                            <template v-for="cor in ordemCores" :key="cor">
                                <span v-if="carga.composicao[cor] > 0" class="inline-flex items-center gap-1">
                                    <span class="h-2.5 w-2.5 rounded-full" :class="classeCor[cor]" aria-hidden="true" />
                                    {{ carga.composicao[cor] }} {{ rotuloCor[cor] }}
                                </span>
                            </template>
                            <span v-if="carga.pacientes_aguardando === 0" class="text-muted-foreground">fila vazia</span>
                        </p>

                        <!-- UC-05, passo 6. -->
                        <p v-if="preteridos[carga.profissional_id] > 0" class="mt-2 text-xs text-amber-800 dark:text-amber-200">
                            Este paciente passará à frente de {{ preteridos[carga.profissional_id] }}
                            {{ preteridos[carga.profissional_id] === 1 ? 'paciente' : 'pacientes' }} já na fila.
                        </p>
                    </label>

                    <InputError :message="form.errors.profissional_id" />
                </fieldset>

                <!-- Transferir muda quem responde pelo paciente: o motivo fica registrado. -->
                <div v-if="jaAtribuido" class="mt-4 grid gap-2">
                    <label for="justificativa" class="text-sm font-medium">Motivo da transferência</label>
                    <input
                        id="justificativa"
                        v-model="form.justificativa"
                        class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                        required
                    />
                    <InputError :message="form.errors.justificativa" />
                    <p class="text-xs text-muted-foreground">
                        A posição na fila é preservada: o tempo já esperado é do paciente, não do profissional.
                    </p>
                </div>

                <div class="mt-4 flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" aria-hidden="true" />
                        {{ jaAtribuido ? 'Transferir' : 'Atribuir' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
