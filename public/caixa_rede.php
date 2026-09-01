<?php
  require  "common.inc.php";
  require_once(__DIR__ . "/financeiro.inc.php");

  verifica_seguranca();

  // Mesma audiência das outras telas do grupo da Rede. A trava do módulo não passa por
  // verifica_seguranca(): aquela função valida qualquer chamada de PAP_ADM sem olhar o
  // parâmetro (common.inc.php:103-110).
  if (!pode_ver_financas_da_rede())
  {
      adiciona_mensagem_status(MSG_TIPO_ERRO, "Usuário não possui permissão para a ação executada.");
      redireciona(PAGINAPRINCIPAL);
      exit();
  }

  $ano = (int)request_get("ano", date('Y'));
  if ($ano < 2000 || $ano > 2200) $ano = (int)date('Y');

  top();
  abas_financeiras('rede', 'caixa');

  $pos = posicao_da_rede();
  $des = resultado_da_rede($ano);

  $nome_mes = array(1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',
                    7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro');

  escreve_mensagem_status();
?>

<legend>Caixa da Rede</legend>

<?php if ($pos === null) { ?>

  <div class="alert alert-danger">
    <strong>Não foi possível montar a posição da Rede.</strong><br>
    Tente de novo daqui a alguns minutos.
  </div>

<?php } else { ?>

<div class="row">
  <div class="col-sm-6">

    <legend style="font-size:medium;">Onde o dinheiro está</legend>

    <table class="table table-bordered table-condensed">
      <tbody>
      <?php foreach ($pos['caixa']['contas'] as $c) { ?>
        <tr>
          <td><?php echo(h($c['nome'])); ?></td>
          <td class="text-right<?php echo($c['em_caixa'] < -0.005 ? ' text-danger' : ''); ?>">
            <?php echo(h(formata_moeda($c['em_caixa']))); ?>
          </td>
        </tr>
      <?php } ?>
      <?php if (!count($pos['caixa']['contas'])) { ?>
        <tr><td colspan="2" class="text-muted">Nenhuma conta da Rede cadastrada.</td></tr>
      <?php } ?>
        <tr>
          <?php
            // O dinheiro nos núcleos É da Rede, só está na mão de outra pessoa. Deixá-lo
            // fora daria um total menor do que o que a Rede de fato tem, e é justamente
            // esse pedaço que ninguém conseguia ver somado.
          ?>
          <td>com os núcleos <small class="text-muted">&mdash; da Rede, na mão deles</small></td>
          <td class="text-right"><?php echo(h(formata_moeda($pos['caixa']['nucleos']))); ?></td>
        </tr>
        <tr class="active">
          <th>total em caixa</th>
          <?php
            // Total negativo é alarme, não detalhe: significa que a Rede gastou mais do
            // que tem em mão, e quem lê precisa ver isso antes de qualquer outra coisa.
          ?>
          <th class="text-right<?php echo($pos['caixa']['total'] < -0.005 ? ' text-danger' : ''); ?>">
            <?php echo(h(formata_moeda($pos['caixa']['total']))); ?>
          </th>
        </tr>
      </tbody>
    </table>

    <p class="small text-muted">
      É o único número desta tela que se confere contra extrato bancário: some o que cada
      pessoa tem na conta dela, mais o que está com os núcleos.
      <br>Conta com valor <strong>negativo</strong> pagou mais do que recebeu — adiantou
      dinheiro próprio, e a Rede deve isso a ela.
    </p>

  </div>
  <div class="col-sm-6">

    <legend style="font-size:medium;">O que está pendurado</legend>

    <?php $p = $pos['pendurado']; ?>
    <table class="table table-bordered table-condensed">
      <tbody>
        <tr>
          <td>
            <a href="cestantes_quadro.php">cestantes devem</a>
            <?php if ($p['cestantes_quantos']) { ?>
            <small class="text-muted">&mdash; <?php echo(h($p['cestantes_quantos'])); ?> pessoa(s)</small>
            <?php } ?>
          </td>
          <td class="text-right"><?php echo(h(formata_moeda($p['cestantes_devem']))); ?></td>
        </tr>
        <?php if ($p['cestantes_credito'] > 0.005) { ?>
        <tr>
          <?php
            // Crédito NÃO se abate do que os outros devem: são pessoas diferentes, e a
            // soma líquida faria parecer que a Rede tem menos a receber do que tem.
          ?>
          <td>cestantes com crédito
            <small class="text-muted">&mdash; <?php echo(h($p['credito_quantos'])); ?> pessoa(s), pagaram adiantado</small>
          </td>
          <td class="text-right"><?php echo(h(formata_moeda($p['cestantes_credito']))); ?></td>
        </tr>
        <?php } ?>
        <tr>
          <td>
            <a href="contas_produtores.php">a pagar a produtores</a>
            <?php if ($p['produtores_quantos']) { ?>
            <small class="text-muted">&mdash; <?php echo(h($p['produtores_quantos'])); ?> produtor(es)</small>
            <?php } ?>
          </td>
          <td class="text-right"><?php echo(h(formata_moeda($p['produtores_a_pagar']))); ?></td>
        </tr>
        <?php if ($p['produtores_adiantado'] > 0.005) { ?>
        <tr>
          <td>pago a produtor além do confirmado
            <small class="text-muted">&mdash; <?php echo(h($p['adiantado_quantos'])); ?>; vale conferir</small>
          </td>
          <td class="text-right text-danger"><?php echo(h(formata_moeda($p['produtores_adiantado']))); ?></td>
        </tr>
        <?php } ?>
        <tr>
          <td>
            <a href="fechamento_chamada.php">estoque guardado</a>
            <small class="text-muted">&mdash; mercadoria paga que ainda não saiu</small>
          </td>
          <td class="text-right"><?php echo(h(formata_moeda($p['estoque']))); ?></td>
        </tr>
      </tbody>
    </table>

    <p class="small text-muted">
      Nada aqui é dinheiro: é o que a Rede tem a receber e o que tem a pagar. Os dois
      lados juntos mostram o giro — quando cestantes devem muito e produtores esperam
      muito ao mesmo tempo, a Rede está financiando os dois com o mesmo dinheiro.
      <br>Cada linha leva à tela que a detalha.
    </p>

  </div>
</div>

<p class="small text-muted">
  <?php
    // A conta de resultado aparece à parte e nomeada, senão alguém a soma ao caixa. Ela é
    // do tipo 'rede' como as outras, e a diferença entre "quanto temos" e "como estamos"
    // não se lê sozinha num número solto.
  ?>
  A <strong>conta de resultado</strong> da Rede está em
  <strong><?php echo(h(formata_moeda($pos['resultado']))); ?></strong>. Ela não é dinheiro
  e por isso fica fora do caixa: acumula o que a Rede absorveu de custo e o que os
  cestantes lhe devem. O desempenho mês a mês, com as despesas, é a próxima parte desta
  tela.
</p>

<legend style="font-size:medium;">Desempenho de <?php echo(h($ano)); ?></legend>

<form class="form-inline hidden-print" method="get" action="caixa_rede.php" style="margin-bottom:10px;">
  <div class="form-group">
    <label for="ano">Ano:&nbsp;</label>
    <select id="ano" name="ano" class="form-control" onchange="this.form.submit();">
      <?php for ($a = (int)date('Y') + 1; $a >= 2024; $a--) { ?>
      <option value="<?php echo(h($a)); ?>"<?php echo($a === $ano ? ' selected' : ''); ?>><?php echo(h($a)); ?></option>
      <?php } ?>
    </select>
  </div>
</form>

<?php if ($des === null) { ?>
  <div class="alert alert-danger">Não foi possível montar o desempenho do ano.</div>
<?php } else { ?>

<div class="tabela-rolante">
<table class="table table-bordered table-condensed table-striped">
  <thead>
    <tr>
      <th rowspan="2">Mês</th>
      <th colspan="4" class="text-center">O que a Rede gerou de próprio</th>
      <th colspan="2" class="text-center">O que custou</th>
      <th rowspan="2" class="text-right">Resultado</th>
    </tr>
    <tr>
      <th class="text-right">Associação</th>
      <th class="text-right">Taxa</th>
      <th class="text-right">Margem</th>
      <th class="text-right">Outras</th>
      <th class="text-right">Nos núcleos</th>
      <th class="text-right">Da Rede</th>
    </tr>
  </thead>
  <tbody>
  <?php
    $algum = false;
    $pulados_corte = 0;
    foreach ($des['meses'] as $m => $x) {
        // MÊS ANTES DO CORTE não vira linha, e é contado à parte: ali a receita existe e
        // o custo não, porque o razão ainda não tinha começado. Mostrá-lo daria um mês
        // lucrativo por falta de dado.
        if ($x['antes_do_corte']) { $pulados_corte++; continue; }
        // mês sem nada não vira linha: doze linhas zeradas escondem as que têm número
        if (abs($x['receita']) < 0.005 && abs($x['custo']) < 0.005) continue;
        $algum = true;
  ?>
    <tr>
      <td><?php echo(h($nome_mes[$m])); ?></td>
      <td class="text-right"><?php echo(h(formata_moeda($x['associacao']))); ?></td>
      <td class="text-right"><?php echo(h(formata_moeda($x['taxa']))); ?></td>
      <td class="text-right"><?php echo(h(formata_moeda($x['margem_nao_associado'] + $x['margem_produto']))); ?></td>
      <td class="text-right"><?php echo(h(formata_moeda($x['outras']))); ?></td>
      <td class="text-right"><?php echo(h(formata_moeda($x['despesas_nucleos']))); ?></td>
      <td class="text-right"><?php echo(h(formata_moeda($x['despesas_rede']))); ?></td>
      <?php
        // TOM. Mês no vermelho não é acusação — a Rede opera perto do equilíbrio de
        // propósito. O número colorido diz o que precisa dizer sem levantar a voz, como
        // no equilíbrio do núcleo.
      ?>
      <td class="text-right"><strong class="<?php echo($x['resultado'] < -0.005 ? 'text-danger' : ($x['resultado'] > 0.005 ? 'text-success' : '')); ?>">
        <?php echo(h(formata_moeda($x['resultado']))); ?>
      </strong></td>
    </tr>
  <?php }
    if (!$algum) { ?>
    <tr><td colspan="8" class="text-muted">
      <?php if ($pulados_corte >= 12) { ?>
        Todo o ano de <?php echo(h($ano)); ?> é anterior ao início da contabilidade
        (<?php echo(h(date('m/Y', strtotime($des['corte'])))); ?>), e por isso não há
        desempenho a mostrar.
      <?php } else { ?>
        Nada registrado em <?php echo(h($ano)); ?>.
      <?php } ?>
    </td></tr>
  <?php } else { $a = $des['ano_total']; ?>
    <tr class="active">
      <th>o ano</th>
      <th class="text-right"><?php echo(h(formata_moeda($a['associacao']))); ?></th>
      <th class="text-right"><?php echo(h(formata_moeda($a['taxa']))); ?></th>
      <th class="text-right"><?php echo(h(formata_moeda($a['margem_nao_associado'] + $a['margem_produto']))); ?></th>
      <th class="text-right"><?php echo(h(formata_moeda($a['outras']))); ?></th>
      <th class="text-right"><?php echo(h(formata_moeda($a['despesas_nucleos']))); ?></th>
      <th class="text-right"><?php echo(h(formata_moeda($a['despesas_rede']))); ?></th>
      <th class="text-right"><strong class="<?php echo($a['resultado'] < -0.005 ? 'text-danger' : ($a['resultado'] > 0.005 ? 'text-success' : '')); ?>">
        <?php echo(h(formata_moeda($a['resultado']))); ?>
      </strong></th>
    </tr>
  <?php } ?>
  </tbody>
</table>
</div>

<p class="small text-muted">
  <strong>Esta receita não é dinheiro novo.</strong> É a mesma associação, taxa e margem
  que aparece no equilíbrio de cada núcleo — aqui somada, ali repartida. Somar as duas
  telas faria a Rede parecer arrecadar o dobro.
  <br><strong>O rateio não entra no custo</strong>, e é de propósito: ele move custo da
  Rede para os núcleos no papel e não muda um centavo do total. Contá-lo aqui somaria o
  mesmo gasto duas vezes. O que entra é a despesa da Rede inteira, antes de repartir, mais
  o que os núcleos gastaram do caixa deles.
  <?php if ($pulados_corte > 0 && $pulados_corte < 12) { ?>
  <br><strong><?php echo(h($pulados_corte)); ?> mês(es) deste ano ficaram de fora</strong>:
  são anteriores a <?php echo(h(date('m/Y', strtotime($des['corte'])))); ?>, quando a
  contabilidade começou. Neles a receita existe — sai dos pedidos, que são de sempre — mas
  o custo não, porque o razão ainda não estava em uso. Mostrá-los daria meses lucrativos
  por falta de dado.
  <?php } ?>
  <br><strong>Sobrou com a Rede</strong> no ano:
  <strong><?php echo(h(formata_moeda($des['ano_total']['sobra']))); ?></strong> —
  a parte das despesas centrais que não foi carimbada em núcleo nenhum. É também a
  distância entre este resultado e a soma dos resultados dos núcleos: os dois só fecham
  quando ela é zero. Centavos aqui são a sobra da divisão; um valor alto quer dizer que
  alguma despesa ficou sem rateio.
</p>

<?php } ?>

<?php } ?>

<?php footer(); ?>
