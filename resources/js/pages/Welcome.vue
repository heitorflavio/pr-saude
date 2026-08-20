<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Activity, ArrowRight, ClipboardList, FileLock2, HeartPulse, ListOrdered, LogIn, Pill, ScanLine, ShieldCheck } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Página pública do sistema. É a única tela que qualquer pessoa alcança sem sessão, e
 * por isso **não exibe nenhum dado**: nem contagem de fila, nem nome de unidade, nem
 * indicador. Tudo aqui é texto fixo e dois caminhos de entrada.
 *
 * Os dois caminhos existem porque são dois guards distintos (equipe em `web`, paciente
 * em `paciente`, doc §2.4): mandar todo mundo para `/login` faria o paciente tentar
 * entrar na porta da equipe e receber credencial inválida sem entender por quê.
 */
const page = usePage<SharedData>();

const usuario = computed(() => page.props.auth?.user ?? null);
const ehPaciente = computed(() => usuario.value?.tipo === 'PACIENTE');

const jornada = [
    {
        icone: ScanLine,
        titulo: 'Recepção e identificação',
        texto: 'O cadastro gera a pulseira com nome, data de nascimento e um QR Code de token opaco — sem CPF impresso e sem identificador de banco.',
    },
    {
        icone: Activity,
        titulo: 'Triagem e classificação de risco',
        texto: 'Queixa e sinais vitais levam a uma das cinco cores do Protocolo de Manchester, cada uma com seu tempo-alvo de espera.',
    },
    {
        icone: ListOrdered,
        titulo: 'Fila por prioridade clínica',
        texto: 'A ordem é prioridade primeiro, chegada como desempate. Quem espera além do alvo é sinalizado para reavaliação — a prioridade nunca sobe sozinha.',
    },
    {
        icone: ClipboardList,
        titulo: 'Atendimento, medicação e exames',
        texto: 'Evolução, prescrição, checklist de doses e resultados de exame ficam no mesmo prontuário, com autoria e horário de servidor.',
    },
];

const garantias = [
    {
        icone: FileLock2,
        titulo: 'O prontuário não é sobrescrito',
        texto: 'Registro clínico não aceita edição nem exclusão. Correção entra como adendo apontando para o registro retificado, e os dois permanecem legíveis.',
    },
    {
        icone: Pill,
        titulo: 'Alergia verificada por princípio ativo',
        texto: 'A checagem não depende do nome comercial. Medicamento de alta vigilância exige um segundo profissional, distinto de quem administra.',
    },
    {
        icone: ShieldCheck,
        titulo: 'Acesso vinculado e auditado',
        texto: 'Ler prontuário sem vínculo assistencial exige justificativa registrada. Toda leitura de dado clínico deixa rastro, inclusive a do administrador.',
    },
    {
        icone: HeartPulse,
        titulo: 'O paciente acompanha, não edita',
        texto: 'No portal o paciente vê a própria jornada em linguagem acessível. Resultado de exame aparece só depois da liberação do profissional.',
    },
];
</script>

<template>
    <Head title="Gestão hospitalar de pronto-socorro" />

    <div class="min-h-screen bg-background text-foreground">
        <a
            href="#conteudo-principal"
            class="sr-only z-50 rounded bg-background px-4 py-2 font-semibold focus:not-sr-only focus:fixed focus:left-4 focus:top-4"
        >
            Pular para o conteúdo principal
        </a>

        <header class="border-b">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-4">
                <p class="flex items-center gap-2 text-lg font-bold">
                    <HeartPulse class="h-6 w-6 text-red-700 dark:text-red-500" aria-hidden="true" />
                    {{ $page.props.name }}
                </p>

                <nav aria-label="Acesso ao sistema" class="flex flex-wrap items-center gap-2">
                    <!-- Quem já tem sessão não deveria ver duas portas de entrada: vai
                         direto para o lado que lhe pertence. -->
                    <template v-if="usuario">
                        <Button as-child>
                            <Link :href="ehPaciente ? route('portal.acompanhamento') : route('dashboard')">
                                {{ ehPaciente ? 'Ir ao portal' : 'Ir ao painel' }}
                                <ArrowRight class="ml-2 h-4 w-4" aria-hidden="true" />
                            </Link>
                        </Button>
                    </template>
                    <template v-else>
                        <Button variant="ghost" as-child>
                            <Link :href="route('portal.login')">Portal do paciente</Link>
                        </Button>
                        <Button as-child>
                            <Link :href="route('login')">
                                <LogIn class="mr-2 h-4 w-4" aria-hidden="true" />
                                Acesso da equipe
                            </Link>
                        </Button>
                    </template>
                </nav>
            </div>
        </header>

        <main id="conteudo-principal" tabindex="-1" class="mx-auto max-w-5xl px-4">
            <section class="py-14 sm:py-20">
                <h1 class="max-w-3xl text-3xl font-bold tracking-tight sm:text-4xl">Da recepção à alta, com a prioridade clínica sempre visível</h1>
                <p class="mt-4 max-w-2xl text-lg text-muted-foreground">
                    Sistema de gestão hospitalar para pronto-socorro: cadastro e pulseira, classificação de risco, fila ordenada por gravidade,
                    prontuário rastreável, prescrição com dupla checagem e portal de acompanhamento para o paciente.
                </p>

                <div v-if="!usuario" class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <Button size="lg" as-child>
                        <Link :href="route('login')">
                            <LogIn class="mr-2 h-4 w-4" aria-hidden="true" />
                            Sou da equipe
                        </Link>
                    </Button>
                    <Button size="lg" variant="outline" as-child>
                        <Link :href="route('portal.login')">
                            <HeartPulse class="mr-2 h-4 w-4" aria-hidden="true" />
                            Sou paciente
                        </Link>
                    </Button>
                </div>
                <p v-if="!usuario" class="mt-3 text-sm text-muted-foreground">
                    O paciente entra com CPF e a senha entregue na recepção. A equipe entra com o e-mail e a senha institucionais.
                </p>
            </section>

            <section aria-labelledby="jornada" class="border-t py-12">
                <h2 id="jornada" class="text-xl font-semibold">A jornada do paciente</h2>
                <ol class="mt-6 grid gap-6 sm:grid-cols-2">
                    <li v-for="(etapa, indice) in jornada" :key="etapa.titulo" class="rounded-xl border p-5">
                        <div class="flex items-center gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border font-semibold">
                                {{ indice + 1 }}
                            </span>
                            <h3 class="flex items-center gap-2 font-semibold">
                                <component :is="etapa.icone" class="h-4 w-4 text-muted-foreground" aria-hidden="true" />
                                {{ etapa.titulo }}
                            </h3>
                        </div>
                        <p class="mt-3 text-sm text-muted-foreground">{{ etapa.texto }}</p>
                    </li>
                </ol>
            </section>

            <section aria-labelledby="garantias" class="border-t py-12">
                <h2 id="garantias" class="text-xl font-semibold">O que o sistema garante</h2>
                <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                    Regras que valem no banco e no código, não apenas na tela — porque em sistema clínico o controle que depende de alguém lembrar de
                    aplicá-lo já falhou.
                </p>
                <ul class="mt-6 grid gap-6 sm:grid-cols-2">
                    <li v-for="garantia in garantias" :key="garantia.titulo" class="rounded-xl border p-5">
                        <h3 class="flex items-center gap-2 font-semibold">
                            <component :is="garantia.icone" class="h-4 w-4 text-muted-foreground" aria-hidden="true" />
                            {{ garantia.titulo }}
                        </h3>
                        <p class="mt-3 text-sm text-muted-foreground">{{ garantia.texto }}</p>
                    </li>
                </ul>
            </section>
        </main>

        <footer class="border-t">
            <div class="mx-auto flex max-w-5xl flex-col gap-2 px-4 py-8 text-sm text-muted-foreground">
                <p>
                    Acesso restrito a profissionais autorizados e aos pacientes cadastrados. Os dados de saúde tratados aqui são pessoais sensíveis:
                    todo acesso é registrado em trilha de auditoria.
                </p>
                <p>
                    <strong class="font-semibold text-foreground">Emergência?</strong> Este sistema não substitui atendimento. Em caso de risco de
                    vida, procure o pronto-socorro mais próximo ou ligue 192 (SAMU).
                </p>
            </div>
        </footer>
    </div>
</template>
