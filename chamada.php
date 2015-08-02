<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO]);
  top();
?>

<?php

		$action = request_get("action",-1);
		if($action==-1) redireciona(PAGINAPRINCIPAL);

		$cha_prodt = request_get("cha_prodt","");		
		$cha_id =  request_get("cha_id","");

		
		if ( $action == ACAO_INCLUIR) // exibe formulário vazio para inserir novo registro
		{
			$cha_dt_min = "";
			$cha_dt_max = "";
			$cha_hh_min = "";
			$cha_hh_max = "";
			$cha_dt_entrega = "";
			$sql = "SELECT prodt_nome FROM produtotipos WHERE prodt_id= ". prep_para_bd($cha_prodt) . " ";
			$res = executa_sql($sql);
			if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
			{				  
				$prodt_nome = $row["prodt_nome"];
			}
			
		}
		else if ($action == ACAO_SALVAR) // salvar formulário preenchido
		{
			
			if($cha_id=="")
			{
				$sql = "INSERT INTO chamadas (cha_prodt) VALUES (" . prep_para_bd($_REQUEST["cha_prodt"]) . ") ";
				$res = executa_sql($sql);
				$cha_id = id_inserido();
			}


			// remove nucleos que não estão marcados
			$sql = "DELETE FROM chamadanucleos ";
			$sql.= "WHERE chanuc_cha=". prep_para_bd($cha_id) . " ";	
			if(!empty($_REQUEST["chanuc_nuc"])) $sql.= "AND chanuc_nuc NOT IN (". str_replace(",","','",prep_para_bd(implode(",", $_REQUEST['chanuc_nuc']))) . ")";	
			$res = executa_sql($sql);			

			// insere nucleos marcados (somente os que já não estavam selecionados)
			if(!empty($_REQUEST['chanuc_nuc'])) 				
			{				
				$sql = "INSERT IGNORE INTO chamadanucleos (chanuc_cha, chanuc_nuc) VALUES ";
				$primeiro = 1;
				foreach ($_REQUEST['chanuc_nuc'] as $chanuc_nuc) 
				{
					if($primeiro) $primeiro = 0; else $sql.= ", ";					
					$sql.= "( " . prep_para_bd($cha_id) . ", " . prep_para_bd($chanuc_nuc) . " ) ";
				}	
				$res = executa_sql($sql);	
			}		
			
			
			// salva disponibilidade de produtos		
			foreach($_POST as $p_campo=>$p_valor) 
			{
			  if((strlen($p_campo)>29) && (substr($p_campo,0,29)== 'chaprod_prod_disponibilidade_')  && (is_numeric($p_campo[29]) ) )
			  {
				  		$prod_id =substr($p_campo,29, strlen($p_campo)-29);
						
						$sql = "INSERT INTO chamadaprodutos (chaprod_cha, chaprod_prod, chaprod_disponibilidade) ";
						$sql.= "VALUES (" . prep_para_bd($cha_id) . "," . prep_para_bd($prod_id) . ", ";
						$sql.= prep_para_bd($p_valor) . ") ";
						$sql.= "ON DUPLICATE KEY UPDATE ";
						$sql.= "chaprod_disponibilidade = " . prep_para_bd($p_valor);
						$res2 = executa_sql($sql);															
				}						
			}	
			
			
			// atualiza informações da tabela chamadas
			$sql = "UPDATE chamadas SET ";
			$sql.= "cha_dt_min  = " . prep_para_bd(formata_data_hora_para_mysql($_REQUEST["cha_dt_min"] . " " .  $_REQUEST["cha_hh_min"])) . ", ";
			$sql.= "cha_dt_max  = " . prep_para_bd(formata_data_hora_para_mysql($_REQUEST["cha_dt_max"] . " " .  $_REQUEST["cha_hh_max"])) .  ", ";	
			$sql.= "cha_dt_entrega  = " . prep_para_bd(formata_data_para_mysql($_REQUEST["cha_dt_entrega"]) . " 12:00:00" ) .  "  ";	
			$sql.= "WHERE cha_id=". prep_para_bd($cha_id) . " ";	
			$res = executa_sql($sql);

			if($res)
			{
				$action=ACAO_EXIBIR_LEITURA; //volta para modo visualização somente leitura
				adiciona_mensagem_status(MSG_TIPO_SUCESSO,"Informações da chamada para " . $_REQUEST["cha_dt_entrega"] . " salvas com sucesso.");								
			}
			else
			{
				adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar salvar informações da chamada para o dia " . $_REQUEST["cha_dt_entrega"] . ".");								
			}
			escreve_mensagem_status();
		
		}


		if ($action == ACAO_EXIBIR_LEITURA || $action == ACAO_EXIBIR_EDICAO)  // exibir para visualização, ou exibir para edição
		{
		  $sql = "SELECT DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, DATE_FORMAT(cha_dt_min,'%d/%m/%Y') cha_dt_min, DATE_FORMAT(cha_dt_min,'%H:%i') cha_hh_min, DATE_FORMAT(cha_dt_max,'%d/%m/%Y') cha_dt_max, DATE_FORMAT(cha_dt_max,'%H:%i') cha_hh_max, cha_prodt, prodt_nome FROM chamadas ";
		  $sql.= "LEFT JOIN produtotipos ON cha_prodt = prodt_id ";
		  $sql.= "WHERE cha_id=". prep_para_bd($cha_id) . " ";
		  
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
		}		
		
?>

<?php 
 if($action==ACAO_EXIBIR_LEITURA)  //visualização somente leitura
 {
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
        </tbody>    
  </table>
  </div>  
  
        <div class="panel-footer">
      		<div class="col-sm-offset-2">
         	 	<a class="btn btn-primary" href="chamada.php?action=<?php echo(ACAO_EXIBIR_EDICAO); ?>&cha_id=<?php echo($cha_id); ?>"><i class="glyphicon glyphicon-edit glyphicon-white"></i> editar</a>
         	&nbsp;&nbsp;
         		<a class="btn btn-default" href="chamadas.php"><i class="glyphicon glyphicon-list"></i> listar chamadas</a>             </div>
       </div>
       
  </div>       
  
   
	
<?php 

	
 }
 else  //visualização para edição
 {

?>
 <form class="form-horizontal" action="chamada.php" method="post">

    <fieldset>
    <div class="panel panel-default">
      <div class="panel-heading">
         <strong>Atualização de Informações da Chamada</strong>
      </div>
    
    
     <div class="panel-body">
             
          <input type="hidden" name="cha_id" value="<?php echo($cha_id); ?>" />
          <input type="hidden" name="cha_prodt" value="<?php echo($cha_prodt); ?>" />
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />  

            <div class="form-group">
               <label class="control-label col-sm-2" for="prodt_nome">Tipo</label>
                 <div class="col-sm-2">
                   <span class="well well-sm"><?php echo($prodt_nome); ?></span>
                  </div>
            </div>


            <div class="form-group">
               <label class="control-label col-sm-2" for="cha_dt_entrega">Data da Entrega</label>
                 <div class="col-sm-2">
                   <input type="text" class="data form-control" id="cha_dt_entrega" name="cha_dt_entrega" required="required" value="<?php echo($cha_dt_entrega); ?>"/>
                  </div>
            </div>
            
            <div class="form-group">
               <label class="control-label col-sm-2" for="cha_dt_min">Início do Pedido</label>
                 <div class="col-sm-2"> 
                   Data: <input type="text" class="data form-control" id="cha_dt_min" name="cha_dt_min"  required="required" value="<?php echo($cha_dt_min); ?>"/>
                   </div>
                   <div class="col-sm-2"> 
                   Hora: <input type="text" id="cha_hh_min" name="cha_hh_min"  required="required" class="hora form-control"  value="<?php echo($cha_hh_min); ?>"/>
                   </div>
            </div>
            
             <div class="form-group">
                   <label class="control-label col-sm-2" for="cha_dt_max">Término do Pedido</label>
                   <div class="col-sm-2">   
                   Data: <input type="text" class="data form-control" id="cha_dt_max" name="cha_dt_max" required="required" value="<?php echo($cha_dt_max); ?>"/>
                   </div>
                   <div class="col-sm-2">                      
                   Hora: <input type="text" id="cha_hh_max" name="cha_hh_max"  required="required" class="hora form-control" value="<?php echo($cha_hh_max); ?>"/>
    			   </div>
            </div>    
                
            <div class="form-group">
             <label class="control-label col-sm-2" for="chanuc_nuc">
             	Núcleos Atendidos<br>
                     <label class="checkbox">
                        <input id="marca_todos_nucleos" type="checkbox" value="*"> Marcar Todos
                    </label>                
             </label>
             
             
             <div class="col-sm-9">
				<?php
					$sql =  "SELECT nuc_id, nuc_nome_curto, chanuc_nuc FROM nucleos ";
					$sql.= "LEFT JOIN chamadanucleos on chanuc_nuc =  nuc_id AND chanuc_cha = " . prep_para_bd($cha_id) . " ";
					$sql.= "WHERE nuc_archive='0' ORDER BY nuc_nome_curto ";
					$res = executa_sql($sql);					
				    if($res)
					{
					   $total = mysqli_num_rows($res);
					   $por_coluna = floor($total / 3);
					   $resto = fmod($total , 3);
					   $cont=0; $coluna=0;
					   while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
				       {	
							$cont++;						
							if($cont == 1)
							{
								$coluna++;
								echo("<div class='col-sm-3'>");
							}		
							echo("<label class='checkbox'><input name='chanuc_nuc[]' type='checkbox' class='nucleos'");
							if($row["chanuc_nuc"]) 
							{
								echo(" checked='checked' ");
							}
							echo("value='" . $row["nuc_id"] . "'>" . $row["nuc_nome_curto"] );
							echo("</label>");				
							if($cont == $por_coluna + ($resto >= $coluna)  )
							{
								echo("</div>");
								$cont=0;
							}
					   }
					   if($cont!=0) echo("</div>");
	 			    }
					 
				   ?> 
              </div>              
            </div>    

            
       </div>  <!-- div panel body-->
            
 

				<?php
					if($cha_id=="")
					{
						$bd_id_chamada_anterior = prep_para_bd(get_ultima_chamada_pelo_tipo($cha_prodt));
					}
					else
					{
						$bd_id_chamada_anterior = prep_para_bd(get_chamada_anterior($cha_id));
					}
					
                    $sql = "SELECT chamadaprodutos_anterior.chaprod_disponibilidade as chaprod_disponibilidade_anterior, ";
					$sql.= "prod_id, prod_nome, FORMAT(prod_valor_compra,2) prod_valor_compra, prod_descricao, ";
					$sql.= "FORMAT(prod_valor_venda_margem,2) prod_valor_venda_margem, prod_unidade, ";
					$sql.= "FORMAT(prod_valor_venda,2) prod_valor_venda, chamadaprodutos.chaprod_prod, ";
					$sql.= "chamadaprodutos.chaprod_disponibilidade, forn_nome_curto, forn_nome_completo, forn_link_info, ";
					$sql.= "forn_id, forn_info_chamada, prod_prodt, FORMAT(est_prod_qtde_depois,1) em_estoque FROM produtos ";
                    $sql.= "LEFT JOIN chamadaprodutos on chaprod_prod = prod_id AND chaprod_cha = " . prep_para_bd($cha_id) . " ";
                    $sql.= "LEFT JOIN chamadas on chaprod_cha = cha_id "; 
                    $sql.= "LEFT JOIN fornecedores on prod_forn = forn_id ";
			        $sql.= "LEFT JOIN estoque ON est_prod = prod_id AND est_cha = " . $bd_id_chamada_anterior . " ";	
					$sql.= "LEFT JOIN chamadaprodutos chamadaprodutos_anterior ON chamadaprodutos_anterior.chaprod_prod = prod_id ";
					$sql.= " AND chamadaprodutos_anterior.chaprod_cha= " . $bd_id_chamada_anterior . " " ;				
                    $sql.= "WHERE prod_ini_validade<=NOW() AND prod_fim_validade>=NOW() AND forn_archive = '0' AND prod_prodt = " . prep_para_bd($cha_prodt) . " ";
                    $sql.= "ORDER BY forn_nome_completo, prod_nome, prod_unidade ";
                    $res = executa_sql($sql);	
							   
                    if($res)
                    {
						echo("<table class='table table-striped table-bordered table-condensed table-hover'>");

					   $ultimo_forn = "";
                       while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
                       {
							if($row["forn_nome_completo"]!=$ultimo_forn)
							{
								
								$ultimo_forn = $row["forn_nome_completo"];
								$contador=0;
								?>
                                	<tr><th colspan="7">&nbsp;</th></tr>
										<tr>
                                        	<th>&nbsp;</th>
											<th>
											  <?php 
											  echo($row["forn_nome_curto"]);
											  adiciona_popover_descricao($row["forn_nome_completo"], $row["forn_info_chamada"]);
											  if(isset($row["forn_link_info"]) && $row["forn_link_info"]!="")
											  {
												   echo("&nbsp;<a href='" . $row["forn_link_info"] . "' target='_blank'><span class='badge'><span class='glyphicon glyphicon-search'></span></span></a>");
											  }
											  ?>
                                            </th>
											<th style="width:185px">Disponível <span class="label label-info">azul = anterior</span><br>
                    
												<label class="radio-inline"  style="margin-left: 4px; padding-left:7px;">
                                                <input type="radio"  name="disponibilidade_forn_<?php echo($row["forn_id"]);?>" id="disponibilidade_forn<?php echo($row["prod_id"])?>_X" value="X" data-fornecedor="<?php echo($row["forn_id"]);?>" class="seleciona_produtos_fornecedor radio-inline" style="margin-left:-15px"><span class="label label-info"><i class="glyphicon glyphicon-repeat"></i> </span>
												</label> 

												<label class="radio-inline"  style="margin-left: 4px; padding-left:10px;" >
												  <input type="radio" name="disponibilidade_forn_<?php echo($row["forn_id"]);?>" id="disponibilidade_forn_<?php echo($row["forn_id"]);?>_2" value="2" data-fornecedor="<?php echo($row["forn_id"]);?>" class="seleciona_produtos_fornecedor radio-inline" style="margin-left:-15px">
												  <span class="label label-success"><i class="glyphicon glyphicon-thumbs-up"></i> </span>
												</label>
													
												<label class="radio-inline" style="margin-left: 4px; padding-left:10px;">
												  <input type="radio" name="disponibilidade_forn_<?php echo($row["forn_id"]);?>" id="disponibilidade_forn_<?php echo($row["forn_id"]);?>_1" value="1" data-fornecedor="<?php echo($row["forn_id"]);?>" class="seleciona_produtos_fornecedor radio-inline" style="margin-left:-15px">
													<span class="label label-warning"><i class="glyphicon glyphicon-thumbs-up"></i> </span>
												</label>

												<label class="radio-inline" style="margin-left: 4px; padding-left:10px;">
												  <input type="radio" name="disponibilidade_forn_<?php echo($row["forn_id"]);?>" id="disponibilidade_forn<?php echo($row["prod_id"])?>_0" value="0" data-fornecedor="<?php echo($row["forn_id"]);?>" class="seleciona_produtos_fornecedor radio-inline" style="margin-left:-15px">
											<span class="label label-danger"><i class="glyphicon glyphicon-thumbs-down"></i> </span>
												</label>

                                                
											</th>
											<th>Unid.</th>
											<th>Compra (R$)</th>
											<th>Venda (R$)</th>                                            
											<th>C/ Margem (R$)</th>
										</tr>
								<?php
								
							}   
							
							?>
							<tr> 
                            <td><?php echo(++$contador); ?></td> 
                            <td><?php 
								echo($row["prod_nome"]); 
								adiciona_popover_descricao("Descrição", $row["prod_descricao"]); 
								if($row["em_estoque"]>0) echo("&nbsp;&nbsp;<span class='label label-info'>" . formata_numero_de_mysql($row["em_estoque"]) .  " em estoque</span>");
							
							?></td>
							<td>
                            <!-- hidden incluído com a disponibilidade anterior -->
                                  <input type="hidden" name="chaprod_prod_disponibilidade_anterior_<?php echo($row["prod_id"]);?>" id="chaprod_prod_disponibilidade_anterior_<?php echo($row["prod_id"])?>_X" value="<?php echo($row["chaprod_disponibilidade_anterior"]); ?>" data-fornecedor="<?php echo($row["forn_id"]);?>">
                            <!-- fim do hidden com a disponibilidade anterior -->
                                                                                          
                                <label class="radio-inline <?php echo((!is_null($row["chaprod_disponibilidade_anterior"]) && $row["chaprod_disponibilidade_anterior"] == 2) ? "label-info" : "" ); ?>">
                                  <input type="radio" name="chaprod_prod_disponibilidade_<?php echo($row["prod_id"]);?>" id="chaprod_prod_disponibilidade_<?php echo($row["prod_id"]);?>_2" value="2" <?php echo( ($row["chaprod_disponibilidade"] == 2) ? "checked='checked'" : "") ;?> data-fornecedor="<?php echo($row["forn_id"]);?>">
                                  <span class="label label-success"><i class="glyphicon glyphicon-thumbs-up"></i> </span>
                                </label>
                                                                                                
                                <label class="radio-inline <?php echo((!is_null($row["chaprod_disponibilidade_anterior"]) && $row["chaprod_disponibilidade_anterior"] == 1) ? "label-info" : "" ); ?>"">
                                  <input type="radio" name="chaprod_prod_disponibilidade_<?php echo($row["prod_id"]);?>" id="chaprod_prod_disponibilidade_<?php echo($row["prod_id"]);?>_1" value="1" <?php echo( ($row["chaprod_disponibilidade"] == 1) ? "checked='checked'" : ""); ?> data-fornecedor="<?php echo($row["forn_id"]);?>">
                                 <span class="label label-warning"><i class="glyphicon glyphicon-thumbs-up"></i> </span>
                                </label>
                                <label class="radio-inline  <?php echo((!is_null($row["chaprod_disponibilidade_anterior"]) && $row["chaprod_disponibilidade_anterior"] == 0) ? "label-info" : "" ); ?>"">
                                  <input type="radio" name="chaprod_prod_disponibilidade_<?php echo($row["prod_id"]);?>" id="chaprod_prod_disponibilidade_<?php echo($row["prod_id"])?>_0" value="0" <?php echo((!is_null($row["chaprod_disponibilidade"]) && $row["chaprod_disponibilidade"] == 0) ? "checked='checked'" : "");?> data-fornecedor="<?php echo($row["forn_id"]);?>">
                                 <span class="label label-danger"><i class="glyphicon glyphicon-thumbs-down"></i> </span>
                                </label>
                            </td>
                            <td><?php echo($row["prod_unidade"]); ?></td>
							<td><?php echo(formata_moeda($row["prod_valor_compra"])); ?></td>                            							
							<td><?php echo(formata_moeda($row["prod_valor_venda"])); ?></td> 
							<td><?php echo(formata_moeda($row["prod_valor_venda_margem"]));?></td> 
                            </tr>
                             
							<?php

                       }
					   
					   echo("</table>");
                    } 
               
			      ?>             

		  <div class="panel-footer">        
              <div class="form-group">
                  <div class="col-sm-offset-2">
<button class="btn btn-primary btn-enviando" data-loading-text="salvando chamada..." type="submit"><i class="glyphicon glyphicon-ok glyphicon-white"></i> salvar alterações</button>
                   &nbsp;&nbsp;
                   <button class="btn btn-default" type="button" onclick="javascript:location.href='chamadas.php'"><i class="glyphicon glyphicon-off"></i> descartar alterações</button>
                  </div>
              </div> 
          </div> <!-- div panel footer-->
          
                                 
        </div> <!-- div panel --> 
          
            
      </fieldset> 
    </form>
    
<script type="text/javascript">
	$(function() {
		$(".data").datepicker({
			format: 'dd/mm/yyyy',
			language: 'pt-BR',
			autoclose: true
		}).on('changeDate', verificaDatas);
			
		$(".hora").mask("99:99");
		$(".hora").blur(verificaHora);	
	}); 
</script>    
    
    <?php   
	
   }

   footer();
?>
