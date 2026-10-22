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

  function campo_prod($nome)
  {
      $v = request_get($nome, "");
      return (is_string($v) || is_int($v) || is_float($v)) ? trim((string)$v) : "";
  }

  // valor digitado como 1.234,56 vira float — mesma conversão de despesas_rede.php
  function valor_digitado_prod($txt)
  {
      $txt = trim((string)$txt);
      if ($txt === "") return 0.0;
      return (float)str_replace(',', '.', str_replace('.', '', $txt));
  }

  $mes = (int)request_get("mes", date('n'));
  $ano = (int)request_get("ano", date('Y'));
  if ($mes < 1 || $mes > 12)      $mes = (int)date('n');
  if ($ano < 2000 || $ano > 2200) $ano = (int)date('Y');

  $de  = sprintf('%04d-%02d-01 00:00:00', $ano, $mes);
  $ate = ($mes == 12) ? sprintf('%04d-01-01 00:00:00', $ano + 1)
                      : sprintf('%04d-%02d-01 00:00:00', $ano, $mes + 1);

  // O PAGAMENTO MORA AQUI, e não em Despesas da Rede, onde esteve primeiro. Esta tela já
  // mostra quanto falta para cada produtor — que é a informação sem a qual não se decide
  // pagar. Lá a pessoa precisava chegar com nome e valor trazidos de outra tela, e o
  // lançamento ainda exigia um parágrafo explicando que não era despesa e não se rateava.
  //
  // NÃO É DESPESA: quita o que a Rede já deve pelo produto entregue, cujo custo já foi
  // para quem o recebeu. Ratear cobraria o mesmo produto duas vezes, e por isso
  // lanca_pagamento_a_produtor_da_rede() não escreve em `rateios`.
  if (request_get("action", "") == ACAO_SALVAR)
  {
      $data = date_create_from_format('d/m/Y', campo_prod("dt"));

      $tra = !$data ? null
           : lanca_pagamento_a_produtor_da_rede(date_format($data, 'Y-m-d'),
                 campo_prod("con_produtor"), valor_digitado_prod(campo_prod("valor")),
                 campo_prod("origem"), campo_prod("historico"), campo_prod("comprovante"));

      adiciona_mensagem_status($tra ? MSG_TIPO_SUCESSO : MSG_TIPO_ERRO,
          $tra ? "Pagamento lançado na conta do produtor."
               : "Não foi possível lançar o pagamento. Confira a data, o valor, o produtor"
               . " e a conta de origem.");

      // POST-redirect-GET: volta para o mês em que se estava, e um F5 não repete o
      // pagamento — que aqui seria pagar duas vezes.
      redireciona("contas_produtores.php?ano=" . urlencode($ano) . "&mes=" . urlencode($mes));
      exit();
  }

  top();
  abas_financeiras('rede', 'produtores');

  // o produtor cuja linha está aberta para pagamento
  $pagar = request_get("pagar", "");
  if (!is_string($pagar) && !is_int($pagar)) $pagar = "";
  if (!ctype_digit((string)$pagar) || (int)$pagar <= 0) $pagar = "";

  $origens = contas_de_destino_do_tipo('rede');
  // conta de cada produtor, para o formulário saber onde lançar
  // O NOME COMPLETO vem junto: no quadro de pagamento ele é o que confirma que se está
  // pagando quem se pretendia. A tabela mostra o nome curto, que é ambíguo entre
  // produtores parecidos — e um pagamento no produtor errado é dos erros mais caros de
  // desfazer, porque some do saldo de um e aparece no de outro.
  $conta_de   = array();
  $completo_de = array();
  $res_cp = executa_sql("SELECT c.con_id, c.con_forn, f.forn_nome_completo "
          . "FROM contas c JOIN fornecedores f ON f.forn_id = c.con_forn "
          . "WHERE c.con_tipo = 'produtor'");
  while ($res_cp && $rcp = mysqli_fetch_array($res_cp, MYSQLI_ASSOC))
  {
      $conta_de[(int)$rcp['con_forn']]    = (int)$rcp['con_id'];
      $completo_de[(int)$rcp['con_forn']] = (string)$rcp['forn_nome_completo'];
  }

  $mesa  = posicao_dos_produtores($de, $ate);
  // desde a data de corte: a posição acumulada, que é a fila de quem espera receber
  $desde = posicao_dos_produtores(DATA_CORTE_FINANCEIRO, '2200-01-01 00:00:00');

  $acum = array();
  foreach ((array)$desde as $d) $acum[$d['forn_id']] = $d;

  // QUEM TEM CONTA ABERTA APARECE, mesmo sem movimento no mês escolhido. A pergunta que
  // esta tela responde é "a quem devemos", e ela não é sobre o mês: produtor que entregou
  // em maio e não foi pago segue esperando em agosto, e some da lista justamente quando
  // já esperou demais. O contrário também conta — pago a mais é dinheiro que saiu e não
  // voltou, e sumir de vista é como ele vira prejuízo.
  //
  // Entram com as colunas do MÊS zeradas, que é a verdade: no mês não houve nada.
  $tem_no_mes = array();
  foreach ((array)$mesa as $m) $tem_no_mes[$m['forn_id']] = true;

  $pendentes = array();
  foreach ((array)$desde as $d)
  {
      if (isset($tem_no_mes[$d['forn_id']]))  continue;
      if (abs($d['saldo']) < 0.005)           continue;

      $pendentes[] = array(
          'forn_id'   => $d['forn_id'],
          'nome'      => $d['nome'],
          'arquivado' => $d['arquivado'],
          'a_receber' => 0.0,
          'pago'      => 0.0,
          'saldo'     => 0.0,
          // marca a linha: sem isso ela se lê como "entregou nada e recebeu nada neste
          // mês", que é verdade mas não é o motivo de ela estar aqui
          'so_pendencia' => true,
      );
  }

  if (is_array($mesa) && count($pendentes))
  {
      $mesa = array_merge($mesa, $pendentes);
      // a fila de quem espera primeiro, e o resto pela ordem que já vinha
      usort($mesa, function ($a, $b) use ($acum) {
          $sa = isset($acum[$a['forn_id']]) ? $acum[$a['forn_id']]['saldo'] : 0.0;
          $sb = isset($acum[$b['forn_id']]) ? $acum[$b['forn_id']]['saldo'] : 0.0;
          if (abs($sa - $sb) < 0.005) return strcasecmp($a['nome'], $b['nome']);
          return ($sa > $sb) ? -1 : 1;
      });
  }

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
      <th rowspan="2" class="hidden-print"></th>
      <th colspan="3" class="text-center">No mês</th>
      <th colspan="3" class="text-center">Acumulado desde <?php echo(h(date('m/Y', strtotime(DATA_CORTE_FINANCEIRO)))); ?></th>
    </tr>
    <tr>
      <th class="text-right">A pagar</th>
      <th class="text-right">Pago</th>
      <th class="text-right">Falta</th>
      <th class="text-right">A pagar</th>
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
    <?php
      $em_pagamento = ((string)$f['forn_id'] === (string)$pagar);
      $tem_conta    = isset($conta_de[(int)$f['forn_id']]);
      // O QUE FALTA é o acumulado, não o do mês: é a fila de quem espera receber, e é
      // esse o número que se paga. Vem sugerido no campo, para a pessoa conferir em vez
      // de transcrever de outra tela — mas segue editável, porque pagamento parcial e
      // adiantamento existem.
      $sugerido_pg = ($a['saldo'] > 0.005) ? formata_moeda($a['saldo']) : '';
    ?>
    <tr class="linha-produtor<?php echo($em_pagamento ? ' info' : ($f['arquivado'] ? ' text-muted' : '')); ?>" data-forn="<?php echo(h($f['forn_id'])); ?>">
      <td>
        <?php echo(h($f['nome'])); ?>
        <?php if ($f['arquivado']) { ?>&nbsp;<span class="label label-default">arquivado</span><?php } ?>
        <?php if (!empty($f['so_pendencia'])) { ?>
        <br><small class="text-muted">sem movimento no mês &mdash; está aqui pelo acumulado</small>
        <?php } ?>
      </td>
      <td class="hidden-print text-right">
        <?php if (!$tem_conta) { ?>
          <span class="text-muted small" title="produtor sem conta no razão: crie em Contas, no botão de criar as que faltam">sem conta</span>
        <?php } else { ?>
          <?php
            // SEM RECARREGAR. O formulário de cada produtor já vem no HTML, escondido, e
            // o botão só o mostra — a página fica exatamente onde está. Recarregando, a
            // pessoa perdia a rolagem numa lista de quase trinta linhas e precisava achar
            // de novo o produtor que tinha acabado de escolher.
          ?>
          <button type="button" class="btn btn-default btn-xs abre-pagamento"
                  data-forn="<?php echo(h($f['forn_id'])); ?>"
                  title="registrar um pagamento JÁ FEITO a este produtor — o sistema anota, não transfere">
            <i class="glyphicon glyphicon-usd"></i> <span class="rotulo">registrar</span>
          </button>
        <?php } ?>
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
    <?php if ($tem_conta) { ?>
    <tr class="linha-pagamento" data-forn="<?php echo(h($f['forn_id'])); ?>"<?php echo($em_pagamento ? '' : ' style="display:none;"'); ?>>
      <td colspan="8" style="background:#f7f7f7;">
        <?php if (!count((array)$origens)) { ?>
          <div class="alert alert-warning" style="margin:8px 0;">
            <strong>Nenhuma conta da Rede cadastrada.</strong><br>
            Todo pagamento sai de uma conta, e sem nenhuma cadastrada não há de onde
            lançar. Cadastre em <a href="contas.php">Contas</a>.
          </div>
        <?php } else { ?>
        <?php
          // QUEM SE ESTÁ PAGANDO, dito por extenso e em destaque. A linha da tabela traz
          // só o nome curto, e entre produtores parecidos ele é ambíguo — pagamento
          // lançado no produtor errado some do saldo de um e aparece no de outro, e
          // desfazer exige dois lançamentos e a explicação dos dois.
          $nome_completo_pg = isset($completo_de[(int)$f['forn_id']]) ? $completo_de[(int)$f['forn_id']] : '';
        ?>
        <p style="margin:4px 0 10px;">
          Registrando pagamento a
          <strong style="font-size:larger;"><?php echo(h($f['nome'])); ?></strong>
          <?php if ($nome_completo_pg !== '' && $nome_completo_pg !== $f['nome']) { ?>
          <span class="text-muted">&mdash; <?php echo(h($nome_completo_pg)); ?></span>
          <?php } ?>
        </p>
        <form method="post" action="contas_produtores.php" style="margin:8px 0;">
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />
          <input type="hidden" name="ano" value="<?php echo(h($ano)); ?>" />
          <input type="hidden" name="mes" value="<?php echo(h($mes)); ?>" />
          <input type="hidden" name="con_produtor" value="<?php echo(h($conta_de[(int)$f['forn_id']])); ?>" />

          <div class="row">
            <div class="col-sm-3">
              <label for="pg_dt">Data</label>
              <input type="text" id="pg_dt" name="dt" class="form-control data" required="required"
                     value="<?php echo(h(date('d/m/Y'))); ?>" />
            </div>
            <div class="col-sm-3">
              <?php
                // O NOME NÃO SE REPETE nos campos: a linha de cima do quadro já diz de
                // quem é, com nome curto e completo. Repetir em cada rótulo alonga o
                // campo sem acrescentar nada, e empurra o valor para fora da vista.
              ?>
              <label for="pg_valor">Valor pago</label>
              <input type="text" id="pg_valor" name="valor" class="form-control numero" required="required"
                     value="<?php echo(h($sugerido_pg)); ?>" />
              <span class="help-block small">
                <?php echo($sugerido_pg !== '' ? 'Vem preenchido com o que falta — confira e ajuste.'
                                               : 'Nada consta em aberto para este produtor.'); ?>
              </span>
            </div>
            <div class="col-sm-6">
              <label for="pg_origem">Sai da conta</label>
              <select id="pg_origem" name="origem" class="form-control">
                <?php foreach ($origens as $cid => $rot) { ?>
                <option value="<?php echo(h($cid)); ?>"><?php echo(h($rot)); ?></option>
                <?php } ?>
              </select>
            </div>
          </div>

          <div class="row" style="margin-top:8px;">
            <div class="col-sm-5">
              <label for="pg_historico">Descrição</label>
              <input type="text" id="pg_historico" name="historico" class="form-control" maxlength="200"
                     placeholder="Referente a que entrega, ou o mês pago" />
            </div>
            <div class="col-sm-4">
              <?php
                // MESMO CAMPO do pagamento de cestante, e pelo mesmo motivo: é o que
                // transforma "consta que pagamos" em "aqui está". Quem cobra de novo por
                // engano é justamente quem não achou o registro. Opcional — pagamento em
                // dinheiro não tem link, e exigir um faria inventar.
              ?>
              <label for="pg_comprovante">Comprovante</label>
              <input type="text" id="pg_comprovante" name="comprovante" class="form-control" maxlength="300"
                     placeholder="link do comprovante, ou como identificá-lo" />
            </div>
            <div class="col-sm-3" style="padding-top:24px;">
              <button class="btn btn-success" type="submit">
                <i class="glyphicon glyphicon-ok glyphicon-white"></i> lançar pagamento
              </button>
            </div>
          </div>
        </form>
        <?php } ?>
      </td>
    </tr>
    <?php } ?>
  <?php } ?>
  <?php if (!count($mesa)) { ?>
    <tr><td colspan="8">Nenhum produtor com entrega ou pagamento em <?php echo(h($nome_mes[$mes] . ' de ' . $ano)); ?>.</td></tr>
  <?php } else { ?>
    <tr class="active">
      <th>total</th>
      <th class="hidden-print"></th>
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
  <strong>A pagar</strong> é o que o produtor entregou e Finanças confirmou — o mesmo
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

<script type="text/javascript">
	// A classe .data não vira calendário sozinha: cada tela liga o seu, e esta nascia sem.
	$(function() {
		$(".data").datepicker({
			format: 'dd/mm/yyyy',
			language: 'pt-BR',
			autoclose: true
		});
	});

	// ABRE E FECHA NO LUGAR. O formulário de cada produtor já veio no HTML, escondido —
	// abrir é mostrar o que está aqui, e a página não se mexe. Antes recarregava, e numa
	// lista de quase trinta linhas a pessoa perdia a rolagem e precisava achar de novo o
	// produtor que tinha acabado de escolher.
	$(function() {
		$('.abre-pagamento').on('click', function () {
			var forn  = $(this).data('forn');
			var linha = $('tr.linha-pagamento[data-forn="' + forn + '"]');
			var abrir = !linha.is(':visible');

			// um de cada vez: dois formulários abertos convidam a preencher um e
			// confirmar o outro
			$('tr.linha-pagamento').hide();
			$('tr.linha-produtor').removeClass('info');
			$('.abre-pagamento .rotulo').text('registrar');

			if (abrir) {
				linha.show();
				$('tr.linha-produtor[data-forn="' + forn + '"]').addClass('info');
				$(this).find('.rotulo').text('fechar');
				linha.find('input[name="valor"]').focus();
			}
		});
	});
</script>

<?php footer(); ?>
