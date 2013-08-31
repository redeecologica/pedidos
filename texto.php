<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO]); 
  
  top();
?>


<?php

		$action = request_get("action",-1);
		if($action==-1) redireciona(PAGINAPRINCIPAL);
		
		if($action ==  ACAO_INCLUIR) verifica_seguranca($_SESSION[PAP_ADM]); 		

		$txt_id =  request_get("txt_id","");		
		
		if ( $action == ACAO_CONFIRMAR_PEDIDO)
		{			
			$sql = "UPDATE textos SET ";
			$sql.= "txt_conteudo_publicado = txt_conteudo_rascunho, ";
			$sql.= "txt_usr_atualizacao = " . prep_para_bd($_SESSION["usr.id"]);			
			$sql.= "WHERE txt_id = " . prep_para_bd($txt_id);
			$res = executa_sql($sql);			
			if($res) 
			{
				adiciona_mensagem_status(MSG_TIPO_SUCESSO,"Versão rascunho ativada com sucesso. Novo texto já está valendo. ");
			}
			else adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar ativar rascunho.");
			
			$action=ACAO_EXIBIR_LEITURA; // para visualizar no modo somente leitura						
			
		}

		if ( $action ==  ACAO_INCLUIR) // inserir
		{
			$txt_nome_completo = "";
			$txt_nome_curto = "";
			$txt_conteudo_rascunho = "";
			$txt_conteudo_publicado = "";			
			$txt_modo_html = 0;
		}
		else if ($action == ACAO_SALVAR) // salvar formulário preenchido
		{
			
 			 $campos = array('txt_nome_completo','txt_nome_curto','txt_conteudo_rascunho','txt_modo_html','txt_usr_atualizacao');  			
			 $sql=prepara_sql_atualizacao("txt_id",$campos,"textos");
     		 $res = executa_sql($sql);
			 if($txt_id=="") $txt_id=id_inserido();				 
			 if($res) 
			 {
				$action=ACAO_EXIBIR_LEITURA; //volta para modo visualização somente leitura
				adiciona_mensagem_status(MSG_TIPO_SUCESSO,"Informações do texto " . $_REQUEST["txt_nome_curto"] . " salvas com sucesso. Clique em 'ativar rascunho' para que esta versão passe a valer.");
			 }
			 else
			 {
				adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar salvar informações do texto " . $_REQUEST["txt_nome_curto"] . ".");				 
			 }
		 			 
			 		
		}		

		if ($action == ACAO_EXIBIR_LEITURA || $action == ACAO_EXIBIR_EDICAO)  // exibir para visualização, ou exibir para edição		
		{
		  $sql = "SELECT * FROM textos WHERE txt_id=". prep_para_bd($txt_id) ;
 		  $res = executa_sql($sql);
  	      if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		  {		  
			$txt_nome_completo = $row["txt_nome_completo"];
			$txt_nome_curto = $row["txt_nome_curto"];
			$txt_conteudo_rascunho = $row["txt_conteudo_rascunho"];
			$txt_conteudo_publicado = $row["txt_conteudo_publicado"];			
			$txt_modo_html = $row["txt_modo_html"];			
		
		   }
		}
		
		escreve_mensagem_status();		

?>

<?php 
 if($action==ACAO_EXIBIR_LEITURA)  //visualização somente leitura
 {
 ?>	  
	<legend>Informações do Texto</legend>
<table class="table-condensed">
		<tbody>
    		<tr>
				<th align="right">Nome Interno:</th> <td><?php echo($txt_nome_curto); ?></td>
			</tr>            
    		<tr>
				<th align="right" class="span3">Utilização:</th> <td><?php echo($txt_nome_completo); ?></td>
			</tr>	    
    		<tr>
				<th align="right" class="span3">Formato:</th> <td><?php echo($txt_modo_html ? "HTML" : "Texto Puro"); ?></td>
			</tr>   
             <tr> 
              <th>&nbsp;</th>  
              <td> 
         	 	<a class="btn btn-primary" href="texto.php?action=<?php echo(ACAO_EXIBIR_EDICAO); ?>&txt_id=<?php echo($txt_id); ?>"><i class="icon-edit icon-white"></i> editar conteúdo</a>        
                <?php 
				if($txt_conteudo_rascunho!=$txt_conteudo_publicado)
				{
					?>
                    &nbsp;&nbsp;
              	 	<a class="btn btn-warning confirm-delete" href="texto.php?action=<?php echo(ACAO_CONFIRMAR_PEDIDO); ?>&txt_id=<?php echo($txt_id); ?>">
                    <i class="icon-thumbs-up icon-white"></i> ativar rascunho
                    </a>

                    
                    <?php
				}

				?>
         	&nbsp;&nbsp;
         		<a class="btn" href="textos.php"><i class="icon-list"></i> listar outros textos</a>  
                                
               </td>            
            </tr>
        </tbody>
    
</table>


<hr />
<h4>Conteúdo Rascunho: </h4>
<hr />
<?php echo($txt_modo_html ? $txt_conteudo_rascunho : str_replace("\n","<br />",$txt_conteudo_rascunho) ); ?>

<hr />
<h4>Conteúdo Publicado:</h4>
<hr />
<?php echo($txt_modo_html ? $txt_conteudo_publicado : str_replace("\n","<br />",$txt_conteudo_publicado) ); ?>

     
	
<?php 

	
 }
 else
 {

?>
<script src="ckeditor/ckeditor.js"></script>

    <form id="form_texto" class="form-horizontal" action="texto.php" method="post">
     <legend>Atualização de Informações do Texto</legend>    
        <fieldset>
          <input type="hidden" name="txt_id" value="<?php echo($txt_id); ?>" />
          <input type="hidden" name="txt_usr_atualizacao" value="<?php echo($_SESSION["usr.id"]); ?>" /> 
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" /> 
         
           
           <?php 
		   	 if($_SESSION[PAP_ADM]) 
			 {
			  ?>
            <div class="control-group">
               <label class="control-label" for="txt_nome_curto">Nome Interno (cuidado ao alterar)</label>
                 <div class="controls">
                   <input type="text" name="txt_nome_curto"  required="required" value="<?php echo($txt_nome_curto); ?>" placeholder="Nome Interno" />
                 </div>  
            </div>
            
            <div class="control-group">
               <label class="control-label" for="txt_nome_completo">Utilização (cuidado ao alterar)</label>
                 <div class="controls">
                   <input type="text" name="txt_nome_completo" class="input-xxlarge" required="required" value="<?php echo($txt_nome_completo); ?>" placeholder="Utilização"/>
                  </div>
            </div>
            
            <div class="control-group">
               <label class="control-label" for="txt_modo_html">Formato (cuidado ao alterar)</label>
                 <div class="controls">            
                    <select name="txt_modo_html" id="txt_modo_html" class="input-medium">
                        <option value="0"  <?php echo( $txt_modo_html ? "" : " selected"); ?> >Texto Puro</option>
                        <option value="1"  <?php echo( $txt_modo_html ? " selected" : ""); ?> >HTML</option>            
                    </select>      
                  </div>
                  
              </div>


				 
			  <?php	 
			 }
			 else
			 {
			  ?>
              
            <div class="control-group">
               <label class="control-label" for="txt_nome_curto">Nome Interno</label>
                 <div class="controls">
                   <input type="hidden" name="txt_nome_curto"  value="<?php echo($txt_nome_curto); ?>"/>
                   <span class="well well-small"><?php echo($txt_nome_curto); ?></span>
                 </div>  
            </div>

            <div class="control-group">
               <label class="control-label" for="txt_nome_completo">Utilização</label>
                 <div class="controls">
                   <input type="hidden" name="txt_nome_completo"  value="<?php echo($txt_nome_completo); ?>"/>
                   <span class="well well-small"><?php echo($txt_nome_completo); ?></span>
                 </div>  
            </div>

            <div class="control-group">
               <label class="control-label" for="txt_modo_html">Formato</label>
                 <div class="controls">
                   <input type="hidden" name="txt_modo_html"  value="<?php echo($txt_modo_html); ?>"/>
                   <span class="well well-small"><?php echo($txt_modo_html ? "HTML" : "Texto Puro"); ?></span>
                 </div>  
            </div>
            
                          					 
			  <?php
			 }
		   
		   ?>
           
 
            
            <div class="control-group">
                <label class="control-label" for="txt_conteudo_rascunho">Conteúdo Rascunho</label>
                  <div class="controls">
                    <textarea class="<?php echo($txt_modo_html ? "ckeditor" : "input-xxlarge"); ?>" id="txt_conteudo_rascunho" name="txt_conteudo_rascunho" rows="30"><?php echo($txt_conteudo_rascunho); ?></textarea>
                  </div>
            </div>  
            
            
                   
      
            
           <!--<div class="form-actions">-->
		  <div class="control-group">
            <div class="controls">
                   <button class="btn btn-primary" type="submit"><i class="icon-ok icon-white"></i> salvar alterações</button>
                   &nbsp;&nbsp;
                   <button class="btn" type="button" onclick="javascript:location.href='textos.php'"><i class="icon-off"></i> descartar alterações</button>
                                 
            </div>
          </div>
      </fieldset> 
    </form>

    <?php  
   }

   footer();
?>
