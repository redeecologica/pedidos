<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_ENTREGA]  || $_SESSION[PAP_RESP_FINANCAS]);  
  top();
?>

<?php

		$action =  request_get("action",-1);
		if($action == -1) redireciona(PAGINAPRINCIPAL);
		
		$cha_id =  request_get("cha_id","");
		if($cha_id=="") redireciona(PAGINAPRINCIPAL);

		$nuc_id =  request_get("nuc_id","");
		if($nuc_id=="") redireciona(PAGINAPRINCIPAL);

					
		$sql = " SELECT DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, cha_prodt, prodt_nome FROM chamadas ";
		$sql.= " LEFT JOIN produtotipos ON cha_prodt = prodt_id ";
		$sql.= " WHERE cha_id=". prep_para_bd($cha_id) . " ";  
		$res = executa_sql($sql);
		if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		{				  
			$cha_dt_entrega = $row["cha_dt_entrega"];
			$cha_prodt = $row["cha_prodt"];
			$prodt_nome = $row["prodt_nome"];
		}
		
		$sql = " SELECT nuc_id, nuc_nome_completo FROM nucleos ";
		$sql.= " WHERE nuc_id=". prep_para_bd($nuc_id) . " ";  
		$res = executa_sql($sql);
		if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		{				  
			$nuc_id = $row["nuc_id"];
			$nuc_nome_completo = $row["nuc_nome_completo"];				
		}
		
				
		if ($action == ACAO_SALVAR) // salvar inclusão cestante
		{
			$usr_id=request_get("usr_id",-1);
			echo("usr_id = " . $usr_id );
			if($usr_id==-1)
			{
				adiciona_mensagem_status(MSG_TIPO_ERRO,"Selecionar o cestante a ser adicionado na entrega da chamada de " . $prodt_nome . " - " . $cha_dt_entrega . ".");
			}
			else 
			{
				$sucesso=false;
				
				$sql = "SELECT ped_id FROM pedidos WHERE ";
				$sql.= " ped_cha= " . prep_para_bd($cha_id);
				$sql.= "AND ped_usr= " . prep_para_bd($usr_id);							
				$res = executa_sql($sql);
				if ($res && $row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
				{
					$ped_id = $row["ped_id"];
					$sql="DELETE FROM pedidoprodutos ";
					$sql.=" WHERE pedprod_ped = ". prep_para_bd($ped_id);
					$res_del = executa_sql($sql);
										
					$sql="UPDATE pedidos SET ped_fechado = '1' ";
					$sql.=" WHERE ped_id = ". prep_para_bd($ped_id);
					$res_update = executa_sql($sql);		
						
					if($res_update && $res_del) $sucesso=true;
					
				}
				else
				{
					$sql = "INSERT INTO pedidos (ped_cha, ped_usr, ped_fechado, ped_nuc, ped_usr_associado) ";
					$sql.= " SELECT " . prep_para_bd($cha_id) . " ," . prep_para_bd($usr_id) . ", '1', ";
					$sql.=  prep_para_bd($nuc_id) . " , usr_associado FROM usuarios WHERE usr_id = " . prep_para_bd($usr_id);
					$res = executa_sql($sql);		
	
					if($res) $sucesso=true;
				}				
				  				
				if($sucesso) 
				{
					$action=ACAO_EXIBIR_LEITURA;
					adiciona_mensagem_status(MSG_TIPO_SUCESSO,"Cestante incluso com sucesso na entrega da chamada de "  . $prodt_nome . " - " . $cha_dt_entrega .  ".");	
				}
				else
				{
					adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar incluir cestante na entrega da chamada de "  . $prodt_nome . " - " . $cha_dt_entrega .  ".");								
				}

				if(isset($_POST['back_url']))
				{
					redireciona($_POST['back_url']);
				}										
			}			
			escreve_mensagem_status();					
		}
		
		if ($action == ACAO_EXIBIR_LEITURA || $action == ACAO_EXIBIR_EDICAO )  // exibir para visualização, ou exibir para edição
		{
			// capturar informação de estoque
		}	


	
	
	
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
 
     <strong>Inclusão de cestante que não fez pedido. Entrega <?php echo($prodt_nome . " - " . $cha_dt_entrega); ?></strong>
  </div>

  
  <?php   

 if($action==ACAO_EXIBIR_LEITURA)  //visualização somente leitura
 {
	
 }
 else  //visualização para edição
 {

?>

   <form class="form-horizontal"  method="post" name="frm_entrega">

        <fieldset> 
    
	<div class="panel-body">

          <input type="hidden" name="cha_id" value="<?php echo($cha_id); ?>" />
          <input type="hidden" name="nuc_id" value="<?php echo($nuc_id); ?>" />          
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />
    
		<div class="form-group">
                      <label class="control-label col-sm-2" for="usr_nuc">Núcleo: </label>
                      <div class="col-sm-3">                                       
                        <?php echo($nuc_nome_completo); ?>           
                    </div>   
        </div>
        
   		<div class="form-group">
    
                      <label class="control-label col-sm-2" for="usr_id">Cestante: </label>
                      <div class="col-sm-5">                                       
                        <select name="usr_id" id="usr_id" class="form-control">
                            <option value="-1">SELECIONAR</option>
                            <?php
								$sql="SELECT usr_nome_completo, usr_nome_curto, usr_id ";
								$sql.="FROM usuarios ";
								$sql.=" WHERE usr_archive='0' AND usr_nuc= " . prep_para_bd($nuc_id) . " ";
								$sql.=" AND usr_id NOT IN ";
								$sql.=" (SELECT ped_usr FROM pedidos WHERE ped_fechado = '1' AND ped_cha=" . prep_para_bd($cha_id) . ") ";
								$sql.="ORDER BY usr_nome_completo ";								
								$res = executa_sql($sql);
															
                                if($res)
                                {
                                  while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
                                  {
                                     echo("<option value='" . $row['usr_id'] . "'");
                                     echo (">" . $row['usr_nome_curto'] . " (" .  $row['usr_nome_completo'] . ")" . "</option>");
                                  }
                                }
                            ?>                        
                        </select>                
                    </div>   
                                         

      
             <div align="right">
                <button type="submit"  class="btn btn-primary btn-enviando" data-loading-text="incluindo cestante...">
            <i class="glyphicon glyphicon-ok glyphicon-white"></i> incluir cestante</button>
                   &nbsp;&nbsp;
                   <button class="btn btn-default" type="button" onclick="javascript:location.href=document.referrer;"><i class="glyphicon glyphicon-off"></i> descartar alterações</button>
                    </div>    
                    
            </div>
    </div>
    
          <input type="hidden" name="back_url" id="back_url" value="<?php echo(isset($_POST['back_url']) ? $_POST['back_url'] : ""); ?>" />    
	          <?php if( ! isset($_POST['back_url'])) echo("<script>document.getElementById(\"back_url\").value = document.referrer;</script>"); ?>
            


           </div> 
      </fieldset> 
    </form>
 

    
    <?php   
	
   }

   footer();
?>
