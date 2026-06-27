<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_ADM] || $_SESSION[PAP_RESP_FINANCAS]);
  top();
  
  
	$cha_id =  request_get("cha_id","");	
	$out_message ="";	
	
	
	
	if ( $cha_id !=  "") 
	{
		$sqls = array();
		$labels = array();
		
		$labels[]="Incluindo pedido de associação para todos os cestantes ativos que sejam associados e que não sejam conta ADM Sistema";
		$sqls[]="insert into pedidos (ped_usr, ped_usr_associado, ped_nuc, ped_cha, ped_fechado)
				select usr_id, usr_associado, usr_nuc, " . prep_para_bd($cha_id) . ", '1' 
				from usuarios
				left join nucleos on usr_nuc=nuc_id
				left join nucleotipos on nuc_nuct=nuct_id
				left join associacaotipos on usr_asso=asso_id
				where
				usr_archive=0
				AND usr_associado=1
				and asso_nome!='ADM Sistema'
				ON DUPLICATE KEY UPDATE
				ped_fechado = 1";

		$labels[]="Incluindo produto associação integral para cestantes núcleo semanal";
		$sqls[]="INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade)
				SELECT ped_id, '1544', '1'
				FROM pedidos
				left join usuarios on usr_id = ped_usr
				left join nucleos on usr_nuc=nuc_id
				left join nucleotipos on nuc_nuct=nuct_id
				left join associacaotipos on usr_asso=asso_id
				where ped_cha=" . prep_para_bd($cha_id) . "
				and asso_nome='Integral'
				and nuct_nome='Semanal'
				ON DUPLICATE KEY UPDATE
				pedprod_quantidade = 1";
				
		$labels[]="Incluindo produto associação integral para cestantes núcleo mensal";
		$sqls[]="INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade)
				SELECT ped_id, '1549', '1'
				FROM pedidos
				left join usuarios on usr_id = ped_usr
				left join nucleos on usr_nuc=nuc_id
				left join nucleotipos on nuc_nuct=nuct_id
				left join associacaotipos on usr_asso=asso_id
				where ped_cha=" . prep_para_bd($cha_id) . "
				and asso_nome='Integral'
				and nuct_nome='Mensal'
				ON DUPLICATE KEY UPDATE
				pedprod_quantidade = 1";
			
			
		$labels[]="Incluindo produto associação integral para cestantes núcleo quizenal";
		$sqls[]="INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade)
				SELECT ped_id, '1547', '1'
				FROM pedidos
				left join usuarios on usr_id = ped_usr
				left join nucleos on usr_nuc=nuc_id
				left join nucleotipos on nuc_nuct=nuct_id
				left join associacaotipos on usr_asso=asso_id
				where ped_cha=" . prep_para_bd($cha_id) . "
				and asso_nome='Integral'
				and nuct_nome='Quinzenal'
				ON DUPLICATE KEY UPDATE
				pedprod_quantidade = 1";				
				
				
		$labels[]="Incluindo produto meia-associação para cestantes núcleo semanal";
		$sqls[]="INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade)
				SELECT ped_id, '1546', '1'
				FROM pedidos
				left join usuarios on usr_id = ped_usr
				left join nucleos on usr_nuc=nuc_id
				left join nucleotipos on nuc_nuct=nuct_id
				left join associacaotipos on usr_asso=asso_id
				where ped_cha=" . prep_para_bd($cha_id) . "
				and asso_nome='Meia'
				and nuct_nome='Semanal'
				ON DUPLICATE KEY UPDATE
				pedprod_quantidade = 1";
				
		$labels[]="Incluindo produto associação integral para cestantes núcleo popular";
		$sqls[]="INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade)
				SELECT ped_id, '1548', '1'
				FROM pedidos
				left join usuarios on usr_id = ped_usr
				left join nucleos on usr_nuc=nuc_id
				left join nucleotipos on nuc_nuct=nuct_id
				left join associacaotipos on usr_asso=asso_id
				where ped_cha=" . prep_para_bd($cha_id) . "
				and asso_nome='Popular'
				-- and nuct_nome='Mensal'
				and nuc_nome_completo in ('São João de Meriti','Caxias')
				ON DUPLICATE KEY UPDATE
				pedprod_quantidade = 1";				
				
		$labels[]="Incluindo produto associação produtor popular para produtores cestantes de núcleo popular";
		$sqls[]="INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade)
				SELECT ped_id, '1553', '1'
				FROM pedidos
				left join usuarios on usr_id = ped_usr
				left join nucleos on usr_nuc=nuc_id
				left join nucleotipos on nuc_nuct=nuct_id
				left join associacaotipos on usr_asso=asso_id
				where ped_cha=" . prep_para_bd($cha_id) . "
				and asso_nome='Produtor'
				-- and nuct_nome='Mensal'
				and nuc_nome_completo in ('São João de Meriti','Caxias')
				ON DUPLICATE KEY UPDATE
				pedprod_quantidade = 1";
			
			
		$labels[]="Incluindo produto associação produtor para produtores padrão (não popular)";
		$sqls[]="INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade)
				SELECT ped_id, '1550', '1'
				FROM pedidos
				left join usuarios on usr_id = ped_usr
				left join nucleos on usr_nuc=nuc_id
				left join nucleotipos on nuc_nuct=nuct_id
				left join associacaotipos on usr_asso=asso_id
				where ped_cha=" . prep_para_bd($cha_id) . "
				and asso_nome='Produtor'
				-- and nuct_nome='Mensal'
				and nuc_nome_completo not in ('São João de Meriti','Caxias')
				ON DUPLICATE KEY UPDATE
				pedprod_quantidade = 1";				
				
		$labels[]="Incluindo produto associação isenta para cestantes isentos";
		$sqls[]="INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade)
				SELECT ped_id, '1551', '1'
				FROM pedidos
				left join usuarios on usr_id = ped_usr
				left join nucleos on usr_nuc=nuc_id
				left join nucleotipos on nuc_nuct=nuct_id
				left join associacaotipos on usr_asso=asso_id
				where ped_cha=" . prep_para_bd($cha_id) . "
				and asso_nome='Isento'
				ON DUPLICATE KEY UPDATE
				pedprod_quantidade = 1";
				

	
		
		$tudo_ok = true;
		
		$out_message = "<hr>";		
		foreach (array_combine($sqls, $labels) as $sql => $label) {
			$out_message .= $label . "...<br>";
			$res = executa_sql($sql);
			if($res){
				$out_message .= "Sucesso! ";
				$out_message .= registros_afetados() . " registros afetados.";
			}
			else
			{
				$tudo_ok = false;				
				$out_message .= "ERRO!! ";
			}
			$out_message .="<hr>";
		}	
		
		if($tudo_ok) 
		{			
			adiciona_mensagem_status(MSG_TIPO_SUCESSO,"Pedidos gerados com sucesso.");
		 }
		 else
		 {
			adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao incluir alguns pedidos.");				 
			
		 }		 
		 escreve_mensagem_status();			 		
	}
	
	

  
?>


<div class="panel panel-default">
  <div class="panel-heading">
     <strong>Criação Automática de Pedidos de Associação</strong>

 </div>  
 
 <div class="panel-body">

<form class="form-inline" action="script_gera_pedidos_associacao.php" method="post" name="frm" id="frm">

	<?php  
		$cha_id = request_get("cha_id",-1);
	?>
     <fieldset>
                 <div class="form-group">
  				<label for="cha_id">Chamada: </label>&nbsp;     
                <select name="cha_id" id="cha_id"  class="form-control">
                    <?php
					$sql = "SELECT cha_id, cha_prodt, cha_dt_entrega as cha_dt_entrega_original, date_format(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, prodt_nome ";
					$sql.= "FROM chamadas ";
					$sql.= "LEFT JOIN produtotipos ON cha_prodt = prodt_id ";	
					$sql.= "WHERE  ";
					#$sql.= " prodt_id = '10' ";						
					$sql.= " prodt_nome = 'Associação' ";											
					$sql.= "GROUP BY cha_id ";
					$sql.= "ORDER BY cha_dt_entrega_original DESC ";
					$sql.= "LIMIT 10 ";
					echo('sql=' . $sql);
													
					$res = executa_sql($sql);
					
					if($res)
					{
					  while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
					  {
						 echo("<option value='" . $row['cha_id'] . "'");
						 if($row['cha_id']==$cha_id) echo(" selected");
						 echo (">" . h($row['prodt_nome']) . " " . $row['cha_dt_entrega'] . "</option>");
					  }
					}

                    ?>                        
                </select>    
                
             &nbsp;&nbsp;&nbsp;
                <a class="btn btn-success confirm-delete" href="javascript:document.frm.submit();">
                    <i class="glyphicon glyphicon-send glyphicon-white"></i> Gerar Pedidos
                    </a>
                <!--
                 <input type="submit" value="Gerar Pedidos" class="btn btn-warning confirm-delete" />
                    -->                   
                 </div>     
                                           
                  
     </fieldset>
</form>
    </div>      
    

<?php 

	echo($out_message);
 
	footer();
?>