<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_ADM]);
  
  top();
?>

<?php

		$action = request_get("action",-1);
		if($action==-1) redireciona(PAGINAPRINCIPAL);

		$usr_id =  request_get("usr_id","");
		$pap_id =  request_get("pap_id","");		

		if ( $action == ACAO_INCLUIR) // exibe formulário vazio para inserir novo registro
		{

		}		
		else if ( $action == ACAO_EXCLUIR) // exclui permissão
		{
			$sql = "DELETE FROM usuariopapeis  ";
			$sql.= " WHERE usrp_usr = " . prep_para_bd($usr_id) . " AND usrp_pap = " . prep_para_bd($pap_id) . " ";
 			$res = executa_sql($sql);
			 
			 if($res) 
			 {
				adiciona_mensagem_status(MSG_TIPO_SUCESSO,"Permissão excluída com sucesso.");				
			 }
			 else
			 {
				adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar excluir permissão.");			
			 }
			
			redireciona("permissoes.php?pap_id=" . $pap_id); 
		}		
		else if ($action == ACAO_SALVAR) // salvar formulário preenchido
		{	
		
			$sql = "INSERT INTO usuariopapeis (usrp_usr, usrp_pap, usrp_por_usr) ";
			$sql.= "VALUES (" . prep_para_bd($usr_id) . "," . prep_para_bd($pap_id) . ", ";
			$sql.= prep_para_bd($_SESSION['usr.id']) .  ") ";
			$sql.= "ON DUPLICATE KEY UPDATE ";
			$sql.= "usrp_por_usr = " . prep_para_bd($_SESSION['usr.id']);
	
	 		 $res = executa_sql($sql);
			 
			 
			 if($res) 
			 {
				adiciona_mensagem_status(MSG_TIPO_SUCESSO,"Permissão incluída com sucesso.");				
			 }
			 else
			 {
				adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar conceder permissão.");			
			 }
			
			redireciona("permissoes.php?pap_id=" . $pap_id); 
//			escreve_mensagem_status();
			
		 
		}


		
?>

<?php 
 if($action==ACAO_INCLUIR)  //visualização para inclusão
 {

?>
  <form id="form_permisao" class="form-horizontal" action="permissao.php" method="post">
    <fieldset>
    <div class="panel panel-default">
      <div class="panel-heading">
         <strong>Concessão de Permissão</strong>
      </div>
      
      <div class="panel-body">
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />  
          
                 <div class="form-group">
                   <label class="control-label col-sm-2" for="pap_id">Papel</label>
                   <div class="col-sm-4">
                    <select name="pap_id" id="pap_id" class="form-control">
                        <option value="-1">[Selecionar]</option>
                        <?php
                            
                            $sql = "SELECT pap_id, pap_nome FROM papeis ORDER BY pap_nome ";
                            $res = executa_sql($sql);
                            if($res)
                            {
                              while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
                              {
                                 echo("<option value='" . $row['pap_id'] . "'>" . $row['pap_nome'] . "</option>");
                              }
                            }
                        ?>                        
                    </select>            
                   </div>
                 </div>
          
                 <div class="form-group">
                   <label class="control-label col-sm-2" for="usr_id">Cestante</label>
                   <div class="col-sm-4">                
                     <select name="usr_id" id="usr_id" class="form-control">
                        <option value="-1">[Selecionar]</option>
                        <?php
                            
                            $sql = "SELECT usr_id, usr_nome_completo FROM usuarios ORDER BY usr_nome_completo ";
                            $res = executa_sql($sql);
                            if($res)
                            {
                              while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
                              {
                                 echo("<option value='" . $row['usr_id'] . "'>" . $row['usr_nome_completo'] . "</option>");
                              }
                            }
                        ?>                        
                    </select>                               
                    </div>
                 </div>                          
          
     </div> <!-- div panel-body --> 
     
  		<div class="panel-footer">          
		  <div class="form-group">
	          <div class="col-sm-offset-2">
                   <button class="btn btn-primary" type="submit"><i class="glyphicon glyphicon-ok glyphicon-white"></i> incluir permissão</button>
                   &nbsp;&nbsp;
                   <button class="btn btn-default" type="button" onclick="javascript:location.href='permissoes.php'"><i class="glyphicon glyphicon-off"></i> descartar alterações</button>
              </div>
          </div> <!-- div panel-footer --> 
          
               
  </div>  <!-- div panel -->     
            
          
      </fieldset> 
    </form>

    <?php  
   }

   footer();
?>
