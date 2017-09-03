<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_ENTREGA]  || $_SESSION[PAP_RESP_FINANCAS]);  
  
  top();
  
 $cha_id=request_get("cha_id",-1); 

                      
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
  <li class="active"><a href="#"><i class="glyphicon glyphicon-road"></i> Recebido pelo Núcleo</a></li>
  <li><a href="entrega_cestantes_consolidado.php"><i class="glyphicon glyphicon-grain"></i> Entregue aos Cestantes</a></li>  
</ul>
<br>
  
  <div class="panel panel-default">
  <div class="panel-heading">
     <strong>Consolidado - Recebido pelo Núcleo <?php if($prodt_nome) echo(" - " . $prodt_nome . " - " . $cha_dt_entrega); ?></strong>

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
         </fieldset>
    </form>
    
    </div>
    
   </div> 
    
    
      	
  
    

<?php 

if($cha_id!=-1)
{
	$sql="SELECT nuc_nome_completo, nuc_id, ";
	$sql.=" SUM(prod_valor_compra * pedprod_quantidade) AS valor_pedido ";
	$sql.="FROM chamadaprodutos ";
	$sql.="LEFT JOIN chamadas on cha_id = chaprod_cha LEFT JOIN produtos on prod_id = chaprod_prod ";
	$sql.="LEFT JOIN pedidos ON ped_cha = cha_id LEFT JOIN usuarios on ped_usr = usr_id ";
	$sql.="LEFT JOIN nucleos ON ped_nuc = nuc_id ";
	$sql.="LEFT JOIN pedidoprodutos ON pedprod_ped = ped_id AND pedprod_prod=chaprod_prod ";
	$sql.="WHERE ped_cha = " . prep_para_bd($cha_id);
	$sql.=" AND ped_fechado = '1' AND chaprod_disponibilidade <> '0' ";
	$sql.=" AND prod_ini_validade<=cha_dt_entrega AND prod_fim_validade>=cha_dt_entrega ";
	$sql.="GROUP BY nuc_id ";
	$sql.="ORDER BY nuc_nome_completo";
	$res = executa_sql($sql); 

	$sql="SELECT nuc_id, ";
	$sql.=" SUM(prod_valor_compra * dist_quantidade) AS valor_distribuido, ";		
	$sql.=" SUM(prod_valor_compra * dist_quantidade_recebido) AS valor_recebido ";	
	$sql.="FROM chamadaprodutos ";
	$sql.="LEFT JOIN chamadas on cha_id = chaprod_cha ";
	$sql.="LEFT JOIN produtos on prod_id = chaprod_prod ";
	$sql.="LEFT JOIN distribuicao ON dist_cha = chaprod_cha AND dist_prod = chaprod_prod ";	
	$sql.="LEFT JOIN nucleos ON dist_nuc = nuc_id ";	
	$sql.="WHERE chaprod_cha = " . prep_para_bd($cha_id);
	$sql.=" AND chaprod_disponibilidade <> '0' ";
	$sql.=" AND prod_ini_validade<=cha_dt_entrega AND prod_fim_validade>=cha_dt_entrega ";
	$sql.="GROUP BY nuc_id ";
	$rs_distribuicao = executa_sql($sql); 	
	$distribuicao = array();
	if($rs_distribuicao)
	{
		while($row = mysqli_fetch_array($rs_distribuicao,MYSQLI_ASSOC))
		{
			$distribuicao[$row["nuc_id"]]["distribuido"] = $row["valor_distribuido"];
			$distribuicao[$row["nuc_id"]]["recebido"] = $row["valor_recebido"];			
		}
	}	
	
	
	if($res) 
	{	
		?>		
        
			 <input class="btn btn-success" type="button" value="selecionar tabela para copiar"  onclick="selectElementContents( document.getElementById('selectable') );"> 
             <p />

        
                <table id="selectable" class="table table-striped table-bordered table-condensed table-hover">
                <thead>
                    <tr>
                        <th colspan="9">Consolidado Entrega no Núcleo <?php echo(" - " . $prodt_nome . " " .  $cha_dt_entrega); ?> </th>
                    </tr>
					<tr>
                    	<th>Núcleo</th>
                        <th>Pedido (R$)</th>
                        <th>Distribuído pelo <br>Mutirão (R$)</th>
                        <th>Recebido (R$)</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                
                <tbody>
                <?php

			   $somatorio_pedido=0;
			   $somatorio_ditribuido=0;
			   $somatorio_recebido=0;
		   			   			   
               while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
               {
			   
				   $valor_distribuido=isset($distribuicao[$row["nuc_id"]]["distribuido"]) ? $distribuicao[$row["nuc_id"]]["distribuido"] :0;
				   $valor_recebido=isset($distribuicao[$row["nuc_id"]]["recebido"]) ? $distribuicao[$row["nuc_id"]]["recebido"] : 0;				   
			   
				   $somatorio_pedido+=$row["valor_pedido"];
				   
				   $somatorio_ditribuido+=$valor_distribuido;
				   $somatorio_recebido+=$valor_recebido;
              
                    ?>
                    <tr>                              
                    <td><?php echo($row["nuc_nome_completo"]);?></td>
                    <td><?php echo(formata_moeda($row["valor_pedido"])); ?></td>
                    <td><?php echo(formata_moeda($valor_distribuido)); ?></td>
                    <td><?php echo(formata_moeda($valor_recebido)); ?></td> 
                  
                    <td>
                        <a class="btn btn-default <?php echo($valor_recebido>0? "" : "btn-danger" ); ?>" href="entrega_nucleo.php?action=<?php echo(ACAO_EXIBIR_EDICAO . "&cha_id=" . $cha_id .  "&nuc_id=" . $row["nuc_id"]);?>"><i class="glyphicon glyphicon-pencil glyphicon-white"></i> atualizar</a>
                    </td>
                                        
                    </tr>
                     
                    <?php

               }
          ?>             
          <tr>          
            <th>TOTAL</th>
            <th><?php echo(formata_moeda($somatorio_pedido));?></th>
            <th><?php echo(formata_moeda($somatorio_ditribuido));?></th>
            <th><?php echo(formata_moeda($somatorio_recebido));?></th>
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