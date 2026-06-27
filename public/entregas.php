<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_ENTREGA]  || $_SESSION[PAP_RESP_FINANCAS]);  
  top();
  
?>

<ul class="nav nav-tabs">
  <li class="active"><a href="#">Entregas</a></li>
  <li><a href="entrega_nucleos_consolidado.php"><i class="glyphicon glyphicon-road"></i> Recebido pelo Núcleo</a></li>
  <li><a href="entrega_cestantes_consolidado.php"><i class="glyphicon glyphicon-grain"></i> Entregue aos Cestantes</a></li>  
  <li><a href="entrega_divergencias.php"><i class="glyphicon glyphicon-eye-open"></i> Divergências</a></li>    
</ul>

                                    

 <br><br>
  
    <div class="panel panel-primary">
      <div class="panel-heading">Instruções para Registro de Entregas</div>
      <div class="panel-body">
         <ul>
          <li>
		  Acesse a aba "Recebido pelo Núcleo" para registrar o que chegou no núcleo. Esta informação subsidiará Finanças no registro do que foi entregue pelo produtor, e então dar base para o que deve ser pago ao produtor.
          </li>
          <br>
          <li>
          Acesse a aba "Entregue aos Cestantes" para registrar o que foi entregue para cada cestante. Esta informação alimenta o relatório de Entrega Final e o Quadro de Cestantes.
          </li>          
         </ul> 
         <br>
		<span class="glyphicon glyphicon-alert"></span> Para cada chamada existe um prazo definido por Finanças para finalização destes registros.
          
          <br><br>
          Na <a href="relatorios.php">área de Relatórios</a> (menu ADM/Relatórios) é possível visualizar o resultado final do registro de entrega dos cestantes.<br>
          Na <a href="cestantes_quadro.php">funcionalidade Quadro de Cestantes</a> (menu ADM/Quadro de Cestantes) é possível visualizar o total entregue para cada cestante em determinado período.<br>          
            
      </div>
    </div>

<br>

  

    
  
<?php 
 
	footer();
?>