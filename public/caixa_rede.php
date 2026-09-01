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

  top();
  abas_financeiras('rede', 'caixa');

  $pos = posicao_da_rede();

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

<?php } ?>

<?php footer(); ?>
