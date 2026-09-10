-- Cria as tabelas do razão em PRODUÇÃO. Passada ÚNICA, para rodar à mão.
--
-- POR QUE ESTE ARQUIVO EXISTE. bd_estrutura.sql não serve aqui: é o schema completo
-- para instalação nova e começa DERRUBANDO tabelas. Rodá-lo contra produção apagaria a
-- base. Este traz só o que falta, e nada mais.
--
-- ELE SUBSTITUI TRÊS MIGRAÇÕES. As tabelas nascem aqui na forma FINAL, com as colunas
-- que estes arquivos acrescentariam depois:
--
--   migracao-transacao-alteracao.sql   tra_usr_alteracao, tra_dt_alteracao
--   migracao-transacao-categoria.sql   tra_categoria
--   migracao-favorecido.sql            tra_favorecido
--
-- NÃO RODE OS TRÊS depois deste: eles fariam ADD COLUMN de coluna que já existe e o
-- banco recusaria. Eles continuam no repositório porque servem a uma base que já tenha
-- a forma antiga — não é o caso de produção, que não tem nenhuma das três tabelas.
--
-- migracao-rateio.sql CONTINUA NECESSÁRIA e é a única: ela mexe em tabelas que já
-- existem (nucleos, nucleotipos) e cria a quarta tabela do módulo, rateios.
--
-- O DDL VEIO DO BANCO LOCAL, por SHOW CREATE TABLE, e não de bd_estrutura.sql. Aquele
-- arquivo está à deriva: absorveu a migração de alteração mas não as de categoria e
-- favorecido, então descreve um schema que a suíte reprovaria. O banco local é o que os
-- 454 testes exercitam de verdade.
--
-- É SEGURO RODAR DE NOVO: as três são CREATE TABLE IF NOT EXISTS. Não há DROP,
-- TRUNCATE, DELETE nem UPDATE neste arquivo.
--
-- ORDEM IMPOSTA PELO BANCO: lancamentos declara FOREIGN KEY para contas e transacoes,
-- então as duas precisam existir antes. A ordem abaixo já é essa.
--
-- COMO RODAR
--   1. bloco ANTES — numa base que nunca viu o módulo, não devolve linha nenhuma
--   2. os três CREATE
--   3. bloco DEPOIS — as três aparecem, e vazias
--   4. só então migracao-rateio.sql


-- ---------------------------------------------------------------- ANTES ------
SELECT table_name, engine, table_collation
  FROM information_schema.tables
 WHERE table_schema = DATABASE()
   AND table_name IN ('contas','transacoes','lancamentos','rateios');


-- ---------------------------------------------------------------- CRIA -------
CREATE TABLE IF NOT EXISTS `contas` (
  `con_id` mediumint(6) unsigned NOT NULL AUTO_INCREMENT,
  `con_tipo` varchar(10) NOT NULL COMMENT 'cestante | nucleo | produtor | rede',
  `con_usr` mediumint(6) unsigned DEFAULT NULL COMMENT 'usuarios.usr_id quando cestante',
  `con_nuc` mediumint(6) unsigned DEFAULT NULL COMMENT 'nucleos.nuc_id quando nucleo',
  `con_forn` mediumint(6) unsigned DEFAULT NULL COMMENT 'fornecedores.forn_id quando produtor',
  `con_nome` varchar(120) DEFAULT NULL COMMENT 'rotulo de exibicao; obrigatorio no tipo rede',
  `con_chave` varchar(30) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL COMMENT 'identidade estavel, nao e rotulo; utf8_bin: byte a byte, sem dobrar caixa nem acento',
  `con_archive` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`con_id`),
  UNIQUE KEY `conta_usuario` (`con_usr`),
  UNIQUE KEY `conta_nucleo` (`con_nuc`),
  UNIQUE KEY `conta_fornecedor` (`con_forn`),
  UNIQUE KEY `conta_chave` (`con_chave`),
  KEY `conta_tipo` (`con_tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
;

CREATE TABLE IF NOT EXISTS `transacoes` (
  `tra_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tra_dt` datetime NOT NULL COMMENT 'data do fato, nao a da digitacao',
  `tra_tipo` varchar(20) NOT NULL COMMENT 'debito_entrega | pagamento | ajuste',
  `tra_cha` mediumint(6) unsigned DEFAULT NULL COMMENT 'procedencia, so em debito_entrega',
  `tra_historico` varchar(200) DEFAULT NULL,
  `tra_comprovante` varchar(300) DEFAULT NULL,
  `tra_obs` varchar(400) DEFAULT NULL,
  `tra_usr_registro` mediumint(6) unsigned NOT NULL,
  `tra_dt_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `tra_usr_alteracao` mediumint(6) unsigned DEFAULT NULL COMMENT 'quem editou a descricao; nulo = nunca editada',
  `tra_dt_alteracao` datetime DEFAULT NULL COMMENT 'quando a descricao foi editada',
  `tra_categoria` varchar(30) DEFAULT NULL,
  `tra_favorecido` varchar(120) DEFAULT NULL,
  PRIMARY KEY (`tra_id`),
  KEY `transacao_data` (`tra_dt`),
  KEY `transacao_chamada` (`tra_cha`),
  KEY `transacao_tipo` (`tra_tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
;

CREATE TABLE IF NOT EXISTS `lancamentos` (
  `lan_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lan_tra` int(10) unsigned NOT NULL,
  `lan_con` mediumint(6) unsigned NOT NULL,
  `lan_valor` decimal(10,2) NOT NULL COMMENT 'com sinal; negativo debita a conta',
  PRIMARY KEY (`lan_id`),
  KEY `lancamento_conta` (`lan_con`),
  KEY `lancamento_transacao` (`lan_tra`),
  CONSTRAINT `fk_lancamento_conta` FOREIGN KEY (`lan_con`) REFERENCES `contas` (`con_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_lancamento_transacao` FOREIGN KEY (`lan_tra`) REFERENCES `transacoes` (`tra_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8
;


-- ---------------------------------------------------------------- DEPOIS -----
-- as três têm de aparecer, InnoDB e utf8_general_ci como o resto da base
SELECT table_name, engine, table_collation
  FROM information_schema.tables
 WHERE table_schema = DATABASE()
   AND table_name IN ('contas','transacoes','lancamentos')
 ORDER BY table_name;

-- e vazias: este script cria estrutura, nunca dado
SELECT (SELECT COUNT(*) FROM contas)      AS contas,
       (SELECT COUNT(*) FROM transacoes)  AS transacoes,
       (SELECT COUNT(*) FROM lancamentos) AS lancamentos;

-- as duas FOREIGN KEY de lancamentos precisam ter sido aceitas
SELECT constraint_name, referenced_table_name
  FROM information_schema.key_column_usage
 WHERE table_schema = DATABASE() AND table_name = 'lancamentos'
   AND referenced_table_name IS NOT NULL;
