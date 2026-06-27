<?php  
  require  "common.inc.php"; 
		
  $ped_id =  request_get("ped_id","");
  $ped_id_bd = prep_para_bd($ped_id);
  
  $ped_usr = get_usr_from_ped_id($ped_id);

  verifica_seguranca($_SESSION["usr.id"]== $ped_usr);
    
  
  top();
?>

<?php


			$sql = "SELECT  usr_nome_curto, ped_usr, ped_usr_associado, usr_nome_completo, usr_contatos, prodt_nome, ";
			$sql.= "nuc_nome_curto, nuc_id, ped_fechado, ped_cha, DATE_FORMAT(ped_dt_atualizacao,'%d/%m/%Y %H:%i') ped_dt_atualizacao, FORMAT(cha_taxa_percentual,2) as cha_taxa_percentual, ";
			$sql.= "DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, DATE_FORMAT(cha_dt_max,'%d/%m/%Y %H:%i') cha_dt_max  FROM pedidos ";
			$sql.= "LEFT JOIN usuarios ON ped_usr = usr_id ";	
			$sql.= "LEFT JOIN nucleos ON ped_nuc = nuc_id ";	
			$sql.= "LEFT JOIN chamadas ON ped_cha = cha_id ";				
			$sql.= "LEFT JOIN produtotipos ON cha_prodt = prodt_id ";
			$sql.= "WHERE ped_id = " . prep_para_bd($ped_id) .  "  ";
			
			
 		  $res = executa_sql($sql);
  	      if ($res && $row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		  {
			$prodt_nome = $row["prodt_nome"]; 
			$usr_nome_curto = $row["usr_nome_curto"];
			$usr_contatos = $row["usr_contatos"];
			$ped_usr_associado = $row["ped_usr_associado"]; 
			$usr_nome_completo = $row["usr_nome_completo"];
			$nuc_nome_curto = $row["nuc_nome_curto"];						
			$nuc_id = $row["nuc_id"];									
			$ped_fechado = $row["ped_fechado"];	
			$ped_dt_atualizacao = $row["ped_dt_atualizacao"];
			$cha_dt_entrega = $row["cha_dt_entrega"]; 
			$cha_dt_max = $row["cha_dt_max"];
			$cha_taxa_percentual = $row["cha_taxa_percentual"];		
			$ped_cha = $row["ped_cha"]; // serve como parametro  
			$ped_usr = $row["ped_usr"]; // serve como parametro 			
			
		   }
		   else 
		   {			   
			   adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar visualizar a entrega");
		   }

		
		
		
?>
 
	<legend>Pedido Entregue de <?php echo(($prodt_nome) . " - " . ($cha_dt_entrega)); ?></legend>
      <div class="row">
       	<div class="col-md-5"><strong>Cestante</strong>: <?php echo($usr_nome_curto);?> (<?php echo($usr_contatos ? $usr_contatos : "sem contato informado"); ?>) </div>
        <div class="col-md-4"><strong>Núcleo de Entrega: </strong>    <?php echo($nuc_nome_curto);?></div>
     	<div class="col-md-3 hidden-print"><strong>Associado:</strong> <?php echo($ped_usr_associado==1 ? "Sim" : "Não")?></div>    
             
     </div>
<hr class="hidden-print">

          
<?php

		$sql="SELECT pedprod_entregue, forn_nome_curto, forn_nome_completo, forn_link_info, usr_nome_curto, ped_usr_associado, prod_id, prod_nome, prod_valor_venda, prod_valor_venda_margem, prod_id, prod_retornavel, prod_descricao, ";
		$sql.="prod_unidade, pedprod_quantidade, (pedprod_entregue - pedprod_quantidade) AS pedprod_extra, chaprod_disponibilidade, FORMAT(cha_taxa_percentual,2) as cha_taxa_percentual ";
		$sql.="FROM chamadaprodutos ";
		$sql.="LEFT JOIN chamadas on cha_id = chaprod_cha ";
		$sql.="LEFT JOIN produtos on prod_id = chaprod_prod ";
		$sql.="LEFT JOIN pedidos ON ped_cha = cha_id ";
		$sql.="LEFT JOIN usuarios on ped_usr = usr_id ";
		$sql.="LEFT JOIN pedidoprodutos ON pedprod_ped = ped_id AND pedprod_prod=chaprod_prod ";
		$sql.="LEFT JOIN fornecedores on prod_forn = forn_id ";
		$sql.="WHERE ped_cha= " . prep_para_bd($ped_cha) . " ";
		$sql.="AND (pedprod_quantidade > '0.0' OR  pedprod_entregue >'0.0' ) ";	 
		$sql.="AND ped_fechado = '1' ";	
		$sql.="AND ped_id = " . prep_para_bd($ped_id) . " ";	
		$sql.="AND chaprod_disponibilidade <> '0' ";
		$sql.="AND prod_ini_validade<=cha_dt_entrega AND prod_fim_validade>=cha_dt_entrega  ";
		$sql.="ORDER BY forn_nome_curto, prod_nome, prod_unidade ";			
		$res = executa_sql($sql);
				
		
		
		if($res)
		{
		   $ultimo_forn = "";
		   $total_associado=0;
		   $total_nao_associado=0;		   
		   ?>		   
		   
           <table class="table table-pedido table-striped table-bordered table-condensed">
				<thead>
                	<tr>
							  <th>Produtor/Produto</th>
							  <th>Unidade</th>
							  <th>Preço para<br>Associado (R$)</th>
							  <th>Preço para<br>Não-Associado (R$)</th>
							  <th>Pedido</th>
                              <th>Entregue</th>                                                                      
							  <th>Total Entregue (R$)</th>                                                                          
					</tr>
                 </thead>           		   
                <tbody>
		   <?php
		   
		   while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		   {
				if($row["forn_nome_completo"]!=$ultimo_forn)
				{					
					$ultimo_forn = $row["forn_nome_completo"];					
					?>
					 
							<tr>
							  <th colspan="7">
							  
							  		<?php 
									echo($row["forn_nome_completo"]);
                              
                                          if(isset($row["forn_link_info"]) && $row["forn_link_info"]!="")
                                          {
                                               echo("&nbsp;<a href='" . $row["forn_link_info"] . "' target='_blank' class='hidden-print'><span class='badge'><span class='glyphicon glyphicon-search'></span></span></a>");
                                          }																												
                                   ?>
                              
                              </th>
    						</tr>
			 
					<?php					
				}  
				$total_associado+=$row["prod_valor_venda"] * $row["pedprod_entregue"] ; 
				$total_nao_associado+=$row["prod_valor_venda_margem"] * $row["pedprod_entregue"] ;		
						
				?>
				<tr> 
				<td style="text-align:left;">
					<?php echo($row["prod_nome"]); adiciona_popover_descricao("Descrição", $row["prod_descricao"]); ?> 
					<?php if($row["prod_retornavel"]!=0) echo("&nbsp;<i class='glyphicon glyphicon-retweet' title='Produto com embalagem retornável'></i>");?>
					<?php if($row["chaprod_disponibilidade"]==1) echo("&nbsp;&nbsp;<span class='label label-warning'>entrega parcial</span>");?>
                </td>
				<td><?php echo($row["prod_unidade"]);?></td>
				<td><?php echo(formata_numero_de_mysql($row["prod_valor_venda"]) ); ?></td>
				<td><?php echo(formata_numero_de_mysql($row["prod_valor_venda_margem"]) ); ?></td> 
				<td><?php echo_digitos_significativos($row["pedprod_quantidade"]); ?></td>
				<td class="<?php if($row["pedprod_quantidade"]!=$row["pedprod_entregue"]) echo("info");?>"><?php echo_digitos_significativos($row["pedprod_entregue"]); ?></td>                
                    
				<td><?php echo (formata_moeda($row["pedprod_entregue"] * ( $ped_usr_associado==1 ? $row["prod_valor_venda"] : $row["prod_valor_venda_margem"]) ) ); ?></td>    
				
				</tr>
				 
				<?php
				


		   }
		$texto_indicador_categoria_preco = "<span class='label label-info'>para o seu caso, vale este </span>&nbsp;";
				
		  ?>  
          
           <tr>
          	 <td colspan="6"><div align="right"><?php  echo ($ped_usr_associado==1 ? "" : $texto_indicador_categoria_preco); ?> TOTAL se não associado: </div></td> 
             <td><?php echo(formata_moeda($total_nao_associado));?></td>
           </tr>
           <tr>
          	 <td colspan="6"><div align="right"><?php echo($ped_usr_associado==1 ? $texto_indicador_categoria_preco : "") ; ?> TOTAL se associado: </div></td> 
             <td><?php echo(formata_moeda($total_associado));?></td>
           </tr>
           <tr>
          	 <td colspan="6"><div align="right">taxa de <?php echo($cha_taxa_percentual) * 100; ?>% para associado</div></td> 
             <td><?php echo(formata_moeda($total_associado*$cha_taxa_percentual));?></td>
           </tr>    
			<tr>
          	 <th colspan="6"><div align="right">TOTAL FINAL</div></th> 
             <th>R$ <?php echo(formata_moeda($ped_usr_associado==1 ? $total_associado*(1+$cha_taxa_percentual) : $total_nao_associado ));?></th>
           </tr>
		   

          
          </tbody>
		   </table>
		   
		   <?php
		} 
   
	


   footer();
?>
