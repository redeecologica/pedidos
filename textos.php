<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO]);
  top();
?>



	<legend>Configuração de Textos Internos</legend>
        
	<table class="table table-striped table-bordered">
		<thead>
			<tr>
				<th class="span1">#</th>
				<th>Nome interno</th>
				<th>Utilização</th>
				<th>Data Última Atualização</th>
				<th>Por usuário</th>                
			</tr>
		</thead>
		<tbody>
				<?php
					
					$sql = "SELECT txt_id, txt_nome_curto, txt_nome_completo, txt_dt_atualizacao, usr_nome_completo ";
					$sql.= "FROM textos LEFT JOIN usuarios ON txt_usr_atualizacao = usr_id ";
					$sql.= "ORDER BY txt_nome_curto ";
					$contador=0;
					$res = executa_sql($sql);
				    if($res)
					{
					 while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
				     {
				?>				 
				  <tr>
                  	 <td><?php echo(++$contador); ?></td>
					 <td><a href="texto.php?action=<?php echo(ACAO_EXIBIR_LEITURA);?>&amp;txt_id=<?php echo($row['txt_id']);?>"><?php echo($row['txt_nome_curto']);?></a></td>
                     <td><?php echo($row['txt_nome_completo']);?></td> 
					 <td><?php echo($row['txt_dt_atualizacao']);?> </td> 
					 <td><?php echo($row['usr_nome_completo']);?> </td>                                          
				  </tr>
				<?php 
				     }
				   }
				?>
		</tbody>
	</table>

<div align="right">
<a href="texto.php?action=<?php echo(ACAO_INCLUIR);?>" class="btn"><i class="icon-plus"></i> adicionar novo</a>
</div>
<?php 
 
	footer();
?>