<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO]);
  top();
?>

<ul>
<?php 
                      
    $sql = "SELECT cha_id, prodt_nome, cha_dt_entrega cha_dt_entrega_original, DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega ";
    $sql.= "FROM chamadas LEFT JOIN produtotipos ON prodt_id = cha_prodt ";
    $sql.= "ORDER BY cha_dt_entrega_original DESC LIMIT 10 ";
    $res = executa_sql($sql);
    if($res)
    {
      while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
      {				
	  	 ?>			
         <li>
         <strong>
	         <?php echo($row['prodt_nome'] . " - " . $row['cha_dt_entrega']);?>
         </strong> 
             <ul>
              <?php
			  if($row['prodt_nome']=="Frescos")
			  {
			  ?>	  
                <li>
                    Para encaminhar ao Produtor: 
                    <a href="rel_pedido_por_produtor.php?cha_id=<?php echo($row['cha_id']);?>">Pedido de cada Produto por Núcleo</a>
                </li>
<!--
                <li>
	                Para o Mutirão: 
                    <a href="rel_pedido_pre_mutirao.php?cha_id=<?php echo($row['cha_id']);?>">Pedido de Cada Núcleo</a> 
                </li>
-->
                <li>
                    Para o responsável pela Entrega:
                    <a href="rel_pedido_por_cestante.php?cha_id=<?php echo($row['cha_id']);?>">Pedido de cada Cestante do Núcleo</a>
                </li>
                
			  <?php
			  }
			  else
			  {
			  ?>	  
                <li>
                    Para encaminhar ao Produtor: 
                    <a href="rel_pedido_por_produtor.php?cha_id=<?php echo($row['cha_id']);?>">Pedido de cada Produto por Núcleo</a>
                </li>
                <li>
	                Para o Mutirão: 
                    <a href="rel_pedido_pre_mutirao.php?cha_id=<?php echo($row['cha_id']);?>">Pedido de Cada Núcleo</a> 
                </li>
                <li>
                    Para o responsável pela Entrega:
                    <a href="rel_pedido_por_cestante.php?cha_id=<?php echo($row['cha_id']);?>">Pedido de cada Cestante do Núcleo</a>
                </li>
			  <?php
			  }			  
			  ?>
              


             </ul>       
         </li>         
         <?php
      }
    }

?>
</ul>


<?php 
 
	footer();
?>