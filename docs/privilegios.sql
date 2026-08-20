-- PR Saúde — privilégios mínimos para MySQL 8
-- Execute como administrador do banco, troque as senhas e restrinja o host conforme a rede.

CREATE USER IF NOT EXISTS 'prsaude_app'@'%' IDENTIFIED BY 'ALTERE-ANTES-DE-USAR';
CREATE USER IF NOT EXISTS 'prsaude_leitura'@'%' IDENTIFIED BY 'ALTERE-ANTES-DE-USAR';

-- A aplicação não recebe DDL, GRANT, FILE, PROCESS nem acesso a outros schemas.
-- SELECT e INSERT são gerais; UPDATE/DELETE são concedidos somente às tabelas mutáveis.
-- Isso evita depender de `partial_revokes`, que não é habilitado em todo MySQL.
GRANT SELECT, INSERT ON `prsaude`.* TO 'prsaude_app'@'%';

GRANT UPDATE, DELETE ON `prsaude`.`users` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`password_reset_tokens` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`sessions` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`cache` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`cache_locks` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`jobs` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`job_batches` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`failed_jobs` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`permissions` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`roles` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`model_has_permissions` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`model_has_roles` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`role_has_permissions` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`unidade` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`classificacao_risco` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`cid10` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`queixa` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`medicamento` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`exame` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`exame_faixa_critica` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`paciente` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`profissional` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`profissional_disponibilidade` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`paciente_alergia` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`paciente_condicao` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`atendimento` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`atendimento_sintoma` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`sinal_vital` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`fila_item` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`diagnostico` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`prescricao` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`prescricao_item` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`aprazamento` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`exame_solicitacao` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`exame_resultado` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`exame_resultado_item` TO 'prsaude_app'@'%';
GRANT UPDATE, DELETE ON `prsaude`.`exame_anexo` TO 'prsaude_app'@'%';

-- Sem UPDATE/DELETE por construção: auditoria_log, registro_clinico,
-- atendimento_status_historico, triagem, administracao_medicamento e pulseira_impressao.

-- Conta para BI/auditoria: leitura somente, sem acesso à tabela de credenciais.
GRANT SELECT ON `prsaude`.`vw_fila_ordenada` TO 'prsaude_leitura'@'%';
GRANT SELECT ON `prsaude`.`vw_carga_profissional` TO 'prsaude_leitura'@'%';
GRANT SELECT ON `prsaude`.`vw_doses_pendentes` TO 'prsaude_leitura'@'%';
GRANT SELECT ON `prsaude`.`atendimento` TO 'prsaude_leitura'@'%';
GRANT SELECT ON `prsaude`.`atendimento_status_historico` TO 'prsaude_leitura'@'%';
GRANT SELECT ON `prsaude`.`triagem` TO 'prsaude_leitura'@'%';
GRANT SELECT ON `prsaude`.`fila_item` TO 'prsaude_leitura'@'%';
GRANT SELECT ON `prsaude`.`classificacao_risco` TO 'prsaude_leitura'@'%';

FLUSH PRIVILEGES;
