<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_ADM]);
  top();
?>

<div class="panel panel-default">
  <div class="panel-heading">
     <strong>Funções de Administração do Sistema</strong>
  </div>
 <div class="panel-body">
 
<h4>Permissões</h4>

<a href="permissoes.php">Administrar permissões</a>

<hr/>

<h4>Textos Internos</h4>

<a href="textos.php">Administrar textos internos</a>

<hr/>

<h4>Cestantes</h4>

<a href="cestantes_sem_senha.php">Consultar cestantes sem senha temporária criada</a>

<hr/>

<h4>Produtos</h4>

<a href="rel_produtos_ao_longo_do_ano.php">Consultar produtos disponíveis / pedidos ao longo do ano</a>
<br>
<a href="produtotipos.php">Administrar tipos de produto/chamada</a>

<hr/>

<h4>Tipos de Núcleos e de Associação</h4>

<a href="nucleotipos.php">Administrar tipos de núcleo</a>
<br>
<a href="associacaotipos.php">Administrar tipos de associação</a>


</div>

<?php 
 
	footer();
?>