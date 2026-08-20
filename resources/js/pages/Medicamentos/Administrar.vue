<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PainelAlergias from '@/components/sgh/PainelAlergias.vue';
import Button from '@/components/ui/button/Button.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { AlertTriangle, ShieldAlert } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    aprazamento: { id: number; previsto: string; situacao: string };
    atendimento: { id: number; numero: string };
    paciente: { nome: string; nascimento: string; idade: string };
    alergias: any[];
    medicamento: {
        nome: string;
        principio_ativo: string;
        concentracao: string;
        forma: string;
        alta_vigilancia: boolean;
        dose: string;
        unidade: string;
        via: string;
        via_rotulo: string;
    };
    profissionais: { user_id: number; nome_completo: string; conselho_tipo: string | null; conselho_numero: string | null }[];
}>();

const form = useForm({
    resultado: 'ADMINISTRADA',
    dose_administrada: props.medicamento.dose,
    via: props.medicamento.via,
    motivo_nao_administracao: '',
    conferente_id: '',
    justificativa_alergia: '',
    observacao: '',
    lote: '',
    validade: '',
    validade_conferida: false,
    orientacao_prestada: false,
});
const divergente = computed(() => Math.abs(Number(form.dose_administrada) - Number(props.medicamento.dose)) > 0.001);
const possivelAlergia = computed(() =>
    props.alergias.some((a) => a.principio_ativo.toLocaleLowerCase() === props.medicamento.principio_ativo.toLocaleLowerCase()),
);
const enviar = () => form.post(route('medicamentos.administrar', props.aprazamento.id));
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Medicamentos', href: '/medicamentos' },
    { title: 'Conferência', href: '#' },
];
</script>

<template>
    <Head title="Conferência de administração" />
    <AppLayout :breadcrumbs="breadcrumbs"
        ><main class="mx-auto w-full max-w-4xl p-4">
            <h1 class="text-2xl font-bold">Conferência de administração</h1>
            <p class="font-mono text-sm text-muted-foreground">Atendimento {{ atendimento.numero }}</p>
            <form class="mt-5 grid gap-0 overflow-hidden rounded-xl border" @submit.prevent="enviar">
                <section class="grid gap-2 border-b p-4">
                    <strong>1 · Paciente certo</strong>
                    <p class="text-lg font-bold">{{ paciente.nome }}</p>
                    <p>{{ paciente.nascimento }} · {{ paciente.idade }}</p>
                    <PainelAlergias :alergias="alergias" />
                </section>
                <section v-if="possivelAlergia" role="alert" class="border-b bg-red-50 p-4 text-red-950 dark:bg-red-950 dark:text-red-50">
                    <p class="flex items-center gap-2 font-bold"><AlertTriangle aria-hidden="true" /> Alerta de alergia ao princípio ativo</p>
                    <label class="mt-3 grid gap-1 text-sm"
                        >Justificativa clínica para prosseguir<textarea
                            v-model="form.justificativa_alergia"
                            class="rounded border bg-transparent p-2"
                        />
                    </label>
                </section>
                <section class="grid gap-3 border-b p-4 md:grid-cols-2">
                    <div>
                        <strong>2 · Medicamento certo</strong>
                        <p>{{ medicamento.nome }} ({{ medicamento.principio_ativo }})</p>
                    </div>
                    <div>
                        <strong>8 · Forma certa</strong>
                        <p>{{ medicamento.forma }} · {{ medicamento.concentracao }}</p>
                    </div>
                </section>
                <section class="grid gap-3 border-b p-4 md:grid-cols-3">
                    <label class="grid gap-1"
                        ><strong>3 · Dose certa</strong
                        ><input v-model="form.dose_administrada" type="number" step="0.001" class="h-9 rounded border bg-transparent px-2"
                    /></label>
                    <div>
                        <strong>Prescrita</strong>
                        <p>{{ medicamento.dose }} {{ medicamento.unidade }}</p>
                    </div>
                    <label class="grid gap-1"
                        ><strong>4 · Via certa</strong
                        ><select v-model="form.via" class="h-9 rounded border bg-transparent px-2">
                            <option :value="medicamento.via">{{ medicamento.via_rotulo }}</option>
                        </select></label
                    >
                    <p v-if="divergente" class="col-span-full flex items-center gap-1 text-sm font-semibold text-amber-800 dark:text-amber-300">
                        <AlertTriangle class="h-4 w-4" /> Dose divergente: permitido com observação obrigatória (RN-23).
                    </p>
                </section>
                <section class="grid gap-3 border-b p-4 md:grid-cols-3">
                    <div>
                        <strong>5 · Horário certo</strong>
                        <p>Aprazado: {{ aprazamento.previsto }}</p>
                    </div>
                    <label class="grid gap-1"
                        ><strong>6 · Validade</strong
                        ><input v-model="form.validade" placeholder="MM/AAAA" class="h-9 rounded border bg-transparent px-2" /></label
                    ><label class="flex items-center gap-2 pt-6"
                        ><input v-model="form.validade_conferida" type="checkbox" /> Validade e lote conferidos</label
                    ><label class="grid gap-1"><span>Lote</span><input v-model="form.lote" class="h-9 rounded border bg-transparent px-2" /></label
                    ><label class="col-span-2 flex items-center gap-2 pt-6"
                        ><input v-model="form.orientacao_prestada" type="checkbox" /> <strong>7 · Orientação prestada ao paciente</strong></label
                    >
                </section>
                <section v-if="medicamento.alta_vigilancia" class="border-b bg-amber-50 p-4 text-amber-950 dark:bg-amber-950 dark:text-amber-50">
                    <p class="flex items-center gap-2 font-bold"><ShieldAlert /> Alta vigilância — dupla checagem independente</p>
                    <select v-model="form.conferente_id" class="mt-2 h-9 w-full rounded border bg-transparent px-2">
                        <option value="">Identifique o segundo profissional</option>
                        <option v-for="p in profissionais" :key="p.user_id" :value="p.user_id">
                            {{ p.nome_completo }} · {{ p.conselho_tipo }} {{ p.conselho_numero }}
                        </option>
                    </select>
                </section>
                <section class="grid gap-3 border-b p-4">
                    <strong>9 · Registro da administração</strong
                    ><label class="grid gap-1"
                        >Resultado<select v-model="form.resultado" class="h-9 rounded border bg-transparent px-2">
                            <option value="ADMINISTRADA">Administrada</option>
                            <option value="NAO_ADMINISTRADA">Não administrada</option>
                        </select></label
                    ><label v-if="form.resultado === 'NAO_ADMINISTRADA'" class="grid gap-1"
                        >Motivo<select v-model="form.motivo_nao_administracao" class="h-9 rounded border bg-transparent px-2">
                            <option value="">Selecione</option>
                            <option value="RECUSA_PACIENTE">Recusa do paciente</option>
                            <option value="INDISPONIVEL">Indisponível</option>
                            <option value="JEJUM">Jejum</option>
                            <option value="SUSPENSA_MEDICO">Suspensa pelo médico</option>
                            <option value="INTERCORRENCIA">Intercorrência</option>
                            <option value="ACESSO_INDISPONIVEL">Acesso indisponível</option>
                            <option value="OUTRO">Outro</option>
                        </select></label
                    ><label class="grid gap-1">Observação<textarea v-model="form.observacao" class="rounded border bg-transparent p-2" /></label
                    ><InputError
                        :message="
                            form.errors.administracao || form.errors.observacao || form.errors.validade_conferida || form.errors.orientacao_prestada
                        "
                    />
                </section>
                <div class="flex justify-end p-4"><Button type="submit" :disabled="form.processing">Confirmar registro</Button></div>
            </form>
        </main></AppLayout
    >
</template>
