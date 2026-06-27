<?php  
  require  "common.inc.php"; 
  verifica_seguranca( $_SESSION[PAP_RESP_MUTIRAO]  || $_SESSION[PAP_RESP_ENTREGA]  || $_SESSION[PAP_RESP_FINANCAS]   );
  top();
?>

<?php

		$action = request_get("action",-1);
		if($action==-1) redireciona(PAGINAPRINCIPAL);

		$cha_id =  request_get("cha_id","");
		if($cha_id=="") redireciona(PAGINAPRINCIPAL);
				 
        $nuc_id = request_get("nuc_id",$_SESSION['usr.nuc']);
		
		if ($action<>-1) // por enquanto, vai precisar para todos os casos
		{
		  $sql = " SELECT DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, cha_prodt, prodt_nome, nuc_nome_curto FROM chamadas ";
		  $sql.= " LEFT JOIN produtotipos ON cha_prodt = prodt_id LEFT JOIN nucleos on nuc_id = " . prep_para_bd($nuc_id);
		  $sql.= " WHERE cha_id=". prep_para_bd($cha_id) . " ";
		  
 		  $res = executa_sql($sql);
  	      if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		  {				  
			$cha_dt_entrega = $row["cha_dt_entrega"];
			$cha_prodt = $row["cha_prodt"];
			$prodt_nome = $row["prodt_nome"];
			$nuc_nome_curto = $row["nuc_nome_curto"];
			
		  }
		}	
		
				
		if ($action == ACAO_SALVAR) // salvar formulário preenchido
		{
			
			// salva disponibilidade de produtos
			$n = isset($_REQUEST['dist_quantidade']) ? sizeof($_REQUEST['dist_quantidade']) : 0;
			$cha_id_bd = prep_para_bd($cha_id);
			$nuc_id_bd = prep_para_bd($nuc_id);
										
			for($i=0;$i<$n;$i++)
			{
				$qtde_salvar = $_REQUEST['dist_quantidade'][$i]=="" ? 'NULL' : prep_para_bd(formata_numero_para_mysql($_REQUEST['dist_quantidade'][$i]));
				
				$sql = "INSERT INTO distribuicao (dist_cha, dist_nuc, dist_prod, dist_quantidade) ";
				$sql.= "VALUES ( " . $cha_id_bd . " ," .  $nuc_id_bd . " ," . prep_para_bd($_REQUEST['prod_id'][$i]) . ", ";
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

	$sql = "SELECT prod_id, prod_nome, prod_unidade, dist_quantidade, ";
	$sql.= " forn_nome_curto, forn_nome_completo, forn_id, SUM(pedprod_quantidade) total_pedido_nucleo ";
	$sql.= " FROM chamadaprodutos ";
	$sql.= "LEFT JOIN produtos on chaprod_prod = prod_id ";
	$sql.= "LEFT JOIN chamadas on chaprod_cha = cha_id "; 
	$sql.= "LEFT JOIN fornecedores on prod_forn  = forn_id ";
	$sql.= "LEFT JOIN pedidos ON ped_cha = cha_id "; 	
	$sql.= "LEFT JOIN pedidoprodutos ON pedprod_ped = ped_id AND pedprod_prod=chaprod_prod ";		
	$sql.= "LEFT JOIN distribuicao on dist_cha  = cha_id AND dist_prod = prod_id AND dist_nuc = " . prep_para_bd($nuc_id)  ;
	$sql.= " WHERE prod_ini_validade<=NOW() AND prod_fim_validade>=NOW()  AND ped_fechado = '1' AND ped_nuc = " . prep_para_bd($nuc_id)  ;
	$sql.= " AND chaprod_cha = " . prep_para_bd($cha_id) . " AND chaprod_disponibilidade > 0  ";
	$sql.= " GROUP BY forn_id, prod_id ";
	$sql.= " ORDER BY forn_nome_curto, prod_nome, prod_unidade ";
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
  <li><a href="distribuicao_consolidado_por_produtor.php">Distribuição por Produto</a></li>
  <li class="active"><a href="distribuicao_consolidado.php">Distribuição por Núcleo</a></li>
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
 
<button class="btn btn-default" type="button" onclick="javascript:location.href=document.referrer;"><i class="glyphicon glyphicon-arrow-left"></i> voltar</button> 
 
                <form class="form-inline" action="distribuicao.php" name="frm_filtro" id="frm_filtro">
                    
                     <fieldset>
                           <input type="hidden" name="action" value="<?php echo(ACAO_EXIBIR_LEITURA); ?>" /> 
                           <input type="hidden" name="cha_id" value="<?php echo($cha_id); ?>" />     
						   <input type="hidden" name="back_url" id="back_url" value="<?php echo(isset($_POST['back_url']) ? $_POST['back_url'] : ""); ?>" />    
					          <?php if( ! isset($_POST['back_url'])) echo("<script>document.getElementById(\"back_url\").value = document.referrer;</script>"); ?>
                           
						   <!--
                           <div class="form-group">
                             <label for="nuc_id">Núcleo: </label>            
                                <select name="nuc_id" id="nuc_id" onchange="javascript:frm_filtro.submit();" class="form-control">
                                    <option value="-1" <?php echo( ($nuc_id)==-1?" selected" : ""); ?> >SELECIONE</option>
                                    <?php
                                        
                                        $sql = "SELECT nuc_id, nuc_nome_curto ";
                                        $sql.= " FROM chamadanucleos INNER JOIN nucleos ON chanuc_nuc = nuc_id ";
										$sql.= " WHERE chanuc_cha = " . prep_para_bd($cha_id);
                                        $sql.= " ORDER BY nuc_nome_curto ";
                                        $res2 = executa_sql($sql);
                                        if($res2)
                                        {
                                          while ($row = mysqli_fetch_array($res2,MYSQLI_ASSOC)) 
                                          {
                                             echo("<option value='" . $row['nuc_id'] . "'");
                                             if($row['nuc_id']==$nuc_id) echo(" selected");
                                             echo (">" . $row['nuc_nome_curto'] . "</option>");
                                          }
                                        }
                                    ?>                        
                                </select> 
                              </div>                          
                              -->
                     </fieldset>
                </form>						
                
             </div>  
            <?php     
                

             if($nuc_id!=-1)
			 {      
			?>
         
						
						
                        <table class="table table-striped table-bordered table-condensed table-hover">
						<thead>
                        	<tr>
                            	<th colspan="4">Distribuído para o núcleo  <?php echo($nuc_nome_curto); ?></th>
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
                                            <th>Pedido</th>
											<th>Distribuído</th>
										</tr>
								<?php
								
							}   
							
							?>
							<tr>                              
                            <td><?php echo($row["prod_nome"]);?></td>
                            <td><?php echo($row["prod_unidade"]); ?></td>  
                            <td>                            
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
                          		<?php 
									if($row["dist_quantidade"]) 
									{
										echo_digitos_significativos($row["dist_quantidade"]); 
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
					   
					   echo("</tbody></table>");
					   
                    }  // nuc_id != -1
               
			      ?>             
		  </div>
                

    
<?php 


	
 }
 else  //visualização para edição
 {

?>
	
    <form class="form-horizontal" action="distribuicao.php" method="post">
    
    
	<div class="panel-body">
    
             <div align="right">
                <button type="submit"  class="btn btn-primary btn-enviando" data-loading-text="salvando entrega...">
            <i class="glyphicon glyphicon-ok glyphicon-white"></i> salvar alterações</button>
                   &nbsp;&nbsp;
                   <button class="btn btn-default" type="button" onclick="javascript:location.href=document.referrer;"><i class="glyphicon glyphicon-off"></i> descartar alterações</button>
                    </div>    
    </div>    
<!--
	<div align="right">
                
                       <button type="submit"  class="btn btn-primary btn-enviando" data-loading-text="salvando...">
                <i class="glyphicon glyphicon-ok glyphicon-white"></i> salvar alterações</button>
                       
                       &nbsp;&nbsp;
                       
                       
                       
                       <button class="btn btn-default" type="button" onclick="javascript:location.href='distribuicao.php?action=<?php echo(ACAO_EXIBIR_LEITURA);?>&cha_id=<?php echo($cha_id);?>'"><i class="glyphicon glyphicon-off"></i> descartar alterações</button>
	</div>     
-->
        <fieldset> 
        
          <input type="hidden" name="cha_id" value="<?php echo($cha_id); ?>" />
          <input type="hidden" name="nuc_id" value="<?php echo($nuc_id); ?>" />          
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />  
		   <input type="hidden" name="back_url" id="back_url" value="<?php echo(isset($_POST['back_url']) ? $_POST['back_url'] : ""); ?>" />    
          <?php if( ! isset($_POST['back_url'])) echo("<script>document.getElementById(\"back_url\").value = document.referrer;</script>"); ?>
            

                
                 
                 <table class='table table-striped table-bordered table-condensed table-hover'>                 
                 
                  <thead>
                        	<tr>
                            	<th colspan="4">Relatório do que foi distribuído para o núcleo <?php echo($nuc_nome_curto); ?></th>
                            </tr>
                    </thead>
                    <tbody>
              
                        <tr>
                            <td>&nbsp;</td><td>&nbsp;</td>
                            <td colspan="2">
                            <button type="button" class="btn btn-info" name="copia_produtos_pedido" id="copia_produtos_pedido" onclick="javascript:replicaDados('replica-origem','replica-destino');">
							  <i class="glyphicon glyphicon-paste"></i> replicar do pedido
							</button>                                                        
                            </td>
                        </tr>   
                                      

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
											<th>
											  <?php echo($row["forn_nome_curto"]);
											  adiciona_popover_descricao("",$row["forn_nome_completo"]);
											  ?>
                                            </th>
											<th>Unidade</th>
                                            <th>Pedido</th>
											<th class="coluna-quantidade">Distribuído</th>
										</tr>
								<?php
								
							}   
							
							?>
							<tr> 
                            <input type="hidden" name="prod_id[]" value="<?php echo($row["prod_id"]); ?>"/>
                             
                            <td><?php echo($row["prod_nome"]);?></td>
                            <td><?php echo($row["prod_unidade"]); ?></td>
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
