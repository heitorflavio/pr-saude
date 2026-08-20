<script setup lang="ts">
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

/**
 * Placeholder do portal do paciente — destino do fluxograma da doc §8.3 quando não há
 * sessão.
 *
 * Esta página **não autentica ninguém**, de propósito. O login do portal depende das
 * mitigações M-1 a M-12 da doc §12.2.3, e a mais importante delas (M-3, que transforma
 * a data de nascimento em dois fatores usando o token da pulseira) é o que torna o
 * esquema defensável. Um formulário de login sem elas seria pior que não ter portal.
 * A Fase 11 substitui esta tela.
 *
 * Também é o que garante que a rota `/p/{token}` não sirva de oráculo: token existente e
 * inexistente caem os dois aqui, sem revelar qual é qual.
 */
</script>

<template>
    <AuthLayout title="Acompanhamento do paciente" description="Consulte o andamento do seu atendimento.">
        <Head title="Portal do paciente" />

        <div class="grid gap-4 text-sm">
            <p>
                O portal de acompanhamento ainda não está disponível nesta instalação. Para saber o andamento do seu atendimento, procure a recepção
                da unidade.
            </p>

            <p class="text-muted-foreground">Se você é da equipe assistencial, entre com as suas credenciais para continuar.</p>

            <Link
                :href="route('login')"
                class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground"
            >
                Entrar como equipe
            </Link>
        </div>
    </AuthLayout>
</template>
