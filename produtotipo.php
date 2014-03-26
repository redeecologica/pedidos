<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO]);
  top();
?>

<?php

		$action = request_get("action",-1);
		if($action==-1) redireciona(PAGINAPRINCIPAL);

		$prodt_id =  request_get("prodt_id","");		

		if ( $action ==  ACAO_INCLUIR) // inserir
		{
			$prodt_nome = "";
			$prodt_mutirao = 0;	
		}
		else if ($action == ACAO_SALVAR) // salvar formulário preenchido
		{			
 			 $campos = array('prodt_nome','prodt_mutirao');  			
			 $sql=prepara_sql_atualizacao("prodt_id",$campos,"produtotipos");
     		 $res = executa_sql($sql);
			 if($prodt_id=="") $prodt_id=id_inserido();	
			 
			 if($res) 
			 {
				$action=ACAO_EXIBIR_LEITURA; //volta para modo visualização somente leitura
				adiciona_mensagem_status(MSG_TIPO_SUCESSO,"Informações do tipo " . $_REQUEST["prodt_nome"] . " salvas com sucesso.");
			 }
			 else
			 {
				adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar salvar informações do tipo " . $_REQUEST["prodt_nome"] . ".");				 
			 }
			 escreve_mensagem_status();			 			 			 
			 		
		}
		

		if ($action == ACAO_EXIBIR_LEITURA || $action == ACAO_EXIBIR_EDICAO)  // exibir para visualização, ou exibir para edição		
		{
		  $sql = "SELECT * FROM produtotipos WHERE prodt_id=". prep_para_bd($prodt_id) ;
 		  $res = executa_sql($sql);
  	      if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		  {		  
			$prodt_nome = $row["prodt_nome"];
			$prodt_mutirao = $row["prodt_mutirao"];
		
		   }
		}		

?>

<?php 
 if($action==ACAO_EXIBIR_LEITURA)  //visualização somente leitura
 {
 ?>	  
	<legend>Informações do Tipo de Produto / Chamada</legend>
<table class="table-condensed">
		<tbody>
    		<tr>
				<th align="right">Nome: </th> <td><?php echo($prodt_nome); ?></td>
			</tr>	    
 
    		<tr>
				<th align="right">Associado à funcionalidade Mutirão?</th> <td><?php echo( ($prodt_mutirao==1)?"Sim":"Não"); ?></td>
			</tr>                                    
            <tr><td colspan="2"></td></tr>
            <tr> 
              <th>&nbsp;</th>  
              <td> 
         	 	<a class="btn btn-primary" href="produtotipo.php?action=<?php echo(ACAO_EXIBIR_EDICAO); ?>&prodt_id=<?php echo($prodt_id); ?>"><i class="icon-edit icon-white"></i> editar</a>
         	&nbsp;&nbsp;
         		<a class="btn" href="produtotipos.php"><i class="icon-list"></i> listar tipos</a>          </td>            
            </tr>
        </tbody>
    
</table>
  
   
	
<?php 

	
 }
 else
 {

?>
    <form id="form_nucleo" class="form-horizontal" action="produtotipo.php" method="post">
     <legend>Atualização de Informações do Tipo de Produto/Chamada</legend>    
        <fieldset>
          <input type="hidden" name="prodt_id" value="<?php echo($prodt_id); ?>" />
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />  
            <div class="control-group">
               <label class="control-label" for="prodt_nome">Nome</label>
                 <div class="controls">
                   <input type="text" name="prodt_nome" required="required" value="<?php echo($prodt_nome); ?>"/>
                  </div>
            </div>
      
       
                   <div class="control-group">
                <label class="control-label" for="nuc_archive">Tem funcionalidade Mutirão? </label>
                  <div class="controls">
                
                    <select name="prodt_mutirao" id="prodt_mutirao">
                        <option value="0" <?php echo(($prodt_mutirao==0)?" selected" : ""); ?> >Não</option>
                        <option value="1" <?php echo(($prodt_mutirao==1)?" selected" : ""); ?> >Sim</option>            
                    </select>   
                    
                  </div>
            </div>  
            
           <!--<div class="form-actions">-->
		  <div class="control-group">
            <div class="controls">
                   <button class="btn btn-primary" type="submit"><i class="icon-ok icon-white"></i> salvar alterações</button>
                   &nbsp;&nbsp;
                   <button class="btn" type="button" onclick="javascript:location.href='produtotipos.php'"><i class="icon-off"></i> descartar alterações</button>
                                 
            </div>
          </div>
      </fieldset> 
    </form>


    <?php  
   }

   footer();
?>
