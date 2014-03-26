<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_ADM]);
  top();
  
?>

	<legend>Lista de Tipos de Produto/Chamada</legend>
<div align="right">
<a href="produtotipo.php?action=<?php echo(ACAO_INCLUIR);?>" class="btn"><i class="icon-plus"></i> adicionar novo</a>
</div>
        
	<table class="table table-striped table-bordered">
		<thead>
			<tr>
				<th class="span1">#</th>
				<th>Nome</th>                
                <th>Associado à funcionalidade Mutirão?</th>                
			</tr>
		</thead>
		<tbody>
				<?php
					
					$sql = "SELECT prodt_id, prodt_nome, prodt_mutirao ";
					$sql.= "FROM produtotipos ";
					$sql.= "ORDER BY prodt_nome ";
								
					$res = executa_sql($sql);

					$contador = 0;
				    if($res)
					{
					 while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
				     {
				?>				 
				  <tr>
                  	 <td><?php echo(++$contador);?></td>               
					 <td><a href="produtotipo.php?action=0&amp;prodt_id=<?php echo($row['prodt_id']);?>"><?php echo($row['prodt_nome']);?></a></td>
					 <td><?php echo($row['prodt_mutirao'] == 1 ? "Sim": "Não" );?></td>                   

				  </tr>
				<?php 
				     }
				   }
				?>
		</tbody>
	</table>

<div align="right">
<a href="produtotipo.php?action=<?php echo(ACAO_INCLUIR);?>" class="btn"><i class="icon-plus"></i> adicionar novo</a>
</div>


<?php 
 
	footer();
?>