INSERT INTO `produtotipos` (`prodt_id`, `prodt_nome`) VALUES
(1, 'Frescos'),
(2, 'Secos'),
(3, 'Sementes');

INSERT INTO `papeis` (`pap_id`, `pap_nome`) VALUES
(1, 'Administrador'),
(2, 'Responsável por Núcleo'),
(3, 'Responsável por Pedido'),
(4, 'Responsável pelo Mutirão');

INSERT INTO `nucleos` (`nuc_id`, `nuc_nome_curto`, `nuc_nome_completo`, `nuc_email`, `nuc_entrega_horario`, `nuc_entrega_endereco`, `nuc_archive`) VALUES
(1, 'Núcleo Teste', 'Nucleo Teste Início Sistema', 'nucleo@mudar.com', '10h às 12h', 'Rua Tal, 123 - Bairro', 0);

INSERT INTO `usuarios` (`usr_id`, `usr_nuc`, `usr_associado`, `usr_email`, `usr_email_alternativo`, `usr_senha`, `usr_nome_curto`, `usr_nome_completo`, `usr_endereco`, `usr_contatos`, `usr_desde`, `usr_archive`) VALUES
(1, 1, 1, 'admin@mudar.com', '', '', 'Administrador', 'Administrador Inicial Sistema', '', '1234-5678', NULL, 0);

INSERT INTO `usuariopapeis` (`usrp_usr`, `usrp_pap`, `usrp_por_usr`) VALUES
(1, 1, 1);


INSERT INTO `temp_senhas` (`pass_id`, `pass_nome`) VALUES
(1, 'abacate2'),
(2, 'abacaxi1'),
(3, 'abiu4'),
(4, 'abobora3'),
(5, 'abobrin2'),
(6, 'acelga9'),
(7, 'acerola3'),
(8, 'agriao2'),
(9, 'aipim1'),
(10, 'aipo4'),
(11, 'alcacho1'),
(12, 'alface9'),
(13, 'alfafa7');




