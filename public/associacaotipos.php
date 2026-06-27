<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_ADM]);
  top();
  
?>

<div class="panel panel-default">
  <div class="panel-heading">
     <strong>Lista de Tipos de Associação</strong>
       <span class="pull-right">
		<a href="associacaotipo.php?action=<?php echo(ACAO_INCLUIR);?>" class="btn btn-default btn-xs"><i class="glyphicon glyphicon-plus"></i> adicionar novo</a>
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
					
					$sql = "SELECT asso_id, asso_nome ";
					$sql.= "FROM associacaotipos ";
					$sql.= "ORDER BY asso_nome ";
								
					$res = executa_sql($sql);

					$contador = 0;
				    if($res)
					{
					 while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
				     {
				?>				 
				  <tr>
                  	 <td><?php echo(++$contador);?></td>               
					 <td><a href="associacaotipo.php?action=0&amp;asso_id=<?php echo($row['asso_id']);?>"><?php echo($row['asso_nome']);?></a></td>
				
				  </tr>
				<?php 
				     }
				   }
				?>
		</tbody>
	</table>

</div>

       <span class="pull-right">
		<a href="associacaotipo.php?action=<?php echo(ACAO_INCLUIR);?>" class="btn btn-default"><i class="glyphicon glyphicon-plus"></i> adicionar novo</a>
	</span>
    
    
<?php 
 
	footer();
?>