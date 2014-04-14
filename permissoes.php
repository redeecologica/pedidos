<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_ADM]);
  top();
?>

<div class="panel panel-default">
  <div class="panel-heading">
     <strong>Lista de Permissões</strong>
       <span class="pull-right">
		<a href="permissao.php?action=<?php echo(ACAO_INCLUIR);?>" class="btn btn-default btn-xs"><i class="glyphicon glyphicon-plus"></i> conceder permissão</a>
	</span>
  </div>
 <div class="panel-body">

<form class="form-inline" action="permissoes.php" method="post" name="frm_filtro" id="frm_filtro">

	<?php  
		$usr_nuc = request_get("usr_nuc",-1) ;
		$pap_id = request_get("pap_id",-1) ;		

	?>

  <fieldset>
	  <div class="form-group">
  				<label for="usr_nuc">Núcleo: </label>            
                <select name="usr_nuc" id="usr_nuc" onchange="javascript:frm_filtro.submit();" class="form-control">
                    <option value="-1" <?php echo(($usr_nuc==-1)?" selected" : ""); ?> >TODOS</option>
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
                 &nbsp;   

      <div class="form-group">            
  				<label for="pap_id">Papel: </label>            
                <select name="pap_id" id="pap_id" onchange="javascript:frm_filtro.submit();" class="form-control">
                    <option value="-1" <?php echo( ($pap_id==-1)?" selected" : ""); ?> >TODOS</option>
                    <option value="-1">-------------</option>                     
                    <?php
                        
                        $sql = "SELECT pap_id, pap_nome ";
                        $sql.= "FROM papeis ";
                        $sql.= "ORDER BY pap_nome ";
                        $res = executa_sql($sql);
                        if($res)
                        {
                          while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
                          {
                             echo("<option value='" . $row['pap_id'] . "'");
                             if($row['pap_id']==$pap_id) echo(" selected");
                             echo (">" . $row['pap_nome'] . "</option>");
                          }
                        }
                    ?>                        
                </select>                                            
                    
     </fieldset>
</form>
       </div>
	<table class="table table-striped table-bordered table-hover">
		<thead>
			<tr>
				<th>#</th>			
				<th>Papel</th>
				<th>Remover</th>                
        		<th>Cestante</th>
				<th>Email</th>		
				<th>Núcleo</th>
			</tr>
		</thead>
		<tbody>
				<?php
					
					$sql = "SELECT usr_id, pap_id, pap_nome, usr_nome_completo, usr_email, nuc_nome_curto ";
					$sql.= "FROM usuariopapeis ";
					$sql.= "LEFT JOIN papeis ON usrp_pap = pap_id ";	
					$sql.= "LEFT JOIN usuarios ON usrp_usr = usr_id ";	
					$sql.= "LEFT JOIN nucleos ON usr_nuc = nuc_id ";	
					$sql.= "WHERE 1 ";
					if($usr_nuc!=-1) 	 $sql.= " AND usr_nuc = '" . $usr_nuc .  "' ";						
					if($pap_id!=-1) 	 $sql.= " AND usrp_pap = '" . $pap_id .  "' ";						
					$sql.= "ORDER BY pap_nome, usr_nome_completo ";
					$res = executa_sql($sql);
					$contador = 0;
				    if($res)
					{
					 while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
				     {
				?>				 
				  <tr>
                  	 <td><?php echo(++$contador);?></td>               
                     <td><?php echo($row['pap_nome']);?></td> 
		 <td><a href="permissao.php?action=<?php echo(ACAO_EXCLUIR);?>&amp;usr_id=<?php echo($row['usr_id']);?>&amp;pap_id=<?php echo($row['pap_id']);?>" class="confirm-delete btn btn-default"><i class="glyphicon glyphicon-remove"></i> remover</a></td>
					 <td><a href="cestante.php?action=<?php echo(ACAO_EXIBIR_LEITURA);?>&amp;usr_id=<?php echo($row['usr_id']);?>"><?php echo($row['usr_nome_completo']);?></a></td>
					 <td><?php echo($row['usr_email']);?> </td>                     
					 <td><?php echo($row['nuc_nome_curto'])?></td>               
				  </tr>
				<?php 
				     }
				   }
				?>
		</tbody>
	</table>
    
    </div>
    
       <span class="pull-right">
		<a href="permissao.php?action=<?php echo(ACAO_INCLUIR);?>" class="btn btn-default"><i class="glyphicon glyphicon-plus"></i> conceder permissão</a>
	</span>
                

<?php 
 
	footer();
?>