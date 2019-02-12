<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_ADM]);
  top();
?>

<?php

		$action = request_get("action",-1);
		if($action==-1) redireciona(PAGINAPRINCIPAL);

		$nuct_id =  request_get("nuct_id","");		

		if ( $action ==  ACAO_INCLUIR) // inserir
		{
			$nuct_nome = "";
		}
		else if ($action == ACAO_SALVAR) // salvar formulário preenchido
		{			
 			 $campos = array('nuct_nome');  			
			 $sql=prepara_sql_atualizacao("nuct_id",$campos,"nucleotipos");
     		 $res = executa_sql($sql);
			 if($nuct_id=="") $nuct_id=id_inserido();	
			 
			 if($res) 
			 {
				$action=ACAO_EXIBIR_LEITURA; //volta para modo visualização somente leitura
				adiciona_mensagem_status(MSG_TIPO_SUCESSO,"Informações do tipo " . $_REQUEST["nuct_nome"] . " salvas com sucesso.");
			 }
			 else
			 {
				adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar salvar informações do tipo " . $_REQUEST["nuct_nome"] . ".");				 
			 }
			 escreve_mensagem_status();			 			 			 
			 		
		}
		

		if ($action == ACAO_EXIBIR_LEITURA || $action == ACAO_EXIBIR_EDICAO)  // exibir para visualização, ou exibir para edição		
		{
		  $sql = "SELECT * FROM nucleotipos WHERE nuct_id=". prep_para_bd($nuct_id) ;
 		  $res = executa_sql($sql);
  	      if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		  {		  
			$nuct_nome = $row["nuct_nome"];
		
		   }
		}		

?>

<?php 
 if($action==ACAO_EXIBIR_LEITURA)  //visualização somente leitura
 {
 ?>	  
 <div class="panel panel-default">
  <div class="panel-heading">
     <strong>Informações do Tipo de Núcleo</strong>
  </div>
 <div class="panel-body"> 

<table class="table-condensed table-info-cadastro">
		<tbody>
    		<tr>
				<th>Nome: </th> <td><?php echo($nuct_nome); ?></td>
			</tr>	    
                                  
        </tbody>
    
</table>
  
   </div>  
  
        <div class="panel-footer">
      		<div class="col-sm-offset-2">
         	 	<a class="btn btn-primary" href="nucleotipo.php?action=<?php echo(ACAO_EXIBIR_EDICAO); ?>&nuct_id=<?php echo($nuct_id); ?>"><i class="glyphicon glyphicon-edit glyphicon-white"></i> editar</a>
         	&nbsp;&nbsp;
         		<a class="btn btn-default" href="nucleotipos.php"><i class="glyphicon glyphicon-list"></i> listar tipos</a>
             </div>
       </div>
       
  </div>       
    
	
<?php 

	
 }
 else
 {

?>
<form id="form_nucleo" class="form-horizontal" action="nucleotipo.php" method="post">
    <fieldset>
    <div class="panel panel-default">
      <div class="panel-heading">
         <strong>Atualização de Informações do Tipo de Núcleo</strong>
      </div>
    
     <div class="panel-body">        
          <input type="hidden" name="nuct_id" value="<?php echo($nuct_id); ?>" />
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" /> 
            <div class="form-group">
               <label class="control-label col-sm-3" for="nuct_nome">Nome</label>
                 <div class="col-sm-4">
                   <input type="text" name="nuct_nome" class="form-control" required="required" value="<?php echo($nuct_nome); ?>"/>
                  </div>
            </div>
      
       
            
          
        </div>  <!-- div panel-body --> 
    
            <div class="panel-footer">          
              <div class="form-group">
                  <div class="col-sm-offset-2">
                   <button class="btn btn-primary" type="submit"><i class="glyphicon glyphicon-ok glyphicon-white"></i> salvar alterações</button>
                   &nbsp;&nbsp;
                   <button class="btn btn-default" type="button" onclick="javascript:location.href='nucleotipos.php'"><i class="glyphicon glyphicon-off"></i> descartar alterações</button>              </div>   
            </div>   <!-- div panel-footer -->        
         
      </div>  <!-- div panel -->            
      </fieldset> 
    </form>


    <?php  
   }

   footer();
?>
