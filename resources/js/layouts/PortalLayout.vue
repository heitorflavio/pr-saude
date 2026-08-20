<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { HeartPulse, LogOut } from 'lucide-vue-next';

const sair = useForm({});
</script>

<template>
    <div class="min-h-screen bg-neutral-50 text-neutral-950 dark:bg-neutral-950 dark:text-neutral-50">
        <a
            href="#conteudo-principal"
            class="sr-only z-50 rounded bg-white px-4 py-2 font-semibold focus:not-sr-only focus:fixed focus:left-4 focus:top-4 dark:bg-neutral-900"
            >Pular para o conteúdo principal</a
        >
        <header class="border-b bg-white dark:bg-neutral-900">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                <Link href="/portal" class="flex items-center gap-2 font-bold"
                    ><HeartPulse class="h-6 w-6 text-red-700" aria-hidden="true" /> Portal do paciente</Link
                >
                <nav aria-label="Portal do paciente" class="flex flex-wrap items-center gap-4 text-sm">
                    <Link href="/portal">Agora</Link><Link href="/portal/medicamentos">Medicamentos</Link><Link href="/portal/exames">Exames</Link
                    ><Link href="/portal/historico">Histórico</Link>
                    <button type="button" class="inline-flex items-center gap-1" @click="sair.post(route('portal.sair'))">
                        <LogOut class="h-4 w-4" aria-hidden="true" /> Sair
                    </button>
                </nav>
            </div>
        </header>
        <div id="conteudo-principal" tabindex="-1"><slot /></div>
    </div>
</template>
