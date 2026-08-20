<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import Button from '@/components/ui/button/Button.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { AlertTriangle, FileDown, Plus, ShieldCheck } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    solicitacao: {
        id: number;
        carater: string;
        situacao: string;
        situacao_rotulo: string;
        solicitado_em: string;
        indicacao: string | null;
        solicitante: string;
    };
    atendimento: { id: number; numero: string };
    paciente: { id: number; nome: string };
    exame: { codigo: string; nome: string; tipo: string; preparo: string | null };
    resultado: null | {
        id: number;
        laudo: string | null;
        conclusao: string | null;
        critico: boolean;
        visivel: boolean;
        executado_em: string;
        executor: string;
        itens: any[];
        anexos: { id: number; nome: string; mime: string; tamanho: number }[];
    };
    permissoes: { executar: boolean; liberar: boolean };
}>();

const page = usePage<SharedData>();
const status = computed(() => page.props.flash?.status);
const situacao = useForm({ situacao: '', motivo: '' });
const mover = (destino: string) => {
    situacao.situacao = destino;
    situacao.post(route('exames.situacao', props.solicitacao.id), { preserveScroll: true });
};
const resultadoForm = useForm({
    laudo: '',
    conclusao: '',
    itens: [{ analito: '', valor: '', unidade: '', referencia_min: '', referencia_max: '', referencia_texto: '' }],
    anexos: [] as File[],
});
const adicionarAnalito = () =>
    resultadoForm.itens.push({ analito: '', valor: '', unidade: '', referencia_min: '', referencia_max: '', referencia_texto: '' });
const arquivos = (e: Event) => {
    resultadoForm.anexos = Array.from((e.target as HTMLInputElement).files ?? []);
};
const enviarResultado = () => resultadoForm.post(route('exames.resultado', props.solicitacao.id), { forceFormData: true });
const liberar = useForm({});
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Exames', href: '/exames' },
    { title: props.exame.nome, href: '#' },
];
</script>

<template>
    <Head :title="exame.nome" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="mx-auto flex w-full max-w-4xl flex-col gap-5 p-4">
            <p v-if="status" role="status" class="rounded bg-green-50 p-3 text-sm font-medium text-green-900 dark:bg-green-950 dark:text-green-100">
                {{ status }}
            </p>
            <header>
                <h1 class="text-2xl font-bold">{{ exame.nome }}</h1>
                <p class="font-mono text-sm text-muted-foreground">{{ exame.codigo }} · {{ atendimento.numero }}</p>
                <p>{{ paciente.nome }}</p>
            </header>

            <section class="grid gap-2 rounded-xl border p-4">
                <p><strong>Situação:</strong> {{ solicitacao.situacao_rotulo }}</p>
                <p><strong>Caráter:</strong> {{ solicitacao.carater === 'URGENTE' ? 'Urgente' : 'Rotina' }}</p>
                <p><strong>Solicitante:</strong> {{ solicitacao.solicitante }} · {{ solicitacao.solicitado_em }}</p>
                <p v-if="solicitacao.indicacao"><strong>Indicação:</strong> {{ solicitacao.indicacao }}</p>
                <p v-if="exame.preparo"><strong>Preparo:</strong> {{ exame.preparo }}</p>
            </section>

            <section v-if="permissoes.executar && ['SOLICITADO', 'COLETADO'].includes(solicitacao.situacao)" class="flex flex-wrap gap-2">
                <Button v-if="solicitacao.situacao === 'SOLICITADO'" @click="mover('COLETADO')">Registrar coleta</Button>
                <Button v-if="solicitacao.situacao === 'COLETADO'" @click="mover('EM_EXECUCAO')">Iniciar execução</Button>
                <input
                    v-model="situacao.motivo"
                    placeholder="Motivo para cancelar"
                    class="h-9 flex-1 rounded-md border bg-transparent px-2 text-sm"
                />
                <Button variant="destructive" @click="mover('CANCELADO')">Cancelar</Button>
                <InputError :message="situacao.errors.situacao || situacao.errors.motivo" />
            </section>

            <form
                v-if="permissoes.executar && solicitacao.situacao === 'EM_EXECUCAO'"
                class="grid gap-4 rounded-xl border p-4"
                @submit.prevent="enviarResultado"
            >
                <h2 class="text-lg font-semibold">Registrar resultado</h2>
                <div v-for="(item, i) in resultadoForm.itens" :key="i" class="grid gap-2 rounded-md bg-muted/40 p-3 md:grid-cols-5">
                    <input v-model="item.analito" placeholder="Analito" class="h-9 rounded border bg-transparent px-2" />
                    <input v-model="item.valor" placeholder="Valor" class="h-9 rounded border bg-transparent px-2" />
                    <input v-model="item.unidade" placeholder="Unidade" class="h-9 rounded border bg-transparent px-2" />
                    <input
                        v-model="item.referencia_min"
                        type="number"
                        step="0.0001"
                        placeholder="Ref. mín."
                        class="h-9 rounded border bg-transparent px-2"
                    />
                    <input
                        v-model="item.referencia_max"
                        type="number"
                        step="0.0001"
                        placeholder="Ref. máx."
                        class="h-9 rounded border bg-transparent px-2"
                    />
                </div>
                <Button type="button" variant="outline" size="sm" class="justify-self-start" @click="adicionarAnalito"
                    ><Plus class="h-4 w-4" /> Analito</Button
                >
                <textarea v-model="resultadoForm.laudo" rows="4" placeholder="Laudo" class="rounded border bg-transparent p-2" />
                <textarea v-model="resultadoForm.conclusao" rows="2" placeholder="Conclusão" class="rounded border bg-transparent p-2" />
                <label class="grid gap-1 text-sm"
                    >Anexos (PDF ou imagem, até 10 MB)<input type="file" multiple accept=".pdf,.jpg,.jpeg,.png" @change="arquivos"
                /></label>
                <InputError :message="resultadoForm.errors.resultado" />
                <Button type="submit" :disabled="resultadoForm.processing">Concluir exame</Button>
            </form>

            <section v-if="resultado" class="grid gap-4 rounded-xl border p-4">
                <header class="flex items-start justify-between">
                    <h2 class="text-lg font-semibold">Resultado</h2>
                    <span
                        v-if="resultado.critico"
                        class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-1 text-xs font-bold text-red-900 dark:bg-red-950 dark:text-red-100"
                        ><AlertTriangle class="h-4 w-4" /> Valor crítico</span
                    >
                    <span v-else class="inline-flex items-center gap-1 text-sm text-muted-foreground"
                        ><ShieldCheck class="h-4 w-4" /> Sem valor crítico</span
                    >
                </header>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr>
                                <th>Analito</th>
                                <th>Valor</th>
                                <th>Referência</th>
                                <th>Sinalização</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in resultado.itens" :key="item.id">
                                <td>{{ item.analito }}</td>
                                <td>{{ item.valor }} {{ item.unidade }}</td>
                                <td>{{ item.referencia_min }} – {{ item.referencia_max }}</td>
                                <td>{{ item.sinalizacao }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="resultado.laudo"><strong>Laudo:</strong> {{ resultado.laudo }}</p>
                <p v-if="resultado.conclusao"><strong>Conclusão:</strong> {{ resultado.conclusao }}</p>
                <ul>
                    <li v-for="a in resultado.anexos" :key="a.id">
                        <a :href="route('exames.anexo', a.id)" class="inline-flex items-center gap-1 underline"
                            ><FileDown class="h-4 w-4" /> {{ a.nome }}</a
                        >
                    </li>
                </ul>
                <p v-if="resultado.visivel" class="text-sm font-semibold text-green-800 dark:text-green-300">Liberado ao paciente</p>
                <form v-else-if="permissoes.liberar" @submit.prevent="liberar.post(route('exames.liberar', resultado.id))">
                    <Button type="submit" :disabled="liberar.processing">Registrar ciência e liberar ao paciente</Button
                    ><InputError :message="liberar.errors.liberacao" />
                </form>
            </section>
        </main>
    </AppLayout>
</template>
