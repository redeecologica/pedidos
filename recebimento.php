<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_MUTIRAO] || $_SESSION[PAP_RESP_ENTREGA]  || $_SESSION[PAP_RESP_FINANCAS] );
  top();
?>

<?php

		$action = request_get("action",-1);
		if($action==-1) redireciona(PAGINAPRINCIPAL);

		$cha_id =  request_get("cha_id",-1);
		//if($cha_id=="") redireciona(PAGINAPRINCIPAL);	
		
		$recebimento =  request_get("recebimento","previa"); 
		// recebimento pode ser previa ou final
		// previa é o informado pelo mutirão (campo;)
		// final é o confirmado pela Finanças, que vai dar origem ao pagamento efetivo
		$recebimento_campo=$recebimento=="final" ? "chaprod_recebido_confirmado": "chaprod_recebido";
	
		

		if ($cha_id<>-1) 
		{
		  $sql = "SELECT DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, cha_prodt, prodt_nome FROM chamadas ";
		  $sql.= "LEFT JOIN produtotipos ON cha_prodt = prodt_id ";
		  $sql.= "WHERE cha_id=". prep_para_bd($cha_id) . " ";
		  
 		  $res = executa_sql($sql);
  	      if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		  {				  
			$cha_dt_entrega = $row["cha_dt_entrega"];
			$cha_prodt = $row["cha_prodt"];
			$prodt_nome = $row["prodt_nome"];
			
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

	$sql = "SELECT prod_id, prod_nome, FORMAT(chaprod_recebido,1) chaprod_recebido, ";
	$sql.= " FORMAT(chaprod_recebido_confirmado,1) chaprod_recebido_confirmado,SUM(pedprod_quantidade) total_demanda, ";
	$sql.= " est_prod_qtde_depois total_estoque, prod_unidade, forn_nome_curto, forn_nome_completo, forn_id, ";
	$sql.= " FORMAT( GREATEST(0,(SUM(pedprod_quantidade) - IF(est_prod_qtde_depois IS NULL, 0, est_prod_qtde_depois))), 1) total_pedido ";
	$sql.= " FROM chamadaprodutos ";
	$sql.= "LEFT JOIN produtos on chaprod_prod = prod_id ";
	$sql.= "LEFT JOIN chamadas on chaprod_cha = cha_id "; 
	$sql.= "LEFT JOIN fornecedores on prod_forn  = forn_id ";
	$sql.= "LEFT JOIN pedidos ON ped_cha = cha_id "; 
	$sql.= "LEFT JOIN pedidoprodutos ON pedprod_ped = ped_id AND pedprod_prod=chaprod_prod ";					
	$sql.= "LEFT JOIN estoque ON est_prod = chaprod_prod AND est_cha = " . prep_para_bd(get_chamada_anterior($cha_id)) . " ";	
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
                <ol class="breadcrumb">
                  <li> <a href="mutirao.php">Mutirão</a></li>
                  <li class="active">Recebimento</li>
                </ol>            
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

		&nbsp;&nbsp;
           
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
                            <th colspan="5">Recebimento - <?php echo($prodt_nome . " - " . $cha_dt_entrega); ?></th>
                            <th colspan="3"><a class="btn btn-primary" href="recebimento.php?action=<?php echo(ACAO_EXIBIR_EDICAO); ?>&cha_id=<?php echo($cha_id); ?>&recebimento=<?php echo($recebimento); ?>"><i class="glyphicon glyphicon-edit"></i> editar</a></th>
                            
    
                            
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
											<th>Estoque</th>                                                                                        
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
                          		<?php echo(get_hifen_se_zero(formata_numero_de_mysql($row["total_demanda"]))); ?> 
                             </td>   
							<td>                            
                          		<?php echo(get_hifen_se_zero(formata_numero_de_mysql($row["total_estoque"]))); ?> 
                             </td>                                
							<td>                            
                          		<?php echo(get_hifen_se_zero(formata_numero_de_mysql($row["total_pedido"]))); ?> 
                             </td>              
							<td>                            
                          		<?php echo($row["chaprod_recebido"] ? get_hifen_se_zero(formata_numero_de_mysql($row["chaprod_recebido"])):"&nbsp;"); ?> 
                             </td>
							<td>   
                          		<?php echo(isset($receb_nucleos[$row["prod_id"]]) ? get_hifen_se_zero(formata_numero_de_mysql($receb_nucleos[$row["prod_id"]])):"&nbsp;"); ?>
                             </td>
							<td>                            
                          		<?php echo($row["chaprod_recebido_confirmado"] ? get_hifen_se_zero(formata_numero_de_mysql($row["chaprod_recebido_confirmado"])):"&nbsp;"); ?> 
                             </td>               
                                 
                            </tr>
                             
							<?php

                       }
					   
					   echo("</tbody></table>");
                    } 
               
			      ?>       
                  </div>      

    <span class="pull-right"><a class="btn btn-primary" href="recebimento.php?action=<?php echo(ACAO_EXIBIR_EDICAO); ?>&cha_id=<?php echo($cha_id); ?>&recebimento=<?php echo($recebimento) ;?>"><i class="glyphicon glyphicon-edit"></i> editar</a>    
    </span>

   
	
<?php 

	
 }
 else  //visualização para edição
 {

?>

    <form class="form-horizontal" action="recebimento.php" method="post">


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
                            	<th colspan="8">Registro do que foi recebido referente à chamada de <?php echo($prodt_nome . " - " . $cha_dt_entrega); ?></th>
                            </tr>
	                  		</thead>
                             <tr>
                            <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
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
											<th>Estoque</th>                                            
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
                            
                            <input type="hidden" name="total_pedido[]" class="replica-origem-pedido" value="<?php echo($row["total_pedido"]?formata_numero_de_mysql($row["total_pedido"]):""); ?>">  
                            <input type="hidden" name="total_mutirao[]" class="replica-origem-mutirao" value="<?php echo($row["chaprod_recebido"]?formata_numero_de_mysql($row["chaprod_recebido"]):""); ?>">		
                            <input type="hidden" name="total_nucleo[]" class="replica-origem-nucleo" value="<?php echo(isset($receb_nucleos[$row["prod_id"]])?formata_numero_de_mysql($receb_nucleos[$row["prod_id"]]):""); ?>">  
                            
                             
                            <td><?php echo($row["prod_nome"]);?></td>
                            <td><?php echo($row["prod_unidade"]); ?></td>
                            <td><?php echo(formata_numero_de_mysql($row["total_demanda"]));?></td>                            
                            <td><?php echo(formata_numero_de_mysql($row["total_estoque"]));?></td>
                            <td>
								<?php echo(formata_numero_de_mysql($row["total_pedido"]));?>
                               
                            </td>
                            
                            <?php 
							if($recebimento_campo=="chaprod_recebido")
							{
								?>								
                                <td>                            
                                <input type="text" class="replica-destino form-control propaga-colar" style="font-size:18px; text-align:center;" value="<?php echo($row[$recebimento_campo]?formata_numero_de_mysql($row[$recebimento_campo]):""); ?>" name="<?php echo($recebimento_campo);?>[]"/>
                                </td>
                                 <td><?php echo(isset($receb_nucleos[$row["prod_id"]]) ? get_hifen_se_zero(formata_numero_de_mysql($receb_nucleos[$row["prod_id"]])):"&nbsp;"); ?></td>
                                 <td><?php echo(formata_numero_de_mysql($row["chaprod_recebido_confirmado"]));?> </td> 
                                <?php
							}
							else if ($recebimento_campo=="chaprod_recebido_confirmado")
							{
								?>					
							  	<td><?php echo(formata_numero_de_mysql($row["chaprod_recebido"]));?> </td>
                                <td><?php echo(isset($receb_nucleos[$row["prod_id"]]) ? get_hifen_se_zero(formata_numero_de_mysql($receb_nucleos[$row["prod_id"]])):"&nbsp;"); ?></td>
                                <td>                            
                                <input type="text" class="replica-destino form-control propaga-colar" style="font-size:18px; text-align:center;" value="<?php echo($row[$recebimento_campo]?formata_numero_de_mysql($row[$recebimento_campo]):""); ?>" name="<?php echo($recebimento_campo);?>[]"/>
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
                       
        
            
                <span class="pull-right">
                	  <button type="submit"  class="btn btn-primary btn-enviando" data-loading-text="salvando...">
            <i class="glyphicon glyphicon-ok"></i> salvar alterações</button>
                &nbsp;&nbsp;
                   
                   <button class="btn btn-default" type="button" onclick="javascript:window.history.go(-1);"><i class="glyphicon glyphicon-off"></i> descartar alterações</button>
                   
                </span>
                
	             
                   
                   
                                 
      </fieldset> 
    </form>
 
    
    
    <?php   
	
   }
   echo("</div>");

   footer();
?>
