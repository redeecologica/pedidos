<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_ENTREGA]  || $_SESSION[PAP_RESP_FINANCAS]);  
  top();
?>

<?php

	
		$action = request_get("action",-1);
		if($action==-1) redireciona(PAGINAPRINCIPAL);

		$cha_id =  request_get("cha_id","");
		if($cha_id=="") redireciona(PAGINAPRINCIPAL);
				 
        $ped_id = request_get("ped_id","");
		if($ped_id=="") redireciona(PAGINAPRINCIPAL);	
			
		
		if ($action<>-1) // por enquanto, vai precisar para todos os casos
		{
		  $sql = " SELECT DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, ((cha_dt_prazo_contabil is null) OR (cha_dt_prazo_contabil > now() ) ) as cha_dentro_prazo, ";
		  $sql.= " cha_prodt, prodt_nome, nuc_id, nuc_nome_curto, usr_nome_completo FROM chamadas ";
		  $sql.= " LEFT JOIN produtotipos ON cha_prodt = prodt_id ";
		  $sql.= " LEFT JOIN pedidos ON ped_cha = cha_id ";
		  $sql.= " LEFT JOIN nucleos ON ped_nuc = nuc_id ";		
		  $sql.= " LEFT JOIN usuarios ON ped_usr = usr_id ";				    
		  $sql.= " WHERE cha_id=". prep_para_bd($cha_id) . " ";
		  $sql.= " AND ped_id=". prep_para_bd($ped_id) . " ";
		  
 		  $res = executa_sql($sql);
  	      if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		  {				  
			$cha_dt_entrega = $row["cha_dt_entrega"];
			$cha_prodt = $row["cha_prodt"];
			$prodt_nome = $row["prodt_nome"];
			$nuc_id = $row["nuc_id"];
			$nuc_nome_curto = $row["nuc_nome_curto"];
			$usr_nome_completo = $row["usr_nome_completo"];			
			$cha_dentro_prazo = $row["cha_dentro_prazo"];
		  }
		}	
		
		if( ($action == ACAO_SALVAR || $action == ACAO_EXIBIR_EDICAO) && (!$cha_dentro_prazo))
		{			
			adiciona_mensagem_status(MSG_TIPO_AVISO,"Não posui permissão para edição.");
			redireciona("entregas.php");
		}		
	
				
		if ($action == ACAO_SALVAR) // salvar formulário preenchido
		{

			// salva dados entrega
			$n = isset($_REQUEST['pedprod_entregue']) ? sizeof($_REQUEST['pedprod_entregue']) : 0;
			$cha_id_bd = prep_para_bd($cha_id);
			$ped_id_bd = prep_para_bd($ped_id);

			for($i=0;$i<$n;$i++)
			{
				
				$qtde_bd = $_REQUEST['pedprod_entregue'][$i]=="" ? 'NULL' : prep_para_bd(formata_numero_para_mysql($_REQUEST['pedprod_entregue'][$i]));
				$sql = "INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_entregue) ";
				$sql.= "VALUES ( " . $ped_id_bd . " ," . prep_para_bd($_REQUEST['prod_id'][$i]) . ", ";
				$sql.= $qtde_bd . ") ";
				$sql.= "ON DUPLICATE KEY UPDATE ";
				$sql.= "pedprod_entregue = " . $qtde_bd ;
				$res = executa_sql($sql);				

			}


			if(true) //todo: verificar erro em cada update do for
			{
								
				$action=ACAO_EXIBIR_LEITURA;
				adiciona_mensagem_status(MSG_TIPO_SUCESSO,"As informações de entrega relacionada à chamada de " . $cha_dt_entrega . " foram salvas com sucesso.");

				if(isset($_POST['back_url']))
				{
					redireciona($_POST['back_url']);
				}
				

			}
			else
			{
				adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar salvar informações de entrega relacionada à chamada de " . $cha_dt_entrega . ".");								
			}
			escreve_mensagem_status();
		
		}
		
		if ($action == ACAO_EXIBIR_LEITURA || $action == ACAO_EXIBIR_EDICAO )  // exibir para visualização, ou exibir para edição
		{
			// capturar informação de estoque
		}	


	
	
	
?>

<?php 

	$sql="SELECT pedprod_entregue, forn_nome_curto, forn_nome_completo, usr_nome_curto, ped_usr_associado, prod_nome, prod_valor_venda, prod_valor_venda_margem, prod_id,  ";
	$sql.="prod_unidade, IFNULL(FORMAT(pedprod_quantidade,ceiling(log10(0.0001 + cast(reverse(cast(truncate((prod_multiplo_venda - truncate(prod_multiplo_venda,0)) *1000,0) as CHAR)) as UNSIGNED)))) , FORMAT(pedprod_quantidade,0)) as pedprod_quantidade, (pedprod_entregue - pedprod_quantidade) AS pedprod_extra, chaprod_disponibilidade ";
	$sql.="FROM chamadaprodutos ";
	$sql.="LEFT JOIN chamadas on cha_id = chaprod_cha ";
	$sql.="LEFT JOIN produtos on prod_id = chaprod_prod ";
	$sql.="LEFT JOIN pedidos ON ped_cha = cha_id ";
	$sql.="LEFT JOIN usuarios on ped_usr = usr_id ";
	$sql.="LEFT JOIN pedidoprodutos ON pedprod_ped = ped_id AND pedprod_prod=chaprod_prod ";
	$sql.="LEFT JOIN fornecedores on prod_forn = forn_id ";
	$sql.="WHERE ped_cha= " . prep_para_bd($cha_id) . " ";
	$sql.="AND ped_fechado = '1' ";	
	$sql.="AND ped_id = " . prep_para_bd($ped_id) . " ";	
	$sql.="AND chaprod_disponibilidade <> '0' ";
	$sql.="AND prod_ini_validade<=cha_dt_entrega AND prod_fim_validade>=cha_dt_entrega  ";
	$sql.="ORDER BY forn_nome_curto, prod_nome, prod_unidade, usr_nome_curto ";
	$res = executa_sql($sql);

	
  ?>	
 
<ul class="nav nav-tabs">
  <li><a href="entregas.php">Entregas</a></li>
  <li><a href="entrega_nucleos_consolidado.php"><i class="glyphicon glyphicon-road"></i> Recebido pelo Núcleo</a></li>
  <li class="active"><a href="entrega_cestantes_consolidado.php"><i class="glyphicon glyphicon-grain"></i> Entregue aos Cestantes</a></li>  
  <li><a href="entrega_divergencias.php"><i class="glyphicon glyphicon-eye-open"></i> Divergências</a></li>    
</ul>


<br>

  
  <div class="panel panel-default">
  <div class="panel-heading">
 
     <strong>Informações de entrega relacionada à chamada de <?php echo($prodt_nome . " - " . $cha_dt_entrega); ?></strong>

  </div>

  
  <?php   

 if($action==ACAO_EXIBIR_LEITURA)  //visualização somente leitura
 {
 ?>	  
 
  
 <div class="panel-body">

  <button class="btn btn-default" type="button" onclick="javascript:location.href=document.referrer;"><i class="glyphicon glyphicon-arrow-left"></i> voltar</button>

 
                <form class="form-inline" method="get" name="frm_filtro" id="frm_filtro">
                    
                     <fieldset>
                           <input type="hidden" name="action" value="<?php echo(ACAO_EXIBIR_LEITURA); ?>" /> 
                    
						   
                     </fieldset>
                </form>						
                
             </div>  
						
                <table class="table table-striped table-bordered table-condensed table-hover">
                <thead>
                    <tr>
                        <th colspan="5">Relatório do que foi distribuído/entregue para cestante <?php echo($usr_nome_completo); ?></th>
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
                                    <th>Entregue</th>
                                    <th>Extra</th>
                                </tr>
                        <?php
                        
                    }   
                    
                    ?>
                    <tr>                              
                    <td><?php echo($row["prod_nome"]);?></td>
                    <td><?php echo($row["prod_unidade"]); ?></td>  
                    <td>                            
                        <?php 
                            echo_digitos_significativos($row["pedprod_quantidade"]);
                        ?> 
                     </td>                                                    				
                    <td>                            
                        <?php 
                            echo_digitos_significativos($row["pedprod_entregue"]);                         
                        ?> 
                     </td> 
                     
                    <td>                            
                        <?php 
                            echo_digitos_significativos($row["pedprod_extra"]);
                         
                        ?> 
                     </td>                      
                         
                    </tr>
                     
                    <?php

               }
          ?>             
                         
          </tbody></table>
       

		  </div>
                

    
<?php 


	
 }
 else  //visualização para edição
 {

?>

   <form class="form-horizontal"  method="post" name="frm_entrega">
    
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
          <input type="hidden" name="ped_id" value="<?php echo($ped_id); ?>" />          
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />
    
          <input type="hidden" name="back_url" id="back_url" value="<?php echo(isset($_POST['back_url']) ? $_POST['back_url'] : ""); ?>" />    
	          <?php if( ! isset($_POST['back_url'])) echo("<script>document.getElementById(\"back_url\").value = document.referrer;</script>"); ?>
            

                
                 
                 <table class='table table-striped table-bordered table-condensed table-hover'>               
                 
                  <thead>
                        	<tr>
                            	<th colspan="4">Registro do que foi entregue para cestante <?php echo($usr_nome_completo); ?></th>
                            </tr>
                    </thead>
                    <tbody>
              
                        <tr>
                            <td>&nbsp;</td><td>&nbsp;</td>
                            <td colspan="2">
                            <button type="button" class="btn btn-info" name="copia_produtos_entrega" id="copia_produtos_entrega" onclick="javascript:replicaDados('replica-origem','replica-destino');">
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
											<th class="coluna-quantidade">Entregue</th>
										</tr>
								<?php
								
							}   
							
							?>
							<tr> 
                            <input type="hidden" name="prod_id[]" value="<?php echo($row["prod_id"]); ?>"/>
                             
                            <td><?php echo($row["prod_nome"]);?></td>
                            <td><?php echo($row["prod_unidade"]); ?></td>
                            <td>    
                               <input type="hidden" name="pedprod_quantidade[]" class="replica-origem" value="<?php  echo_digitos_significativos($row["pedprod_quantidade"],"");?>">                        
                          		<?php 
									echo_digitos_significativos($row["pedprod_quantidade"],"");
								?> 
                             </td>                              
                            <td>
                            <input type="text" class="replica-destino form-control propaga-colar-entrega" style="font-size:18px; text-align:center;" value="<?php  if($row["pedprod_entregue"]) echo_digitos_significativos($row["pedprod_entregue"],"0");?>" name="pedprod_entregue[]"/>
                            </td>
                            
                                                
                            </tr>
                             
							<?php

                       }
                    } 
               
			      ?>             
                       </tbody></table>
            
           </div> 
           
           <div align="right">

                
                   <button type="submit"  class="btn btn-primary btn-enviando" data-loading-text="salvando entrega...">
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
