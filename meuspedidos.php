<?php  
  require  "common.inc.php"; 
  verifica_seguranca();
  top();
?>


    <legend>Chamadas disponíveis para o seu núcleo</legend>
	<?php
        
		
		$filtro_disponiveis="RIGHT JOIN chamadanucleos ON chanuc_nuc = usr_nuc AND chanuc_cha = cha_id ";
		$filtro_disponiveis.="WHERE cha_dt_min <= now() AND NOW()<= cha_dt_max ORDER BY cha_dt_max_original DESC, prodt_nome ";
		
		$filtro_anteriores="WHERE NOW() > cha_dt_max  ORDER BY cha_dt_entrega_original DESC, prodt_nome LIMIT 10"; 	
			
        $sql = "SELECT cha_id, cha_prodt,  cha_dt_entrega cha_dt_entrega_original, date_format(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, ";
        $sql.= "date_format(cha_dt_min,'%d/%m/%Y %H:%i') cha_dt_min, cha_dt_max cha_dt_max_original, ";
        $sql.= "date_format(cha_dt_max,'%d/%m/%Y %H:%i') cha_dt_max, prodt_nome, ped_fechado, ped_id ";
        $sql.= "FROM chamadas ";
        $sql.= "LEFT JOIN pedidos ON cha_id = ped_cha AND ped_usr = " . prep_para_bd($_SESSION['usr.id']) . " ";	
        $sql.= "LEFT JOIN produtotipos ON cha_prodt = prodt_id ";	
		$sql.= "LEFT JOIN usuarios on usr_id = " . prep_para_bd($_SESSION['usr.id']) . " ";
		
        $res = executa_sql($sql . $filtro_disponiveis); 
		
		  
        $contador = 0;
        if($res && mysqli_num_rows($res))
        {			
			?>

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Tipo</th>
                        <th>Data de Entrega</th>
                        <th>Prazo para enviar o Pedido</th>
                        <th>Status do seu Pedido</th>
                    </tr>
                </thead>
                <tbody>		    
            <?php
			$contador = 0;
			while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
			{				
				
					?>
				  <tr class="<?php echo( $row['ped_fechado']==1 ? "success": ($row['ped_id'] ? "info" : "")); ?>" >
                  	 <td><?php echo(++$contador);?></td>               
					 <td><?php echo($row['prodt_nome']);?></td>               
					 <td><?php echo($row['cha_dt_entrega']);?></td>
					 <td><?php echo($row['cha_dt_max']);?> </td>                     
					 <td>
                     <?php
                     	if(!$row['ped_id'])
						{
							echo("Você ainda não enviou pedido para esta chamada. <a class=\"btn btn-default btn-sm\" href=\"pedido.php?action=" . ACAO_INCLUIR . "&amp;ped_cha=" . $row['cha_id']);
							echo("&amp;ped_usr=" . $_SESSION['usr.id'] . "\">");
							echo("<i class=\"glyphicon glyphicon-plus\"></i> criar pedido</a>");						
						}
                        else
                        {
							if($row['ped_fechado']==1) echo("Seu pedido foi enviado. ");
							else echo("Pedido em elaboração. Você ainda precisa enviá-lo.");
														
							echo("<a class=\"btn btn-default btn-sm\" href=\"pedido.php?action=" . ACAO_EXIBIR_LEITURA);
							echo("&amp;ped_id=" . $row['ped_id'] . "\">");
							echo("<i class=\"glyphicon glyphicon-search\"></i> ver pedido</a>");						
                        }
					 ?>
					 
                     </td>     
                     
				  </tr>
				<?php 
			   }
			?>
            </tbody>
        </table>
		<?php 
       }
	   else
	   {
		   echo("No momento não há chamadas disponíveis.<hr>");
	   }


        $res = executa_sql($sql . $filtro_anteriores);      
        $contador = 0;
        if($res && mysqli_num_rows($res))
        {			
			?>
		    <legend>Chamadas anteriores (últimas dez chamadas)</legend>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Tipo</th>
                        <th>Entrega</th>
                        <th>Prazo envio do Pedido</th>
                        <th>Chamada</th>
                        <th>Seu Pedido</th>
                    </tr>
                </thead>
                <tbody>		    
            <?php
			$contador=0;
			while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
			{				
					?>
				  <tr class="<?php echo( $row['ped_fechado']==1 ? "success": ($row['ped_id'] ? "info" : "")); ?>" >
                  	 <td><?php echo(++$contador); ?></td>               
					 <td><?php echo($row['prodt_nome']); ?></td>               
					 <td><?php echo($row['cha_dt_entrega']); ?></td>
					 <td><?php echo($row['cha_dt_max']); ?> </td>  
					 <td> <?php echo("<a class=\"btn btn-default btn-sm\" href=\"chamada_info.php?action=" . ACAO_EXIBIR_LEITURA .  "&amp;cha_id=" . $row['cha_id'] . "\"><i class=\"glyphicon glyphicon-leaf\"></i> ver produtos</a>");?></td>
                                                                                  
					 <td>
                     <?php
                     	if(!$row['ped_id'])
						{
							echo("Você não enviou pedido para esta chamada.");						
						}
                        else
                        {
							if($row['ped_fechado']==1) echo("Você enviou pedido para esta chamada.");
							else echo("Você criou o pedido mas não enviou.");														
							echo("<a class=\"btn btn-default btn-sm\" href=\"pedido.php?action=" . ACAO_EXIBIR_LEITURA);
							echo("&amp;ped_id=" . $row['ped_id'] . "\">");
							echo("<i class=\"glyphicon glyphicon-search\"></i> ver pedido</a>");						
                        }
					 ?>
					 
                     </td>     
                     
				  </tr>
				<?php 
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