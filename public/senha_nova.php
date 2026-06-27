<?php 

  require  "common.inc.php";  
  
  $usr_email = request_get("login_usr_email",""); 
 
  $sucesso = false;
 
  if(isset($_POST["login_usr_email"]) )
  {	 
  	$sql = "SELECT usr_id, usr_nome_curto FROM usuarios WHERE ";
	$sql.= " usr_archive != '1' ";
	$sql.= " AND usr_email = " . prep_para_bd($_POST["login_usr_email"]);

	$res = executa_sql($sql);
	if($row = mysqli_fetch_array($res,MYSQLI_ASSOC))
	{
		$usr_id = $row['usr_id'];		
		$usr_nome_curto = $row['usr_nome_curto'];
		
		$base_codigo_temp = date("hi") . $usr_id . date("s");
		$codigo_temp = crypt($base_codigo_temp,PASSWORD_SALT);
		
		$sql = " INSERT INTO usuarioreiniciasenha (pass_usr, pass_codigo) VALUES (";
		$sql.= prep_para_bd($usr_id) . "," . prep_para_bd($codigo_temp)  .  ")";
		$res2 = executa_sql($sql);
		
		if($res2)
		{			
			$mensagem="Oi, $usr_nome_curto." . "\n\n";			
			$mensagem.="Segue o link para poder criar sua nova senha de acesso ao sistema de pedidos:" . "\n";		
			$mensagem.= URL_ABSOLUTA . "/senha_zera.php?ui=$usr_id&temp=" . urlencode($codigo_temp) . "\n\n";			

			$mensagem.=get_texto_interno("txt_email_final_info_conta");	
			
			$sucesso = envia_email_cestante($usr_id,'Informações para criar nova senha',"",$mensagem);	
	
		}
				
		if($sucesso)
		{
			adiciona_mensagem_status(MSG_TIPO_INFO,"As informações para criar a nova senha foram enviadas para o email " .$_POST["login_usr_email"] . ", com cópia para os emails adicionais informados em seu cadastro.");			
		}
		else
		{
			adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar enviar email com as informações para criar nova senha.");			
		}
							
	}	
	else
	{
		adiciona_mensagem_status(MSG_TIPO_ERRO,"O email informado não está cadastrado no sistema.");
	}

  }
  
  
  top();
  
  
?>


     <form class="form-signin" action="senha_nova.php" method="POST">

     
		<fieldset>
        <h2 class="form-signin-heading" align="center">Criar nova senha</h2>

		<br />	
        <label for="login_usr_email">Email</label> 
  		<div class="input-group">
        	<span class="input-group-addon"><i class="glyphicon glyphicon-user"></i></span>
        	<input type="text" class="form-control" placeholder="endereço de email" name="login_usr_email" value="<?php echo($usr_email); ?>">
           </div>
           
	<span class="help-block">Após preencher seu endereço de email e clicar no botão abaixo, você receberá um email com um link para criação de nova senha de acesso à sua conta no <?php echo(NOME_SISTEMA); ?>.</span>	
		        
        
        <input class="btn btn-lg btn-primary btn-block btn-enviando" data-loading-text="aguarde..." type="submit" value="Criar nova senha" name="Solicitar">
       </fieldset>
       <br>
			<div class="clear"></div>
			<div align="right">Já possui login e senha?&nbsp;<a href="login.php">Clique aqui para fazer login</a></div>
			<div align="right">Não tem cadastro?&nbsp;<a href="solicita_cadastro.php">Clique aqui para solicitar</a></div>	
			<div class="clear"></div>


      </form>
        
  
<?php
footer();
 
?>