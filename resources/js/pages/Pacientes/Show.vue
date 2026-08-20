<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PainelAlergias from '@/components/sgh/PainelAlergias.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissoes } from '@/composables/usePermissoes';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { IdCard, KeyRound, LoaderCircle, Printer } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Alergia {
    id: number;
    substancia: string;
    principio_ativo: string;
    gravidade: string;
    reacao: string | null;
}

interface Condicao {
    id: number;
    descricao: string;
    cid10_codigo: string | null;
    desde: string | null;
}

const props = defineProps<{
    paciente: Record<string, string | number | boolean | null>;
    alergias: Alergia[];
    condicoes: Condicao[];
    podeRegularizar: boolean;
}>();

const page = usePage<SharedData>();
const status = computed(() => page.props.flash?.status);
const alerta = computed(() => page.props.flash?.alerta);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pacientes', href: '/pacientes' },
    { title: String(props.paciente.nome), href: '#' },
];

const { pode } = usePermissoes();
const mostrarRegularizacao = ref(false);

// UC-01, passo 11: a ficha oferece "Imprimir Pulseira" logo apos o cadastro.
// Form HTML puro, e nao useForm: a resposta e um PDF, nao uma resposta do Inertia.
const csrf = computed(() => page.props.csrf_token);

// RN-30: vincula o CPF real preservando todo o histórico do paciente.
const form = useForm({
    cpf: '',
    nome_completo: String(props.paciente.nome_completo ?? ''),
    cns: '',
});

const regularizar = () => form.put(route('pacientes.regularizar', props.paciente.user_id));

const mascararCpf = (cpf: unknown) => (typeof cpf === 'string' ? cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4') : '—');
</script>

<template>
    <Head :title="String(paciente.nome)" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4">
            <div
                v-if="status"
                class="rounded-md bg-green-50 px-4 py-3 text-sm font-medium text-green-900 dark:bg-green-950 dark:text-green-100"
                role="status"
            >
                {{ status }}
            </div>
            <div
                v-if="alerta"
                class="rounded-md bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900 dark:bg-amber-950 dark:text-amber-100"
                role="alert"
            >
                {{ alerta }}
            </div>

            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold">{{ paciente.nome }}</h1>
                    <p v-if="paciente.nome_social" class="text-sm text-muted-foreground">Nome civil: {{ paciente.nome_completo }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">{{ paciente.data_nascimento }} · {{ paciente.idade }}</p>
                </div>

                <form v-if="pode('pulseira.imprimir')" :action="route('pulseira.imprimir', paciente.user_id)" method="post" target="_blank">
                    <input type="hidden" name="_token" :value="csrf" />
                    <input type="hidden" name="motivo" value="PRIMEIRA" />
                    <Button type="submit" variant="outline">
                        <Printer class="h-4 w-4" aria-hidden="true" />
                        Imprimir pulseira
                    </Button>
                </form>

                <span
                    v-if="paciente.identificacao_provisoria"
                    class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-900 ring-1 ring-inset ring-amber-600/30 dark:bg-amber-950 dark:text-amber-100 dark:ring-amber-400/40"
                >
                    <IdCard class="h-4 w-4" aria-hidden="true" />
                    Não identificado — {{ paciente.codigo_provisorio }}
                </span>
            </header>

            <!-- RF-11: alergias em destaque, sempre no topo. -->
            <PainelAlergias :alergias="alergias" />

            <section class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Documentos</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">CPF</dt>
                            <dd class="font-mono">{{ mascararCpf(paciente.cpf) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">CNS</dt>
                            <dd class="font-mono">{{ paciente.cns ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Nome da mãe</dt>
                            <dd>{{ paciente.nome_mae ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Contato</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Telefone</dt>
                            <dd>{{ paciente.telefone ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Emergência</dt>
                            <dd>{{ paciente.contato_emergencia_nome ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Município</dt>
                            <dd>{{ paciente.municipio ?? '—' }} {{ paciente.uf ?? '' }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section v-if="condicoes.length" class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Condições crônicas</h2>
                <ul class="mt-3 space-y-1 text-sm">
                    <li v-for="condicao in condicoes" :key="condicao.id">
                        {{ condicao.descricao }}
                        <span v-if="condicao.cid10_codigo" class="font-mono text-xs text-muted-foreground">({{ condicao.cid10_codigo }})</span>
                        <span v-if="condicao.desde" class="text-xs text-muted-foreground">— desde {{ condicao.desde }}</span>
                    </li>
                </ul>
            </section>

            <section class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                    <KeyRound class="h-4 w-4" aria-hidden="true" />
                    Acesso ao portal
                </h2>
                <p class="mt-2 text-sm">
                    Login: <span class="font-mono">{{ paciente.login }}</span>
                </p>
                <p v-if="paciente.senha_provisoria" class="mt-1 text-xs text-amber-800 dark:text-amber-200">
                    Senha provisória ativa: a data de nascimento no formato DDMMAAAA. O paciente é obrigado a trocá-la no primeiro acesso (RN-06).
                </p>
            </section>

            <!-- RN-30 -->
            <section v-if="podeRegularizar" class="rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950/40">
                <h2 class="text-sm font-semibold">Regularizar identificação</h2>
                <p class="mt-1 text-xs text-amber-900 dark:text-amber-100">
                    Vincula o CPF real preservando todo o histórico: mesmo prontuário, mesma pulseira, mesmos atendimentos.
                </p>

                <Button v-if="!mostrarRegularizacao" type="button" variant="outline" size="sm" class="mt-3" @click="mostrarRegularizacao = true">
                    Informar CPF
                </Button>

                <form v-else class="mt-3 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end" @submit.prevent="regularizar">
                    <div class="grid gap-2">
                        <Label for="cpf_regularizacao">CPF</Label>
                        <Input id="cpf_regularizacao" v-model="form.cpf" inputmode="numeric" maxlength="14" required />
                        <InputError :message="form.errors.cpf" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="nome_regularizacao">Nome completo</Label>
                        <Input id="nome_regularizacao" v-model="form.nome_completo" />
                        <InputError :message="form.errors.nome_completo" />
                    </div>
                    <Button type="submit" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" aria-hidden="true" />
                        Regularizar
                    </Button>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
