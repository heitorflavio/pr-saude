import { onBeforeUnmount, ref, shallowRef } from 'vue';

/**
 * Leitura de QR Code pela câmera (doc §4.3, RF-44).
 *
 * Usa a Barcode Detection API nativa quando existe e cai no ponyfill (ZXing-C++ em
 * WASM) quando não — Firefox e Safari não têm a API nativa. O import é dinâmico para
 * não baixar o `.wasm` em navegadores que já a possuem.
 */
type DetectorConstruivel = new (opcoes: { formats: string[] }) => {
    detect(fonte: CanvasImageSource): Promise<Array<{ rawValue: string }>>;
};

async function resolverDetector(): Promise<DetectorConstruivel> {
    if ('BarcodeDetector' in globalThis) {
        return (globalThis as unknown as { BarcodeDetector: DetectorConstruivel }).BarcodeDetector;
    }

    const { BarcodeDetector: Ponyfill } = await import('barcode-detector/pure');

    return Ponyfill as unknown as DetectorConstruivel;
}

/** Os dois modos de falha que VÃO acontecer em uso real. */
export type FalhaCamera = 'permissao-negada' | 'sem-camera' | 'sem-suporte' | null;

export function useQrScanner() {
    const lendo = ref(false);
    const falha = ref<FalhaCamera>(null);
    const ultimoToken = ref<string | null>(null);

    const video = ref<HTMLVideoElement | null>(null);
    const stream = shallowRef<MediaStream | null>(null);
    let intervalo: ReturnType<typeof setInterval> | null = null;

    const parar = () => {
        if (intervalo !== null) {
            clearInterval(intervalo);
            intervalo = null;
        }

        stream.value?.getTracks().forEach((track) => track.stop());
        stream.value = null;
        lendo.value = false;
    };

    const iniciar = async (elemento: HTMLVideoElement, aoDetectar: (token: string) => void) => {
        falha.value = null;
        video.value = elemento;

        // getUserMedia só existe em contexto seguro (HTTPS ou localhost). Sem isso não
        // há o que tentar — e o profissional precisa saber disso, não ver a câmera
        // "não funcionar".
        if (!navigator.mediaDevices?.getUserMedia) {
            falha.value = 'sem-suporte';
            return;
        }

        try {
            stream.value = await navigator.mediaDevices.getUserMedia({
                // A traseira é a que aponta para o punho do paciente.
                video: { facingMode: 'environment' },
            });
        } catch (erro) {
            // Os dois casos que acontecem de verdade em corredor de hospital: o
            // profissional negou a permissão uma vez e esqueceu, ou o desktop da
            // recepção não tem câmera nenhuma.
            const nome = (erro as DOMException)?.name;
            falha.value = nome === 'NotAllowedError' || nome === 'SecurityError' ? 'permissao-negada' : 'sem-camera';
            return;
        }

        elemento.srcObject = stream.value;
        await elemento.play();

        const Detector = await resolverDetector();
        const detector = new Detector({ formats: ['qr_code'] });

        lendo.value = true;

        intervalo = setInterval(async () => {
            if (!lendo.value || elemento.readyState < 2) return;

            try {
                const encontrados = await detector.detect(elemento);
                if (encontrados.length === 0) return;

                const valor = encontrados[0].rawValue;
                // O QR carrega a URL inteira; o que interessa é o último segmento.
                const token = valor.split('/').pop() ?? valor;

                if (token === ultimoToken.value) return;

                ultimoToken.value = token;
                aoDetectar(token);
            } catch {
                // Quadro ilegível é o caso comum (movimento, reflexo, pulseira suja).
                // Não é erro: a próxima tentativa vem em 300 ms.
            }
        }, 300);
    };

    onBeforeUnmount(parar);

    return { iniciar, parar, lendo, falha, ultimoToken };
}
