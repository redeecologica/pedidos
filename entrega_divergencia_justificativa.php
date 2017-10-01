<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_ENTREGA]  || $_SESSION[PAP_RESP_FINANCAS]); 
  top();
  
    
	$action = request_get("action",-1);
	if($action==-1) redireciona(PAGINAPRINCIPAL);

	$cha_id =  request_get("cha_id","");
	if($cha_id=="") redireciona(PAGINAPRINCIPAL);
			 
	$prod_id =  request_get("prod_id","");
	if($prod_id=="") redireciona(PAGINAPRINCIPAL);
	
	$nuc_id =  request_get("nuc_id","");
	if($nuc_id=="") redireciona(PAGINAPRINCIPAL);				 
		
	

	$sql="SELECT DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, ((cha_dt_prazo_contabil is null) OR (cha_dt_prazo_contabil > now() ) ) as cha_dentro_prazo, ";
	$sql.=" IFNULL(SUM(pedprod_entregue),0) as total_entregue, nuc_nome_curto, forn_nome_curto, prod_nome, prod_unidade, ";
	$sql.=" prod_id, IFNULL(SUM(pedprod_quantidade),0) as total_pedido, chaprod_disponibilidade, IFNULL(dist_quantidade_recebido,0) as total_recebido, ";
	$sql.=" dist_just_dif_entrega, prodt_nome ";
	$sql.="FROM chamadaprodutos ";
	$sql.="LEFT JOIN chamadas on cha_id = chaprod_cha ";
	$sql.="LEFT JOIN produtotipos on prodt_id = cha_prodt ";			
	$sql.="LEFT JOIN produtos on prod_id = chaprod_prod ";
	$sql.="LEFT JOIN pedidos ON ped_cha = cha_id ";
	$sql.="LEFT JOIN nucleos ON ped_nuc = nuc_id ";
	$sql.="LEFT JOIN pedidoprodutos ON pedprod_ped = ped_id AND pedprod_prod=chaprod_prod ";
	$sql.="LEFT JOIN fornecedores on prod_forn = forn_id ";
	$sql.="LEFT JOIN distribuicao ON dist_cha = chaprod_cha AND dist_prod = chaprod_prod AND ped_nuc = dist_nuc ";
	$sql.="WHERE ped_cha= " . prep_para_bd($cha_id) . " ";
	$sql.="AND ped_fechado = '1' ";	
	$sql.="AND ped_nuc = " . prep_para_bd($nuc_id) . " ";	
	$sql.="AND prod_id = " . prep_para_bd($prod_id) . " ";				
	$sql.="AND chaprod_disponibilidade <> '0' ";
	$sql.="AND prod_ini_validade<=cha_dt_entrega AND prod_fim_validade>=cha_dt_entrega  ";
	$sql.="GROUP BY ped_nuc, forn_id, prod_id ";
	
	$res = executa_sql($sql); 
	
	if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
	{				  
		$cha_dt_entrega = $row["cha_dt_entrega"];
		$prodt_nome = $row["prodt_nome"];
		$nuc_nome_curto = $row["nuc_nome_curto"];	
		$cha_dentro_prazo = $row["cha_dentro_prazo"];
		$forn_nome_curto = $row["forn_nome_curto"];
		$prod_nome = $row["prod_nome"];
		$prod_unidade = $row["prod_unidade"];	
		$total_entregue = $row["total_entregue"];
		$total_recebido = $row["total_recebido"];
		$dist_just_dif_entrega = $row["dist_just_dif_entrega"];
		
	}
			
		  

					  
			
	if ($action == ACAO_SALVAR) // salvar formulário preenchido
	{
		$sql = "INSERT INTO distribuicao (dist_cha, dist_prod, dist_nuc, dist_just_dif_entrega ) ";
		$sql.= "VALUES ( " . prep_para_bd($cha_id) . " ," . prep_para_bd($prod_id) . ", ";
		$sql.=  prep_para_bd($nuc_id) . ", " .   prep_para_bd($_REQUEST["dist_just_dif_entrega"])   .  ") ";
		$sql.= "ON DUPLICATE KEY UPDATE ";
		$sql.= "dist_just_dif_entrega = " . prep_para_bd($_REQUEST["dist_just_dif_entrega"]) ;
		$res = executa_sql($sql);	

		if($res) 
		{							
			$action=ACAO_EXIBIR_LEITURA; 
			adiciona_mensagem_status(MSG_TIPO_SUCESSO,"A justificativa foi salva com sucesso.");

			if(isset($_POST['back_url']))
			{
				redireciona($_POST['back_url']);
			}
		}
		else
		{
			adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar salvar informações de justificativa.");								
		}
		escreve_mensagem_status();
	
	}
	
	if ($action == ACAO_EXIBIR_LEITURA || $action == ACAO_EXIBIR_EDICAO )  // exibir para visualização, ou exibir para edição
	{

	}	
	
?>

 <ul class="nav nav-tabs">
  <li><a href="entregas.php">Entregas</a></li>
  <li><a href="entrega_nucleos_consolidado.php"><i class="glyphicon glyphicon-road"></i> Recebido pelo Núcleo</a></li>
  <li><a href="entrega_cestantes_consolidado.php"><i class="glyphicon glyphicon-grain"></i> Entregue aos Cestantes</a></li>  
  <li class="active"><a href="entrega_divergencias.php"><i class="glyphicon glyphicon-eye-open"></i> Divergências</a></li>    
</ul>

<br>


    
   
  <?php   

 if($action==ACAO_EXIBIR_LEITURA)  //visualização somente leitura
 {
	//sem função no momento
 ?>	  
 		
          
<?php 
	
 }
 else  //visualização para edição
 {

?>

 <div class="panel panel-default">
  <div class="panel-heading">
		 <strong>Justificativa Divergência - Entrega no Núcleo <?php if($prodt_nome) echo(" - " . $prodt_nome . " - " . $cha_dt_entrega); ?></strong></strong>
  </div>
 <div class="panel-body">
	
    <table class="table table-striped table-bordered table-condensed table-hover">
    	<tr>
        	<th>Núcleo</th>
            <th>Fornecedor</th>
            <th>Produto</th>
            <th>Unidade</th>
            <th>Recebido</th>
            <th>Entregue</th>  
			<th>Recebido e Não Entregue</th>                        
        </tr>        
        <tr>
        	<td><?php echo($nuc_nome_curto); ?></td>
        	<td><?php echo($forn_nome_curto); ?></td>
        	<td><?php echo($prod_nome); ?></td>
        	<td><?php echo($prod_unidade); ?></td>
        	<td><?php echo_digitos_significativos($total_recebido); ?></td>
        	<td><?php echo_digitos_significativos($total_entregue); ?></td>  
        	<td class="alert alert-<?php echo($dist_just_dif_entrega ? "info" : "danger"); ?>"><?php echo_digitos_significativos($total_recebido - $total_entregue); ?></td>                        
                                                            
        </tr>
        
    </table>  
    
           
        
    <form class="form-horizontal"  method="post">
        <fieldset> 
        
          <input type="hidden" name="cha_id" value="<?php echo($cha_id); ?>" />
          <input type="hidden" name="nuc_id" value="<?php echo($nuc_id); ?>" />
          <input type="hidden" name="prod_id" value="<?php echo($prod_id); ?>" />                         
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />
    
          <input type="hidden" name="back_url" id="back_url" value="<?php echo(isset($_POST['back_url']) ? $_POST['back_url'] : ""); ?>" />    
	          <?php if( ! isset($_POST['back_url'])) echo("<script>document.getElementById(\"back_url\").value = document.referrer;</script>"); ?>
            

        
        
             <div class="form-group">
                   <label class="control-label" for="dist_just_dif_entrega">Justificativa da Divergência:</label>
                   <div>   
                  <input type="text" class="data form-control" id="dist_just_dif_entrega" name="dist_just_dif_entrega" value="<?php echo($dist_just_dif_entrega); ?>"/>
                   </div>
                  
            </div>                     

           <div align="right">

                
                   <button type="submit"  class="btn btn-primary btn-enviando" data-loading-text="salvando...">
            <i class="glyphicon glyphicon-ok glyphicon-white"></i> salvar alterações</button>
                   
                   &nbsp;&nbsp;
                   
                   
                   
                   <button class="btn btn-default" type="button" onclick="javascript:location.href=document.referrer;"><i class="glyphicon glyphicon-off"></i> descartar alterações</button>
                                 
               </div>

      </fieldset> 
    </form>
 
 </div>
 </div>




<script language="javascript">
	 $("input:text:visible:first").focus();
</script>

    
    <?php   
	
   }

   footer();
?>
