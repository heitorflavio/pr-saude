<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import Button from '@/components/ui/button/Button.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { KeyRound } from 'lucide-vue-next';

const form = useForm({ password: '', password_confirmation: '' });
const enviar = () => form.post(route('portal.senha.atualizar'), { onFinish: () => form.reset() });
</script>

<template>
    <Head title="Defina sua senha" />
    <main class="grid min-h-screen place-items-center bg-neutral-50 p-4 dark:bg-neutral-950">
        <section class="w-full max-w-md rounded-2xl border bg-white p-6 dark:bg-neutral-900">
            <KeyRound class="h-9 w-9" aria-hidden="true" />
            <h1 class="mt-2 text-2xl font-bold">Crie uma senha pessoal</h1>
            <p class="mt-2 text-sm text-muted-foreground">Use ao menos 8 caracteres. Não use seu CPF, sua data de nascimento nem uma senha comum.</p>
            <form class="mt-5 grid gap-4" @submit.prevent="enviar">
                <label class="grid gap-1 text-sm font-medium"
                    >Nova senha<input
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        class="h-10 rounded-md border bg-transparent px-3"
                        required /></label
                ><InputError :message="form.errors.password" /><label class="grid gap-1 text-sm font-medium"
                    >Confirmar senha<input
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        class="h-10 rounded-md border bg-transparent px-3"
                        required /></label
                ><Button type="submit" :disabled="form.processing">Salvar e continuar</Button>
            </form>
        </section>
    </main>
</template>
