<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO]);
  top();
?>

<div class="panel panel-default">
  <div class="panel-heading">
     <strong>Lista de Produtores</strong>
       <span class="pull-right">
		<a href="produtor.php?action=<?php echo(ACAO_INCLUIR);?>" class="btn btn-default btn-xs"><i class="glyphicon glyphicon-plus"></i> adicionar novo</a>
	</span>
  </div>
  <div class="panel-body">


<form class="form-inline" action="produtores.php" method="post" name="frm_filtro" id="frm_filtro">
    
	<?php  
  		$forn_archive = isset($_REQUEST['forn_archive']) ? mysqli_real_escape_string($conn_link,$_REQUEST['forn_archive']) : 0 ;
	?>
     <fieldset>
     	  <div class="form-group">
  		<label for="forn_archive">Situação: </label>&nbsp;
            
                    <select name="forn_archive" id="forn_archive" onchange="javascript:frm_filtro.submit();" class="form-control">
                        <option value="-1" <?php echo( ($forn_archive)==-1?" selected" : ""); ?> >TODOS</option>
                        <option value="0"  <?php echo( ($forn_archive)==0?" selected" : ""); ?> >Ativos</option>
                        <option value="1"  <?php echo( ($forn_archive)==1?" selected" : ""); ?> >Inativos</option>            
                    </select>     
                    
            </div>                      
     </fieldset>
</form>
  </div>
        
	<table class="table table-striped table-bordered">
		<thead>
			<tr>
				<th>#</th>
				<th>Nome Completo</th>
				<th>Nome Curto</th>
				<th>Email</th>
				<th>Produtos</th>   
			</tr>
		</thead>
		<tbody>
				<?php
					
					$sql = "SELECT forn_id, forn_nome_curto, forn_nome_completo, forn_email, forn_archive, ";
					$sql.= "COUNT( produtos.prod_forn ) AS forn_qtde_produtos ";
					$sql.= "FROM fornecedores LEFT JOIN produtos ON forn_id = prod_forn  ";
					$sql.= "AND prod_ini_validade <= NOW() AND prod_fim_validade >= NOW() ";
					$sql.= "WHERE 1 ";
					if($forn_archive!=-1) $sql.= " AND forn_archive = " . prep_para_bd($forn_archive) .  " ";
//					$sql.= "AND prod_ini_validade <= NOW() AND prod_fim_validade >= NOW() ";
					$sql.= "GROUP BY forn_id ";
					$sql.= "ORDER BY forn_archive, forn_nome_completo ";
					$contador=0;
					$res = executa_sql($sql);
				    if($res)
					{
					 while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
				     {
						$classe_arquivado = ($row['forn_archive'] == 0) ? "": " class='warning'";
						$icone_arquivado = ($row['forn_archive'] == 0) ? "": " <i class='glyphicon glyphicon-inbox'></i> ";
				?>				 
				  <tr <?php echo($classe_arquivado); ?>>
                  	 <td><?php echo(++$contador); ?></td>
					 <td><a href="produtor.php?action=0&amp;forn_id=<?php echo($row['forn_id']);?>"><?php echo($icone_arquivado);?><?php echo($row['forn_nome_completo']);?></a></td>
                     <td><?php echo($row['forn_nome_curto']);?></td> 
					 <td><?php echo($row['forn_email']);?> </td>                     
					 <td>&nbsp;<?php echo($row['forn_qtde_produtos']);?> &nbsp; <a class="btn btn-default btn-xs" href="produtos.php?prod_forn=<?php echo($row['forn_id']);?>"><i class="glyphicon glyphicon-search"></i> consultar</a></td>                     
				  </tr>
				<?php 
				     }
				   }
				?>
		</tbody>
	</table>
    
  </div>  

       <span class="pull-right">
		<a href="produtor.php?action=<?php echo(ACAO_INCLUIR);?>" class="btn btn-default"><i class="glyphicon glyphicon-plus"></i> adicionar novo</a>
	</span>


<?php 
 
	footer();
?>