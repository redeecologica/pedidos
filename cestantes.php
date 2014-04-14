<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO]);
  top();
?>

<div class="panel panel-default">
  <div class="panel-heading">
     <strong>Lista de Cestantes</strong>
       <span class="pull-right">
		<a href="cestante.php?action=<?php echo(ACAO_INCLUIR);?>" class="btn btn-default btn-xs"><i class="glyphicon glyphicon-plus"></i> adicionar novo</a>
	</span>
  </div>
 <div class="panel-body">

	<?php  
  		$usr_archive = request_get("usr_archive",0);  		
		$usr_nuc = request_get("usr_nuc",($_SESSION[PAP_RESP_NUCLEO]? $_SESSION['usr.nuc'] : -1)) ;
	
	?>
    <form class="form-inline" action="cestantes.php" method="post" name="frm_filtro" id="frm_filtro" role="form">    
     <fieldset>

	  <div class="form-group">
  
     
  				<label for="usr_archive">Situação: </label>&nbsp;      
                 <select name="usr_archive" id="usr_archive" onchange="javascript:frm_filtro.submit();" class="form-control">
                        <option value="-1" <?php echo( ($usr_archive)==-1?" selected" : ""); ?> >TODOS</option>
                        <option value="0"  <?php echo( ($usr_archive)==0?" selected" : ""); ?> >Ativos</option>
                        <option value="1"  <?php echo( ($usr_archive)==1?" selected" : ""); ?> >Inativos</option>            
                 </select>    
                 
	  </div>&nbsp;&nbsp;
       <div class="form-group">                
  				<label for="usr_nuc">Núcleo: </label>&nbsp;            
                <select name="usr_nuc" id="usr_nuc" onchange="javascript:frm_filtro.submit();" class="form-control">
                    <option value="-1" <?php echo( ($usr_nuc)==-1?" selected" : ""); ?> >TODOS</option>
                    <option value="-1">-------------</option>                     
                    <?php
                        
                        $sql = "SELECT nuc_id, nuc_nome_curto, nuc_archive ";
                        $sql.= "FROM nucleos ";
                        $sql.= "ORDER BY nuc_archive, nuc_nome_curto ";
                        $res = executa_sql($sql);
                        if($res)
                        {
						  $arquivados=0;
                          while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
                          {
							 if(!$arquivados)
							 {
								 if($row["nuc_archive"]==1) 
								 {
									 echo("<option value='-1'>-------------</option>");									 
									 $arquivados=1;
								 }
							 }
                             echo("<option value='" . $row['nuc_id'] . "'");
                             if($row['nuc_id']==$usr_nuc) echo(" selected");
                             echo (">" . $row['nuc_nome_curto'] . "</option>");
                          }
                        }
                    ?>                        
                </select>                           
             </div>     
                    
     </fieldset>
</form>

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
		
			</tr>
		</thead>
		<tbody>
				<?php
					
					$sql = "SELECT usr_id, usr_associado, usr_nome_curto, usr_nome_completo, usr_email, usr_archive, nuc_nome_curto ";
					$sql.= "FROM usuarios LEFT JOIN nucleos ON usr_nuc = nuc_id ";	
					$sql.= "WHERE 1 ";
					if($usr_archive!=-1) $sql.= " AND usr_archive = ' " . $usr_archive .  " ' ";
					if($usr_nuc!=-1) 	 $sql.= " AND usr_nuc = '" . $usr_nuc .  "' ";						
					$sql.= "ORDER BY nuc_nome_curto, usr_archive, usr_nome_completo ";
								
					$res = executa_sql($sql);
					$contador = 0;
				    if($res)
					{
					 while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
				     {
						$classe_arquivado = ($row['usr_archive'] == 0) ? "": " class='warning'";
						$icone_arquivado = ($row['usr_archive'] == 0) ? "": " <i class='glyphicon glyphicon-inbox'></i> ";
				?>				 
				  <tr <?php echo( $classe_arquivado); ?>>
                  	 <td><?php echo(++$contador);?></td>               
					 <td><?php echo($row['nuc_nome_curto']);?></td>   
					 <td><?php echo($row['usr_associado']? "Sim" : "Não"); ?></td>                                    
					 <td><a href="cestante.php?action=0&amp;usr_id=<?php echo( $row['usr_id']);?>"><?php echo($icone_arquivado);?> <?php echo($row['usr_nome_completo']);?></a></td>
                     <td><?php echo($row['usr_nome_curto']);?></td> 
					 <td><?php echo($row['usr_email']);?> </td>                     

				  </tr>
				<?php 
				     }
				   }
				?>
		</tbody>
	</table>
    
    </div>

 <span class="pull-right"><a href="cestante.php?action=<?php echo(ACAO_INCLUIR);?>" class="btn btn-default"><i class="glyphicon glyphicon-plus"></i> adicionar novo</a></span> 

<?php 
 
	footer();
?>
