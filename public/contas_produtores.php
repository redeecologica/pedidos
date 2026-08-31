<?php
  require  "common.inc.php";
  require_once(__DIR__ . "/financeiro.inc.php");

  verifica_seguranca();

  if (!pode_ver_financas_da_rede())
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

  top();
  abas_financeiras('rede', 'produtores');

  $mesa  = posicao_dos_produtores($de, $ate);
  // desde a data de corte: a posição acumulada, que é a fila de quem espera receber
  $desde = posicao_dos_produtores(DATA_CORTE_FINANCEIRO, '2200-01-01 00:00:00');

  $acum = array();
  foreach ((array)$desde as $d) $acum[$d['forn_id']] = $d;

  $nome_mes = array(1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',
                    7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro');

  escreve_mensagem_status();
?>

<legend>Produtores &middot; <?php echo(h($nome_mes[$mes] . ' de ' . $ano)); ?></legend>

<form class="form-inline hidden-print" method="get" action="contas_produtores.php">
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
</form>
<br>

<?php if ($mesa === null || $desde === null) { ?>

  <div class="alert alert-danger">
    <strong>Não foi possível montar a posição dos produtores.</strong><br>
    Tente de novo daqui a alguns minutos.
  </div>

<?php } else { ?>

<table class="table table-bordered table-condensed table-striped">
  <thead>
    <tr>
      <th rowspan="2">Produtor</th>
      <th colspan="3" class="text-center">No mês</th>
      <th colspan="3" class="text-center">Acumulado desde <?php echo(h(date('m/Y', strtotime(DATA_CORTE_FINANCEIRO)))); ?></th>
    </tr>
    <tr>
      <th class="text-right">A receber</th>
      <th class="text-right">Pago</th>
      <th class="text-right">Falta</th>
      <th class="text-right">A receber</th>
      <th class="text-right">Pago</th>
      <th class="text-right">Falta</th>
    </tr>
  </thead>
  <tbody>
  <?php
    $t = array('r'=>0.0,'p'=>0.0,'ar'=>0.0,'ap'=>0.0);
    foreach ($mesa as $f)
    {
        $a = isset($acum[$f['forn_id']]) ? $acum[$f['forn_id']]
           : array('a_receber'=>0.0,'pago'=>0.0,'saldo'=>0.0);
        $t['r'] += $f['a_receber']; $t['p'] += $f['pago'];
        $t['ar'] += $a['a_receber']; $t['ap'] += $a['pago'];
  ?>
    <tr<?php echo($f['arquivado'] ? ' class="text-muted"' : ''); ?>>
      <td>
        <?php echo(h($f['nome'])); ?>
        <?php if ($f['arquivado']) { ?>&nbsp;<span class="label label-default">arquivado</span><?php } ?>
      </td>
      <td class="text-right"><?php echo(h(formata_moeda($f['a_receber']))); ?></td>
      <td class="text-right"><?php echo(h(formata_moeda($f['pago']))); ?></td>
      <td class="text-right"><?php echo(h(formata_moeda($f['saldo']))); ?></td>
      <td class="text-right"><?php echo(h(formata_moeda($a['a_receber']))); ?></td>
      <td class="text-right"><?php echo(h(formata_moeda($a['pago']))); ?></td>
      <td class="text-right<?php echo($a['saldo'] > 0.005 ? ' text-danger' : ''); ?>">
        <strong><?php echo(h(formata_moeda($a['saldo']))); ?></strong>
      </td>
    </tr>
  <?php } ?>
  <?php if (!count($mesa)) { ?>
    <tr><td colspan="7">Nenhum produtor com entrega ou pagamento em <?php echo(h($nome_mes[$mes] . ' de ' . $ano)); ?>.</td></tr>
  <?php } else { ?>
    <tr class="active">
      <th>total</th>
      <th class="text-right"><?php echo(h(formata_moeda($t['r']))); ?></th>
      <th class="text-right"><?php echo(h(formata_moeda($t['p']))); ?></th>
      <th class="text-right"><?php echo(h(formata_moeda($t['r'] - $t['p']))); ?></th>
      <th class="text-right"><?php echo(h(formata_moeda($t['ar']))); ?></th>
      <th class="text-right"><?php echo(h(formata_moeda($t['ap']))); ?></th>
      <th class="text-right"><?php echo(h(formata_moeda($t['ar'] - $t['ap']))); ?></th>
    </tr>
  <?php } ?>
  </tbody>
</table>

<p class="small text-muted">
  <strong>A receber</strong> é o que o produtor entregou e Finanças confirmou — o mesmo
  número da Previsão de Pagamento, a preço de compra. Ele é <strong>calculado</strong>, não
  gravado: acompanha a confirmação e não pode divergir dela.
  <br><strong>Pago</strong> vem do razão, e junta os dois caminhos: o cestante que pagou
  direto ao produtor e o núcleo que pagou por ele.
  <br><strong>Falta</strong> acumulado é a fila de quem espera receber. Negativo quer dizer
  que se pagou mais do que foi confirmado — adiantamento, ou pagamento lançado no produtor
  errado; nos dois casos vale olhar.
  <br>Produtor que aparece só na coluna de pago não entregou no período: recebeu por
  entrega de outro mês, ou o lançamento está no produtor errado.
</p>

<?php } ?>

<?php footer(); ?>
