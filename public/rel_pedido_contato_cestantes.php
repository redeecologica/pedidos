<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO] || $_SESSION[PAP_ACOMPANHA_RELATORIOS]  || $_SESSION[PAP_RESP_ENTREGA]  || $_SESSION[PAP_RESP_FINANCAS]);  
  top();
  
  
 $cha_id=request_get("cha_id",0);
 $ped_nuc=request_get("nuc_id",0);
                      
 $sql = "SELECT prodt_nome, DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega ";
 $sql.= "FROM chamadas LEFT JOIN produtotipos ON prodt_id = cha_prodt ";
 $sql.= "WHERE cha_id = " . prep_para_bd($cha_id);
 $res = executa_sql($sql);
 $row = mysqli_fetch_array($res,MYSQLI_ASSOC);  
 if(!$res) redireciona(PAGINAPRINCIPAL);

 $prodt_nome = $row["prodt_nome"];
 $cha_dt_entrega = $row["cha_dt_entrega"];
 
 if($ped_nuc !=0)
 {
	$sql = "SELECT nuc_nome_curto FROM nucleos WHERE nuc_id = " .  prep_para_bd($ped_nuc);
	 $res = executa_sql($sql);
	 $row = mysqli_fetch_array($res,MYSQLI_ASSOC);	 
	 $nuc_nome_curto = $row["nuc_nome_curto"];
 }


?>

<legend>Contato dos cestantes<?php if($ped_nuc!=0) echo(" de " . $nuc_nome_curto);?> que fizeram Pedido de <?php echo($prodt_nome . " - " . $cha_dt_entrega);?></legend>

 		  <input class="btn btn-success" type="button" value="selecionar tabela para copiar" 
           onclick="selectElementContents( document.getElementById('selectable') );">
           <br><br> 
       
	<table id="selectable" class="table table-striped table-bordered">
		<thead>
	 		<tr>
				<th>#</th>
				<th>Núcleo</th>                
				<th>Associado</th>                
				<th>Nome Curto</th>
        		<th>Nome Completo</th>
				<th>Contatos</th>                                
				<th>Email Principal</th>
				<th>Emails Adicionais</th>                		
			</tr>
		</thead>
		<tbody>
				<?php
					
					$sql = "SELECT usr_id, usr_associado, usr_nome_curto, usr_nome_completo, usr_contatos, nuc_nome_curto, ";
					$sql.= "usr_email, usr_email_alternativo ";
					$sql.= "FROM pedidos ";
					$sql.= "LEFT JOIN usuarios on ped_usr = usr_id  ";
					$sql.= "LEFT JOIN nucleos on ped_nuc = nuc_id  ";										
					$sql.= "WHERE ped_fechado='1' ";
					$sql.= "AND ped_cha= " . prep_para_bd($cha_id) . " ";				
					if($ped_nuc!=0) $sql.= "AND ped_nuc= " . prep_para_bd($ped_nuc) . " ";									
					$sql.= "ORDER BY nuc_nome_curto, usr_nome_curto ";
								
					$res = executa_sql($sql);
					$contador = 0;
				    if($res)
					{
					 while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
				     {
				?>				 
                  	 <td><?php echo(++$contador);?></td>               
                     <td><?php echo($row['nuc_nome_curto']);?></td> 
					 <td><?php echo($row['usr_associado']? "Sim" : "Não"); ?></td>                                    
                     <td><?php echo($row['usr_nome_curto']);?></td> 
                     <td><?php echo($row['usr_nome_completo']);?></td> 
                     <td><?php echo($row['usr_contatos']);?></td>                      
					 <td><?php echo($row['usr_email']);?> </td>                     
					 <td><?php echo($row['usr_email_alternativo']);?> </td>
				  </tr>
				<?php 
				     }
				   }
				?>
		</tbody>
	</table>


<?php 
 
	footer();
?>