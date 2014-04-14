<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO]);
  top();
?>

<div class="panel panel-default">
  <div class="panel-heading">
     <strong>Relatórios</strong>
  </div>
 <div class="panel-body">
 
<ul>
<?php 
                      
    $sql = "SELECT cha_id, prodt_nome, prodt_mutirao, cha_dt_entrega cha_dt_entrega_original, DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega ";
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
			  if($row['prodt_mutirao']== 0)
			  {
			  ?>	  
                <li>
                    Para encaminhar ao Produtor: 
                    <ul>
                   	 	<li>
           				<a href="rel_pedido_por_produtor.php?cha_id=<?php echo($row['cha_id']);?>">Pedido consolidado dos núcleos</a>
                	    </li>
                    </ul>
                </li>
<!--
                <li>
	                Para o Mutirão: 
                    <a href="rel_pedido_pre_mutirao.php?cha_id=<?php echo($row['cha_id']);?>">Pedido de Cada Núcleo</a> 
                </li>
-->
                <li>
                    Para o responsável pela Entrega:
                    <ul>
                   	 	<li>
							<a href="rel_pedido_por_cestante.php?cha_id=<?php echo($row['cha_id']);?>">Pedido de cada cestante</a> (1 relatório para cada núcleo)
                	    </li>
                        <!--
                        <li>
                    <a href="rel_pedido_contato_cestantes.php?cha_id=<?php echo($row['cha_id']);?>">Contato de todos os cestantes que enviaram pedido</a>
                	    </li>
                        -->                
                        </ul>
 				</li>
   
                
			  <?php
			  }
			  else
			  {
			  ?>	  

                <li>
                    Para encaminhar ao Produtor: 
                    <ul>
                   	 	<li>
           				<a href="rel_pedido_por_produtor_considera_estoque.php?cha_id=<?php echo($row['cha_id']);?>">Pedido consolidado dos núcleos</a> (considerando estoque informado pelo mutirão)
                	    </li>
                    </ul>
                </li>

                <li>
                  Para o Mutirão:
                    <ul>
                   	 	<li>
           				<a href="rel_pedido_pre_mutirao.php?cha_id=<?php echo($row['cha_id']);?>">Pedido consolidado dos núcleos para distribuição</a> 
                	    </li>
                    </ul>
                </li>

                <li>
                    Para o responsável pela Entrega:
                    <ul>
                   	 	<li>
							<a href="rel_pedido_por_cestante.php?cha_id=<?php echo($row['cha_id']);?>">Pedido de cada cestante</a> (1 relatório para cada núcleo)
                	    </li>
                        <!--
                        <li>
                    <a href="rel_pedido_contato_cestantes.php?cha_id=<?php echo($row['cha_id']);?>">Contato de todos os cestantes que enviaram pedido</a>
                	    </li>
                        -->                
                        </ul>
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

</div>

<?php 
 
	footer();
?>