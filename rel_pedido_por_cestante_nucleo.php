<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO] || $_SESSION[PAP_ACOMPANHA_RELATORIOS]  || $_SESSION[PAP_RESP_ENTREGA]  || $_SESSION[PAP_RESP_FINANCAS]);    
  
   if(!isset($_REQUEST["baixar"]))
   {
	   top();
		echo("<legend>Relatório - Responsável pela Entrega</legend> <br />");
   }
   else
   {
	   header("Content-type: application/vnd.ms-excel; charset=UTF-8");   
	   header("Content-type: application/force-download");  
	   header("Content-Disposition: attachment; filename=pedido_nucleo.xls");
	   header("Pragma: no-cache");
   }
  
  
 $cha_id=request_get("cha_id",0);
 $nuc_id=request_get("nuc_id",0); 
                      
 $sql = "SELECT prodt_nome, nuc_nome_curto, DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, FORMAT(cha_taxa_percentual,2) as cha_taxa_percentual ";
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
$cha_taxa_percentual = $row["cha_taxa_percentual"];


?>



<?php 

$sql="SELECT usr_nome_curto, usr_contatos, ped_usr_associado FROM pedidos ";
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
		$cestante_valor[] = 0;
		$cestante_valor_fornecedor[] = 0;
		$cestante_associado[] = $usuario['ped_usr_associado'];
	}
	
}

$sql="SELECT  nuc_nome_curto, forn_nome_curto, usr_nome_curto, ped_usr_associado, prod_nome, prod_valor_venda, prod_valor_venda_margem,  ";
$sql.="prod_unidade, IFNULL(FORMAT(pedprod_quantidade,ceiling(log10(0.0001 + cast(reverse(cast(truncate((prod_multiplo_venda - truncate(prod_multiplo_venda,0)) *1000,0) as CHAR)) as UNSIGNED)))) , FORMAT(pedprod_quantidade,0)) as pedprod_quantidade, chaprod_disponibilidade ";
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


		if($res)
		{
			$num_colunas=2*count($cestante_nome)+7;
			$total=0;
								   
			?>
         
		  <input class="btn btn-success" type="button" value="selecionar tabela para copiar" 
           onclick="selectElementContents( document.getElementById('selectable') );">
           <br><br>
           
            
			<table id="selectable" class="table table-striped table-bordered table-condensed">
            <thead> 
             <tr>
               <th colspan="<?php echo($num_colunas); ?>" style="text-align:center;vertical-align:middle">Núcleo <?php echo($nuc_nome_curto); ?> - Pedido de <?php echo($prodt_nome); ?> - Entrega em <?php echo($cha_dt_entrega); ?> </th>            
             </tr>		
             
               <tr>
               	<th colspan="2">&nbsp;</th>
                
                <th style='text-align:right'  colspan="2">associado</th>
                     <?php   
                  for ($i = 0; $i < count($cestante_nome); $i++)
                  {                                                                                              
                   echo("<th colspan='2' style='text-align:center'> ");
                   echo($cestante_associado[$i]==0 ? "Não" : "Sim");
                   echo("</th>");
                  }                                            
                  ?> 
                  
               	<th colspan="3">&nbsp;</th>
              </tr>
                       	

            </thead>           		   
            
            <tbody>
			             
            <?php					

		   $ultimo_forn = "";
		   $total_qtde_produto=0;
		   
		   while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		   {
				if($row["forn_nome_curto"]!=$ultimo_forn)
				{					
					if($ultimo_forn!="")
					{
						
						?>
                        <tr>
				         	<th colspan="4" style="text-align:right"> SUBTOTAL <?php echo($ultimo_forn);?> </th>
						  <?php	
						  $total=0;				                         
                           for ($i = 0; $i < count($cestante_nome); $i++)
                           {	                                                                                            
                                echo("<th colspan='2' style='text-align:center'>R$ " . formata_moeda($cestante_valor_fornecedor[$i]) .  "</th>");
								$total+=$cestante_valor_fornecedor[$i];
                                $cestante_valor_fornecedor[$i]=0;
                           }                                            
                           ?> 
                           <th colspan="2" style="text-align:center">R$ <?php echo(formata_moeda($total));?></th>
                           <th>&nbsp;</th>
                           
                       </tr>
                        
						<?php
					}

					$ultimo_forn = $row["forn_nome_curto"];
					
								
					?>
                          <tr>
                            <th rowspan="2"><?php echo($row["forn_nome_curto"]);?></th>
                            <th rowspan="2">Unidade</th>
                            <th rowspan="2">Associado (R$)</th>
                            <th rowspan="2">Não Associado (R$)</th>                            
						 	 <?php
                     	      	foreach ($cestante_nome as $cestante) echo("<th colspan='2' style='text-align:center'>$cestante</th>");
                             ?> 
							<th colspan="3" style='text-align:center'>TOTAL</th>                                                         
                          </tr>
                          <tr>
						 	 <?php
                     	      	foreach ($cestante_nome as $cestante) echo("<th>qtde</th><th>R$</th>");
                             ?> 
                             <th>qtde</th><th>R$</th><th>conf</th>
                          </tr>                        
                          
                        </tr>
             
					<?php
                    		
				}  
				
				$total_qtde_produto=0;	
				?>
				<tr> 
				<td><?php echo($row["prod_nome"]);?><?php if($row["chaprod_disponibilidade"]==1) echo("&nbsp;(parcial)");?></td>
				<td><?php echo($row["prod_unidade"]);?></td>
				<td><?php echo(formata_moeda($row["prod_valor_venda"]));?></td>
				<td><?php echo(formata_moeda($row["prod_valor_venda_margem"]));?></td>
                                                  
				  <?php
				  $total=0.0;
                   for ($i = 0; $i < count($cestante_nome); $i++)
                   {	
						if($i>0) $row = mysqli_fetch_array($res,MYSQLI_ASSOC);						
						$a_pagar=$row["pedprod_quantidade"]*($row["ped_usr_associado"]==0?$row["prod_valor_venda_margem"]:$row["prod_valor_venda"]);
						if($row["pedprod_quantidade"]>0)
						{																	
	                        echo("<td>" . formata_numero_de_mysql($row["pedprod_quantidade"]) .  "</td>");	
    	                    echo("<td>" . formata_moeda($a_pagar) .  "</td>");							
						}
						else
						{
							echo("<td>-</td><td>-</td>");
						}
						$total_qtde_produto+=$row["pedprod_quantidade"];						
				   		$total+=$a_pagar;
						$cestante_valor[$i]+= $a_pagar;
						$cestante_valor_fornecedor[$i] += $a_pagar;			

                   }                                            
                   ?> 
                
                <td><?php echo(formata_numero_de_mysql($total_qtde_produto));?></td>   
				<td><?php echo(formata_moeda($total)); ?></td>                                    
				<td>&nbsp;</td>			
				</tr>

				 
				<?php
				


		   }

						?>
                        <tr>
				         	<th colspan="4" style="text-align:right"> SUBTOTAL <?php echo($ultimo_forn);?> </th>
						  <?php	
						  $total=0;				                         
                           for ($i = 0; $i < count($cestante_nome); $i++)
                           {	                                                                                            
                                echo("<th colspan='2' style='text-align:center'>R$ " . formata_moeda($cestante_valor_fornecedor[$i]) .  "</th>");
								$total+=$cestante_valor_fornecedor[$i];
                                $cestante_valor_fornecedor[$i]=0;
                           }                                            
                           ?> 
                           <th colspan="2" style="text-align:center">R$ <?php echo(formata_moeda($total));?></th>
                           <th>&nbsp;</th>
                           
                       </tr>


		   
	

           <tr>
            <th colspan="4" style="text-align:right">cestante</th>
				  <?php
                   for ($i = 0; $i < count($cestante_nome); $i++)
                   {																		
                        echo("<th colspan='2'>" . $cestante_nome[$i] .  "</th>");										
                   }                                            
                   ?>                  
            <th colspan="3">TOTAL</th>
           </tr>

          
           <tr>
            <th colspan="4" style="text-align:right">total sem taxa</th>
				  <?php
				  $total=0.0;
                   for ($i = 0; $i < count($cestante_nome); $i++)
                   {
					   $total+=$cestante_valor[$i];																
                        echo("<td colspan='2'> R$ " . formata_moeda($cestante_valor[$i]) .  "</td>");										
                   }                                            
                   ?>                  
            <th colspan="2">R$ <?php echo(formata_moeda($total)); ?></th>
            <th>&nbsp;</th>
           </tr>

           <tr>
            <th colspan="4" style="text-align:right">taxa de <?php echo($cha_taxa_percentual) * 100; ?>% para associado</th>
				  <?php
				  $total=0.0;
                   for ($i = 0; $i < count($cestante_nome); $i++)
                   {
					   $valor_taxa = $cestante_associado[$i] ? $cestante_valor[$i]*$cha_taxa_percentual : 0;
					   $total+=$valor_taxa;															
                        echo("<td colspan='2'> R$ " . formata_moeda($valor_taxa) .  "</td>");										
                   }                                            
                   ?>                  
            <th colspan="2">R$ <?php echo(formata_moeda($total)); ?></th>
            <th>&nbsp;</th>
          </tr>

           <tr>
            <th colspan="4" style="text-align:right">total final</th>
				  <?php
				  $total=0.0;
                   for ($i = 0; $i < count($cestante_nome); $i++)
                   {	
				   		$valor_final = $cestante_associado[$i] ? $cestante_valor[$i]*(1+$cha_taxa_percentual ): $cestante_valor[$i];
					   $total+=$valor_final;																		
                        echo("<th colspan='2' align='center'> R$ " . formata_moeda($valor_final) .  "</th>");										
                   }                                            
                   ?>                  
            <th colspan="2">R$ <?php echo(formata_moeda($total)); ?></th>
            <th>&nbsp;</th>
            
           </tr>

          </tbody>
		  </table>
		   
		   <?php
		} 

 
	footer();
?>