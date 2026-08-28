-- Acrescenta o rastro de edição da DESCRIÇÃO de uma transação.
-- Passada ÚNICA, para rodar à mão. Não é lida por script nenhum.
--
-- POR QUE
-- Histórico e comprovante são descritivos: nenhuma conta os lê (o saldo vem de
-- SUM(lan_valor) e o invariante de contar pernas), então editá-los não move um
-- centavo. O que faltava era o rastro: transacoes guarda quem CRIOU a linha e
-- quando, e sem estas duas colunas uma edição ficaria invisível nos dados — a
-- linha continuaria dizendo que foi registrada por quem a criou, na data original.
--
-- Valor, contas e data continuam SEM edição: esses estão na aritmética, e em
-- partidas dobradas o certo é lançar um ajuste, não reescrever o passado.
--
-- Idempotente NÃO É: MySQL 5.6 não tem ADD COLUMN IF NOT EXISTS. Rodar duas vezes
-- dá erro 1060 (Duplicate column name), que é inofensivo — mas confira antes.

-- confira ANTES: tem de devolver 0
SELECT COUNT(*) AS ja_existe
FROM information_schema.columns
WHERE table_schema = DATABASE() AND table_name = 'transacoes'
  AND column_name IN ('tra_usr_alteracao', 'tra_dt_alteracao');

ALTER TABLE `transacoes`
  ADD COLUMN `tra_usr_alteracao` mediumint(6) unsigned DEFAULT NULL COMMENT 'quem editou a descricao; nulo = nunca editada' AFTER `tra_dt_registro`,
  ADD COLUMN `tra_dt_alteracao`  datetime DEFAULT NULL COMMENT 'quando a descricao foi editada' AFTER `tra_usr_alteracao`;

-- confira DEPOIS: tem de devolver 2
SELECT COUNT(*) AS criadas
FROM information_schema.columns
WHERE table_schema = DATABASE() AND table_name = 'transacoes'
  AND column_name IN ('tra_usr_alteracao', 'tra_dt_alteracao');
