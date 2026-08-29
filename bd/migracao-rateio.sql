-- Rateio das despesas da Rede entre os núcleos. Passada ÚNICA, à mão.
-- Nenhum script lê este arquivo.
--
-- O QUE ENTRA
--   1. a quota de rateio, em dois níveis: padrão por tipo de núcleo e exceção por núcleo
--   2. a tabela de ATRIBUIÇÃO do rateio
--
-- POR QUE A QUOTA EM DOIS NÍVEIS
-- A regra geral é o tipo: núcleo semanal entrega 4 vezes ao mês e paga 4 quotas,
-- quinzenal 2, mensal 1. Isso é padrão e mora em `nucleotipos`, como dado e não como
-- lista escrita em PHP.
--
-- Mas existem dois casos que o tipo não descreve, e os dois são reais:
--   · núcleo POPULAR paga meia quota (0,5) — não é uma frequência, é um desconto
--   · Logística e Mutirão são núcleos SENTINELA: existem como núcleo no sistema,
--     recebem entrega, e NÃO entram no rateio
-- Por isso a coluna de exceção em `nucleos`: NULL usa o padrão do tipo, 0 fica de fora,
-- e qualquer outro valor manda. Sem ela, "quem não rateia" viraria uma lista de ids
-- dentro do código — e no dia em que nascesse um terceiro sentinela ninguém lembraria.
--
-- POR QUE `rateios` É TABELA PRÓPRIA, E NÃO `lancamentos`
-- Rateio é ATRIBUIÇÃO, não dívida: ninguém transfere dinheiro por causa dele. É um
-- custo carimbado no núcleo para medir se ele se paga. Lançamento tem de somar zero, e
-- pôr a atribuição lá dentro a transformaria em obrigação — que é justamente o que ela
-- não é. Separada, o invariante do razão fica intacto e a atribuição fica auditável:
-- dá para abrir uma despesa da Rede e ver para onde ela foi.
--
-- COMO RODAR (faça backup antes)
--   1. bloco ANTES — as três contagens têm de voltar 0
--   2. os ALTER e o CREATE
--   3. os UPDATE que preenchem as quotas
--   4. bloco DEPOIS — confira as quotas e o total


-- ---------------------------------------------------------------- ANTES ------
SELECT
 (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='nucleotipos' AND COLUMN_NAME='nuct_quota_rateio') AS ja_tem_quota_tipo,
 (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='nucleos' AND COLUMN_NAME='nuc_quota_rateio')      AS ja_tem_quota_nucleo,
 (SELECT COUNT(*) FROM information_schema.TABLES  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='rateios')                                          AS ja_tem_rateios;


-- ---------------------------------------------------------------- SCHEMA -----
ALTER TABLE nucleotipos ADD COLUMN nuct_quota_rateio decimal(3,1) NOT NULL DEFAULT 1.0;
ALTER TABLE nucleos     ADD COLUMN nuc_quota_rateio  decimal(3,1) DEFAULT NULL;

CREATE TABLE rateios (
  rat_tra   int(10) unsigned      NOT NULL,
  rat_nuc   mediumint(6) unsigned NOT NULL,
  rat_valor decimal(10,2)         NOT NULL,
  PRIMARY KEY (rat_tra, rat_nuc),
  KEY rateio_nucleo (rat_nuc)
) ENGINE=InnoDB;


-- ---------------------------------------------------------------- DADOS ------
-- semanal 4 · quinzenal 2 · mensal 1, como a planilha rateia hoje
UPDATE nucleotipos SET nuct_quota_rateio = 4.0 WHERE nuct_nome = 'Semanal';
UPDATE nucleotipos SET nuct_quota_rateio = 2.0 WHERE nuct_nome = 'Quinzenal';
UPDATE nucleotipos SET nuct_quota_rateio = 1.0 WHERE nuct_nome = 'Mensal';

-- sentinelas: existem como núcleo, não entram no rateio
UPDATE nucleos SET nuc_quota_rateio = 0.0 WHERE nuc_nome_curto IN ('Logística','Logistica','Mutirão','Mutirao');


-- ---------------------------------------------------------------- DEPOIS -----
SELECT n.nuc_nome_curto, t.nuct_nome, t.nuct_quota_rateio AS padrao_do_tipo,
       n.nuc_quota_rateio AS excecao,
       IFNULL(n.nuc_quota_rateio, t.nuct_quota_rateio) AS quota_valendo
FROM nucleos n JOIN nucleotipos t ON t.nuct_id = n.nuc_nuct
WHERE n.nuc_archive = 0 ORDER BY quota_valendo DESC, n.nuc_nome_curto;

-- soma das quotas: é o divisor do rateio por entrega
SELECT SUM(IFNULL(n.nuc_quota_rateio, t.nuct_quota_rateio)) AS total_de_quotas,
       COUNT(*) AS nucleos_que_rateiam
FROM nucleos n JOIN nucleotipos t ON t.nuct_id = n.nuc_nuct
WHERE n.nuc_archive = 0 AND IFNULL(n.nuc_quota_rateio, t.nuct_quota_rateio) > 0;
