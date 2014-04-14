<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_MUTIRAO]);
  top();

?>

<div class="panel panel-default">
  <div class="panel-heading">
     <strong>Lista de Chamadas para o Mutirão</strong>
  </div>
 <div class="panel-body">   
     <div class="well">Atenção: módulo em desenvolvimento</div>
 </div>   
 
	<table class="table table-striped table-bordered">
		<thead>
			<tr>            
				<th>#</th>
				<th>Tipo</th>
        		<th>Data de Entrega</th>
				<th>1) Estoque</th>
				<th>2) Recebimento</th>                
				<th>3) Distribuição</th>  
				<th>Relatório Consolidado</th>                  
                              
			</tr>
		</thead>
		<tbody>
				<?php
					$sql = "SELECT cha_id, cha_prodt, cha_dt_entrega cha_dt_entrega_original, date_format(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, prodt_nome ";
					$sql.= "FROM chamadas ";
					$sql.= "LEFT JOIN produtotipos ON cha_prodt = prodt_id ";	
					$sql.= "WHERE prodt_mutirao = '1' ";
					$sql.= "ORDER BY cha_dt_entrega_original DESC ";
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
                     <td><a href="estoque.php?action=<?php echo(ACAO_EXIBIR_LEITURA);?>&est_cha=<?php echo($row['cha_id']);?>">estoque</a></td> 
                     <td><a href="recebimento.php?action=<?php echo(ACAO_EXIBIR_LEITURA);?>&cha_id=<?php echo($row['cha_id']);?>">recebimento</a></td>  
                     <td><a href="distribuicao.php?action=<?php echo(ACAO_EXIBIR_LEITURA);?>&cha_id=<?php echo($row['cha_id']);?>">distribuicao</a></td>                      
                     <td><a href="rel_pedido_pre_mutirao.php?cha_id=<?php echo($row['cha_id']);?>">relatório</a></td>                      
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