<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO]);
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
    while ($nucleo = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
	{
		$nucleos[] = $nucleo['nuc_nome_curto'];
	}		
}

$sql="SELECT  forn_nome_curto, prod_nome, prod_valor_compra,prod_unidade, nuc_nome_curto, FORMAT(sum(pedprod_quantidade),1) total_nucleo ";
$sql.="FROM chamadaprodutos ";
$sql.="LEFT JOIN chamadas on cha_id = chaprod_cha ";
$sql.="LEFT JOIN produtos on prod_id = chaprod_prod ";
$sql.="LEFT JOIN pedidos ON ped_cha = cha_id ";
$sql.="LEFT JOIN nucleos ON ped_nuc = nuc_id ";
$sql.="LEFT JOIN pedidoprodutos ON pedprod_ped = ped_id AND pedprod_prod=chaprod_prod ";
$sql.="LEFT JOIN fornecedores on prod_forn = forn_id ";
$sql.="WHERE ped_cha= " . prep_para_bd($cha_id) . " ";
$sql.="AND ped_fechado = '1' ";
$sql.="AND chaprod_disponibilidade <> '0' ";
$sql.="AND prod_ini_validade<=cha_dt_entrega AND prod_fim_validade>=cha_dt_entrega  ";
$sql.="GROUP BY  forn_id,prod_id, prod_unidade, nuc_id ";
$sql.="ORDER BY forn_nome_curto,prod_nome, nuc_nome_curto";


$res = executa_sql($sql);

		if($res)
		{
		   $ultimo_forn = "";
		   $total_valor_fornecedor=0;
		   $total_qtde_produto=0;		   
		   $num_colunas=count($nucleos)+5;
		   
		   while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		   {
				if($row["forn_nome_curto"]!=$ultimo_forn)
				{	
				
					if($ultimo_forn!="")
					{
						?>
                           <tr>
				         	<th colspan="<?php echo($num_colunas - 1);?>">TOTAL: </th>
				            <th>R$ <?php echo(formata_moeda($total_valor_fornecedor));?></th>
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
                                  <th>Total</th>
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
                        echo("<td>" . formata_numero_de_mysql($row["total_nucleo"]) .  "</td>");	
						$total_qtde_produto+=$row["total_nucleo"];
                   }                                            
                   ?> 
                
                <td><?php echo(formata_numero_de_mysql($total_qtde_produto));?></td>
                    
				<td><?php echo (formata_moeda($total_qtde_produto * $row["prod_valor_compra"])); ?></td>
			
				</tr>
				 
				<?php
				$total_valor_fornecedor+=$total_qtde_produto * $row["prod_valor_compra"];


		   }
		   
		  ?>  
          
           <tr>
            <th colspan="<?php echo($num_colunas - 1);?>">TOTAL: </th>
            <th>R$ <?php echo(formata_moeda($total_valor_fornecedor));?></th>
           </tr>
         
          </tbody>
		   </table>
		   
		   <?php
		} 
 

 
	footer();
?>