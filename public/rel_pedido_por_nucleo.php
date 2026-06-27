<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO] || $_SESSION[PAP_ACOMPANHA_RELATORIOS]  || $_SESSION[PAP_RESP_ENTREGA]  || $_SESSION[PAP_RESP_FINANCAS]);    
  
  top();
  
 
?>

<legend>Relatorio - Pedido de Secos dos Núcleos</legend>



<?php
 
	footer();
?>