<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_ADM]);
  top();
?>

<?php

		$action = request_get("action",-1);
		if($action==-1) redireciona(PAGINAPRINCIPAL);

		$asso_id =  request_get("asso_id","");		

		if ( $action ==  ACAO_INCLUIR) // inserir
		{
			$asso_nome = "";
			$asso_descricao = "";
		}
		else if ($action == ACAO_SALVAR) // salvar formulário preenchido
		{			
 			 $campos = array('asso_nome','asso_descricao');  			
			 $sql=prepara_sql_atualizacao("asso_id",$campos,"associacaotipos");
     		 $res = executa_sql($sql);
			 if($asso_id=="") $asso_id=id_inserido();	
			 
			 if($res) 
			 {
				$action=ACAO_EXIBIR_LEITURA; //volta para modo visualização somente leitura
				adiciona_mensagem_status(MSG_TIPO_SUCESSO,"Informações do tipo " . $_REQUEST["asso_nome"] . " salvas com sucesso.");
			 }
			 else
			 {
				adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar salvar informações do tipo " . $_REQUEST["asso_nome"] . ".");				 
			 }
			 escreve_mensagem_status();			 			 			 
			 		
		}
		

		if ($action == ACAO_EXIBIR_LEITURA || $action == ACAO_EXIBIR_EDICAO)  // exibir para visualização, ou exibir para edição		
		{
		  $sql = "SELECT * FROM associacaotipos WHERE asso_id=". prep_para_bd($asso_id) ;
 		  $res = executa_sql($sql);
  	      if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		  {		  
			$asso_nome = $row["asso_nome"];
			$asso_descricao = $row["asso_descricao"];
		
		   }
		}		

?>

<?php 
 if($action==ACAO_EXIBIR_LEITURA)  //visualização somente leitura
 {
 ?>	  
 <div class="panel panel-default">
  <div class="panel-heading">
     <strong>Informações do Tipo de Associação</strong>
  </div>
 <div class="panel-body"> 

<table class="table-condensed table-info-cadastro">
		<tbody>
    		<tr>
				<th>Nome: </th> <td><?php echo($asso_nome); ?></td>
			</tr>	 
            
    		<tr>
				<th>Descrição: </th> <td><?php echo($asso_descricao); ?></td>
			</tr>	               
                                  
        </tbody>
    
</table>
  
   </div>  
  
        <div class="panel-footer">
      		<div class="col-sm-offset-2">
         	 	<a class="btn btn-primary" href="associacaotipo.php?action=<?php echo(ACAO_EXIBIR_EDICAO); ?>&asso_id=<?php echo($asso_id); ?>"><i class="glyphicon glyphicon-edit glyphicon-white"></i> editar</a>
         	&nbsp;&nbsp;
         		<a class="btn btn-default" href="associacaotipos.php"><i class="glyphicon glyphicon-list"></i> listar tipos</a>
             </div>
       </div>
       
  </div>       
    
	
<?php 

	
 }
 else
 {

?>
<form id="form_associacao" class="form-horizontal" action="associacaotipo.php" method="post">
    <fieldset>
    <div class="panel panel-default">
      <div class="panel-heading">
         <strong>Atualização de Informações do Tipo de Associação</strong>
      </div>
    
     <div class="panel-body">        
          <input type="hidden" name="asso_id" value="<?php echo($asso_id); ?>" />
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" /> 
            <div class="form-group">
               <label class="control-label col-sm-3" for="asso_nome">Nome</label>
                 <div class="col-sm-4">
                   <input type="text" name="asso_nome" class="form-control" required="required" value="<?php echo($asso_nome); ?>"/>
                  </div>
            </div>
      
            <div class="form-group">
               <label class="control-label col-sm-3" for="asso_descricao">Descrição</label>
                 <div class="col-sm-4">
                   <textarea name="asso_descricao" class="form-control"><?php echo($asso_descricao); ?></textarea> 
                  </div>
            </div>
                  
       
            
          
        </div>  <!-- div panel-body --> 
    
            <div class="panel-footer">          
              <div class="form-group">
                  <div class="col-sm-offset-2">
                   <button class="btn btn-primary" type="submit"><i class="glyphicon glyphicon-ok glyphicon-white"></i> salvar alterações</button>
                   &nbsp;&nbsp;
                   <button class="btn btn-default" type="button" onclick="javascript:location.href='associacaotipos.php'"><i class="glyphicon glyphicon-off"></i> descartar alterações</button>              </div>   
            </div>   <!-- div panel-footer -->        
         
      </div>  <!-- div panel -->            
      </fieldset> 
    </form>


    <?php  
   }

   footer();
?>
