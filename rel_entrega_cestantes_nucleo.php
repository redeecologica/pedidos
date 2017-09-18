<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO] || $_SESSION[PAP_ACOMPANHA_RELATORIOS]  || $_SESSION[PAP_RESP_ENTREGA]  || $_SESSION[PAP_RESP_FINANCAS]);
    
  top();
  
 $cha_id=request_get("cha_id",0);
 $nuc_id=request_get("nuc_id",$_SESSION['usr.nuc']); 
 if($nuc_id==-1) $nuc_id=$_SESSION['usr.nuc']; 
                      
 $sql = "SELECT prodt_nome, nuc_nome_curto, DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega ";
 $sql.= "FROM chamadas LEFT JOIN produtotipos ON prodt_id = cha_prodt ";
 $sql.= "LEFT JOIN nucleos on nuc_id = " . prep_para_bd($nuc_id) . " " ;
 $sql.= "WHERE cha_id = " . prep_para_bd($cha_id);


 $res = executa_sql($sql);
 $row = mysqli_fetch_array($res,MYSQLI_ASSOC);

 if(!$res)
 {
	 redireciona(PAGINAPRINCIPAL);
 }

$prodt_nome = $row["prodt_nome"];
$cha_dt_entrega = $row["cha_dt_entrega"];
$nuc_nome_curto = $row["nuc_nome_curto"];


?>

  
  <div class="panel panel-default">
  <div class="panel-heading">
     <strong>Relatório - Entrega aos Cestantes no Núcleo - <?php echo($prodt_nome . " - " . $cha_dt_entrega); ?></strong>

  </div>

  	  
 <div class="panel-body">
 
 <form class="form-inline"  method="get" name="frm_filtro" id="frm_filtro">
	<?php  
  		$cha_id = request_get("cha_id",-1) ;
		$nuc_id = request_get("nuc_id",$_SESSION['usr.nuc']) ;
	?>
     <fieldset>
     
     	<div class="form-group">
  				<label for="cha_id">Chamada: </label>            
                 <select name="cha_id" id="cha_id" onchange="javascript:frm_filtro.submit();" class="form-control">
                 	<option value="-1">SELECIONE</option>
                    <?php
                        
                       $sql = "SELECT cha_id, prodt_nome, cha_dt_entrega cha_dt_entrega_original, DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega ";
                        $sql.= "FROM chamadas LEFT JOIN produtotipos ON prodt_id = cha_prodt ";
                        $sql.= "ORDER BY cha_dt_entrega_original DESC LIMIT 10";
						
                        $res = executa_sql($sql);
                        if($res)
                        {
						  $achou=false;
						  while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
                          {							
                             echo("<option value='" . $row['cha_id'] . "'");
                             if($row['cha_id']==$cha_id) 
							 {
								 echo(" selected");
								 $achou=true;
							 }
                             echo (">" . $row['prodt_nome'] . " - " . $row['cha_dt_entrega'] . "</option>");
                          }
						  if($cha_id!=-1 && !$achou)
						  {
							  $sql = "SELECT cha_id, prodt_nome, cha_dt_entrega cha_dt_entrega_original, DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega ";
							  $sql.= "FROM chamadas LEFT JOIN produtotipos ON prodt_id = cha_prodt ";
							  $sql.= "WHERE cha_id = " . prep_para_bd($cha_id);
							  $res2 = executa_sql($sql);
							  $row = mysqli_fetch_array($res2,MYSQLI_ASSOC);
							  if($row)
							  {
								  echo("<option value='" . $row['cha_id'] . "' selected>");
								  echo ($row['prodt_nome'] . " - " . $row['cha_dt_entrega'] . "</option>");	
							  }
						  }
						  
                        }
                    ?>                        
                 </select>    
		</div>                 

		&nbsp;&nbsp;
          <div class="form-group">          
  				<label for="nuc_id">Núcleo: </label>            
                <select name="nuc_id" id="nuc_id" onchange="javascript:frm_filtro.submit();" class="form-control">
                    <option value="-1" <?php echo(($nuc_id==-1)?" selected" : ""); ?> >SELECIONAR</option>
                    <option value="-1">-------------</option>                     
                    <?php
                        
                        $sql = "SELECT nuc_id, nuc_nome_curto, nuc_archive ";
                        $sql.= "FROM nucleos WHERE nuc_id IN ";
						$sql.= " (SELECT chanuc_nuc FROM chamadanucleos WHERE chanuc_cha = " . prep_para_bd($cha_id) . ") ";						
                        $sql.= "ORDER BY nuc_archive, nuc_nome_curto ";
                        $res = executa_sql($sql);
                        if($res)
                        {
						  $arquivados=0;
                          while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
                          {
							 if(!$arquivados)
							 {
								 if($row["nuc_archive"]==1) 
								 {
									 echo("<option value='-1'>-------------</option>");									 
									 $arquivados=1;
								 }
							 }
                             echo("<option value='" . $row['nuc_id'] . "'");
                             if($row['nuc_id']==$nuc_id) echo(" selected");
                             echo (">" . $row['nuc_nome_curto'] . "</option>");
                          }
                        }
                    ?>                        
                </select>                           
       
       
           </div>

           
         </fieldset>
    </form>
    
    </div>
    
   </div> 
    
    
      	
  
    

<?php 

if($nuc_id==-1) footer();
else
{
	
	
 // obtem valor taxa percentual da chamada
 $sql = "SELECT FORMAT(cha_taxa_percentual,2) as cha_taxa_percentual ";
 $sql.= "FROM chamadas ";
 $sql.= "WHERE cha_id = " . prep_para_bd($cha_id);
 $res = executa_sql($sql);
 $row = mysqli_fetch_array($res,MYSQLI_ASSOC);
 $cha_taxa_percentual = $row["cha_taxa_percentual"];



	

$sql="SELECT usr_nome_curto, usr_contatos, ped_usr_associado, ped_id FROM pedidos ";
$sql.="LEFT JOIN nucleos ON ped_nuc = nuc_id ";
$sql.="LEFT JOIN usuarios ON ped_usr = usr_id ";
$sql.="WHERE ped_cha = " . prep_para_bd($cha_id) . " AND ped_fechado = '1' AND ped_nuc = " . prep_para_bd($nuc_id) . " ";
$sql.=" ORDER BY usr_nome_curto ";
$res = executa_sql($sql); // lista de usuarios com pedido para esta chamada


if($res) 
{
	$cestante_nome = array();
    $cestante_valor=array();
    while ($usuario = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
	{
		$cestante_nome[] = $usuario['usr_nome_curto'];
		$cestante_contato[] = $usuario['usr_contatos'];
		$cestante_pedido[] = $usuario['ped_id'];
		$cestante_valor[] = 0;
		$cestante_valor_fornecedor[] = 0;
		$cestante_valor_entregue[] = 0;
		$cestante_valor_fornecedor_entregue[] = 0;		
		$cestante_associado[] = $usuario['ped_usr_associado'];
	}
	
}


// recebido pelo nucleo

$sql="SELECT prod_id, ";
$sql.=" FORMAT(SUM(dist_quantidade_recebido),1) dist_quantidade_recebido ";	
$sql.="FROM chamadaprodutos ";
$sql.="LEFT JOIN chamadas on cha_id = chaprod_cha ";
$sql.="LEFT JOIN produtos on prod_id = chaprod_prod ";
$sql.="LEFT JOIN distribuicao ON dist_cha = chaprod_cha AND dist_prod = chaprod_prod ";	
$sql.="WHERE chaprod_cha = " . prep_para_bd($cha_id);
$sql.=" AND dist_nuc = " . prep_para_bd($nuc_id);
$sql.=" AND chaprod_disponibilidade <> '0' ";
$sql.=" AND prod_ini_validade<=cha_dt_entrega AND prod_fim_validade>=cha_dt_entrega ";
$sql.="GROUP BY prod_id ";
$res_recebido = executa_sql($sql); 	
$recebido = array();
if($res_recebido)
{
	while($row = mysqli_fetch_array($res_recebido,MYSQLI_ASSOC))
	{
		$recebido[$row["prod_id"]] = $row["dist_quantidade_recebido"];	
	}
}	


// entregue

$sql="SELECT FORMAT(pedprod_entregue,2) as pedprod_entregue, nuc_nome_curto, forn_nome_curto, usr_nome_curto, ped_usr_associado, prod_nome, prod_valor_venda, prod_valor_venda_margem,  ";
$sql.="prod_unidade, prod_id, IFNULL(FORMAT(pedprod_quantidade,ceiling(log10(0.0001 + cast(reverse(cast(truncate((prod_multiplo_venda - truncate(prod_multiplo_venda,0)) *1000,0) as CHAR)) as UNSIGNED)))) , FORMAT(pedprod_quantidade,0)) as pedprod_quantidade, chaprod_disponibilidade ";
$sql.="FROM chamadaprodutos ";
$sql.="LEFT JOIN chamadas on cha_id = chaprod_cha ";
$sql.="LEFT JOIN produtos on prod_id = chaprod_prod ";
$sql.="LEFT JOIN pedidos ON ped_cha = cha_id ";
$sql.="LEFT JOIN nucleos ON ped_nuc = nuc_id ";
$sql.="LEFT JOIN usuarios on ped_usr = usr_id ";
$sql.="LEFT JOIN pedidoprodutos ON pedprod_ped = ped_id AND pedprod_prod=chaprod_prod ";
$sql.="LEFT JOIN fornecedores on prod_forn = forn_id ";
$sql.="WHERE ped_cha= " . prep_para_bd($cha_id) . " ";
$sql.="AND ped_fechado = '1' ";

$sql.="AND ped_nuc = " . prep_para_bd($nuc_id) . " ";

$sql.="AND chaprod_disponibilidade <> '0' ";
$sql.="AND prod_ini_validade<=cha_dt_entrega AND prod_fim_validade>=cha_dt_entrega  ";
$sql.="ORDER BY nuc_nome_curto, forn_nome_curto , prod_nome, prod_unidade, usr_nome_curto ";
$res = executa_sql($sql);


		if($res && isset($cestante_pedido))
		{
			$num_colunas=4 + 6*(count($cestante_nome)+1) + 2;
			$total=0;
			$total_entregue=0;
								   
			?>
         

			 <input class="btn btn-success" type="button" value="selecionar tabela para copiar"  onclick="selectElementContents( document.getElementById('selectable') );"> 
             <p />
                      
            
			<table id="selectable" class="table table-striped table-bordered table-condensed">
            <thead> 
             <tr>
               <th colspan="<?php echo($num_colunas); ?>" style="text-align:left;vertical-align:middle">Núcleo <?php echo($nuc_nome_curto); ?> - Pedido de <?php echo($prodt_nome); ?> - Entrega em <?php echo($cha_dt_entrega); ?> </th>            
             </tr>		
               <tr>
               	<th colspan="2">&nbsp;</th>
                
                <th style='text-align:right'  colspan="2">associado</th>
                     <?php   
                  for ($i = 0; $i < count($cestante_nome); $i++)
                  {                                                                                              
                   echo("<th colspan='6' style='text-align:center'> ");
                   echo($cestante_associado[$i]==0 ? "Não" : "Sim");
                   echo("</th>");
                  }                                            
                  ?> 
                  
               	<th colspan="8">&nbsp;</th>
              </tr>
                       	

            </thead>           		   
            
            <tbody>
			             
            <?php					

		   $ultimo_forn = "";
		   $total_qtde_produto=0;
		   $total_qtde_produto_entregue=0;

		   $total_final_recebido_fornecedor=0.0;
		   $total_final_nao_distribuido=0.0;
		   		   
		   $total_recebido_fornecedor=0.0;
		   $total_nao_distribuido=0.0;
		   
		   while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		   {
	   		   $total_qtde_recebido_fornecedor=isset($recebido[$row["prod_id"]])? $recebido[$row["prod_id"]] : 0;
				if($row["forn_nome_curto"]!=$ultimo_forn)
				{					
					if($ultimo_forn!="")
					{
						
						?>
                        <tr>
				         	<th colspan="4" style="text-align:right"> SUBTOTAL <?php echo($ultimo_forn);?> </th>
						  <?php	
						  $total=0;
						  $total_entregue=0;
					                           
                           for ($i = 0; $i < count($cestante_nome); $i++)
                           {	                                                                                            
                                echo("<th colspan='2' style='text-align:center'>R$ " . formata_moeda($cestante_valor_fornecedor[$i]) .  "</th>");
								echo("<th colspan='2' style='text-align:center'>R$ " . formata_moeda($cestante_valor_fornecedor_entregue[$i] - $cestante_valor_fornecedor[$i] ) .  "</th>");		
								echo("<th colspan='2' style='text-align:center'>R$ " . formata_moeda($cestante_valor_fornecedor_entregue[$i]) .  "</th>");	

								
								$total+=$cestante_valor_fornecedor[$i];
                                $cestante_valor_fornecedor[$i]=0;
								
								$total_entregue+=$cestante_valor_fornecedor_entregue[$i];
                                $cestante_valor_fornecedor_entregue[$i]=0;																								
								
                           }                                            
                           ?> 
                           <th colspan="2" style="text-align:center">R$ <?php echo(formata_moeda($total));?></th>
						   <th colspan="2" style="text-align:center">R$ <?php echo(formata_moeda($total_entregue - $total));?></th>
						   <th colspan="2" style="text-align:center">R$ <?php echo(formata_moeda($total_entregue));?></th>
                           <th style="text-align:center">R$ <?php echo(formata_moeda($total_recebido_fornecedor));?></th>
						   <th style="text-align:center">R$ <?php echo(formata_moeda($total_nao_distribuido));?></th>
                       </tr>
                        
						<?php
							$total_final_recebido_fornecedor+=$total_recebido_fornecedor;
							$total_final_nao_distribuido+=$total_nao_distribuido;

						   $total_recebido_fornecedor=0.0;
						   $total_nao_distribuido=0.0;
					}

					$ultimo_forn = $row["forn_nome_curto"];
					
								
					?>
                          <tr>
                            <th rowspan="2"><?php echo($row["forn_nome_curto"]);?></th>
                            <th rowspan="2">Unidade</th>
                            <th rowspan="2">Associado (R$)</th>
                            <th rowspan="2">Não Associado (R$)</th>                            
						 	 <?php
                     	      	foreach ($cestante_nome as $cestante) echo("<th colspan='6' style='text-align:center'>$cestante</th>");
                             ?> 
							<th colspan="6" style='text-align:center'>TOTAL Entregue aos Cestantes</th>
                            <th colspan="2">TOTAL Recebido pelo Núcleo</th>                                                         
                          </tr>
                          <tr>
						 	 <?php
                     	      	foreach ($cestante_nome as $cestante) 
								{
									echo("<th>pedido</th><th>R$</th>");
									echo("<th>extra</th><th>R$</th>");			
									echo("<th>entregue</th><th>R$</th>");								
						
								}
                             ?> 
                             <th>pedido</th><th>R$</th><th>extra</th><th>R$</th><th>entregue</th><th>R$</th>
                             <th>recebido</th><th nowrap="nowrap">recebido e não distribuído</th>
                             
                          </tr>                        
                          
                        </tr>
             
					<?php
                    		
				}  
				
				$total_qtde_produto=0;	
				$total_qtde_produto_entregue=0;					
				?>
				<tr> 
				<td><?php echo($row["prod_nome"]);?><?php if($row["chaprod_disponibilidade"]==1) echo("&nbsp;(parcial)");?></td>
				<td><?php echo($row["prod_unidade"]);?></td>
				<td><?php echo(formata_moeda($row["prod_valor_venda"]));?></td>
				<td><?php echo(formata_moeda($row["prod_valor_venda_margem"]));?></td>
                                                  
				  <?php
				  $total=0.0;
				  $total_entregue=0.0;
                   for ($i = 0; $i < count($cestante_nome); $i++)
                   {	
						if($i>0) $row = mysqli_fetch_array($res,MYSQLI_ASSOC);
						$valor_unitario = $row["ped_usr_associado"]==0?$row["prod_valor_venda_margem"]:$row["prod_valor_venda"];					
						$a_pagar=$row["pedprod_quantidade"]*$valor_unitario;
						$a_pagar_entregue=$row["pedprod_entregue"]*$valor_unitario;						
						
						if($row["pedprod_quantidade"]>0)
						{																	
	                        echo("<td>" . formata_numero_de_mysql($row["pedprod_quantidade"]) .  "</td>");	
    	                    echo("<td>" . formata_moeda($a_pagar) .  "</td>");		
						}
						else
						{
							echo("<td>-</td><td>-</td>");							
						}

						if($row["pedprod_entregue"] - $row["pedprod_quantidade"]!=0)
						{																	
	                        echo("<td>" . formata_numero_de_mysql($row["pedprod_entregue"] - $row["pedprod_quantidade"]) .  "</td>");	
    	                    echo("<td>" . formata_moeda($a_pagar_entregue - $a_pagar) .  "</td>");
						}
						else
						{
							echo("<td>-</td><td>-</td>");							
						}

						if($row["pedprod_entregue"]>0)
						{																	
	                        echo("<td>" . formata_numero_de_mysql($row["pedprod_entregue"]) .  "</td>");	
    	                    echo("<td>" . formata_moeda($a_pagar_entregue) .  "</td>");	
						}
						else
						{
							echo("<td>-</td><td>-</td>");							
						}	
						

																				
						
						$total_qtde_produto+=$row["pedprod_quantidade"];						
						$total_qtde_produto_entregue+=$row["pedprod_entregue"];						
				   		
						$total+=$a_pagar;
						$total_entregue+=$a_pagar_entregue;
						
						$cestante_valor[$i]+= $a_pagar;
						$cestante_valor_entregue[$i]+= $a_pagar_entregue;
						
						$cestante_valor_fornecedor[$i] += $a_pagar;
						$cestante_valor_fornecedor_entregue[$i] += $a_pagar_entregue;			


                   }     
				   
				   $total_recebido_fornecedor+=$total_qtde_recebido_fornecedor*$row["prod_valor_venda"];
				   $total_nao_distribuido+=($total_qtde_recebido_fornecedor - $total_qtde_produto_entregue)*$row["prod_valor_venda"];				   
                                    
                   ?> 
                
                <td><?php echo(formata_numero_de_mysql($total_qtde_produto));?></td>                   
				<td><?php echo(formata_moeda($total)); ?></td>                                                                       
                <td><?php echo(formata_numero_de_mysql($total_qtde_produto_entregue - $total_qtde_produto));?></td>                   
				<td><?php echo(formata_moeda($total_entregue - $total)); ?></td>             
                <td><?php echo(formata_numero_de_mysql($total_qtde_produto_entregue));?></td>                   
				<td><?php echo(formata_moeda($total_entregue)); ?></td>          
                <td><?php echo(formata_numero_de_mysql($total_qtde_recebido_fornecedor));?></td>                   
                <td <?php if($total_qtde_recebido_fornecedor - $total_qtde_produto_entregue !=0) echo("class='danger'");?>><?php echo(formata_numero_de_mysql($total_qtde_recebido_fornecedor - $total_qtde_produto_entregue));?></td>                   
                                                
				</tr>

				 
				<?php
				


		   }

						?>
                        <tr>
				         	<th colspan="4" style="text-align:right"> SUBTOTAL <?php echo($ultimo_forn);?> </th>
						  <?php	
						  $total=0;
						  $total_entregue=0;				
						                           
                           for ($i = 0; $i < count($cestante_nome); $i++)
                           {	                                                                                            
                                echo("<th colspan='2' style='text-align:center'>R$ " . formata_moeda($cestante_valor_fornecedor[$i]) .  "</th>");
								echo("<th colspan='2' style='text-align:center'>R$ " . formata_moeda($cestante_valor_fornecedor_entregue[$i] - $cestante_valor_fornecedor[$i] ) .  "</th>");		
								echo("<th colspan='2' style='text-align:center'>R$ " . formata_moeda($cestante_valor_fornecedor_entregue[$i]) .  "</th>");	

								
								$total+=$cestante_valor_fornecedor[$i];
                                $cestante_valor_fornecedor[$i]=0;
								
								$total_entregue+=$cestante_valor_fornecedor_entregue[$i];
                                $cestante_valor_fornecedor_entregue[$i]=0;																								
								
                           }                                            
                           ?> 
                           <th colspan="2" style="text-align:center">R$ <?php echo(formata_moeda($total));?></th>
						   <th colspan="2" style="text-align:center">R$ <?php echo(formata_moeda($total_entregue - $total));?></th>
						   <th colspan="2" style="text-align:center">R$ <?php echo(formata_moeda($total_entregue));?></th>
                           <th style="text-align:center">R$ <?php echo(formata_moeda($total_recebido_fornecedor));?></th>
						   <th style="text-align:center">R$ <?php echo(formata_moeda($total_nao_distribuido));?></th>    
                           
                           <?php 
						   
  							$total_final_recebido_fornecedor+=$total_recebido_fornecedor;
							$total_final_nao_distribuido+=$total_nao_distribuido;
						   ?>                    


                           
                       </tr>

		   
	

           <tr>
            <th colspan="4" style="text-align:right">cestante</th>
				  <?php
                   for ($i = 0; $i < count($cestante_nome); $i++)
                   {																		
                        echo("<th colspan='6' style='text-align:center'>" . $cestante_nome[$i] .  "</th>");										
                   }                                            
                   ?>                  
            <th colspan="6"  style="text-align:center">TOTAL Entregue aos Cestantes</th>          
            <th colspan="2">TOTAL Recebido pelo Núcleo</th> 
            </tr>

           <tr>
            <th colspan="4" style="text-align:right">somatório</th>
				  <?php
                   for ($i = 0; $i < count($cestante_nome) + 1; $i++)
                   {					   
                       echo("<th colspan='2'>pedido</th>");
                       echo("<th colspan='2'>extra</th>");	
                       echo("<th colspan='2'>entregue</th>");
                   }                                            
                   ?>                  
            <th>recebido</th>
            <th>recebido e não distribuído</th>            
            
           </tr>
           
          
           <tr>
            <th colspan="4" style="text-align:right">total sem taxa</th>
				  <?php
				  $total=0.0;
				  $total_entregue=0.0;
                   for ($i = 0; $i < count($cestante_nome); $i++)
                   {
					   $total+=$cestante_valor[$i];
					   $total_entregue+=$cestante_valor_entregue[$i];																
                       echo("<td colspan='2'> R$ " . formata_moeda($cestante_valor[$i]) .  "</td>");										
                       echo("<td colspan='2'> R$ " . formata_moeda($cestante_valor_entregue[$i] - $cestante_valor[$i]) .  "</td>");					   
                       echo("<td colspan='2'> R$ " . formata_moeda($cestante_valor_entregue[$i]) .  "</td>");															
						
                   }                                            
                   ?>                  
            <th colspan="2">R$ <?php echo(formata_moeda($total)); ?></th>
            <th colspan="2">R$ <?php echo(formata_moeda($total_entregue - $total)); ?></th>  
            <th colspan="2">R$ <?php echo(formata_moeda($total_entregue)); ?></th>
            
            <th>R$ <?php echo(formata_moeda($total_final_recebido_fornecedor)); ?></th>
            <th <?php if($total_final_nao_distribuido !=0) echo("class='danger'");?>>R$ <?php echo(formata_moeda($total_final_nao_distribuido)); ?></th>
                      
           </tr>

           <tr>
            <th colspan="4" style="text-align:right">taxa de <?php echo($cha_taxa_percentual) * 100; ?>% para associado</th>
				  <?php
				  $total=0.0;
				  $total_entregue=0.0;
                   for ($i = 0; $i < count($cestante_nome); $i++)
                   {
					   $valor_taxa = $cestante_associado[$i] ? $cha_taxa_percentual : 0;
					   $total+=$cestante_valor[$i]*$valor_taxa;
					   $total_entregue+=$cestante_valor_entregue[$i]*$valor_taxa;
					   
                       echo("<td colspan='2'> R$ " . formata_moeda($cestante_valor[$i]*$valor_taxa) .  "</td>");
					   echo("<td colspan='2'> R$ " . formata_moeda(($cestante_valor_entregue[$i] - $cestante_valor[$i])*$valor_taxa) .  "</td>");	
					   echo("<td colspan='2'> R$ " . formata_moeda($cestante_valor_entregue[$i]*$valor_taxa) .  "</td>");
				
                   }                                            
                   ?>                  
            <th colspan="2">R$ <?php echo(formata_moeda($total)); ?></th>
            <th colspan="2">R$ <?php echo(formata_moeda($total_entregue - $total)); ?></th>
            <th colspan="2">R$ <?php echo(formata_moeda($total_entregue)); ?></th>

            <th>&nbsp;</th>
            <th>&nbsp;</th>            

          </tr>

           <tr>
            <th colspan="4" style="text-align:right">Total Final</th>
				  <?php
				  $total=0.0;
				  $total_entregue=0.0;
                   for ($i = 0; $i < count($cestante_nome); $i++)
                   {	
				   	$valor_final = $cestante_associado[$i] ? $cestante_valor[$i]*(1+$cha_taxa_percentual ): $cestante_valor[$i];
				   	$valor_final_entregue = $cestante_associado[$i] ? $cestante_valor_entregue[$i]*(1+$cha_taxa_percentual ): $cestante_valor_entregue[$i];					
					$total+=$valor_final;
					$total_entregue+=$valor_final_entregue;
                        echo("<th colspan='2' align='center'> R$ " . formata_moeda($valor_final) .  "</th>");	
                        echo("<th colspan='2' align='center'> R$ " . formata_moeda($valor_final_entregue - $valor_final) .  "</th>");	
                        echo("<th colspan='2' align='center'> R$ " . formata_moeda($valor_final_entregue) .  "</th>");																					
                   }                                            
                   ?>                  
            <th colspan="2">R$ <?php echo(formata_moeda($total)); ?></th>
            <th colspan="2">R$ <?php echo(formata_moeda($total_entregue - $total)); ?></th>          
            <th colspan="2">R$ <?php echo(formata_moeda($total_entregue)); ?></th>            
              
            <th>&nbsp;</th>
            <th>&nbsp;</th>            
                          
            
           </tr>

          </tbody>
		  </table>
		   
		   <?php
		} 

 
	footer();
}
?>