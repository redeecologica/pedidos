<?php
  require  "common.inc.php";
  require_once(__DIR__ . "/financeiro.inc.php");

  verifica_seguranca();

  // Fechar chamada é ato de quem cuida do dinheiro da Rede: os lançamentos que saem
  // daqui entram no resultado de todo mundo. Mesma trava de despesas_rede.php e
  // quotas_rateio.php — RESP_FINANÇAS ou ADM.
  //
  // A trava do módulo NÃO passa por verifica_seguranca(): aquela função valida qualquer
  // chamada de PAP_ADM sem olhar o parâmetro (common.inc.php:103-110).
  if (!pode_ver_financeiro()
      || (empty($_SESSION[PAP_RESP_FINANCAS]) && empty($_SESSION[PAP_ADM])))
  {
      adiciona_mensagem_status(MSG_TIPO_ERRO, "Usuário não possui permissão para a ação executada.");
      redireciona(PAGINAPRINCIPAL);
      exit();
  }

  $mes = (int)request_get("mes", date('n'));
  $ano = (int)request_get("ano", date('Y'));
  if ($mes < 1 || $mes > 12)      $mes = (int)date('n');
  if ($ano < 2000 || $ano > 2200) $ano = (int)date('Y');

  $de  = sprintf('%04d-%02d-01 00:00:00', $ano, $mes);
  $ate = ($mes == 12) ? sprintf('%04d-01-01 00:00:00', $ano + 1)
                      : sprintf('%04d-%02d-01 00:00:00', $ano, $mes + 1);

  // A data de corte é o começo da contabilidade: antes dela não há o que fechar, e
  // oferecer chamada de 2013 seria convidar a lançar história que ninguém conferiu.
  if ($de < DATA_CORTE_FINANCEIRO) $de = DATA_CORTE_FINANCEIRO;

  $volta = "fechamento_chamada.php?ano=" . urlencode($ano) . "&mes=" . urlencode($mes);

  if (request_get("action", "") == ACAO_SALVAR)
  {
      $cha_id = request_get("cha_id", "");
      if (!is_string($cha_id) && !is_int($cha_id)) $cha_id = "";
      $o_que  = request_get("o_que", "");

      if ($o_que === "abertura")
      {
          $tra = lanca_abertura_do_estoque($cha_id);

          adiciona_mensagem_status($tra ? MSG_TIPO_SUCESSO : MSG_TIPO_ERRO,
              $tra   ? "Estoque de abertura lançado."
                     : ($tra === 0
                         ? "A abertura já havia sido lançada — nada foi feito de novo."
                         : "Não foi possível lançar a abertura."));
      }
      else
      {
          // Fechar é lançar os DOIS lados: o estoque que a chamada mexeu e o débito de
          // cada cestante. Os dois na mesma confirmação porque é um ato só para quem
          // fecha — e cada um já é idempotente por conta própria.
          $tra   = lanca_estoque_da_chamada($cha_id);
          $mat   = materializa_debitos_da_chamada($cha_id);

          $fez = array();
          if ($tra) $fez[] = "estoque lançado";
          if (is_array($mat) && $mat['lancados'] > 0)
              $fez[] = $mat['lancados'] . " débito(s) congelado(s), R$ " . formata_moeda($mat['valor']);

          // Cada desfecho é uma coisa: lançou, não havia o que lançar, não deu. Dizer
          // "fechada com sucesso" quando nada aconteceu faria parecer que algo mudou.
          if ($tra === null || $mat === null)
              adiciona_mensagem_status(MSG_TIPO_ERRO,
                  "Não foi possível fechar a chamada."
                  . (count($fez) ? " Parte foi lançada: " . implode("; ", $fez) . "." : ""));
          else if (!count($fez))
              adiciona_mensagem_status(MSG_TIPO_SUCESSO, "Nada a lançar: esta chamada já estava fechada.");
          else
              adiciona_mensagem_status(MSG_TIPO_SUCESSO, "Chamada fechada — " . implode("; ", $fez) . ".");
      }

      // POST-redirect-GET: um F5 depois de fechar não relança. O lançamento é
      // idempotente, mas a mensagem repetida faria parecer que lançou duas vezes.
      redireciona($volta);
      exit();
  }

  top();
  abas_financeiras('rede', 'fechamento');

  $fila        = chamadas_a_fechar($de, $ate);
  $con_estoque = conta_de_estoque();
  $saldo_est   = ($con_estoque === null) ? null : saldo_da_conta($con_estoque);

  // A abertura só existe uma vez, e sem ela a conta guarda a soma das variações em vez
  // do valor do estoque. Enquanto não houver lançamento nenhum, a tela pede por ela.
  $precisa_abertura = false;
  if ($con_estoque)
  {
      $r = executa_sql("SELECT COUNT(*) n FROM lancamentos WHERE lan_con = " . prep_para_bd($con_estoque));
      $rw = $r ? mysqli_fetch_array($r, MYSQLI_ASSOC) : null;
      $precisa_abertura = ($rw && (int)$rw['n'] === 0);
  }

  $nome_mes = array(1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',
                    7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro');

  escreve_mensagem_status();
?>

<legend>Fechamento de chamadas &middot; <?php echo(h($nome_mes[$mes] . ' de ' . $ano)); ?></legend>

<form class="form-inline hidden-print" method="get" action="fechamento_chamada.php">
  <div class="form-group">
    <label for="mes">Mês:&nbsp;</label>
    <select id="mes" name="mes" class="form-control" onchange="this.form.submit();">
      <?php foreach ($nome_mes as $m => $nm) { ?>
      <option value="<?php echo(h($m)); ?>"<?php echo($m === $mes ? ' selected' : ''); ?>><?php echo(h($nm)); ?></option>
      <?php } ?>
    </select>
  </div>
  &nbsp;
  <div class="form-group">
    <select id="ano" name="ano" class="form-control" onchange="this.form.submit();">
      <?php for ($a = (int)date('Y') + 1; $a >= 2024; $a--) { ?>
      <option value="<?php echo(h($a)); ?>"<?php echo($a === $ano ? ' selected' : ''); ?>><?php echo(h($a)); ?></option>
      <?php } ?>
    </select>
  </div>
  <?php if ($saldo_est !== null) { ?>
  &nbsp;&nbsp;<span class="text-muted">estoque hoje: <strong>R$ <?php echo(h(formata_moeda(-$saldo_est))); ?></strong></span>
  <?php } ?>
</form>
<br>

<?php if ($fila === null) { ?>

  <div class="alert alert-danger">
    <strong>Não foi possível montar a fila de fechamento.</strong><br>
    Tente de novo daqui a alguns minutos.
  </div>

<?php } else { ?>

<?php
  // A abertura vem antes de tudo, e só quando a conta está vazia. Fechar chamadas sem
  // ela deixaria o estoque valendo a soma das variações — o quanto mudou, não quanto é.
  $primeira = null;
  foreach ($fila as $f) { if (abs($f['estoque']['antes']) > 0.005) { $primeira = $f; break; } }

  if ($precisa_abertura && $primeira !== null) { ?>
  <div class="panel panel-warning">
    <div class="panel-heading">Antes de fechar a primeira chamada</div>
    <div class="panel-body">
      <p>
        O estoque nunca foi lançado no razão. Sem esse primeiro lançamento a conta de
        estoque passa a guardar <strong>o quanto o estoque mudou</strong>, e não quanto ele
        vale — as duas coisas só coincidem se ele tivesse partido de zero, e não parte.
      </p>
      <p>
        A chamada mais antiga desta lista, <strong><?php echo(h($primeira['tipo'] . ' de ' . date('d/m/Y', strtotime($primeira['dt'])))); ?></strong>,
        começou com <strong>R$ <?php echo(h(formata_moeda($primeira['estoque']['antes']))); ?></strong>
        guardados. É esse o ponto de partida.
      </p>
      <form method="post" action="fechamento_chamada.php">
        <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />
        <input type="hidden" name="o_que" value="abertura" />
        <input type="hidden" name="cha_id" value="<?php echo(h($primeira['cha_id'])); ?>" />
        <input type="hidden" name="ano" value="<?php echo(h($ano)); ?>" />
        <input type="hidden" name="mes" value="<?php echo(h($mes)); ?>" />
        <button class="btn btn-warning" type="submit">
          <i class="glyphicon glyphicon-flag"></i> lançar o estoque de abertura
        </button>
        <span class="help-block small">
          Acontece uma vez só. Depois disto, cada fechamento lança apenas o que a chamada mudou.
        </span>
      </form>
    </div>
  </div>
<?php } ?>

<table class="table table-bordered table-condensed table-striped">
  <thead>
    <tr>
      <th>Entrega</th>
      <th>Chamada</th>
      <th class="text-right">Estoque antes</th>
      <th class="text-right">Estoque depois</th>
      <th class="text-right">Estoque a lançar</th>
      <th class="text-right">Débitos a congelar</th>
      <th>Situação</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($fila as $f) {
        $e = $f['estoque']; ?>
    <tr>
      <td><?php echo(h(date('d/m/Y', strtotime($f['dt'])))); ?></td>
      <td><?php echo(h($f['tipo'])); ?> <small class="text-muted">#<?php echo(h($f['cha_id'])); ?></small></td>
      <td class="text-right"><?php echo(h(formata_moeda($e['antes']))); ?></td>
      <td class="text-right"><?php echo(h(formata_moeda($e['depois']))); ?></td>
      <td class="text-right">
        <?php if (abs($e['falta']) < 0.005) { ?>
          <span class="text-muted">&ndash;</span>
        <?php } else { ?>
          <strong><?php echo(h(formata_moeda($e['falta']))); ?></strong>
          <br><small class="text-muted"><?php echo($e['falta'] > 0 ? 'a guardar' : 'a consumir'); ?></small>
        <?php } ?>
      </td>
      <td class="text-right">
        <?php $d = $f['debitos']; if ($d['a_lancar'] === 0) { ?>
          <span class="text-muted">&ndash;</span>
          <?php if ($d['ja_lancados'] > 0) { ?>
            <br><small class="text-muted"><?php echo(h($d['ja_lancados'])); ?> já congelados</small>
          <?php } ?>
        <?php } else { ?>
          <strong><?php echo(h(formata_moeda($d['valor']))); ?></strong>
          <br><small class="text-muted"><?php echo(h($d['a_lancar'])); ?> cestante(s)</small>
        <?php } ?>
      </td>
      <td>
        <?php if ($f['fechada']) { ?>
          <span class="text-success"><i class="glyphicon glyphicon-ok"></i> fechada</span>
          <?php
            // O sinal do que foi lançado não diz nada sozinho: "-2.260,00 no razão" faz
            // quem lê parar para decidir se aquilo é bom ou ruim. As palavras dizem.
            if (abs($e['lancado']) > 0.005) { ?>
            <small class="text-muted">&middot;
              <?php echo(h(($e['lancado'] > 0 ? 'guardou R$ ' : 'consumiu R$ ')
                           . formata_moeda(abs($e['lancado'])))); ?></small>
          <?php } ?>
        <?php } else if (!$f['congelavel']) { ?>
          <span class="text-muted">prazo contábil ainda não venceu</span>
        <?php } else { ?>
          <span class="text-danger">a fechar</span>
          <?php if (abs($e['lancado']) > 0.005) { ?>
            <small class="text-muted">&middot; correção &mdash; R$ <?php echo(h(formata_moeda(abs($e['lancado'])))); ?> já lançados</small>
          <?php } ?>
        <?php } ?>
      </td>
      <td class="text-right">
        <?php
          // O botão só aparece quando há o que fazer E o prazo já venceu. Antes do prazo
          // os insumos ainda mudam, e lançar cedo grava um retrato que a entrega desmente.
          if (!$f['fechada'] && $f['congelavel']) { ?>
        <form method="post" action="fechamento_chamada.php" style="display:inline;">
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />
          <input type="hidden" name="cha_id" value="<?php echo(h($f['cha_id'])); ?>" />
          <input type="hidden" name="ano" value="<?php echo(h($ano)); ?>" />
          <input type="hidden" name="mes" value="<?php echo(h($mes)); ?>" />
          <button class="btn btn-success btn-xs" type="submit">
            <i class="glyphicon glyphicon-ok glyphicon-white"></i> fechar
          </button>
        </form>
        <?php } ?>
      </td>
    </tr>
  <?php } ?>
  <?php if (!count($fila)) { ?>
    <tr><td colspan="8">Nenhuma chamada a fechar em <?php echo(h($nome_mes[$mes] . ' de ' . $ano)); ?>.</td></tr>
  <?php } ?>
  </tbody>
</table>

<p class="small text-muted">
  Fechar uma chamada lança duas coisas. <strong>O estoque</strong> que ela mexeu —
  mercadoria que sobrou vira ativo da Rede, mercadoria consumida vira custo da entrega —,
  e <strong>o débito de cada cestante</strong>, que até aqui era calculado a cada leitura e
  passa a ser um lançamento.
  <br>Congelar o débito só é seguro depois do prazo contábil, e é por isso que o botão só
  aparece então: antes dele a entrega ainda muda, e um débito gravado cedo vira um retrato
  que a realidade desmente — sem ninguém perceber, porque ele deixou de acompanhar a entrega.
  <br>Chamada já fechada continua na lista, marcada — ausência se confunde com esquecimento.
  Se o estoque for corrigido depois, ela reaparece como <em>a fechar</em> e o novo lançamento
  é só a diferença. <strong>Débito já congelado não se refaz</strong>: para corrigir dinheiro
  de alguém, lança-se um ajuste, com valor e motivo.
</p>

<?php } ?>

<?php footer(); ?>
