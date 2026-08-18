-- =====================================================================
-- Sistema de Gestão Hospitalar (SGH)
-- Script de criação do esquema físico
-- SGBD alvo: MySQL 8.4 (InnoDB, utf8mb4)
-- Versão: 1.0  ·  Data: 2026-08-18
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS sgh
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_0900_ai_ci;
USE sgh;

-- ---------------------------------------------------------------------
-- 1. ESTRUTURA ORGANIZACIONAL
-- ---------------------------------------------------------------------

CREATE TABLE unidade (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome            VARCHAR(150)    NOT NULL,
    cnes            VARCHAR(7)      NULL COMMENT 'Cadastro Nacional de Estabelecimentos de Saúde',
    fuso_horario    VARCHAR(40)     NOT NULL DEFAULT 'America/Sao_Paulo',
    ativo           BOOLEAN         NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMP       NULL,
    updated_at      TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_unidade_cnes (cnes)
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 2. IDENTIDADE, ACESSO E AUTORIZAÇÃO
-- ---------------------------------------------------------------------

CREATE TABLE usuario (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    login               VARCHAR(60)     NOT NULL COMMENT 'CPF do paciente, matrícula do profissional ou código provisório',
    senha_hash          VARCHAR(255)    NOT NULL COMMENT 'Argon2id (RNF-07)',
    senha_provisoria    BOOLEAN         NOT NULL DEFAULT FALSE COMMENT 'RN-06: força troca no primeiro acesso',
    senha_alterada_em   TIMESTAMP       NULL,
    tipo                ENUM('PACIENTE','PROFISSIONAL','ADMIN') NOT NULL,
    ativo               BOOLEAN         NOT NULL DEFAULT TRUE,
    ultimo_login_em     TIMESTAMP       NULL,
    tentativas_falhas   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_ate       TIMESTAMP       NULL COMMENT 'RNF-08: bloqueio progressivo',
    created_at          TIMESTAMP       NULL,
    updated_at          TIMESTAMP       NULL,
    deleted_at          TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_usuario_login (login),
    KEY ix_usuario_tipo_ativo (tipo, ativo)
) ENGINE = InnoDB;

CREATE TABLE perfil (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome        VARCHAR(60)     NOT NULL,
    descricao   VARCHAR(255)    NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_perfil_nome (nome)
) ENGINE = InnoDB;

CREATE TABLE permissao (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    chave       VARCHAR(100)    NOT NULL COMMENT 'ex.: prontuario.criar',
    descricao   VARCHAR(255)    NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_permissao_chave (chave)
) ENGINE = InnoDB;

CREATE TABLE perfil_permissao (
    perfil_id       BIGINT UNSIGNED NOT NULL,
    permissao_id    BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (perfil_id, permissao_id),
    CONSTRAINT fk_pp_perfil    FOREIGN KEY (perfil_id)    REFERENCES perfil (id)    ON DELETE CASCADE,
    CONSTRAINT fk_pp_permissao FOREIGN KEY (permissao_id) REFERENCES permissao (id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE usuario_perfil (
    usuario_id  BIGINT UNSIGNED NOT NULL,
    perfil_id   BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (usuario_id, perfil_id),
    CONSTRAINT fk_up_usuario FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE CASCADE,
    CONSTRAINT fk_up_perfil  FOREIGN KEY (perfil_id)  REFERENCES perfil (id)  ON DELETE CASCADE
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 3. PACIENTE (especialização de usuario — D-02)
-- ---------------------------------------------------------------------

CREATE TABLE paciente (
    usuario_id                   BIGINT UNSIGNED NOT NULL,
    uuid                         CHAR(36)        NOT NULL,
    token_pulseira               VARCHAR(64)     NOT NULL COMMENT 'RN-03: opaco, único, imutável',
    nome_completo                VARCHAR(150)    NOT NULL,
    nome_social                  VARCHAR(150)    NULL,
    cpf                          CHAR(11)        NULL COMMENT 'RF-04: nulo permitido para não identificado',
    cns                          CHAR(15)        NULL,
    data_nascimento              DATE            NOT NULL,
    sexo                         ENUM('FEMININO','MASCULINO','OUTRO','NAO_INFORMADO') NOT NULL DEFAULT 'NAO_INFORMADO',
    nome_mae                     VARCHAR(150)    NULL,
    telefone                     VARCHAR(20)     NULL,
    contato_emergencia_nome      VARCHAR(150)    NULL,
    contato_emergencia_telefone  VARCHAR(20)     NULL,
    logradouro                   VARCHAR(180)    NULL,
    numero                       VARCHAR(20)     NULL,
    complemento                  VARCHAR(80)     NULL,
    bairro                       VARCHAR(100)    NULL,
    municipio                    VARCHAR(100)    NULL,
    uf                           CHAR(2)         NULL,
    cep                          CHAR(8)         NULL,
    identificacao_provisoria     BOOLEAN         NOT NULL DEFAULT FALSE,
    codigo_provisorio            VARCHAR(20)     NULL,
    observacoes                  TEXT            NULL,
    created_at                   TIMESTAMP       NULL,
    updated_at                   TIMESTAMP       NULL,
    deleted_at                   TIMESTAMP       NULL,
    PRIMARY KEY (usuario_id),
    UNIQUE KEY uk_paciente_uuid (uuid),
    UNIQUE KEY uk_paciente_token (token_pulseira),
    UNIQUE KEY uk_paciente_cpf (cpf),
    UNIQUE KEY uk_paciente_cns (cns),
    UNIQUE KEY uk_paciente_provisorio (codigo_provisorio),
    KEY ix_paciente_nome (nome_completo),
    KEY ix_paciente_nascimento (data_nascimento),
    CONSTRAINT fk_paciente_usuario FOREIGN KEY (usuario_id) REFERENCES usuario (id),
    CONSTRAINT ck_paciente_cpf_digitos CHECK (cpf IS NULL OR cpf REGEXP '^[0-9]{11}$'),
    CONSTRAINT ck_paciente_identificacao CHECK (
        (identificacao_provisoria = FALSE AND cpf IS NOT NULL)
        OR (identificacao_provisoria = TRUE AND codigo_provisorio IS NOT NULL)
    )
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 4. PROFISSIONAL (especialização de usuario — D-02)
-- ---------------------------------------------------------------------

CREATE TABLE profissional (
    usuario_id       BIGINT UNSIGNED NOT NULL,
    unidade_id       BIGINT UNSIGNED NOT NULL,
    nome_completo    VARCHAR(150)    NOT NULL,
    matricula        VARCHAR(30)     NULL,
    categoria        ENUM('MEDICO','ENFERMEIRO','TECNICO_ENFERMAGEM','LABORATORIO','RECEPCAO','FARMACIA','ADMIN') NOT NULL,
    conselho_tipo    ENUM('CRM','COREN','CRF','CRBM','OUTRO') NULL,
    conselho_numero  VARCHAR(20)     NULL,
    conselho_uf      CHAR(2)         NULL,
    especialidade    VARCHAR(100)    NULL,
    capacidade_fila  SMALLINT UNSIGNED NOT NULL DEFAULT 20 COMMENT 'Teto de referência para balanceamento (§7.4)',
    ativo            BOOLEAN         NOT NULL DEFAULT TRUE,
    created_at       TIMESTAMP       NULL,
    updated_at       TIMESTAMP       NULL,
    deleted_at       TIMESTAMP       NULL,
    PRIMARY KEY (usuario_id),
    UNIQUE KEY uk_profissional_conselho (conselho_tipo, conselho_numero, conselho_uf),
    UNIQUE KEY uk_profissional_matricula (matricula),
    KEY ix_profissional_unidade_cat (unidade_id, categoria, ativo),
    CONSTRAINT fk_profissional_usuario FOREIGN KEY (usuario_id) REFERENCES usuario (id),
    CONSTRAINT fk_profissional_unidade FOREIGN KEY (unidade_id) REFERENCES unidade (id),
    CONSTRAINT ck_profissional_conselho CHECK (
        categoria NOT IN ('MEDICO','ENFERMEIRO') OR conselho_numero IS NOT NULL
    )
) ENGINE = InnoDB;

CREATE TABLE profissional_disponibilidade (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    profissional_id  BIGINT UNSIGNED NOT NULL,
    situacao         ENUM('DISPONIVEL','EM_ATENDIMENTO','PAUSA','AUSENTE','FORA_PLANTAO') NOT NULL,
    inicio_em        DATETIME        NOT NULL,
    fim_em           DATETIME        NULL COMMENT 'NULL = situação vigente',
    observacao       VARCHAR(255)    NULL,
    PRIMARY KEY (id),
    KEY ix_disp_prof_vigente (profissional_id, fim_em),
    CONSTRAINT fk_disp_profissional FOREIGN KEY (profissional_id) REFERENCES profissional (usuario_id),
    CONSTRAINT ck_disp_periodo CHECK (fim_em IS NULL OR fim_em >= inicio_em)
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 5. CATÁLOGOS DE DOMÍNIO
-- ---------------------------------------------------------------------

CREATE TABLE classificacao_risco (
    id                          TINYINT UNSIGNED NOT NULL,
    nome                        VARCHAR(40)      NOT NULL,
    cor_nome                    ENUM('VERMELHO','LARANJA','AMARELO','VERDE','AZUL') NOT NULL,
    cor_hex                     CHAR(7)          NOT NULL,
    tempo_alvo_minutos          SMALLINT UNSIGNED NOT NULL,
    peso_ordenacao              TINYINT UNSIGNED NOT NULL COMMENT 'Menor = mais prioritário',
    exige_atendimento_imediato  BOOLEAN          NOT NULL DEFAULT FALSE,
    descricao                   VARCHAR(255)     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_risco_cor (cor_nome),
    UNIQUE KEY uk_risco_peso (peso_ordenacao)
) ENGINE = InnoDB;

CREATE TABLE cid10 (
    codigo      CHAR(7)      NOT NULL,
    descricao   VARCHAR(255) NOT NULL,
    PRIMARY KEY (codigo),
    KEY ix_cid10_descricao (descricao)
) ENGINE = InnoDB;

CREATE TABLE queixa (
    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    descricao               VARCHAR(150)    NOT NULL,
    fluxograma_manchester   VARCHAR(100)    NULL,
    ativo                   BOOLEAN         NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id),
    UNIQUE KEY uk_queixa_descricao (descricao)
) ENGINE = InnoDB;

CREATE TABLE medicamento (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome_comercial      VARCHAR(150)    NOT NULL,
    principio_ativo     VARCHAR(150)    NOT NULL,
    concentracao        VARCHAR(60)     NULL COMMENT 'ex.: 500 mg/mL',
    forma_farmaceutica  VARCHAR(60)     NULL COMMENT 'ex.: comprimido, ampola',
    classe_via          ENUM('ORAL','IV','IM','SC','TOPICO','INALATORIO','RETAL','OFTALMICO','SL','OUTRA') NOT NULL,
    injetavel           BOOLEAN         NOT NULL DEFAULT FALSE,
    alta_vigilancia     BOOLEAN         NOT NULL DEFAULT FALSE COMMENT 'RN-22: exige dupla checagem',
    controlado          BOOLEAN         NOT NULL DEFAULT FALSE COMMENT 'Portaria SVS/MS 344/1998',
    unidade_dose_padrao VARCHAR(20)     NULL,
    dose_maxima_diaria  DECIMAL(10,3)   NULL,
    observacao          TEXT            NULL,
    ativo               BOOLEAN         NOT NULL DEFAULT TRUE,
    created_at          TIMESTAMP       NULL,
    updated_at          TIMESTAMP       NULL,
    deleted_at          TIMESTAMP       NULL,
    PRIMARY KEY (id),
    KEY ix_medicamento_principio (principio_ativo),
    KEY ix_medicamento_nome (nome_comercial),
    KEY ix_medicamento_vigilancia (alta_vigilancia)
) ENGINE = InnoDB;

CREATE TABLE exame (
    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo                  VARCHAR(20)     NOT NULL,
    nome                    VARCHAR(150)    NOT NULL,
    tipo                    ENUM('LABORATORIAL','IMAGEM','GRAFICO','OUTRO') NOT NULL,
    preparo                 TEXT            NULL,
    prazo_padrao_minutos    SMALLINT UNSIGNED NULL,
    ativo                   BOOLEAN         NOT NULL DEFAULT TRUE,
    created_at              TIMESTAMP       NULL,
    updated_at              TIMESTAMP       NULL,
    deleted_at              TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_exame_codigo (codigo),
    KEY ix_exame_tipo (tipo, ativo)
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 6. HISTÓRICO CLÍNICO DO PACIENTE (transversal aos atendimentos)
-- ---------------------------------------------------------------------

CREATE TABLE paciente_alergia (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    paciente_id     BIGINT UNSIGNED NOT NULL,
    substancia      VARCHAR(150)    NOT NULL,
    medicamento_id  BIGINT UNSIGNED NULL COMMENT 'Vínculo ao catálogo, quando aplicável',
    gravidade       ENUM('LEVE','MODERADA','GRAVE','DESCONHECIDA') NOT NULL DEFAULT 'DESCONHECIDA',
    reacao          VARCHAR(255)    NULL,
    registrado_por  BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP       NULL,
    updated_at      TIMESTAMP       NULL,
    deleted_at      TIMESTAMP       NULL,
    PRIMARY KEY (id),
    KEY ix_alergia_paciente (paciente_id),
    KEY ix_alergia_medicamento (medicamento_id),
    CONSTRAINT fk_alergia_paciente     FOREIGN KEY (paciente_id)    REFERENCES paciente (usuario_id),
    CONSTRAINT fk_alergia_medicamento  FOREIGN KEY (medicamento_id) REFERENCES medicamento (id),
    CONSTRAINT fk_alergia_registrador  FOREIGN KEY (registrado_por) REFERENCES profissional (usuario_id)
) ENGINE = InnoDB;

CREATE TABLE paciente_condicao (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    paciente_id     BIGINT UNSIGNED NOT NULL,
    descricao       VARCHAR(255)    NOT NULL,
    cid10_codigo    CHAR(7)         NULL,
    desde           DATE            NULL,
    registrado_por  BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP       NULL,
    updated_at      TIMESTAMP       NULL,
    deleted_at      TIMESTAMP       NULL,
    PRIMARY KEY (id),
    KEY ix_condicao_paciente (paciente_id),
    CONSTRAINT fk_condicao_paciente FOREIGN KEY (paciente_id)   REFERENCES paciente (usuario_id),
    CONSTRAINT fk_condicao_cid      FOREIGN KEY (cid10_codigo)  REFERENCES cid10 (codigo),
    CONSTRAINT fk_condicao_registrador FOREIGN KEY (registrado_por) REFERENCES profissional (usuario_id)
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 7. ATENDIMENTO
-- ---------------------------------------------------------------------

CREATE TABLE atendimento (
    id                          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid                        CHAR(36)        NOT NULL,
    numero                      VARCHAR(20)     NOT NULL COMMENT 'RF-21: ex. 2026-000148',
    paciente_id                 BIGINT UNSIGNED NOT NULL,
    unidade_id                  BIGINT UNSIGNED NOT NULL,
    profissional_responsavel_id BIGINT UNSIGNED NULL,
    classificacao_risco_id      TINYINT UNSIGNED NULL COMMENT 'Classificação vigente (RN-09)',
    status                      ENUM(
                                    'AGUARDANDO_TRIAGEM',
                                    'AGUARDANDO_ATENDIMENTO',
                                    'EM_ATENDIMENTO',
                                    'AGUARDANDO_EXAME',
                                    'EM_EXAME',
                                    'AGUARDANDO_MEDICACAO',
                                    'EM_OBSERVACAO',
                                    'FINALIZADO',
                                    'CANCELADO'
                                ) NOT NULL DEFAULT 'AGUARDANDO_TRIAGEM',
    origem                      ENUM('ESPONTANEA','SAMU','ENCAMINHADO','TRANSFERENCIA') NOT NULL DEFAULT 'ESPONTANEA',
    sintomas_entrada            TEXT            NULL,
    admitido_em                 DATETIME        NOT NULL,
    primeiro_atendimento_em     DATETIME        NULL,
    finalizado_em               DATETIME        NULL,
    desfecho                    ENUM('ALTA','ENCAMINHAMENTO','INTERNACAO','EVASAO','OBITO','TRANSFERENCIA') NULL,
    desfecho_observacao         TEXT            NULL,
    aberto_por                  BIGINT UNSIGNED NULL,
    created_at                  TIMESTAMP       NULL,
    updated_at                  TIMESTAMP       NULL,
    deleted_at                  TIMESTAMP       NULL,
    -- D-07: unicidade de atendimento ativo por paciente/unidade
    ativo_key                   BIGINT UNSIGNED
                                GENERATED ALWAYS AS (
                                    CASE WHEN status IN ('FINALIZADO','CANCELADO')
                                         THEN NULL ELSE paciente_id END
                                ) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uk_atendimento_uuid (uuid),
    UNIQUE KEY uk_atendimento_numero (numero),
    UNIQUE KEY uk_atendimento_ativo (unidade_id, ativo_key),
    KEY ix_atendimento_paciente (paciente_id, admitido_em),
    KEY ix_atendimento_status (unidade_id, status),
    KEY ix_atendimento_responsavel (profissional_responsavel_id, status),
    CONSTRAINT fk_atend_paciente    FOREIGN KEY (paciente_id)                 REFERENCES paciente (usuario_id),
    CONSTRAINT fk_atend_unidade     FOREIGN KEY (unidade_id)                  REFERENCES unidade (id),
    CONSTRAINT fk_atend_responsavel FOREIGN KEY (profissional_responsavel_id) REFERENCES profissional (usuario_id),
    CONSTRAINT fk_atend_risco       FOREIGN KEY (classificacao_risco_id)      REFERENCES classificacao_risco (id),
    CONSTRAINT fk_atend_aberto_por  FOREIGN KEY (aberto_por)                  REFERENCES usuario (id),
    CONSTRAINT ck_atend_desfecho CHECK (status <> 'FINALIZADO' OR desfecho IS NOT NULL),
    CONSTRAINT ck_atend_finalizado CHECK (status <> 'FINALIZADO' OR finalizado_em IS NOT NULL)
) ENGINE = InnoDB;

CREATE TABLE atendimento_status_historico (
    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    atendimento_id          BIGINT UNSIGNED NOT NULL,
    status_anterior         VARCHAR(30)     NULL,
    status_novo             VARCHAR(30)     NOT NULL,
    alterado_por            BIGINT UNSIGNED NOT NULL,
    observacao              TEXT            NULL,
    permanencia_segundos    INT UNSIGNED    NULL COMMENT 'RF-39: tempo no status anterior',
    criado_em               DATETIME(6)     NOT NULL,
    PRIMARY KEY (id),
    KEY ix_hist_atendimento (atendimento_id, criado_em),
    CONSTRAINT fk_hist_atendimento FOREIGN KEY (atendimento_id) REFERENCES atendimento (id),
    CONSTRAINT fk_hist_autor       FOREIGN KEY (alterado_por)   REFERENCES usuario (id)
) ENGINE = InnoDB;

CREATE TABLE atendimento_sintoma (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    atendimento_id      BIGINT UNSIGNED NOT NULL,
    queixa_id           BIGINT UNSIGNED NULL,
    descricao_livre     VARCHAR(255)    NULL,
    PRIMARY KEY (id),
    KEY ix_sintoma_atendimento (atendimento_id),
    CONSTRAINT fk_sintoma_atendimento FOREIGN KEY (atendimento_id) REFERENCES atendimento (id),
    CONSTRAINT fk_sintoma_queixa      FOREIGN KEY (queixa_id)      REFERENCES queixa (id),
    CONSTRAINT ck_sintoma_conteudo CHECK (queixa_id IS NOT NULL OR descricao_livre IS NOT NULL)
) ENGINE = InnoDB;

CREATE TABLE sinal_vital (
    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    atendimento_id          BIGINT UNSIGNED NOT NULL,
    pressao_sistolica       SMALLINT UNSIGNED NULL,
    pressao_diastolica      SMALLINT UNSIGNED NULL,
    frequencia_cardiaca     SMALLINT UNSIGNED NULL,
    frequencia_respiratoria SMALLINT UNSIGNED NULL,
    saturacao_o2            DECIMAL(4,1)    NULL,
    temperatura             DECIMAL(4,1)    NULL,
    glicemia                DECIMAL(5,1)    NULL,
    peso_kg                 DECIMAL(5,2)    NULL,
    altura_cm               SMALLINT UNSIGNED NULL,
    escala_dor              TINYINT UNSIGNED NULL,
    aferido_por             BIGINT UNSIGNED NOT NULL,
    aferido_em              DATETIME        NOT NULL,
    PRIMARY KEY (id),
    KEY ix_sinal_atendimento (atendimento_id, aferido_em),
    CONSTRAINT fk_sinal_atendimento FOREIGN KEY (atendimento_id) REFERENCES atendimento (id),
    CONSTRAINT fk_sinal_aferidor    FOREIGN KEY (aferido_por)    REFERENCES profissional (usuario_id),
    CONSTRAINT ck_sinal_dor CHECK (escala_dor IS NULL OR escala_dor BETWEEN 0 AND 10),
    CONSTRAINT ck_sinal_spo2 CHECK (saturacao_o2 IS NULL OR saturacao_o2 BETWEEN 0 AND 100),
    CONSTRAINT ck_sinal_temp CHECK (temperatura IS NULL OR temperatura BETWEEN 25 AND 45)
) ENGINE = InnoDB;

CREATE TABLE triagem (
    id                          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    atendimento_id              BIGINT UNSIGNED NOT NULL,
    classificacao_risco_id      TINYINT UNSIGNED NOT NULL,
    sinal_vital_id              BIGINT UNSIGNED NULL,
    realizada_por               BIGINT UNSIGNED NOT NULL,
    queixa_principal            TEXT            NOT NULL,
    justificativa_classificacao TEXT            NULL,
    reclassificacao             BOOLEAN         NOT NULL DEFAULT FALSE,
    triagem_anterior_id         BIGINT UNSIGNED NULL,
    criado_em                   DATETIME(6)     NOT NULL,
    PRIMARY KEY (id),
    KEY ix_triagem_atendimento (atendimento_id, criado_em),
    CONSTRAINT fk_triagem_atendimento FOREIGN KEY (atendimento_id)         REFERENCES atendimento (id),
    CONSTRAINT fk_triagem_risco       FOREIGN KEY (classificacao_risco_id) REFERENCES classificacao_risco (id),
    CONSTRAINT fk_triagem_sinal       FOREIGN KEY (sinal_vital_id)         REFERENCES sinal_vital (id),
    CONSTRAINT fk_triagem_executor    FOREIGN KEY (realizada_por)          REFERENCES profissional (usuario_id),
    CONSTRAINT fk_triagem_anterior    FOREIGN KEY (triagem_anterior_id)    REFERENCES triagem (id)
) ENGINE = InnoDB;

CREATE TABLE fila_item (
    id                          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    atendimento_id              BIGINT UNSIGNED NOT NULL,
    profissional_id             BIGINT UNSIGNED NULL COMMENT 'NULL = fila geral, sem atribuição',
    classificacao_risco_id      TINYINT UNSIGNED NOT NULL,
    situacao                    ENUM('AGUARDANDO','CHAMADO','EM_ATENDIMENTO','CONCLUIDO','TRANSFERIDO','DESISTENCIA') NOT NULL DEFAULT 'AGUARDANDO',
    entrou_em                   DATETIME(6)     NOT NULL,
    chamado_em                  DATETIME        NULL,
    saiu_em                     DATETIME        NULL,
    transferido_de_id           BIGINT UNSIGNED NULL,
    justificativa_transferencia TEXT            NULL,
    criado_por                  BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY ix_fila_ordenacao (profissional_id, situacao, classificacao_risco_id, entrou_em),
    KEY ix_fila_atendimento (atendimento_id),
    CONSTRAINT fk_fila_atendimento FOREIGN KEY (atendimento_id)         REFERENCES atendimento (id),
    CONSTRAINT fk_fila_profissional FOREIGN KEY (profissional_id)       REFERENCES profissional (usuario_id),
    CONSTRAINT fk_fila_risco       FOREIGN KEY (classificacao_risco_id) REFERENCES classificacao_risco (id),
    CONSTRAINT fk_fila_anterior    FOREIGN KEY (transferido_de_id)      REFERENCES fila_item (id),
    CONSTRAINT fk_fila_criador     FOREIGN KEY (criado_por)             REFERENCES usuario (id)
) ENGINE = InnoDB;

CREATE TABLE pulseira_impressao (
    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    paciente_id             BIGINT UNSIGNED NOT NULL,
    atendimento_id          BIGINT UNSIGNED NULL,
    classificacao_risco_id  TINYINT UNSIGNED NULL,
    motivo                  ENUM('PRIMEIRA','REIMPRESSAO','RECLASSIFICACAO','DANIFICADA','OUTRO') NOT NULL DEFAULT 'PRIMEIRA',
    observacao              VARCHAR(255)    NULL,
    impressa_por            BIGINT UNSIGNED NOT NULL,
    criado_em               DATETIME        NOT NULL,
    PRIMARY KEY (id),
    KEY ix_pulseira_paciente (paciente_id, criado_em),
    KEY ix_pulseira_atendimento (atendimento_id),
    CONSTRAINT fk_pulseira_paciente    FOREIGN KEY (paciente_id)            REFERENCES paciente (usuario_id),
    CONSTRAINT fk_pulseira_atendimento FOREIGN KEY (atendimento_id)         REFERENCES atendimento (id),
    CONSTRAINT fk_pulseira_risco       FOREIGN KEY (classificacao_risco_id) REFERENCES classificacao_risco (id),
    CONSTRAINT fk_pulseira_impressor   FOREIGN KEY (impressa_por)           REFERENCES profissional (usuario_id)
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 8. PRONTUÁRIO (append-only — D-05)
-- ---------------------------------------------------------------------

CREATE TABLE registro_clinico (
    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid                    CHAR(36)        NOT NULL,
    atendimento_id          BIGINT UNSIGNED NOT NULL,
    tipo                    ENUM('ANAMNESE','EVOLUCAO_MEDICA','EVOLUCAO_ENFERMAGEM','OBSERVACAO','ADENDO','SUMARIO_ALTA','INTERCORRENCIA') NOT NULL,
    subjetivo               TEXT            NULL COMMENT 'SOAP - S',
    objetivo                TEXT            NULL COMMENT 'SOAP - O',
    avaliacao               TEXT            NULL COMMENT 'SOAP - A',
    plano                   TEXT            NULL COMMENT 'SOAP - P',
    conteudo_livre          TEXT            NULL,
    sigiloso                BOOLEAN         NOT NULL DEFAULT FALSE COMMENT 'RF-77: oculto no portal do paciente',
    registro_retificado_id  BIGINT UNSIGNED NULL COMMENT 'RN-16: adendo aponta para o original',
    motivo_retificacao      TEXT            NULL,
    autor_id                BIGINT UNSIGNED NOT NULL,
    autor_nome              VARCHAR(150)    NOT NULL COMMENT 'Snapshot: o log não muda se o cadastro mudar',
    autor_conselho          VARCHAR(40)     NULL COMMENT 'Snapshot ex.: CRM/SP 123456',
    hash_conteudo           CHAR(64)        NOT NULL COMMENT 'SHA-256 do conteúdo canônico',
    hash_anterior           CHAR(64)        NULL COMMENT 'Encadeamento com o registro anterior do atendimento',
    criado_em               DATETIME(6)     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_registro_uuid (uuid),
    KEY ix_registro_atendimento (atendimento_id, criado_em),
    KEY ix_registro_autor (autor_id, criado_em),
    KEY ix_registro_retificado (registro_retificado_id),
    CONSTRAINT fk_registro_atendimento FOREIGN KEY (atendimento_id)         REFERENCES atendimento (id),
    CONSTRAINT fk_registro_autor       FOREIGN KEY (autor_id)               REFERENCES profissional (usuario_id),
    CONSTRAINT fk_registro_retificado  FOREIGN KEY (registro_retificado_id) REFERENCES registro_clinico (id),
    CONSTRAINT ck_registro_adendo CHECK (
        tipo <> 'ADENDO' OR (registro_retificado_id IS NOT NULL AND motivo_retificacao IS NOT NULL)
    )
) ENGINE = InnoDB;

CREATE TABLE diagnostico (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    atendimento_id  BIGINT UNSIGNED NOT NULL,
    cid10_codigo    CHAR(7)         NOT NULL,
    natureza        ENUM('SUSPEITA','DEFINITIVO','DIFERENCIAL') NOT NULL DEFAULT 'SUSPEITA',
    principal       BOOLEAN         NOT NULL DEFAULT FALSE,
    observacao      TEXT            NULL,
    registrado_por  BIGINT UNSIGNED NOT NULL,
    criado_em       DATETIME        NOT NULL,
    PRIMARY KEY (id),
    KEY ix_diagnostico_atendimento (atendimento_id),
    KEY ix_diagnostico_cid (cid10_codigo),
    CONSTRAINT fk_diag_atendimento FOREIGN KEY (atendimento_id) REFERENCES atendimento (id),
    CONSTRAINT fk_diag_cid         FOREIGN KEY (cid10_codigo)   REFERENCES cid10 (codigo),
    CONSTRAINT fk_diag_registrador FOREIGN KEY (registrado_por) REFERENCES profissional (usuario_id)
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 9. MEDICAMENTOS: PRESCRIÇÃO -> APRAZAMENTO -> ADMINISTRAÇÃO (D-04)
-- ---------------------------------------------------------------------

CREATE TABLE prescricao (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    atendimento_id      BIGINT UNSIGNED NOT NULL,
    prescrito_por       BIGINT UNSIGNED NOT NULL,
    status              ENUM('VIGENTE','SUSPENSA','CONCLUIDA') NOT NULL DEFAULT 'VIGENTE',
    vigencia_inicio     DATETIME        NOT NULL,
    vigencia_fim        DATETIME        NULL,
    observacao          TEXT            NULL,
    suspensa_por        BIGINT UNSIGNED NULL,
    suspensa_em         DATETIME        NULL,
    motivo_suspensao    TEXT            NULL,
    criado_em           DATETIME(6)     NOT NULL,
    PRIMARY KEY (id),
    KEY ix_prescricao_atendimento (atendimento_id, status),
    CONSTRAINT fk_presc_atendimento FOREIGN KEY (atendimento_id) REFERENCES atendimento (id),
    CONSTRAINT fk_presc_prescritor  FOREIGN KEY (prescrito_por)  REFERENCES profissional (usuario_id),
    CONSTRAINT fk_presc_suspensor   FOREIGN KEY (suspensa_por)   REFERENCES profissional (usuario_id)
) ENGINE = InnoDB;

CREATE TABLE prescricao_item (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    prescricao_id       BIGINT UNSIGNED NOT NULL,
    medicamento_id      BIGINT UNSIGNED NOT NULL,
    dose                DECIMAL(10,3)   NOT NULL,
    unidade_dose        VARCHAR(20)     NOT NULL,
    via                 ENUM('ORAL','IV','IM','SC','TOPICO','INALATORIO','RETAL','OFTALMICO','SL','OUTRA') NOT NULL,
    frequencia_horas    SMALLINT UNSIGNED NULL COMMENT 'NULL quando se_necessario = TRUE',
    duracao_horas       SMALLINT UNSIGNED NULL,
    se_necessario       BOOLEAN         NOT NULL DEFAULT FALSE COMMENT 'Medicação SOS / PRN',
    diluicao            VARCHAR(255)    NULL,
    velocidade_infusao  VARCHAR(60)     NULL,
    observacao          TEXT            NULL,
    status              ENUM('VIGENTE','SUSPENSO','CONCLUIDO') NOT NULL DEFAULT 'VIGENTE',
    PRIMARY KEY (id),
    KEY ix_item_prescricao (prescricao_id, status),
    KEY ix_item_medicamento (medicamento_id),
    CONSTRAINT fk_item_prescricao  FOREIGN KEY (prescricao_id)  REFERENCES prescricao (id),
    CONSTRAINT fk_item_medicamento FOREIGN KEY (medicamento_id) REFERENCES medicamento (id),
    CONSTRAINT ck_item_dose CHECK (dose > 0),
    CONSTRAINT ck_item_frequencia CHECK (se_necessario = TRUE OR frequencia_horas IS NOT NULL)
) ENGINE = InnoDB;

CREATE TABLE aprazamento (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    prescricao_item_id  BIGINT UNSIGNED NOT NULL,
    sequencia           SMALLINT UNSIGNED NOT NULL,
    horario_previsto    DATETIME        NOT NULL,
    situacao            ENUM('PENDENTE','ADMINISTRADA','NAO_ADMINISTRADA','SUSPENSA') NOT NULL DEFAULT 'PENDENTE',
    PRIMARY KEY (id),
    UNIQUE KEY uk_aprazamento_seq (prescricao_item_id, sequencia),
    KEY ix_aprazamento_agenda (situacao, horario_previsto),
    CONSTRAINT fk_apraz_item FOREIGN KEY (prescricao_item_id) REFERENCES prescricao_item (id)
) ENGINE = InnoDB;

CREATE TABLE administracao_medicamento (
    id                          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    aprazamento_id              BIGINT UNSIGNED NULL COMMENT 'RN-20: único -> impede dupla administração da mesma dose',
    prescricao_item_id          BIGINT UNSIGNED NOT NULL,
    atendimento_id              BIGINT UNSIGNED NOT NULL,
    dose_administrada           DECIMAL(10,3)   NULL,
    unidade_dose                VARCHAR(20)     NULL,
    via                         ENUM('ORAL','IV','IM','SC','TOPICO','INALATORIO','RETAL','OFTALMICO','SL','OUTRA') NULL,
    administrado_em             DATETIME(6)     NOT NULL COMMENT 'RN-29: horário de servidor',
    administrado_por            BIGINT UNSIGNED NOT NULL,
    checado_por                 BIGINT UNSIGNED NULL COMMENT 'RN-22: dupla checagem',
    resultado                   ENUM('ADMINISTRADA','NAO_ADMINISTRADA') NOT NULL DEFAULT 'ADMINISTRADA',
    motivo_nao_administracao    ENUM('RECUSA_PACIENTE','INDISPONIVEL','JEJUM','SUSPENSA_MEDICO','INTERCORRENCIA','ACESSO_INDISPONIVEL','OUTRO') NULL,
    alerta_alergia_sobreposto   BOOLEAN         NOT NULL DEFAULT FALSE,
    justificativa               TEXT            NULL,
    observacao                  TEXT            NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_adm_aprazamento (aprazamento_id),
    KEY ix_adm_atendimento (atendimento_id, administrado_em),
    KEY ix_adm_item (prescricao_item_id),
    KEY ix_adm_executor (administrado_por, administrado_em),
    CONSTRAINT fk_adm_aprazamento FOREIGN KEY (aprazamento_id)     REFERENCES aprazamento (id),
    CONSTRAINT fk_adm_item        FOREIGN KEY (prescricao_item_id) REFERENCES prescricao_item (id),
    CONSTRAINT fk_adm_atendimento FOREIGN KEY (atendimento_id)     REFERENCES atendimento (id),
    CONSTRAINT fk_adm_executor    FOREIGN KEY (administrado_por)   REFERENCES profissional (usuario_id),
    CONSTRAINT fk_adm_checador    FOREIGN KEY (checado_por)        REFERENCES profissional (usuario_id),
    CONSTRAINT ck_adm_motivo CHECK (
        resultado = 'ADMINISTRADA' OR motivo_nao_administracao IS NOT NULL
    ),
    CONSTRAINT ck_adm_justificativa CHECK (
        alerta_alergia_sobreposto = FALSE OR justificativa IS NOT NULL
    ),
    CONSTRAINT ck_adm_dose CHECK (
        resultado = 'NAO_ADMINISTRADA' OR dose_administrada IS NOT NULL
    )
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 10. CLÍNICA E EXAMES
-- ---------------------------------------------------------------------

CREATE TABLE exame_solicitacao (
    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    atendimento_id          BIGINT UNSIGNED NOT NULL,
    exame_id                BIGINT UNSIGNED NOT NULL,
    solicitado_por          BIGINT UNSIGNED NOT NULL,
    carater                 ENUM('ROTINA','URGENTE') NOT NULL DEFAULT 'ROTINA',
    indicacao_clinica       TEXT            NULL,
    situacao                ENUM('SOLICITADO','COLETADO','EM_EXECUCAO','CONCLUIDO','LIBERADO','CANCELADO') NOT NULL DEFAULT 'SOLICITADO',
    solicitado_em           DATETIME(6)     NOT NULL,
    coletado_em             DATETIME        NULL,
    coletado_por            BIGINT UNSIGNED NULL,
    cancelado_em            DATETIME        NULL,
    cancelado_por           BIGINT UNSIGNED NULL,
    motivo_cancelamento     TEXT            NULL,
    PRIMARY KEY (id),
    KEY ix_solic_atendimento (atendimento_id, situacao),
    KEY ix_solic_fila_lab (situacao, carater, solicitado_em),
    KEY ix_solic_exame (exame_id),
    CONSTRAINT fk_solic_atendimento FOREIGN KEY (atendimento_id) REFERENCES atendimento (id),
    CONSTRAINT fk_solic_exame       FOREIGN KEY (exame_id)       REFERENCES exame (id),
    CONSTRAINT fk_solic_solicitante FOREIGN KEY (solicitado_por) REFERENCES profissional (usuario_id),
    CONSTRAINT fk_solic_coletor     FOREIGN KEY (coletado_por)   REFERENCES profissional (usuario_id),
    CONSTRAINT fk_solic_cancelador  FOREIGN KEY (cancelado_por)  REFERENCES profissional (usuario_id),
    CONSTRAINT ck_solic_cancelamento CHECK (
        situacao <> 'CANCELADO' OR motivo_cancelamento IS NOT NULL
    )
) ENGINE = InnoDB;

CREATE TABLE exame_resultado (
    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    exame_solicitacao_id    BIGINT UNSIGNED NOT NULL,
    laudo                   TEXT            NULL,
    conclusao               TEXT            NULL,
    possui_valor_critico    BOOLEAN         NOT NULL DEFAULT FALSE COMMENT 'RN-25',
    executado_por           BIGINT UNSIGNED NOT NULL,
    executado_em            DATETIME        NOT NULL,
    liberado_por            BIGINT UNSIGNED NULL,
    liberado_em             DATETIME        NULL,
    visivel_ao_paciente     BOOLEAN         NOT NULL DEFAULT FALSE COMMENT 'RN-24',
    criado_em               DATETIME(6)     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_resultado_solicitacao (exame_solicitacao_id),
    KEY ix_resultado_critico (possui_valor_critico, criado_em),
    CONSTRAINT fk_result_solicitacao FOREIGN KEY (exame_solicitacao_id) REFERENCES exame_solicitacao (id),
    CONSTRAINT fk_result_executor    FOREIGN KEY (executado_por)        REFERENCES profissional (usuario_id),
    CONSTRAINT fk_result_liberador   FOREIGN KEY (liberado_por)         REFERENCES profissional (usuario_id),
    CONSTRAINT ck_result_liberacao CHECK (
        visivel_ao_paciente = FALSE OR (liberado_por IS NOT NULL AND liberado_em IS NOT NULL)
    )
) ENGINE = InnoDB;

CREATE TABLE exame_resultado_item (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    exame_resultado_id  BIGINT UNSIGNED NOT NULL,
    analito             VARCHAR(120)    NOT NULL,
    valor               VARCHAR(60)     NOT NULL,
    unidade             VARCHAR(30)     NULL,
    referencia_min      DECIMAL(12,4)   NULL,
    referencia_max      DECIMAL(12,4)   NULL,
    referencia_texto    VARCHAR(120)    NULL,
    sinalizacao         ENUM('NORMAL','BAIXO','ALTO','CRITICO','INDETERMINADO') NOT NULL DEFAULT 'NORMAL',
    PRIMARY KEY (id),
    KEY ix_item_resultado (exame_resultado_id),
    CONSTRAINT fk_ritem_resultado FOREIGN KEY (exame_resultado_id) REFERENCES exame_resultado (id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE exame_anexo (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    exame_resultado_id  BIGINT UNSIGNED NOT NULL,
    nome_original       VARCHAR(255)    NOT NULL,
    caminho             VARCHAR(255)    NOT NULL,
    mime                VARCHAR(100)    NOT NULL,
    tamanho_bytes       INT UNSIGNED    NOT NULL,
    hash_sha256         CHAR(64)        NOT NULL COMMENT 'Integridade do arquivo',
    enviado_por         BIGINT UNSIGNED NOT NULL,
    criado_em           DATETIME        NOT NULL,
    PRIMARY KEY (id),
    KEY ix_anexo_resultado (exame_resultado_id),
    CONSTRAINT fk_anexo_resultado FOREIGN KEY (exame_resultado_id) REFERENCES exame_resultado (id),
    CONSTRAINT fk_anexo_remetente FOREIGN KEY (enviado_por)        REFERENCES profissional (usuario_id)
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 11. AUDITORIA (imutável — RNF-11)
-- ---------------------------------------------------------------------

CREATE TABLE auditoria_log (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id          BIGINT UNSIGNED NULL,
    perfis_no_momento   VARCHAR(255)    NULL,
    acao                VARCHAR(60)     NOT NULL,
    entidade            VARCHAR(60)     NULL,
    entidade_id         BIGINT UNSIGNED NULL,
    paciente_id         BIGINT UNSIGNED NULL,
    atendimento_id      BIGINT UNSIGNED NULL,
    justificativa       TEXT            NULL,
    dados_antes         JSON            NULL,
    dados_depois        JSON            NULL,
    ip                  VARCHAR(45)     NULL,
    user_agent          VARCHAR(255)    NULL,
    criado_em           DATETIME(6)     NOT NULL,
    PRIMARY KEY (id),
    KEY ix_audit_paciente (paciente_id, criado_em),
    KEY ix_audit_usuario (usuario_id, criado_em),
    KEY ix_audit_acao (acao, criado_em),
    KEY ix_audit_entidade (entidade, entidade_id)
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 12. CARGA INICIAL DE DOMÍNIO
--     Protocolo de Manchester: cinco níveis, tempos-alvo oficiais
-- ---------------------------------------------------------------------

INSERT INTO classificacao_risco
    (id, nome, cor_nome, cor_hex, tempo_alvo_minutos, peso_ordenacao, exige_atendimento_imediato, descricao)
VALUES
    (1, 'Emergência',   'VERMELHO', '#D32F2F',   0, 1, TRUE,  'Atendimento imediato. Risco iminente de morte.'),
    (2, 'Muito urgente','LARANJA',  '#F57C00',  10, 2, FALSE, 'Atendimento praticamente imediato.'),
    (3, 'Urgente',      'AMARELO',  '#FBC02D',  60, 3, FALSE, 'Atendimento rápido, mas o paciente pode aguardar.'),
    (4, 'Pouco urgente','VERDE',    '#388E3C', 120, 4, FALSE, 'Pode aguardar atendimento ou ser encaminhado.'),
    (5, 'Não urgente',  'AZUL',     '#1976D2', 240, 5, FALSE, 'Pode aguardar atendimento ou ser encaminhado.');

-- ---------------------------------------------------------------------
-- 13. VISÕES DE APOIO
-- ---------------------------------------------------------------------

-- Fila ordenada conforme RN-10 (prioridade, depois ordem de entrada)
CREATE OR REPLACE VIEW vw_fila_ordenada AS
SELECT
    f.id                        AS fila_item_id,
    f.profissional_id,
    a.id                        AS atendimento_id,
    a.numero                    AS atendimento_numero,
    a.status                    AS atendimento_status,
    p.usuario_id                AS paciente_id,
    COALESCE(p.nome_social, p.nome_completo) AS paciente_nome,
    p.data_nascimento,
    TIMESTAMPDIFF(YEAR, p.data_nascimento, CURDATE()) AS idade_anos,
    cr.nome                     AS prioridade_nome,
    cr.cor_nome                 AS prioridade_cor,
    cr.cor_hex                  AS prioridade_hex,
    cr.tempo_alvo_minutos,
    a.admitido_em,
    f.entrou_em,
    TIMESTAMPDIFF(MINUTE, f.entrou_em, NOW()) AS espera_minutos,
    (TIMESTAMPDIFF(MINUTE, f.entrou_em, NOW()) > cr.tempo_alvo_minutos) AS tempo_alvo_excedido,
    ROW_NUMBER() OVER (
        PARTITION BY f.profissional_id
        ORDER BY cr.peso_ordenacao ASC, f.entrou_em ASC
    ) AS posicao
FROM fila_item f
JOIN atendimento a          ON a.id = f.atendimento_id
JOIN paciente p             ON p.usuario_id = a.paciente_id
JOIN classificacao_risco cr ON cr.id = f.classificacao_risco_id
WHERE f.situacao IN ('AGUARDANDO','CHAMADO')
  AND a.deleted_at IS NULL;

-- Carga por profissional, para a tela de atribuição (UC-05)
CREATE OR REPLACE VIEW vw_carga_profissional AS
SELECT
    pr.usuario_id                       AS profissional_id,
    pr.nome_completo,
    pr.categoria,
    pr.especialidade,
    pr.capacidade_fila,
    COALESCE(d.situacao, 'FORA_PLANTAO') AS situacao,
    COUNT(f.id)                          AS pacientes_aguardando,
    SUM(CASE WHEN cr.cor_nome = 'VERMELHO' THEN 1 ELSE 0 END) AS qtd_vermelho,
    SUM(CASE WHEN cr.cor_nome = 'LARANJA'  THEN 1 ELSE 0 END) AS qtd_laranja,
    SUM(CASE WHEN cr.cor_nome = 'AMARELO'  THEN 1 ELSE 0 END) AS qtd_amarelo,
    SUM(CASE WHEN cr.cor_nome = 'VERDE'    THEN 1 ELSE 0 END) AS qtd_verde,
    SUM(CASE WHEN cr.cor_nome = 'AZUL'     THEN 1 ELSE 0 END) AS qtd_azul,
    -- carga ponderada: quanto menor o peso_ordenacao, maior o custo assistencial
    COALESCE(SUM(6 - cr.peso_ordenacao), 0) AS carga_ponderada
FROM profissional pr
LEFT JOIN profissional_disponibilidade d
       ON d.profissional_id = pr.usuario_id AND d.fim_em IS NULL
LEFT JOIN fila_item f
       ON f.profissional_id = pr.usuario_id AND f.situacao IN ('AGUARDANDO','CHAMADO')
LEFT JOIN classificacao_risco cr
       ON cr.id = f.classificacao_risco_id
WHERE pr.ativo = TRUE
  AND pr.deleted_at IS NULL
  AND pr.categoria IN ('MEDICO','ENFERMEIRO')
GROUP BY pr.usuario_id, pr.nome_completo, pr.categoria, pr.especialidade,
         pr.capacidade_fila, d.situacao;

-- Doses pendentes do turno, para o checklist de enfermagem (RF-60)
CREATE OR REPLACE VIEW vw_doses_pendentes AS
SELECT
    ap.id                       AS aprazamento_id,
    ap.horario_previsto,
    ap.situacao,
    a.id                        AS atendimento_id,
    a.numero                    AS atendimento_numero,
    COALESCE(p.nome_social, p.nome_completo) AS paciente_nome,
    p.token_pulseira,
    m.nome_comercial,
    m.principio_ativo,
    m.alta_vigilancia,
    pi.dose,
    pi.unidade_dose,
    pi.via,
    (TIMESTAMPDIFF(MINUTE, ap.horario_previsto, NOW()) > 30) AS atrasada
FROM aprazamento ap
JOIN prescricao_item pi ON pi.id = ap.prescricao_item_id
JOIN prescricao pc      ON pc.id = pi.prescricao_id
JOIN atendimento a      ON a.id = pc.atendimento_id
JOIN paciente p         ON p.usuario_id = a.paciente_id
JOIN medicamento m      ON m.id = pi.medicamento_id
WHERE ap.situacao = 'PENDENTE'
  AND pc.status = 'VIGENTE'
  AND pi.status = 'VIGENTE'
  AND a.status NOT IN ('FINALIZADO','CANCELADO');
