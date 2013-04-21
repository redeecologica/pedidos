<?php  
  require_once("header.inc.php"); 

  $url_abs = "http://" . $_SERVER["SERVER_NAME"]. substr($_SERVER["PHP_SELF"],0,strrpos($_SERVER["PHP_SELF"],"/")) ; 
  
?>

<legend>Erro ao tentar acessar a base de dados</legend>

Infelizmente houve um erro ao tentar acessar a base de dados do sistema de pedidos da Rede Ecológica :( <br>
Favor tentar novamente mais tarde acessando o endereço: <br>
<a href="<?php echo($url_abs); ?>"><?php echo($url_abs); ?></a><br><br>

Contato para dúvidas: comissaopedidos@gmail.com


<?php 

  require_once("footer.inc.php"); 
?>