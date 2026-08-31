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

      if ($o_que === "prazo")
      {
          // O PRAZO DE REGISTRO DE ENTREGA MORA AQUI, junto do fechamento, porque as duas
          // decisões são a mesma conversa: até quando a entrega ainda pode mudar, e
          // quando o número pode ser congelado. Estavam em telas separadas, e quem fechava
          // não via o prazo que autoriza fechar.
          //
          // NÃO SE MEXE DEPOIS DE FECHADA. Mover o prazo de uma chamada já congelada não
          // desfaz nada — os lançamentos continuam lá — mas passa a sugerir que a entrega
          // ainda pode mudar, quando ela já virou dívida de cestante. A trava é conferida
          // AQUI, no servidor, e não só escondendo o botão: a tela esconde, o POST não.
          $fila_agora = chamadas_a_fechar($de, $ate);
          $ja_fechada = null;
          foreach ((array)$fila_agora as $x)
              if ((string)$x['cha_id'] === (string)$cha_id) $ja_fechada = $x['fechada'];

          $data = date_create_from_format('d/m/Y', trim((string)request_get("prazo", "")));
          $hora = trim((string)request_get("prazo_hh", ""));
          if (!preg_match('/^\d{1,2}:\d{2}$/', $hora)) $hora = '23:59';

          if ($fila_agora === null)
              adiciona_mensagem_status(MSG_TIPO_ERRO, "Não foi possível conferir a situação da chamada.");
          else if ($ja_fechada === null)
              adiciona_mensagem_status(MSG_TIPO_ERRO, "Chamada fora da fila deste mês.");
          else if ($ja_fechada)
              adiciona_mensagem_status(MSG_TIPO_ERRO,
                  "Esta chamada já foi fechada — o prazo de registro não muda mais.");
          else if (!$data)
              adiciona_mensagem_status(MSG_TIPO_ERRO, "Data inválida. Use dd/mm/aaaa.");
          else
          {
              $ok = executa_sql("UPDATE chamadas SET cha_dt_prazo_contabil = "
                  . prep_para_bd(date_format($data, 'Y-m-d') . ' ' . $hora . ':00')
                  . " WHERE cha_id = " . prep_para_bd($cha_id));

              adiciona_mensagem_status($ok ? MSG_TIPO_SUCESSO : MSG_TIPO_ERRO,
                  $ok ? "Prazo de registro de entrega atualizado."
                      : "Não foi possível atualizar o prazo.");
          }
      }
      else if ($o_que === "abertura")
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


  // qual chamada está com o prazo aberto para edição
  $editando_prazo = request_get("prazo_de", "");
  if (!is_string($editando_prazo) && !is_int($editando_prazo)) $editando_prazo = "";
  if (!ctype_digit((string)$editando_prazo)) $editando_prazo = "";

  $nome_mes = array(1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',
                    7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro');

  escreve_mensagem_status();
?>

<legend>Fechamento contábil &middot; <?php echo(h($nome_mes[$mes] . ' de ' . $ano)); ?></legend>

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
  // A abertura vem antes de tudo. UMA POR CORRENTE, não uma por conta: Secos e Secos
  // Bimestral guardam estoques independentes, e abrir só a primeira deixaria a outra
  // fora do razão para sempre — a conta ficaria a menos, em silêncio.
  //
  // Mostra a chamada mais antiga de cada corrente que ainda tem abertura pendente. Quem
  // decide o que está pendente é abertura_pendente_da_chamada(), a mesma função que o
  // lançamento usa: aqui a tela só pergunta, não repete a regra.
  $abrir = array();
  foreach ($fila as $f)
      if ($f['abertura'] > 0.005 && !isset($abrir[$f['tipo']])) $abrir[$f['tipo']] = $f;

  foreach ($abrir as $tipo => $f) { ?>
  <div class="panel panel-warning">
    <div class="panel-heading">Antes de fechar a primeira chamada de <?php echo(h($tipo)); ?></div>
    <div class="panel-body">
      <p>
        O estoque de <strong><?php echo(h($tipo)); ?></strong> nunca foi lançado no razão. Sem
        esse primeiro lançamento a conta passa a guardar <strong>o quanto o estoque mudou</strong>,
        e não quanto ele vale — as duas coisas só coincidem se ele tivesse partido de zero, e
        não parte.
      </p>
      <p>
        A chamada mais antiga desta lista, <strong><?php echo(h($tipo . ' de ' . date('d/m/Y', strtotime($f['dt'])))); ?></strong>,
        começou com <strong>R$ <?php echo(h(formata_moeda($f['abertura']))); ?></strong>
        guardados. É esse o ponto de partida.
      </p>
      <form method="post" action="fechamento_chamada.php">
        <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />
        <input type="hidden" name="o_que" value="abertura" />
        <input type="hidden" name="cha_id" value="<?php echo(h($f['cha_id'])); ?>" />
        <input type="hidden" name="ano" value="<?php echo(h($ano)); ?>" />
        <input type="hidden" name="mes" value="<?php echo(h($mes)); ?>" />
        <button class="btn btn-warning" type="submit">
          <i class="glyphicon glyphicon-flag"></i> lançar o estoque de abertura de <?php echo(h($tipo)); ?>
        </button>
        <span class="help-block small">
          Acontece uma vez por tipo de chamada. Depois disto, cada fechamento lança apenas o
          que a chamada mudou.
        </span>
      </form>
    </div>
  </div>
<?php } ?>

<?php
  // As colunas de estoque só existem se ALGUMA chamada do mês guardar estoque. Um mês só
  // de Frescos não tem o que mostrar ali, e três colunas de 0,00 fazem quem lê procurar o
  // que nunca foi registrado. Num mês misto as colunas ficam, e a chamada sem mutirão
  // mostra travessão: é pergunta que não se faz, não zero medido.
  $algum_estoque = false;
  foreach ($fila as $f) if ($f['tem_mutirao']) { $algum_estoque = true; break; }
?>

<table class="table table-bordered table-condensed table-striped">
  <thead>
    <tr>
      <th>Entrega</th>
      <th>Chamada</th>
      <th>Prazo p/ registro da entrega</th>
      <?php if ($algum_estoque) { ?>
      <th class="text-right">Estoque antes</th>
      <th class="text-right">Estoque depois</th>
      <th class="text-right">Estoque a lançar</th>
      <?php } ?>
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
      <?php
        // O PRAZO DECIDE SE DÁ PARA FECHAR, e por isso fica na mesma linha do botão de
        // fechar. Enquanto ele não vence, Finanças olha os números ainda quentes; depois
        // dele, olha tudo parado e decide congelar. Eram duas telas, e quem fechava não
        // via o prazo que autoriza fechar.
        $em_prazo = ((string)$f['cha_id'] === (string)$editando_prazo);
      ?>
      <td class="<?php echo($em_prazo ? '' : 'text-nowrap'); ?>">
        <?php if ($em_prazo) { ?>
        <form method="post" action="fechamento_chamada.php" class="form-inline">
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />
          <input type="hidden" name="o_que" value="prazo" />
          <input type="hidden" name="cha_id" value="<?php echo(h($f['cha_id'])); ?>" />
          <input type="hidden" name="ano" value="<?php echo(h($ano)); ?>" />
          <input type="hidden" name="mes" value="<?php echo(h($mes)); ?>" />
          <input type="text" name="prazo" class="form-control input-sm data" style="width:105px;"
                 required="required" autofocus
                 value="<?php echo(h($f['prazo'] ? date('d/m/Y', strtotime($f['prazo'])) : '')); ?>" />
          <input type="text" name="prazo_hh" class="form-control input-sm" style="width:62px;"
                 value="<?php echo(h($f['prazo'] ? date('H:i', strtotime($f['prazo'])) : '23:59')); ?>" />
          <button class="btn btn-success btn-xs" type="submit">ok</button>
          <a class="btn btn-link btn-xs" href="<?php echo(h($volta)); ?>">cancelar</a>
        </form>
        <?php } else { ?>
          <?php if ($f['prazo']) { ?>
            <?php echo(h(date('d/m/Y H:i', strtotime($f['prazo'])))); ?>
            <?php if (!$f['congelavel']) { ?>
              <br><small class="text-muted">a entrega ainda pode mudar</small>
            <?php } ?>
          <?php } else { ?>
            <span class="text-danger">não definido</span>
            <br><small class="text-muted">sem prazo não há o que congelar</small>
          <?php } ?>
          <?php
            // Fechada não muda mais: mover o prazo não desfaz lançamento nenhum, mas
            // passaria a sugerir que a entrega ainda pode mudar depois de ela já ter
            // virado dívida de cestante.
            if (!$f['fechada']) { ?>
          &nbsp;<a class="btn btn-default btn-xs hidden-print"
                   href="<?php echo(h($volta)); ?>&amp;prazo_de=<?php echo(h($f['cha_id'])); ?>"
                   title="mudar o prazo de registro de entrega desta chamada">
            <i class="glyphicon glyphicon-pencil"></i>
          </a>
          <?php } ?>
        <?php } ?>
      </td>
      <?php if ($algum_estoque && !$f['tem_mutirao']) { ?>
      <td class="text-right" colspan="3">
        <span class="text-muted" title="o produtor entrega direto no núcleo: esta chamada não guarda estoque">não guarda estoque</span>
      </td>
      <?php } else if ($algum_estoque) { ?>
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
      <?php } ?>
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
            // O sinal do que foi lançado não diz nada sozinho: um negativo no razão faz
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
    <tr><td colspan="<?php echo($algum_estoque ? 9 : 6); ?>">Nenhuma chamada a fechar em <?php echo(h($nome_mes[$mes] . ' de ' . $ano)); ?>.</td></tr>
  <?php } ?>
  </tbody>
</table>

<p class="small text-muted">
  Fechar uma chamada lança duas coisas. <strong>O estoque</strong> que ela mexeu —
  mercadoria que sobrou vira ativo da Rede, mercadoria consumida vira custo da entrega —,
  e <strong>o débito de cada cestante</strong>, que até aqui era calculado a cada leitura e
  passa a ser um lançamento.
  <br>O <strong>prazo para registro da entrega</strong> é o que separa as duas fases, e por
  isso fica aqui, na mesma linha do botão. Enquanto ele não vence, os núcleos ainda anotam e
  corrigem entrega; depois dele, os números param e dá para conferir tudo parado antes de
  congelar. Congelar antes grava um retrato que a realidade desmente — sem ninguém perceber,
  porque ele deixou de acompanhar a entrega. É por isso que o botão de fechar só aparece
  depois do prazo.
  <br>O prazo se muda pelo lápis, enquanto a chamada não estiver fechada. <strong>Depois de
  fechada, não</strong>: mover o prazo não desfaz lançamento nenhum, mas passaria a sugerir
  que a entrega ainda pode mudar quando ela já virou dívida de cestante.
  <br>Chamada já fechada continua na lista, marcada — ausência se confunde com esquecimento.
  Se o estoque for corrigido depois, ela reaparece como <em>a fechar</em> e o novo lançamento
  é só a diferença. <strong>Débito já congelado não se refaz</strong>: para corrigir dinheiro
  de alguém, lança-se um ajuste, com valor e motivo.
</p>

<?php } ?>

<?php footer(); ?>
