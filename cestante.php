<?php  
  require  "common.inc.php"; 

	$action = request_get("action",-1);
	if($action==-1) redireciona(PAGINAPRINCIPAL);
	$usr_id =  request_get("usr_id","");
  
    verifica_seguranca($_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO] || ($_SESSION["usr.id"]== $usr_id) );
  
  top();
?>

<?php

		$gera_primeira_senha = request_get("gera_primeira_senha","");

		if($gera_primeira_senha!="")
		{
			$sucesso_cria_senha = gera_primeira_senha_acesso($usr_id);

			if($sucesso_cria_senha)
			{
				adiciona_mensagem_status(MSG_TIPO_SUCESSO,"Senha de primeiro acesso foi gerada e enviada para o email principal do cestante, com cópia para os emails alternativos.");				
			}
			else
			{
				adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar gerar senha de primeiro acesso e enviá-la ao cestante.");					
			}
			escreve_mensagem_status();			
		}
		
		
		if ( $action == ACAO_INCLUIR) // exibe formulário vazio para inserir novo registro
		{
			$usr_nome_completo = "";
			$usr_nome_curto = "";
			$usr_email = "";
			$usr_email_alternativo = "";			
			$usr_contatos = "";
			$usr_endereco = "";
			$usr_nuc = "";
			$usr_archive = "";		
			$nuc_nome_curto = "";		
			$usr_associado=0;
			$usr_desde="";			
		}
		else if ($action == ACAO_SALVAR) // salvar formulário preenchido
		{			
			 $sucesso=false;			 
			 $salvar = true;
			 
 			 $sql = "SELECT usr_nome_completo FROM usuarios WHERE usr_email=" . prep_para_bd(request_get('usr_email',""));
			 $sql.= " AND usr_id <> " . prep_para_bd($usr_id) ;
			 $res = executa_sql($sql);
			 if($res && mysqli_num_rows($res))
			 {
				$row = mysqli_fetch_array($res,MYSQLI_ASSOC);
			 	$usr_outro = $row["usr_nome_completo"];
				adiciona_mensagem_status(MSG_TIPO_ERRO,"Já existe cestante cadastrado com o email " . request_get('usr_email',""). ". Favor informar outro e tantar salvar novamente; ou então solicitar ao responsável no núcleo a utilização da respectiva conta, hoje em nome de '$usr_outro'.");		
				$salvar=false;		
			 }	
			 
			 $sql = "SELECT usr_nome_completo FROM usuarios WHERE usr_nuc = " . prep_para_bd(request_get('usr_nuc',""));
			 $sql.= " AND usr_nome_curto = " . prep_para_bd(request_get('usr_nome_curto',"")) ;
			 $sql.= " AND usr_archive='0' AND usr_id <> " . prep_para_bd($usr_id) ;
			 $res = executa_sql($sql);
			 if($res && mysqli_num_rows($res))			 
			 {
				 $row = mysqli_fetch_array($res,MYSQLI_ASSOC);
			 	$usr_outro = $row["usr_nome_completo"];
				adiciona_mensagem_status(MSG_TIPO_ERRO,"Já existe cestante neste núcleo cadastrado com o nome curto " . request_get('usr_nome_curto',""). ". Favor informar outro e tantar salvar novamente; ou então solicitar ao responsável no núcleo a utilização do respectivo nome, hoje associado a '$usr_outro'.");				
				$salvar = false;
			 }
			 
			 if($salvar)		 
			 {				 									
				 $campos = array('usr_nome_completo','usr_nome_curto','usr_contatos','usr_endereco','usr_email','usr_email_alternativo','usr_nuc','usr_archive','usr_associado');  			
				 $sql=prepara_sql_atualizacao("usr_id",$campos,"usuarios");				 
				 $res = executa_sql($sql);				 
				 if($usr_id=="") $usr_id = id_inserido();	
				 if($res)
				 {
					  $sucesso = true;
					  $usr_desde = request_get("usr_desde","");
					  
					  if ($usr_desde=="") $bd_usr_desde = 'Null';
					  else $bd_usr_desde = prep_para_bd(formata_data_para_mysql($usr_desde));
					  
 					  $sql = "UPDATE usuarios SET usr_desde = " . $bd_usr_desde;
 					  $sql.= " WHERE usr_id=". prep_para_bd($usr_id) . " ";	
					  $res = executa_sql($sql);			 		 						 						 
					  if(!$res) $sucesso=false;					 
				 }
				 if(!$sucesso)
				 {
					adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar salvar informações do(a) cestante " . $_REQUEST["usr_nome_curto"] . ".");								 
				 }
			 }
			 
			 if($sucesso) 
			 {
				$action=ACAO_EXIBIR_LEITURA; //volta para modo visualização somente leitura
				adiciona_mensagem_status(MSG_TIPO_SUCESSO,"Informações do(a) cestante " . $_REQUEST["usr_nome_curto"] . " salvas com sucesso.");				
			 }
			 else
			 {
				
				$usr_nome_completo = request_get('usr_nome_completo',"");
				$usr_nome_curto = request_get('usr_nome_curto',"");
				$usr_email = request_get('usr_email',"");
				$usr_email_alternativo = request_get('usr_email_alternativo',"");			
				$usr_contatos = request_get('usr_contatos',"");
				$usr_endereco = request_get('usr_endereco',"");
				$usr_nuc = request_get('usr_nuc',"");
				$usr_archive = request_get('usr_archive',"");		
				$nuc_nome_curto = "";		
				$usr_associado=request_get('usr_associado',0);	
				$usr_associado=request_get('usr_desde',"");	
							
				
			 }
			escreve_mensagem_status();
			 
		 
		}


		if ($action == ACAO_EXIBIR_LEITURA || $action == ACAO_EXIBIR_EDICAO)  // exibir para visualização, ou exibir para edição
		{
		  $sql = "SELECT usr_nome_completo, usr_nome_curto, usr_email, usr_email_alternativo, usr_contatos, usr_endereco, usr_nuc, usr_archive, usr_associado, DATE_FORMAT(usr_desde,'%d/%m/%Y') usr_desde, usr_senha is null as usr_sem_senha_acesso, nuc_nome_curto FROM usuarios LEFT JOIN nucleos ON usr_nuc = nuc_id WHERE usr_id=". prep_para_bd($usr_id);
 		  $res = executa_sql($sql);
  	      if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		  {		  
			$usr_nome_completo = $row["usr_nome_completo"];
			$usr_nome_curto = $row["usr_nome_curto"]; 
			$usr_email = $row["usr_email"]; 
			$usr_email_alternativo = $row["usr_email_alternativo"]; 			
			$usr_contatos = $row["usr_contatos"];
			$usr_endereco = $row["usr_endereco"];
			$usr_nuc = $row["usr_nuc"];						
			$usr_archive = $row["usr_archive"];
			$usr_associado = $row["usr_associado"];			
			$nuc_nome_curto = $row['nuc_nome_curto'];
			$usr_sem_senha_acesso = $row['usr_sem_senha_acesso'];
			$usr_desde = $row['usr_desde'];

		   }
		}		
		
?>

<?php 
 if($action==ACAO_EXIBIR_LEITURA)  //visualização somente leitura
 {
 ?>	  
	<legend>Informações do Cestante</legend>
<table class="table-condensed">
		<tbody>
    		<tr>
				<th align="right" class="span3">Nome Completo:</th> <td><?php echo($usr_nome_completo); ?></td>
			</tr>	    
    		<tr>
				<th align="right">Nome Curto:</th> <td><?php echo($usr_nome_curto); ?></td>
			</tr>            
    		<tr>
				<th align="right">Email Principal:</th>	<td><?php echo($usr_email); ?></td>
			</tr>

    		<tr>
				<th align="right">Emails Adicionais:</th>	<td><?php echo($usr_email_alternativo); ?></td>
			</tr>
            
    		<tr>
				<th align="right">Contatos:</th> <td><?php echo($usr_contatos); ?></td>
			</tr>        
    		<tr>
				<th align="right">Endereço:</th> <td><?php echo($usr_endereco); ?></td>
			</tr>    
	   		<tr>
				<th align="right">Data de Entrada:</th> <td><?php echo($usr_desde); ?></td>
			</tr>                   
             
    		<tr>
				<th align="right">Núcleo:</th> <td><?php echo($nuc_nome_curto); ?></td>
			</tr> 
    		<tr>
				<th align="right">Situação:</th> 
                <td>
					<?php echo( ($usr_archive==1)?"Inativo":"Ativo"); ?>
                 
                     <?php 
                if($usr_archive==0 && $usr_sem_senha_acesso && ($_SESSION[PAP_ADM]  || $_SESSION[PAP_RESP_NUCLEO] || $_SESSION[PAP_RESP_PEDIDO] ) )
                 {							 
                 ?>               
                    <span class='text-error'> (sem senha cadastrada)</span> &nbsp;
				<button type="button" class="btn btn-danger btn-small btn-enviando" data-loading-text="gerando..." onclick="javascript:location.href='cestante.php?action=<?php echo(ACAO_EXIBIR_LEITURA); ?>&usr_id=<?php echo($usr_id); ?>&gera_primeira_senha=1'">
            <i class="icon-lock icon-white"></i> Gerar primeira senha de acesso</button>
                         		                     
                 <?php 					 
                 }
                 
                 ?>
                 
                </td>
			</tr>                                    
    		<tr>
				<th align="right">Associado:</th> <td><?php echo( ($usr_associado==1)?"Sim":"Não"); ?></td>
			</tr>
                
			                                    
       
            <tr><td colspan="2"></td></tr>
            <tr> 
              <th>&nbsp;</th>  
              <td> 
         	 	<a class="btn btn-primary" href="cestante.php?action=<?php echo(ACAO_EXIBIR_EDICAO); ?>&usr_id=<?php echo($usr_id); ?>"><i class="icon-edit icon-white"></i> editar</a>
         	&nbsp;&nbsp;
	         		<?php 
					if($_SESSION[PAP_ADM]  || $_SESSION[PAP_RESP_NUCLEO] || $_SESSION[PAP_RESP_PEDIDO] )
					  { 
					 ?>
                     	  <a class="btn" href="cestantes.php"><i class="icon-list"></i> listar cestantes</a> 
            		<?php
					   } // fim do if tem permisao
					
					?>
                    
              </td>            
            </tr>
        </tbody>
    
</table>
  
   
	
<?php 

	
 }
 else  //visualização para edição
 {

?>
    <form id="form_cestante" class="form-horizontal" action="cestante.php" method="post">
     <legend>Atualização de Informações do Cestante</legend>    
        <fieldset>
          <input type="hidden" name="usr_id" value="<?php echo($usr_id); ?>" />
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />  
            <div class="control-group">
               <label class="control-label" for="usr_nome_completo">Nome Completo</label>
                 <div class="controls">
                   <input type="text" name="usr_nome_completo" class="input-xlarge" required="required" value="<?php echo($usr_nome_completo); ?>" placeholder="Nome Completo"/>
                  </div>
            </div>
            
            <div class="control-group">
               <label class="control-label" for="usr_nome_curto">Nome Curto</label>
                 <div class="controls">
                   <input type="text" class="input-small" name="usr_nome_curto"  required="required" value="<?php echo($usr_nome_curto); ?>" placeholder="Nome Curto" />
                   <span class="help-inline">Preferencialmente com no máximo 10 caracteres, para economizar na impressão de relatório de pedidos</span> 
                 </div>  
            </div>
            
             <div class="control-group">
                   <label class="control-label" for="usr_email">Email Principal </label>
                   <div class="controls">   
                    <input type="text" class="input-xlarge" required="required" id="usr_email" name="usr_email" value="<?php echo($usr_email); ?>" placeholder="Email" /><br />
                    <span class="help-inline"> É o principal email de contato. E é utilizado para identificar a associação no sistema (login).</span>
    			   </div>
            </div>        
       

             <div class="control-group">
                <label class="control-label" for="usr_email_alternativo">Emails Adicionais</label>
                  <div class="controls">
                    <textarea name="usr_email_alternativo" id="usr_email_alternativo" rows="3" class="input-xlarge" placeholder="Emails adicionais para recebimento das comunicações."><?php echo($usr_email_alternativo); ?></textarea><span class="help-inline">Emails adicionais para recebimento das comunicações, bastante útil para quem compartilha associação.<br>Informar valores separados por vírgula (ex.: fulano@dominio.com.br, ciclano@dominio.com.br)</span>
                  </div>
            </div>
                      
       
             <div class="control-group">
                <label class="control-label" for="usr_contatos">Contatos</label>
                  <div class="controls">
                    <textarea name="usr_contatos" rows="2" required="required"  class="input-xlarge" placeholder="ex.: 8888-9999/2333-4567"><?php echo($usr_contatos); ?></textarea>
                    <br>
                    <span class="help-inline">Contatos (telefone celular, fixo,...). Ex.: 8888-9999 / 2333-4567</span>
                  </div>
            </div>
          
          
            <div class="control-group">
                <label class="control-label" for="usr_endereco">Endereço</label>
                  <div class="controls">
                    <textarea name="usr_endereco" rows="4"  class="input-xlarge" placeholder="Endereço"><?php echo($usr_endereco); ?></textarea>
                  </div>
            </div>  
            
            <div class="control-group">
                <label class="control-label" for="usr_desde">Data de Entrada</label>
                  <div class="controls">
                  	<input type="text"  value="<?php echo($usr_desde); ?>" class="data input-small" name="usr_desde" id="usr_desde"/ >                    Ex.: 01/09/2010
                  </div>
            </div>             


			<?php 
			   if($_SESSION[PAP_ADM]  || $_SESSION[PAP_RESP_NUCLEO] || $_SESSION[PAP_RESP_PEDIDO] )
                  { 
             ?>
                     <div class="control-group">
                      <label class="control-label" for="usr_nuc">Núcleo</label>
                      <div class="controls">                                       
                        <select name="usr_nuc" id="usr_nuc">
                            <option value="-1">SELECIONAR</option>
                            <?php
                                $sql = "SELECT nuc_id, nuc_nome_curto ";
                                $sql.= "FROM nucleos ";
                                $sql.= "ORDER BY nuc_archive, nuc_nome_curto ";
                                $res2 = executa_sql($sql);
                                if($res2)
                                {
                                  while ($row = mysqli_fetch_array($res2,MYSQLI_ASSOC)) 
                                  {
                                     echo("<option value='" . $row['nuc_id'] . "'");
                                     if($row['nuc_id']==$usr_nuc) echo(" selected");
                                     echo (">" . $row['nuc_nome_curto'] . "</option>");
                                  }
                                }
                            ?>                        
                        </select>                
                      </div>                  
                    </div>  
    
           
                     <div class="control-group">
                       <label class="control-label" for="usr_archive">Situação</label>
                       <div class="controls">                
                         <select name="usr_archive" id="usr_archive">
                            <option value="0" <?php echo( ($usr_archive)==0?" selected" : ""); ?> >Ativo</option>
                            <option value="1" <?php echo( ($usr_archive)==1?" selected" : "");?> >Inativo</option>            
                         </select>                       
                                                     
                       </div>
                     </div>
                     

                     
              
                     <div class="control-group">
                       <label class="control-label" for="usr_associado">Associado</label>
                       <div class="controls">                
                         <select name="usr_associado" id="usr_associado">
                            <option value="1" <?php echo( ($usr_associado)==1?" selected" : ""); ?> >Associado</option>
                            <option value="0" <?php echo( ($usr_associado)==0?" selected" : ""); ?> >Não Associado</option>            
                         </select>                       
                       </div>
                     </div>                 


            <?php    
				  } // fim do if tem permissão edição
				  else
				  {
					 ?> 
                     <div class="control-group">
                      <label class="control-label" for="usr_nuc">Núcleo</label>
                      <div class="controls">                                       
                       	<span class="well well-small"><?php echo($nuc_nome_curto); ?></span>
                      </div>                  
                    </div>  
    
           
                     <div class="control-group">
                       <label class="control-label" for="usr_archive">Situação</label>
                       <div class="controls">                
							<span class="well well-small"><?php echo(($usr_archive)==0?"Ativo" : "Não Ativo"); ?></span>
	                  </div>
                     </div>              
                     <div class="control-group">
                       <label class="control-label" for="usr_associado">Associado</label>
                       <div class="controls">                
							<span class="well well-small"><?php echo(($usr_associado)==1?"Sim" : "Não"); ?></span>
                       </div>
                     </div>                 
		
            <?php		  
				  } // fim do if (tem permissão)
            ?>
                    

            
             
           <!--<div class="form-actions">-->
		  <div class="control-group">
            <div class="controls">
                   <button class="btn btn-primary" type="submit"><i class="icon-ok icon-white"></i> salvar alterações</button>
                   &nbsp;&nbsp;
                   <?php 
				   
				   if($usr_id!="") 
				   {
				   ?>
                   <button class="btn" type="button" onclick="javascript:location.href='cestante.php?&action=<?php echo(ACAO_EXIBIR_LEITURA); ?>&usr_id=<?php echo($usr_id); ?>'"><i class="icon-off"></i> descartar alterações</button>
                   
				   <?php 
				   }
				   else
				   {
				   ?>
					<button class="btn" type="button" onclick="javascript:location.href='cestantes.php'"><i class="icon-off"></i> descartar alterações</button>                  
                   
                   <?php
				   }
				   ?>
                               
            </div>
          </div>
      </fieldset> 
    </form>
    
    
<script type="text/javascript">
	$(function() {
		$(".data").datepicker({
			format: 'dd/mm/yyyy',
			language: 'pt-BR',
			autoclose: true
		});

		$("#form_cestante").submit(validaCestante);
	}); 
	
			
</script> 

	  

</script> 
    <?php  
   }

   footer();
?>
