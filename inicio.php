<?php  
  require  "common.inc.php"; 
  verifica_seguranca();
  top();
?>
<legend>Notícias da Rede</legend>

<?php 

	 $sql = "SELECT not_publicado FROM noticia ORDER BY dt_atualizacao DESC LIMIT 1 ";
	 $res = executa_sql($sql);
  	 if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
	 {	
		  	echo( $row["not_publicado"]);	
	 }

	footer();
?>