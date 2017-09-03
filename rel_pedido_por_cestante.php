<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO] || $_SESSION[PAP_ACOMPANHA_RELATORIOS]  || $_SESSION[PAP_RESP_ENTREGA]  || $_SESSION[PAP_RESP_FINANCAS]);  
  
  top();  
  
 $cha_id=request_get("cha_id",0);
                      
 $sql = "SELECT prodt_nome, DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega ";
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


?>

<legend>Relatório - <?php echo($row["prodt_nome"] . " - " . $row["cha_dt_entrega"]);?> - Pedido de cada cestante por Núcleo</legend>

<?php 

$sql="SELECT count(ped_id) as total_pedidos, nuc_nome_completo, nuc_id ";
$sql.="FROM pedidos ";
$sql.="LEFT JOIN chamadas on ped_cha = cha_id ";
$sql.="LEFT JOIN nucleos on ped_nuc = nuc_id ";
$sql.="WHERE ped_cha = " . prep_para_bd($cha_id) . " AND ped_fechado = '1'  ";
$sql.="GROUP BY nuc_nome_completo ORDER BY nuc_nome_completo ";

$res = executa_sql($sql); // lista de núcleos com pedido para esta chamada
if($res) 
{
	while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 	
	{
		?>
        	<strong><?php echo($row["nuc_nome_completo"]);?></strong> (total de <?php echo($row["total_pedidos"]);?> pedidos): 
            &nbsp;
             <a href="rel_pedido_por_cestante_nucleo.php?cha_id=<?php echo($cha_id);?>&nuc_id=<?php echo($row["nuc_id"]);?>">Relatório de Pedidos</a> &nbsp; &nbsp;

             <a href="rel_pedido_contato_cestantes.php?cha_id=<?php echo($cha_id);?>&nuc_id=<?php echo($row["nuc_id"]);?>">Contato dos Cestantes</a>
             
            <br />            <br />
        <?php 
	}
}

?>

<hr>
<a class="btn btn-default" href="arquivos/modelo_relatorio_pedidos_para_nucleo.xlsx?ver=2.0"><i class="glyphicon glyphicon-download"></i> Baixar modelo de planilha para copiar/colar os dados</a>

<?php

	footer();
?>