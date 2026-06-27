<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_MUTIRAO]);
  top();

?>


<ul class="nav nav-tabs">
  <li class="active"><a href="#">Mutirão</a></li>
  <li><a href="estoque_pre.php"><i class="glyphicon glyphicon-bed"></i> Estoque Pré-Mutirão</a></li>
  <li><a href="recebimento.php"><i class="glyphicon glyphicon-road"></i> Recebimento</a></li>
  <li><a href="distribuicao_consolidado_por_produtor.php"><i class="glyphicon glyphicon-fullscreen"></i> Distribuição</a></li>  
  <li><a href="estoque_pos.php"><i class="glyphicon glyphicon-bed"></i> Estoque Pós-Mutirão</a></li>
  <li><a href="mutirao_divergencias.php"><i class="glyphicon glyphicon-eye-open"></i> Divergências</a></li>

</ul>


<br>
<br>
     <div class="panel panel-primary">
      <div class="panel-heading">Instruções para Registro do Mutirão</div>
      <div class="panel-body">
         <ul>
          <li>
		  Acesse a aba "Estoque Pré-Mutirão" para registrar o estoque existente antes do mutirão.
          </li>
          <br>
          <li>
          Acesse a aba "Recebimento" para registrar o que foi recebido no mutirão.
          </li>     
           <br>     
          <li>
          Acesse a aba "Distribuição" para registrar o que foi distribuído para cada núcleo.
          </li>
           <br>          
          <li>
          Acesse a aba "Estoque Pós-Mutirão" para registrar o que ficou de estoque depois do mutirão.
          </li>
           <br>          
          <li>
          Acesse a aba "Divergências" para analisar divergências nos registros.
          </li>          
         </ul> 
         <br>
		<span class="glyphicon glyphicon-alert"></span> Para cada chamada existe um prazo definido por Finanças para finalização destes registros.
          
      </div>
    </div>
    

<?php 
 
	footer();
?>