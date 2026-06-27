<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO] || $_SESSION[PAP_ACOMPANHA_PRODUTOR] || $_SESSION[PAP_ACOMPANHA_RELATORIOS] ||  $_SESSION[PAP_RESP_ENTREGA]  || $_SESSION[PAP_RESP_FINANCAS]);
  top();
  
 $cha_id=request_get("cha_id","");
                      
 $sql = "SELECT prodt_nome, prodt_mutirao, DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega ";
 $sql.= "FROM chamadas LEFT JOIN produtotipos ON prodt_id = cha_prodt ";
 $sql.= "WHERE cha_id = " . prep_para_bd($cha_id);
 $res = executa_sql($sql);
 $row = mysqli_fetch_array($res,MYSQLI_ASSOC);

 if(!$res)
 {
	 redireciona(PAGINAPRINCIPAL);
 }

?>

<legend>Relatório - Previsão de Pagamento - <?php echo($row["prodt_nome"]); ?> - Entrega em <?php echo($row["cha_dt_entrega"]); ?></legend>


<?php 

$sql="SELECT  forn_nome_curto, forn_nome_completo, SUM(prod_valor_compra*chaprod_recebido_confirmado) total_fornecedor ";
$sql.="FROM chamadaprodutos ";
$sql.="LEFT JOIN chamadas on cha_id = chaprod_cha ";
$sql.="LEFT JOIN produtos on prod_id = chaprod_prod ";
$sql.="LEFT JOIN fornecedores on prod_forn = forn_id ";
$sql.="WHERE chaprod_cha = " . prep_para_bd($cha_id) . " ";
$sql.="AND chaprod_disponibilidade <> '0' ";
$sql.="AND prod_ini_validade<=cha_dt_entrega AND prod_fim_validade>=cha_dt_entrega  ";
$sql.="GROUP BY  forn_id ";
$sql.="ORDER BY forn_nome_curto ";

$res = executa_sql($sql);

		if($res)
		{
		   $total_geral_rede=0;	
		   
			?>
            
           <input class="btn btn-success" type="button" value="selecionar tabela para copiar" 
           onclick="selectElementContents( document.getElementById('selectable') );">
           <br><br> 

                <table id="selectable" class="table table-striped table-bordered table-condensed">
                <thead>
                    <tr>
                              <th>Produtor (nome curto)</th>
                              <th>Produtor (nome completo)</th>
                              <th>Total a pagar (R$)</th>                                            
                    </tr>
                 </thead>           		   
                <tbody>
	 
			<?php		   
		   
		   while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		   {					
				
				?>
				<tr> 
				<td><?php echo($row["forn_nome_curto"]);?></td>
				<td><?php echo($row["forn_nome_completo"]);?></td>                   
				<td style="text-align:center"><?php echo (formata_moeda($row["total_fornecedor"])); ?></td>			
				</tr>
				 
				<?php

				$total_geral_rede+=$row["total_fornecedor"];
		   }
		   
		  ?>  
          
              <tr>
                    <th colspan="2" style="text-align:right">TOTAL GERAL </th>                               
                    <th style="text-align:center"> <?php echo(formata_moeda($total_geral_rede));?></th>
                   </tr>
          </tbody>
		   </table>
    
          
           <hr />           
           Para o detalhamento a nível de produtos, ver <a href="rel_previsao_pagamento_detalhado.php?cha_id=<?php echo($cha_id);?>">relatório de previsão de pagamento detalhado</a>. 
<br>           
           
		   <?php
		} 
 

 
	footer();
?>