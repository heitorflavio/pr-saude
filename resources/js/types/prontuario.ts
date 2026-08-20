/**
 * Formas de dado do prontuário (doc §9), compartilhadas entre a linha do tempo do
 * atendimento e o consolidado do paciente.
 */
export interface Registro {
    id: number;
    tipo: string;
    tipo_rotulo: string;
    usa_soap: boolean;
    subjetivo: string | null;
    objetivo: string | null;
    avaliacao: string | null;
    plano: string | null;
    conteudo_livre: string | null;
    sigiloso: boolean;
    motivo_retificacao: string | null;
    /** Id do registro que este adendo retifica (RN-16); nulo em registro comum. */
    retifica: number | null;
    /** Verdadeiro quando existe adendo apontando para este registro (RF-50). */
    retificado: boolean;
    retificado_por: { id: number; criado_em: string; motivo: string }[];
    autor_nome: string;
    autor_conselho: string | null;
    criado_em: string;
}

export interface Diagnostico {
    id: number;
    codigo: string;
    descricao: string | null;
    natureza: string;
    principal: boolean;
    observacao: string | null;
    criado_em: string;
}

export interface Alergia {
    id: number;
    substancia: string;
    principio_ativo: string;
    gravidade: string;
    reacao: string | null;
}

export interface Episodio {
    id: number;
    numero: string;
    unidade: string | null;
    status: string;
    status_rotulo: string;
    prioridade: string | null;
    prioridade_cor: string | null;
    admitido_em: string | null;
    finalizado_em: string | null;
    desfecho: string | null;
    diagnosticos: Diagnostico[];
    registros: Registro[];
}
