<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO] || $_SESSION[PAP_RESP_MUTIRAO]);
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

<legend>Relatorio para o Mutirão - <?php echo($row["prodt_nome"]); ?> - Entrega em <?php echo($row["cha_dt_entrega"]); ?>
</legend>

<a class="btn btn-default" href="arquivos/modelo_relatorio_mutirao.xlsx"><i class="glyphicon glyphicon-download"></i> Baixar modelo de planilha para copiar/colar os dados</a>

</div>
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

$sql="SELECT  forn_nome_curto, prod_nome, prod_unidade, nuc_nome_curto, ";
$sql.=" FORMAT(chaprod_recebido,1) chaprod_recebido, ";
$sql.=" FORMAT(estoque_anterior.est_prod_qtde_depois,1) estoque_anterior_depois, ";
$sql.=" FORMAT(estoque_anterior.est_prod_qtde_antes,1) estoque_anterior_antes, ";
$sql.=" FORMAT(estoque_atual.est_prod_qtde_depois,1) estoque_atual_depois, ";
$sql.=" FORMAT(estoque_atual.est_prod_qtde_antes,1) estoque_atual_antes, ";
$sql.=" FORMAT(dist_quantidade,1) distribuido_nucleo, ";
$sql.=" FORMAT(sum(pedprod_quantidade),1) total_nucleo ";
$sql.="FROM chamadaprodutos ";
$sql.="LEFT JOIN chamadas on cha_id = chaprod_cha ";
$sql.="LEFT JOIN produtos on prod_id = chaprod_prod ";
$sql.="LEFT JOIN pedidos ON ped_cha = cha_id ";
$sql.="LEFT JOIN nucleos ON ped_nuc = nuc_id ";
$sql.="LEFT JOIN pedidoprodutos ON pedprod_ped = ped_id AND pedprod_prod=chaprod_prod ";
$sql.="LEFT JOIN fornecedores ON prod_forn = forn_id ";
$sql.="LEFT JOIN distribuicao ON dist_cha = ped_cha AND dist_nuc = ped_nuc AND dist_prod = pedprod_prod ";
$sql.="LEFT JOIN estoque estoque_anterior ON estoque_anterior.est_prod = chaprod_prod AND estoque_anterior.est_cha = " . prep_para_bd(get_chamada_anterior($cha_id)) .  " ";
$sql.="LEFT JOIN estoque estoque_atual ON estoque_atual.est_prod = chaprod_prod AND estoque_atual.est_cha = " . prep_para_bd($cha_id) .  " ";
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
		   $total_qtde_produto=0;		   
		   $num_colunas=count($nucleos)*2 +4;
		   
		   while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		   {
				if($row["forn_nome_curto"]!=$ultimo_forn)
				{	
				
					if($ultimo_forn!="")
					{
						?>

                          	</tbody>
							</table>		
						<?php
					}
					
					$ultimo_forn = $row["forn_nome_curto"];
					
								
					?>
                    <table class="table table-striped table-bordered table-condensed">
                    <thead>
                        <tr>
                                  <th rowspan="2"><?php echo($row["forn_nome_curto"]);?></th>
                                  <th rowspan="2">Unidade</th>
                                  
								  <?php
	                               foreach ($nucleos as $nucleo)
								   {
									   	echo("<th colspan='2'>$nucleo</th>");									   
								   }                                            
        	                       ?>            
                                  <th colspan="8">Consolidação do Mutirão</th>
                        </tr>
                        <tr>
								  <?php
	                               foreach ($nucleos as $nucleo)
								   {
									   	echo("<th>Pedido</th> <th>Distribuído</th>");									   
								   }                                           
								   echo("<th>Estoque Pré-Mutirão Esperado</th> <th>Estoque Pré-Mutirão Real</th> <th>Pedido pelos Núcleos</th> <th>Pedido ao Produtor</th> <th>Entregue pelo Produtor</th> <th>Total Distribuído</th><th>Estoque Pós-Mutirão Esperado</th><th>Estoque Pós-Mutirão Real</th>"); 
        	                       ?>            
                        </tr>                        
                     </thead>           		   
                    <tbody>
			 
					<?php
                    		
				}  
				
				$total_qtde_produto=0;	
				$total_distribuido=0;
				?>
				<tr> 
				<td><?php echo($row["prod_nome"]);?></td>
				<td><?php echo($row["prod_unidade"]);?></td>
                                                  
				  <?php
                   for ($i = 0; $i < count($nucleos); $i++)
                   {
						if($i>0) $row = mysqli_fetch_array($res,MYSQLI_ASSOC);																	
                        echo("<td>" . get_hifen_se_zero(formata_numero_de_mysql($row["total_nucleo"])) .  "</td>");	
						echo("<td>" . ($row["distribuido_nucleo"] ? get_hifen_se_zero(formata_numero_de_mysql($row["distribuido_nucleo"])) : "&nbsp;") .  "</td>");	
						
						$total_qtde_produto+=$row["total_nucleo"];
						$total_distribuido+=$row["distribuido_nucleo"];
                   }                                            
                   ?> 
                
                <td><?php echo(get_hifen_se_zero(formata_numero_de_mysql($row["estoque_anterior_depois"])));?></td>
                <td><?php echo($row["estoque_atual_antes"] ? get_hifen_se_zero(formata_numero_de_mysql($row["estoque_atual_antes"])) : "&nbsp;");?></td>
                <td><?php echo(get_hifen_se_zero(formata_numero_de_mysql($total_qtde_produto)));?></td>
                <td><?php echo(get_hifen_se_zero(formata_numero_de_mysql(max(0,$total_qtde_produto - $row["estoque_anterior_depois"]))));?></td>
                <td>
				<?php echo($row["chaprod_recebido"] ? get_hifen_se_zero(formata_numero_de_mysql($row["chaprod_recebido"])) : "&nbsp;" );?>
                </td>  <!-- o que de fato chegou do produtor -->
                
                <td><?php echo(get_hifen_se_zero(formata_numero_de_mysql($total_distribuido)));?></td> 
                
                <td><?php 
					$estoque_esperado=0;					
					if($row["chaprod_recebido"])
					{
						$estoque_esperado = max(0, $row["chaprod_recebido"] + $row["estoque_atual_antes"] - $total_distribuido);
					}
					else 
					{
						$estoque_esperado = max(0, $row["estoque_anterior_depois"] - $total_qtde_produto);
					}
					
					echo(get_hifen_se_zero(formata_numero_de_mysql($estoque_esperado)));?></td> 
                
                <td><?php echo($row["estoque_atual_depois"] ? get_hifen_se_zero(formata_numero_de_mysql($row["estoque_atual_depois"])) : "&nbsp;");?></td>                
                
				</tr>
				 
				<?php
				

		   }
		   
		  ?>  
          
         
          </tbody>
		   </table>
		   
		   <?php
		} 
 
	 echo("<div>") ;
 
	footer();
?>