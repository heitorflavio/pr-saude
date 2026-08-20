<script setup lang="ts">
import PortalLayout from '@/layouts/PortalLayout.vue';
import { Head } from '@inertiajs/vue3';
import { FlaskConical } from 'lucide-vue-next';

defineProps<{
    exames: {
        id: number;
        nome: string;
        solicitado_em: string;
        situacao: string;
        resultado: null | {
            laudo: string | null;
            conclusao: string | null;
            itens: { analito: string; valor: string; unidade: string; referencia: string }[];
        };
    }[];
}>();
</script>

<template>
    <Head title="Meus exames" /><PortalLayout
        ><main class="mx-auto flex max-w-4xl flex-col gap-5 p-4">
            <header>
                <h1 class="flex items-center gap-2 text-2xl font-bold"><FlaskConical aria-hidden="true" /> Exames</h1>
            </header>
            <ul v-if="exames.length" class="grid gap-4">
                <li v-for="e in exames" :key="e.id" class="rounded-xl border bg-white p-4 dark:bg-neutral-900">
                    <header>
                        <strong>{{ e.nome }}</strong>
                        <p class="text-sm">{{ e.situacao }}</p>
                        <p class="text-xs text-muted-foreground">Solicitado em {{ e.solicitado_em }}</p>
                    </header>
                    <div v-if="e.resultado" class="mt-4 grid gap-3 border-t pt-3">
                        <div class="overflow-x-auto">
                            <table v-if="e.resultado.itens.length" class="w-full text-left text-sm">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Resultado</th>
                                        <th>Referência</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in e.resultado.itens" :key="item.analito">
                                        <td>{{ item.analito }}</td>
                                        <td>{{ item.valor }} {{ item.unidade }}</td>
                                        <td>{{ item.referencia }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-if="e.resultado.conclusao"><strong>Conclusão:</strong> {{ e.resultado.conclusao }}</p>
                        <p v-if="e.resultado.laudo"><strong>Laudo:</strong> {{ e.resultado.laudo }}</p>
                    </div>
                </li>
            </ul>
            <p v-else class="rounded-xl border border-dashed p-6 text-muted-foreground">Nenhum exame solicitado.</p>
        </main></PortalLayout
    >
</template>
