<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_ADM]);
  top();
?>

<div class="panel panel-default">
  <div class="panel-heading">
     <strong>Usuários sem senha de acesso criada</strong>
  </div>
    <table class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>#</th>
                <th>Núcleo</th>
                <th>Associado</th>              
                <th>Nome Completo</th>
                <th>Nome Curto</th>
                <th>Email</th>
                <th>Emails Adicionais</th>        
                <th>Link</th> 
            </tr>
        </thead>
        <tbody>



	<?php  
		
		$sql = "SELECT usr_id, usr_associado, usr_nome_curto, usr_nome_completo, usr_email, usr_email_alternativo, nuc_nome_curto ";
		$sql.= "FROM usuarios LEFT JOIN nucleos ON usr_nuc = nuc_id ";	
		$sql.= "WHERE usr_senha is null ";
		$sql.= " AND usr_archive = '0' ";
		$sql.= "ORDER BY nuc_nome_curto, usr_nome_completo ";
					
		$res = executa_sql($sql);
		$contador = 0;
		if($res)
		{
			
		 while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		 {
			 ?>
             
             
				  <tr>
                  	 <td><?php echo(++$contador);?></td>               
					 <td><?php echo($row['nuc_nome_curto']);?></td>   
					 <td><?php echo($row['usr_associado']? "Sim" : "Não"); ?></td>                                    
					 <td><?php echo($row['usr_nome_completo']);?></td>
                     <td><?php echo($row['usr_nome_curto']);?></td> 
					 <td><?php echo($row['usr_email']);?> </td>                     
 					 <td><?php echo($row['usr_email_alternativo']);?> </td> 
 					 <td><a href="cestante.php?action=<?php echo(ACAO_EXIBIR_LEITURA); ?>&usr_id=<?php echo($row['usr_id']); ?>&gera_primeira_senha=1">gerar senha</a></td>                      
				  </tr>
             
             
             <?php 

			 
		 }
		}

	echo("	</tbody></table> </div>");
 
	footer();
?>