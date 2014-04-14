<?php 

  require  "common.inc.php";  
 
  $sucesso_login = 0;
  
  if(! isset($_SESSION['usr.id'])) $_SESSION['usr.id']="";
  if(! isset($_SESSION['usr.nuc'])) $_SESSION['usr.nuc']="";  
  if(! isset($_SESSION['usr.nome'])) $_SESSION['usr.nome']="undefined";
  
 
  $usr_email = request_get("login_usr_email",""); 
  
  if(isset($_POST["login_usr_email"]) && isset($_POST["login_usr_senha"]))
  {	 
  	$sql = "SELECT usr_id,usr_nome_curto,usr_nuc FROM usuarios ";
	$sql.= " WHERE ";
	$sql.= " usr_archive != '1' ";
	$sql.= " AND usr_email = " . prep_para_bd($_POST["login_usr_email"]);
	$sql.= " AND usr_senha = " . prep_para_bd(crypt($_POST["login_usr_senha"],PASSWORD_SALT));	
	
	$res = executa_sql($sql);
	if($res)
	{
		if(mysqli_num_rows($res))
		{
			$row = mysqli_fetch_array($res,MYSQLI_ASSOC);
		
			$_SESSION['usr.id']=$row['usr_id'];
			$_SESSION['usr.nome']=$row['usr_nome_curto'];
			$_SESSION['usr.nuc']=$row['usr_nuc'];
						
			//atribuicao dos papeis
			$_SESSION[PAP_ADM] = false;
			$_SESSION[PAP_RESP_NUCLEO] = false;
			$_SESSION[PAP_RESP_PEDIDO] = false;
			$_SESSION[PAP_RESP_MUTIRAO] = false; 
						
			$sql=  "SELECT pap_nome FROM papeis, usuariopapeis ";
			$sql.= "WHERE usrp_pap = pap_id AND usrp_usr = " . prep_para_bd($row['usr_id']);	
			$res2 = executa_sql($sql);			
			if($res2)
			{
				while($row2 = mysqli_fetch_array($res2,MYSQLI_ASSOC))
				{	
					$_SESSION[$row2["pap_nome"]] = true;
				}
			}

			$sucesso_login =  1;		
						
			session_write_close();	
			header("Location:" . PAGINAPRINCIPAL);
			redireciona(PAGINAPRINCIPAL);
			exit();

		}
	}
	
	if(!$sucesso_login) adiciona_mensagem_status(MSG_TIPO_ERRO,"O email informado não está cadastrado ou a senha fornecida está incorreta.");

	 	 
  }
  
  
  if(isset($_REQUEST["logoff"]))
  {
		if(session_id()) 
	  	{			
			session_destroy();
			session_start();
			session_regenerate_id();		   
		}
  }  
    
  top();
  
  
  
?>


     <form class="form-signin" action="login.php" method="POST" role="form">     
		<fieldset>        
        <h2 class="form-signin-heading" align="center">Entrar no Sistema</h2>			
            <br>
            <label for="login_usr_email">Login</label> (seu email principal cadastrado)
            <div class="input-group">
            <span class="input-group-addon"><i class="glyphicon glyphicon-user"></i></span>
  	   	 	<input type="text" class="form-control" placeholder="endereço de email"  name="login_usr_email" required="required" autofocus value="<?php echo($usr_email); ?>">
            </div>
            <br>
             <label for="login_usr_senha">Senha</label>
	         <div class="input-group">
              <span class="input-group-addon"><i class="glyphicon glyphicon-lock"></i></span>
    	    <input type="password" class="form-control" required="required"  name="login_usr_senha">
            </div> 
            <br><br>
        	<input class="btn btn-lg btn-primary btn-block"  type="submit" name="Entrar" value="Entrar">
  
       </fieldset>
       <br>
			<div class="clear"></div>
			<div align="right">Esqueceu a senha?&nbsp;<a href="senha_nova.php">Clique aqui para criar uma nova</a></div>
			<div align="right">Não tem cadastro?&nbsp;<a href="solicita_cadastro.php">Clique aqui para informações</a></div>	
			<div class="clear"></div>
      </form>
          
  
<?php
footer();
 
?>