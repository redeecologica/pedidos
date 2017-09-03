<?php  
  require  "common.inc.php"; 
  // por questões de performance, relatório está disponível somente para administrador
  verifica_seguranca($_SESSION[PAP_ADM]); 
  
  top();  
  
 
 ?>
 
 <div class="panel panel-default">
  <div class="panel-heading">
     <strong>Produtos Disponíveis ao Longo do Ano</strong>
  </div> 
 
 <div class="panel-body">
  <form class="form-inline" method="post" name="frm_filtro" id="frm_filtro">

	<?php  
  		
		$cha_prodt = request_get("cha_prodt",-1);
		$forn_prodt = request_get("forn_prodt",-1);	
		$cha_dt_ini = request_get("cha_dt_ini",date("d/m/Y", strtotime("-3 months"))) ;
		$cha_dt_fim = request_get("cha_dt_fim",date("d/m/Y")) ;		
		$forn_nome = request_get("forn_nome","") ;
		$prod_nome = request_get("prod_nome","") ;
		$chamada_tipos = array();
						
	?>
     <fieldset>     
     	<div class="form-group">
  				<label for="cha_prodt">Tipo Chamada: </label>            
                 <select name="cha_prodt" id="cha_prodt" class="form-control">
                        <option value="-1" <?php echo(($cha_prodt==-1)?" selected" : ""); ?> >SELECIONE</option>
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
							  	 $chamada_tipos[$row['prodt_id']]=$row['prodt_nome'];
                              }

 
                            }
							
                        ?>  
                 </select>    
         </div>   


        &nbsp;&nbsp
        <div class="form-group">            
  				
                 <label class="control-label" for="cha_dt_ini">Data entrega min: </label>            
                   <input type="text" name="cha_dt_ini" required="required" value="<?php echo($cha_dt_ini); ?>"  class="form-control"/>                    
                 
                 &nbsp;&nbsp
                 <label class="control-label" for="cha_dt_fim">máx: </label>            
                   <input type="text" name="cha_dt_fim" required="required" value="<?php echo($cha_dt_fim); ?>"  class="form-control"/>   
         </div> 
         
      
<p />      
         
     	<div class="form-group">
  				<label for="forn_prodt">Tipo Produtor: </label>            
                 <select name="forn_prodt" id="forn_prodt" class="form-control">
                        <option value="-1" <?php echo(($forn_prodt==-1)?" selected" : ""); ?> >TODOS</option>
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
                                 if($row['prodt_id']==$forn_prodt) echo(" selected");
                                 echo (">" . $row['prodt_nome'] . "</option>");
                              }
                            }
                        ?>  
                 </select>    
         </div>                
    
         
         
         &nbsp;
        <div class="form-group">            
               <label class="control-label" for="forn_nome">Produtor: </label>
                   <input type="text" name="forn_nome" value="<?php echo($forn_nome); ?>" placeholder="ex: *brejal*"  class="form-control"/>
                    
         </div> 
         
        &nbsp;
        <div class="form-group">            
               <label class="control-label" for="prod_nome">Produto: </label>
                   <input type="text" name="prod_nome"  value="<?php echo($prod_nome); ?>" placeholder="ex: banana*" class="form-control"/>                    
         </div> 
            
          &nbsp;&nbsp &nbsp;&nbsp
         
         <button type="submit" class="btn btn-default btn-enviando"  data-loading-text="aguarde..." >Consultar</button>
                                                   
                    
      </fieldset>
  </form>
 </div>
 
 
 
 </div>
 
<?php

if ($_SERVER['REQUEST_METHOD'] == 'POST')
 {	 
	 $sql = "SELECT forn_nome_curto, prod_id, prod_nome, prod_unidade, prod_valor_compra, ";
	 $sql.= " MAX(chaprod_disponibilidade) chaprod_disponibilidade, FORMAT(SUM(pedprod_quantidade), 1) total_pedido, cha_dt_entrega, MIN(cha_prodt) cha_prodt "; 
	 $sql.= " FROM produtos ";
	 $sql.= " LEFT JOIN fornecedores ON prod_forn = forn_id ";	 
	 $sql.= " CROSS JOIN chamadas "; 
	 $sql.= " LEFT JOIN pedidoprodutos ON pedprod_prod=prod_id AND pedprod_ped IN ";
	 $sql.= "  (SELECT ped_id FROM pedidos where ped_cha = cha_id AND ped_fechado = '1' )";
	 $sql.= " LEFT JOIN chamadaprodutos ON chaprod_cha = cha_id AND chaprod_prod = prod_id ";
	 $sql.= " WHERE  ";	 
	 if($cha_prodt!=-1) $sql.= " cha_prodt = " .  prep_para_bd($cha_prodt) . " AND ";	
	 if($forn_prodt!=-1) $sql.= " forn_prodt = " .  prep_para_bd($forn_prodt) . " AND ";		 
	 if($forn_nome!="")
	 {
		  $sql.= " ( forn_nome_curto LIKE " .  prep_para_bd(str_replace('*','%',$forn_nome)) . " OR ";	 
		  $sql.= " forn_nome_completo LIKE " .  prep_para_bd(str_replace('*','%',$forn_nome)) . " ) ";	 		
		  $sql.= " AND " ;
	 }	 
	 if($prod_nome!="") $sql.= " prod_nome LIKE " .  prep_para_bd(str_replace('*','%',$prod_nome)) . " AND " ;	 	 
	 
	 $sql.= " DATE(cha_dt_entrega) >= " . prep_para_bd(formata_data_para_mysql($cha_dt_ini)) . " AND ";
	 $sql.= " DATE(cha_dt_entrega) <= " . prep_para_bd(formata_data_para_mysql($cha_dt_fim)) . " AND ";	 	 	 	 
	 $sql.= " DATE(prod_ini_validade) <= " . prep_para_bd(formata_data_para_mysql($cha_dt_fim)) . " AND ";
	 $sql.= " DATE(prod_fim_validade) >= " . prep_para_bd(formata_data_para_mysql($cha_dt_fim)) . "   ";	
	 $sql.= " GROUP BY cha_prodt, forn_nome_curto , prod_id, cha_dt_entrega ";
	 $sql.= " ORDER BY cha_prodt, forn_nome_curto , prod_nome , prod_unidade, cha_dt_entrega ";
	 $res = executa_sql($sql);	 

 }

?> 


<?php 

if($res) 
{ 
	?>
    
    <p /> 
    <input class="btn btn-info" type="button" value="selecionar tabela para copiar" onclick="selectElementContents( document.getElementById('selectable') );">
           
    <p />
    
    <table id="selectable"  class="table table-bordered table-condensed">
     
    <thead>
      <tr>
        <th rowspan="3">Chamada</th>
        <th rowspan="3">Produtor</th>
        <th rowspan="3">Produto</th>
        <th rowspan="3">Unidade</th>        
        <th rowspan="3">Valor compra em <?php echo($cha_dt_fim);?> </th>
   
        
        <?php
         
		 $filtro_chamadas= " cha_prodt = " .  prep_para_bd($cha_prodt) . " AND ";	 
	 	 $filtro_chamadas.= " DATE(cha_dt_entrega) >= " . prep_para_bd(formata_data_para_mysql($cha_dt_ini)) . " AND ";
	 	 $filtro_chamadas.= " DATE(cha_dt_entrega) <= " . prep_para_bd(formata_data_para_mysql($cha_dt_fim));	 	 	 	 
		 

		 
		 $sql = "SELECT YEAR(cha_dt_entrega) AS ano, COUNT(YEAR(cha_dt_entrega)) total ";
		 $sql.= " FROM chamadas WHERE ";
		 $sql.= $filtro_chamadas;		 
		 $sql.= " GROUP BY YEAR(cha_dt_entrega) ORDER BY cha_dt_entrega ";
		 $res2 = executa_sql($sql);	 
	 	 $total_entregas = 0;
	     while ($entregas_ano = mysqli_fetch_array($res2,MYSQLI_ASSOC)) 
		 {
		 	$total_entregas+= $entregas_ano["total"];
			 echo("<th colspan='". $entregas_ano["total"] . "'>" . $entregas_ano["ano"] . "</th>");
		 }	
		 echo("</tr>");	 
		 
		 
		 
		 $sql = "SELECT MONTH(cha_dt_entrega) AS mes, COUNT(MONTH(cha_dt_entrega)) total ";
		 $sql.= " FROM chamadas WHERE ";
		 $sql.= $filtro_chamadas;		 
		 $sql.= " GROUP BY YEAR(cha_dt_entrega), MONTH(cha_dt_entrega) ORDER BY cha_dt_entrega ";
		 $res2 = executa_sql($sql);	 
	 	 $total_entregas = 0;
	     while ($entregas_mes = mysqli_fetch_array($res2,MYSQLI_ASSOC)) 
		 {
		 	$total_entregas+= $entregas_mes["total"];
			 echo("<th colspan='". $entregas_mes["total"] . "'>" . $meses[$entregas_mes["mes"]] . "</th>");
		 }
		 	
		 echo("</tr>");
		 
		 echo("<tr>");
		 $sql = "SELECT DAY(cha_dt_entrega) AS dia ";
		 $sql.= " FROM chamadas WHERE ";
 		 $sql.= $filtro_chamadas;
		 $res2 = executa_sql($sql);
	     while ($entregas_dia = mysqli_fetch_array($res2,MYSQLI_ASSOC)) 
		 {
			 echo("<th>" . $entregas_dia["dia"] . "</th>");
		 }		 
		 echo("</tr>");				 
		?>
        

      </tr>
  	</thead>
    <tbody>
    
    <?php
	
	if($total_entregas==0)
	{
		echo("<tr><td colspan='5'>Nenhuma chamada para o período informado</td></tr>");
	}
	else
	{
 	while($produto = mysqli_fetch_array($res,MYSQLI_ASSOC))
	{
		echo("<tr>");
		echo("<td>" . $chamada_tipos[$produto["cha_prodt"]] . "</td>");
		echo("<td>" . $produto["forn_nome_curto"] . "</td>");
		echo("<td>" . $produto["prod_nome"] . "</td>");
		echo("<td>" . $produto["prod_unidade"] . "</td>");
		echo("<td>" . formata_moeda($produto["prod_valor_compra"]) . "</td>");	
		for($i=1; $i <= $total_entregas; $i++)
		{
			if($i<>1) $produto = mysqli_fetch_array($res,MYSQLI_ASSOC);
			
			if($produto["chaprod_disponibilidade"]==1 || $produto["chaprod_disponibilidade"]==2)
			{
				echo("<td style='background-color: #" . ( ($produto["chaprod_disponibilidade"]==2 )? 'd0e9c6' : 'faf2cc') . "'");
				echo(">" . formata_numero_de_mysql($produto["total_pedido"]) . "</td>");							
			}
			else
			{
				echo("<td>&nbsp;</td>");
			}

		}		
		echo("</tr>");				
	}
	}
	
 	
	?>
    
    </tbody>
    
    </table>
    
    <?php
}

?>



<?php

	footer();
?>
