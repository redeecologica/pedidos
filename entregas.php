<?php  
  require  "common.inc.php"; 
  verifica_seguranca( ($_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO]) && $_SESSION[PAP_BETA_TESTER]);
  top();

?>

<div class="panel panel-default">
  <div class="panel-heading">
     <strong>Entregas</strong> &nbsp;&nbsp;&nbsp;
          <label class="label label-danger">Atenção: módulo em desenvolvimento</label>
  </div>
 
	<table class="table table-striped table-bordered">
		<thead>
			<tr>            
				<th>#</th>
				<th>Tipo</th>
        		<th>Data de Entrega</th>
				<th>1) Entregue pelo Produtor</th>                 
				<th>2) Entregue ao Núcleo</th>                
				<th>3) Entregue ao Cestante</th>  
				<th>Previsão Pagamento</th>                  
                              
			</tr>
		</thead>
		<tbody>
				<?php
					$sql = "SELECT cha_id, cha_prodt, cha_dt_entrega cha_dt_entrega_original, date_format(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, prodt_nome ";
					$sql.= "FROM chamadas ";
					$sql.= "LEFT JOIN produtotipos ON cha_prodt = prodt_id ";	
					$sql.= "ORDER BY cha_dt_entrega_original DESC, prodt_nome ";
					$sql.= "LIMIT 10 ";
													
					$res = executa_sql($sql);
					$contador = 0;
				    if($res)
					{
					 while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
				     {
				?>				 
				  <tr>
                  	 <td><?php echo(++$contador);?></td>               
					 <td><?php echo($row['prodt_nome']);?></td>               
					 <td><?php echo($row['cha_dt_entrega']);?></td>
                     <td>
                      <a href="recebimento.php?action=<?php echo(ACAO_EXIBIR_LEITURA);?>&cha_id=<?php echo($row['cha_id']);?>">ver</a>
                      &nbsp;&nbsp;
                     <a href="recebimento.php?action=<?php echo(ACAO_EXIBIR_EDICAO);?>&cha_id=<?php echo($row['cha_id']);?>">editar</a></td>  
                     <td>
                      <a href="rel_distribuicao.php?cha_id=<?php echo($row['cha_id']);?>">ver</a>
                      &nbsp;&nbsp;
                     <a href="distribuicao.php?action=<?php echo(ACAO_EXIBIR_LEITURA);?>&cha_id=<?php echo($row['cha_id']);?>">editar</a></td>  
                     <td>
                     <a href="rel_entrega_cestantes_nucleo.php?cha_id=<?php echo($row['cha_id']);?>">ver</a>
                     &nbsp;&nbsp;
                     <a href="entrega_cestante.php?action=<?php echo(ACAO_EXIBIR_LEITURA);?>&cha_id=<?php echo($row['cha_id']);?>">editar</a></td>                      
                     <td>
                      <a href="rel_previsao_pagamento.php?cha_id=<?php echo($row['cha_id']);?>">consolidado</a> &nbsp;
                      <a href="rel_previsao_pagamento_detalhado.php?cha_id=<?php echo($row['cha_id']);?>">detalhado</a>                      
                     <br>
                     </td>                      
				  </tr>
				<?php 
				     }
				   }
				?>
		</tbody>
	</table>
 </div>   
 

<?php 
 
	footer();
?>