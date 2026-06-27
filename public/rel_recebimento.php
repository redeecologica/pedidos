<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO] || $_SESSION[PAP_ACOMPANHA_PRODUTOR] || $_SESSION[PAP_ACOMPANHA_RELATORIOS] || $_SESSION[PAP_RESP_ENTREGA]  || $_SESSION[PAP_RESP_FINANCAS]);
  top();
?>

<?php

		$cha_id =  request_get("cha_id",-1);
		if($cha_id=="") redireciona(PAGINAPRINCIPAL);	
		
				
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
		
?>

<?php 

	$sql = "SELECT prod_id, prod_nome, chaprod_recebido, ";
	$sql.= " chaprod_recebido_confirmado, SUM(pedprod_quantidade) total_demanda, ";
	$sql.= " est_prod_qtde_depois total_estoque, prod_unidade, forn_nome_curto, forn_nome_completo, forn_id, ";
	$sql.= " GREATEST(0,(SUM(pedprod_quantidade) - IF(est_prod_qtde_depois IS NULL, 0, est_prod_qtde_depois))) total_pedido ";
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
	

	$sql="SELECT prod_id, ";
	$sql.=" SUM(dist_quantidade_recebido) dist_quantidade_recebido ";	
	$sql.="FROM chamadaprodutos ";
	$sql.="LEFT JOIN chamadas on cha_id = chaprod_cha ";
	$sql.="LEFT JOIN produtos on prod_id = chaprod_prod ";
	$sql.="LEFT JOIN distribuicao ON dist_cha = chaprod_cha AND dist_prod = chaprod_prod ";	
	$sql.="WHERE chaprod_cha = " . prep_para_bd($cha_id);
	$sql.=" AND chaprod_disponibilidade <> '0' ";
	$sql.=" AND prod_ini_validade<=cha_dt_entrega AND prod_fim_validade>=cha_dt_entrega ";
	$sql.="GROUP BY prod_id ";
	$res_receb_nucleos = executa_sql($sql); 	
	$receb_nucleos = array();
	if($res_receb_nucleos)
	{
		while($row = mysqli_fetch_array($res_receb_nucleos,MYSQLI_ASSOC))
		{
			$receb_nucleos[$row["prod_id"]] = $row["dist_quantidade_recebido"];	
		}
	}	
	
	

?>
      
      

   <div class="panel panel-default">
  <div class="panel-heading">
     <strong>Relatório - Recebimento</strong>

  </div>

  <br>
  
		    <input class="btn btn-success" type="button" value="selecionar tabela para copiar" 
	    		  onclick="selectElementContents( document.getElementById('selectable') );">
                  <br><br>
                  
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
                            <thead>
						 <tr>
                            <th colspan="8">Recebimento - <?php echo($prodt_nome . " - " . $cha_dt_entrega); ?></th>
                        </tr>	
                            </thead>
                            <tbody>                        
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
											<th>Demanda</th>
											<th nowrap="nowrap">Estoque<?php adiciona_popover_descricao("Descrição", "Estoque informado pelo mutirão anterior e que deu base à encomenda"); ?></th>                                                                                        
											<th>Pedido</th>
											<th>Recebido<br/>Mutirão</th>
                                            <th>Recebido<br/>Núcleos</th>
											<th>Recebido<br/>FINAL</th>                                            
										</tr>
								<?php
								
							}   
							
							?>
							<tr>                              
                            <td><?php echo($row["prod_nome"]);?></td>
                            <td><?php echo($row["prod_unidade"]); ?></td>                          							
							<td>                            
                          		<?php echo_digitos_significativos($row["total_demanda"]); ?> 
                             </td>   
							<td>                            
                          		<?php echo_digitos_significativos($row["total_estoque"]); ?> 
                             </td>                                
							<td>                            
                          		<?php echo_digitos_significativos($row["total_pedido"]); ?> 
                             </td>              
							<td>                            
                          		<?php if($row["chaprod_recebido"]) echo_digitos_significativos($row["chaprod_recebido"]); else echo("&nbsp;") ; ?> 
                             </td>
							<td>   
                          		<?php if(isset($receb_nucleos[$row["prod_id"]])) echo_digitos_significativos($receb_nucleos[$row["prod_id"]]); else echo("&nbsp;"); ?>
                             </td>
							<td>                            
                          		<?php if($row["chaprod_recebido_confirmado"]) echo_digitos_significativos($row["chaprod_recebido_confirmado"]); else echo("&nbsp;"); ?> 
                             </td>               
                                 
                            </tr>
                             
							<?php

                       }
					   
					   echo("</tbody></table>");
                    } 
               
			      ?>       
                  </div>      
   
	<?php
    
   echo("</div>");

   footer();
?>
