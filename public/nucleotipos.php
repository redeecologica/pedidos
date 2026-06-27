<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_ADM]);
  top();
  
?>

<div class="panel panel-default">
  <div class="panel-heading">
     <strong>Lista de Tipos de Núcleo</strong>
       <span class="pull-right">
		<a href="nucleotipo.php?action=<?php echo(ACAO_INCLUIR);?>" class="btn btn-default btn-xs"><i class="glyphicon glyphicon-plus"></i> adicionar novo</a>
	</span>
  </div>

	<table class="table table-striped table-bordered">
		<thead>
			<tr>
				<th>#</th>
				<th>Nome</th>                              
			</tr>
		</thead>
		<tbody>
				<?php
					
					$sql = "SELECT nuct_id, nuct_nome ";
					$sql.= "FROM nucleotipos ";
					$sql.= "ORDER BY nuct_nome ";
								
					$res = executa_sql($sql);

					$contador = 0;
				    if($res)
					{
					 while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
				     {
				?>				 
				  <tr>
                  	 <td><?php echo(++$contador);?></td>               
					 <td><a href="nucleotipo.php?action=0&amp;nuct_id=<?php echo($row['nuct_id']);?>"><?php echo($row['nuct_nome']);?></a></td>
				
				  </tr>
				<?php 
				     }
				   }
				?>
		</tbody>
	</table>

</div>

       <span class="pull-right">
		<a href="nucleotipo.php?action=<?php echo(ACAO_INCLUIR);?>" class="btn btn-default"><i class="glyphicon glyphicon-plus"></i> adicionar novo</a>
	</span>
    
    
<?php 
 
	footer();
?>