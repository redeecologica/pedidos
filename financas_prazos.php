<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_FINANCAS]);
  top();
?>

<ul class="nav nav-tabs">
  <li><a href="financas.php">Finanças</a></li>
  <li><a href="recebimento.php?action=0&recebimento=final"><i class="glyphicon glyphicon-road"></i> Confirmação Entrega dos Produtores</a></li>
  <li class="active"><a href="#"><i class="glyphicon glyphicon-calendar"></i> Configuração Prazos</a></li>  
</ul>
                                    
<br>

Em desenvolvimento


<?php
	 
  footer();

?>