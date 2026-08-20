<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ShieldAlert } from 'lucide-vue-next';

defineProps<{ paciente: { nome: string } }>();

const form = useForm({ justificativa: '' });
const enviar = () => form.post(route('quebra-sigilo.store'));
</script>

<template>
    <Head title="Quebra de sigilo" />
    <AppLayout :breadcrumbs="[{ title: 'Quebra de sigilo', href: '#' }]">
        <main class="mx-auto w-full max-w-2xl p-4">
            <section class="rounded-xl border border-amber-400 bg-amber-50 p-6 text-amber-950 dark:bg-amber-950 dark:text-amber-50">
                <h1 class="flex items-center gap-2 text-xl font-bold">
                    <ShieldAlert class="h-6 w-6" aria-hidden="true" />
                    Acesso excepcional ao prontuário
                </h1>
                <p class="mt-3">
                    Você não possui vínculo assistencial com {{ paciente.nome }}. O acesso completo exige justificativa e será registrado na auditoria.
                </p>

                <form class="mt-5 space-y-3" @submit.prevent="enviar">
                    <label for="justificativa" class="block font-medium">Justificativa clínica ou assistencial</label>
                    <textarea
                        id="justificativa"
                        v-model="form.justificativa"
                        required
                        minlength="10"
                        maxlength="1000"
                        rows="5"
                        class="w-full rounded-md border border-amber-600 bg-white p-3 text-neutral-950 focus:outline-none focus:ring-2 focus:ring-amber-800"
                    />
                    <InputError :message="form.errors.justificativa" />
                    <button type="submit" :disabled="form.processing" class="rounded-md bg-amber-900 px-4 py-2 font-semibold text-white focus:outline-none focus:ring-2 focus:ring-amber-950 focus:ring-offset-2 disabled:opacity-50">
                        Justificar e acessar
                    </button>
                </form>
            </section>
        </main>
    </AppLayout>
</template>
