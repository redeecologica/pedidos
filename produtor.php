<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO]);
  top();
?>

<?php

		$action = request_get("action",-1);
		if($action==-1) redireciona(PAGINAPRINCIPAL);
		
		$forn_id =  request_get("forn_id","");


		if ( $action == ACAO_INCLUIR) // exibe formulário vazio para inserir novo registro
		{
			$forn_nome_completo = "";
			$forn_nome_curto = "";
			$forn_email = "";
			$forn_contatos = "";
			$forn_endereco = "";
			$forn_archive = "";
			$forn_id = "";
		
		}
		else if ($action == ACAO_SALVAR) // salvar formulário preenchido
		{
 			 $campos = array('forn_nome_completo','forn_nome_curto','forn_contatos','forn_endereco','forn_email','forn_archive');  			
			 $sql=prepara_sql_atualizacao("forn_id",$campos,"fornecedores");
     		 $res = executa_sql($sql);
			 if($res) 
			 {
				$action=ACAO_EXIBIR_LEITURA; //volta para modo visualização somente leitura
				adiciona_mensagem_status(MSG_TIPO_SUCESSO,"Informações do produtor " . $_REQUEST["forn_nome_curto"] . " salvas com sucesso.");				
				escreve_mensagem_status();
			 }		
			 
 			 if($forn_id=="") $forn_id = id_inserido();	
		}
		
		
		if ($action == ACAO_EXIBIR_LEITURA || $action == ACAO_EXIBIR_EDICAO)  // exibir para visualização, ou exibir para edição
		{
		  $sql = "SELECT * FROM fornecedores WHERE forn_id=" . prep_para_bd($forn_id) ;
 		  $res = executa_sql($sql);
  	      if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		  {		  
	  
			$forn_nome_completo = $row["forn_nome_completo"];
			$forn_nome_curto = $row["forn_nome_curto"];
			$forn_email = $row["forn_email"]; 
			$forn_contatos = $row["forn_contatos"];
			$forn_endereco = $row["forn_endereco"];			
			$forn_archive = $row["forn_archive"];
		   }
		}		

?>

<?php 
 if($action==ACAO_EXIBIR_LEITURA)  //visualização somente leitura
 {
 ?>	  
	<legend>Informações do Produtor</legend>
<table class="table-condensed">
		<tbody>
    		<tr>
				<th align="right" class="span3">Nome Completo:</th> <td><?php echo($forn_nome_completo); ?></td>
			</tr>	    
    		<tr>
				<th align="right">Nome Curto:</th> <td><?php echo($forn_nome_curto); ?></td>
			</tr>            
    		<tr>
				<th align="right">Email:</th>	<td><?php echo($forn_email); ?></td>
			</tr>
    		<tr>
				<th align="right">Contatos:</th> <td><?php echo($forn_contatos); ?></td>
			</tr>        
    		<tr>
				<th align="right">Endereço:</th> <td><?php echo($forn_endereco); ?></td>
			</tr>     
    		<tr>
				<th align="right">Situação:</th> <td><?php echo( ($forn_archive==1)?"Inativo":"Ativo"); ?></td>
			</tr>                                    
            <tr><td colspan="2"></td></tr>
            <tr> 
              <th>&nbsp;</th>  
              <td> 
         	 	<a class="btn btn-primary" href="produtor.php?action=<?php echo(ACAO_EXIBIR_EDICAO); ?>&forn_id=<?php echo($forn_id); ?>"><i class="icon-edit icon-white"></i> editar</a>
         	&nbsp;&nbsp;
         		<a class="btn" href="produtores.php"><i class="icon-list"></i> listar produtores</a>          </td>            
            </tr>
        </tbody>
    
</table>
  
   
	
<?php 

	
 }
 else
 {

?>
    <form class="form-horizontal" action="produtor.php" method="post">
     <legend>Atualização de Informações do Produtor</legend>    
        <fieldset>
          <input type="hidden" name="forn_id" value="<?php echo($forn_id); ?>" />
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />  
            <div class="control-group">
               <label class="control-label" for="forn_nome_completo">Nome Completo</label>
                 <div class="controls">
                   <input type="text" name="forn_nome_completo" required="required" value="<?php echo($forn_nome_completo); ?>" placeholder="Nome Completo" class="input-xxlarge"/>
                  </div>
            </div>
            
            <div class="control-group">
               <label class="control-label" for="forn_nome_curto">Nome Curto</label>
                 <div class="controls">
                   <input type="text" name="forn_nome_curto"  required="required" value="<?php echo($forn_nome_curto); ?>" placeholder="Nome Curto" />
                 </div>  
            </div>
            
             <div class="control-group">
                   <label class="control-label" for="forn_email">Email </label>
                   <div class="controls">   
                    <input type="text" class="input-xlarge" name="forn_email" value="<?php echo($forn_email); ?>" placeholder="Email" />
    			   </div>
            </div>        
          
       
             <div class="control-group">
                <label class="control-label" for="forn_contatos">Contatos</label>
                  <div class="controls">
                    <textarea name="forn_contatos" rows="2"  class="input-xlarge" placeholder="Contatos (telefone fixo, celular,...)"><?php echo($forn_contatos); ?></textarea>
                  </div>
            </div>
          
          
            <div class="control-group">
                <label class="control-label" for="forn_endereco">Endereço</label>
                  <div class="controls">
                    <textarea name="forn_endereco" rows="3"  class="input-xlarge" placeholder="Endereço"><?php echo($forn_endereco); ?></textarea>
                  </div>
            </div>  
       
                   <div class="control-group">
                <label class="control-label" for="forn_archive">Situação: </label>
                  <div class="controls">
                
                    <select name="forn_archive" id="forn_archive">
                        <option value="0" <?php echo( ($forn_archive ==0) ?" selected" : ""); ?> >Ativo</option>
                        <option value="1" <?php echo( ($forn_archive ==1) ?" selected" : ""); ?> >Inativo</option>            
                    </select>   
                    
                  </div>
            </div>  
            
           <!--<div class="form-actions">-->
		  <div class="control-group">
            <div class="controls">
                   <button class="btn btn-primary" type="submit"><i class="icon-ok icon-white"></i> salvar alterações</button>
                   &nbsp;&nbsp;
                   <button class="btn" type="button" onclick="javascript:location.href='produtores.php'"><i class="icon-off"></i> descartar alterações</button>
                                 
            </div>
          </div>
      </fieldset> 
    </form>
    
    <?php  
   }

   footer();
?>
