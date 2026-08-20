<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import BadgePrioridade from '@/components/sgh/BadgePrioridade.vue';
import PainelAlergias from '@/components/sgh/PainelAlergias.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { AlertTriangle, History, LoaderCircle, Stethoscope } from 'lucide-vue-next';
import { computed } from 'vue';

interface Alergia {
    id: number;
    substancia: string;
    principio_ativo: string;
    gravidade: string;
    reacao: string | null;
}

interface Classificacao {
    id: number;
    nome: string;
    cor: string;
    cor_hex: string;
    tempo_alvo_minutos: number;
    exige_atendimento_imediato: boolean;
    descricao: string | null;
}

interface TriagemRegistro {
    id: number;
    classificacao: string | null;
    cor: string | null;
    queixa_principal: string;
    justificativa: string | null;
    reclassificacao: boolean;
    em: string | null;
    por: string | null;
    sinais_vitais: Record<string, string | number | null> | null;
}

const props = defineProps<{
    atendimento: {
        id: number;
        numero: string;
        status_rotulo: string;
        admitido_em: string | null;
        prioridade: string | null;
        prioridade_cor: string | null;
    };
    paciente: { user_id: number; nome: string; data_nascimento: string | null; idade: string | null };
    alergias: Alergia[];
    classificacoes: Classificacao[];
    queixas: { id: number; descricao: string; fluxograma_manchester: string | null }[];
    jaTriado: boolean;
    triagens: TriagemRegistro[];
    espera: { minutos: number; tempo_alvo: number; rotulo: string; acao: string; sugere_reavaliacao: boolean } | null;
}>();

const page = usePage<SharedData>();
const status = computed(() => page.props.flash?.status);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pacientes', href: '/pacientes' },
    { title: props.paciente.nome, href: `/pacientes/${props.paciente.user_id}` },
    { title: `Triagem ${props.atendimento.numero}`, href: '#' },
];

const sinaisVazios = {
    pressao_sistolica: '',
    pressao_diastolica: '',
    frequencia_cardiaca: '',
    frequencia_respiratoria: '',
    saturacao_o2: '',
    temperatura: '',
    glicemia: '',
    escala_dor: '',
};

const formTriagem = useForm({
    classificacao_risco_id: '',
    queixa_principal: '',
    justificativa_classificacao: '',
    sinais_vitais: { ...sinaisVazios },
});

const formReclassificar = useForm({
    classificacao_risco_id: '',
    justificativa: '',
    sinais_vitais: { ...sinaisVazios },
});

const triar = () => formTriagem.post(route('triagem.store', props.atendimento.id));
const reclassificar = () => formReclassificar.post(route('triagem.reclassificar', props.atendimento.id));

const corPrioridade = computed(() => props.atendimento.prioridade_cor);
</script>

<template>
    <Head :title="`Triagem — ${atendimento.numero}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4">
            <div
                v-if="status"
                class="rounded-md bg-green-50 px-4 py-3 text-sm font-medium text-green-900 dark:bg-green-950 dark:text-green-100"
                role="status"
            >
                {{ status }}
            </div>

            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold">{{ paciente.nome }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ paciente.data_nascimento }} · {{ paciente.idade }} · atendimento
                        <span class="font-mono">{{ atendimento.numero }}</span>
                    </p>
                </div>
                <BadgePrioridade :cor="corPrioridade" :rotulo="atendimento.prioridade" />
            </header>

            <PainelAlergias :alergias="alergias" />

            <!--
                doc §7.3.1: a espera é tornada VISÍVEL, não usada para reordenar. O
                sistema convoca um profissional a reclassificar; ele nunca promove
                sozinho a prioridade de ninguém.
            -->
            <section
                v-if="espera"
                class="rounded-xl border p-4"
                :class="
                    espera.sugere_reavaliacao
                        ? 'border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-950/40'
                        : 'border-sidebar-border/70 dark:border-sidebar-border'
                "
            >
                <h2 class="flex items-center gap-2 text-sm font-semibold">
                    <AlertTriangle v-if="espera.sugere_reavaliacao" class="h-4 w-4" aria-hidden="true" />
                    Espera: {{ espera.minutos }} min de um alvo de {{ espera.tempo_alvo }} min — {{ espera.rotulo }}
                </h2>
                <p class="mt-1 text-xs text-muted-foreground">{{ espera.acao }}</p>
                <p v-if="espera.sugere_reavaliacao" class="mt-1 text-xs font-medium text-amber-900 dark:text-amber-100">
                    A prioridade não é alterada automaticamente: quem espera muito pode ter piorado, e essa avaliação é clínica.
                </p>
            </section>

            <!-- Triagem inicial -->
            <section v-if="!jaTriado" class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                    <Stethoscope class="h-4 w-4" aria-hidden="true" />
                    Classificação de risco
                </h2>

                <form class="mt-4 grid gap-4" @submit.prevent="triar">
                    <fieldset class="grid gap-2">
                        <legend class="mb-2 text-sm font-medium">Nível de prioridade</legend>
                        <!-- RNF-15: cada opção traz cor, nome e tempo-alvo por escrito. -->
                        <label
                            v-for="classificacao in classificacoes"
                            :key="classificacao.id"
                            class="flex cursor-pointer items-center gap-3 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                        >
                            <input
                                v-model="formTriagem.classificacao_risco_id"
                                type="radio"
                                :value="classificacao.id"
                                name="classificacao"
                                class="h-4 w-4"
                            />
                            <span class="h-6 w-6 shrink-0 rounded-full border" :style="{ background: classificacao.cor_hex }" aria-hidden="true" />
                            <span class="flex-1">
                                <strong>{{ classificacao.nome }}</strong>
                                <span class="text-muted-foreground"> — {{ classificacao.cor }}</span>
                                <span class="block text-xs text-muted-foreground">
                                    Tempo-alvo: {{ classificacao.tempo_alvo_minutos }} min.
                                    {{ classificacao.descricao }}
                                </span>
                            </span>
                        </label>
                        <InputError :message="formTriagem.errors.classificacao_risco_id" />
                    </fieldset>

                    <div class="grid gap-2">
                        <Label for="queixa">Queixa principal</Label>
                        <Input id="queixa" v-model="formTriagem.queixa_principal" required />
                        <InputError :message="formTriagem.errors.queixa_principal" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="justificativa">Justificativa da classificação</Label>
                        <Input id="justificativa" v-model="formTriagem.justificativa_classificacao" />
                    </div>

                    <fieldset class="grid gap-3 sm:grid-cols-4">
                        <legend class="mb-2 text-sm font-medium">Sinais vitais</legend>
                        <div class="grid gap-1">
                            <Label for="pas" class="text-xs">PA sistólica</Label>
                            <Input id="pas" v-model="formTriagem.sinais_vitais.pressao_sistolica" inputmode="numeric" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="pad" class="text-xs">PA diastólica</Label>
                            <Input id="pad" v-model="formTriagem.sinais_vitais.pressao_diastolica" inputmode="numeric" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="fc" class="text-xs">FC</Label>
                            <Input id="fc" v-model="formTriagem.sinais_vitais.frequencia_cardiaca" inputmode="numeric" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="fr" class="text-xs">FR</Label>
                            <Input id="fr" v-model="formTriagem.sinais_vitais.frequencia_respiratoria" inputmode="numeric" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="spo2" class="text-xs">SpO₂ (%)</Label>
                            <Input id="spo2" v-model="formTriagem.sinais_vitais.saturacao_o2" inputmode="decimal" />
                            <InputError :message="formTriagem.errors['sinais_vitais.saturacao_o2']" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="temp" class="text-xs">Temp. (°C)</Label>
                            <Input id="temp" v-model="formTriagem.sinais_vitais.temperatura" inputmode="decimal" />
                            <InputError :message="formTriagem.errors['sinais_vitais.temperatura']" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="glicemia" class="text-xs">Glicemia</Label>
                            <Input id="glicemia" v-model="formTriagem.sinais_vitais.glicemia" inputmode="decimal" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="dor" class="text-xs">Dor (0–10)</Label>
                            <Input id="dor" v-model="formTriagem.sinais_vitais.escala_dor" inputmode="numeric" />
                            <InputError :message="formTriagem.errors['sinais_vitais.escala_dor']" />
                        </div>
                    </fieldset>

                    <div class="flex justify-end">
                        <Button type="submit" :disabled="formTriagem.processing">
                            <LoaderCircle v-if="formTriagem.processing" class="h-4 w-4 animate-spin" aria-hidden="true" />
                            Registrar triagem
                        </Button>
                    </div>
                </form>
            </section>

            <!-- Reclassificação -->
            <section v-else class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Reclassificar risco</h2>
                <p class="mt-1 text-xs text-muted-foreground">
                    A triagem anterior permanece no histórico. A posição na fila não é perdida: o tempo já esperado é preservado (doc §7.5).
                </p>

                <form class="mt-4 grid gap-4" @submit.prevent="reclassificar">
                    <div class="grid gap-2">
                        <Label for="nova-classificacao">Novo nível</Label>
                        <select
                            id="nova-classificacao"
                            v-model="formReclassificar.classificacao_risco_id"
                            class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                            required
                        >
                            <option value="" disabled>Selecione</option>
                            <option v-for="c in classificacoes" :key="c.id" :value="c.id">
                                {{ c.nome }} — {{ c.cor }} ({{ c.tempo_alvo_minutos }} min)
                            </option>
                        </select>
                        <InputError :message="formReclassificar.errors.classificacao_risco_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="motivo">Justificativa</Label>
                        <Input id="motivo" v-model="formReclassificar.justificativa" required />
                        <InputError :message="formReclassificar.errors.justificativa" />
                    </div>

                    <fieldset class="grid gap-3 sm:grid-cols-4">
                        <legend class="mb-2 text-sm font-medium">Nova aferição (opcional)</legend>
                        <div class="grid gap-1">
                            <Label for="r-spo2" class="text-xs">SpO₂ (%)</Label>
                            <Input id="r-spo2" v-model="formReclassificar.sinais_vitais.saturacao_o2" inputmode="decimal" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="r-fc" class="text-xs">FC</Label>
                            <Input id="r-fc" v-model="formReclassificar.sinais_vitais.frequencia_cardiaca" inputmode="numeric" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="r-temp" class="text-xs">Temp. (°C)</Label>
                            <Input id="r-temp" v-model="formReclassificar.sinais_vitais.temperatura" inputmode="decimal" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="r-dor" class="text-xs">Dor (0–10)</Label>
                            <Input id="r-dor" v-model="formReclassificar.sinais_vitais.escala_dor" inputmode="numeric" />
                        </div>
                    </fieldset>

                    <div class="flex justify-end">
                        <Button type="submit" :disabled="formReclassificar.processing">
                            <LoaderCircle v-if="formReclassificar.processing" class="h-4 w-4 animate-spin" aria-hidden="true" />
                            Reclassificar
                        </Button>
                    </div>
                </form>
            </section>

            <!-- doc §7.5: a cadeia inteira, da mais recente para a mais antiga. -->
            <section v-if="triagens.length" class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                    <History class="h-4 w-4" aria-hidden="true" />
                    Histórico de classificações
                </h2>

                <ol class="mt-4 space-y-4">
                    <li
                        v-for="triagem in triagens"
                        :key="triagem.id"
                        class="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <BadgePrioridade :cor="triagem.cor" :rotulo="triagem.classificacao" />
                            <span class="text-xs text-muted-foreground">
                                {{ triagem.em }}<template v-if="triagem.por"> · {{ triagem.por }}</template>
                                <template v-if="triagem.reclassificacao"> · reclassificação</template>
                            </span>
                        </div>
                        <p class="mt-2 text-sm">{{ triagem.queixa_principal }}</p>
                        <p v-if="triagem.justificativa" class="mt-1 text-xs italic text-muted-foreground">
                            {{ triagem.justificativa }}
                        </p>
                        <p v-if="triagem.sinais_vitais" class="mt-2 text-xs text-muted-foreground">
                            PA {{ triagem.sinais_vitais.pressao }} · FC {{ triagem.sinais_vitais.fc }} · FR {{ triagem.sinais_vitais.fr }} · SpO₂
                            {{ triagem.sinais_vitais.spo2 }} · Temp {{ triagem.sinais_vitais.temperatura }} · Dor {{ triagem.sinais_vitais.dor }}
                        </p>
                    </li>
                </ol>
            </section>
        </div>
    </AppLayout>
</template>
