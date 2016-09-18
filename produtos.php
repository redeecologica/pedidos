<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_ACOMPANHA_PRODUTOR]);
  top();
?>

<div class="panel panel-default">
  <div class="panel-heading">
     <strong>Lista de Produtos</strong>
       <span class="pull-right">
		<a href="produto.php?action=<?php echo(ACAO_INCLUIR);?>" class="btn btn-default btn-xs"><i class="glyphicon glyphicon-plus"></i> adicionar novo</a>
	</span>
  </div>
 <div class="panel-body">
 

<form class="form-inline" action="produtos.php" method="post" name="frm_filtro" id="frm_filtro">

	<?php  
  		$forn_prodt = request_get("forn_prodt",-1);
		$prod_forn = request_get("prod_forn",-1);
  		$forn_archive = request_get("forn_archive",0); 
	?>
     <fieldset>     
     
          	<div class="form-group">
  				<label for="forn_prodt">Tipo Produtor: </label>            
                 <select name="forn_prodt" id="forn_prodt" onchange="javascript:frm_filtro.submit();" class="form-control">
                        <option value="-1" <?php echo(($forn_prodt==-1)?" selected" : ""); ?> >TODOS</option>
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
                                 if($row['prodt_id']==$forn_prodt) echo(" selected");
                                 echo (">" . $row['prodt_nome'] . "</option>");
                              }
                            }
                        ?>  
                 </select>    
         </div>        
                 &nbsp;
                 
     
          	  <div class="form-group">
  		<label for="forn_archive">Situação Produtor: </label>&nbsp;
            
                    <select name="forn_archive" id="forn_archive" onchange="javascript:frm_filtro.submit();" class="form-control">
                        <option value="-1" <?php echo( ($forn_archive)==-1?" selected" : ""); ?> >TODOS</option>
                        <option value="0"  <?php echo( ($forn_archive)==0?" selected" : ""); ?> >Ativos</option>
                        <option value="1"  <?php echo( ($forn_archive)==1?" selected" : ""); ?> >Inativos</option>            
                    </select>     
                    
            </div>  
            
            &nbsp;

        <div class="form-group">            
  				<label for="prod_forn">Produtor: </label>            
                <select name="prod_forn" id="prod_forn" onchange="javascript:frm_filtro.submit();" class="form-control">
                    <option value="-1" <?php echo( ($prod_forn==-1)?" selected" : ""); ?> >TODOS</option>
                    <option value="-1">-------------</option>                    
                    <?php
               
                        $sql = "SELECT forn_id, forn_archive, forn_nome_curto ";
                        $sql.= "FROM fornecedores WHERE 1 ";
						if($forn_prodt!=-1) $sql.= " AND forn_prodt = " . prep_para_bd($forn_prodt) .  " ";
						if($forn_archive!=-1) $sql.= " AND forn_archive = " . prep_para_bd($forn_archive) .  " ";
                        $sql.= "ORDER BY forn_archive, forn_nome_curto ";
                        $res = executa_sql($sql);

						$achou=false;
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
                             if($row['forn_id']==$prod_forn)
							 {
								  echo(" selected");
								  $achou=true;
							 }
                             echo (">" . $row['forn_nome_curto'] . "</option>");
                          }
						  if(!$achou) $prod_forn = -1;
                        }
						
                    ?>                        
                </select>                           
                    
         </div>                                  
                    
      </fieldset>
  </form>
 </div>
        
	<table class="table table-striped table-bordered">
		<thead>
			<tr>
				<th>#</th>
				<th>Tipo</th>                
                <th>Produtor</th>                
        		<th>Nome</th>
				<th>Unidade</th>
				<th>Compra (R$)</th>
				<th>Venda (R$)</th>
				<th>Venda Não Assoc. (R$)</th>
				<th>Peso Bruto(g)</th>
			</tr>
		</thead>
		<tbody>
				<?php
					
					$sql = "SELECT prod_id, prod_nome, prod_unidade,FORMAT(prod_valor_venda,2) prod_valor_venda, ";
					$sql.= "FORMAT(prod_valor_compra,2) prod_valor_compra, FORMAT(prod_valor_venda_margem,2) prod_valor_venda_margem, ";
					$sql.= "prod_forn, forn_prodt, prodt_nome, forn_nome_curto, prod_descricao, prod_peso_bruto, prod_retornavel  ";
					$sql.= "FROM produtos LEFT JOIN fornecedores ON prod_forn = forn_id ";
					$sql.= "LEFT JOIN produtotipos ON forn_prodt = prodt_id ";						
					$sql.= "WHERE prod_ini_validade <= NOW() AND prod_fim_validade >= NOW() ";
					if($forn_prodt!=-1) $sql.= "  AND forn_prodt = " . prep_para_bd($forn_prodt) .  " ";
					if($forn_archive!=-1) $sql.= " AND forn_archive = " . prep_para_bd($forn_archive) .  " ";
					if($prod_forn!=-1) 	 $sql.= " AND forn_id = " . prep_para_bd($prod_forn) .  " ";						
					$sql.= "ORDER BY prodt_nome, forn_nome_curto, prod_nome, prod_unidade ";
								
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
					 <td>
                     	<a href="produto.php?action=0&amp;prod_id=<?php echo($row['prod_id']);?>"><?php echo($row['prod_nome']);?></a> 
					 	<?php adiciona_popover_descricao("Descrição", $row["prod_descricao"]);?>
                        <?php if($row["prod_retornavel"]!=0) echo("&nbsp;<i class='glyphicon glyphicon-retweet' title='Produto com embalagem retornável'></i>");?>
                     </td>
                     <td><?php echo($row['prod_unidade']);?></td> 
                     <td><?php echo(formata_moeda($row['prod_valor_compra']));?></td>
                     <td><?php echo(formata_moeda($row['prod_valor_venda']));?></td> 
					 <td><?php echo(formata_moeda($row['prod_valor_venda_margem']));?> </td>                     
                     <td><?php echo($row['prod_peso_bruto']);?></td> 
				  </tr>
				<?php 
				     }
				   }
				?>
		</tbody>
	</table>

</div>

       <span class="pull-right">
		<a href="produto.php?action=<?php echo(ACAO_INCLUIR);?>" class="btn btn-default"><i class="glyphicon glyphicon-plus"></i> adicionar novo</a>
	</span>

<?php 
 
	footer();
?>