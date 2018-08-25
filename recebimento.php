<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_MUTIRAO] || $_SESSION[PAP_RESP_ENTREGA]  || $_SESSION[PAP_RESP_FINANCAS] );
  top();
?>

<?php

		$action = request_get("action",-1);
		if($action==-1) $action=ACAO_EXIBIR_LEITURA;

		$cha_id =  request_get("cha_id",-1);
		 if($cha_id==-1)
		 {
			 if(isset($_SESSION['cha_id_pref']))
			 {
				$cha_id=$_SESSION['cha_id_pref'];	 
			 }
		 }
		 $_SESSION['cha_id_pref']=$cha_id;
		 
		
		$recebimento =  request_get("recebimento","previa"); 
		// recebimento pode ser previa ou final
		// previa é o informado pelo mutirão (campo;)
		// final é o confirmado pela Finanças, que vai dar origem ao pagamento efetivo
		$recebimento_campo=$recebimento=="final" ? "chaprod_recebido_confirmado": "chaprod_recebido";
	
		

		if ($cha_id<>-1) 
		{
		  $sql = "SELECT prodt_nome, DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, cha_taxa_percentual, ((cha_dt_prazo_contabil is null) OR (cha_dt_prazo_contabil > now() ) ) as cha_dentro_prazo, date_format(cha_dt_prazo_contabil,'%d/%m/%Y %H:%i') cha_dt_prazo_contabil, cha_prodt ";
		  $sql.= "FROM chamadas LEFT JOIN produtotipos ON prodt_id = cha_prodt ";
		  $sql.= "WHERE cha_id = " . prep_para_bd($cha_id);
		  
 		  $res = executa_sql($sql);
  	      if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		  {				  
			$prodt_nome = $row["prodt_nome"];
			$cha_dt_entrega = $row["cha_dt_entrega"];
			$cha_prodt = $row["cha_prodt"];
			$cha_taxa_percentual = $row["cha_taxa_percentual"];
			$cha_dt_prazo_contabil = $row["cha_dt_prazo_contabil"];
			$cha_dentro_prazo = $row["cha_dentro_prazo"];			 			
		  }
		}
		
				
		if ($action == ACAO_SALVAR) // salvar formulário preenchido
		{
			
			$n = isset($_REQUEST[$recebimento_campo]) ? sizeof($_REQUEST[$recebimento_campo]) : 0;
			$cha_id_bd = prep_para_bd($cha_id);
										
			for($i=0;$i<$n;$i++)
			{
				$qtde_salvar = $_REQUEST[$recebimento_campo][$i]=="" ? 'NULL' : prep_para_bd(formata_numero_para_mysql($_REQUEST[$recebimento_campo][$i]));
				$sql = "UPDATE chamadaprodutos SET  ";
				$sql.= " " . $recebimento_campo . " = " . $qtde_salvar ;
				$sql.= " WHERE chaprod_cha = " . $cha_id_bd;
				$sql.= " AND chaprod_prod = " . prep_para_bd($_REQUEST['chaprod_prod'][$i]) ;
				$res = executa_sql($sql);
				
			}
			
			if($res)
			{
				$action=ACAO_EXIBIR_LEITURA; //volta para modo visualização somente leitura
				adiciona_mensagem_status(MSG_TIPO_SUCESSO,"As informações de recebimento relacionado à chamada " . $cha_dt_entrega . " foram salvas com sucesso.");								
			}
			else
			{
				adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar salvar informações de recebimento da chamada para o dia " . $cha_dt_entrega . ".");								
			}
			escreve_mensagem_status();
		
		}

	
		
?>

<?php 

	$sql = "SELECT prod_id, prod_nome, chaprod_recebido, ";
	$sql.= " chaprod_recebido_confirmado,SUM(pedprod_quantidade) total_demanda, estoque_atual.est_prod_qtde_antes estoque_pre_real, ";
	$sql.= " estoque_anterior.est_prod_qtde_depois estoque_pre_esperado, prod_unidade, forn_nome_curto, forn_nome_completo, forn_id, ";
	$sql.= " GREATEST(0,(SUM(pedprod_quantidade) - IF(estoque_anterior.est_prod_qtde_depois IS NULL, 0, estoque_anterior.est_prod_qtde_depois))) total_pedido ";
	$sql.= " FROM chamadaprodutos ";
	$sql.= "LEFT JOIN produtos on chaprod_prod = prod_id ";
	$sql.= "LEFT JOIN chamadas on chaprod_cha = cha_id "; 
	$sql.= "LEFT JOIN fornecedores on prod_forn  = forn_id ";
	$sql.= "LEFT JOIN pedidos ON ped_cha = cha_id "; 
	$sql.= "LEFT JOIN pedidoprodutos ON pedprod_ped = ped_id AND pedprod_prod=chaprod_prod ";					
	$sql.= "LEFT JOIN estoque estoque_anterior ON estoque_anterior.est_prod = chaprod_prod AND estoque_anterior.est_cha = " . prep_para_bd(get_chamada_anterior($cha_id)) . " ";	
	$sql.= "LEFT JOIN estoque estoque_atual ON estoque_atual.est_prod = chaprod_prod AND estoque_atual.est_cha = cha_id ";		
	$sql.= "WHERE prod_ini_validade<=NOW() AND prod_fim_validade>=NOW() AND ped_fechado = '1' ";
	$sql.= "AND chaprod_cha = " . prep_para_bd($cha_id) . " AND chaprod_disponibilidade > 0  ";
	$sql.= "GROUP BY forn_id, prod_id ";
	$sql.= "ORDER BY forn_nome_curto, prod_nome, prod_unidade ";
	$res = executa_sql($sql);	
	

	$sql="SELECT prod_id, ";
	$sql.=" FORMAT(SUM(dist_quantidade_recebido),1) dist_quantidade_recebido ";	
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

	<?php
		if($recebimento == "previa")
		{
			?>
            <ul class="nav nav-tabs">
              <li><a href="mutirao.php">Mutirão</a></li>
              <li><a href="estoque_pre.php"><i class="glyphicon glyphicon-bed"></i> Estoque Pré-Mutirão</a></li>
              <li class="active"><a href="#"><i class="glyphicon glyphicon-road"></i> Recebimento</a></li>
              <li><a href="distribuicao_consolidado_por_produtor.php"><i class="glyphicon glyphicon-fullscreen"></i> Distribuição</a></li>  
              <li><a href="estoque_pos.php"><i class="glyphicon glyphicon-bed"></i> Estoque Pós-Mutirão</a></li>
              <li><a href="mutirao_divergencias.php"><i class="glyphicon glyphicon-eye-open"></i> Divergências</a></li>
            </ul>
            <br>
            <?php			
		}
		else
		{
			?>
                <ul class="nav nav-tabs">
                  <li><a href="financas.php">Finanças</a></li>
                  <li class="active"><a href="#"><i class="glyphicon glyphicon-road"></i> Confirmação Entrega dos Produtores</a></li>
                  <li><a href="financas_prazos.php"><i class="glyphicon glyphicon-calendar"></i> Configuração Prazos</a></li>  
                </ul>
                <br>
            <?php
		}
		
	?>

  <!--  
  <div class="panel panel-default">
  <div class="panel-heading">
     <strong>Informações de Recebimento relacionado à chamada de <?php echo($prodt_nome . " - " . $cha_dt_entrega); ?></strong>
     <?php 
		 if($action == ACAO_EXIBIR_LEITURA)
		 {
		   ?>	 
		 <span class="pull-right"><a class="btn btn-xs btn-primary" href="recebimento.php?action=<?php echo(ACAO_EXIBIR_EDICAO); ?>&cha_id=<?php echo($cha_id); ?>"><i class="glyphicon glyphicon-edit"></i> editar</a></span>
		 <?php
		 }
	  ?>     
      
  </div>
  
  	  -->  
      
      

   <div class="panel panel-default">
  <div class="panel-heading">
     <strong>Recebimento</strong>

  </div>


 <div class="panel-body">
 
 <form class="form-inline"  method="get" name="frm_filtro" id="frm_filtro">
     <fieldset>
     	<input type="hidden" name="action" value="<?php echo(ACAO_EXIBIR_LEITURA);?>"/>
     	<input type="hidden" name="recebimento" value="<?php echo($recebimento);?>"/>        

     	<div class="form-group">
  				<label for="cha_id">Chamada: </label>            
                 <select name="cha_id" id="cha_id" onchange="javascript:frm_filtro.submit();" class="form-control">
                 	<option value="-1">SELECIONE</option>
                    <?php
                        
                       $sql = "SELECT cha_id, prodt_nome, cha_dt_entrega cha_dt_entrega_original, DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega ";
                        $sql.= "FROM chamadas LEFT JOIN produtotipos ON prodt_id = cha_prodt ";
						if($recebimento=="previa") $sql.= "WHERE prodt_mutirao = '1' ";
                        $sql.= "ORDER BY cha_dt_entrega_original DESC LIMIT 10";
						
                        $res_cha = executa_sql($sql);
                        if($res_cha)
                        {
						  $achou=false;
						  while ($row = mysqli_fetch_array($res_cha,MYSQLI_ASSOC)) 
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
			<?php 
				   if($cha_id!=-1 && $recebimento == "previa")
				   {
					 ?>  
                    &nbsp;&nbsp;
                    <label for="cha_dt_prazo_contabil">Prazo para Edição: </label>   <?php echo($cha_dt_prazo_contabil?$cha_dt_prazo_contabil:"ainda não configurado"); ?>
					
					<?php 
                        if(!$cha_dentro_prazo)
                        {
                            echo("<span class='alert alert-danger'>(encerrado)</span>");
                        }
				   }
				 ?>
           
         </fieldset>
    </form>
    
    </div>
    
   <!--</div> -->
    
  
  

<?php
 if($cha_id==-1)
 {
	 echo("Favor selecionar uma chamada.");
	
 }
 else if($action==ACAO_EXIBIR_LEITURA)  //visualização somente leitura
 {
 ?>	  
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
                            <th colspan="6">Informações de Recebimento - <?php echo($prodt_nome . " - " . $cha_dt_entrega); ?></th>
                            
                            <th colspan="3">
							<?php 
							if($recebimento == "final" || ($recebimento=="previa" && $cha_dentro_prazo) )
							{
								?>
								<a class="btn btn-primary" href="recebimento.php?action=<?php echo(ACAO_EXIBIR_EDICAO); ?>&cha_id=<?php echo($cha_id); ?>&recebimento=<?php echo($recebimento); ?>"><i class="glyphicon glyphicon-edit"></i> editar</a>
								<?php 
							}
							else echo("&nbsp;");
							?>
                            </th>

                            
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
											<th>Estoque Pré Esperado<?php adiciona_popover_descricao("Descrição", "Estoque informado pelo mutirão anterior e que deu base à encomenda"); ?></th>                         
											<th>Estoque Pré Real<?php adiciona_popover_descricao("Descrição", "Estoque real encontrado pelo mutirão atual"); ?></th>                                                                                                                                    
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
                          		<?php echo_digitos_significativos($row["estoque_pre_esperado"]); ?> 
                             </td>                                
							<td>                            
                          		<?php echo_digitos_significativos($row["estoque_pre_real"]); ?> 
                             </td>                                
							<td>                            
                          		<?php echo_digitos_significativos($row["total_pedido"]); ?> 
                             </td>              
							<td>                            
                          		<?php if($row["chaprod_recebido"]) echo_digitos_significativos($row["chaprod_recebido"]); else echo("&nbsp;"); ?> 
                             </td>
							<td>   
                          		<?php if(isset($receb_nucleos[$row["prod_id"]])) echo_digitos_significativos($receb_nucleos[$row["prod_id"]]); else echo("&nbsp;"); ?>
                             </td>
							<td>                            
                          		<?php if($row["chaprod_recebido_confirmado"]) echo_digitos_significativos($row["chaprod_recebido_confirmado"]); else echo("&nbsp;");  ?> 
                             </td>               
                                 
                            </tr>
                             
							<?php

                       }
					   
					   echo("</tbody></table>");
                    } 
               
			      ?>       
                  </div>      
                  
			   <?php 
                if($recebimento == "final" || ($recebimento=="previa" && $cha_dentro_prazo) )
                {
                    ?>
                        <span class="pull-right"><a class="btn btn-primary" href="recebimento.php?action=<?php echo(ACAO_EXIBIR_EDICAO); ?>&cha_id=<?php echo($cha_id); ?>&recebimento=<?php echo($recebimento) ;?>"><i class="glyphicon glyphicon-edit"></i> editar</a>    
                        </span>
                         
                    <?php 
                }
                ?>
                                  


   
	
<?php 

	
 }
 else  //visualização para edição
 {

?>

    <form class="form-horizontal" action="recebimento.php" method="post">


    
	<div class="panel-body">
    
             <div align="right">
                <button type="submit"  class="btn btn-primary btn-enviando" data-loading-text="salvando recebimento...">
            <i class="glyphicon glyphicon-ok glyphicon-white"></i> salvar alterações</button>
                   &nbsp;&nbsp;
                   <button class="btn btn-default" type="button" onclick="javascript:location.href=document.referrer;"><i class="glyphicon glyphicon-off"></i> descartar alterações</button>
                    </div>    
    </div>  

        <fieldset>
        
          <input type="hidden" name="cha_id" value="<?php echo($cha_id); ?>" />
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />  
          <input type="hidden" name="recebimento" value="<?php echo($recebimento); ?>" />  
            

				<?php
 										  
                    if($res)
                    {
						?>
                        <table class='table table-striped table-bordered table-condensed table-hover'>
						   <thead>
                            <tr>
                            	<th colspan="9">Registro do que foi recebido referente à chamada de <?php echo($prodt_nome . " - " . $cha_dt_entrega); ?></th>
                            </tr>
	                  		</thead>
                             <tr>
                            <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
                            <td>
                             
                            <span class='btn-popover' data-content='Clique para preencher com os dados do pedido' data-html='true' data-trigger='hover'>
                            <button type="button" class="btn btn-info" name="copia_produtos_entrega" id="copia_produtos_entrega" onclick="javascript:replicaDados('replica-origem-pedido','replica-destino');">
							 <i class='glyphicon glyphicon-paste'></i>
							</button> </span>                                                       
                            </td>
                            
                            <?php
							 if($recebimento=="final")
							 {
								 ?>
                                 <td>
								<span class='btn-popover' data-content='Clique para preencher com os dados de recebimento do mutirão' data-html='true' data-trigger='hover'>
								<button type="button" class="btn btn-info" name="copia_produtos_mutirao" id="copia_produtos_mutirao" onclick="javascript:replicaDados('replica-origem-mutirao','replica-destino');">
								 <i class='glyphicon glyphicon-paste'></i>
								</button> </span>   
                                </td>
                            	<td>
								<span class='btn-popover' data-content='Clique para preencher com os dados de recebimento dos núcleos' data-html='true' data-trigger='hover'>
								<button type="button" class="btn btn-info" name="copia_produtos_nucleos" id="copia_produtos_nucleos" onclick="javascript:replicaDados('replica-origem-nucleo','replica-destino');">
								 <i class='glyphicon glyphicon-paste'></i>
								</button> </span>  
                                                            
                            	</td>
                                
                             <?php 							 
							}
							else
							{
								echo("<td>&nbsp;</td><td>&nbsp;</td>");
							}
							?>

                            
                            
                            <td>&nbsp;</td>
                        </tr>              

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
											<th>Estoque Pré Esperado<?php adiciona_popover_descricao("Descrição", "Estoque informado pelo mutirão anterior e que deu base à encomenda"); ?></th>                         
											<th>Estoque Pré Real<?php adiciona_popover_descricao("Descrição", "Estoque real encontrado pelo mutirão atual"); ?></th>                                                                                                                                    
											<th>Pedido</th>
											<th>Recebido<br/>Mutirão</th>
                                            <th>Recebido<br/>Núcleos</th>     
											<th class="coluna-quantidade">Recebido<br/>FINAL</th>
                                        </tr>
								<?php
								
							}   
							
							?>
							<tr> 
                            <input type="hidden" name="chaprod_prod[]" value="<?php echo($row["prod_id"]); ?>"/>
                            
                            <input type="hidden" name="total_pedido[]" class="replica-origem-pedido" value="<?php echo_digitos_significativos($row["total_pedido"],""); ?>">  
                            <input type="hidden" name="total_mutirao[]" class="replica-origem-mutirao" value="<?php echo_digitos_significativos($row["chaprod_recebido"],""); ?>">		
                            <input type="hidden" name="total_nucleo[]" class="replica-origem-nucleo" value="<?php if(isset($receb_nucleos[$row["prod_id"]])) echo_digitos_significativos($receb_nucleos[$row["prod_id"]],""); else echo(""); ?>">  
                            
                             
                            <td><?php echo($row["prod_nome"]);?></td>
                            <td><?php echo($row["prod_unidade"]); ?></td>
                            <td><?php echo_digitos_significativos($row["total_demanda"]);?></td>                            
							<td>                            
                          		<?php echo_digitos_significativos($row["estoque_pre_esperado"]); ?> 
                             </td>                                
							<td>                            
                          		<?php echo_digitos_significativos($row["estoque_pre_real"]); ?> 
                             </td>  
                             
                            <td>
								<?php echo_digitos_significativos($row["total_pedido"]);?>
                               
                            </td>
                            
                            <?php 
							if($recebimento_campo=="chaprod_recebido")
							{
								?>								
                                <td>                            
                                <input type="text" class="replica-destino form-control propaga-colar" style="font-size:18px; text-align:center;" value="<?php if($row[$recebimento_campo]) echo_digitos_significativos($row[$recebimento_campo],"0"); ?>" name="<?php echo($recebimento_campo);?>[]"/>
                                </td>
                                 <td><?php if(isset($receb_nucleos[$row["prod_id"]])) echo_digitos_significativos($receb_nucleos[$row["prod_id"]],""); else echo("&nbsp;"); ?></td>
                                 <td><?php echo_digitos_significativos($row["chaprod_recebido_confirmado"]);?> </td> 
                                <?php
							}
							else if ($recebimento_campo=="chaprod_recebido_confirmado")
							{
								?>					
							  	<td><?php echo_digitos_significativos($row["chaprod_recebido"]);?> </td>
                                <td><?php if(isset($receb_nucleos[$row["prod_id"]])) echo_digitos_significativos($receb_nucleos[$row["prod_id"]],""); else echo("&nbsp;"); ?></td>
                                <td>                            
                                <input type="text" class="replica-destino form-control propaga-colar" style="font-size:18px; text-align:center;" value="<?php if($row[$recebimento_campo]) echo_digitos_significativos($row[$recebimento_campo],"0"); ?>" name="<?php echo($recebimento_campo);?>[]"/>
                                </td>

                                <?php							
							}
							?>
                                                 
                            </tr>
                             
							<?php

                       }
					   
					   echo("</table>");
                    } 
               
			      ?>             
                       
              
                 <div align="right">
                    <button type="submit"  class="btn btn-primary btn-enviando" data-loading-text="salvando recebimento...">
                <i class="glyphicon glyphicon-ok glyphicon-white"></i> salvar alterações</button>
                       &nbsp;&nbsp;
                       <button class="btn btn-default" type="button" onclick="javascript:location.href=document.referrer;"><i class="glyphicon glyphicon-off"></i> descartar alterações</button>
                        </div>                    
                                        
      </fieldset> 
    </form>
 
    
    
    <?php   
	
   }
   echo("</div>");

   footer();
?>
