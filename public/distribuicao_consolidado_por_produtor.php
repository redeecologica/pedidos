<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_MUTIRAO]);  
  
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
 

                      
 $sql = "SELECT prodt_nome, DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, cha_taxa_percentual, ((cha_dt_prazo_contabil is null) OR (cha_dt_prazo_contabil > now() ) ) as cha_dentro_prazo, date_format(cha_dt_prazo_contabil,'%d/%m/%Y %H:%i') cha_dt_prazo_contabil ";
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
$cha_dt_prazo_contabil = $row["cha_dt_prazo_contabil"];
$cha_dentro_prazo = $row["cha_dentro_prazo"];

?>
<ul class="nav nav-tabs">
  <li><a href="mutirao.php">Mutirão</a></li>
  <li><a href="estoque_pre.php"><i class="glyphicon glyphicon-bed"></i> Estoque Pré-Mutirão</a></li>
  <li><a href="recebimento.php"><i class="glyphicon glyphicon-road"></i> Recebimento</a></li>
  <li class="active"><a href="distribuicao_consolidado_por_produtor.php"><i class="glyphicon glyphicon-fullscreen"></i> Distribuição</a></li>  
  <li><a href="estoque_pos.php"><i class="glyphicon glyphicon-bed"></i> Estoque Pós-Mutirão</a></li>  
  <li><a href="mutirao_divergencias.php"><i class="glyphicon glyphicon-eye-open"></i> Divergências</a></li>
</ul>

<br>

<ul class="nav nav-tabs">
  <li class="active"><a href="distribuicao_consolidado_por_produtor.php">Distribuição por Produto</a></li>
  <li><a href="distribuicao_consolidado.php">Distribuição por Núcleo</a></li>
</ul>

<br>
  
  <div class="panel panel-default">
  <div class="panel-heading">
     <strong>Consolidado - Distribuído para os Núcleos por Produtor <?php if($prodt_nome) echo(" - " . $prodt_nome . " - " . $cha_dt_entrega); ?></strong>

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
    
    
    </div>    
    
    
   </div> 
    
    
      	
  
    

<?php 

if($cha_id!=-1)
{
	$sql="SELECT forn_nome_curto, forn_id, ";
	$sql.=" SUM(prod_valor_compra * pedprod_quantidade) AS valor_pedido ";
	$sql.="FROM chamadaprodutos ";
	$sql.="LEFT JOIN chamadas on cha_id = chaprod_cha LEFT JOIN produtos on prod_id = chaprod_prod ";
	$sql.="LEFT JOIN pedidos ON ped_cha = cha_id LEFT JOIN usuarios on ped_usr = usr_id ";
	$sql.="LEFT JOIN pedidoprodutos ON pedprod_ped = ped_id AND pedprod_prod=chaprod_prod ";
	$sql.="LEFT JOIN fornecedores ON forn_id = prod_forn  ";		
	$sql.="WHERE ped_cha = " . prep_para_bd($cha_id);
	$sql.=" AND ped_fechado = '1' AND chaprod_disponibilidade <> '0' ";
	$sql.=" AND prod_ini_validade<=cha_dt_entrega AND prod_fim_validade>=cha_dt_entrega ";
	$sql.="GROUP BY forn_id ";
	$sql.="ORDER BY forn_nome_curto ";
	$res = executa_sql($sql); 

	$sql="SELECT forn_id, ";
	$sql.=" SUM(prod_valor_compra * dist_quantidade) AS valor_distribuido, ";		
	$sql.=" SUM(prod_valor_compra * dist_quantidade_recebido) AS valor_recebido ";	
	$sql.="FROM chamadaprodutos ";
	$sql.="LEFT JOIN chamadas on cha_id = chaprod_cha ";
	$sql.="LEFT JOIN produtos on prod_id = chaprod_prod ";
	$sql.="LEFT JOIN distribuicao ON dist_cha = chaprod_cha AND dist_prod = chaprod_prod ";	
	$sql.="LEFT JOIN fornecedores ON prod_forn = forn_id ";	
	$sql.="WHERE chaprod_cha = " . prep_para_bd($cha_id);
	$sql.=" AND chaprod_disponibilidade <> '0' ";
	$sql.=" AND prod_ini_validade<=cha_dt_entrega AND prod_fim_validade>=cha_dt_entrega ";
	$sql.="GROUP BY forn_id ";

	$rs_distribuicao = executa_sql($sql); 	
	$distribuicao = array();
	if($rs_distribuicao)
	{
		while($row = mysqli_fetch_array($rs_distribuicao,MYSQLI_ASSOC))
		{
			$distribuicao[$row["forn_id"]]["distribuido"] = $row["valor_distribuido"];
			$distribuicao[$row["forn_id"]]["recebido"] = $row["valor_recebido"];			
		}
	}	
	
	
	if($res) 
	{	
		?>		
               
                <table class="table table-striped table-bordered table-condensed table-hover">
                <thead>
                    <tr>
                        <th colspan="4">Consolidado Distribuído para os Núcleos por Produtor <?php echo(" - " . $prodt_nome . " " .  $cha_dt_entrega); ?> </th>
                    </tr>
					<tr>
                    	<th>Produtor</th>
                        <th>Pedido pelos núcleos (R$)</th>
                        <th>Distribuído pelo <br>Mutirão (R$)</th>
                        <!--<th>Recebido pelo <br>Núcleo (R$)</th>-->
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
			   
				   $valor_distribuido=isset($distribuicao[$row["forn_id"]]["distribuido"]) ? $distribuicao[$row["forn_id"]]["distribuido"] :0;
				   $valor_recebido=isset($distribuicao[$row["forn_id"]]["recebido"]) ? $distribuicao[$row["forn_id"]]["recebido"] : 0;				   
			   
				   $somatorio_pedido+=$row["valor_pedido"];
				   
				   $somatorio_ditribuido+=$valor_distribuido;
				   $somatorio_recebido+=$valor_recebido;
              
                    ?>
                    <tr>                              
                    <td><?php echo($row["forn_nome_curto"]);?></td>
                    <td><?php echo(formata_moeda($row["valor_pedido"])); ?></td>
                    <td><?php echo(formata_moeda($valor_distribuido)); ?></td>
                    <!--<td><?php echo(formata_moeda($valor_recebido)); ?></td>-->
                  
                    <td>
                    	<?php 
						if($cha_dentro_prazo)
						{
						?>
                        <!--
                            <a class="btn btn-default <?php echo($valor_distribuido>0? "" : "btn-danger" ); ?>" href="distribuicao_por_produtor.php?action=<?php echo(ACAO_EXIBIR_EDICAO . "&cha_id=" . $cha_id .  "&forn_id=" . $row["forn_id"]);?>"><i class="glyphicon glyphicon-pencil glyphicon-white"></i> atualizar</a>                            
                        -->
						<?php
						}
						?>
                         <a class="btn btn-default <?php echo($valor_distribuido>0? "" : "btn-danger" ); ?>" href="distribuicao_por_produtor.php?action=<?php echo(ACAO_EXIBIR_LEITURA . "&cha_id=" . $cha_id .  "&forn_id=" . $row["forn_id"]);?>"><i class="glyphicon glyphicon-pencil glyphicon-white"></i> ver/atualizar</a>  
                    </td>
                                        
                    </tr>
                     
                    <?php

               }
          ?>             
          <tr>          
            <th>TOTAL</th>
            <th><?php echo(formata_moeda($somatorio_pedido));?></th>
            <th><?php echo(formata_moeda($somatorio_ditribuido));?></th>
            <!--<th><?php echo(formata_moeda($somatorio_recebido));?></th>-->
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