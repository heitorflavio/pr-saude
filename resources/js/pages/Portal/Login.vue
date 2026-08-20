<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import Button from '@/components/ui/button/Button.vue';
import { type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { HeartPulse, ScanLine, ShieldCheck } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{ pulseiraLida: boolean }>();
const page = usePage<SharedData>();
const status = computed(() => page.props.flash?.status);
const form = useForm({ cpf: '', senha: '' });
const enviar = () => form.post(route('portal.autenticar'), { onFinish: () => form.reset('senha') });
</script>

<template>
    <Head title="Entrar no portal" />
    <main class="grid min-h-screen place-items-center bg-neutral-50 p-4 dark:bg-neutral-950">
        <section class="w-full max-w-md rounded-2xl border bg-white p-6 shadow-sm dark:bg-neutral-900">
            <header class="text-center"><HeartPulse class="mx-auto h-10 w-10 text-red-700" aria-hidden="true" /><h1 class="mt-2 text-2xl font-bold">Acompanhe seu atendimento</h1></header>
            <p v-if="status" role="status" class="mt-4 rounded bg-green-50 p-3 text-sm text-green-900 dark:bg-green-950 dark:text-green-100">{{ status }}</p>
            <p v-if="pulseiraLida" class="mt-4 flex items-center gap-2 rounded bg-blue-50 p-3 text-sm text-blue-950 dark:bg-blue-950 dark:text-blue-50"><ShieldCheck class="h-5 w-5" aria-hidden="true" /> Pulseira reconhecida com segurança.</p>
            <p v-else class="mt-4 flex items-start gap-2 rounded bg-amber-50 p-3 text-sm text-amber-950 dark:bg-amber-950 dark:text-amber-50"><ScanLine class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" /> No primeiro acesso, leia o QR Code da pulseira antes de entrar.</p>
            <form class="mt-5 grid gap-4" @submit.prevent="enviar">
                <label class="grid gap-1 text-sm font-medium">CPF<input v-model="form.cpf" inputmode="numeric" autocomplete="username" class="h-10 rounded-md border bg-transparent px-3" required /></label>
                <InputError :message="form.errors.cpf" />
                <label class="grid gap-1 text-sm font-medium">Senha<input v-model="form.senha" type="password" autocomplete="current-password" class="h-10 rounded-md border bg-transparent px-3" required /></label>
                <Button type="submit" :disabled="form.processing">Entrar</Button>
            </form>
        </section>
    </main>
</template>
