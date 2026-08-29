<?php  
  require  "common.inc.php"; 
  require_once(__DIR__ . "/financeiro.inc.php");
  verifica_seguranca($_SESSION[PAP_RESP_FINANCAS]);
  top();

  // A barra sai de abas_financeiras(), e não escrita aqui: são sete telas, e sete cópias
  // da mesma barra divergem na primeira que alguém esquecer de atualizar.
  //
  // Quem não tem o papel Beta Tester continua vendo a barra ANTIGA — as duas telas que
  // esta página sempre teve. O módulo novo fica invisível até estar pronto, e esta é a
  // única página do grupo que existia antes dele.
  if (pode_ver_financas_da_rede()) abas_financeiras('rede', 'hub');
  else {
?>
<ul class="nav nav-tabs">
  <li class="active"><a href="#">Finanças</a></li>
  <li><a href="recebimento.php?action=0&recebimento=final"><i class="glyphicon glyphicon-road"></i> Confirmação Entrega dos Produtores</a></li>
  <li><a href="financas_prazos.php"><i class="glyphicon glyphicon-calendar"></i> Configuração Prazos</a></li>  
</ul>
<br>
<?php } ?>
  
    <div class="panel panel-primary">
      <div class="panel-heading">Instruções para Finanças</div>
      <div class="panel-body">
         <ul>
          <li>
		  <strong>Recebimento dos produtores</strong> — registre o total que foi entregue pelos produtores. É a base do pagamento a eles, e o número que Finanças confirma depois de ler as justificativas de divergência.
          </li>
          <?php if (pode_ver_financas_da_rede()) { ?>
          <br>
          <li>
          <strong>Fechamento de chamadas</strong> — congela o que a chamada mexeu: o estoque de secos e o débito de cada cestante. Só depois do prazo contábil, e uma chamada por vez.
          </li>
          <br>
          <li>
          <strong>Despesas da Rede</strong> — lance os custos do mês e confirme quanto cabe a cada núcleo. O rateio é sugerido, nunca automático.
          </li>
          <br>
          <li>
          <strong>Quotas de rateio</strong> — quanto cada núcleo pesa na divisão por entrega. Sugerida pelo tipo do núcleo, e editável.
          </li>
          <br>
          <li>
          <strong>Contas</strong> — as contas da Rede e as que nascem sozinhas para núcleo, produtor e cestante.
          </li>
          <?php } ?>
          <br>
          <li>
          <strong>Prazos</strong> — o prazo final para edição das informações de entrega de cada chamada. É ele que autoriza o congelamento.
          </li>          
         </ul>     
          
        
            
      </div>
    </div>


<?php 
 
	footer();
?>