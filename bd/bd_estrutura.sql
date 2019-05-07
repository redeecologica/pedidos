-- phpMyAdmin SQL Dump
-- version 3.5.7
-- http://www.phpmyadmin.net
--
-- Server version: 5.1.54-rel12.6-log
-- PHP Version: 5.3.14

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT=0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;


-- --------------------------------------------------------


DROP TABLE IF EXISTS `chamadas`;
DROP TABLE IF EXISTS `distribuicao`;
DROP TABLE IF EXISTS `estoque`;
DROP TABLE IF EXISTS `fornecedores`;
DROP TABLE IF EXISTS `nucleos`;
DROP TABLE IF EXISTS `papeis`;
DROP TABLE IF EXISTS `pedidoprodutos`;
DROP TABLE IF EXISTS `produtotipos`;
DROP TABLE IF EXISTS `temp_senhas`;
DROP TABLE IF EXISTS `usuariopapeis`;
DROP TABLE IF EXISTS `usuarioreiniciasenha`;
DROP TABLE IF EXISTS `usuarios`;
DROP TABLE IF EXISTS `chamadanucleos`;
DROP TABLE IF EXISTS `chamadaprodutos`;
DROP TABLE IF EXISTS `produtos`;
DROP TABLE IF EXISTS `pedidos`;
DROP TABLE IF EXISTS `nucleofornecedores`;

DROP TABLE IF EXISTS `nucleotipos`;
DROP TABLE IF EXISTS `associacaotipos`;

--
-- Table structure for table `nucleofornecedores`
--

CREATE TABLE IF NOT EXISTS `nucleofornecedores` (
  `nucforn_nuc` mediumint(6) unsigned NOT NULL,
  `nucforn_forn` mediumint(6) unsigned NOT NULL,
  PRIMARY KEY (`nucforn_nuc`,`nucforn_forn`),
  KEY `fk_nucleofornecedor_nucleo_idx` (`nucforn_nuc`),
  KEY `fk_nucleofornecedor_fornecedor_idx` (`nucforn_forn`),
  KEY `nucforn.nuc` (`nucforn_nuc`),
  KEY `nucforn.forn` (`nucforn_forn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



--
-- Table structure for table `chamadanucleos`
--

CREATE TABLE IF NOT EXISTS `chamadanucleos` (
  `chanuc_cha` mediumint(6) unsigned NOT NULL,
  `chanuc_nuc` mediumint(6) unsigned NOT NULL,
  PRIMARY KEY (`chanuc_cha`,`chanuc_nuc`),
  KEY `fk_chamadanucleo_chamada_idx` (`chanuc_cha`),
  KEY `fk_chamadanucleo_nucleo_idx` (`chanuc_nuc`),
  KEY `chanuc.cha` (`chanuc_cha`),
  KEY `chanuc.nuc` (`chanuc_nuc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `chamadaprodutos`
--

CREATE TABLE IF NOT EXISTS `chamadaprodutos` (
  `chaprod_cha` mediumint(6) unsigned NOT NULL,
  `chaprod_prod` mediumint(6) unsigned NOT NULL,
  `chaprod_disponibilidade` tinyint(4) DEFAULT NULL COMMENT '0 - Não\\n1 - Incerta / Parcial\\n2 - Sim',
  `chaprod_recebido` decimal(7,2) DEFAULT NULL,
  `chaprod_recebido_confirmado` DECIMAL( 7, 2 ) NULL DEFAULT NULL,
  PRIMARY KEY (`chaprod_cha`,`chaprod_prod`),
  KEY `fk_chamadaprod_chamada_idx` (`chaprod_cha`),
  KEY `fk_chamadaprod_produto_idx` (`chaprod_prod`),
  KEY `chaprod.prod` (`chaprod_prod`),
  KEY `chaprod.cha` (`chaprod_cha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `chamadas`
--

CREATE TABLE IF NOT EXISTS `chamadas` (
  `cha_id` mediumint(6) unsigned NOT NULL AUTO_INCREMENT,
  `cha_prodt` smallint(2) unsigned NOT NULL,
  `cha_dt_entrega` datetime DEFAULT NULL,
  `cha_dt_min` datetime NOT NULL,
  `cha_dt_max` datetime DEFAULT NULL,
  `cha_taxa_percentual` DECIMAL(4,2) NOT NULL DEFAULT '0', 
  `cha_dt_prazo_contabil` DATETIME DEFAULT NULL,
  PRIMARY KEY (`cha_id`),
  KEY `fk_chamada_tipo_idx` (`cha_prodt`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `distribuicao`
--

CREATE TABLE IF NOT EXISTS `distribuicao` (
  `dist_cha` mediumint(6) unsigned NOT NULL,
  `dist_nuc` mediumint(6) unsigned NOT NULL,
  `dist_prod` mediumint(6) unsigned NOT NULL,
  `dist_quantidade` decimal(7,2) DEFAULT NULL,
  `dist_quantidade_recebido` DECIMAL( 7, 2 ) NULL DEFAULT NULL,
  `dist_just_dif_entrega` VARCHAR(200) NULL, 
  PRIMARY KEY (`dist_cha`,`dist_nuc`,`dist_prod`),
  KEY `fk_dist_cha_idx` (`dist_cha`),
  KEY `fk_dist_nuc_idx` (`dist_nuc`),
  KEY `fk_dist_prod_idx` (`dist_prod`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `estoque`
--

CREATE TABLE IF NOT EXISTS `estoque` (
  `est_cha` mediumint(6) unsigned NOT NULL DEFAULT '0',
  `est_prod` mediumint(6) unsigned NOT NULL DEFAULT '0',
  `est_prod_qtde_antes` decimal(7,2) DEFAULT NULL,
  `est_prod_qtde_depois` decimal(7,2) DEFAULT NULL,
  `est_obs_antes` VARCHAR(200) NULL,
  `est_obs_depois` VARCHAR(200) NULL,  
  PRIMARY KEY (`est_cha`,`est_prod`),
  KEY `fk_est_cha_idx` (`est_cha`),
  KEY `fk_est_prod_idx` (`est_prod`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `fornecedores`
--

CREATE TABLE IF NOT EXISTS `fornecedores` (
  `forn_id` mediumint(6) unsigned NOT NULL AUTO_INCREMENT,
  `forn_prodt` SMALLINT(2) NOT NULL DEFAULT '-1',
  `forn_nome_curto` varchar(40) NOT NULL,
  `forn_nome_completo` varchar(150) DEFAULT NULL,
  `forn_email` varchar(150) DEFAULT NULL,
  `forn_endereco` varchar(400) DEFAULT NULL,
  `forn_contatos` varchar(400) DEFAULT NULL,
  `forn_archive` tinyint(2) DEFAULT '0',
  `forn_link_info` VARCHAR(200) DEFAULT NULL,
  `forn_info_chamada` TEXT,  
  PRIMARY KEY (`forn_id`),
  KEY `fk_fornecedor_tipo_idx` (`forn_prodt`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estrutura da tabela `textos`
--

CREATE TABLE IF NOT EXISTS `textos` (
  `txt_id` mediumint(6) unsigned NOT NULL AUTO_INCREMENT,
  `txt_modo_html` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '0 se for texto puro, 1 se for HTML',
  `txt_nome_curto` varchar(40) NOT NULL,
  `txt_nome_completo` varchar(200) DEFAULT NULL,
  `txt_conteudo_rascunho` text NOT NULL,
  `txt_conteudo_publicado` text NOT NULL,
  `txt_usr_atualizacao` mediumint(6) unsigned NOT NULL,
  `txt_dt_atualizacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`txt_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;


--
-- Table structure for table `nucleos`
--

CREATE TABLE IF NOT EXISTS `nucleos` (
  `nuc_id` mediumint(6) unsigned NOT NULL AUTO_INCREMENT,
  `nuc_nome_curto` varchar(100) NOT NULL,
  `nuc_nome_completo` varchar(100) DEFAULT NULL,
  `nuc_email` varchar(70) DEFAULT NULL,
  `nuc_entrega_horario` varchar(100) DEFAULT NULL,
  `nuc_entrega_endereco` varchar(1000) DEFAULT NULL,
  `nuc_archive` tinyint(2) DEFAULT '0',
  `nuc_nuct` smallint(2) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`nuc_id`),
  KEY `nuc_id` (`nuc_id`),
  KEY `nuc_id_2` (`nuc_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `papeis`
--

CREATE TABLE IF NOT EXISTS `papeis` (
  `pap_id` smallint(2) unsigned NOT NULL AUTO_INCREMENT,
  `pap_nome` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`pap_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `pedidoprodutos`
--

CREATE TABLE IF NOT EXISTS `pedidoprodutos` (
  `pedprod_ped` mediumint(6) unsigned NOT NULL,
  `pedprod_prod` mediumint(6) unsigned NOT NULL,
  `pedprod_quantidade` decimal(7,2) DEFAULT NULL,
  `pedprod_entregue` decimal(7,2) DEFAULT NULL,
  PRIMARY KEY (`pedprod_ped`,`pedprod_prod`),
  KEY `fk_pedidoproduto_produto_idx` (`pedprod_prod`),
  KEY `fk_pedidoproduto_pedido_idx` (`pedprod_ped`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `pedidos`
--

CREATE TABLE IF NOT EXISTS `pedidos` (
  `ped_id` mediumint(6) unsigned NOT NULL AUTO_INCREMENT,
  `ped_usr` mediumint(6) unsigned NOT NULL,
  `ped_usr_associado` tinyint(1) NOT NULL DEFAULT '0',
  `ped_nuc` mediumint(6) unsigned NOT NULL,
  `ped_cha` mediumint(6) unsigned NOT NULL,
  `ped_fechado` tinyint(1) DEFAULT NULL,
  `ped_dt_atualizacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ped_id`),
  KEY `fk_pedido_usuario_idx` (`ped_usr`),
  KEY `fk_pedido_nucleo_idx` (`ped_nuc`),
  KEY `fk_pedido_chamada_idx` (`ped_cha`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `produtos`
--

CREATE TABLE IF NOT EXISTS `produtos` (
  `prod_auto_inc` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `prod_id` mediumint(6) unsigned NOT NULL,
  `prod_prodt` smallint(2) unsigned NOT NULL,
  `prod_forn` mediumint(6) unsigned NOT NULL,
  `prod_nome` varchar(100) DEFAULT NULL,
  `prod_unidade` varchar(20) DEFAULT NULL,
  `prod_valor_compra` decimal(6,2) NOT NULL,
  `prod_valor_venda` decimal(6,2) NOT NULL,
  `prod_valor_venda_margem` decimal(6,2) NOT NULL,
  `prod_descricao` text,
  `prod_ini_validade` datetime NOT NULL,
  `prod_fim_validade` datetime NOT NULL,
  `prod_multiplo_venda` decimal(4,2) NOT NULL,
  `prod_peso_bruto` mediumint(6) unsigned NULL, 
  `prod_retornavel` smallint unsigned NOT NULL DEFAULT '0',  
  PRIMARY KEY (`prod_auto_inc`),
  KEY `fk_produto_tipo_idx` (`prod_prodt`),
  KEY `fk_produto_fornecedor_idx` (`prod_forn`),
  KEY `prod_id` (`prod_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

-- campo prod_prodt não está sendo mais usado

-- --------------------------------------------------------

--
-- Table structure for table `produtotipos`
--

CREATE TABLE IF NOT EXISTS `produtotipos` (
  `prodt_id` smallint(2) unsigned NOT NULL AUTO_INCREMENT,
  `prodt_nome` varchar(50) NOT NULL,
  `prodt_mutirao` TINYINT( 2 ) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`prodt_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `temp_senhas`
--

CREATE TABLE IF NOT EXISTS `temp_senhas` (
  `pass_id` mediumint(6) unsigned NOT NULL AUTO_INCREMENT,
  `pass_nome` varchar(8) COLLATE latin1_general_ci NOT NULL,
  PRIMARY KEY (`pass_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `usuariopapeis`
--

CREATE TABLE IF NOT EXISTS `usuariopapeis` (
  `usrp_usr` mediumint(6) unsigned NOT NULL,
  `usrp_pap` smallint(2) unsigned NOT NULL,
  `usrp_por_usr` mediumint(6) unsigned NOT NULL,
  `usrp_dt_atualizacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`usrp_usr`,`usrp_pap`),
  KEY `fk_usuariopapel_usuario_idx` (`usrp_usr`),
  KEY `fk_usuariopapel_papel_idx` (`usrp_pap`),
  KEY `fk_usuariopapel_por_usr_idx` (`usrp_por_usr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `usuarioreiniciasenha`
--

CREATE TABLE IF NOT EXISTS `usuarioreiniciasenha` (
  `pass_id` mediumint(6) unsigned NOT NULL AUTO_INCREMENT,
  `pass_usr` mediumint(6) unsigned NOT NULL,
  `pass_dt_pedido` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `pass_codigo` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`pass_id`),
  KEY `fk_pass_usr_idx` (`pass_usr`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE IF NOT EXISTS `usuarios` (
  `usr_id` mediumint(6) unsigned NOT NULL AUTO_INCREMENT,
  `usr_nuc` mediumint(6) unsigned NOT NULL,
  `usr_associado` tinyint(1) DEFAULT '0' COMMENT '1 - associado, 0 - nao_associado',
  `usr_asso` SMALLINT(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT 'tipo de associacao',
  `usr_email` varchar(120) NOT NULL,
  `usr_email_alternativo` varchar(800) DEFAULT NULL,
  `usr_senha` varchar(45) DEFAULT NULL,
  `usr_nome_curto` varchar(120) DEFAULT NULL,
  `usr_nome_completo` varchar(120) DEFAULT NULL,
  `usr_endereco` varchar(300) DEFAULT NULL,
  `usr_contatos` varchar(300) DEFAULT NULL,
  `usr_desde` date DEFAULT NULL,
  `usr_atividades` VARCHAR(600) NOT NULL DEFAULT 'a preencher',
  `usr_archive` tinyint(2) DEFAULT '0',
  `usr_dt_atualizacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`usr_id`),
  KEY `usr.nuc` (`usr_nuc`),
  KEY `fk_usuario_nucleo_idx` (`usr_nuc`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;


-- --------------------------------------------------------

--
-- Table structure for table `usuarios_changelog`
--

CREATE TABLE IF NOT EXISTS `usuarios_changelog` (
  `usrlog_id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `usrlog_usr` mediumint(6) unsigned NOT NULL,
  `usrlog_nuc` mediumint(6) unsigned NOT NULL,
  `usrlog_associado` tinyint(1) DEFAULT '0' COMMENT '1 - associado, 0 - nao_associado',
  `usrlog_asso` SMALLINT(2) UNSIGNED COMMENT 'tipo de associacao',
  `usrlog_email` varchar(120) NOT NULL,
  `usrlog_nome_curto` varchar(120) DEFAULT NULL,  
  `usrlog_nome_completo` varchar(120) DEFAULT NULL,
  `usrlog_atividades` VARCHAR(600) NOT NULL DEFAULT 'a preencher',
  `usrlog_archive` tinyint(2) DEFAULT '0',
  `usrlog_dt_atualizacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`usrlog_id`),
  KEY `fk_usrlog_usuario_idx` (`usrlog_usr`),
  KEY `fk_usrlog_nucleo_idx` (`usrlog_nuc`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

--


-- Table structure for table `nucleotipos`
--

CREATE TABLE IF NOT EXISTS `nucleotipos` (
  `nuct_id` smallint(2) unsigned NOT NULL AUTO_INCREMENT,
  `nuct_nome` varchar(50) NOT NULL,
  PRIMARY KEY (`nuct_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;




-- Table structure for table `associacaotipos`
--

CREATE TABLE IF NOT EXISTS `associacaotipos` (
  `asso_id` smallint(2) unsigned NOT NULL AUTO_INCREMENT,
  `asso_nome` varchar(50) NOT NULL,
  `asso_descricao` varchar(600) DEFAULT NULL,  
  PRIMARY KEY (`asso_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;





--  -------------------
-- Triggers para atualizacao da tabela de usuarios_changelog, a cada atualizacao de certos campos na tabela usuarios
DROP TRIGGER IF EXISTS `insert_usuarios_changelog`;
CREATE TRIGGER `insert_usuarios_changelog` AFTER INSERT ON `usuarios` FOR EACH ROW INSERT INTO usuarios_changelog (usrlog_usr, usrlog_nuc, usrlog_associado, usrlog_asso, usrlog_email, usrlog_nome_curto, usrlog_nome_completo, usrlog_atividades, usrlog_archive, usrlog_dt_atualizacao)  VALUES (NEW.usr_id, NEW.usr_nuc, NEW.usr_associado, NEW.usr_asso, NEW.usr_email, NEW.usr_nome_curto, NEW.usr_nome_completo, NEW.usr_atividades, NEW.usr_archive, NEW.usr_dt_atualizacao);


DROP TRIGGER IF EXISTS `update_usuarios_changelog`;
DELIMITER //
DROP TRIGGER IF EXISTS `update_usuarios_changelog`//
CREATE TRIGGER `update_usuarios_changelog`
    AFTER UPDATE ON `usuarios`
    FOR EACH ROW
BEGIN
   IF NOT (OLD.usr_nuc = NEW.usr_nuc AND OLD.usr_associado = NEW.usr_associado AND OLD.usr_asso = NEW.usr_asso AND OLD.usr_email = NEW.usr_email AND OLD.usr_nome_curto = NEW.usr_nome_curto AND  OLD.usr_nome_completo = NEW.usr_nome_completo AND OLD.usr_atividades = NEW.usr_atividades AND OLD.usr_archive = NEW.usr_archive) THEN 
	INSERT INTO usuarios_changelog (usrlog_usr, usrlog_nuc, usrlog_associado, usrlog_asso, usrlog_email, usrlog_nome_curto, usrlog_nome_completo, usrlog_atividades, usrlog_archive, usrlog_dt_atualizacao)  VALUES (NEW.usr_id, NEW.usr_nuc, NEW.usr_associado, NEW.usr_asso, NEW.usr_email, NEW.usr_nome_curto, NEW.usr_nome_completo, NEW.usr_atividades, NEW.usr_archive, NEW.usr_dt_atualizacao);
   END IF;
END//
DELIMITER ;

-----------

ALTER TABLE `pedidos` ADD UNIQUE( `ped_usr`, `ped_cha`);


--
-- Constraints for dumped tables
--

--
-- Constraints for table `chamadanucleos`
--
ALTER TABLE `chamadanucleos`
  ADD CONSTRAINT `fk_chamadanucleo_chamada` FOREIGN KEY (`chanuc_cha`) REFERENCES `chamadas` (`cha_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_chamadanucleo_nucleo` FOREIGN KEY (`chanuc_nuc`) REFERENCES `nucleos` (`nuc_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints for table `chamadaprodutos`
--
ALTER TABLE `chamadaprodutos`
  ADD CONSTRAINT `fk_chamadaprod_chamada` FOREIGN KEY (`chaprod_cha`) REFERENCES `chamadas` (`cha_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_chamadaprod_produto` FOREIGN KEY (`chaprod_prod`) REFERENCES `produtos` (`prod_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints for table `chamadas`
--
ALTER TABLE `chamadas`
  ADD CONSTRAINT `fk_chamada_tipo` FOREIGN KEY (`cha_prodt`) REFERENCES `produtotipos` (`prodt_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints for table `distribuicao`
--
ALTER TABLE `distribuicao`
  ADD CONSTRAINT `fk_dist_cha` FOREIGN KEY (`dist_cha`) REFERENCES `chamadas` (`cha_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_dist_nuc` FOREIGN KEY (`dist_nuc`) REFERENCES `nucleos` (`nuc_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_dist_prod` FOREIGN KEY (`dist_prod`) REFERENCES `produtos` (`prod_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints for table `estoque`
--
ALTER TABLE `estoque`
  ADD CONSTRAINT `fk_est_cha` FOREIGN KEY (`est_cha`) REFERENCES `chamadas` (`cha_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_est_prod` FOREIGN KEY (`est_prod`) REFERENCES `produtos` (`prod_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints for table `pedidoprodutos`
--
ALTER TABLE `pedidoprodutos`
  ADD CONSTRAINT `fk_pedidoproduto_pedido` FOREIGN KEY (`pedprod_ped`) REFERENCES `pedidos` (`ped_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_pedidoproduto_produto` FOREIGN KEY (`pedprod_prod`) REFERENCES `produtos` (`prod_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints for table `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `fk_pedido_chamada` FOREIGN KEY (`ped_cha`) REFERENCES `chamadas` (`cha_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_pedido_nucleo` FOREIGN KEY (`ped_nuc`) REFERENCES `nucleos` (`nuc_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_pedido_usuario` FOREIGN KEY (`ped_usr`) REFERENCES `usuarios` (`usr_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints for table `usuariopapeis`
--
ALTER TABLE `usuariopapeis`
  ADD CONSTRAINT `fk_usuariopapel_papel` FOREIGN KEY (`usrp_pap`) REFERENCES `papeis` (`pap_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_usuariopapel_por_usr` FOREIGN KEY (`usrp_por_usr`) REFERENCES `usuarios` (`usr_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_usuariopapel_usuario` FOREIGN KEY (`usrp_usr`) REFERENCES `usuarios` (`usr_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints for table `usuarioreiniciasenha`
--
ALTER TABLE `usuarioreiniciasenha`
  ADD CONSTRAINT `fk_pass_usr` FOREIGN KEY (`pass_usr`) REFERENCES `usuarios` (`usr_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuario_nucleo` FOREIGN KEY (`usr_nuc`) REFERENCES `nucleos` (`nuc_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;
SET FOREIGN_KEY_CHECKS=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
