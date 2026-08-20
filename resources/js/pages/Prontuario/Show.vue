<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import BadgePrioridade from '@/components/sgh/BadgePrioridade.vue';
import PainelAlergias from '@/components/sgh/PainelAlergias.vue';
import RegistroClinicoCard from '@/components/sgh/RegistroClinico.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { type Alergia, type Diagnostico, type Registro } from '@/types/prontuario';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { FileDown, LoaderCircle, ShieldCheck, ShieldX } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * UC-08 — prontuário do atendimento (RF-45 a RF-50).
 *
 * Não existe formulário de edição nesta tela, e a ausência é deliberada: a correção é
 * um adendo novo (RN-16). Oferecer um campo "editar" prometeria ao usuário algo que o
 * model, o CHECK e o `REVOKE UPDATE` recusam — e a promessa quebrada apareceria só
 * depois que ele já tivesse redigido a correção.
 */

const props = defineProps<{
    atendimento: {
        id: number;
        numero: string;
        status: string;
        status_rotulo: string;
        terminal: boolean;
        unidade: string | null;
        admitido_em: string | null;
        prioridade: string | null;
        prioridade_cor: string | null;
    };
    paciente: { user_id: number; nome: string; idade: string | null; data_nascimento: string | null };
    alergias: Alergia[];
    registros: Registro[];
    diagnosticos: Diagnostico[];
    tipos: { valor: string; rotulo: string; usa_soap: boolean }[];
    permissoes: { registrar: boolean; retificar: boolean; diagnosticar: boolean; marcar_sigiloso: boolean };
    integridade: { integra: boolean; quebras: { id: number; motivo: string }[] };
}>();

const page = usePage<SharedData>();
const status = computed(() => page.props.flash?.status);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pacientes', href: '/pacientes' },
    { title: props.paciente.nome, href: `/pacientes/${props.paciente.user_id}` },
    { title: `Prontuário ${props.atendimento.numero}`, href: '#' },
];

const nota = useForm({
    tipo: props.tipos[0]?.valor ?? '',
    subjetivo: '',
    objetivo: '',
    avaliacao: '',
    plano: '',
    conteudo_livre: '',
    sigiloso: false,
});

const tipoSelecionado = computed(() => props.tipos.find((t) => t.valor === nota.tipo));

const enviarNota = () =>
    nota.post(route('prontuario.store', props.atendimento.id), {
        preserveScroll: true,
        onSuccess: () => nota.reset('subjetivo', 'objetivo', 'avaliacao', 'plano', 'conteudo_livre', 'sigiloso'),
    });

// RF-50: a retificação abre um formulário próprio, ancorado no registro original.
const emRetificacao = ref<Registro | null>(null);

const adendo = useForm({
    motivo: '',
    subjetivo: '',
    objetivo: '',
    avaliacao: '',
    plano: '',
    conteudo_livre: '',
});

const abrirRetificacao = (registro: Registro) => {
    emRetificacao.value = registro;
    adendo.reset();
    adendo.clearErrors();
};

const enviarAdendo = () => {
    if (!emRetificacao.value) return;

    adendo.post(route('prontuario.retificar', emRetificacao.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            emRetificacao.value = null;
            adendo.reset();
        },
    });
};

const diagnostico = useForm({
    cid10_codigo: '',
    natureza: 'SUSPEITA',
    principal: false,
    observacao: '',
});

const enviarDiagnostico = () =>
    diagnostico.post(route('diagnosticos.store', props.atendimento.id), {
        preserveScroll: true,
        onSuccess: () => diagnostico.reset(),
    });

const rotuloNatureza: Record<string, string> = {
    SUSPEITA: 'Suspeita',
    DIFERENCIAL: 'Diferencial',
    DEFINITIVO: 'Definitivo',
};
</script>

<template>
    <Head :title="`Prontuário — ${atendimento.numero}`" />

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
                        {{ paciente.data_nascimento }}
                        <template v-if="paciente.idade"> · {{ paciente.idade }}</template>
                    </p>
                    <p class="mt-1 font-mono text-sm text-muted-foreground">
                        {{ atendimento.numero }} · {{ atendimento.status_rotulo }}
                        <template v-if="atendimento.unidade"> · {{ atendimento.unidade }}</template>
                    </p>
                </div>
                <div class="flex flex-col items-start gap-2 sm:items-end">
                    <BadgePrioridade :cor="atendimento.prioridade_cor" :rotulo="atendimento.prioridade" />
                    <div class="flex flex-wrap gap-3 text-xs">
                        <!-- RF-51 -->
                        <Link :href="route('prontuario.consolidado', paciente.user_id)" class="underline underline-offset-4">
                            Prontuário consolidado
                        </Link>
                        <!-- RF-52 -->
                        <a
                            :href="route('prontuario.pdf', atendimento.id)"
                            target="_blank"
                            class="inline-flex items-center gap-1 underline underline-offset-4"
                        >
                            <FileDown class="h-3.5 w-3.5" aria-hidden="true" />
                            PDF
                        </a>
                    </div>
                </div>
            </header>

            <!-- RF-11 -->
            <PainelAlergias :alergias="alergias" />

            <!-- doc §9.4: o estado da cadeia fica à vista. Uma quebra descoberta seis
                 meses depois, por acaso, já não serve como evidência de nada. -->
            <p
                class="flex items-start gap-2 rounded-md px-3 py-2 text-xs"
                :class="
                    integridade.integra
                        ? 'bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-400'
                        : 'bg-red-50 font-semibold text-red-900 dark:bg-red-950 dark:text-red-100'
                "
            >
                <ShieldCheck v-if="integridade.integra" class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                <ShieldX v-else class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                <span v-if="integridade.integra">Cadeia de integridade verificada: {{ registros.length }} registro(s), sem alteração detectada.</span>
                <span v-else>
                    Integridade violada —
                    <template v-for="(quebra, i) in integridade.quebras" :key="`${quebra.id}-${quebra.motivo}`">
                        <template v-if="i > 0">; </template>
                        registro #{{ quebra.id }}: {{ quebra.motivo }}
                    </template>
                </span>
            </p>

            <!-- RF-46 -->
            <section class="flex flex-col gap-3">
                <h2 class="text-lg font-semibold">Diagnósticos (CID-10)</h2>

                <ul v-if="diagnosticos.length" class="grid gap-2">
                    <li
                        v-for="d in diagnosticos"
                        :key="d.id"
                        class="rounded-lg border border-sidebar-border/70 px-3 py-2 text-sm dark:border-sidebar-border"
                    >
                        <span class="font-mono font-semibold">{{ d.codigo }}</span>
                        <span> — {{ d.descricao }}</span>
                        <span class="ml-2 rounded bg-neutral-100 px-1.5 py-0.5 text-xs dark:bg-neutral-800">{{ rotuloNatureza[d.natureza] }}</span>
                        <span
                            v-if="d.principal"
                            class="ml-1 rounded bg-neutral-900 px-1.5 py-0.5 text-xs text-white dark:bg-neutral-100 dark:text-neutral-900"
                        >
                            principal
                        </span>
                        <p v-if="d.observacao" class="mt-1 text-xs text-muted-foreground">{{ d.observacao }}</p>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">Nenhum diagnóstico registrado.</p>

                <form
                    v-if="permissoes.diagnosticar && !atendimento.terminal"
                    class="grid gap-2 sm:grid-cols-[1fr_10rem_auto]"
                    @submit.prevent="enviarDiagnostico"
                >
                    <div>
                        <label for="cid10" class="sr-only">Código CID-10</label>
                        <input
                            id="cid10"
                            v-model="diagnostico.cid10_codigo"
                            placeholder="Código CID-10 (ex.: R51)"
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                            required
                        />
                        <InputError :message="diagnostico.errors.cid10_codigo" />
                    </div>
                    <div>
                        <label for="natureza" class="sr-only">Natureza</label>
                        <select
                            id="natureza"
                            v-model="diagnostico.natureza"
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm"
                        >
                            <option value="SUSPEITA">Suspeita</option>
                            <option value="DIFERENCIAL">Diferencial</option>
                            <option value="DEFINITIVO">Definitivo</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-1.5 text-xs">
                            <input v-model="diagnostico.principal" type="checkbox" class="h-4 w-4" />
                            principal
                        </label>
                        <Button type="submit" size="sm" :disabled="diagnostico.processing">Adicionar</Button>
                    </div>
                </form>
            </section>

            <!-- RF-47: a evolução em entradas cronológicas. -->
            <section class="flex flex-col gap-3">
                <h2 class="text-lg font-semibold">Evolução</h2>

                <div v-if="registros.length" class="flex flex-col gap-3">
                    <template v-for="registro in registros" :key="registro.id">
                        <RegistroClinicoCard :registro="registro" :pode-retificar="permissoes.retificar" @retificar="abrirRetificacao" />

                        <!-- RF-50: o formulário nasce ancorado no registro original. -->
                        <form
                            v-if="emRetificacao?.id === registro.id"
                            class="ml-6 grid gap-3 rounded-xl border-2 border-dashed border-sidebar-border/70 p-4 dark:border-sidebar-border"
                            @submit.prevent="enviarAdendo"
                        >
                            <p class="text-sm font-semibold">Adendo ao registro de {{ registro.criado_em }}</p>
                            <p class="text-xs text-muted-foreground">
                                O registro original permanece visível e inalterado. O adendo o complementa — não o substitui.
                            </p>

                            <div class="grid gap-1">
                                <label for="motivo" class="text-sm font-medium">Motivo da retificação</label>
                                <input
                                    id="motivo"
                                    v-model="adendo.motivo"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                                    required
                                />
                                <InputError :message="adendo.errors.motivo" />
                            </div>

                            <template v-if="registro.usa_soap">
                                <div v-for="campo in ['subjetivo', 'objetivo', 'avaliacao', 'plano'] as const" :key="campo" class="grid gap-1">
                                    <label :for="`adendo-${campo}`" class="text-sm font-medium capitalize">{{ campo }}</label>
                                    <textarea
                                        :id="`adendo-${campo}`"
                                        v-model="adendo[campo]"
                                        rows="2"
                                        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm"
                                    />
                                </div>
                            </template>
                            <div v-else class="grid gap-1">
                                <label for="adendo-livre" class="text-sm font-medium">Conteúdo corrigido</label>
                                <textarea
                                    id="adendo-livre"
                                    v-model="adendo.conteudo_livre"
                                    rows="3"
                                    class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm"
                                />
                            </div>

                            <div class="flex justify-end gap-2">
                                <Button type="button" variant="outline" size="sm" @click="emRetificacao = null">Cancelar</Button>
                                <Button type="submit" size="sm" :disabled="adendo.processing">
                                    <LoaderCircle v-if="adendo.processing" class="h-4 w-4 animate-spin" aria-hidden="true" />
                                    Registrar adendo
                                </Button>
                            </div>
                        </form>
                    </template>
                </div>
                <p v-else class="text-sm text-muted-foreground">Nenhum registro clínico neste atendimento.</p>
            </section>

            <!-- RF-45, RF-47, RF-48 -->
            <section v-if="permissoes.registrar && !atendimento.terminal" class="flex flex-col gap-3">
                <h2 class="text-lg font-semibold">Novo registro</h2>

                <form class="grid gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border" @submit.prevent="enviarNota">
                    <div class="grid gap-1">
                        <label for="tipo" class="text-sm font-medium">Tipo</label>
                        <select id="tipo" v-model="nota.tipo" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                            <option v-for="tipo in tipos" :key="tipo.valor" :value="tipo.valor">{{ tipo.rotulo }}</option>
                        </select>
                        <InputError :message="nota.errors.tipo" />
                    </div>

                    <!-- doc §9.2: os quatro campos rotulados. É a estrutura que cobra o
                         raciocínio explícito de quem escreve. -->
                    <template v-if="tipoSelecionado?.usa_soap">
                        <div class="grid gap-1">
                            <label for="subjetivo" class="text-sm font-medium">Subjetivo — o que o paciente relata</label>
                            <textarea
                                id="subjetivo"
                                v-model="nota.subjetivo"
                                rows="2"
                                class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm"
                            />
                        </div>
                        <div class="grid gap-1">
                            <label for="objetivo" class="text-sm font-medium">Objetivo — o que se constata e mede</label>
                            <textarea
                                id="objetivo"
                                v-model="nota.objetivo"
                                rows="2"
                                class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm"
                            />
                        </div>
                        <div class="grid gap-1">
                            <label for="avaliacao" class="text-sm font-medium">Avaliação — raciocínio e hipóteses</label>
                            <textarea
                                id="avaliacao"
                                v-model="nota.avaliacao"
                                rows="2"
                                class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm"
                            />
                        </div>
                        <div class="grid gap-1">
                            <label for="plano" class="text-sm font-medium">Plano — conduta</label>
                            <textarea
                                id="plano"
                                v-model="nota.plano"
                                rows="2"
                                class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm"
                            />
                        </div>
                    </template>
                    <div v-else class="grid gap-1">
                        <label for="livre" class="text-sm font-medium">Conteúdo</label>
                        <textarea
                            id="livre"
                            v-model="nota.conteudo_livre"
                            rows="4"
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm"
                        />
                    </div>

                    <InputError :message="nota.errors.conteudo_livre" />

                    <!-- doc §9.6: sigilo é sobre a exibição no portal, não sobre o
                         direito de acesso — e marcar fica auditado. -->
                    <label v-if="permissoes.marcar_sigiloso" class="flex items-start gap-2 text-sm">
                        <input v-model="nota.sigiloso" type="checkbox" class="mt-0.5 h-4 w-4" />
                        <span>
                            Não exibir no portal do paciente
                            <span class="block text-xs text-muted-foreground">
                                Para suspeita grave ainda não comunicada, relato que envolve terceiro ou risco de autoagressão. O paciente mantém o
                                direito de obter o prontuário completo por via presencial. Marcar é registrado em auditoria.
                            </span>
                        </span>
                    </label>

                    <div class="flex justify-end">
                        <Button type="submit" :disabled="nota.processing">
                            <LoaderCircle v-if="nota.processing" class="h-4 w-4 animate-spin" aria-hidden="true" />
                            Registrar
                        </Button>
                    </div>
                </form>

                <p class="text-xs text-muted-foreground">
                    O registro é definitivo: não há edição nem exclusão. Uma correção posterior é feita por adendo, e o texto original permanece
                    visível (RN-16, RN-17).
                </p>
            </section>
        </div>
    </AppLayout>
</template>
