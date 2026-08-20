<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pacientes', href: '/pacientes' },
    { title: 'Novo paciente', href: '/pacientes/novo' },
];

/**
 * UC-01. Toda validação é do servidor (CadastrarPacienteRequest) — aqui há apenas
 * máscara e feedback visual. Validação no cliente que divirja da do servidor é passivo
 * de segurança em sistema clínico.
 */
const form = useForm({
    nome_completo: '',
    nome_social: '',
    cpf: '',
    identificacao_provisoria: false as boolean,
    cns: '',
    data_nascimento: '',
    sexo: 'NAO_INFORMADO',
    nome_mae: '',
    telefone: '',
    email: '',
    contato_emergencia_nome: '',
    contato_emergencia_telefone: '',
    municipio: '',
    uf: '',
    observacoes: '',
    alergias: [] as { substancia: string; gravidade: string; reacao: string }[],
    condicoes: [] as { descricao: string; desde: string }[],
});

// D-01: a idade é derivada, aqui só para conferência visual do cadastro. O valor
// oficial vem sempre do servidor.
const idadePrevia = computed(() => {
    if (!form.data_nascimento) return null;

    const nascimento = new Date(form.data_nascimento + 'T00:00:00');
    if (Number.isNaN(nascimento.getTime())) return null;

    const hoje = new Date();
    const dias = Math.floor((hoje.getTime() - nascimento.getTime()) / 86_400_000);
    if (dias < 0) return 'data no futuro';
    if (dias <= 30) return `${dias} dia${dias === 1 ? '' : 's'}`;

    const meses = (hoje.getFullYear() - nascimento.getFullYear()) * 12 + (hoje.getMonth() - nascimento.getMonth());
    if (meses < 24) return `${meses} ${meses === 1 ? 'mês' : 'meses'}`;

    const anos = Math.floor(meses / 12);
    return `${anos} ano${anos === 1 ? '' : 's'}`;
});

// A3: menor de idade exige responsável legal.
const ehMenor = computed(() => {
    if (!form.data_nascimento) return false;
    const nascimento = new Date(form.data_nascimento + 'T00:00:00');
    if (Number.isNaN(nascimento.getTime())) return false;
    return Date.now() - nascimento.getTime() < 18 * 365.25 * 86_400_000;
});

const adicionarAlergia = () => form.alergias.push({ substancia: '', gravidade: 'DESCONHECIDA', reacao: '' });
const removerAlergia = (indice: number) => form.alergias.splice(indice, 1);
const adicionarCondicao = () => form.condicoes.push({ descricao: '', desde: '' });
const removerCondicao = (indice: number) => form.condicoes.splice(indice, 1);

const enviar = () => form.post(route('pacientes.store'));
</script>

<template>
    <Head title="Novo paciente" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="mx-auto flex w-full max-w-3xl flex-col gap-8 p-4" @submit.prevent="enviar">
            <section class="grid gap-4">
                <h2 class="text-lg font-semibold">Identificação</h2>

                <div class="flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 dark:border-amber-700 dark:bg-amber-950/40">
                    <Checkbox id="identificacao_provisoria" v-model:checked="form.identificacao_provisoria" />
                    <Label for="identificacao_provisoria" class="cursor-pointer text-sm font-medium">
                        Paciente não identificado (sem documento ou inconsciente)
                    </Label>
                </div>
                <p class="-mt-2 text-xs text-muted-foreground">
                    O sistema gera um código provisório no formato <code>NI-2026-0031</code>, que passa a ser o login do paciente até a regularização.
                </p>

                <div class="grid gap-2">
                    <Label for="cpf">CPF {{ form.identificacao_provisoria ? '(dispensado)' : '' }}</Label>
                    <Input id="cpf" v-model="form.cpf" inputmode="numeric" maxlength="14" :disabled="form.identificacao_provisoria" />
                    <InputError :message="form.errors.cpf" />
                </div>

                <div class="grid gap-2">
                    <Label for="nome_completo">Nome completo</Label>
                    <Input id="nome_completo" v-model="form.nome_completo" required autofocus />
                    <InputError :message="form.errors.nome_completo" />
                </div>

                <div class="grid gap-2">
                    <Label for="nome_social">Nome social</Label>
                    <Input id="nome_social" v-model="form.nome_social" />
                    <p class="text-xs text-muted-foreground">Quando informado, é o nome exibido em todas as telas e na pulseira.</p>
                    <InputError :message="form.errors.nome_social" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="data_nascimento">Data de nascimento</Label>
                        <Input id="data_nascimento" v-model="form.data_nascimento" type="date" required />
                        <p v-if="idadePrevia" class="text-xs text-muted-foreground">Idade: {{ idadePrevia }}</p>
                        <InputError :message="form.errors.data_nascimento" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="sexo">Sexo</Label>
                        <select id="sexo" v-model="form.sexo" class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm">
                            <option value="NAO_INFORMADO">Não informado</option>
                            <option value="FEMININO">Feminino</option>
                            <option value="MASCULINO">Masculino</option>
                            <option value="OUTRO">Outro</option>
                        </select>
                        <InputError :message="form.errors.sexo" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="cns">CNS</Label>
                        <Input id="cns" v-model="form.cns" inputmode="numeric" maxlength="15" />
                        <InputError :message="form.errors.cns" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="nome_mae">Nome da mãe</Label>
                        <Input id="nome_mae" v-model="form.nome_mae" />
                        <InputError :message="form.errors.nome_mae" />
                    </div>
                </div>
            </section>

            <section class="grid gap-4">
                <h2 class="text-lg font-semibold">
                    Contato
                    <span
                        v-if="ehMenor"
                        class="ml-2 rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900 dark:bg-amber-950 dark:text-amber-100"
                    >
                        responsável legal obrigatório
                    </span>
                </h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="telefone">Telefone</Label>
                        <Input id="telefone" v-model="form.telefone" inputmode="tel" />
                        <InputError :message="form.errors.telefone" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="email">E-mail</Label>
                        <Input id="email" v-model="form.email" type="email" />
                        <p class="text-xs text-muted-foreground">Opcional: o cadastro de urgência não é bloqueado por falta de e-mail.</p>
                        <InputError :message="form.errors.email" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="contato_emergencia_nome">{{ ehMenor ? 'Responsável legal' : 'Contato de emergência' }}</Label>
                        <Input id="contato_emergencia_nome" v-model="form.contato_emergencia_nome" />
                        <InputError :message="form.errors.contato_emergencia_nome" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="contato_emergencia_telefone">Telefone do {{ ehMenor ? 'responsável' : 'contato' }}</Label>
                        <Input id="contato_emergencia_telefone" v-model="form.contato_emergencia_telefone" inputmode="tel" />
                        <InputError :message="form.errors.contato_emergencia_telefone" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-[1fr_6rem]">
                    <div class="grid gap-2">
                        <Label for="municipio">Município</Label>
                        <Input id="municipio" v-model="form.municipio" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="uf">UF</Label>
                        <Input id="uf" v-model="form.uf" maxlength="2" />
                    </div>
                </div>
            </section>

            <section class="grid gap-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Alergias conhecidas</h2>
                    <Button type="button" variant="outline" size="sm" @click="adicionarAlergia">
                        <Plus class="h-4 w-4" aria-hidden="true" />
                        Adicionar
                    </Button>
                </div>
                <p class="-mt-2 text-xs text-muted-foreground">
                    Exibidas em destaque em toda tela do atendimento (RF-11) e verificadas por princípio ativo na administração de medicamentos
                    (RN-21).
                </p>

                <div
                    v-for="(alergia, indice) in form.alergias"
                    :key="indice"
                    class="grid gap-3 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border sm:grid-cols-[1fr_10rem_1fr_auto] sm:items-end"
                >
                    <div class="grid gap-2">
                        <Label :for="`alergia-${indice}`">Substância</Label>
                        <Input :id="`alergia-${indice}`" v-model="alergia.substancia" />
                        <InputError :message="form.errors[`alergias.${indice}.substancia` as keyof typeof form.errors]" />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`gravidade-${indice}`">Gravidade</Label>
                        <select
                            :id="`gravidade-${indice}`"
                            v-model="alergia.gravidade"
                            class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                        >
                            <option value="DESCONHECIDA">Desconhecida</option>
                            <option value="LEVE">Leve</option>
                            <option value="MODERADA">Moderada</option>
                            <option value="GRAVE">Grave</option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`reacao-${indice}`">Reação</Label>
                        <Input :id="`reacao-${indice}`" v-model="alergia.reacao" />
                    </div>
                    <Button type="button" variant="ghost" size="sm" :aria-label="`Remover alergia ${indice + 1}`" @click="removerAlergia(indice)">
                        <Trash2 class="h-4 w-4" aria-hidden="true" />
                    </Button>
                </div>
            </section>

            <section class="grid gap-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Condições crônicas</h2>
                    <Button type="button" variant="outline" size="sm" @click="adicionarCondicao">
                        <Plus class="h-4 w-4" aria-hidden="true" />
                        Adicionar
                    </Button>
                </div>

                <div
                    v-for="(condicao, indice) in form.condicoes"
                    :key="indice"
                    class="grid gap-3 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border sm:grid-cols-[1fr_12rem_auto] sm:items-end"
                >
                    <div class="grid gap-2">
                        <Label :for="`condicao-${indice}`">Descrição</Label>
                        <Input :id="`condicao-${indice}`" v-model="condicao.descricao" />
                        <InputError :message="form.errors[`condicoes.${indice}.descricao` as keyof typeof form.errors]" />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`desde-${indice}`">Desde</Label>
                        <Input :id="`desde-${indice}`" v-model="condicao.desde" type="date" />
                    </div>
                    <Button type="button" variant="ghost" size="sm" :aria-label="`Remover condição ${indice + 1}`" @click="removerCondicao(indice)">
                        <Trash2 class="h-4 w-4" aria-hidden="true" />
                    </Button>
                </div>
            </section>

            <div class="flex justify-end gap-2 border-t border-sidebar-border/70 pt-4 dark:border-sidebar-border">
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" aria-hidden="true" />
                    Cadastrar paciente
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
