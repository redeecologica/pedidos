<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO] || $_SESSION[PAP_ACOMPANHA_PRODUTOR] || $_SESSION[PAP_ACOMPANHA_RELATORIOS] || $_SESSION[PAP_RESP_ENTREGA]  || $_SESSION[PAP_RESP_FINANCAS] );
  top();
  
 $cha_id=request_get("cha_id","");
                      
 $sql = "SELECT prodt_nome, DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega ";
 $sql.= "FROM chamadas LEFT JOIN produtotipos ON prodt_id = cha_prodt ";
 $sql.= "WHERE cha_id = " . prep_para_bd($cha_id);
 $res = executa_sql($sql);
 $row = mysqli_fetch_array($res,MYSQLI_ASSOC);

 if(!$res)
 {
	 redireciona(PAGINAPRINCIPAL);
 }

?>

<legend>Relatório - Pedidos para os Produtores - <?php echo($row["prodt_nome"]); ?> - Entrega em <?php echo($row["cha_dt_entrega"]); ?></legend>
<br>

<?php 

$sql="SELECT nuc_nome_curto FROM pedidos ";
$sql.="LEFT JOIN nucleos ON ped_nuc = nuc_id ";
$sql.="WHERE ped_cha = " . prep_para_bd($cha_id) . " AND ped_fechado = '1' ";
$sql.="GROUP BY nuc_id  ORDER BY nuc_nome_curto ";
$res = executa_sql($sql); // lista de núcleos com pedido para esta chamada
if($res) 
{
	$nucleos = array();
	$total_valor_nucleos = array();
	$total_geral_valor_nucleos = array();	
    while ($nucleo = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
	{
		$nucleos[] = $nucleo['nuc_nome_curto'];
		$total_valor_nucleos[] = 0;		
		$total_geral_valor_nucleos[] = 0;				
	}	
}

$sql="SELECT  forn_nome_curto, prod_nome, prod_valor_compra,prod_unidade, nuc_nome_curto, ";
$sql.=" SUM(IFNULL(FORMAT(pedprod_quantidade,ceiling(log10(0.0001 + cast(reverse(cast(truncate((prod_multiplo_venda - truncate(prod_multiplo_venda,0)) *1000,0) as CHAR)) as UNSIGNED)))) , FORMAT(pedprod_quantidade,0))) as total_qtde_nucleo, ";
$sql.="FORMAT(est_prod_qtde_depois,2) estoque ";
$sql.="FROM chamadaprodutos ";
$sql.="LEFT JOIN chamadas on cha_id = chaprod_cha ";
$sql.="LEFT JOIN produtos on prod_id = chaprod_prod ";
$sql.="LEFT JOIN pedidos ON ped_cha = cha_id ";
$sql.="LEFT JOIN nucleos ON ped_nuc = nuc_id ";
$sql.="LEFT JOIN pedidoprodutos ON pedprod_ped = ped_id AND pedprod_prod=chaprod_prod ";
$sql.="LEFT JOIN fornecedores on prod_forn = forn_id ";
$sql.="LEFT JOIN estoque ON est_prod = chaprod_prod AND est_cha = " . prep_para_bd(get_chamada_anterior($cha_id)) . " ";
$sql.="WHERE ped_cha= " . prep_para_bd($cha_id) . " ";
$sql.="AND ped_fechado = '1' ";
$sql.="AND chaprod_disponibilidade <> '0' ";
$sql.="AND prod_ini_validade<=cha_dt_entrega AND prod_fim_validade>=cha_dt_entrega  ";
$sql.="GROUP BY  forn_id,prod_id, nuc_id ";
$sql.="ORDER BY forn_nome_curto,prod_nome, prod_unidade, nuc_nome_curto";

$res = executa_sql($sql);

		if($res)
		{
		   $ultimo_forn = "";
		   $total_valor_fornecedor=0;
		   $total_qtde_produto=0;		   
		   $num_colunas=count($nucleos)+7;
		   
		   
		   ?>
		   <input class="btn btn-success" type="button" value="selecionar tabela para copiar" 
           onclick="selectElementContents( document.getElementById('selectable') );">
           <br><br>
           
  		   <div id="selectable">
           
		   <?php
		   
		   		   
		   
		   while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		   {
				if($row["forn_nome_curto"]!=$ultimo_forn)
				{	
				
					if($ultimo_forn!="")
					{
						?>
              		       <tr>
				         	<th colspan="3" style="text-align:right">TOTAL a pagar: </th>
							  <?php
								  $somatorio_nucleos=0;
                                for ($i = 0; $i < count($total_valor_nucleos); $i++)
                               {
								    $somatorio_nucleos+=$total_valor_nucleos[$i];
                                    echo("<th style='text-align:center'>R$ ". formata_moeda($total_valor_nucleos[$i]) . "</th>");	
									$total_valor_nucleos[$i]=0;		   
									
                               }                                            
                               ?>     
                            <th colspan="2"  style="text-align:center">R$ <?php echo(formata_moeda($somatorio_nucleos));?></th>
				            <th colspan="2"  style="text-align:center">R$ <?php echo(formata_moeda($total_valor_fornecedor));?></th>
				           </tr>
                           
                           
                          	</tbody>
							</table>		
						<?php
					}
					
					$total_valor_fornecedor=0;
					$ultimo_forn = $row["forn_nome_curto"];
					
								
					?>
                    <table class="table table-striped table-bordered table-condensed">
                    <thead>
                        <tr>
                                  <th><?php echo($row["forn_nome_curto"]);?></th>
                                  <th>Unidade</th>
                                  <th>Valor (R$)</th>
                                  
								  <?php
	                               foreach ($nucleos as $nucleo)
								   {
									   	echo("<th>$nucleo</th>");									   
								   }                                            
        	                       ?>            
                                  <th>Demanda dos Núcleos</th>
                                  <th>Estoque Local</th>
                                  <th>Total Pedido</th>
                                  <th>Total (R$)</th>                                            
                        </tr>
                     </thead>           		   
                    <tbody>
			 
					<?php
                    		
				}  
				
				$total_qtde_produto=0;	
				?>
				<tr> 
				<td><?php echo($row["prod_nome"]);?></td>
				<td><?php echo($row["prod_unidade"]);?></td>
				<td><?php echo(formata_moeda($row["prod_valor_compra"]));?></td>

                                                  
				  <?php
                   for ($i = 0; $i < count($nucleos); $i++)
                   {
						if($i>0) $row = mysqli_fetch_array($res,MYSQLI_ASSOC);																	
                        echo("<td>" . formata_numero_de_mysql($row["total_qtde_nucleo"]) .  "</td>");	
						$total_qtde_produto+=$row["total_qtde_nucleo"];
						$total_valor_nucleos[$i]+=$row["total_qtde_nucleo"]*$row["prod_valor_compra"];
						$total_geral_valor_nucleos[$i]+=$row["total_qtde_nucleo"]*$row["prod_valor_compra"];										
                   }                            
				   $pedido_produtor = max(0,$total_qtde_produto - $row["estoque"]);                
                   ?> 
                
                <td><?php echo(formata_numero_de_mysql($total_qtde_produto));?></td>
                <td><?php echo(formata_numero_de_mysql(get_hifen_se_zero($row["estoque"])));?></td>
                <td><?php echo(formata_numero_de_mysql($pedido_produtor ));?></td>                
				<td><?php echo (formata_moeda($pedido_produtor * $row["prod_valor_compra"])); ?></td>
			
				</tr>
				  
				<?php
				$total_valor_fornecedor+=$pedido_produtor * $row["prod_valor_compra"];


		   }
		   
		  ?>  
          
                           <tr>
				         	<th colspan="3" style="text-align:right">TOTAL a pagar: </th>
							  <?php
								  $somatorio_nucleos=0;
                                for ($i = 0; $i < count($total_valor_nucleos); $i++)
                               {
								    $somatorio_nucleos+=$total_valor_nucleos[$i];
                                    echo("<th style='text-align:center'>R$ ". formata_moeda($total_valor_nucleos[$i]) . "</th>");	
									$total_valor_nucleos[$i]=0;		   
									
                               }                                            
                               ?>     
                            <th colspan="2"  style="text-align:center">R$ <?php echo(formata_moeda($somatorio_nucleos));?></th>
				            <th colspan="2"  style="text-align:center">R$ <?php echo(formata_moeda($total_valor_fornecedor));?></th>
				           </tr>
         
          </tbody>
		   </table>
		   
           
           
                     
            <table class="table table-striped table-bordered table-condensed">
                <thead>
                    <tr>
                          <th style="text-align:right">Somatório Geral: </th>
                          
						  <?php
                           foreach ($nucleos as $nucleo)
                           {
                                echo("<th>$nucleo</th>");									   
                           }                                            
                           ?>
                           <th>Total Núcleos</th>
                      </tr>                   
                 </thead>	   
                <tbody>                               
                      <tr>
                    	<th>Total Pedido</th>                                              
                          <?php
						   $total_geral_rede = 0;
                            for ($i = 0; $i < count($total_valor_nucleos); $i++)
                           {
                                echo("<th style='text-align:center'>R$ ". formata_moeda($total_geral_valor_nucleos[$i]) . "</th>");	
                                $total_geral_rede+=	$total_geral_valor_nucleos[$i];  
                           }                                            
                           ?>                                  
                        <th style="text-align:center">R$ <?php echo(formata_moeda($total_geral_rede));?></th                  
               ></tbody>
		   </table>
           
           </div>
           
		   <?php
		} 
 

 
	footer();
?>