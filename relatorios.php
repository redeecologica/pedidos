<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO] || $_SESSION[PAP_RESP_MUTIRAO] || $_SESSION[PAP_ACOMPANHA_PRODUTOR] || $_SESSION[PAP_ACOMPANHA_RELATORIOS]  || $_SESSION[PAP_RESP_ENTREGA]  || $_SESSION[PAP_RESP_FINANCAS]);  
  top();
?>



<div class="panel panel-default">
  <div class="panel-heading">
     <strong>Relatórios</strong>
  </div>
 <div class="panel-body">
  
 
 <form class="form-inline" action="relatorios.php" method="post" name="frm_filtro" id="frm_filtro">

	<?php  
  		$cha_ano = request_get("cha_ano",-1) ;
		$cha_maximo = request_get("cha_maximo",10) ;
		$cha_prodt = request_get("cha_prodt",-1) ;
	?>
     <fieldset>     
     	<div class="form-group">
  				<label for="cha_prodt">Tipo Chamada: </label>            
                 <select name="cha_prodt" id="cha_prodt" onchange="javascript:frm_filtro.submit();" class="form-control">
                        <option value="-1" <?php echo(($cha_prodt==-1)?" selected" : ""); ?> >TODOS</option>
						<?php
                            
                            $sql = "SELECT prodt_id, prodt_nome ";
                            $sql.= "FROM produtotipos ";
                            $sql.= "ORDER BY prodt_nome ";
                            $res = executa_sql($sql);
                            if($res)
                            {
                              while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
                              {
                                 echo("<option value='" . $row['prodt_id'] . "'");
                                 if($row['prodt_id']==$cha_prodt) echo(" selected");
                                 echo (">" . $row['prodt_nome'] . "</option>");
                              }
                            }
                        ?>  
                 </select>    
         </div>        
                 &nbsp;&nbsp;&nbsp;
        <div class="form-group">            
  				<label for="cha_ano">Ano da Entrega: </label>            
                <select name="cha_ano" id="cha_ano" onchange="javascript:frm_filtro.submit();" class="form-control">
                    <option value="-1" <?php echo( ($cha_ano==-1)?" selected" : ""); ?> >TODOS</option>          
                    <?php
               
                        $sql = "SELECT distinct year(cha_dt_entrega) as ano ";
                        $sql.= "FROM chamadas ";
                        $sql.= "GROUP BY ano ORDER BY ano desc ";
                        $res = executa_sql($sql);
                        if($res)
                        {
                          while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
                          {
	                          echo("<option value='" . $row['ano'] . "'");
                             if($row['ano']==$cha_ano)
							 {
								  echo(" selected");
							 }
                             echo (">" . $row['ano'] . "</option>");
                          }
                        }
                    ?>                        
                </select>                           
                    
         </div> 
         
                        &nbsp;&nbsp;&nbsp;
        <div class="form-group">            
  				<label for="cha_maximo">Exibir no Máximo: </label>            
                <select name="cha_maximo" id="cha_maximo" onchange="javascript:frm_filtro.submit();" class="form-control">
                 	 <option value="10" <?php if($cha_maximo==10) echo(" selected"); ?>>10</option>
                     <option value="25" <?php if($cha_maximo==25) echo(" selected"); ?>>25</option>
                     <option value="50" <?php if($cha_maximo==50) echo(" selected"); ?>>50</option>
                     <option value="100" <?php if($cha_maximo==100) echo(" selected"); ?>>100</option>                           
                                        
                </select>                           
                    
         </div> 
                                          
                    
      </fieldset>
  </form>
 </div>
 
 
 
 
	<table class="table table-striped table-bordered">
		<thead>
			<tr>
				<th>#</th>
				<th>Tipo</th>                
                <th>Data Entrega</th>
        		<th>Ao Produtor</th>
				<th>Ao Mutirão</th>
				<th>Ao Resp. Entrega</th>
				<th>Entrega Final</th>
				<th>Recebido do Produtor</th>
				<th>Previsão Pagamento ao Produtor</th>
                <th>Funcionalidades</th>
			</tr>
		</thead>
		<tbody>
        
        
<?php 
                      
    $sql = "SELECT cha_id, prodt_nome, prodt_mutirao, cha_dt_entrega cha_dt_entrega_original, DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega ";
    $sql.= "FROM chamadas LEFT JOIN produtotipos ON prodt_id = cha_prodt ";
	$sql.= "WHERE 1 ";	
	if($cha_prodt!=-1)	$sql.= " AND cha_prodt = " . prep_para_bd($cha_prodt) .  " ";
	if($cha_ano!=-1)	$sql.= " AND year(cha_dt_entrega) = " . prep_para_bd($cha_ano) .  " ";		
    $sql.= "ORDER BY cha_dt_entrega_original DESC, prodt_nome, cha_dt_max DESC LIMIT " . intval($cha_maximo);
    $res = executa_sql($sql);
    if($res)
    {
	 	  
	  $contador=1;
      while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
      {				
	  	 ?>		
         	<tr>
            <td><?php echo($contador++);?></td>
            <td><?php echo($row['prodt_nome']);?></td>                
            <td><?php echo($row['cha_dt_entrega']);?></td>
			<?php
              if($row['prodt_mutirao']== 0)
              {
              ?>	  
                <td>
                	<a href="rel_pedido_por_produtor.php?cha_id=<?php echo($row['cha_id']);?>">demanda da rede</a>
                </td>
            	<td>n/a</td>
            
             <?php
			  }
              else  
              {
              ?>  
                <td>
                	<a href="rel_pedido_por_produtor_considera_estoque.php?cha_id=<?php echo($row['cha_id']);?>">demanda da rede</a> (considera estoque)
                </td>
            	<td><a href="rel_pedido_pre_mutirao.php?cha_id=<?php echo($row['cha_id']);?>">distribuição núcleos</a> </td>           
              
              <?php
              } // end if prodt_mutirao==0
              ?>          

        		<td><a href="rel_pedido_por_cestante.php?cha_id=<?php echo($row['cha_id']);?>">pedidos de cada núcleo</a></td>    
                
                

                <td><a href="rel_entrega_cestantes_nucleo.php?cha_id=<?php echo($row['cha_id']);?>">entrega aos cestantes</a></td>   
                <td><a href="rel_recebimento.php?cha_id=<?php echo($row['cha_id']);?>">entregue pelo produtor</a></td>
                <td><a href="rel_previsao_pagamento.php?cha_id=<?php echo($row['cha_id']);?>">consolidado</a> ou <a href="rel_previsao_pagamento_detalhado.php?cha_id=<?php echo($row['cha_id']);?>">detalhado</a> </td>
                
                <td>
                  <a href="entrega_nucleos_consolidado.php?cha_id=<?php echo($row['cha_id']);?>">módulo entrega</a><br>
                  <a href="financas_prazo.php?action=1&cha_id=<?php echo($row['cha_id']);?>">prazo finanças</a>
                </td>             

                                

                
               </tr>  
						
         <?php
      }
    }

?>
</tbody>
</table>

</div>

<?php 
 
	footer();
?>