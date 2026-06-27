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
 
                      
 $sql = "SELECT prodt_nome, nuc_nome_curto, DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, cha_taxa_percentual,((cha_dt_prazo_contabil is null) OR (cha_dt_prazo_contabil > now() ) ) as cha_dentro_prazo, date_format(cha_dt_prazo_contabil,'%d/%m/%Y %H:%i') cha_dt_prazo_contabil ";
 $sql.= "FROM chamadas LEFT JOIN produtotipos ON prodt_id = cha_prodt ";
 $sql.= "LEFT JOIN nucleos on nuc_id = " . prep_para_bd($nuc_id) . " " ;
 $sql.= "WHERE cha_id = " . prep_para_bd($cha_id);


 $res = executa_sql($sql);
 $row = mysqli_fetch_array($res,MYSQLI_ASSOC);

 if(!$res)
 {
	 redireciona(PAGINAPRINCIPAL);
 }

$prodt_nome = $row["prodt_nome"];
$cha_dt_entrega = $row["cha_dt_entrega"];
$nuc_nome_curto = $row["nuc_nome_curto"];
$cha_taxa_percentual = $row["cha_taxa_percentual"];
$cha_dt_prazo_contabil = $row["cha_dt_prazo_contabil"];
$cha_dentro_prazo = $row["cha_dentro_prazo"];


?>

<ul class="nav nav-tabs">
  <li><a href="entregas.php">Entregas</a></li>
  <li><a href="entrega_nucleos_consolidado.php"><i class="glyphicon glyphicon-road"></i> Recebido pelo Núcleo</a></li>
  <li class="active"><a href="#"><i class="glyphicon glyphicon-grain"></i> Entregue aos Cestantes</a></li>  
  <li><a href="entrega_divergencias.php"><i class="glyphicon glyphicon-eye-open"></i> Divergências</a></li>    
</ul>
<br>


  <div class="panel panel-default">
  <div class="panel-heading">
     <strong>Consolidado - Entregue aos Cestantes no Núcleo <?php if($prodt_nome) echo(" - " . $prodt_nome . " - " . $cha_dt_entrega); ?></strong>

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
    
    </div>
    
   </div> 
    
    
      	
  
    

<?php 

if($nuc_id!=-1 && $cha_id!=-1)
{
	$sql="SELECT usr_nome_completo, ped_usr_associado, ped_id, ";
	$sql.="IF(ped_usr_associado='0', SUM(prod_valor_venda_margem * pedprod_quantidade),SUM(prod_valor_venda * pedprod_quantidade)) AS valor_pedido, ";
	$sql.="IF(ped_usr_associado='0', SUM(prod_valor_venda_margem * pedprod_entregue),SUM(prod_valor_venda * pedprod_entregue)) AS valor_entregue, ";
	$sql.=" SUM(pedprod_entregue) AS total_itens_entregues, ";
	$sql.="IF(ped_usr_associado='0', SUM(prod_valor_venda_margem * (IFNULL(pedprod_entregue,0) - IFNULL(pedprod_quantidade,0))),SUM(prod_valor_venda * (IFNULL(pedprod_entregue,0) - IFNULL(pedprod_quantidade,0))) ) AS valor_extra ";
	$sql.="FROM chamadaprodutos ";
	$sql.="LEFT JOIN chamadas on cha_id = chaprod_cha LEFT JOIN produtos on prod_id = chaprod_prod ";
	$sql.="LEFT JOIN pedidos ON ped_cha = cha_id LEFT JOIN usuarios on ped_usr = usr_id ";
	$sql.="LEFT JOIN pedidoprodutos ON pedprod_ped = ped_id AND pedprod_prod=chaprod_prod ";
	
	$sql.="WHERE ped_cha = " . prep_para_bd($cha_id) . " AND ped_nuc = " . prep_para_bd($nuc_id) . " ";
	$sql.=" AND ped_fechado = '1' AND chaprod_disponibilidade <> '0' ";
	$sql.=" AND prod_ini_validade<=cha_dt_entrega AND prod_fim_validade>=cha_dt_entrega ";
	$sql.="GROUP BY usr_id, ped_id ";
	$sql.="ORDER BY usr_nome_completo";
	
	$res = executa_sql($sql); 
	
	
	if($res) 
	{	
		?>		
        
			 <input class="btn btn-success" type="button" value="selecionar tabela para copiar"  onclick="selectElementContents( document.getElementById('selectable') );"> 
            &nbsp;&nbsp;&nbsp;&nbsp;

             <a class="btn btn-default" href="entrega_cestante_incluir.php?action=<?php echo(ACAO_EXIBIR_EDICAO); ?>&cha_id=<?php echo($cha_id); ?>&nuc_id=<?php echo($nuc_id); ?>">
               <i class="glyphicon glyphicon-plus"></i> incluir na entrega cestante que não fez pedido
             </a>
							  
                                        
            
             <p />

        
                <table id="selectable" class="table table-striped table-bordered table-condensed table-hover">
                <thead>
                    <tr>
                        <th colspan="9">Consolidado Entrega Cestantes - Núcleo <?php echo($nuc_nome_curto); ?> - <?php echo($prodt_nome . " " .  $cha_dt_entrega); ?> </th>
                    </tr>
					<tr>
                    	<th>Cestante</th>
                        <th>Associado</th>
                        <th>Pedido (R$)</th>
                        <th>Extra (R$)</th>
                        <th>Entregue (R$)</th>
                        <th>Taxa <?php echo(formata_numero_de_mysql($cha_taxa_percentual*100)); ?>% (R$)</th>   
                        <th>Total (R$)</th>                                                
                        <th>Ações</th>
                    </tr>
                </thead>
                
                <tbody>
                <?php

			   $somatorio_pedido=0;
			   $somatorio_extra=0;
			   $somatorio_entregue=0;
			   $somatorio_taxa=0;
		   			   			   
               while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
               {
				   $somatorio_pedido+=$row["valor_pedido"];
				   $somatorio_extra+=$row["valor_extra"];
				   $somatorio_entregue+=$row["valor_entregue"];
				   $somatorio_taxa+=$row["ped_usr_associado"]=='0' ? $row["valor_entregue"] : $row["valor_entregue"]*$cha_taxa_percentual;
              
                    ?>
                    <tr>                              
                    <td><?php echo($row["usr_nome_completo"]);?></td>
                    <td><?php echo($row["ped_usr_associado"]=='0'?"Não":"Sim"); ?></td> 
                    <td><?php echo(formata_moeda($row["valor_pedido"])); ?></td>
                    <td><?php echo(formata_moeda($row["valor_extra"])); ?></td>
                    <td><?php echo(formata_moeda($row["valor_entregue"])); ?></td> 
                    <td><?php echo(formata_moeda($row["ped_usr_associado"]=='0' ? $row["valor_entregue"] : $row["valor_entregue"]*$cha_taxa_percentual)); ?></td>
                    <td><?php echo(formata_moeda($row["ped_usr_associado"]=='0'  ? $row["valor_entregue"] : $row["valor_entregue"]*(1+$cha_taxa_percentual))); ?></td>                   
                    <!--                      
                    <td>
                        <a class="btn btn-default" href="entrega_cestante.php?action=<?php echo(ACAO_EXIBIR_LEITURA . "&cha_id=" . $cha_id .  "&ped_id=" . $row["ped_id"]);?>"><i class="glyphicon glyphicon-search glyphicon-white"></i> visualizar</a>
                        
                    </td>
                    -->
                    <td nowrap="nowrap">
                       

<?php 
						if($cha_dentro_prazo)
						{
						?>
                        <a class="btn btn-default <?php echo( ($row["valor_entregue"]>0  || $row["valor_extra"]>0 || $row["total_itens_entregues"]>0) ? "": "btn-danger"  ); ?>" href="entrega_cestante.php?action=<?php echo(ACAO_EXIBIR_EDICAO . "&cha_id=" . $cha_id .  "&ped_id=" . $row["ped_id"]);?>"><i class="glyphicon glyphicon-pencil glyphicon-white"></i> atualizar</a>
 
						<?php
						}
						?>                      
                         <a class="btn btn-default" href="entrega_cestante.php?action=<?php echo(ACAO_EXIBIR_LEITURA . "&cha_id=" . $cha_id .  "&ped_id=" . $row["ped_id"]);?>"><i class="glyphicon glyphicon-search glyphicon-white"></i> ver</a>  
                         

                    </td>
                                        
                    </tr>
                     
                    <?php

               }
          ?>             
          <tr>          
            <th>TOTAL</th>
            <th>&nbsp;</th>
            <th><?php echo(formata_moeda($somatorio_pedido));?></th>
            <th><?php echo(formata_moeda($somatorio_extra));?></th>
            <th><?php echo(formata_moeda($somatorio_entregue));?></th>
            <th><?php echo(formata_moeda($somatorio_taxa));?></th>
            <th><?php echo(formata_moeda($somatorio_entregue + $somatorio_taxa));?></th>                                     
            <th>&nbsp;</th>
                        
          </tr>
                         
          </tbody></table>
       

        <?php 
	}

}
	
	

 ?>             
                         
          </div>
       

 <?php 


footer();

?>