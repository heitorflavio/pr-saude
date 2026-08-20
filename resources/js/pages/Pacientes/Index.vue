<script setup lang="ts">
import LeitorPulseira from '@/components/sgh/LeitorPulseira.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissoes } from '@/composables/usePermissoes';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { OctagonAlert, ScanLine, Search, UserPlus } from 'lucide-vue-next';
import { ref } from 'vue';

interface PacienteLinha {
    user_id: number;
    nome: string;
    nome_completo: string;
    idade: string | null;
    data_nascimento: string | null;
    cpf: string | null;
    identificacao_provisoria: boolean;
    codigo_provisorio: string | null;
    alergias_count: number;
}

const props = defineProps<{
    pacientes: { data: PacienteLinha[]; links: { url: string | null; label: string; active: boolean }[] };
    filtros: { busca: string };
}>();

const { pode } = usePermissoes();
const busca = ref(props.filtros.busca);

// RF-44: a leitura da pulseira nunca executa acao direta -- ela resolve o token e leva
// a tela de conferencia de identidade.
const mostrarLeitor = ref(false);

// RF-09: nome, CPF, CNS, data de nascimento, código provisório e token de pulseira.
// A busca vai ao servidor — o filtro é feito lá, com os índices da doc §5.5.
const buscar = () => {
    router.get(route('pacientes.index'), { busca: busca.value }, { preserveState: true, replace: true });
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pacientes', href: '/pacientes' }];

const mascararCpf = (cpf: string | null) => (cpf ? cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4') : null);

// Os rotulos do paginador do Laravel vem em ingles e com entidades HTML
// ("&laquo; Previous"). Traduzi-los aqui resolve duas coisas de uma vez: a interface
// fica em pt-BR (nenhuma string em ingles visivel ao usuario) e o v-html deixa de ser
// necessario -- injetar HTML num componente e desnecessario para exibir uma seta.
const rotuloPagina = (label: string) => {
    if (label.includes('Previous')) return '‹ Anterior';
    if (label.includes('Next')) return 'Próxima ›';
    return label;
};
</script>

<template>
    <Head title="Pacientes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <form class="flex w-full max-w-xl flex-col gap-2 sm:flex-row sm:items-end" @submit.prevent="buscar">
                    <div class="w-full min-w-0 flex-1">
                        <Label for="busca">Buscar paciente</Label>
                        <Input
                            id="busca"
                            v-model="busca"
                            type="search"
                            class="mt-1"
                            placeholder="Nome, CPF, CNS, data de nascimento, código provisório ou token da pulseira"
                        />
                    </div>
                    <Button type="submit" class="w-full sm:w-auto">
                        <Search class="h-4 w-4" aria-hidden="true" />
                        Buscar
                    </Button>
                </form>

                <div class="grid w-full gap-2 sm:flex sm:w-auto sm:flex-wrap">
                    <Button type="button" variant="outline" class="w-full sm:w-auto" @click="mostrarLeitor = !mostrarLeitor">
                        <ScanLine class="h-4 w-4" aria-hidden="true" />
                        Ler pulseira
                    </Button>

                    <Button v-if="pode('paciente.criar')" as-child class="w-full sm:w-auto">
                        <Link :href="route('pacientes.create')">
                            <UserPlus class="h-4 w-4" aria-hidden="true" />
                            Novo paciente
                        </Link>
                    </Button>
                </div>
            </div>

            <section v-if="mostrarLeitor" class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <LeitorPulseira />
            </section>

            <p class="text-xs text-muted-foreground md:hidden">Deslize horizontalmente para consultar todos os dados dos pacientes.</p>
            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <caption class="sr-only">
                        Pacientes cadastrados
                    </caption>
                    <thead class="bg-neutral-50 text-xs uppercase tracking-wide text-neutral-600 dark:bg-neutral-900 dark:text-neutral-400">
                        <tr>
                            <th scope="col" class="px-4 py-3">Nome</th>
                            <th scope="col" class="px-4 py-3">Identificação</th>
                            <th scope="col" class="px-4 py-3">Nascimento</th>
                            <th scope="col" class="px-4 py-3">Idade</th>
                            <th scope="col" class="px-4 py-3">Alergias</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                        <tr v-for="paciente in pacientes.data" :key="paciente.user_id" class="hover:bg-neutral-50 dark:hover:bg-neutral-900">
                            <td class="px-4 py-3 font-medium">
                                <Link :href="route('pacientes.show', paciente.user_id)" class="underline-offset-4 hover:underline">
                                    {{ paciente.nome }}
                                </Link>
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="paciente.identificacao_provisoria" class="font-mono text-xs">
                                    {{ paciente.codigo_provisorio }}
                                    <span
                                        class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-semibold text-amber-900 dark:bg-amber-950 dark:text-amber-100"
                                    >
                                        não identificado
                                    </span>
                                </span>
                                <span v-else class="font-mono text-xs">{{ mascararCpf(paciente.cpf) }}</span>
                            </td>
                            <td class="px-4 py-3">{{ paciente.data_nascimento }}</td>
                            <td class="px-4 py-3">{{ paciente.idade }}</td>
                            <td class="px-4 py-3">
                                <!-- RNF-15: cor + rótulo + ícone, nunca só a cor. -->
                                <span
                                    v-if="paciente.alergias_count > 0"
                                    class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-900 ring-1 ring-inset ring-red-600/30 dark:bg-red-950 dark:text-red-100 dark:ring-red-400/40"
                                >
                                    <OctagonAlert class="h-3.5 w-3.5" aria-hidden="true" />
                                    {{ paciente.alergias_count }} alergia{{ paciente.alergias_count > 1 ? 's' : '' }}
                                </span>
                                <span v-else class="text-xs text-neutral-500">Nenhuma registrada</span>
                            </td>
                        </tr>
                        <tr v-if="!pacientes.data.length">
                            <td colspan="5" class="px-4 py-10 text-center text-neutral-500">Nenhum paciente encontrado para a busca informada.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="pacientes.links.length > 3" class="flex flex-wrap gap-1" aria-label="Paginação">
                <Link
                    v-for="link in pacientes.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    class="rounded-md px-3 py-1.5 text-sm"
                    :class="[
                        link.active
                            ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900'
                            : 'hover:bg-neutral-100 dark:hover:bg-neutral-800',
                        !link.url && 'pointer-events-none opacity-50',
                    ]"
                    :aria-current="link.active ? 'page' : undefined"
                >
                    {{ rotuloPagina(link.label) }}
                </Link>
            </nav>
        </div>
    </AppLayout>
</template>
