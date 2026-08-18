<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{ status?: string }>();

// RN-06: a troca é obrigatória. Toda validação é do servidor (FormRequest / controller);
// aqui só há máscara e feedback visual. Em sistema clínico, validação no cliente que
// diverge da do servidor é passivo de segurança.
const form = useForm({
    senha: '',
    senha_confirmation: '',
});

const enviar = () => {
    form.put(route('senha.provisoria.atualizar'), {
        onFinish: () => form.reset('senha', 'senha_confirmation'),
    });
};
</script>

<template>
    <AuthLayout title="Defina uma nova senha" description="Sua senha atual é provisória. Escolha uma nova senha para continuar.">
        <Head title="Nova senha" />

        <div
            v-if="status"
            class="mb-4 rounded-md bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900 dark:bg-amber-950 dark:text-amber-100"
            role="status"
        >
            {{ status }}
        </div>

        <form @submit.prevent="enviar">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="senha">Nova senha</Label>
                    <Input
                        id="senha"
                        v-model="form.senha"
                        type="password"
                        name="senha"
                        autocomplete="new-password"
                        class="mt-1 block w-full"
                        autofocus
                        required
                    />
                    <p class="text-xs text-muted-foreground">Mínimo de 8 caracteres.</p>
                    <InputError :message="form.errors.senha" />
                </div>

                <div class="grid gap-2">
                    <Label for="senha_confirmation">Repita a nova senha</Label>
                    <Input
                        id="senha_confirmation"
                        v-model="form.senha_confirmation"
                        type="password"
                        name="senha_confirmation"
                        autocomplete="new-password"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError :message="form.errors.senha_confirmation" />
                </div>

                <Button type="submit" class="mt-2 w-full" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Salvar nova senha
                </Button>
            </div>
        </form>
    </AuthLayout>
</template>
