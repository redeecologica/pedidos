<?php  
  require  "common.inc.php"; 
  // por questões de performance, relatório está disponível somente para administrador
  verifica_seguranca($_SESSION[PAP_ADM]); 
  
  top();  
  
 
 ?>
 
 <form class="form-inline" method="post" name="frm_filtro" id="frm_filtro">
	<legend>Produtos Disponíveis ao Longo do Ano</legend>
   
	<?php  		
  		$ano = request_get("ano",date("Y")) ;
		$prodt = request_get("prodt",-1) ;
		
	
		
	?>
     <fieldset>
     
     
  				<label for="prodt">Tipo: </label>            
                 <select name="prodt" id="prodt" class="input-medium">
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
                                 if($row['prodt_id']==$prodt) echo(" selected");
                                 echo (">" . $row['prodt_nome'] . "</option>");
                              }
                            }
                        ?>  
                 </select>    
                 
                 &nbsp;
                    
  				<label for="ano">Ano: </label>            
                <input type="text" name="ano" id="ano" class="input-mini" value="<?php echo($ano); ?>">
                &nbsp;&nbsp;
			<button class="btn btn-primary" type="submit"><i class="icon-search icon-white"></i> consultar</button>
                                           
                    
     </fieldset>
</form>

<?php
 if($prodt!=-1 )
 {
	 $sql = "SELECT forn_nome_curto, prod_id, prod_nome, prod_unidade, FORMAT(prod_valor_compra, 2) prod_valor_compra,";
	 $sql.= "chaprod_disponibilidade, cha_id, FORMAT(SUM(pedprod_quantidade), 1) total_pedido, cha_dt_entrega "; 
	 $sql.= " FROM produtos ";
	 $sql.= " LEFT JOIN fornecedores ON prod_forn = forn_id";
	 $sql.= " LEFT JOIN chamadas ON cha_prodt = prod_prodt AND year(cha_dt_entrega)= " . prep_para_bd($ano);
	 $sql.= " LEFT JOIN pedidoprodutos ON pedprod_prod=prod_id AND pedprod_ped IN ";
	 $sql.= "  (SELECT ped_id FROM pedidos where ped_cha = cha_id AND ped_fechado = '1' )";
	 $sql.= " LEFT JOIN chamadaprodutos ON chaprod_cha = cha_id AND chaprod_prod = prod_id ";
	 $sql.= " WHERE  ";
	 $sql.= " prod_prodt = " .  prep_para_bd($prodt) . " AND ";
	 $sql.= " prod_ini_validade <= now() AND ";
	 $sql.= " prod_fim_validade >= now() ";	
	 $sql.= " GROUP BY forn_nome_curto , prod_nome, prod_unidade, cha_dt_entrega ";
	 $sql.= " ORDER BY forn_nome_curto , prod_nome , prod_unidade, cha_dt_entrega ";
	 $res = executa_sql($sql);
 }

?> 

<legend>Relatório - Produtos ao longo do ano de <?php echo($ano);?> </legend>

<?php 

if($res) 
{ 
	?>
    <table class="table table-bordered table-condensed">
    <thead>
      <tr>
        <th rowspan="2">Produtor</th>
        <th rowspan="2">Produto</th>
        <th rowspan="2">Unidade</th>        
        <th rowspan="2">Valor</th>
        
        <?php
        
		 $sql = "SELECT MONTH(cha_dt_entrega) AS mes, COUNT(MONTH(cha_dt_entrega)) total ";
		 $sql.= " FROM chamadas ";
		 $sql.= " WHERE YEAR(cha_dt_entrega)= " . prep_para_bd($ano) . " AND cha_prodt = " . prep_para_bd($prodt);
		 $sql.= " GROUP BY MONTH(cha_dt_entrega) ORDER BY cha_dt_entrega ";
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
		 $sql.= " FROM chamadas ";
		 $sql.= " WHERE YEAR(cha_dt_entrega)= " . prep_para_bd($ano) . " AND cha_prodt = " . prep_para_bd($prodt);
		 $sql.= " ORDER BY cha_dt_entrega";
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
		echo("Nenhum");
	}
	else
	{
 	while($produto = mysqli_fetch_array($res,MYSQLI_ASSOC))
	{
		echo("<tr>");
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

<hr>

<?php

	footer();
?>
