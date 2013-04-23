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
			$sql =  "SELECT prod_id FROM produtos ";
			$sql.= "WHERE prod_ini_validade<=NOW() AND prod_fim_validade>=NOW() AND prod_prodt = " . prep_para_bd($cha_prodt) . " ";
			$res = executa_sql($sql);			
			if($res)
			{
				while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
				{
					if(isset($_REQUEST["chaprod_prod_disponibilidade_" . $row["prod_id"]]))
					{
						$sql = "INSERT INTO chamadaprodutos (chaprod_cha, chaprod_prod, chaprod_disponibilidade) ";
						$sql.= "VALUES (" . prep_para_bd($cha_id) . "," . prep_para_bd($row["prod_id"]) . ", ";
						$sql.= prep_para_bd($_REQUEST["chaprod_prod_disponibilidade_" . $row["prod_id"]]) . ") ";
						$sql.= "ON DUPLICATE KEY UPDATE ";
						$sql.= "chaprod_disponibilidade = " . prep_para_bd($_REQUEST["chaprod_prod_disponibilidade_" . $row["prod_id"]]);
						$res2 = executa_sql($sql);
						
					}					
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
	<legend>Informações da Chamada</legend>
 <table class="table-condensed">
		<tbody>
    		<tr>
				<th align="right" class="span3">Tipo:</th> <td><?php echo($prodt_nome); ?></td>
			</tr>	    

    		<tr>
				<th align="right" class="span3">Data da Entrega:</th> <td><?php echo($cha_dt_entrega); ?></td>
			</tr>	    
    		<tr>
				<th align="right">Início Pedido:</th> <td><?php echo( ($cha_dt_min) . " " . ($cha_hh_min) ) ; ?></td>
			</tr>            
    		<tr>
				<th align="right">Término Pedido:</th>	<td><?php echo( ($cha_dt_max)  . " " . ($cha_hh_max)); ?></td>
			</tr>
    	
            <tr><td colspan="2"></td></tr>
            <tr> 
              <th>&nbsp;</th>  
              <td> 
         	 	<a class="btn btn-primary" href="chamada.php?action=<?php echo(ACAO_EXIBIR_EDICAO); ?>&cha_id=<?php echo($cha_id); ?>"><i class="icon-edit icon-white"></i> editar</a>
         	&nbsp;&nbsp;
         		<a class="btn" href="chamadas.php"><i class="icon-list"></i> listar chamadas</a>          </td>            
            </tr>
        </tbody>
    
</table>
  
   
	
<?php 

	
 }
 else  //visualização para edição
 {

?>
    <form class="form-horizontal" action="chamada.php" method="post">
     <legend>Atualização de Informações da Chamada</legend>    
        <fieldset>
          <input type="hidden" name="cha_id" value="<?php echo($cha_id); ?>" />
          <input type="hidden" name="cha_prodt" value="<?php echo($cha_prodt); ?>" />
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />  

            <div class="control-group">
               <label class="control-label" for="prodt_nome">Tipo</label>
                 <div class="controls">
                   <span class="well well-small"><?php echo($prodt_nome); ?></span>
                  </div>
            </div>


            <div class="control-group">
               <label class="control-label" for="cha_dt_entrega">Data da Entrega</label>
                 <div class="controls">
                   <input type="text" class="data input-small" id="cha_dt_entrega" name="cha_dt_entrega" required="required" value="<?php echo($cha_dt_entrega); ?>"/>
                  </div>
            </div>
            
            <div class="control-group">
               <label class="control-label" for="cha_dt_min">Início do Pedido</label>
                 <div class="controls">
                   Data: <input type="text" class="data input-small" id="cha_dt_min" name="cha_dt_min"  required="required" value="<?php echo($cha_dt_min); ?>"/>
                   Hora: <input type="text" id="cha_hh_min" name="cha_hh_min"  required="required" class="hora input-mini"  value="<?php echo($cha_hh_min); ?>"/>
                 </div>  
            </div>
            
             <div class="control-group">
                   <label class="control-label" for="cha_dt_max">Término do Pedido</label>
                   <div class="controls">   
                   Data: <input type="text" class="data input-small" id="cha_dt_max" name="cha_dt_max" required="required" value="<?php echo($cha_dt_max); ?>"/>
                   Hora: <input type="text" id="cha_hh_max" name="cha_hh_max"  required="required" class="hora input-mini" value="<?php echo($cha_hh_max); ?>"/>
    			   </div>
            </div>    
                
            <div class="control-group">
             <label class="control-label" for="chanuc_nuc">Núcleos Atendidos</label>
             <div class="controls">
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
								echo("<div class='span2'>");
							}		
							echo("<label class='checkbox'><input name='chanuc_nuc[]' type='checkbox'");
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
            
            
            <div class="control-group">
               <label class="control-label" for="chaprod_prod">Produtos Disponíveis</label>
                 <div class="container">

				<?php
                    $sql = "SELECT prod_id, prod_nome, FORMAT(prod_valor_compra,2) prod_valor_compra, ";
					$sql.= "FORMAT(prod_valor_venda_margem,2) prod_valor_venda_margem, prod_unidade, ";
					$sql.= "chaprod_prod, chaprod_disponibilidade, forn_nome_curto, forn_nome_completo, forn_id, forn_contatos, prod_prodt FROM produtos ";
                    $sql.= "LEFT JOIN chamadaprodutos on chaprod_prod = prod_id AND chaprod_cha = " . prep_para_bd($cha_id) . " ";
                    $sql.= "LEFT JOIN chamadas on chaprod_cha = cha_id "; 
                    $sql.= "LEFT JOIN fornecedores on prod_forn = forn_id ";
                    $sql.= "WHERE prod_ini_validade<=NOW() AND prod_fim_validade>=NOW() AND forn_archive = '0' AND prod_prodt = " . prep_para_bd($cha_prodt) . " ";
                    $sql.= "ORDER BY forn_nome_curto, prod_nome, prod_unidade ";
                    $res = executa_sql($sql);					
				
					  
                    if($res)
                    {
						echo("<table class='table table-striped table-bordered table-condensed table-hover'>");

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
											  adiciona_popover_descricao($row["forn_nome_completo"], $row["forn_contatos"]);
											  ?>
                                            </th>
											<th>Disponível</th>
											<th>Unidade</th>
											<th>Valor (R$)</th>
											<th>C/ Margem (R$)</th>
										</tr>
								<?php
								
							}   
							
							?>
							<tr> 
                            <td><?php echo($row["prod_nome"]);?></td>
							<td>
                                <label class="radio inline">
                                  <input type="radio" name="chaprod_prod_disponibilidade_<?php echo($row["prod_id"]);?>" id="chaprod_prod_disponibilidade_<?php echo($row["prod_id"]);?>_2" value="2" <?php echo( ($row["chaprod_disponibilidade"] == 2) ? "checked='checked'" : "") ;?>>
                                  SIM
                                </label>
                                                                                                
                                <label class="radio inline">
                                  <input type="radio" name="chaprod_prod_disponibilidade_<?php echo($row["prod_id"]);?>" id="chaprod_prod_disponibilidade_<?php echo($row["prod_id"]);?>_1" value="1" <?php echo( ($row["chaprod_disponibilidade"] == 1) ? "checked='checked'" : ""); ?>>
                                  parcial
                                </label>
                                <label class="radio inline">
                                  <input type="radio" name="chaprod_prod_disponibilidade_<?php echo($row["prod_id"]);?>" id="chaprod_prod_disponibilidade_<?php echo($row["prod_id"])?>_0" value="0" <?php echo((!is_null($row["chaprod_disponibilidade"]) && $row["chaprod_disponibilidade"] == 0) ? "checked='checked'" : "");?>>
                                  não
                                </label>
                            </td>
                            <td><?php echo($row["prod_unidade"]); ?></td>
							<td><?php echo(formata_moeda($row["prod_valor_compra"])); ?></td>                            							
							<td><?php echo(formata_moeda($row["prod_valor_venda_margem"]));?></td> 
                            </tr>
                             
							<?php

                       }
					   
					   echo("</table>");
                    } 
               
			      ?>             
                       
                 </div>  
            </div>
            
            
		  <div class="control-group">
            <div class="controls">
                   <button class="btn btn-primary" type="submit"><i class="icon-ok icon-white"></i> salvar alterações</button>
                   &nbsp;&nbsp;
                   <button class="btn" type="button" onclick="javascript:location.href='chamadas.php'"><i class="icon-off"></i> descartar alterações</button>
                                 
            </div>
          </div>
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
