<?php 

  require  "common.inc.php";  
  
  verifica_seguranca();
  
  $usr_id = $_SESSION["usr.id"];
  $nova_senha = request_get("login_usr_senha_nova","");     
 
  $sucesso = 0;
  
	if($nova_senha!="")
	{
		if(strlen($nova_senha)<8)
		{
			adiciona_mensagem_status(MSG_TIPO_ERRO,"A senha deve ter no mínimo 8 caracteres.");
		}
		else if(eh_palavra_temp($nova_senha))
		{
			adiciona_mensagem_status(MSG_TIPO_ERRO,"Essa senha não é permitida. Por favor escolha outra.");
		}
		else
		{
			$sql = "UPDATE usuarios  SET usr_senha = " . prep_para_bd(hash_senha($nova_senha));
			$sql.= " WHERE usr_id = " . prep_para_bd($usr_id);
			$res = executa_sql($sql);
			if(!$res)
			{
				adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar salvar nova senha.");
			}
			else
			{
				unset($_SESSION['deve_trocar_senha']); // resolveu a troca obrigatória
				adiciona_mensagem_status(MSG_TIPO_SUCESSO,"Sua senha foi alterada com sucesso.");
				$sucesso = 1;

			}
	//		redireciona("meusdados.php");

		}
	}
 
  
  top();
  

if($sucesso)
{
  ?>
  
    Parabéns por zelar pelas suas informações de acesso ao sistema de pedidos!    <br>
     Você pode continuar navegando normalmente. <br> 
     Que tal ver as <a href="inicio.php">notícias da página inicial</a>?
<?php
	
}

else

{  
?>




     <form class="form-signin"  action="senha_altera.php" method="POST">
  
		<fieldset>
        <h2 class="form-signin-heading" align="center">Informar a nova senha</h2>
		<br>
        <label for="login_usr_senha_nova">Nova senha</label> (mínimo 8 caracteres)
        <div class="input-group">
  			<span class="input-group-addon"><i class="glyphicon glyphicon-lock"></i></span>
        	<input type="password" class="form-control campo-senha" minlength="8" name="login_usr_senha_nova" id="login_usr_senha_nova" value="" required="required">
        	<span class="input-group-btn"><button type="button" class="btn btn-default btn-mostrar-senha" tabindex="-1" aria-label="Mostrar senha"><i class="glyphicon glyphicon-eye-open"></i></button></span>
         </div> 
		<br />

        <label for="login_usr_senha_nova_conf">Confirmar nova senha</label> 
  		<div class="input-group">
        	<span class="input-group-addon"><i class="glyphicon glyphicon-lock"></i></span>
        	<input type="password" class="form-control campo-senha" minlength="8" name="login_usr_senha_nova_conf" id="login_usr_senha_nova_conf" value="" required="required">
        	<span class="input-group-btn"><button type="button" class="btn btn-default btn-mostrar-senha" tabindex="-1" aria-label="Mostrar senha"><i class="glyphicon glyphicon-eye-open"></i></button></span>
         </div> 

        <br><br>
        <input class="btn btn-lg btn-primary btn-block" type="submit" value="Salvar senha" name="salvar_senha">
       </fieldset>

      </form>
      
       
  <script type="text/javascript">
	$(function() {
		$("form").submit(verificaSenha);
	}); 
  </script>  


<?php
}

footer();
 
?>