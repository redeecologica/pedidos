<?php  
  require  "common.inc.php"; 
  verifica_seguranca();
  top();
?>

<?php 
     
	 campanha_atualizacao_cadastro();

	 echo(get_texto_interno("txt_pagina_inicio"));
	 
 	 footer();
?>