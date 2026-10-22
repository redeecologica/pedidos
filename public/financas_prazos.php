<?php  
  require  "common.inc.php"; 
  require_once(__DIR__ . "/financeiro.inc.php");
  verifica_seguranca($_SESSION[PAP_RESP_FINANCAS]);
  top();
  
    
?>

<?php
  // A barra vinha copiada aqui, com as três abas que existiam antes do módulo — então
  // chegar nesta tela fazia as novas sumirem, como se não existissem. É o defeito que
  // uma sequência copiada sempre produz, e o mesmo que já tinha acontecido em entregas.
  // Quem decide o que entra é abas_financeiras_do_grupo(), num lugar só.
  abas_financeiras('rede', 'prazos');
?>

<?php

   $sql = "SELECT cha_id, prodt_nome, cha_dt_entrega cha_dt_entrega_original, DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, ";
   $sql.= " date_format(cha_dt_prazo_contabil,'%d/%m/%Y %H:%i') as cha_dt_prazo_contabil ";
   $sql.= "FROM chamadas LEFT JOIN produtotipos ON prodt_id = cha_prodt ";
   $sql.= "ORDER BY cha_dt_entrega_original DESC LIMIT 20";
   $res = executa_sql($sql);


	if($res)
	{
	?>		
        
     <div class="panel panel-default">
      <div class="panel-heading">
         <strong>Prazo para Registro das Entregas</strong>
      </div>
     <div class="panel-body">
            
        <table class="table table-striped table-bordered table-condensed table-hover">
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Data da Entrega</th>
                <th>Prazo Registro da Entrega</th>
                <th>Ações</th>
            </tr>
        </thead>
        
        <tbody>
		<?php
    
       while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
       {
            ?>
            <tr>                              
            <td><?php echo(h($row["prodt_nome"]));?></td>
            <td><?php echo(h($row["cha_dt_entrega"]));?></td>
            <td><?php echo(h($row["cha_dt_prazo_contabil"]));?></td>            
            <td>
                <a class="btn btn-default" href="financas_prazo.php?action=<?php echo(ACAO_EXIBIR_EDICAO . "&cha_id=" . $row["cha_id"] . "&back_url=financas_prazos.php" ); ?>"><i class="glyphicon glyphicon-pencil glyphicon-white"></i> atualizar</a>
            </td>
                                
            </tr>
             
            <?php
       }
  ?>             
  
                 
  </tbody></table>

	</div></div>
    
<?php 
	}

?>


<?php
	 
  footer();

?>