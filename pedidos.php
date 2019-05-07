<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO]);
  top();
?>


<div class="panel panel-default">
  <div class="panel-heading">
     <strong>Lista de Pedidos</strong>
  </div>
 <div class="panel-body">

<form class="form-inline" action="pedidos.php" method="post" name="frm_filtro" id="frm_filtro">
	<?php  
  		$ped_cha = request_get("ped_cha",-1) ;
		$ped_nuc = request_get("ped_nuc",-1) ;
		$ped_status = request_get("ped_status","*");
	?>
     <fieldset>
     
     	<div class="form-group">
  				<label for="ped_cha">Chamada: </label>            
                 <select name="ped_cha" id="ped_cha" onchange="javascript:frm_filtro.submit();" class="form-control">
                 	<option value="-1">SELECIONE</option>
                    <?php
                        
                       $sql = "SELECT cha_id, prodt_nome, cha_dt_entrega cha_dt_entrega_original, DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega ";
                        $sql.= "FROM chamadas LEFT JOIN produtotipos ON prodt_id = cha_prodt ";
                        $sql.= "ORDER BY cha_dt_entrega_original DESC LIMIT 10";
						echo($sql);
                        $res = executa_sql($sql);
                        if($res)
                        {
						  while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
                          {							
                             echo("<option value='" . $row['cha_id'] . "'");
                             if($row['cha_id']==$ped_cha) echo(" selected");
                             echo (">" . $row['prodt_nome'] . " - " . $row['cha_dt_entrega'] . "</option>");
                          }
                        }
                    ?>                        
                 </select>    
		</div>                 
                 
                 &nbsp;&nbsp;
          <div class="form-group">          
  				<label for="ped_nuc">Núcleo: </label>            
                <select name="ped_nuc" id="ped_nuc" onchange="javascript:frm_filtro.submit();" class="form-control">
                    <option value="-1" <?php echo(($ped_nuc==-1)?" selected" : ""); ?> >TODOS</option>
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
                             if($row['nuc_id']==$ped_nuc) echo(" selected");
                             echo (">" . $row['nuc_nome_curto'] . "</option>");
                          }
                        }
                    ?>                        
                </select>                           
           </div>
             &nbsp;&nbsp;
           <div class="form-group">         
  				<label for="ped_status">Status Pedido: </label>            
                 <select name="ped_status" id="ped_status" onchange="javascript:frm_filtro.submit();" class="form-control">
                 	<option value="*" <?php if($ped_status=="*") echo("selected"); ?>>TODOS</option>
                 	<option value="-1"  <?php if($ped_status=="-1") echo("selected"); ?>>Sem Pedido</option>
                 	<option value="0"  <?php if($ped_status=="0") echo("selected"); ?>>Em elaboração</option>
                 	<option value="1"  <?php if($ped_status=="1") echo("selected"); ?>>Enviado</option>
                 </select>  
                                                            
           </div>
         </fieldset>
    </form>
    </div>
        

				<?php
					
					$sql = "SELECT ped_id, ped_cha, nucleos.nuc_id cest_nuc_id, usr_id, usr_email, usr_nome_curto, nucleo_entrega.nuc_nome_curto entrega_nuc_nome_curto, ";
					$sql.= "nucleos.nuc_nome_curto cest_nuc_nome_curto, ped_fechado, DATE_FORMAT(ped_dt_atualizacao,'%d/%m/%Y %H:%i') ped_dt_atualizacao, asso_nome, usr_archive ";
					$sql.= "FROM usuarios ";
					$sql.= "LEFT JOIN pedidos ON ped_usr = usr_id  AND ped_cha = " . prep_para_bd($ped_cha) .  " ";	
					$sql.= "LEFT JOIN associacaotipos ON usr_asso = asso_id ";
					$sql.= "LEFT JOIN nucleos ON usr_nuc = nucleos.nuc_id ";	
					$sql.= "LEFT JOIN nucleos nucleo_entrega ON ped_nuc = nucleo_entrega.nuc_id ";
					$sql.= "WHERE  (usr_archive = '0' or ( ped_id is not NULL) ) ";
					if($ped_cha==-1)$sql.= " AND 0 "; // não permite que mostre todos... este é um parâmetro obrigatório
					if($ped_nuc!=-1) $sql.= " AND nucleos.nuc_id = " . prep_para_bd($ped_nuc) .  " ";						
					if($ped_status!="*")
					{
						if($ped_status=="1") $sql.= " AND ped_fechado = 1 ";
						else if($ped_status=="0") $sql.= " AND ped_fechado = 0 ";
						else if($ped_status=="-1") $sql.= " AND ped_id is NULL ";
						
					}

					$sql.= "ORDER BY nucleos.nuc_nome_curto, usr_nome_curto ";
								
					$res = executa_sql($sql);
					$contador = 0;
				    if($res && mysqli_num_rows($res))
					{
				  ?>	

                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Núcleo</th>
                            <th>Cestante</th>
                            <th>Tipo Associação</th>
                            <th>Email</th>
                            <th>Última Atualização</th>
                            <th>Status do Pedido</th>		

                        </tr>
                    </thead>
                    <tbody>		

				<?php        				
					$fechados=$abertos=$nao_criados=0;
					 while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
				     {
						 if($row['ped_fechado']==1) $fechados++;
						 else if($row['ped_id'] && !$row['ped_fechado']) $abertos++;
						 else $nao_criados++;
				?>				 
                      <tr class="<?php echo( $row['ped_fechado']==1 ? "success": ($row['ped_id'] ? "info" : "")); ?>" >
                         <td><?php echo(++$contador);?></td>               
                         <td><?php echo($row['cest_nuc_nome_curto']);?> <?php if($row['entrega_nuc_nome_curto'] && $row['entrega_nuc_nome_curto']!=$row['cest_nuc_nome_curto']) echo("(entrega em ". $row['entrega_nuc_nome_curto'] . ")"); ?> </td>                         
                         <td><?php 
						 	echo($row['usr_nome_curto']); 
							if($row['usr_archive']) echo(" <span class='label label-danger'>inativo</span>");?>
                         </td>   
                         <td><?php echo($row['asso_nome']);?></td>                                   
						 <td><?php echo($row['usr_email']);?></td>                                                             
                         <td><?php echo($row['ped_dt_atualizacao']); ?></td>      
                         <td>
							 <?php
                                if(!$row['ped_id'])
                                {
                                    echo("Sem pedido <a class=\"btn btn-default btn-sm\" href=\"pedido.php?action=" . ACAO_INCLUIR . "&amp;ped_cha=" . $ped_cha);
                                    echo("&amp;ped_usr=" . $row['usr_id'] . "\">");
                                    echo("<i class=\"glyphicon glyphicon-plus\"></i> criar</a>");						
                                }
                                else
                                {
                                    if($row['ped_fechado']==1) echo("Pedido enviado ");
                                    else echo("Pedido em elaboração ");
                                                                
                                    echo("<a class=\"btn btn-default btn-sm\" href=\"pedido.php?action=0");
                                    echo("&amp;ped_id=" . $row['ped_id'] . "\">");
                                    echo("<i class=\"glyphicon glyphicon-search\"></i> ver</a>");						
        
                                    if($row['ped_fechado']!=1)
                                    {
                                        echo("&nbsp;<a class=\"btn btn-default btn-sm\" href=\"pedido.php?action=1");
                                        echo("&amp;ped_id=" . $row['ped_id'] . "\">");
                                        echo("<i class=\"glyphicon glyphicon-edit\"></i> editar</a>");
                                    }
                                }
                             ?>

                         </td>   
                       
                      </tr>
				<?php 
				     }
			 ?>

				</tbody>
			</table>

		 <div class="panel-footer" align="center">
              Em aberto: <?php echo($abertos);?> &nbsp;&nbsp;
              Enviado: <?php echo($fechados);?>
         </div>

	  </div>
      
		<?php 
				   }
				?>


<?php 
 
	footer();
?>