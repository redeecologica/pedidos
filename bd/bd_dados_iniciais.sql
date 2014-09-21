INSERT INTO `produtotipos` (`prodt_id`, `prodt_nome`, `prodt_mutirao`) VALUES
(1, 'Frescos', '0'),
(2, 'Secos', '1');

INSERT INTO `papeis` (`pap_id`, `pap_nome`) VALUES
(1, 'Administrador'),
(2, 'Responsável por Núcleo'),
(3, 'Responsável por Pedido'),
(4, 'Responsável pelo Mutirão'),
(5, 'Beta Tester'),
(6, 'Acompanhamento de Produtor'),
(7, 'Acompanhamento Relatórios');


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



INSERT INTO `textos` (`txt_id`, `txt_modo_html`, `txt_nome_curto`, `txt_nome_completo`, `txt_conteudo_rascunho`, `txt_conteudo_publicado`, `txt_usr_atualizacao`) VALUES
(1, 1, 'txt_pagina_inicio', 'Texto que aparece na página de início, que é mostrada logo após o usuário fazer login.', '<p><strong>Informes</strong></p>\r\n\r\n<hr />\r\n<p><strong>Atualiza&ccedil;&atilde;o dos seus dados</strong></p>\r\n\r\n<p>&Eacute; importante que voc&ecirc; atualize os seus dados (op&ccedil;&atilde;o &quot;Minha Conta&quot; / &quot;Meus Dados&quot;). Caso precise alterar o n&uacute;cleo ou status de sua associa&ccedil;&atilde;o, procure o <a href="ajuda.php">respons&aacute;vel pelo cadastro no seu n&uacute;cleo</a> (somente ele poder&aacute; alterar este tipo de informa&ccedil;&atilde;o).</p>\r\n\r\n<p>&nbsp;</p>','<p><strong>Informes</strong></p>\r\n\r\n<hr />\r\n<p><strong>Atualiza&ccedil;&atilde;o dos seus dados</strong></p>\r\n\r\n<p>&Eacute; importante que voc&ecirc; atualize os seus dados (op&ccedil;&atilde;o &quot;Minha Conta&quot; / &quot;Meus Dados&quot;). Caso precise alterar o n&uacute;cleo ou status de sua associa&ccedil;&atilde;o, procure o <a href="ajuda.php">respons&aacute;vel pelo cadastro no seu n&uacute;cleo</a> (somente ele poder&aacute; alterar este tipo de informa&ccedil;&atilde;o).</p>\r\n\r\n<p>&nbsp;</p>', 1),
(2, 1, 'txt_pagina_ajuda', 'Texto utilizado na página de Ajuda', '<h3>Ajuda do Sistema</h3>','<h3>Ajuda do Sistema</h3>' , 1),
(3, 1, 'txt_pagina_info_solicita_cad', 'Texto utilizado na página com informações para o cestante solicitar cadastro no sistema.', 'Caso você ainda não possua cadastro, procure o administrador do seu núcleo: ... ','Caso você ainda não possua cadastro, procure o administrador do seu núcleo: ... ', 1),
(4, 0, 'txt_email_final_confirmacao', 'Texto utilizado no final do email de confirmação do pedido, logo após informar horário / local de entrega.', 'Vale Lembrar:\r\n\r\nSe as encomendas não forem buscadas, o consumidor pagará pelos produtos encomendados. Haverá tentativa de repasse a outros cestantes.\r\n\r\nPor favor leve suas bolsas, sacolas de pano, caixas de papelão, caixas de ovos e dinheiro trocado ou cheque para facilitar na entrega dos produtos.\r\n\r\n\r\nUm abraço!\r\nRede Ecológica', 'Vale Lembrar:\r\n\r\nSe as encomendas não forem buscadas, o consumidor pagará pelos produtos encomendados. Haverá tentativa de repasse a outros cestantes.\r\n\r\nPor favor leve suas bolsas, sacolas de pano, caixas de papelão, caixas de ovos e dinheiro trocado ou cheque para facilitar na entrega dos produtos.\r\n\r\n\r\nUm abraço!\r\nRede Ecológica', 1),
(5, 0, 'txt_email_final_info_conta', 'Texto utilizado no final dos emails relacionados à criação de conta / senha.', 'Saudações e até o futuro,\r\nComissão de Pedidos da Rede Ecológica\r\ncomissaopedidos@gmail.com', 'Saudações e até o futuro,\r\nComissão de Pedidos da Rede Ecológica\r\ncomissaopedidos@gmail.com', 1);

