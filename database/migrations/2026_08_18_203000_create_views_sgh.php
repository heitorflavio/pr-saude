<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * As tres views de apoio do schema.sql. Elas ja resolvem a ordenacao da fila, a carga
 * ponderada e o checklist de doses -- essa logica NAO deve ser reimplementada em PHP.
 *
 * Unica adaptacao em relacao ao schema.sql: `paciente.usuario_id` e
 * `profissional.usuario_id` viraram `user_id` (DECISOES.md D-01).
 */
return new class extends Migration
{
    public function up(): void
    {
        // RN-10: prioridade clinica primeiro, ordem de entrada como desempate. A
        // posicao e calculada aqui por ROW_NUMBER(), nunca persistida.
        // RF-33: `tempo_alvo_excedido` sinaliza o estouro do tempo-alvo da cor.
        DB::statement("
            CREATE OR REPLACE VIEW vw_fila_ordenada AS
            SELECT
                f.id                        AS fila_item_id,
                f.profissional_id,
                a.id                        AS atendimento_id,
                a.numero                    AS atendimento_numero,
                a.status                    AS atendimento_status,
                p.user_id                   AS paciente_id,
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
            JOIN paciente p             ON p.user_id = a.paciente_id
            JOIN classificacao_risco cr ON cr.id = f.classificacao_risco_id
            WHERE f.situacao IN ('AGUARDANDO','CHAMADO')
              AND a.deleted_at IS NULL
        ");

        // RF-27, RF-28: carga por profissional para a tela de atribuicao (UC-05).
        // A carga ponderada usa (6 - peso_ordenacao): quanto mais prioritaria a cor,
        // maior o custo assistencial que ela representa na fila do profissional.
        DB::statement("
            CREATE OR REPLACE VIEW vw_carga_profissional AS
            SELECT
                pr.user_id                          AS profissional_id,
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
                COALESCE(SUM(6 - cr.peso_ordenacao), 0) AS carga_ponderada
            FROM profissional pr
            LEFT JOIN profissional_disponibilidade d
                   ON d.profissional_id = pr.user_id AND d.fim_em IS NULL
            LEFT JOIN fila_item f
                   ON f.profissional_id = pr.user_id AND f.situacao IN ('AGUARDANDO','CHAMADO')
            LEFT JOIN classificacao_risco cr
                   ON cr.id = f.classificacao_risco_id
            WHERE pr.ativo = TRUE
              AND pr.deleted_at IS NULL
              AND pr.categoria IN ('MEDICO','ENFERMEIRO')
            GROUP BY pr.user_id, pr.nome_completo, pr.categoria, pr.especialidade,
                     pr.capacidade_fila, d.situacao
        ");

        // RF-60: checklist de doses do turno para a enfermagem.
        DB::statement("
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
            JOIN paciente p         ON p.user_id = a.paciente_id
            JOIN medicamento m      ON m.id = pi.medicamento_id
            WHERE ap.situacao = 'PENDENTE'
              AND pc.status = 'VIGENTE'
              AND pi.status = 'VIGENTE'
              AND a.status NOT IN ('FINALIZADO','CANCELADO')
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_doses_pendentes');
        DB::statement('DROP VIEW IF EXISTS vw_carga_profissional');
        DB::statement('DROP VIEW IF EXISTS vw_fila_ordenada');
    }
};
