<?php  
  require  "common.inc.php"; 
  verifica_seguranca( $_SESSION[PAP_RESP_MUTIRAO]);
  top();
?>

<?php

		$action = request_get("action",-1);
		if($action==-1) redireciona(PAGINAPRINCIPAL);

		$cha_id =  request_get("cha_id","");
		if($cha_id=="") redireciona(PAGINAPRINCIPAL);
				 
        $forn_id = request_get("forn_id",-1);
		
        $prod_id = request_get("prod_id",-1);		
		if($action== ACAO_EXIBIR_EDICAO && $prod_id=="") redireciona(PAGINAPRINCIPAL);
		
		if ($action<>-1) // por enquanto, vai precisar para todos os casos
		{
		  $sql = " SELECT DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, cha_prodt, prodt_nome, ((cha_dt_prazo_contabil is null) OR (cha_dt_prazo_contabil > now() ) ) as cha_dentro_prazo, date_format(cha_dt_prazo_contabil,'%d/%m/%Y %H:%i') cha_dt_prazo_contabil FROM chamadas ";
		  $sql.= " LEFT JOIN produtotipos ON cha_prodt = prodt_id ";
		  $sql.= " WHERE cha_id=". prep_para_bd($cha_id) . " ";
		  
 		  $res = executa_sql($sql);
  	      if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		  {				  
			$cha_dt_entrega = $row["cha_dt_entrega"];
			$cha_prodt = $row["cha_prodt"];
			$prodt_nome = $row["prodt_nome"];		
			$cha_dt_prazo_contabil = $row["cha_dt_prazo_contabil"];
			$cha_dentro_prazo = $row["cha_dentro_prazo"];			
		  }
		  
		  if(($action==ACAO_EXIBIR_EDICAO  || $action == ACAO_SALVAR) && (!$cha_dentro_prazo))
		  {
			  redireciona(PAGINAPRINCIPAL);			  
		  }
		  
		  
		  $sql = " SELECT forn_id, forn_nome_curto, forn_nome_completo FROM fornecedores ";
		  $sql.= " WHERE forn_id =". prep_para_bd($forn_id) . " ";		  		  
 		  $res = executa_sql($sql);
  	      if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		  {				  
			$forn_nome_curto = $row["forn_nome_curto"];
			$forn_nome_completo = $row["forn_nome_completo"];			
		  }
		  		  
		}	
		
				
		if ($action == ACAO_SALVAR) // salvar formulário preenchido
		{
			
			// salva distribuição do produto
			$n = isset($_REQUEST['dist_quantidade']) ? sizeof($_REQUEST['dist_quantidade']) : 0;
			$cha_id_bd = prep_para_bd($cha_id);
			$prod_id_bd = prep_para_bd($prod_id);			
										
			for($i=0;$i<$n;$i++)
			{
				$qtde_salvar = $_REQUEST['dist_quantidade'][$i]=="" ? 'NULL' : prep_para_bd(formata_numero_para_mysql($_REQUEST['dist_quantidade'][$i]));
				
				$sql = "INSERT INTO distribuicao (dist_cha, dist_nuc, dist_prod, dist_quantidade) ";
				$sql.= "VALUES ( " . $cha_id_bd . " ," .  prep_para_bd($_REQUEST['nuc_id'][$i]) . " ," . $prod_id_bd . ", ";
				$sql.= $qtde_salvar . ") ";
				$sql.= "ON DUPLICATE KEY UPDATE ";
				$sql.= " dist_quantidade = " . $qtde_salvar . " ";
				$res = executa_sql($sql);
				
			}

			

			if($res)
			{
				$action=ACAO_EXIBIR_LEITURA; //volta para modo visualização somente leitura
				adiciona_mensagem_status(MSG_TIPO_SUCESSO,"As informações de distribuição relacionadas à chamada de " . $cha_dt_entrega . " foram salvas com sucesso.");
				
				if(isset($_POST['back_url']))
				{
					redireciona($_POST['back_url']);
				}
																
			}
			else
			{
				adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar salvar informações de distribuição da chamada de " . $cha_dt_entrega . ".");								
			}
			escreve_mensagem_status();
		
		}
		
		if ($action == ACAO_EXIBIR_LEITURA || $action == ACAO_EXIBIR_EDICAO )  // exibir para visualização, ou exibir para edição
		{
			// capturar informação de estoque
		}	


	
		
?>

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

	$sql="SELECT  forn_nome_curto, forn_nome_completo, forn_id, prod_nome, prod_unidade, prod_id, nuc_nome_curto, nuc_id, ";
	$sql.=" chaprod_recebido chaprod_recebido, dist_quantidade, ";
	$sql.=" estoque_anterior.est_prod_qtde_depois estoque_anterior_depois, ";
	$sql.=" estoque_anterior.est_prod_qtde_antes estoque_anterior_antes, ";
	$sql.=" estoque_atual.est_prod_qtde_depois estoque_atual_depois, ";
	$sql.=" estoque_atual.est_prod_qtde_antes estoque_atual_antes, ";
	$sql.=" dist_quantidade distribuido_nucleo, ";
	//$sql.=" FORMAT(sum(pedprod_quantidade),1) total_nucleo ";
	$sql.=" SUM(IFNULL(pedprod_quantidade,0)) as total_pedido_nucleo ";
	$sql.="FROM chamadaprodutos ";
	$sql.="LEFT JOIN chamadas on cha_id = chaprod_cha ";
	$sql.="LEFT JOIN produtos on prod_id = chaprod_prod ";
	$sql.="LEFT JOIN pedidos ON ped_cha = cha_id ";
	$sql.="LEFT JOIN nucleos ON ped_nuc = nuc_id ";
	$sql.="LEFT JOIN pedidoprodutos ON pedprod_ped = ped_id AND pedprod_prod=chaprod_prod ";
	$sql.="LEFT JOIN fornecedores ON prod_forn = forn_id ";	
	$sql.="LEFT JOIN distribuicao ON dist_cha = chaprod_cha AND dist_nuc = ped_nuc AND dist_prod = chaprod_prod ";
	$sql.="LEFT JOIN estoque estoque_anterior ON estoque_anterior.est_prod = chaprod_prod AND estoque_anterior.est_cha = " . prep_para_bd(get_chamada_anterior($cha_id)) .  " ";
	$sql.="LEFT JOIN estoque estoque_atual ON estoque_atual.est_prod = chaprod_prod AND estoque_atual.est_cha = " . prep_para_bd($cha_id) .  " ";
	$sql.="WHERE ped_cha= " . prep_para_bd($cha_id) . " ";
	$sql.="AND prod_forn= " . prep_para_bd($forn_id) . " ";
	if($prod_id!=-1) $sql.="AND prod_id= " . prep_para_bd($prod_id) . " ";	
	$sql.="AND ped_fechado = '1' ";
	$sql.="AND chaprod_disponibilidade <> '0' ";
	$sql.="AND prod_ini_validade<=cha_dt_entrega AND prod_fim_validade>=cha_dt_entrega  ";
	$sql.="GROUP BY  forn_id,prod_id, nuc_id ";
	$sql.="ORDER BY forn_nome_curto,prod_nome, prod_unidade, nuc_nome_curto";
	
	$res = executa_sql($sql);

	
  ?>	
  
<ul class="nav nav-tabs">
  <li><a href="mutirao.php">Mutirão</a></li>
  <li><a href="estoque_pre.php"><i class="glyphicon glyphicon-bed"></i> Estoque Pré-Mutirão</a></li>
  <li><a href="recebimento.php"><i class="glyphicon glyphicon-road"></i> Recebimento</a></li>
  <li class="active"><a href="distribuicao_consolidado_por_produtor.php"><i class="glyphicon glyphicon-fullscreen"></i> Distribuição</a></li>  
  <li><a href="estoque_pos.php"><i class="glyphicon glyphicon-bed"></i> Estoque Pós-Mutirão</a></li>  
  <li><a href="mutirao_divergencias.php"><i class="glyphicon glyphicon-eye-open"></i> Divergências</a></li>
</ul>
<br>

<ul class="nav nav-tabs">
  <li class="active"><a href="distribuicao_consolidado_por_produtor.php">Distribuição por Produto</a></li>
  <li><a href="distribuicao_consolidado.php">Distribuição por Núcleo</a></li>
</ul>

<br>
  
  <div class="panel panel-default">
  <div class="panel-heading">
     <strong>Informações de Distribuição relacionada à chamada de <?php echo($prodt_nome . " - " . $cha_dt_entrega); ?></strong>

  </div>

  
  <?php   

 if($action==ACAO_EXIBIR_LEITURA)  //visualização somente leitura
 {
 ?>	  
 <div class="panel-body">
 
<a class="btn btn-default" href="distribuicao_consolidado_por_produtor.php"><i class="glyphicon glyphicon-arrow-left"></i> voltar</a> 
 
                <form class="form-inline" name="frm_filtro" id="frm_filtro">
                    
                     <fieldset>
                           <input type="hidden" name="action" value="<?php echo(ACAO_EXIBIR_LEITURA); ?>" /> 
                           <input type="hidden" name="cha_id" value="<?php echo($cha_id); ?>" />     
						   <input type="hidden" name="back_url" id="back_url" value="<?php echo(isset($_POST['back_url']) ? $_POST['back_url'] : ""); ?>" />    
					          <?php if( ! isset($_POST['back_url'])) echo("<script>document.getElementById(\"back_url\").value = document.referrer;</script>"); ?>
                           
					 </fieldset>
                </form>						
                
             </div> 
 </div>              
            <?php     
                

             if($forn_id!=-1)
			 { 
			 	$num_colunas=count($nucleos)*2 +10;     
			?>	
						
                        <table class="table table-striped table-bordered table-condensed table-hover">
						<thead>
                        	<tr>
                            	<th colspan="<?php echo($num_colunas);?>">Distribuído para os núcleos  - Produtor <?php echo($forn_nome_curto); ?></th>
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
	
    							<!--
								</tbody>
								</table>
                                -->		
							<?php
						}
						
						$ultimo_forn = $row["forn_nome_curto"];
						
									
						?>
                        <!--
						<table class="table table-striped table-bordered table-condensed">
						<thead>
                        -->
							<tr>

									<th rowspan="2">##</th>
									  <th rowspan="2"><?php echo($row["forn_nome_curto"]);?></th>
									  <th rowspan="2">Unidade</th>	  
									  <?php
									   foreach ($nucleos as $nucleo)
									   {
											echo("<th colspan='2'>$nucleo</th>");									   
									   }                                            
									   ?>            									  

                                      <th rowspan="2">Estoque Pré-Mutirão Real</th>
									  <th rowspan="2">Entregue pelo Produtor</th>
									  <th rowspan="2">Demanda</th>
									  <th rowspan="2">Distribuído</th>                                      
                                      <th rowspan="2">Estoque Pós-Mutirão Esperado</th>
                                      <th rowspan="2">Estoque Pós-Mutirão Real</th>  
                                      
							</tr>
							<tr>
									  <?php
									   foreach ($nucleos as $nucleo)
									   {
											echo("<th>Ped</th> <th>Dist</th>");									   
									   }                                           
									   echo(""); 
									   ?>
                                       

<!--									   <th>Estoque Pré-Mutirão Esperado</th>-->
                                       <!--
									   <th>Pedido pelos Núcleos</th>
									   <th>Pedido ao Produtor</th>
									   <th>Total Distribuído</th>
									   <th>Estoque Pós-Mutirão Esperado</th>
									   <th>Estoque Pós-Mutirão Real</th>        
                                       -->
							</tr>                        
						 <!--
                         </thead>           		   
						<tbody>
				 		-->
						<?php
								
					}  
					
					$total_qtde_produto=0;	
					$total_distribuido=0;
					?>
					<tr> 
                    <td>
                    <?php if ($cha_dentro_prazo)
					{
					?>	
                    <a class="btn btn-default <?php echo(0==0? "" : "btn-danger" ); ?>" href="distribuicao_por_produtor.php?action=<?php echo(ACAO_EXIBIR_EDICAO . "&cha_id=" . $cha_id .  "&forn_id=" . $row["forn_id"] .  "&prod_id=" . $row["prod_id"] );?>"><i class="glyphicon glyphicon-pencil glyphicon-white"></i> atualizar</a>
                    <?php
					}
					?>
                    </td>
					<td><?php echo($row["prod_nome"]);?></td>
					<td><?php echo($row["prod_unidade"]);?></td>                    								                                          
                    
					  <?php
					   for ($i = 0; $i < count($nucleos); $i++)
					   {
							if($i>0) $row = mysqli_fetch_array($res,MYSQLI_ASSOC);																	
							echo("<td>");
							echo_digitos_significativos($row["total_pedido_nucleo"]);
							echo("</td>");	
							echo("<td>");
							if($row["distribuido_nucleo"]) echo_digitos_significativos($row["distribuido_nucleo"]); else echo("&nbsp;"); 
							echo("</td>");	
							
							$total_qtde_produto+=$row["total_pedido_nucleo"];
							$total_distribuido+=$row["distribuido_nucleo"];
					   }                                            
					   ?> 


					<td><?php if($row["estoque_atual_antes"]) echo_digitos_significativos($row["estoque_atual_antes"]); else echo("&nbsp;");?></td>
					<td>
					<?php if($row["chaprod_recebido"]) echo_digitos_significativos($row["chaprod_recebido"]); else echo("&nbsp;");?>
					</td>  
					<td><?php echo_digitos_significativos($total_qtde_produto);?></td>
					<td><?php echo_digitos_significativos($total_distribuido);?></td> 
<!--
					<td><?php echo_digitos_significativos(max(0,$total_qtde_produto - $row["estoque_anterior_depois"]));?></td>
	-->			
					<?php 
						$estoque_esperado=0;					
						if($row["chaprod_recebido"] || $total_distribuido > 0 )
						{
							$estoque_esperado = $row["chaprod_recebido"] + $row["estoque_atual_antes"] - $total_distribuido;
						}
						else 
						{
							$estoque_esperado = max($row["estoque_anterior_depois"] - $total_qtde_produto,0);
						}					
					?>				
                
                	<td<?php if($estoque_esperado<0) echo(" class='danger'");?> ><?php echo_digitos_significativos($estoque_esperado);?></td> 
                    <td><?php if($row["estoque_atual_depois"]) echo_digitos_significativos($row["estoque_atual_depois"]); else echo("&nbsp;");?></td>                                    
                    <!--
                    <td><?php if($row["estoque_atual_antes"]) echo_digitos_significativos($row["estoque_atual_antes"]); else echo("&nbsp;");?></td>
					<td>
					<?php if($row["chaprod_recebido"]) echo_digitos_significativos($row["chaprod_recebido"]); else echo("&nbsp;");?>
					</td>  
					!-->
                	</tr>
					 
					<?php
					
	
		   }
		   
		  ?>  
          
         
          </tbody>
		   </table>
		   
		   <?php
		} 
		?> 
		
                

    
<?php 


	
 }
 else  //visualização para edição
 {

?>
	
    <form class="form-horizontal" method="post">
    
    
	<div class="panel-body">
    
             <div align="right">
                <button type="submit"  class="btn btn-primary btn-enviando" data-loading-text="salvando entrega...">
            <i class="glyphicon glyphicon-ok glyphicon-white"></i> salvar alterações</button>
                   &nbsp;&nbsp;
                   <button class="btn btn-default" type="button" onclick="javascript:location.href=document.referrer;"><i class="glyphicon glyphicon-off"></i> descartar alterações</button>
                    </div>    
    </div>    

        <fieldset> 
        
          <input type="hidden" name="cha_id" value="<?php echo($cha_id); ?>" />
          <input type="hidden" name="forn_id" value="<?php echo($forn_id); ?>" />          
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />  
		   <input type="hidden" name="back_url" id="back_url" value="<?php echo(isset($_POST['back_url']) ? $_POST['back_url'] : ""); ?>" />    
          <?php if( ! isset($_POST['back_url'])) echo("<script>document.getElementById(\"back_url\").value = document.referrer;</script>"); ?>
            

                
                 
                 <table class='table table-striped table-bordered table-condensed table-hover'>                 
                 
                  <thead>
                        	<tr>
                            	<th colspan="4">Relatório do que foi distribuído para os núcleos - produtor <?php echo($forn_nome_curto); ?> </th>
                            </tr>
                    </thead>
                    <tbody>
              

                                      

				<?php
 										  
                    if($res)
                    {
						echo("");

					   $ultimo_forn = "";
                       while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
                       {
							if($row["forn_nome_curto"]!=$ultimo_forn)
							{
								
								$ultimo_forn = $row["forn_nome_curto"];
								
								?>
                        <tr>
                            <th><?php echo( $row["prod_nome"]);  echo(" - "); echo ($row["prod_unidade"]);?></th>
                            <th colspan="2">
                            <button type="button" class="btn btn-info" name="copia_produtos_pedido" id="copia_produtos_pedido" onclick="javascript:replicaDados('replica-origem','replica-destino');">
							  <i class="glyphicon glyphicon-paste"></i> replicar do pedido
							</button>              
<!--
                            &nbsp;&nbsp;                                          


                            <button type="button" class="btn btn-warning" name="zera_quantidade" id="zera_quantidade" onclick="javascript:zeraDados('replica_destino','0');">
							  <i class="glyphicon glyphicon-off"></i> zerar 
							</button>  
    -->                        
                            
                            </th>
                        </tr>   
                                                        
										<tr>
                                         <th>Núcleo</th>
                                            <th>Pedido</th>
											<th class="coluna-quantidade">Distribuído</th>
										</tr>
								<?php
								
							}   
							
							?>
							<tr> 
                            <input type="hidden" name="nuc_id[]" value="<?php echo($row["nuc_id"]); ?>"/>
                            <td><?php echo($row["nuc_nome_curto"]);?></td> 
                            <td>         
                             <input type="hidden" name="total_pedido_nucleo[]" class="replica-origem" value="<?php echo_digitos_significativos($row["total_pedido_nucleo"],""); ?>">   
                                              
                          		<?php 
									if($row["total_pedido_nucleo"]) 
									{
										echo_digitos_significativos($row["total_pedido_nucleo"]); 
									}
									else
									{
										 echo("&nbsp;");								 
									}
								?> 
                             </td>                              
                            <td>
                            <input type="text" class="replica-destino form-control propaga-colar" style="font-size:18px; text-align:center;" value="<?php echo_digitos_significativos($row["dist_quantidade"],""); ?>" name="dist_quantidade[]"/>
                            </td>
                                                     
                            </tr>
                             
							<?php

                       }
                    } 
               
			      ?>             
                       </tbody></table>
            
           </div> 

	<div align="right">
                
                       <button type="submit"  class="btn btn-primary btn-enviando" data-loading-text="salvando...">
                <i class="glyphicon glyphicon-ok glyphicon-white"></i> salvar alterações</button>
                       
                       &nbsp;&nbsp;
                       
                       
                       
                       <button class="btn btn-default" type="button" onclick="javascript:location.href=document.referrer;"><i class="glyphicon glyphicon-off"></i> descartar alterações</button>
	</div>                                 
    

      </fieldset> 
    </form>
 

    
    <?php   
	
   }

   footer();
?>
