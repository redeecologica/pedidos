<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_MUTIRAO]);
  top();
?>

<?php

		$action = request_get("action",-1);
		if($action==-1) redireciona(PAGINAPRINCIPAL);

		$est_cha =  request_get("est_cha","");
		if($est_cha=="") redireciona(PAGINAPRINCIPAL);		
		
		$importar = request_get("importar","");
		if($importar<>"") importar_estoque_anterior($est_cha);


		if ($action<>-1) // por enquanto, vai precisar para todos os casos
		{
		  $sql = "SELECT DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, cha_prodt, prodt_nome FROM chamadas ";
		  $sql.= "LEFT JOIN produtotipos ON cha_prodt = prodt_id ";
		  $sql.= "WHERE cha_id=". prep_para_bd($est_cha) . " ";
		  
 		  $res = executa_sql($sql);
  	      if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		  {				  
			$cha_dt_entrega = $row["cha_dt_entrega"];
			$cha_prodt = $row["cha_prodt"];
			$prodt_nome = $row["prodt_nome"];
			 
		   }
		}	
		
				
		if ($action == ACAO_SALVAR) // salvar formulário preenchido
		{
			
			// salva disponibilidade de produtos
			$n = isset($_REQUEST['est_prod']) ? sizeof($_REQUEST['est_prod']) : 0;
			
			for($i=0;$i<$n;$i++)
			{
				$est_cha_bd = prep_para_bd($est_cha);
				$qtde_antes_bd = $_REQUEST['est_prod_qtde_antes'][$i] <>"" ? prep_para_bd(formata_numero_para_mysql($_REQUEST['est_prod_qtde_antes'][$i])) : 'NULL';
				$qtde_depois_bd = $_REQUEST['est_prod_qtde_depois'][$i] <> "" ? prep_para_bd(formata_numero_para_mysql($_REQUEST['est_prod_qtde_depois'][$i])) : 'NULL';
				
				$sql = "INSERT INTO estoque (est_cha, est_prod, est_prod_qtde_antes, est_prod_qtde_depois ) ";
				$sql.= "VALUES ( " . $est_cha_bd . " ," . prep_para_bd($_REQUEST['est_prod'][$i]) . ", ";
				$sql.= $qtde_antes_bd . ", " . $qtde_depois_bd . ") ";
				$sql.= "ON DUPLICATE KEY UPDATE ";
				$sql.= " est_prod_qtde_antes = " . $qtde_antes_bd . ", ";
				$sql.= " est_prod_qtde_depois = " . $qtde_depois_bd ;
				$res = executa_sql($sql);
				
				
			}

			

			if($res)
			{
				$action=ACAO_EXIBIR_LEITURA; //volta para modo visualização somente leitura
				adiciona_mensagem_status(MSG_TIPO_SUCESSO,"As informações de estoque relacionado à chamada " . $cha_dt_entrega . " foram salvas com sucesso.");								
			}
			else
			{
				adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar salvar informações de estoque da chamada para o dia " . $cha_dt_entrega . ".");								
			}
			escreve_mensagem_status();
		
		}
		
		if ($action == ACAO_EXIBIR_LEITURA || $action == ACAO_EXIBIR_EDICAO )  // exibir para visualização, ou exibir para edição
		{
			// capturar informação de estoque
		}	


	
		
?>

<ol class="breadcrumb">
  <li><a href="mutirao.php">Mutirão</a></li>
  <li class="active">Estoque</li>
</ol>


<?php 
 if($action==ACAO_EXIBIR_LEITURA)  //visualização somente leitura
 {
 ?>	  
 
<div class="panel panel-default">
  <div class="panel-heading">
     <strong>Informações de Estoque relacionado à chamada de <?php echo($prodt_nome . " - " . $cha_dt_entrega); ?></strong>

  </div>
   
				<?php
                    $sql = "SELECT prod_id, prod_nome, FORMAT(est_prod_qtde_antes,1) est_prod_qtde_antes, ";
					$sql.= "FORMAT(est_prod_qtde_depois,1) est_prod_qtde_depois, prod_unidade, ";
					$sql.= "est_prod, forn_nome_curto, forn_nome_completo, forn_id FROM estoque ";
                    $sql.= "LEFT JOIN produtos on est_prod = prod_id ";
                    $sql.= "LEFT JOIN chamadas on est_cha = cha_id "; 
                    $sql.= "LEFT JOIN fornecedores on prod_forn = forn_id ";
                    $sql.= "WHERE prod_ini_validade<=NOW() AND prod_fim_validade>=NOW() ";
					$sql.= "AND est_cha = " . prep_para_bd($est_cha) . " AND (est_prod_qtde_antes>0 OR est_prod_qtde_depois > 0)  ";
                    $sql.= "ORDER BY forn_nome_curto, prod_nome, prod_unidade ";
                    $res = executa_sql($sql);	
						
					  
                    if($res && mysqli_num_rows($res)==0)
					{
					?>	

                    <div class="panel-body">
                    <!--
					<button type="button" class="btn btn-default btn-enviando" data-loading-text="importando..." onclick="javascript:location.href='estoque.php?action=<?php echo(ACAO_EXIBIR_LEITURA);?>&est_cha=<?php echo($est_cha); ?>&importar=sim'">
            <i class="icon glyphicon glyphicon-resize-small"></i> importar estoque do último mutirão
            </button>
            -->
            <br /><br /><br /><div class='well'> Sem produtos em estoque. Se de fato houver, clique em editar para registrar. </div><br />
            </div>
					
					<?php
					}
					else if($res)
                    {
					   ?>

                  <div align="right">
         	 	<a class="btn btn-primary" href="estoque.php?action=<?php echo(ACAO_EXIBIR_EDICAO); ?>&est_cha=<?php echo($est_cha); ?>"><i class="glyphicon glyphicon-edit glyphicon-white"></i> editar</a>
         	</div><br>
            
            
                       	<table class="table table-striped table-bordered table-condensed table-hover">
                  	   <?php

					   $ultimo_forn = "";
                       while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
                       {
							if($row["forn_nome_curto"]!=$ultimo_forn)
							{
								
								$ultimo_forn = $row["forn_nome_curto"];
								
								?>
										<tr>
											<th>
											  <?php echo($row["forn_nome_curto"]);
											  adiciona_popover_descricao("",$row["forn_nome_completo"]);
											  ?>
                                            </th>
											<th>Unidade</th>
											<th>Estoque Pré-Mutirão</th>
											<th>Estoque Pós-Mutirão</th>
										</tr>
								<?php
								
							}   
							
							?>
							<tr>                              
                            <td><?php echo($row["prod_nome"]);?></td>
                            <td><?php echo($row["prod_unidade"]); ?></td>
                            <td>
                           <?php echo($row["est_prod_qtde_antes"]?get_hifen_se_zero(formata_numero_de_mysql($row["est_prod_qtde_antes"])):"&nbsp;"); ?>                            </td>                            							
							<td>                            
                           <?php echo($row["est_prod_qtde_depois"]?get_hifen_se_zero(formata_numero_de_mysql($row["est_prod_qtde_depois"])):"&nbsp;"); ?>
                           </td> 
                            </tr>
                             
							<?php

                       }
					   
					   echo("</table>");
                    } 
               
			      ?>   
                  
                  </div>          
				<div align="right">
         	 	<a class="btn btn-primary" href="estoque.php?action=<?php echo(ACAO_EXIBIR_EDICAO); ?>&est_cha=<?php echo($est_cha); ?>"><i class="glyphicon glyphicon-edit glyphicon-white"></i> editar</a>
         	</div>
      
  
   
	
<?php 

	
 }
 else  //visualização para edição
 {

?>
    <form class="form-horizontal" action="estoque.php" method="post">
     <legend>Informações de Estoque relacionado à chamada de <?php echo($prodt_nome . " - " . $cha_dt_entrega); ?></legend>    
        <fieldset>
        
          <input type="hidden" name="est_cha" value="<?php echo($est_cha); ?>" />
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />  
            
            <div class="form-group">
                 <div class="container">

				<?php
                    $sql = "SELECT prod_id, prod_nome, FORMAT(est_prod_qtde_antes,1) est_prod_qtde_antes, ";
					$sql.= "FORMAT(est_prod_qtde_depois,1) est_prod_qtde_depois, prod_unidade, ";
					$sql.= "chaprod_prod, forn_nome_curto, forn_nome_completo, forn_id FROM chamadaprodutos ";
                    $sql.= "LEFT JOIN produtos on chaprod_prod = prod_id ";
                    $sql.= "LEFT JOIN chamadas on chaprod_cha = cha_id ";  
                    $sql.= "LEFT JOIN fornecedores on prod_forn = forn_id ";
					$sql.= "LEFT JOIN estoque on est_cha = cha_id AND est_prod = chaprod_prod ";
                    $sql.= "WHERE prod_ini_validade<=NOW() AND prod_fim_validade>=NOW() ";
					$sql.= "AND chaprod_cha = " . prep_para_bd($est_cha) . " AND chaprod_disponibilidade > 0 ";
                    $sql.= "ORDER BY forn_nome_curto, prod_nome, prod_unidade ";
                    $res = executa_sql($sql);	
					
				
					  
                    if($res)
                    {
						echo("<table class='table table-striped table-bordered table-condensed table-hover'>");

					   $ultimo_forn = "";
                       while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
                       {
							if($row["forn_nome_curto"]!=$ultimo_forn)
							{
								
								$ultimo_forn = $row["forn_nome_curto"];
								
								?>
										<tr>
											<th>
											  <?php echo($row["forn_nome_curto"]);
											  adiciona_popover_descricao("",$row["forn_nome_completo"]);
											  ?>
                                            </th>
											<th>Unidade</th>
											<th>Estoque Pré-Mutirão</th>
											<th>Estoque Pós-Mutirão</th>
										</tr>
								<?php
								
							}   
							
							?>
							<tr> 
                            <input type="hidden" name="est_prod[]" value="<?php echo($row["prod_id"]); ?>"/>
                             
                            <td><?php echo($row["prod_nome"]);?></td>
                            <td><?php echo($row["prod_unidade"]); ?></td>
                            <td>
                            <input type="text" class="input-mini propaga-colar" style="font-size:18px; text-align:center;" value="<?php echo($row["est_prod_qtde_antes"]?formata_numero_de_mysql($row["est_prod_qtde_antes"]):""); ?>" name="est_prod_qtde_antes[]" id="est_prod_qtde_antes_<?php echo($row["prod_id"]); ?>"/>
                            </td>                            							
							<td>                            
                            <input type="text" class="input-mini propaga-colar-2" style="font-size:18px; text-align:center;" value="<?php echo($row["est_prod_qtde_depois"]?formata_numero_de_mysql($row["est_prod_qtde_depois"]):""); ?>" name="est_prod_qtde_depois[]" id="est_prod_qtde_depois_<?php echo($row["prod_id"]); ?>"/>
                                                        
                            </td> 
                            </tr>
                             
							<?php

                       }
					   
					   echo("</table>");
                    } 
               
			      ?>             
                       
                 </div>  
            </div>
            
            
		  <div class="form-group" align="right">
            <div class="controls">                 
                   <button type="submit"  class="btn btn-primary btn-enviando" data-loading-text="salvando...">
            <i class="glyphicon glyphicon-ok glyphicon-white"></i> salvar alterações</button>
                   
                   &nbsp;&nbsp;
                   
                   
                   
                   <button class="btn btn-default" type="button" onclick="javascript:location.href='mutirao.php'"><i class="glyphicon glyphicon-off"></i> descartar alterações</button>
                                 
            </div>
          </div>
      </fieldset> 
    </form>
 
<!--
<script type="text/javascript">
	$(function() {
		$(".est_prod_qtde_antes").bind('keydown', keyCheck);
		$(".est_prod_qtde_depois").bind('keydown', keyCheck);
	}); 
</script>  
-->
    
    <?php   
	
   }

   footer();
?>
