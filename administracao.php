<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_ADM]);
  top();
?>

<hr/>

<h4>Permissões</h4>

<a href="permissoes.php">Administrar permissões</a>

<hr/>

<h4>Textos Internos</h4>

<a href="textos.php">Administrar textos internos</a>

<hr/>

<h4>Cestantes</h4>

<a href="cestantes_sem_senha.php">Consultar cestantes sem senha temporária criada</a>

<hr/>


<?php 
 
	footer();
?>