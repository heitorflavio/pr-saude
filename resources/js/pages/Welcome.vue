<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    Check,
    ChevronRight,
    CircleCheck,
    ClipboardCheck,
    FileClock,
    HeartPulse,
    LayoutDashboard,
    ListChecks,
    LockKeyhole,
    LogIn,
    Pill,
    ScanLine,
    ShieldCheck,
    Sparkles,
    Stethoscope,
    UserRound,
    UsersRound,
} from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Página pública do sistema. Ela não recebe dados assistenciais: toda a visualização
 * abaixo é uma representação estática e anonimizada do fluxo, nunca um retrato da fila.
 */
const page = usePage<SharedData>();

const usuario = computed(() => page.props.auth?.user ?? null);
const ehPaciente = computed(() => usuario.value?.tipo === 'PACIENTE');
const destinoAutenticado = computed(() => (ehPaciente.value ? route('portal.acompanhamento') : route('dashboard')));
const rotuloDestinoAutenticado = computed(() => (ehPaciente.value ? 'Ir ao meu acompanhamento' : 'Abrir painel do plantão'));

const jornada = [
    {
        icone: ScanLine,
        numero: '01',
        titulo: 'Identificação segura',
        texto: 'Cadastro, dois identificadores e pulseira com token opaco — sem CPF impresso ou dado de banco no QR Code.',
    },
    {
        icone: Activity,
        numero: '02',
        titulo: 'Triagem por risco',
        texto: 'Queixa e sinais vitais orientam a classificação clínica, sempre com cor, rótulo e tempo-alvo visíveis.',
    },
    {
        icone: Stethoscope,
        numero: '03',
        titulo: 'Cuidado coordenado',
        texto: 'Fila por gravidade, prontuário, prescrições, doses e exames reunidos em uma jornada contínua.',
    },
    {
        icone: ClipboardCheck,
        numero: '04',
        titulo: 'Alta com rastreabilidade',
        texto: 'Cada decisão mantém autoria e horário do servidor; correções entram como adendos e preservam o histórico.',
    },
];

const garantias = [
    {
        icone: FileClock,
        titulo: 'Histórico preservado',
        texto: 'O prontuário clínico não é sobrescrito. Retificações ficam ligadas ao registro original.',
    },
    {
        icone: Pill,
        titulo: 'Medicação mais segura',
        texto: 'Alergias são conferidas por princípio ativo e itens de alta vigilância exigem dupla checagem.',
    },
    {
        icone: ShieldCheck,
        titulo: 'Acesso auditável',
        texto: 'Permissões e vínculo assistencial limitam o acesso; consultas a dados clínicos deixam rastro.',
    },
    {
        icone: UserRound,
        titulo: 'Paciente no centro',
        texto: 'O portal traduz a jornada com clareza e só mostra exames depois da liberação profissional.',
    },
];

const classificacoes = [
    { cor: 'bg-red-500', rotulo: 'Emergência', estado: 'Atendimento imediato', largura: 'w-full' },
    { cor: 'bg-orange-500', rotulo: 'Muito urgente', estado: 'Chamada prioritária', largura: 'w-4/5' },
    { cor: 'bg-yellow-400', rotulo: 'Urgente', estado: 'Em acompanhamento', largura: 'w-3/5' },
];
</script>

<template>
    <Head>
        <title>Gestão hospitalar para pronto-socorro</title>
        <meta
            name="description"
            content="Cuidado coordenado da recepção à alta: triagem, fila clínica, prontuário, medicação, exames e portal do paciente."
        />
    </Head>

    <div
        class="min-h-screen overflow-hidden bg-[#f8fbfa] text-slate-950 selection:bg-emerald-200 selection:text-emerald-950 dark:bg-[#07110f] dark:text-slate-50"
    >
        <a
            href="#conteudo-principal"
            class="sr-only z-50 rounded-lg bg-white px-4 py-2 font-semibold text-slate-950 shadow-lg focus:not-sr-only focus:fixed focus:left-4 focus:top-4"
        >
            Pular para o conteúdo principal
        </a>

        <header class="relative z-40 border-b border-emerald-950/10 bg-[#f8fbfa]/90 backdrop-blur-xl dark:border-white/10 dark:bg-[#07110f]/90">
            <div class="mx-auto flex h-20 max-w-7xl items-center justify-between gap-6 px-5 sm:px-8 lg:px-10">
                <Link :href="route('home')" class="group flex items-center gap-3" aria-label="Página inicial">
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-700 text-white shadow-[0_8px_24px_-10px_rgba(4,120,87,0.9)] transition-transform group-hover:-rotate-3"
                    >
                        <HeartPulse class="h-5 w-5" aria-hidden="true" />
                    </span>
                    <span class="leading-none">
                        <span class="block text-base font-extrabold tracking-tight">{{ $page.props.name }}</span>
                        <span class="mt-1 block text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-700 dark:text-emerald-400"
                            >Pronto-socorro</span
                        >
                    </span>
                </Link>

                <nav
                    aria-label="Navegação principal"
                    class="hidden items-center gap-8 text-sm font-semibold text-slate-600 dark:text-slate-300 md:flex"
                >
                    <a href="#jornada" class="transition-colors hover:text-emerald-700 dark:hover:text-emerald-300">Jornada</a>
                    <a href="#acessos" class="transition-colors hover:text-emerald-700 dark:hover:text-emerald-300">Acessos</a>
                    <a href="#seguranca" class="transition-colors hover:text-emerald-700 dark:hover:text-emerald-300">Segurança</a>
                </nav>

                <Button v-if="usuario" as-child class="rounded-xl bg-emerald-700 hover:bg-emerald-800">
                    <Link :href="destinoAutenticado">
                        {{ ehPaciente ? 'Ir ao portal' : 'Ir ao painel' }}
                        <ArrowRight class="ml-2 h-4 w-4" aria-hidden="true" />
                    </Link>
                </Button>
                <div v-else class="flex items-center gap-2">
                    <Button variant="ghost" as-child class="hidden rounded-xl sm:inline-flex">
                        <Link :href="route('portal.login')">Portal do paciente</Link>
                    </Button>
                    <Button as-child class="rounded-xl bg-emerald-700 shadow-sm hover:bg-emerald-800">
                        <Link :href="route('login')">
                            <LogIn class="mr-2 h-4 w-4" aria-hidden="true" />
                            <span class="hidden sm:inline">Acesso da equipe</span>
                            <span class="sm:hidden">Entrar</span>
                        </Link>
                    </Button>
                </div>
            </div>
        </header>

        <main id="conteudo-principal" tabindex="-1">
            <section class="relative border-b border-emerald-950/10 dark:border-white/10">
                <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
                    <div class="absolute -right-32 -top-32 h-[34rem] w-[34rem] rounded-full bg-emerald-200/50 blur-3xl dark:bg-emerald-900/20"></div>
                    <div class="landing-grid absolute inset-0 opacity-40 dark:opacity-15"></div>
                </div>

                <div
                    class="relative mx-auto grid max-w-7xl items-center gap-14 px-5 py-16 sm:px-8 sm:py-20 lg:grid-cols-[1.02fr_0.98fr] lg:px-10 lg:py-24"
                >
                    <div class="max-w-2xl">
                        <div
                            class="mb-7 inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-white/80 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.13em] text-emerald-800 shadow-sm dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300"
                        >
                            <Sparkles class="h-3.5 w-3.5" aria-hidden="true" />
                            Cuidado coordenado em cada etapa
                        </div>

                        <h1 class="text-balance text-4xl font-black leading-[1.04] tracking-[-0.045em] sm:text-5xl lg:text-6xl xl:text-[4.25rem]">
                            A urgência pede clareza.
                            <span class="text-emerald-700 dark:text-emerald-400">O cuidado também.</span>
                        </h1>
                        <p class="mt-6 max-w-xl text-pretty text-lg leading-8 text-slate-600 dark:text-slate-300 sm:text-xl">
                            Da recepção à alta, uma jornada clínica conectada para a equipe decidir com contexto e o paciente acompanhar com
                            tranquilidade.
                        </p>

                        <div v-if="usuario" class="mt-9 flex flex-col gap-3 sm:flex-row">
                            <Button
                                size="lg"
                                as-child
                                class="h-12 rounded-xl bg-emerald-700 px-6 text-base shadow-[0_12px_30px_-12px_rgba(4,120,87,0.75)] hover:bg-emerald-800"
                            >
                                <Link :href="destinoAutenticado">
                                    {{ rotuloDestinoAutenticado }}
                                    <ArrowRight class="ml-2 h-4 w-4" aria-hidden="true" />
                                </Link>
                            </Button>
                        </div>
                        <div v-else class="mt-9 flex flex-col gap-3 sm:flex-row">
                            <Button
                                size="lg"
                                as-child
                                class="h-12 rounded-xl bg-emerald-700 px-6 text-base shadow-[0_12px_30px_-12px_rgba(4,120,87,0.75)] hover:bg-emerald-800"
                            >
                                <Link :href="route('login')">
                                    Acessar como equipe
                                    <ArrowRight class="ml-2 h-4 w-4" aria-hidden="true" />
                                </Link>
                            </Button>
                            <Button
                                size="lg"
                                variant="outline"
                                as-child
                                class="h-12 rounded-xl border-emerald-950/15 bg-white/70 px-6 text-base hover:bg-white dark:border-white/15 dark:bg-white/5 dark:hover:bg-white/10"
                            >
                                <Link :href="route('portal.login')">Acompanhar meu atendimento</Link>
                            </Button>
                        </div>

                        <div class="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm font-medium text-slate-600 dark:text-slate-300">
                            <span class="flex items-center gap-2">
                                <CircleCheck class="h-4 w-4 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                                Prioridade clínica visível
                            </span>
                            <span class="flex items-center gap-2">
                                <CircleCheck class="h-4 w-4 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                                Histórico rastreável
                            </span>
                        </div>
                    </div>

                    <div class="relative mx-auto w-full max-w-[36rem] lg:mx-0 lg:justify-self-end">
                        <div
                            class="absolute -inset-5 rounded-[2.25rem] bg-gradient-to-br from-emerald-300/35 via-cyan-100/10 to-transparent blur-2xl dark:from-emerald-800/30"
                            aria-hidden="true"
                        ></div>
                        <div
                            class="relative overflow-hidden rounded-[1.6rem] border border-white/90 bg-white/90 p-2.5 shadow-[0_30px_80px_-30px_rgba(6,78,59,0.35)] backdrop-blur dark:border-white/10 dark:bg-slate-950/85"
                        >
                            <div class="overflow-hidden rounded-[1.15rem] border border-slate-200 bg-slate-50 dark:border-white/10 dark:bg-slate-900">
                                <div
                                    class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 dark:border-white/10 dark:bg-slate-950"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-700 text-white">
                                            <LayoutDashboard class="h-4 w-4" aria-hidden="true" />
                                        </span>
                                        <div>
                                            <p class="text-xs font-bold text-slate-900 dark:text-white">Visão do plantão</p>
                                            <p class="text-[10px] text-slate-500 dark:text-slate-400">Fluxo assistencial em tempo real</p>
                                        </div>
                                    </div>
                                    <span
                                        class="flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        Atualizado
                                    </span>
                                </div>

                                <div class="grid grid-cols-3 gap-2 p-3 sm:gap-3 sm:p-4">
                                    <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-white/10 dark:bg-slate-950">
                                        <UsersRound class="h-4 w-4 text-emerald-700 dark:text-emerald-400" aria-hidden="true" />
                                        <p class="mt-4 text-[10px] font-medium text-slate-500 dark:text-slate-400">Fila clínica</p>
                                        <p class="mt-1 text-sm font-extrabold text-slate-900 dark:text-white">Por prioridade</p>
                                    </div>
                                    <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-white/10 dark:bg-slate-950">
                                        <Stethoscope class="h-4 w-4 text-cyan-700 dark:text-cyan-400" aria-hidden="true" />
                                        <p class="mt-4 text-[10px] font-medium text-slate-500 dark:text-slate-400">Atendimento</p>
                                        <p class="mt-1 text-sm font-extrabold text-slate-900 dark:text-white">Contexto único</p>
                                    </div>
                                    <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-white/10 dark:bg-slate-950">
                                        <LockKeyhole class="h-4 w-4 text-violet-700 dark:text-violet-400" aria-hidden="true" />
                                        <p class="mt-4 text-[10px] font-medium text-slate-500 dark:text-slate-400">Prontuário</p>
                                        <p class="mt-1 text-sm font-extrabold text-slate-900 dark:text-white">Protegido</p>
                                    </div>
                                </div>

                                <div class="px-3 pb-3 sm:px-4 sm:pb-4">
                                    <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-950">
                                        <div class="mb-4 flex items-center justify-between">
                                            <div>
                                                <p class="text-xs font-bold text-slate-900 dark:text-white">Fila por risco clínico</p>
                                                <p class="mt-0.5 text-[10px] text-slate-500 dark:text-slate-400">Representação anonimizada</p>
                                            </div>
                                            <ListChecks class="h-4 w-4 text-slate-400" aria-hidden="true" />
                                        </div>
                                        <div class="space-y-2.5">
                                            <div
                                                v-for="item in classificacoes"
                                                :key="item.rotulo"
                                                class="grid grid-cols-[auto_1fr] items-center gap-3 rounded-xl bg-slate-50 px-3 py-2.5 dark:bg-white/5"
                                            >
                                                <span :class="item.cor" class="h-8 w-1 rounded-full" aria-hidden="true"></span>
                                                <div class="min-w-0">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <p class="truncate text-[11px] font-bold text-slate-800 dark:text-slate-100">
                                                            {{ item.rotulo }}
                                                        </p>
                                                        <p class="truncate text-[9px] font-medium text-slate-500 dark:text-slate-400">
                                                            {{ item.estado }}
                                                        </p>
                                                    </div>
                                                    <div class="mt-2 h-1 overflow-hidden rounded-full bg-slate-200 dark:bg-white/10">
                                                        <div :class="[item.cor, item.largura]" class="h-full rounded-full opacity-80"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="absolute -bottom-5 -left-5 hidden items-center gap-3 rounded-2xl border border-emerald-100 bg-white p-3 shadow-xl dark:border-emerald-900 dark:bg-slate-950 sm:flex"
                        >
                            <span
                                class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                            >
                                <Check class="h-5 w-5" aria-hidden="true" />
                            </span>
                            <div>
                                <p class="text-[10px] font-medium text-slate-500 dark:text-slate-400">Decisão com contexto</p>
                                <p class="text-xs font-extrabold text-slate-900 dark:text-white">No momento certo</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section aria-label="Diferenciais do sistema" class="border-b border-emerald-950/10 bg-white dark:border-white/10 dark:bg-slate-950">
                <div
                    class="mx-auto grid max-w-7xl grid-cols-2 divide-x divide-y divide-emerald-950/10 px-5 dark:divide-white/10 sm:px-8 md:grid-cols-4 md:divide-y-0 lg:px-10"
                >
                    <div class="flex items-center gap-3 px-3 py-5 sm:px-5">
                        <ScanLine class="h-5 w-5 shrink-0 text-emerald-700 dark:text-emerald-400" aria-hidden="true" />
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-200 sm:text-sm">Identificação segura</span>
                    </div>
                    <div class="flex items-center gap-3 px-3 py-5 sm:px-5">
                        <Activity class="h-5 w-5 shrink-0 text-emerald-700 dark:text-emerald-400" aria-hidden="true" />
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-200 sm:text-sm">Risco sempre visível</span>
                    </div>
                    <div class="flex items-center gap-3 px-3 py-5 sm:px-5">
                        <ShieldCheck class="h-5 w-5 shrink-0 text-emerald-700 dark:text-emerald-400" aria-hidden="true" />
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-200 sm:text-sm">Acesso auditado</span>
                    </div>
                    <div class="flex items-center gap-3 px-3 py-5 sm:px-5">
                        <UserRound class="h-5 w-5 shrink-0 text-emerald-700 dark:text-emerald-400" aria-hidden="true" />
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-200 sm:text-sm">Portal do paciente</span>
                    </div>
                </div>
            </section>

            <section id="jornada" aria-labelledby="titulo-jornada" class="bg-[#f8fbfa] py-20 dark:bg-[#07110f] sm:py-28">
                <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
                    <div class="max-w-2xl">
                        <p class="text-xs font-extrabold uppercase tracking-[0.2em] text-emerald-700 dark:text-emerald-400">Uma linha de cuidado</p>
                        <h2 id="titulo-jornada" class="mt-4 text-balance text-3xl font-black tracking-[-0.035em] sm:text-4xl">
                            Cada etapa informa a próxima.
                        </h2>
                        <p class="mt-4 text-lg leading-8 text-slate-600 dark:text-slate-300">
                            Menos informação dispersa. Mais contexto para agir do primeiro acolhimento ao desfecho.
                        </p>
                    </div>

                    <ol class="relative mt-12 grid gap-4 lg:grid-cols-4">
                        <li
                            v-for="etapa in jornada"
                            :key="etapa.titulo"
                            class="group relative rounded-2xl border border-emerald-950/10 bg-white p-6 transition duration-300 hover:-translate-y-1 hover:border-emerald-600/40 hover:shadow-[0_18px_50px_-30px_rgba(6,78,59,0.5)] dark:border-white/10 dark:bg-slate-950 dark:hover:border-emerald-500/50"
                        >
                            <div class="flex items-start justify-between">
                                <span
                                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 transition-colors group-hover:bg-emerald-700 group-hover:text-white dark:bg-emerald-950 dark:text-emerald-300"
                                >
                                    <component :is="etapa.icone" class="h-5 w-5" aria-hidden="true" />
                                </span>
                                <span class="text-xs font-black tracking-[0.2em] text-emerald-700/50 dark:text-emerald-300/50">{{
                                    etapa.numero
                                }}</span>
                            </div>
                            <h3 class="mt-8 text-lg font-extrabold tracking-tight">{{ etapa.titulo }}</h3>
                            <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-400">{{ etapa.texto }}</p>
                            <ChevronRight
                                class="mt-6 h-4 w-4 text-emerald-600 opacity-0 transition group-hover:translate-x-1 group-hover:opacity-100"
                                aria-hidden="true"
                            />
                        </li>
                    </ol>
                </div>
            </section>

            <section
                id="acessos"
                aria-labelledby="titulo-acessos"
                class="border-y border-emerald-950/10 bg-[#eaf5f0] py-20 dark:border-white/10 dark:bg-emerald-950/30 sm:py-24"
            >
                <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
                    <div class="grid items-end gap-6 lg:grid-cols-2">
                        <div>
                            <p class="text-xs font-extrabold uppercase tracking-[0.2em] text-emerald-800 dark:text-emerald-300">
                                Acesso certo, sem desvio
                            </p>
                            <h2 id="titulo-acessos" class="mt-4 text-balance text-3xl font-black tracking-[-0.035em] sm:text-4xl">
                                Duas portas. A mesma jornada.
                            </h2>
                        </div>
                        <p class="max-w-xl text-base leading-7 text-slate-600 dark:text-slate-300 lg:justify-self-end">
                            Equipe e paciente entram por ambientes próprios, com credenciais e permissões adequadas ao seu papel.
                        </p>
                    </div>

                    <div
                        class="mt-10 grid overflow-hidden rounded-[1.75rem] border border-emerald-950/10 bg-white shadow-[0_22px_70px_-45px_rgba(6,78,59,0.45)] dark:border-white/10 dark:bg-slate-950 lg:grid-cols-2"
                    >
                        <article class="flex flex-col p-7 sm:p-9 lg:border-r lg:border-emerald-950/10 dark:lg:border-white/10">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-700 text-white">
                                <UsersRound class="h-5 w-5" aria-hidden="true" />
                            </span>
                            <p class="mt-7 text-xs font-extrabold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-400">
                                Para profissionais
                            </p>
                            <h3 class="mt-2 text-2xl font-black tracking-tight">Painel da equipe</h3>
                            <p class="mt-3 max-w-md text-sm leading-6 text-slate-600 dark:text-slate-400">
                                Acesso institucional ao fluxo do plantão, conforme função, permissão e vínculo assistencial.
                            </p>
                            <Button
                                v-if="!usuario || !ehPaciente"
                                as-child
                                variant="link"
                                class="mt-6 h-auto w-fit p-0 font-bold text-emerald-700 dark:text-emerald-400"
                            >
                                <Link :href="usuario ? route('dashboard') : route('login')">
                                    {{ usuario ? 'Abrir meu painel' : 'Entrar como equipe' }}
                                    <ArrowRight class="ml-2 h-4 w-4" aria-hidden="true" />
                                </Link>
                            </Button>
                        </article>

                        <article class="flex flex-col border-t border-emerald-950/10 p-7 dark:border-white/10 sm:p-9 lg:border-t-0">
                            <span
                                class="flex h-12 w-12 items-center justify-center rounded-2xl bg-cyan-100 text-cyan-800 dark:bg-cyan-950 dark:text-cyan-300"
                            >
                                <UserRound class="h-5 w-5" aria-hidden="true" />
                            </span>
                            <p class="mt-7 text-xs font-extrabold uppercase tracking-[0.16em] text-cyan-700 dark:text-cyan-400">Para pacientes</p>
                            <h3 class="mt-2 text-2xl font-black tracking-tight">Portal de acompanhamento</h3>
                            <p class="mt-3 max-w-md text-sm leading-6 text-slate-600 dark:text-slate-400">
                                Uma visão clara do próprio atendimento, acessada com CPF e a senha entregue na recepção.
                            </p>
                            <Button
                                v-if="!usuario || ehPaciente"
                                as-child
                                variant="link"
                                class="mt-6 h-auto w-fit p-0 font-bold text-cyan-700 dark:text-cyan-400"
                            >
                                <Link :href="usuario ? route('portal.acompanhamento') : route('portal.login')">
                                    {{ usuario ? 'Abrir meu acompanhamento' : 'Entrar como paciente' }}
                                    <ArrowRight class="ml-2 h-4 w-4" aria-hidden="true" />
                                </Link>
                            </Button>
                        </article>
                    </div>
                </div>
            </section>

            <section id="seguranca" aria-labelledby="titulo-seguranca" class="bg-white py-20 dark:bg-slate-950 sm:py-28">
                <div class="mx-auto grid max-w-7xl gap-14 px-5 sm:px-8 lg:grid-cols-[0.72fr_1.28fr] lg:px-10">
                    <div class="lg:sticky lg:top-10 lg:self-start">
                        <p class="text-xs font-extrabold uppercase tracking-[0.2em] text-emerald-700 dark:text-emerald-400">
                            Segurança que acompanha o cuidado
                        </p>
                        <h2 id="titulo-seguranca" class="mt-4 text-balance text-3xl font-black tracking-[-0.035em] sm:text-4xl">
                            Confiança não pode ser só uma promessa.
                        </h2>
                        <p class="mt-5 text-base leading-7 text-slate-600 dark:text-slate-300">
                            Por isso, as garantias clínicas vivem no fluxo, no código e no banco de dados — não apenas na interface.
                        </p>
                    </div>

                    <ul
                        class="grid gap-px overflow-hidden rounded-[1.75rem] border border-emerald-950/10 bg-emerald-950/10 dark:border-white/10 dark:bg-white/10 sm:grid-cols-2"
                    >
                        <li v-for="garantia in garantias" :key="garantia.titulo" class="bg-[#f8fbfa] p-7 dark:bg-[#07110f] sm:p-8">
                            <component :is="garantia.icone" class="h-6 w-6 text-emerald-700 dark:text-emerald-400" aria-hidden="true" />
                            <h3 class="mt-10 text-lg font-extrabold">{{ garantia.titulo }}</h3>
                            <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-400">{{ garantia.texto }}</p>
                        </li>
                    </ul>
                </div>
            </section>

            <section class="bg-white px-5 pb-20 dark:bg-slate-950 sm:px-8 sm:pb-28 lg:px-10">
                <div
                    class="relative mx-auto max-w-7xl overflow-hidden rounded-[2rem] bg-emerald-900 px-6 py-12 text-white sm:px-12 sm:py-14 lg:flex lg:items-center lg:justify-between lg:gap-10"
                >
                    <div
                        class="pointer-events-none absolute -right-20 -top-40 h-96 w-96 rounded-full border-[70px] border-emerald-700/50"
                        aria-hidden="true"
                    ></div>
                    <div class="relative max-w-2xl">
                        <p class="text-xs font-extrabold uppercase tracking-[0.2em] text-emerald-300">Pronto para continuar?</p>
                        <h2 class="mt-4 text-balance text-3xl font-black tracking-[-0.035em] sm:text-4xl">Entre pelo ambiente certo para você.</h2>
                        <p class="mt-4 text-sm leading-6 text-emerald-100 sm:text-base">
                            A equipe usa as credenciais institucionais. O paciente usa CPF e a senha recebida na recepção.
                        </p>
                    </div>
                    <div class="relative mt-8 flex shrink-0 flex-col gap-3 sm:flex-row lg:mt-0">
                        <Button v-if="usuario" size="lg" as-child class="h-12 rounded-xl bg-white px-6 text-emerald-950 hover:bg-emerald-50">
                            <Link :href="destinoAutenticado">
                                {{ rotuloDestinoAutenticado }}
                                <ArrowRight class="ml-2 h-4 w-4" aria-hidden="true" />
                            </Link>
                        </Button>
                        <template v-else>
                            <Button size="lg" as-child class="h-12 rounded-xl bg-white px-6 text-emerald-950 hover:bg-emerald-50">
                                <Link :href="route('login')">Acesso da equipe</Link>
                            </Button>
                            <Button
                                size="lg"
                                variant="outline"
                                as-child
                                class="h-12 rounded-xl border-white/30 bg-transparent px-6 text-white hover:bg-white/10 hover:text-white"
                            >
                                <Link :href="route('portal.login')">Portal do paciente</Link>
                            </Button>
                        </template>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-emerald-950/10 bg-[#f8fbfa] dark:border-white/10 dark:bg-[#07110f]">
            <div class="mx-auto grid max-w-7xl gap-8 px-5 py-10 sm:px-8 md:grid-cols-[1fr_auto] md:items-end lg:px-10">
                <div>
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-700 text-white">
                            <HeartPulse class="h-4 w-4" aria-hidden="true" />
                        </span>
                        <span class="font-extrabold">{{ $page.props.name }}</span>
                    </div>
                    <p class="mt-4 max-w-2xl text-xs leading-5 text-slate-500 dark:text-slate-400">
                        Acesso restrito a profissionais autorizados e pacientes cadastrados. Dados de saúde são pessoais sensíveis e todo acesso
                        clínico é registrado.
                    </p>
                </div>
                <p
                    class="max-w-md rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs leading-5 text-red-900 dark:border-red-900/70 dark:bg-red-950/40 dark:text-red-200"
                >
                    <strong class="font-extrabold">Em caso de emergência:</strong> procure o pronto-socorro mais próximo ou ligue 192 (SAMU).
                </p>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.landing-grid {
    background-image:
        linear-gradient(to right, rgb(6 78 59 / 0.07) 1px, transparent 1px), linear-gradient(to bottom, rgb(6 78 59 / 0.07) 1px, transparent 1px);
    background-size: 42px 42px;
    mask-image: linear-gradient(to bottom, black, transparent 85%);
}
</style>
