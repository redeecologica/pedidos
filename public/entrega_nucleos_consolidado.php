<?php  
  require  "common.inc.php"; 
  // abas_entregas() mora aqui. Cada tela declara o que usa em vez de o menu
  // carregar o arquivo nas 87 páginas: o Balanço vive na barra de Entregas, e
  // nenhuma outra parte do sistema precisa dele.
  require_once(__DIR__ . "/balanco.inc.php"); 
  verifica_seguranca($_SESSION[PAP_RESP_ENTREGA]  || $_SESSION[PAP_RESP_FINANCAS]);  
  
  top();
  
 $cha_id=request_get("cha_id",-1);
 if($cha_id==-1)
 {
	 if(isset($_SESSION['cha_id_pref']))
	 {
		$cha_id=$_SESSION['cha_id_pref'];	 
	 }
 }
 $_SESSION['cha_id_pref']=$cha_id;
 

                      
 $sql = "SELECT prodt_nome, prodt_mutirao, DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, cha_taxa_percentual, ((cha_dt_prazo_contabil is null) OR (cha_dt_prazo_contabil > now() ) ) as cha_dentro_prazo, date_format(cha_dt_prazo_contabil,'%d/%m/%Y %H:%i') cha_dt_prazo_contabil ";
 $sql.= "FROM chamadas LEFT JOIN produtotipos ON prodt_id = cha_prodt ";
 $sql.= "WHERE cha_id = " . prep_para_bd($cha_id);

 $res = executa_sql($sql);
 $row = mysqli_fetch_array($res,MYSQLI_ASSOC);

 if(!$res)
 {
	 redireciona(PAGINAPRINCIPAL);
 }

$prodt_nome = $row["prodt_nome"];
$cha_dt_entrega = $row["cha_dt_entrega"];
$cha_taxa_percentual = $row["cha_taxa_percentual"];
$cha_dt_prazo_contabil = $row["cha_dt_prazo_contabil"];
$cha_dentro_prazo = $row["cha_dentro_prazo"];
// se o tipo desta chamada passa pelo mutirão — sem ele não há distribuição a mostrar
$tem_mutirao = ($row && (int)$row["prodt_mutirao"] === 1);

?>

<?php abas_entregas('nucleos'); ?>
<br>
  
  <div class="panel panel-default">
  <div class="panel-heading">
     <strong>Consolidado - Recebido pelo Núcleo <?php if($prodt_nome) echo(" - " . $prodt_nome . " - " . $cha_dt_entrega); ?></strong>

  </div>

  	  
 <div class="panel-body">
 
 <form class="form-inline"  method="get" name="frm_filtro" id="frm_filtro">
	<?php  

	?>
     <fieldset>

     	<div class="form-group">
  				<label for="cha_id">Chamada: </label>            
                 <select name="cha_id" id="cha_id" onchange="javascript:frm_filtro.submit();" class="form-control">
                 	<option value="-1">SELECIONE</option>
                    <?php
                        
                       $sql = "SELECT cha_id, prodt_nome, cha_dt_entrega cha_dt_entrega_original, DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega ";
                        $sql.= "FROM chamadas LEFT JOIN produtotipos ON prodt_id = cha_prodt ";
                        $sql.= "ORDER BY cha_dt_entrega_original DESC LIMIT 20";
						// 20, e não 10, em todo o módulo de Entregas: dez cobria pouco mais de
						// um mês, e quem confere costuma comparar com a chamada equivalente
						// anterior, que ficava de fora. A chamada em foco continua entrando
						// mesmo fora da lista, no bloco logo abaixo.
						
                        $res = executa_sql($sql);
                        if($res)
                        {
						  $achou=false;
						  while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
                          {							
                             echo("<option value='" . $row['cha_id'] . "'");
                             if($row['cha_id']==$cha_id) 
							 {
								 echo(" selected");
								 $achou=true;
							 }
                             echo (">" . h($row['prodt_nome']) . " - " . $row['cha_dt_entrega'] . "</option>");
                          }
						  if($cha_id!=-1 && !$achou)
						  {
							  $sql = "SELECT cha_id, prodt_nome, cha_dt_entrega cha_dt_entrega_original, DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega ";
							  $sql.= "FROM chamadas LEFT JOIN produtotipos ON prodt_id = cha_prodt ";
							  $sql.= "WHERE cha_id = " . prep_para_bd($cha_id);
							  $res2 = executa_sql($sql);
							  $row = mysqli_fetch_array($res2,MYSQLI_ASSOC);
							  if($row)
							  {
								  echo("<option value='" . $row['cha_id'] . "' selected>");
								  echo (h($row['prodt_nome']) . " - " . $row['cha_dt_entrega'] . "</option>");	
							  }
						  }
						  
                        }
                    ?>                        
                 </select>  
                 <?php 
				   if($cha_id!=-1)
				   {
					 ?>  
                    &nbsp;&nbsp;
                    <label for="cha_dt_prazo_contabil">Prazo para Edição: </label>   <?php echo($cha_dt_prazo_contabil?$cha_dt_prazo_contabil:"ainda não configurado"); ?>
					
					<?php 
                        if(!$cha_dentro_prazo)
                        {
                            echo("<span class='alert alert-danger'>(encerrado)</span>");
                        }
				   }
				 ?>
                 
                 
    
    	</div>                 
         </fieldset>
    </form>
    
    
    </div>    
    
    
   </div> 
    
    
      	
  
    

<?php 

if($cha_id!=-1)
{
	// A preço de venda, pela mesma função que dá ao Balanço os números dele — ver
	// recebido_pelos_nucleos() em balanco.inc.php, que diz o que mudou e por quê.
	$recebidos = recebido_pelos_nucleos($cha_id);

	if ($recebidos === null)
	{
		// null é "não deu para perguntar", e não "nenhum núcleo": uma tabela vazia aqui
		// diria que ninguém recebeu nada
		?>
		<div class="alert alert-danger">Não foi possível consultar o que os núcleos receberam nesta chamada. Tente recarregar a página.</div>
		<?php
	}
	else
	{
		?>

			 <input class="btn btn-success" type="button" value="selecionar tabela para copiar"  onclick="selectElementContents( document.getElementById('selectable') );">
             <p />

                <table id="selectable" class="table table-striped table-bordered table-condensed table-hover">
                <thead>
                    <tr>
                        <th colspan="9">Consolidado Entrega no Núcleo <?php echo(" - " . h($prodt_nome) . " " .  h($cha_dt_entrega)); ?>
                          <small class="text-muted" style="font-weight:normal;">&mdash; valores a preço de venda</small></th>
                    </tr>
					<tr>
                    	<th>Núcleo</th>
                        <?php
                          // O pedido DAQUI não é o do Balanço: aqui é o que o núcleo devia
                          // receber, sem o que ficou indisponível; lá é a demanda inteira. O
                          // cabeçalho diz, para quem compara as duas abas não achar um erro.
                        ?>
                        <th>Pedido (R$) <small class="text-muted" style="font-weight:normal;">sem os indisponíveis</small></th>
                        <?php
                          // Chamada que não passa pelo mutirão não tem distribuição: o
                          // produtor entrega direto no núcleo. A coluna só existe onde a
                          // etapa existe — a mesma regra do Balanço.
                          if ($tem_mutirao) { ?>
                        <th>Distribuído pelo <br>Mutirão (R$)</th>
                        <?php } ?>
                        <th>Recebido (R$)</th>
                        <th>Ações</th>
                    </tr>
                </thead>

                <tbody>
                <?php

			   $somatorio_pedido=0;
			   $somatorio_ditribuido=0;
			   $somatorio_recebido=0;

               foreach ($recebidos as $n)
               {
				   $somatorio_pedido+=$n["pedido"];
				   $somatorio_ditribuido+=$n["distribuido"];
				   $somatorio_recebido+=$n["recebido"];

                    ?>
                    <tr>
                    <td><?php echo(h($n["nome"]));?></td>
                    <td><?php echo(formata_moeda($n["pedido"])); ?></td>
                    <?php if ($tem_mutirao) { ?>
                    <td><?php echo(formata_moeda($n["distribuido"])); ?></td>
                    <?php } ?>
                    <td><?php echo(formata_moeda($n["recebido"])); ?></td>

                    <td>
                    	<?php
						if($cha_dentro_prazo)
						{
							// vermelho = o núcleo ainda não registrou quantidade nenhuma.
							// Olha a quantidade, e não o valor: produto de compra ou venda 0
							// deixava o núcleo vermelho para sempre.
						?>
                            <a class="btn btn-default <?php echo($n["registrou"] ? "" : "btn-danger" ); ?>" href="<?php echo(h("entrega_nucleo.php?action=" . ACAO_EXIBIR_EDICAO . "&cha_id=" . $cha_id .  "&nuc_id=" . $n["nuc_id"]));?>"><i class="glyphicon glyphicon-pencil glyphicon-white"></i> atualizar</a>

						<?php
						}
						?>
                         <a class="btn btn-default" href="<?php echo(h("entrega_nucleo.php?action=" . ACAO_EXIBIR_LEITURA . "&cha_id=" . $cha_id .  "&nuc_id=" . $n["nuc_id"]));?>"><i class="glyphicon glyphicon-search glyphicon-white"></i> ver</a>
                    </td>

                    </tr>

                    <?php

               }
          ?>
          <tr>
            <th>TOTAL</th>
            <th><?php echo(formata_moeda($somatorio_pedido));?></th>
            <?php if ($tem_mutirao) { ?>
            <th><?php echo(formata_moeda($somatorio_ditribuido));?></th>
            <?php } ?>
            <th><?php echo(formata_moeda($somatorio_recebido));?></th>
            <th>&nbsp;</th>

          </tr>

          </tbody></table>


        <?php
	}

}
	
	

 ?>             
                         
          </div>
       

 <?php 


footer();

?>