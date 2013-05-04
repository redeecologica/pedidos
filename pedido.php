<?php  
  require  "common.inc.php"; 

  $action = request_get("action",-1);
  if($action==-1) redireciona(PAGINAPRINCIPAL);
		
  $ped_id =  request_get("ped_id","");
  $ped_id_bd = prep_para_bd($ped_id);
  
  $ped_usr = request_get("ped_usr","");
  if($ped_usr=="") $ped_usr = get_usr_from_ped_id($ped_id);

  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO] || $_SESSION["usr.id"]== $ped_usr );
  
  if($action==ACAO_CONFIRMAR_PEDIDO || $action==ACAO_CANCELAR_PEDIDO  || $action==ACAO_SALVAR || $action==ACAO_EXIBIR_EDICAO  )
  {
     verifica_seguranca($_SESSION[PAP_RESP_PEDIDO] || ($_SESSION["usr.id"]== $ped_usr && pedido_esta_dentro_do_prazo($ped_id) ) );
  }  
  
  
  top();
?>

<?php

		$pedido_enviado=false;
					
		if ( $action == ACAO_CONFIRMAR_PEDIDO) // confirmar/enviar pedido
		{
			$sql = "UPDATE pedidos SET ";
			$sql.= "ped_fechado = '1' ";
			$sql.= "WHERE ped_id = $ped_id_bd";
			$res = executa_sql($sql);
			
			if($res) 
			{
				adiciona_mensagem_status(MSG_TIPO_SUCESSO,"Seu pedido foi enviado com sucesso.");
				$pedido_enviado=true;
			}
			else adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar enviar o pedido.");
			
//			if($_SESSION["usr.id"]== )					redireciona("meuspedidos.php");

			$action=ACAO_EXIBIR_LEITURA; // para visualizar no modo somente leitura
	
		}
		else if ($action == ACAO_CANCELAR_PEDIDO) // cancelar pedido
		{
			$sucesso_cancelar=1;
			
			 
			$sql = "UPDATE pedidos SET ped_fechado ='0' ";
			$sql.= "WHERE ped_id = $ped_id_bd";
			$res = executa_sql($sql);
			
			if(!$res) $sucesso_cancelar=0;
			 
			if($sucesso_cancelar) adiciona_mensagem_status(MSG_TIPO_SUCESSO,"Seu pedido foi cancelado com sucesso.");
			else adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar cancelar o pedido.");
			
//			redireciona('meuspedidos.php');
			
			$action=ACAO_EXIBIR_LEITURA; // para visualizar no modo edição	
		}
		

		if ( $action == ACAO_INCLUIR) // exibe formulário vazio para salvar novo registro
		{
			// já cria o pedido, sem produtos associados
			// antes, verifica se já não está criado

			$ped_cha =  request_get("ped_cha","");			
			$ped_usr =  request_get("ped_usr","");		
			
			$sql = "SELECT ped_id FROM pedidos WHERE ";
			$sql.= " ped_cha= " . prep_para_bd($ped_cha);
			$sql.= "AND ped_usr= " . prep_para_bd($ped_usr);
						
			$res = executa_sql($sql);
  		    if ($res && $row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
			{
				$ped_id = $row["ped_id"];				
			}
			else
			{
				$sql = "INSERT INTO pedidos (ped_cha, ped_usr, ped_fechado, ped_nuc) ";
				$sql.= "SELECT " . prep_para_bd($ped_cha) . " ," . prep_para_bd($ped_usr) . ", '0', ";
				$sql.= " usr_nuc FROM usuarios WHERE usr_id = " . prep_para_bd($ped_usr);
				$res = executa_sql($sql);							 
				$ped_id = id_inserido();
			}
			
			$action = ACAO_EXIBIR_EDICAO; // depois de pré-incluir pedido, exibe para edição
			
		}
		else if ($action == ACAO_SALVAR) // salvar formulário preenchido
		{
			$n = isset($_REQUEST['pedprod_quantidade_prod']) ? sizeof($_REQUEST['pedprod_quantidade_prod']) : 0;
			
			for($i=0;$i<$n;$i++)
			{
				$qtde_bd = prep_para_bd(formata_numero_para_mysql($_REQUEST['pedprod_quantidade'][$i]));
				$sql = "INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade) ";
				$sql.= "VALUES ( " . $ped_id_bd . " ," . prep_para_bd($_REQUEST['pedprod_quantidade_prod'][$i]) . ", ";
				$sql.= $qtde_bd . ") ";
				$sql.= "ON DUPLICATE KEY UPDATE ";
				$sql.= "pedprod_quantidade = " . $qtde_bd ;
				$res = executa_sql($sql);
			}
			
			// se núcleo do usuário alterou, ou se o tipo de associado dele mudou, modifica no pedido
			$sql = "SELECT usr_nuc, usr_associado, ped_fechado FROM pedidos LEFT JOIN usuarios ON ped_usr = usr_id WHERE ped_id = $ped_id_bd";
			$res = executa_sql($sql);
			$ped_fechado=0;
			if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
			{
				$ped_fechado=$row['ped_fechado'];
				$sql = "UPDATE pedidos SET ";
				$sql.= "ped_nuc = " . prep_para_bd($row['usr_nuc']) . ", ";
				$sql.= "ped_usr_associado = " . prep_para_bd($row['usr_associado']) . ", ";
				$sql.= "ped_dt_atualizacao  = NOW() ";
				$sql.= "WHERE ped_id = $ped_id_bd";
				$res = executa_sql($sql);
			}
			
			if($res) 
			{
				adiciona_mensagem_status(MSG_TIPO_SUCESSO,"Informações do pedido atualizadas com sucesso.");
				if(!$ped_fechado) adiciona_mensagem_status(MSG_TIPO_SUCESSO,"Não se esqueça de enviar o pedido (botão verde abaixo da lista de produtos)");
				$action=ACAO_EXIBIR_LEITURA;
			}
			else adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar salvar o pedido.");				 			
 	
			 			 
		}


		if ($action == ACAO_EXIBIR_LEITURA || $action == ACAO_EXIBIR_EDICAO)  // exibir para visualização, ou exibir para edição
		{
			$sql = "SELECT (cha_dt_max<now()) somente_leitura, usr_nome_curto, ped_usr, usr_associado, ped_usr_associado, usr_nome_completo, usr_contatos, prodt_nome, ";
			$sql.= "nuc_nome_curto, nuc_id, ped_fechado, ped_cha, DATE_FORMAT(ped_dt_atualizacao,'%d/%m/%Y %H:%i') ped_dt_atualizacao, ";
			$sql.= "DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, DATE_FORMAT(cha_dt_max,'%d/%m/%Y %H:%i') cha_dt_max  FROM pedidos ";
			$sql.= "LEFT JOIN usuarios ON ped_usr = usr_id ";	
			$sql.= "LEFT JOIN nucleos ON ped_nuc = nuc_id ";	
			$sql.= "LEFT JOIN chamadas ON ped_cha = cha_id ";				
			$sql.= "LEFT JOIN produtotipos ON cha_prodt = prodt_id ";
			$sql.= "WHERE ped_id = " . prep_para_bd($ped_id) .  "  ";
			
			
 		  $res = executa_sql($sql);
  	      if ($res && $row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		  {		  
			$prodt_nome = $row["prodt_nome"]; 
			$usr_nome_curto = $row["usr_nome_curto"];
			$usr_contatos = $row["usr_contatos"];
			$usr_associado = $row["usr_associado"];
			if($action == ACAO_EXIBIR_EDICAO) $ped_usr_associado = $row["usr_associado"]; 
			else $ped_usr_associado = $row["ped_usr_associado"]; 
			$usr_nome_completo = $row["usr_nome_completo"];
			$nuc_nome_curto = $row["nuc_nome_curto"];						
			$nuc_id = $row["nuc_id"];									
			$ped_fechado = $row["ped_fechado"];	
			$ped_dt_atualizacao = $row["ped_dt_atualizacao"];
			$cha_dt_entrega = $row["cha_dt_entrega"]; 
			$cha_dt_max = $row["cha_dt_max"];
			$ped_cha = $row["ped_cha"]; // serve como parametro  
			$ped_usr = $row["ped_usr"]; // serve como parametro 			
			$ped_somente_leitura = $row["somente_leitura"];
			
		   }
		   else 
		   {			   
			   adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar visualizar o pedido");
		   }
		   
		}	
		
		escreve_mensagem_status();	
		
		
		
?>
 
	<legend>Pedido de <?php echo(($prodt_nome) . " - " . ($cha_dt_entrega)); ?></legend>
      <div class="row">
       	<div class="span5"><strong>Cestante</strong>: <?php echo($usr_nome_curto);?> (<?php echo($usr_contatos ? $usr_contatos : "sem contato informado"); ?>) </div>
        <div class="span4"><strong>Núcleo de Entrega: </strong>    <?php echo($nuc_nome_curto);?></div>
     	<div class="span3"><strong>Associado:</strong> <?php echo($usr_associado==1 ? "Sim" : "Não")?></div>
	    <div class="span6"><strong>Status do Pedido:</strong>    <span class="label <?php echo($ped_fechado ? "label-success" : "label-important") ?>"><?php echo($ped_fechado ? "Enviado" : "Ainda não enviado") ?></span> (última atualização em <?php echo($ped_dt_atualizacao) ?>) </div>
        
     
     </div>
<hr>

<?php 
 if($action==ACAO_EXIBIR_LEITURA)  //visualização somente leitura
 {
 ?>	  

          
<?php

		$sql = "SELECT prod_id, prod_nome, prod_descricao, FORMAT(prod_valor_venda,2) prod_valor_venda, forn_nome_curto, ";
		$sql.= "FORMAT(pedprod_quantidade,1) pedprod_quantidade, chaprod_disponibilidade, ";
		$sql.= "FORMAT (prod_valor_venda_margem,2) prod_valor_venda_margem, prod_unidade ";
		$sql.= "FROM pedidoprodutos ";
		$sql.= "LEFT JOIN produtos ON pedprod_prod = prod_id ";
		$sql.= "LEFT JOIN pedidos ON pedprod_ped = ped_id AND ped_id = " . prep_para_bd($ped_id) . " ";		
		$sql.= "LEFT JOIN chamadas ON ped_cha = cha_id ";
		$sql.= "LEFT JOIN chamadaprodutos ON chaprod_cha = ped_cha AND chaprod_prod = prod_id ";
		$sql.= "LEFT JOIN fornecedores ON prod_forn = forn_id ";
		$sql.= "WHERE pedprod_quantidade > '0.0' ";
		$sql.= "AND pedprod_ped = " . prep_para_bd($ped_id) . " ";
		$sql.= "AND prod_ini_validade<=cha_dt_entrega AND prod_fim_validade>=cha_dt_entrega ";

		$sql.= "ORDER BY forn_nome_curto, prod_nome, prod_unidade ";
		$res = executa_sql($sql);
				
		
		
		if($res)
		{
		   $ultimo_forn = "";
		   $total_associado=0;
		   $total_nao_associado=0;		   
		   ?>		   
		   
           <table class="table table-pedido table-striped table-bordered table-condensed">
				<thead>
                	<tr>
							  <th>Produtor/Produto</th>
							  <th>Unidade</th>
							  <th>Preço para<br>Associado (R$)</th>
							  <th>Preço para<br>Não-Associado (R$)</th>
							  <th>Pedido</th>
							  <th>Total (R$)</th>                                            
					</tr>
                 </thead>           		   
                <tbody>
		   <?php
		   
		   while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		   {
				if($row["forn_nome_curto"]!=$ultimo_forn)
				{					
					$ultimo_forn = $row["forn_nome_curto"];					
					?>
					 
							<tr>
							  <th colspan="6"><?php echo($row["forn_nome_curto"]);?></th>
    						</tr>
			 
					<?php					
				}  
				$total_associado+=$row["prod_valor_venda"] * $row["pedprod_quantidade"] ; 
				$total_nao_associado+=$row["prod_valor_venda_margem"] * $row["pedprod_quantidade"] ;		
						
				?>
				<tr> 
				<td style="text-align:left;"><?php echo($row["prod_nome"]); adiciona_popover_descricao("Descrição", $row["prod_descricao"]); ?> <?php if($row["chaprod_disponibilidade"]==1) echo("&nbsp;&nbsp;<span class='label label-warning'>entrega parcial</span>");?></td>
				<td><?php echo($row["prod_unidade"]);?></td>
				<td><?php echo(formata_numero_de_mysql($row["prod_valor_venda"]) ); ?></td>
				<td><?php echo(formata_numero_de_mysql($row["prod_valor_venda_margem"]) ); ?></td> 
				<td><?php echo(formata_numero_de_mysql($row["pedprod_quantidade"]) ); ?></td>
                    
				<td><?php echo (formata_moeda($row["pedprod_quantidade"] * ( $ped_usr_associado==1 ? $row["prod_valor_venda"] : $row["prod_valor_venda_margem"]) ) ); ?></td>    
				
				</tr>
				 
				<?php
				


		   }
		$texto_indicador_categoria_preco = "<span class='label label-info'>para o seu caso, vale este </span>&nbsp;";
				
		  ?>  
          
           <tr>
          	 <td colspan="5"><div align="right"><?php  echo ($ped_usr_associado==1 ? "" : $texto_indicador_categoria_preco); ?> TOTAL se não associado: </div></td> 
             <td><?php echo(formata_moeda($total_nao_associado));?></td>
           </tr>
           <tr>
          	 <td colspan="5"><div align="right"><?php echo($ped_usr_associado==1 ? $texto_indicador_categoria_preco : "") ; ?> TOTAL se associado: </div></td> 
             <td><?php echo(formata_moeda($total_associado));?></td>
           </tr>
           <tr>
          	 <td colspan="5"><div align="right">taxa de <?php echo(TAXA_ASSOCIADO) * 100; ?>% para associado</div></td> 
             <td><?php echo(formata_moeda($total_associado*TAXA_ASSOCIADO));?></td>
           </tr>    
			<tr>
          	 <th colspan="5"><div align="right">TOTAL FINAL</div></th> 
             <th>R$ <?php echo(formata_moeda($ped_usr_associado==1 ? $total_associado*(1+TAXA_ASSOCIADO) : $total_nao_associado ));?></th>
           </tr>
		   

          
          </tbody>
		   </table>
		   
		   <?php
		} 
   
	  ?> 
      
    <div class="container" align="right">
   	<a name="botao_editar"></a>
	  <?php 
	  if($ped_somente_leitura && (!($_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_ADM] )  ) )
	  {
	 ?>
            <div class="text-error">O prazo limite para edição do pedido foi <?php echo($cha_dt_max);?>.</div>
			<?php		  
		  
	  }
	  else if($ped_fechado)
	  {
			?>
            <div class="span6 text-info">Caso precise alterar seu pedido enviado, clique em editar. Caso queira cancelar o pedido enviado, clique em cancelar. Tais ações estarão disponíveis até <?php echo($cha_dt_max);?></div>
		    <div>    
			<a class="btn btn-danger" href="pedido.php?action=<?php echo(ACAO_CANCELAR_PEDIDO);?>&ped_id=<?php echo($ped_id); ?>"><i class="icon-remove icon-white"></i> cancelar pedido</a>
			&nbsp;&nbsp;

			<a class="btn btn-primary" href="pedido.php?action=<?php echo(ACAO_EXIBIR_EDICAO);?>&ped_id=<?php echo($ped_id); ?>"><i class="icon-edit icon-white"></i> editar</a>
            </div>
			<?php	
		}
		else
		{
			?>

      		<div class="span6 text-warning">Prazo para você enviar seu pedido: <?php echo($cha_dt_max);?>. Mesmo após enviado, você poderá alterar o seu pedido ou mesmo cancelá-lo, desde que dentro do prazo. </div>
		    <div>                            		
			<a class="btn btn-primary" href="pedido.php?action=<?php echo(ACAO_EXIBIR_EDICAO);?>&ped_id=<?php echo($ped_id); ?>"><i class="icon-edit icon-white"></i> editar</a>
			&nbsp;&nbsp;
			<button type="button" class="btn btn-success btn-large btn-enviando" data-loading-text="enviando..." onclick="javascript:location.href='pedido.php?action=<?php echo(ACAO_CONFIRMAR_PEDIDO);?>&ped_id=<?php echo($ped_id); ?>'">
            <i class="icon-ok icon-white"></i> enviar pedido
            </button>
            <!--<i class="icon-ok icon-white"></i> enviar pedido-->
            
            </div>			
			<?php		
		}		
		
	  ?>
		
    </div>
		

<?php 

	
 }
 else  //visualização para edição
 {

?>


<form action="pedido.php" method="post">
<!--       <legend>Produtos</legend>  -->
  <fieldset>
          <input type="hidden" name="ped_id" value="<?php echo($ped_id); ?>" />
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />  
          
<?php
 
		
                    $sql = "SELECT prod_id, prod_nome, prod_descricao,FORMAT(prod_valor_venda,2) prod_valor_venda, forn_nome_curto, ";
					$sql.= "forn_nome_completo, FORMAT(pedprod_quantidade,1) pedprod_quantidade, chaprod_disponibilidade, ";
					$sql.= "FORMAT (prod_valor_venda_margem,2) prod_valor_venda_margem, prod_unidade, prod_multiplo_venda ";
					$sql.= "FROM chamadaprodutos ";

                    $sql.= "LEFT JOIN produtos ON chaprod_prod = prod_id ";
                    $sql.= "LEFT JOIN chamadas ON chaprod_cha = cha_id ";
                    $sql.= "LEFT JOIN fornecedores ON prod_forn = forn_id ";
                    $sql.= "LEFT JOIN pedidos ON cha_id = ped_cha AND ped_id = " . prep_para_bd($ped_id) . " ";
                    $sql.= "LEFT JOIN pedidoprodutos ON pedprod_prod = prod_id AND pedprod_ped = ped_id ";

                    $sql.= "WHERE chaprod_disponibilidade <>'0' ";
					$sql.= "AND chaprod_cha = " . prep_para_bd($ped_cha) . " ";
					$sql.= "AND prod_ini_validade<=cha_dt_entrega AND prod_fim_validade>=cha_dt_entrega ";

                    $sql.= "ORDER BY forn_nome_curto, prod_nome, prod_unidade ";
                    $res = executa_sql($sql);	
														
                    if($res)
                    {
					   $ultimo_forn = "";
					   
					   ?>
					   
                       <table class="table table-pedido table-striped table-bordered table-condensed table-hover">
						<thead>		 
                            <tr>
                              <th>Produtor/Produto</th>
                              <th>Unidade</th>
                              <th>Preço para<br>Associado (R$)</th>
                              <th>Preço para<br>Não-Associado (R$)</th>
                              <th>Pedido</th>
                              <th>Total (R$)</th>                                            
                            </tr>
					   </thead>
                       <tbody>
					   <?php
					   
					   $total_pedido=0;
                       while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
                       {
							if($row["forn_nome_curto"]!=$ultimo_forn)
							{
								
								$ultimo_forn = $row["forn_nome_curto"];
								
								?>
								 
										<tr>
										  <th colspan="6">
										  				<?php echo($row["forn_nome_completo"]);															
										  				?>
                                            
                                              </th>
										</tr>
                         
								<?php
								
							}   
							
							?>
							<tr> 
                            <td style="text-align:left;">
								<?php echo($row["prod_nome"]); 
									  adiciona_popover_descricao("Descrição", $row["prod_descricao"]);
								?>                                  
                                <?php if($row["chaprod_disponibilidade"]==1) echo("&nbsp;&nbsp;<span class='label label-warning'>entrega parcial</span>");?>
                            
                            </td>
                            <td><?php echo($row["prod_unidade"]);?></td>
							<td><?php echo(formata_numero_de_mysql($row["prod_valor_venda"])); ?></td>
							<td><?php echo(formata_numero_de_mysql($row["prod_valor_venda_margem"])); ?></td> 
							<td>
                            <input type="hidden" name="pedprod_quantidade_prod[]" value="<?php echo($row["prod_id"]); ?>"/>
                            <input type="text" class="input-mini  qtdeprod" style="font-size:18px; text-align:center;" value="<?php echo($row["pedprod_quantidade"]?formata_numero_de_mysql($row["pedprod_quantidade"]):"0,0"); ?>" name="pedprod_quantidade[]" id="qtdeprod_<?php echo($row["prod_id"]);?>" />
                            </td>    
                    		<td>
                            <input type="hidden" name="valorprod_<?php echo($row["prod_id"]);?>" value="<?php echo($ped_usr_associado)==1 ? $row["prod_valor_venda"] : $row["prod_valor_venda_margem"] ?>" id="valorprod_<?php echo($row["prod_id"]);?>"/>
                            <input type="hidden" class="multiploprod" name="multiploprod_<?php echo($row["prod_id"]); ?>" value="<?php echo($row["prod_multiplo_venda"])?>" id="multiploprod_<?php echo($row["prod_id"]);?>"/>
                                                        
                            <div class="text-info total_prod" id="totalprod_<?php echo($row["prod_id"]); ?>"><?php echo (formata_moeda($row["pedprod_quantidade"] * ( $ped_usr_associado==1 ? $row["prod_valor_venda"] : $row["prod_valor_venda_margem"]) ) ); ?></div>
                            </td>    
                            
                            </tr>
                            
                             
							<?php
							
							$total_pedido+=$row["pedprod_quantidade"]*($ped_usr_associado==1 ? $row["prod_valor_venda"] : $row["prod_valor_venda_margem"]);

                       }
					?>    
                            <tr>
                             <th colspan="5"><div align="right">TOTAL</div></th>
                             <th id="total_pedido"><?php echo(formata_moeda($total_pedido)); ?></th>
                           </tr>
                       </tbody>
					</table>
					   
					   <?php
                    } 
               
			      ?>    
            
             
    <!--<div class="form-actions">-->
		  <div class="control-group">
            <div class="controls" align="right">
                   <button class="btn" type="button" onclick="javascript:location.href='pedido.php?action=<?php echo(ACAO_EXIBIR_LEITURA); ?>&amp;ped_id=<?php echo($ped_id);?>'"><i class="icon-off"></i> descartar alterações</button>
                   &nbsp;&nbsp;
                   <button class="btn btn-primary btn-large btn-enviando" data-loading-text="salvando pedido..." type="submit"><i class="icon-ok icon-white"></i> salvar pedido</button>
                                     
                                 
            </div>
          </div>
      </fieldset> 
    </form>
    
  
<script type="text/javascript">
	$(function() {
		$(".total_prod").formataValor();
		$(".qtdeprod").bind('keydown', keyCheck);
		$(".qtdeprod").on('blur', calculaTotalPedido);
	}); 
</script>    
 
 
    <?php  

 
	
   }
   

	if($pedido_enviado)
 	{
		$msg_confirmacao="Seu pedido de " . $prodt_nome . " foi confirmado!\n\n";
		
		$msg_confirmacao.="Para visualizar o seu pedido, acesse o sistema online:\n";
		$msg_confirmacao.="" . URL_ABSOLUTA  . "\n\n";
		
		
		$msg_confirmacao.="A entrega será no dia ". $cha_dt_entrega . " no seu respectivo núcleo:\n\n";

		$sql = "SELECT nuc_nome_completo, nuc_entrega_endereco, nuc_entrega_horario, nuc_email FROM nucleos WHERE nuc_id=". prep_para_bd($nuc_id) ;
 		$res = executa_sql($sql);
  	    if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		{		  
			$msg_confirmacao.="Núcleo: " . $row["nuc_nome_completo"] . "\n";
			$msg_confirmacao.="Endereço: " . $row["nuc_entrega_endereco"] . "\n";			
			$msg_confirmacao.="Horário: " . $row["nuc_entrega_horario"] . "\n\n";
//			$msg_confirmacao.="Email: " . $row["nuc_email"] . "\n\n";					
		}
		
		
		$msg_confirmacao.="Vale Lembrar:\n";
		$msg_confirmacao.="Se as encomendas não forem buscadas, o consumidor pagará pelos produtos encomendados. ";
		$msg_confirmacao.="Haverá tentativa de repasse a outros cestantes.\n\n";
		
		$msg_confirmacao.="Por favor leve suas bolsas, sacolas de pano, caixas de papelão, caixas de ovos e dinheiro ";
		$msg_confirmacao.="trocado ou cheque para facilitar na entrega dos produtos.\n\n";
		
		$msg_confirmacao.="Um abraço!\nRede Ecológica";
		
		envia_email_cestante($ped_usr,"Confirmação do Pedido de " . $prodt_nome . " - " . $cha_dt_entrega,"",$msg_confirmacao);
	}
	


   footer();
?>
