<?php 

  require  "common.inc.php";  
  
  $usr_id = request_get("ui",""); 
  $cod_temp = request_get("temp","");   
  $nova_senha = request_get("login_usr_senha","");     
 
  $usr_nome="";
  $usr_email="";
  
  $sucesso = 0;
 
  if($usr_id!="" && $cod_temp!="" )
  {	 
  	$sql = "SELECT usr_email, usr_nome_curto FROM usuarioreiniciasenha, usuarios  ";
	$sql.= "WHERE usr_id = pass_usr ";
	$sql.= "AND usr_archive != '1' ";
	$sql.= " AND pass_codigo = " . prep_para_bd($cod_temp);	
	$sql.= " AND usr_id = " . prep_para_bd($usr_id);	
	$res = executa_sql($sql);	
	
	if($row = mysqli_fetch_array($res,MYSQLI_ASSOC))
	{
		$usr_nome = $row["usr_nome_curto"];
		$usr_email = $row["usr_email"];
		
		if($nova_senha!="")
		{
			if(strlen($nova_senha)>8)
			{
				adiciona_mensagem_status(MSG_TIPO_ERRO,"A senha deve conter no máximo 8 caracteres.");	
			}
			else
			{
				$sql = "UPDATE usuarios  SET usr_senha = " . prep_para_bd(crypt($nova_senha,PASSWORD_SALT));
				$sql.= " WHERE usr_id = " . prep_para_bd($usr_id);	
				$res = executa_sql($sql);	
				if(!$res)
				{
					adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar salvar nova senha.");	
				}
				else
				{
					$sql = "DELETE FROM usuarioreiniciasenha WHERE pass_usr = " . prep_para_bd($usr_id);	
					$res = executa_sql($sql);			
					adiciona_mensagem_status(MSG_TIPO_SUCESSO,"Nova senha criada com sucesso. Você agora pode fazer login com o seu email principal cadastrado e esta nova senha que foi criada.");
					redireciona(PAGINAPRINCIPAL);
				}
			}
						
		}
		
	}
	else
	{
		adiciona_mensagem_status(MSG_TIPO_ERRO,"O código para criação de nova senha foi expirado.");	
		redireciona(PAGINAPRINCIPAL);
	}

  }
  
  
  top();
  
  
?>


     <form class="form-signin" id="form_conf_senha" action="senha_zera.php" method="POST">

     	<input type="hidden" name="ui" value="<?php echo($usr_id); ?>"/>
     	<input type="hidden" name="temp" value="<?php echo($cod_temp); ?>"/>        
        

  
		<fieldset>
        <h2 class="form-signin-heading"><?php echo($usr_nome); ?>, favor informar a nova senha</h2>
	
        <label for="login_usr_senha">Nova senha: (até 8 digitos)</label> 
  		<div class="input-group"><span class="add-on"><i class="glyphicon glyphicon-lock"></i></span>
        	<input type="password" class="input-xlarge" maxlength="8" max="8" name="login_usr_senha" value="">       </div> 

        <label for="login_usr_senha_conf">Confirmar nova senha:</label> 
  		<div class="input-group"><span class="add-on"><i class="glyphicon glyphicon-lock"></i></span>
        	<input type="password" class="input-xlarge" maxlength="8" max="8" name="login_usr_senha_conf" value="">       </div> 

        
        <input class="btn btn-lg btn-primary" type="submit" value="Salvar senha" name="salvar_senha">
       </fieldset>
       <br>
			<div class="clear"></div>
			<div align="right">Já possui login e senha?&nbsp;<a href="login.php">Clique aqui para fazer login</a></div>
			<div align="right">Não tem cadastro?&nbsp;<a href="solicita_cadastro.php">Clique aqui para solicitar</a></div>	
			<div class="clear"></div>


      </form>
          
          
                  
        
  <script type="text/javascript">
	$(function() {
		$("form").submit(verificaSenha);
	}); 
  </script>  


  

  
<?php
footer();
 
?>