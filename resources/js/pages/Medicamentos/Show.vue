<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PainelAlergias from '@/components/sgh/PainelAlergias.vue';
import Button from '@/components/ui/button/Button.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    atendimento: { id: number; numero: string; terminal: boolean };
    paciente: { id: number; nome: string; idade: string | null };
    alergias: { id: number; substancia: string; principio_ativo: string; gravidade: string; reacao: string | null }[];
    medicamentos: {
        id: number;
        nome: string;
        principio_ativo: string;
        concentracao: string;
        forma: string;
        via: string;
        unidade: string;
        alta_vigilancia: boolean;
    }[];
    vias: { valor: string; rotulo: string }[];
    prescricoes: any[];
    permissoes: { prescrever: boolean; administrar: boolean };
}>();

const page = usePage<SharedData>();
const status = computed(() => page.props.flash?.status);
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pacientes', href: '/pacientes' },
    { title: props.paciente.nome, href: `/pacientes/${props.paciente.id}` },
    { title: 'Medicamentos', href: '#' },
];

const prescricao = useForm({
    observacao: '',
    itens: [
        {
            medicamento_id: '',
            dose: '',
            unidade_dose: 'mg',
            via: 'ORAL',
            frequencia_horas: 6,
            duracao_horas: 24,
            se_necessario: false,
            diluicao: '',
            velocidade_infusao: '',
            observacao: '',
        },
    ],
});
const medicamento = computed(() => props.medicamentos.find((m) => m.id === Number(prescricao.itens[0].medicamento_id)));
const selecionar = () => {
    if (!medicamento.value) return;
    prescricao.itens[0].via = medicamento.value.via;
    prescricao.itens[0].unidade_dose = medicamento.value.unidade;
};
const enviar = () => prescricao.post(route('prescricoes.store', props.atendimento.id), { preserveScroll: true, onSuccess: () => prescricao.reset() });
const suspensao = useForm({ motivo: '' });
const suspender = (id: number) => suspensao.post(route('prescricoes.suspender', id), { preserveScroll: true, onSuccess: () => suspensao.reset() });
</script>

<template>
    <Head :title="`Medicamentos — ${atendimento.numero}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4">
            <p
                v-if="status"
                role="status"
                class="rounded-md bg-green-50 p-3 text-sm font-medium text-green-900 dark:bg-green-950 dark:text-green-100"
            >
                {{ status }}
            </p>
            <header>
                <h1 class="text-2xl font-bold">{{ paciente.nome }}</h1>
                <p class="text-sm text-muted-foreground">{{ paciente.idade }} · {{ atendimento.numero }}</p>
            </header>
            <PainelAlergias :alergias="alergias" />

            <section v-if="permissoes.prescrever && !atendimento.terminal" class="rounded-xl border p-4">
                <h2 class="text-lg font-semibold">Nova prescrição</h2>
                <form class="mt-3 grid gap-3" @submit.prevent="enviar">
                    <div class="grid gap-3 md:grid-cols-3">
                        <label class="grid gap-1 text-sm"
                            >Medicamento
                            <select
                                v-model="prescricao.itens[0].medicamento_id"
                                class="h-9 rounded-md border bg-transparent px-2"
                                required
                                @change="selecionar"
                            >
                                <option value="">Selecione</option>
                                <option v-for="m in medicamentos" :key="m.id" :value="m.id">
                                    {{ m.nome }} — {{ m.principio_ativo }} {{ m.concentracao }}
                                </option>
                            </select>
                        </label>
                        <label class="grid gap-1 text-sm"
                            >Dose
                            <span class="flex"
                                ><input
                                    v-model="prescricao.itens[0].dose"
                                    type="number"
                                    step="0.001"
                                    min="0.001"
                                    class="h-9 min-w-0 flex-1 rounded-l-md border bg-transparent px-2"
                                    required /><input
                                    v-model="prescricao.itens[0].unidade_dose"
                                    class="h-9 w-20 rounded-r-md border border-l-0 bg-transparent px-2"
                                    required
                            /></span>
                        </label>
                        <label class="grid gap-1 text-sm"
                            >Via<select v-model="prescricao.itens[0].via" class="h-9 rounded-md border bg-transparent px-2">
                                <option v-for="v in vias" :key="v.valor" :value="v.valor">{{ v.rotulo }}</option>
                            </select></label
                        >
                        <label class="grid gap-1 text-sm"
                            >Frequência (horas)<input
                                v-model="prescricao.itens[0].frequencia_horas"
                                type="number"
                                min="1"
                                class="h-9 rounded-md border bg-transparent px-2"
                                :disabled="prescricao.itens[0].se_necessario"
                        /></label>
                        <label class="grid gap-1 text-sm"
                            >Duração (horas)<input
                                v-model="prescricao.itens[0].duracao_horas"
                                type="number"
                                min="1"
                                class="h-9 rounded-md border bg-transparent px-2"
                        /></label>
                        <label class="flex items-center gap-2 pt-6 text-sm"
                            ><input v-model="prescricao.itens[0].se_necessario" type="checkbox" /> Se necessário (sem aprazamento)</label
                        >
                    </div>
                    <p
                        v-if="medicamento?.alta_vigilancia"
                        class="rounded bg-amber-50 p-2 text-sm font-semibold text-amber-900 dark:bg-amber-950 dark:text-amber-100"
                    >
                        Alta vigilância: cada administração exigirá outro profissional.
                    </p>
                    <textarea
                        v-model="prescricao.observacao"
                        placeholder="Observação da prescrição"
                        class="rounded-md border bg-transparent p-2 text-sm"
                    />
                    <InputError :message="prescricao.errors.itens" />
                    <div class="flex justify-end"><Button type="submit" :disabled="prescricao.processing">Prescrever e aprazar</Button></div>
                </form>
            </section>

            <section>
                <h2 class="text-lg font-semibold">Prescrições</h2>
                <div class="mt-3 grid gap-4">
                    <article v-for="p in prescricoes" :key="p.id" class="rounded-xl border p-4">
                        <header class="flex justify-between gap-3">
                            <div>
                                <strong>{{ p.status }}</strong>
                                <p class="text-xs text-muted-foreground">{{ p.criado_em }} · {{ p.prescritor }}</p>
                            </div>
                        </header>
                        <ul class="mt-3 grid gap-2">
                            <li v-for="i in p.itens" :key="i.id" class="rounded bg-muted/50 p-3 text-sm">
                                <strong>{{ i.medicamento }}</strong> ({{ i.principio_ativo }}) — {{ i.dose }} {{ i.unidade }} · {{ i.via }} ·
                                {{ i.frequencia }}
                                <ul class="mt-2 flex flex-wrap gap-2">
                                    <li v-for="a in i.aprazamentos" :key="a.id">
                                        <Link
                                            v-if="a.situacao === 'PENDENTE' && permissoes.administrar"
                                            :href="route('medicamentos.conferir', a.id)"
                                            class="rounded border px-2 py-1 underline"
                                            >{{ a.previsto }} · conferir</Link
                                        ><span v-else class="rounded border px-2 py-1">{{ a.previsto }} · {{ a.situacao }}</span>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                        <form
                            v-if="p.status === 'VIGENTE' && permissoes.prescrever"
                            class="mt-3 flex flex-col gap-2 sm:flex-row"
                            @submit.prevent="suspender(p.id)"
                        >
                            <input
                                v-model="suspensao.motivo"
                                placeholder="Motivo da suspensão"
                                class="h-11 w-full min-w-0 flex-1 rounded-md border bg-transparent px-2 text-base sm:h-9 sm:text-sm"
                                required
                            /><Button type="submit" variant="destructive" size="sm" class="w-full sm:w-auto">Suspender</Button>
                        </form>
                    </article>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
