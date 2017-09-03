<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_FINANCAS]);
  top();
  
?>

<ul class="nav nav-tabs">
  <li class="active"><a href="#">Finanças</a></li>
  <li><a href="recebimento.php?action=0&recebimento=final"><i class="glyphicon glyphicon-road"></i> Confirmação Entrega dos Produtores</a></li>
  <li><a href="financas_prazos.php"><i class="glyphicon glyphicon-calendar"></i> Configuração Prazos</a></li>  
</ul>
                                    
<br>
  
    <div class="panel panel-primary">
      <div class="panel-heading">Instruções para Finanças</div>
      <div class="panel-body">
         <ul>
          <li>
		  Acesse a aba "Confirmação Entrega Produtores" para registrar o total que foi entregue pelos produtores. Esta informação será a base para os relatórios de previsão de pagamento aos produtores.
          </li>
          <br>
          <li>
          Acesse a aba "Configuração Prazos" para configurar o prazo final para edição das informações de entrega de cada chamada.
          </li>          
         </ul>     
          
        
            
      </div>
    </div>


<?php 
 
	footer();
?>