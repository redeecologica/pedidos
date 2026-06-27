<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_MUTIRAO]);
  top();


	$action = request_get("action",-1);
	if($action==-1) $action=ACAO_EXIBIR_LEITURA;

	 $cha_id=request_get("cha_id",-1);
	 if($cha_id==-1)
	 {
		 if(isset($_SESSION['cha_id_pref']))
		 {
			$cha_id=$_SESSION['cha_id_pref'];	 
		 }
	 }
	 $_SESSION['cha_id_pref']=$cha_id;	



	  $sql = "SELECT prodt_nome, DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, cha_taxa_percentual, ((cha_dt_prazo_contabil is null) OR (cha_dt_prazo_contabil > now() ) ) as cha_dentro_prazo, date_format(cha_dt_prazo_contabil,'%d/%m/%Y %H:%i') cha_dt_prazo_contabil ";
	  $sql.= "FROM chamadas LEFT JOIN produtotipos ON prodt_id = cha_prodt ";
	  $sql.= "WHERE cha_id = " . prep_para_bd($cha_id);
	  
		 
				  
	  $res = executa_sql($sql);
	  if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
	  {				  
		$prodt_nome = $row["prodt_nome"];
		$cha_dt_entrega = $row["cha_dt_entrega"];
		$cha_taxa_percentual = $row["cha_taxa_percentual"];
		$cha_dt_prazo_contabil = $row["cha_dt_prazo_contabil"];
		$cha_dentro_prazo = $row["cha_dentro_prazo"];			 
	   }
	

?>


<ul class="nav nav-tabs">
  <li><a href="mutirao.php">Mutirão</a></li>
  <li><a href="estoque_pre.php"><i class="glyphicon glyphicon-bed"></i> Estoque Pré-Mutirão</a></li>
  <li><a href="recebimento.php"><i class="glyphicon glyphicon-road"></i> Recebimento</a></li>
  <li><a href="distribuicao_consolidado_por_produtor.php"><i class="glyphicon glyphicon-fullscreen"></i> Distribuição</a></li> 
  <li><a href="estoque_pos.php"><i class="glyphicon glyphicon-bed"></i> Estoque Pós-Mutirão</a></li> 
  <li class="active"><a href="#"><i class="glyphicon glyphicon-eye-open"></i> Divergências</a></li>
</ul>

<br>

<div class="panel panel-default">
  <div class="panel-heading">
     <strong>Divergência</strong>

  </div>
  
  <div class="panel-body">
 
 <form class="form-inline"  method="get" name="frm_filtro" id="frm_filtro">
	<?php  

	?>
     <fieldset>

     	<div class="form-group">
  				<label for="cha_id">Chamada: </label>            
                 <select name="cha_id" id="cha_id" onchange="javascript:frm_filtro.submit();" class="form-control">
                 	<option value="-1">SELECIONE</option>
                    <?php
                        
                       $sql = "SELECT cha_id, prodt_nome, cha_dt_entrega cha_dt_entrega_original, DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega ";
                        $sql.= "FROM chamadas LEFT JOIN produtotipos ON prodt_id = cha_prodt ";
						$sql.= "WHERE prodt_mutirao = '1' ";
                        $sql.= "ORDER BY cha_dt_entrega_original DESC LIMIT 10";
						
                        $res = executa_sql($sql);
                        if($res)
                        {
						  $achou=false;
						  while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
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
                 <?php 
				   if($cha_id!=-1)
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
                 
                 
    
    	</div>                 
         </fieldset>
    </form>
    
    
    <br>
     <a target="_blank" class="btn btn-default" href="rel_pedido_pre_mutirao.php?cha_id=<?php echo($cha_id);?>"><i class="glyphicon glyphicon-new-window"></i> ver em nova janela relatório final mutirão</a>
    
    <br> <br> <br> <br>     
	Lista com divergências em desenvolvimento.    
   <br>

<br>
   </div> 


<?php 
 
	footer();
?>