<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">

<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<title><?php echo(NOME_SISTEMA); ?></title>
	<link href="css/bootstrap-3.4.1.min.css" rel="stylesheet"/>
	<link href="css/bootstrap-theme-3.4.1.min.css" rel="stylesheet"/>
	<link href="css/complemento.css?ver=1.3" rel="stylesheet"/> 
	<link href="css/bootstrap-datepicker-1.10.0.min.css" rel="stylesheet">
    
  	<script src="js/jquery-3.7.1.min.js"></script>
	<script src="js/bootstrap-3.4.1.min.js"></script>
	<script src="js/pedido.js?ver=1.8.6"></script>
	<script src="js/bootstrap-datepicker-1.10.0.min.js" charset="UTF-8"></script>
    <script src="js/locales/bootstrap-datepicker.pt-BR.min.js" charset="UTF-8"></script>
    <script src="js/jquery.maskedinput-1.4.1.min.js" charset="UTF-8"></script>
    
</head>

<body >
 <div class="container">
<img src="img/logo_sistema.png"  class="img-responsive hidden-print"/>

<?php
 if(isset($_SESSION['usr.id']) && $_SESSION['usr.id']!="")
 {
 	 include "menu.inc.php";
 }
 else
 {
	 echo ("<br><br>");
 }
?>


<?php

if( function_exists('escreve_mensagem_status')) escreve_mensagem_status();

?>