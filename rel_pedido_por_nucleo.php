<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO]);
  
  top();
  
 
?>

<legend>Relatorio - Pedido de Secos dos Núcleos</legend>



<?php
 
	footer();
?>