<?php  
  require  "common.inc.php"; 
  verifica_seguranca(); 
  top();
  
?>
 
<?php

		$action = request_get("action",-1);
		if($action==-1) redireciona(PAGINAPRINCIPAL);

		$cha_id =  request_get("cha_id","");
		$bd_cha_id =  prep_para_bd($cha_id);

		$sql = "SELECT DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, DATE_FORMAT(cha_dt_min,'%d/%m/%Y') cha_dt_min, DATE_FORMAT(cha_dt_min,'%H:%i') cha_hh_min, DATE_FORMAT(cha_dt_max,'%d/%m/%Y') cha_dt_max, DATE_FORMAT(cha_dt_max,'%H:%i') cha_hh_max, cha_prodt, prodt_nome FROM chamadas ";
		$sql.= "LEFT JOIN produtotipos ON cha_prodt = prodt_id ";
		$sql.= "WHERE cha_id=". $bd_cha_id  . " ";
		
		$res = executa_sql($sql);
		if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		{				  
			$cha_dt_min = $row["cha_dt_min"];
			$cha_dt_max = $row["cha_dt_max"];
			$cha_hh_min = $row["cha_hh_min"];
			$cha_hh_max = $row["cha_hh_max"];
			$cha_dt_entrega = $row["cha_dt_entrega"];
			$cha_prodt = $row["cha_prodt"];
			$prodt_nome = $row["prodt_nome"];
		}	
		else redireciona(PAGINAPRINCIPAL);
		
		$sql =  "SELECT nuc_nome_curto FROM chamadanucleos ";
		$sql.= "LEFT JOIN nucleos on chanuc_nuc =  nuc_id ";
		$sql.= "WHERE chanuc_cha = " . $bd_cha_id . " ";
		$sql.= " ORDER BY nuc_nome_curto ";
		
		$res = executa_sql($sql);
		$cha_nucleos="nenhum";
		
		if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		{	
			$cha_nucleos=$row["nuc_nome_curto"];
			while($row = mysqli_fetch_array($res,MYSQLI_ASSOC))
			{			
				$cha_nucleos.= ", " . $row["nuc_nome_curto"];
			}			
		}
		
		
?>


<div class="panel panel-default">
  <div class="panel-heading">
     <strong>Informações da Chamada</strong>
  </div>
 <div class="panel-body">
 
 
 <table class="table-condensed table-info-cadastro">
		<tbody>
    		<tr>
				<th>Tipo:</th> <td><?php echo($prodt_nome); ?></td>
			</tr>	    

    		<tr>
				<th>Data da Entrega:</th> <td><?php echo($cha_dt_entrega); ?></td>
			</tr>	    
    		<tr>
				<th>Início Pedido:</th> <td><?php echo( ($cha_dt_min) . " " . ($cha_hh_min) ) ; ?></td>
			</tr>            
    		<tr>
				<th>Término Pedido:</th>	<td><?php echo( ($cha_dt_max)  . " " . ($cha_hh_max)); ?></td>
			</tr>
            <tr>            				 
				<th>Núcleos Atendidos:</th>	<td><?php echo( $cha_nucleos); ?></td>
            </tr>
        </tbody>    
</table>
<hr />
	<strong>Produtos que estavam disponíveis para esta chamada:</strong>
</div>


    
<?php
  
   
                        $sql = "SELECT prod_id, prod_nome, prod_descricao,FORMAT(prod_valor_venda,2) prod_valor_venda, forn_nome_curto, ";
					$sql.= "forn_nome_completo, forn_link_info, chaprod_disponibilidade, ";
					$sql.= "FORMAT (prod_valor_venda_margem,2) prod_valor_venda_margem, prod_unidade, prod_retornavel ";
					$sql.= "FROM chamadaprodutos ";
                    $sql.= "LEFT JOIN produtos ON chaprod_prod = prod_id ";
                    $sql.= "LEFT JOIN chamadas ON chaprod_cha = cha_id ";
                    $sql.= "LEFT JOIN fornecedores ON prod_forn = forn_id ";
                    $sql.= "WHERE chaprod_disponibilidade <>'0' ";
					$sql.= "AND chaprod_cha = " . $bd_cha_id . " ";
					$sql.= "AND prod_ini_validade<=cha_dt_entrega AND prod_fim_validade>=cha_dt_entrega ";
                    $sql.= "ORDER BY forn_nome_completo, prod_nome, prod_unidade ";
                    $res = executa_sql($sql);	
														
                    if($res)
                    {
					   $ultimo_forn = "";
					   
					   ?>
					   
                       <table class="table table-pedido table-striped table-bordered table-condensed table-hover">
						<thead>		 
                            <tr>
                              <th>Produtor/Produto</th>
                              <th>Unidade</th>
                              <th>Preço para<br>Associado (R$)</th>
                              <th>Preço para<br>Não-Associado (R$)</th>
                                       
                            </tr>
					   </thead>
                       <tbody>
					   <?php
					   
					   $total_pedido=0;
                       while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
                       {
							if($row["forn_nome_completo"]!=$ultimo_forn)
							{
								
								$ultimo_forn = $row["forn_nome_completo"];
								
								?>
								 
										<tr>
										  <th colspan="4">
										  				<?php echo($row["forn_nome_completo"]);
														
												  if(isset($row["forn_link_info"]) && $row["forn_link_info"]!="")
												  {
													   echo("&nbsp;<a href='" . $row["forn_link_info"] . "' target='_blank'><span class='badge'><span class='glyphicon glyphicon-search'></span></span></a>");
												  }																													
										  				?>
                                            
                                              </th>
										</tr>
                         
								<?php
								
							}   
							
							?>
							<tr> 
                            <td style="text-align:left;">
								<?php echo($row["prod_nome"]); 
									  adiciona_popover_descricao("Descrição", $row["prod_descricao"]);
								?>      
                               <?php if($row["prod_retornavel"]!=0) echo("&nbsp;<i class='glyphicon glyphicon-retweet' title='Produto com embalagem retornável'></i>");?>
                               <?php if($row["chaprod_disponibilidade"]==1) echo("&nbsp;&nbsp;<span class='label label-warning'>entrega parcial</span>");?>
                            
                            </td>
                            <td><?php echo($row["prod_unidade"]);?></td>
							<td><?php echo(formata_numero_de_mysql($row["prod_valor_venda"])); ?></td>
							<td><?php echo(formata_numero_de_mysql($row["prod_valor_venda_margem"])); ?></td> 
                            
                            </tr>
						<?php       
                        
                           }
                        ?>
                                                    
                       </tbody>
					</table>
                
				<?php       
            
			   }
            ?>
            
  	</div>
    
    <?php   


   footer();
?>

