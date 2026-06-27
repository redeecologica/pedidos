<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_ENTREGA]  || $_SESSION[PAP_RESP_FINANCAS]);    
  
  top();
  
 $cha_id=request_get("cha_id",-1); 
 if($cha_id==-1)
 {
	 if(isset($_SESSION['cha_id_pref']))
	 {
		$cha_id=$_SESSION['cha_id_pref'];	 
	 }
 }
 $_SESSION['cha_id_pref']=$cha_id;
 
  
 $nuc_id=request_get("nuc_id",$_SESSION['usr.nuc']); 
 if($nuc_id==-1) $nuc_id=$_SESSION['usr.nuc'];
 
                      
 $sql = "SELECT prodt_nome, DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, cha_taxa_percentual ";
 $sql.= "FROM chamadas LEFT JOIN produtotipos ON prodt_id = cha_prodt ";
 $sql.= "WHERE cha_id = " . prep_para_bd($cha_id);


 $res = executa_sql($sql);
 $row = mysqli_fetch_array($res,MYSQLI_ASSOC);

 if(!$res)
 {
	 redireciona(PAGINAPRINCIPAL);
 }

$prodt_nome = $row["prodt_nome"];
$cha_dt_entrega = $row["cha_dt_entrega"];
$cha_taxa_percentual = $row["cha_taxa_percentual"];


?>

 <ul class="nav nav-tabs">
  <li><a href="entregas.php">Entregas</a></li>
  <li><a href="entrega_nucleos_consolidado.php"><i class="glyphicon glyphicon-road"></i> Recebido pelo Núcleo</a></li>
  <li><a href="entrega_cestantes_consolidado.php"><i class="glyphicon glyphicon-grain"></i> Entregue aos Cestantes</a></li>  
  <li class="active"><a href="#"><i class="glyphicon glyphicon-eye-open"></i> Divergências</a></li>    
</ul>

<br>


  <div class="panel panel-default">
  <div class="panel-heading">
     <strong>Divergências - Entrega no Núcleo <?php if($prodt_nome) echo(" - " . $prodt_nome . " - " . $cha_dt_entrega); ?></strong>

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
		</div>                 

		&nbsp;&nbsp;
          <div class="form-group">          
  				<label for="nuc_id">Núcleo: </label>            
                <select name="nuc_id" id="nuc_id" onchange="javascript:frm_filtro.submit();" class="form-control">
                    <option value="-1" <?php echo(($nuc_id==-1)?" selected" : ""); ?> >SELECIONAR</option>
                    <option value="-1">-------------</option>    
                 	<option value="0" <?php echo(($nuc_id==0)?" selected" : ""); ?> >[Todos]</option>                                               
                    <?php
                        
                        $sql = "SELECT nuc_id, nuc_nome_curto, nuc_archive ";
                        $sql.= "FROM nucleos WHERE nuc_id IN ";
						$sql.= " (SELECT chanuc_nuc FROM chamadanucleos WHERE chanuc_cha = " . prep_para_bd($cha_id) . ") ";						
                        $sql.= "ORDER BY nuc_archive, nuc_nome_curto ";
                        $res = executa_sql($sql);
                        if($res)
                        {
						  $arquivados=0;
                          while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
                          {
							 if(!$arquivados)
							 {
								 if($row["nuc_archive"]==1) 
								 {
									 echo("<option value='-1'>-------------</option>");									 
									 $arquivados=1;
								 }
							 }
                             echo("<option value='" . $row['nuc_id'] . "'");
                             if($row['nuc_id']==$nuc_id) echo(" selected");
                             echo (">" . $row['nuc_nome_curto'] . "</option>");
                          }
                        }
                    ?>                        
                </select>                           
       
       
           </div>
           
         </fieldset>
    </form>
    
    </div>
    
   </div> 
    
    
      	
  
    

<?php 

if($nuc_id!=-1 && $cha_id!=-1)
{

	$sql="SELECT IFNULL(SUM(pedprod_entregue),0) as total_entregue, nuc_nome_curto, forn_nome_curto, prod_nome, prod_valor_venda, prod_valor_venda_margem, prod_unidade, ";
	$sql.=" prod_id, IFNULL(SUM(pedprod_quantidade),0) as total_pedido, chaprod_disponibilidade, IFNULL(dist_quantidade_recebido,0) as total_recebido, ";
	$sql.=" dist_just_dif_entrega, prod_id, nuc_id ";
	$sql.="FROM chamadaprodutos ";
	$sql.="LEFT JOIN chamadas on cha_id = chaprod_cha ";
	$sql.="LEFT JOIN produtos on prod_id = chaprod_prod ";
	$sql.="LEFT JOIN pedidos ON ped_cha = cha_id ";
	$sql.="LEFT JOIN nucleos ON ped_nuc = nuc_id ";
	$sql.="LEFT JOIN pedidoprodutos ON pedprod_ped = ped_id AND pedprod_prod=chaprod_prod ";
	$sql.="LEFT JOIN fornecedores on prod_forn = forn_id ";
	$sql.="LEFT JOIN distribuicao ON dist_cha = chaprod_cha AND dist_prod = chaprod_prod AND ped_nuc = dist_nuc ";
	$sql.="WHERE ped_cha= " . prep_para_bd($cha_id) . " ";
	$sql.="AND ped_fechado = '1' ";	
	if($nuc_id>0) $sql.="AND ped_nuc = " . prep_para_bd($nuc_id) . " ";	
	$sql.="AND chaprod_disponibilidade <> '0' ";
	$sql.="AND prod_ini_validade<=cha_dt_entrega AND prod_fim_validade>=cha_dt_entrega  ";
	$sql.="GROUP BY ped_nuc, forn_id, prod_id ";
	$sql.=" HAVING IFNULL(SUM(pedprod_entregue),0) - total_recebido <> '0'   ";	
	$sql.="ORDER BY nuc_nome_curto, forn_nome_curto , prod_nome, prod_unidade ";
	$res = executa_sql($sql); 


	if($res &&  mysqli_num_rows($res)>0) 
	{	
		?>	     	
	
	 <input class="btn btn-success" type="button" value="selecionar tabela para copiar"  onclick="selectElementContents( document.getElementById('selectable') );">
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    <?php
	}
     ?>
     
     <a target="_blank" class="btn btn-default" href="rel_entrega_cestantes_nucleo.php?cha_id=<?php echo($cha_id);?>&nuc_id=<?php echo($nuc_id);?>"><i class="glyphicon glyphicon-new-window"></i> ver em nova janela relatório final entrega</a>
        <p />
	
	<?php
	
	if($res &&  mysqli_num_rows($res)>0) 
	{	
		?>	      
			
                <table id="selectable" class="table table-striped table-bordered table-condensed table-hover">
                <thead>
                    <tr>
                        <th colspan="10">Divergências Entrega - <?php echo($prodt_nome . " " .  $cha_dt_entrega); ?> </th>
                    </tr>
					<tr>
                    	<th>Núcleo</th>
                        <th>Produtor</th>
                        <th>Produto</th>
                        <th>Unidade</th>                        
                        <th>Pedido</th>
                        <th>Recebido</th>
                        <th>Entregue</th>   
                        <th>Recebido e Não Entregue</th>
                        <th>Justificativa Diferença</th>
                        <th>Atualizar</th>
                    </tr>
                </thead>
                
                <tbody>
                <?php

		   			   			   
               while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
               {
                    ?>
                    <tr>                              
                    <td><?php echo($row["nuc_nome_curto"]);?></td>
                    <td><?php echo($row["forn_nome_curto"]); ?></td> 
                    <td><?php echo($row["prod_nome"]); ?></td>
                    <td><?php echo($row["prod_unidade"]); ?></td>
                    <td><?php echo_digitos_significativos($row["total_pedido"]); ?></td>
                    <td><?php echo_digitos_significativos($row["total_recebido"]); ?></td>
                    <td><?php echo_digitos_significativos($row["total_entregue"]); ?></td>
                    <td class="alert alert-<?php echo($row["dist_just_dif_entrega"] ? "info" : "danger"); ?>"><?php echo_digitos_significativos($row["total_recebido"] - $row["total_entregue"]); ?></td>
                    <td><?php echo($row["dist_just_dif_entrega"]); ?></td>
                    <td><a href="entrega_divergencia_justificativa.php?action=<?php echo(ACAO_EXIBIR_EDICAO . "&cha_id=" . $cha_id . "&prod_id=" . $row["prod_id"]  . "&nuc_id=" .  $row["nuc_id"]  . "&back_url=entrega_divergencias.php" ); ?>" class="btn btn-default"><i class="glyphicon glyphicon-edit" title="atualizar justificativa"></i></a></td>                    
                    </tr>
                     
                    <?php

               }
          ?>             
          </tbody></table>
       

        <?php 
	}
	else // nao possui registros
	{
		?>
		
        <div class="alert alert-success" role="alert">
        	<i class="glyphicon glyphicon-thumbs-up"></i> Parabéns! Não foram identificadas divergências entre o que foi recebido pelo núcleo e o que foi entregue aos cestantes.
        </div>
        
		<?php
		
	}

}
	
	

 ?>             
                         
          </div>
       

 <?php 


footer();

?>