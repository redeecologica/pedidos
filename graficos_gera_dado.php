<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_FINANCAS]);
  top();
  


	$sql  = "SELECT nuc_id, nuc_nome_curto, prodt_nome, cha_dt_entrega cha_dt_entrega_original, DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, FORMAT(Sum(prod_valor_compra * pedprod_quantidade),2) AS valor_pedido ";
	$sql .= "FROM chamadaprodutos ";
	$sql .= "LEFT JOIN chamadas ON cha_id = chaprod_cha ";
	$sql .= "LEFT JOIN produtotipos ON cha_prodt = prodt_id ";
	$sql .= "LEFT JOIN produtos ON prod_id = chaprod_prod ";
	$sql .= "LEFT JOIN pedidos ON ped_cha = cha_id ";
	$sql .= "LEFT JOIN pedidoprodutos ON pedprod_ped = ped_id ";
	$sql .= "AND pedprod_prod=chaprod_prod ";
	$sql .= "LEFT JOIN nucleos ON ped_nuc = nuc_id WHERE     (year(cha_dt_entrega)= 2017 OR year(cha_dt_entrega)= 2016) AND  ped_fechado = '1' ";
	$sql .= "  AND       chaprod_disponibilidade <> '0' ";
	$sql .= " AND       prod_ini_validade<='2017-10-18 00:00:01' ";
	$sql .= " AND       prod_fim_validade>='2017-10-18 00:00:01' ";
	$sql .= " AND prodt_nome='Secos'";
	$sql .= "GROUP BY nuc_nome_curto, nuc_nome_completo, cha_id, prodt_nome, cha_dt_entrega ";
	$sql .= "ORDER BY nuc_nome_curto, cha_dt_entrega_original";

	$res = executa_sql($sql); 
	$chamadas_pedidos = array();	
	$ultimo_nuc=0;
	$cont=0;
	if($res)
	{
		while($row = mysqli_fetch_array($res,MYSQLI_ASSOC))
		{
			if($row["nuc_id"]!=$ultimo_nuc)
			{
				$cont++;
				echo("var trace" . $cont . " = {<br>");
				echo(" x:")
				
			}
		}
	}
		

	var trace3 = {
	  x: [1, 2, 3, 4], 
	  y: [1, 2, 5, 8], 
	  name: 'botafogo',
	  type: 'scatter'
	};
	
?>




<?php 
 
	footer();
?>