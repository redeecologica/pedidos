<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO]);
  top();
?>

<form class="form-inline" action="produtos.php" method="post" name="frm_filtro" id="frm_filtro">
	<legend>Lista de Produtos</legend>
<div align="right">
<a href="produto.php?action=<?php echo(ACAO_INCLUIR);?>" class="btn"><i class="icon-plus"></i> adicionar novo</a>
</div>


	<?php  
  		$prod_prodt = request_get("prod_prodt",-1) ;
		$prod_forn = request_get("prod_forn",-1) ;
	?>
     <fieldset>
     
     
  				<label for="prod_prodt">Tipo: </label>            
                 <select name="prod_prodt" id="prod_prodt" onchange="javascript:frm_filtro.submit();" class="input-medium">
                        <option value="-1" <?php echo(($prod_prodt==-1)?" selected" : ""); ?> >TODOS</option>
						<?php
                            
                            $sql = "SELECT prodt_id, prodt_nome ";
                            $sql.= "FROM produtotipos ";
                            $sql.= "ORDER BY prodt_nome ";
                            $res = executa_sql($sql);
                            if($res)
                            {
                              while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
                              {
                                 echo("<option value='" . $row['prodt_id'] . "'");
                                 if($row['prodt_id']==$prod_prodt) echo(" selected");
                                 echo (">" . $row['prodt_nome'] . "</option>");
                              }
                            }
                        ?>  
                 </select>    
                 
                 &nbsp;
                    
  				<label for="prod_forn">Produtor: </label>            
                <select name="prod_forn" id="prod_forn" onchange="javascript:frm_filtro.submit();" class="input-medium">
                    <option value="-1" <?php echo( ($prod_forn==-1)?" selected" : ""); ?> >TODOS</option>
                    <option value="-1">-------------</option>                    
                    <?php
               
                        $sql = "SELECT forn_id, forn_archive, forn_nome_curto ";
                        $sql.= "FROM fornecedores ";
                        $sql.= "ORDER BY forn_archive, forn_nome_curto ";
                        $res = executa_sql($sql);
                        if($res)
                        {
						  $arquivados=0;
                          while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
                          {
							 if(!$arquivados)
							 {
								 if($row["forn_archive"]==1) 
								 {
									 echo("<option value='-1'>-------------</option>");									 
									 $arquivados=1;
								 }
							 }
                             echo("<option value='" . $row['forn_id'] . "'");
                             if($row['forn_id']==$prod_forn) echo(" selected");
                             echo (">" . $row['forn_nome_curto'] . "</option>");
                          }
                        }
                    ?>                        
                </select>                           
                    
                                           
                    
     </fieldset>
</form>
        
	<table class="table table-striped table-bordered">
		<thead>
			<tr>
				<th class="span1">#</th>
				<th>Tipo</th>                
                <th>Produtor</th>                
        		<th>Nome</th>
				<th>Unidade</th>
				<th>Valor (R$)</th>
				<th>Valor com Margem (R$)</th>
			</tr>
		</thead>
		<tbody>
				<?php
					
					$sql = "SELECT prod_id, prod_nome, prod_unidade,FORMAT(prod_valor_venda,2) prod_valor_venda, FORMAT(prod_valor_venda_margem,2) prod_valor_venda_margem, prod_forn, prod_prodt, prodt_nome, forn_nome_curto  ";
					$sql.= "FROM produtos LEFT JOIN fornecedores ON prod_forn = forn_id ";
					$sql.= "LEFT JOIN produtotipos ON prod_prodt = prodt_id ";						
					$sql.= "WHERE prod_ini_validade <= NOW() AND prod_fim_validade >= NOW() ";
					if($prod_prodt!=-1) $sql.= "  AND  prodt_id = " . prep_para_bd($prod_prodt) .  " ";
					if($prod_forn!=-1) 	 $sql.= " AND forn_id = " . prep_para_bd($prod_forn) .  " ";						
					$sql.= "ORDER BY prodt_nome, forn_nome_curto, prod_nome ";
								
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
					 <td><?php echo($row['forn_nome_curto']);?></td>                                    
					 <td><a href="produto.php?action=0&amp;prod_id=<?php echo($row['prod_id']);?>"><?php echo($row['prod_nome']);?></a></td>
                     <td><?php echo($row['prod_unidade']);?></td> 
                     <td><?php echo(formata_moeda($row['prod_valor_venda']));?></td> 
					 <td><?php echo(formata_moeda($row['prod_valor_venda_margem']));?> </td>                     

				  </tr>
				<?php 
				     }
				   }
				?>
		</tbody>
	</table>

<div align="right">
<a href="produto.php?action=<?php echo(ACAO_INCLUIR);?>" class="btn"><i class="icon-plus"></i> adicionar novo</a>
</div>


<?php 
 
	footer();
?>