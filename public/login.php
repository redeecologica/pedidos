<?php 

  require  "common.inc.php";  
 
  $sucesso_login = 0;
  
  if(! isset($_SESSION['usr.id'])) $_SESSION['usr.id']="";
  if(! isset($_SESSION['usr.nuc'])) $_SESSION['usr.nuc']="";  
  if(! isset($_SESSION['usr.nome'])) $_SESSION['usr.nome']="undefined";
  
 
  $usr_email = request_get("login_usr_email",""); 
  
  if(isset($_POST["login_usr_email"]) && isset($_POST["login_usr_senha"]))
  {
    $email_login = $_POST["login_usr_email"];
    $senha_login = $_POST["login_usr_senha"];

    if (login_bloqueado($email_login))
    {
      adiciona_mensagem_status(MSG_TIPO_ERRO, "Muitas tentativas de login. Aguarde 15 minutos ou clique em 'Esqueceu a senha'.");
    }
    else
    {
      $sql = "SELECT usr_id, usr_nome_curto, usr_nuc, usr_senha FROM usuarios ";
      $sql.= " WHERE usr_archive != '1' AND usr_email = " . prep_para_bd($email_login);
      $res = executa_sql($sql);
      $row = $res ? mysqli_fetch_array($res, MYSQLI_ASSOC) : null;

      if ($row && verifica_senha($senha_login, $row['usr_senha']))
      {
        limpa_tentativas_login($email_login);

        if (eh_palavra_temp($senha_login))
        {
          $_SESSION['deve_trocar_senha'] = true;   // senha temp: forca a criar uma nova
        }
        else if (strpos($row['usr_senha'], '$2y$') !== 0 && strpos($row['usr_senha'], '$2a$') !== 0)
        {
          // migracao transparente do hash legado para bcrypt
          executa_sql("UPDATE usuarios SET usr_senha = " . prep_para_bd(hash_senha($senha_login)) . " WHERE usr_id = " . prep_para_bd($row['usr_id']));
        }

        $_SESSION['usr.id']=$row['usr_id'];
        $_SESSION['usr.nome']=$row['usr_nome_curto'];
        $_SESSION['usr.nuc']=$row['usr_nuc'];

        //atribuicao dos papeis
        $_SESSION[PAP_ADM] = false;
        $_SESSION[PAP_RESP_NUCLEO] = false;
        $_SESSION[PAP_RESP_PEDIDO] = false;
        $_SESSION[PAP_RESP_MUTIRAO] = false;
        $_SESSION[PAP_BETA_TESTER] = false;
        $_SESSION[PAP_ACOMPANHA_PRODUTOR] = false;
        $_SESSION[PAP_ACOMPANHA_RELATORIOS] = false;
        $_SESSION[PAP_RESP_FINANCAS] = false;
        $_SESSION[PAP_RESP_ENTREGA] = false;

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
      else
      {
        registra_tentativa_login($email_login);
        adiciona_mensagem_status(MSG_TIPO_ERRO,"O email informado não está cadastrado ou a senha fornecida está incorreta.");
      }
    }
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

<br>
<!--
 <div class="alert alert-danger">
    <button type="button" class="close" data-dismiss="alert">&times;</button>
No momento o serviço de hospedagem está com instabilidades. Caso você não consiga fazer login, use este ambiente de contingência:
<a href="https://pedidosredeecologica.000webhostapp.com">https://pedidosredeecologica.000webhostapp.com</a>
<br>
Todas as alterações feitas no ambiente de contingência são salvas no mesmo banco de dados que o sistema principal.
Então não teremos problema de inconsistência de dados.

 </div>
-->


     <form class="form-signin" action="login.php" method="POST" role="form">     
    <fieldset>        
        <h2 class="form-signin-heading" align="center">Entrar no Sistema</h2>      
            <br>
            <label for="login_usr_email">Login</label> (seu email principal cadastrado)
            <div class="input-group">
            <span class="input-group-addon"><i class="glyphicon glyphicon-user"></i></span>
            <input type="text" class="form-control" placeholder="endereço de email"  name="login_usr_email" required="required" autofocus value="<?php echo(h($usr_email)); ?>">
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