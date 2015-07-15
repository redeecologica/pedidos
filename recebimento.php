<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO]  || $_SESSION[PAP_RESP_MUTIRAO]);
  top();
?>

<?php

		$action = request_get("action",-1);
		if($action==-1) redireciona(PAGINAPRINCIPAL);

		$cha_id =  request_get("cha_id","");
		if($cha_id=="") redireciona(PAGINAPRINCIPAL);		
		

		if ($action<>-1) // por enquanto, vai precisar para todos os casos
		{
		  $sql = "SELECT DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, cha_prodt, prodt_nome FROM chamadas ";
		  $sql.= "LEFT JOIN produtotipos ON cha_prodt = prodt_id ";
		  $sql.= "WHERE cha_id=". prep_para_bd($cha_id) . " ";
		  
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
			$n = isset($_REQUEST['chaprod_recebido']) ? sizeof($_REQUEST['chaprod_recebido']) : 0;
			$cha_id_bd = prep_para_bd($cha_id);
										
			for($i=0;$i<$n;$i++)
			{
				$qtde_salvar = $_REQUEST['chaprod_recebido'][$i]=="" ? 'NULL' : prep_para_bd(formata_numero_para_mysql($_REQUEST['chaprod_recebido'][$i]));
				$sql = "UPDATE chamadaprodutos SET  ";
				$sql.= " chaprod_recebido = " . $qtde_salvar ;
				$sql.= " WHERE chaprod_cha = " . $cha_id_bd;
				$sql.= " AND chaprod_prod = " . prep_para_bd($_REQUEST['chaprod_prod'][$i]) ;
				$res = executa_sql($sql);
				
			}

			

			if($res)
			{
				$action=ACAO_EXIBIR_LEITURA; //volta para modo visualização somente leitura
				adiciona_mensagem_status(MSG_TIPO_SUCESSO,"As informações de recebimento relacionado à chamada " . $cha_dt_entrega . " foram salvas com sucesso.");								
			}
			else
			{
				adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar salvar informações de recebimento da chamada para o dia " . $cha_dt_entrega . ".");								
			}
			escreve_mensagem_status();
		
		}
		
		if ($action == ACAO_EXIBIR_LEITURA || $action == ACAO_EXIBIR_EDICAO )  // exibir para visualização, ou exibir para edição
		{
			// capturar informação de estoque
		}	


	
		
?>

<?php 

	$sql = "SELECT prod_id, prod_nome, FORMAT(chaprod_recebido,1) chaprod_recebido, SUM(pedprod_quantidade) total_demanda, ";
	$sql.= " prod_unidade, forn_nome_curto, forn_nome_completo, forn_id, ";
	$sql.= " FORMAT( GREATEST(0,(SUM(pedprod_quantidade) - IF(est_prod_qtde_depois IS NULL, 0, est_prod_qtde_depois))), 1) total_pedido ";
	$sql.= " FROM chamadaprodutos ";
	$sql.= "LEFT JOIN produtos on chaprod_prod = prod_id ";
	$sql.= "LEFT JOIN chamadas on chaprod_cha = cha_id "; 
	$sql.= "LEFT JOIN fornecedores on prod_forn  = forn_id ";
	$sql.= "LEFT JOIN pedidos ON ped_cha = cha_id "; 
	$sql.= "LEFT JOIN pedidoprodutos ON pedprod_ped = ped_id AND pedprod_prod=chaprod_prod ";					
	$sql.= "LEFT JOIN estoque ON est_prod = chaprod_prod AND est_cha = " . prep_para_bd(get_chamada_anterior($cha_id)) . " ";	
	$sql.= "WHERE prod_ini_validade<=NOW() AND prod_fim_validade>=NOW() AND ped_fechado = '1' ";
	$sql.= "AND chaprod_cha = " . prep_para_bd($cha_id) . " AND chaprod_disponibilidade > 0  ";
	$sql.= "GROUP BY forn_id, prod_id ";
	$sql.= "ORDER BY forn_nome_curto, prod_nome, prod_unidade ";
	$res = executa_sql($sql);	
	

?>

 <?php 
		 if($action == ACAO_EXIBIR_LEITURA)
		 {
		   ?>	 

		    <input class="btn btn-success" type="button" value="selecionar tabela para copiar" 
	    		  onclick="selectElementContents( document.getElementById('selectable') );">
		       <br><br> 
		 <?php
		 }
	  ?>  
      

                
      

  
  <div class="panel panel-default">
  <div class="panel-heading">
     <strong>Informações de Recebimento relacionado à chamada de <?php echo($prodt_nome . " - " . $cha_dt_entrega); ?></strong>
     <?php 
		 if($action == ACAO_EXIBIR_LEITURA)
		 {
		   ?>	 
		 <span class="pull-right"><a class="btn btn-xs btn-primary" href="recebimento.php?action=<?php echo(ACAO_EXIBIR_EDICAO); ?>&cha_id=<?php echo($cha_id); ?>"><i class="glyphicon glyphicon-edit"></i> editar</a></span>
		 <?php
		 }
	  ?>     
      
  </div>

<?php
 if($action==ACAO_EXIBIR_LEITURA)  //visualização somente leitura
 {
 ?>	  
				<?php

                    if($res && mysqli_num_rows($res)==0)
					{
					?>	
						Ainda não disponível.
					<?php
					}
					else if($res)
                    {
						?>
                            
                        <div id="selectable">
                        <table class='table table-striped table-bordered table-condensed table-hover'>
                        
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
											<th>Pedido</th>
											<th>Recebido</th>
										</tr>
								<?php
								
							}   
							
							?>
							<tr>                              
                            <td><?php echo($row["prod_nome"]);?></td>
                            <td><?php echo($row["prod_unidade"]); ?></td>                          							
							<td>                            
                          		<?php echo(get_hifen_se_zero(formata_numero_de_mysql($row["total_pedido"]))); ?> 
                             </td>               
                             
                             
							<td>                            
                          		<?php 
									if($row["chaprod_recebido"]) 
									{
										echo(get_hifen_se_zero(formata_numero_de_mysql($row["chaprod_recebido"]))); 
									}
									else
									{
										echo("&nbsp;");
									}
								 
								?> 
                             </td>               
                                 
                            </tr>
                             
							<?php

                       }
					   
					   echo("</div></table>");
                    } 
               
			      ?>       
                  </div>      

    <span class="pull-right"><a class="btn btn-primary" href="recebimento.php?action=<?php echo(ACAO_EXIBIR_EDICAO); ?>&cha_id=<?php echo($cha_id); ?>"><i class="glyphicon glyphicon-edit"></i> editar</a>    
    </span>
    <!--
         	&nbsp;&nbsp;
         	<a class="btn btn-default" href="javascript:window.history.go(-1);"><i class="glyphicon glyphicon-arrow-left"></i> voltar</a>        -->
  
   
	
<?php 

	
 }
 else  //visualização para edição
 {

?>
    <form class="form-horizontal" action="recebimento.php" method="post">
        <fieldset>
        
          <input type="hidden" name="cha_id" value="<?php echo($cha_id); ?>" />
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />  
            

				<?php
 										  
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
											<th>Pedido</th>
											<th class="coluna-quantidade">Recebido</th>
										</tr>
								<?php
								
							}   
							
							?>
							<tr> 
                            <input type="hidden" name="chaprod_prod[]" value="<?php echo($row["prod_id"]); ?>"/>
                             
                            <td><?php echo($row["prod_nome"]);?></td>
                            <td><?php echo($row["prod_unidade"]); ?></td>
                            <td><?php echo(formata_numero_de_mysql($row["total_pedido"]));?></td>
                            <td>
                            <input type="text" class="form-control propaga-colar" style="font-size:18px; text-align:center;" value="<?php echo($row["chaprod_recebido"]?formata_numero_de_mysql($row["chaprod_recebido"]):""); ?>" name="chaprod_recebido[]"/>
                            </td>
                                                     
                            </tr>
                             
							<?php

                       }
					   
					   echo("</table>");
                    } 
               
			      ?>             
                       
               </div>
            
                <span class="pull-right">
                	  <button type="submit"  class="btn btn-primary btn-enviando" data-loading-text="salvando...">
            <i class="glyphicon glyphicon-ok"></i> salvar alterações</button>
                &nbsp;&nbsp;
                   
                   <button class="btn btn-default" type="button" onclick="javascript:window.history.go(-1);"><i class="glyphicon glyphicon-off"></i> descartar alterações</button>
                   
                </span>
                
	             
                   
                   
                                 
      </fieldset> 
    </form>
 

    
    <?php   
	
   }

   footer();
?>
