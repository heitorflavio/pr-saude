<script setup lang="ts">
import { SidebarProvider } from '@/components/ui/sidebar';
import { onMounted, ref } from 'vue';

interface Props {
    variant?: 'header' | 'sidebar';
}

defineProps<Props>();

const isOpen = ref(true);

onMounted(() => {
    isOpen.value = localStorage.getItem('sidebar') !== 'false';
});

const handleSidebarChange = (open: boolean) => {
    isOpen.value = open;
    localStorage.setItem('sidebar', String(open));
};
</script>

<template>
    <div v-if="variant === 'header'" class="flex min-h-screen w-full flex-col">
        <a
            href="#conteudo-principal"
            class="sr-only z-50 rounded bg-background px-4 py-2 font-semibold focus:not-sr-only focus:fixed focus:left-4 focus:top-4"
            >Pular para o conteúdo principal</a
        >
        <slot />
    </div>
    <SidebarProvider v-else :default-open="isOpen" :open="isOpen" @update:open="handleSidebarChange">
        <a
            href="#conteudo-principal"
            class="sr-only z-50 rounded bg-background px-4 py-2 font-semibold focus:not-sr-only focus:fixed focus:left-4 focus:top-4"
            >Pular para o conteúdo principal</a
        >
        <slot />
    </SidebarProvider>
</template>
