<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useQrScanner } from '@/composables/useQrScanner';
import { router } from '@inertiajs/vue3';
import { Camera, CameraOff, KeyboardIcon, ScanLine } from 'lucide-vue-next';
import { ref } from 'vue';

/**
 * RF-44: leitura da pulseira com **confirmação de identidade em duas etapas** antes de
 * qualquer ação crítica.
 *
 * O leitor nunca executa a ação diretamente. Ele resolve o token e apresenta o paciente
 * para conferência — porque o erro que este componente existe para evitar é justamente
 * o de ler a pulseira errada, e um sistema que age no primeiro beep não dá chance de
 * perceber.
 */
const { iniciar, parar, lendo, falha } = useQrScanner();

const video = ref<HTMLVideoElement | null>(null);
const tokenManual = ref('');

const aoDetectar = (token: string) => {
    parar();
    // Etapa 2 é a tela de contexto: ela mostra nome, nascimento e alergias para
    // conferência antes de qualquer ação.
    router.visit(`/p/${token}`);
};

const ligarCamera = async () => {
    if (video.value) {
        await iniciar(video.value, aoDetectar);
    }
};

const buscarManual = () => {
    if (tokenManual.value.trim() !== '') {
        router.visit(`/p/${tokenManual.value.trim()}`);
    }
};
</script>

<template>
    <div class="grid gap-4">
        <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-black dark:border-sidebar-border">
            <video ref="video" class="aspect-video w-full object-cover" muted playsinline></video>
        </div>

        <div class="flex flex-wrap gap-2">
            <Button v-if="!lendo" type="button" @click="ligarCamera">
                <Camera class="h-4 w-4" aria-hidden="true" />
                Ler pulseira
            </Button>
            <Button v-else type="button" variant="outline" @click="parar">
                <CameraOff class="h-4 w-4" aria-hidden="true" />
                Parar leitura
            </Button>

            <span v-if="lendo" class="inline-flex items-center gap-1.5 text-sm text-muted-foreground">
                <ScanLine class="h-4 w-4 animate-pulse" aria-hidden="true" />
                Aponte para o QR Code da pulseira
            </span>
        </div>

        <!--
            Os dois modos de falha que VÃO acontecer em uso real. Em ambos, a busca
            manual continua disponível: o profissional não pode ficar travado porque a
            câmera falhou.
        -->
        <div
            v-if="falha"
            class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100"
            role="alert"
        >
            <p v-if="falha === 'permissao-negada'">
                Permissão de câmera negada. Libere o acesso nas configurações do navegador ou use a busca manual abaixo.
            </p>
            <p v-else-if="falha === 'sem-camera'">Nenhuma câmera disponível neste dispositivo. Use a busca manual abaixo.</p>
            <p v-else>
                Este navegador não permite acesso à câmera nesta página. A leitura por câmera exige conexão segura (HTTPS). Use a busca manual abaixo.
            </p>
        </div>

        <form
            class="flex flex-col gap-2 border-t border-sidebar-border/70 pt-4 dark:border-sidebar-border sm:flex-row sm:items-end"
            @submit.prevent="buscarManual"
        >
            <div class="w-full min-w-0 flex-1">
                <Label for="token-manual" class="flex items-center gap-1.5">
                    <KeyboardIcon class="h-3.5 w-3.5" aria-hidden="true" />
                    Busca manual
                </Label>
                <Input id="token-manual" v-model="tokenManual" class="mt-1 font-mono" placeholder="Código impresso na pulseira" />
            </div>
            <Button type="submit" variant="outline" class="w-full sm:w-auto">Buscar</Button>
        </form>
    </div>
</template>
