<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO] || $_SESSION[PAP_RESP_ENTREGA]  || $_SESSION[PAP_RESP_FINANCAS]);  
  top();
 

 $dt_ini=request_get("dt_ini",date('01/m/Y')); 
 $dt_fim=request_get("dt_fim",date('d/m/Y', strtotime('last day of this month'))); 
 


?>

  
  <div class="panel panel-default">
  <div class="panel-heading">
     <strong>Quadro dos Núcleos</strong>

  </div>

  	  
 <div class="panel-body">
 
 <form class="form-inline"  method="get" name="frm_filtro" id="frm_filtro">
	<?php  

	?>
     <fieldset>

              
		&nbsp;&nbsp;

     	<div class="form-group">
  			   
            	<label for="dt_ini" class="control-label">Período:  De </label> 
                 <input type="text" name="dt_ini" id="dt_ini"  class="data form-control" value="<?php echo($dt_ini);?>">
				 <label for="dt_fim" class="control-label">Até </label>       
                 <input type="text" name="dt_fim" id="dt_fim"  class="data form-control" value="<?php echo($dt_fim);?>">                 
		</div>                 


          
          
        <div class="form-group">           	
                 <input type="submit" name="btn_consultar" id="btn_consultar"  class="form-control btn btn-default" value="consultar">
		</div>      
        
           
         </fieldset>
    </form>
    
    </div>
    
   </div> 
    
    
      	
  
    

<?php 


	
	if(0)
	{
		
	}
	else
	{



		$sql="SELECT cha_id, cha_dt_entrega cha_dt_entrega_original, DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, prodt_nome ";
		$sql.="FROM chamadas ";
		$sql.="LEFT JOIN produtotipos ON prodt_id = cha_prodt ";		
		$sql.="WHERE ";
		$sql.=" cha_dt_entrega>= " . prep_para_bd(formata_data_para_mysql($dt_ini)  . " 00:00:00");
		$sql.=" AND cha_dt_entrega<= " . prep_para_bd(formata_data_para_mysql($dt_fim) . " 23:59:59");			
		$sql.=" ORDER BY cha_dt_entrega_original, prodt_nome ";

		$res_chamadas_validas = executa_sql($sql); 
		$chamadas_validas = array();	
		$chamadas_validas_id = array();
		if($res_chamadas_validas)
		{
			while($row = mysqli_fetch_array($res_chamadas_validas,MYSQLI_ASSOC))
			{	
				$chamadas_validas[] = $row;
				$chamadas_validas_id[] = $row["cha_id"];
			}
		}
			

		$sql="SELECT nuc_id, nuc_nome_curto, nuc_nome_completo, nuc_archive ";
		$sql.="FROM nucleos ";
		$sql.="WHERE nuc_id IN (";
		$sql.="   SELECT chanuc_nuc FROM chamadanucleos ";
		$sql.="   WHERE chanuc_cha IN ("  . implode(",",$chamadas_validas_id) . ") ";		
		$sql.="   GROUP BY chanuc_nuc ";						
		$sql.=") ORDER BY nuc_nome_curto ";		

		$res_nucleos = executa_sql($sql); 


		


		/*primeiro: pegar lista de chamadas pertientes*/
		$sql="SELECT nuc_nome_completo, nuc_id, cha_id, cha_taxa_percentual, ";
		$sql.="FORMAT(IF(ped_usr_associado='0', SUM(prod_valor_venda_margem * pedprod_quantidade),SUM(prod_valor_venda * pedprod_quantidade)),2) AS valor_pedido, ";
		$sql.="FORMAT(IF(ped_usr_associado='0', SUM(prod_valor_venda_margem * pedprod_entregue),SUM(prod_valor_venda * pedprod_entregue)),2) AS valor_entregue, ";
		$sql.="FORMAT(IF(ped_usr_associado='0', SUM(prod_valor_venda_margem * (pedprod_entregue - pedprod_quantidade)),SUM(prod_valor_venda * (pedprod_entregue - pedprod_quantidade)) ),2) AS valor_extra, ";
		$sql.="FORMAT(IF(ped_usr_associado='0', '0', SUM(ROUND(prod_valor_venda * pedprod_entregue * cha_taxa_percentual,2))),2) AS valor_taxa ";		
		$sql.="FROM chamadaprodutos ";
		$sql.="LEFT JOIN chamadas on cha_id = chaprod_cha LEFT JOIN produtos on prod_id = chaprod_prod ";
		$sql.="LEFT JOIN pedidos ON ped_cha = cha_id LEFT JOIN usuarios on ped_usr = usr_id ";
		$sql.="LEFT JOIN pedidoprodutos ON pedprod_ped = ped_id AND pedprod_prod=chaprod_prod ";
		$sql.="LEFT JOIN nucleos ON ped_nuc = nuc_id  ";				
		$sql.="WHERE ped_cha IN ("  . implode(",",$chamadas_validas_id) . ") ";		
		$sql.=" AND ped_fechado = '1' AND chaprod_disponibilidade <> '0' ";
		$sql.=" AND prod_ini_validade<=cha_dt_entrega AND prod_fim_validade>=cha_dt_entrega ";
		$sql.="GROUP BY nuc_id, cha_id ";
		$sql.="ORDER BY nuc_id ";
		
		$res_chamadas_pedidos = executa_sql($sql); 
		
		$chamadas_pedidos = array();	
		if($res_chamadas_pedidos)
		{
			while($row = mysqli_fetch_array($res_chamadas_pedidos,MYSQLI_ASSOC))
			{	
				$chamadas_pedidos[$row["nuc_id"]][$row["cha_id"]] = $row;
			}
		}
			

		
		if($res_nucleos) 
		{	
		
			$total_chamada=array();
			?>		
			
				 <input class="btn btn-success" type="button" value="selecionar tabela para copiar"  onclick="selectElementContents( document.getElementById('selectable') );"> 
                 &nbsp;&nbsp;&nbsp;
	            <button type="button" class="btn btn-info" name="btnAlternarDetalheChamada" id="btnAlternarDetalheChamada"><i class="glyphicon glyphicon-pushpin"></i> mostrar/ocultar detalhamento taxa</button>                   

				 <p />
	
			
					<table id="selectable" class="table table-striped table-bordered table-condensed table-hover">
					<thead>

                        
						<tr>
							<th rowspan="2">#</th>
							<th rowspan="2">Cód.</th>
							<th rowspan="2">Nome Curto</th>
							<th rowspan="2">Nome Completo</th>                                          
							<th rowspan="2">Ativo</th>

							 <?php
							 foreach($chamadas_validas as $chamada)
							 {
								$total_chamada[$chamada["cha_id"]]["entregue"]=0;
								$total_chamada[$chamada["cha_id"]]["taxa"]=0;
								echo("<th colspan='3' class='colspan_alterna_visivel_chamada'>" . $chamada["prodt_nome"] . "<br>" . $chamada["cha_dt_entrega"] . "</th>");
							 }							 
							 ?>
                             <th colspan="3" class='colspan_alterna_visivel_chamada'>TOTAL</th>
						</tr>
                         <tr>                         	
                        	 <?php
							 
							 for ($i=0;$i<=count($chamadas_validas);$i++)
							 {
		                        echo("<th class='alterna_visivel_chamada'>entregue</th><th class='alterna_visivel_chamada'>taxa</th><th>total c/ taxa</th>");
							 }							 
							 ?>
                             
                         </tr>   
                         
                     

					</thead>
					
					<tbody>
					<?php
	
					$cont=0;	
				   $total_cestante_entregue=0;
				   $total_cestante_taxa=0;   			 
				   while ($row = mysqli_fetch_array($res_nucleos,MYSQLI_ASSOC)) 
				   {
					   $cont++;
					   $primeiro=true;					   
					   echo("<tr>");					
					   echo("<td>" .  $cont . "</td>");
					   echo("<td>" .  $row["nuc_id"] . "</td>");
					   ?>
			
							<td><?php echo($row["nuc_nome_curto"]);?></td>
							<td><?php echo($row["nuc_nome_completo"]);?></td>
							<td class="<?php echo($row["nuc_archive"]=='1'?"label-warning":""); ?>">
								<?php echo($row["nuc_archive"]=='1'?"Não":"Sim"); ?>
							</td> 
                            
                       <?php

						foreach($chamadas_validas as $chamada)
						{	
							if(isset($chamadas_pedidos[$row["nuc_id"]][$chamada["cha_id"]]["valor_entregue"]))
							{
								$valor_taxa=$chamadas_pedidos[$row["nuc_id"]][$chamada["cha_id"]]["valor_taxa"]; //ZZ
								
								 echo("<td class='alterna_visivel_chamada'>");
								 echo(formata_moeda($chamadas_pedidos[$row["nuc_id"]][$chamada["cha_id"]]["valor_entregue"])) ;
								 echo("</td>");
								 echo("<td class='alterna_visivel_chamada'>");
								 echo($valor_taxa > 0 ? formata_moeda($valor_taxa) : "&nbsp;") ;									
								 echo("</td>");
								 echo("<td>");
								 echo(formata_moeda($chamadas_pedidos[$row["nuc_id"]][$chamada["cha_id"]]["valor_entregue"] + $valor_taxa));
								 echo("</td>");						
												 
								 $total_cestante_entregue+=$chamadas_pedidos[$row["nuc_id"]][$chamada["cha_id"]]["valor_entregue"];
								 $total_cestante_taxa+=$valor_taxa;										 
								 
								 $total_chamada[$chamada["cha_id"]]["entregue"]+=$chamadas_pedidos[$row["nuc_id"]][$chamada["cha_id"]]["valor_entregue"];
								 $total_chamada[$chamada["cha_id"]]["taxa"]+=$valor_taxa;										 
																 

							}
							else
							{
								echo("<td class='alterna_visivel_chamada'>&nbsp;</td><td class='alterna_visivel_chamada'>&nbsp;</td><td>&nbsp;</td>");
							}

					 }

					   
							
 									
							
				   }
			  ?>   
              
              
              <tr>
              <th colspan="5" class="col_alterna_visivel_cestante">TOTAL</th>
			 <?php		 
                 $total_geral_entregue=0;
				 $total_geral_taxa=0;
				 for($i = 0; $i < count($chamadas_validas); $i++)
				 {
					$total_geral_entregue+=$total_chamada[$chamadas_validas[$i]["cha_id"]]["entregue"];
					$total_geral_taxa+=$total_chamada[$chamadas_validas[$i]["cha_id"]]["taxa"];
					echo("<th class='alterna_visivel_chamada'>" . formata_moeda($total_chamada[$chamadas_validas[$i]["cha_id"]]["entregue"]) . "</th>");
					echo("<th class='alterna_visivel_chamada'>" . formata_moeda($total_chamada[$chamadas_validas[$i]["cha_id"]]["taxa"]) . "</th>");
					echo("<th>" . formata_moeda($total_chamada[$chamadas_validas[$i]["cha_id"]]["entregue"] + $total_chamada[$chamadas_validas[$i]["cha_id"]]["taxa"]) . "</th>");
				 }	                   
				 				 
					echo("<th class='alterna_visivel_chamada'>" . formata_moeda($total_geral_entregue) . "</th>");
					echo("<th class='alterna_visivel_chamada'>" . formata_moeda($total_geral_taxa) . "</th>");
					echo("<th>" . formata_moeda($total_geral_entregue + $total_geral_taxa) . "</th>");
				 

                 ?>

                
    							
			  </tr>

							 
			  </tbody></table>
		   
	
			<?php 
		}
	} // if count(cestantes)> 0 


	
	

 ?>             
                         
          </div>
       

<script type="text/javascript">
	$(function() {
		$(".data").datepicker({
			format: 'dd/mm/yyyy',
			language: 'pt-BR',
			autoclose: true
		})
	}); 
	
	
	$('#btnAlternarDetalheChamada').on('click', function (e) {
        e.preventDefault();
        $('.alterna_visivel_chamada').toggle();
		if($('.colspan_alterna_visivel_chamada').attr('colspan')==1)
		{
			$('.colspan_alterna_visivel_chamada').attr('colspan','3');
		}
		else
		{
			$('.colspan_alterna_visivel_chamada').attr('colspan','1');
		}

    });
	
		
    $('.alterna_visivel_chamada').toggle();
	$('.colspan_alterna_visivel_chamada').attr('colspan','1');
	
</script>    
    

 <?php 


footer();

?>