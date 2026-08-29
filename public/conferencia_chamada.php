<?php
  require  "common.inc.php";
  require_once(__DIR__ . "/financeiro.inc.php");

  verifica_seguranca();

  // Mesma audiência de entrega_divergencias.php — é a continuação daquela tela, com o
  // dinheiro ao lado das quantidades. Mais a trava do módulo, que não passa por
  // verifica_seguranca(): aquela função valida qualquer chamada de PAP_ADM sem olhar o
  // parâmetro (common.inc.php:103-110).
  if (!pode_ver_financeiro()
      || (empty($_SESSION[PAP_RESP_ENTREGA]) && empty($_SESSION[PAP_RESP_FINANCAS])
          && empty($_SESSION[PAP_ADM])))
  {
      adiciona_mensagem_status(MSG_TIPO_ERRO, "Usuário não possui permissão para a ação executada.");
      redireciona(PAGINAPRINCIPAL);
      exit();
  }

  // A chamada em foco é lembrada entre as telas de entrega, como em
  // entrega_divergencias.php:8-15 — quem está conferindo uma chamada não quer
  // reescolhê-la a cada clique.
  $cha_id = request_get("cha_id", -1);
  if ($cha_id == -1 && isset($_SESSION['cha_id_pref'])) $cha_id = $_SESSION['cha_id_pref'];
  if (!is_string($cha_id) && !is_int($cha_id)) $cha_id = -1;
  if (ctype_digit((string)$cha_id) && (int)$cha_id > 0) $_SESSION['cha_id_pref'] = (int)$cha_id;

  top();

  $conf = (ctype_digit((string)$cha_id) && (int)$cha_id > 0)
        ? conferencia_da_chamada($cha_id) : null;

  escreve_mensagem_status();
?>

<ul class="nav nav-tabs">
  <li><a href="entregas.php">Entregas</a></li>
  <li><a href="entrega_nucleos_consolidado.php"><i class="glyphicon glyphicon-road"></i> Recebido pelo Núcleo</a></li>
  <li><a href="entrega_cestantes_consolidado.php"><i class="glyphicon glyphicon-grain"></i> Entregue aos Cestantes</a></li>
  <li><a href="entrega_divergencias.php"><i class="glyphicon glyphicon-eye-open"></i> Divergências</a></li>
  <li class="active"><a href="#"><i class="glyphicon glyphicon-scale"></i> Conferência em R$</a></li>
</ul>
<br>

<form class="form-inline hidden-print" method="get" action="conferencia_chamada.php">
  <div class="form-group">
    <label for="cha_id">Chamada:&nbsp;</label>
    <select id="cha_id" name="cha_id" class="form-control" onchange="this.form.submit();">
      <option value="-1">escolha uma chamada</option>
      <?php
        // Só a partir da data de corte: conferir chamada de 2013 em dinheiro não serve
        // a ninguém, e a lista ficaria com centenas de linhas.
        $res_cha = executa_sql(
            "SELECT c.cha_id, c.cha_dt_entrega, pt.prodt_nome FROM chamadas c "
          . "JOIN produtotipos pt ON pt.prodt_id = c.cha_prodt "
          . "WHERE c.cha_dt_entrega >= " . prep_para_bd(DATA_CORTE_FINANCEIRO) . " "
          . "ORDER BY c.cha_dt_entrega DESC, c.cha_id DESC");
        while ($res_cha && $rc = mysqli_fetch_array($res_cha, MYSQLI_ASSOC)) {
      ?>
      <option value="<?php echo(h($rc['cha_id'])); ?>"<?php echo(((string)$rc['cha_id'] === (string)$cha_id) ? ' selected' : ''); ?>>
        <?php echo(h($rc['prodt_nome'] . ' — ' . date('d/m/Y', strtotime($rc['cha_dt_entrega'])))); ?>
      </option>
      <?php } ?>
    </select>
  </div>
</form>
<br>

<?php if ($conf === null) { ?>

  <div class="alert alert-info">Escolha uma chamada para conferir onde a mercadoria entrou e saiu.</div>

<?php } else { ?>

<legend style="font-size:medium;">
  <?php echo(h($conf['tipo'] . ' — entrega de ' . date('d/m/Y', strtotime($conf['dt'])))); ?>
</legend>

<div class="row">
  <div class="col-sm-7">
    <table class="table table-bordered table-condensed">
      <tbody>
        <tr><td>estoque no começo</td><td class="text-right"><?php echo(h(formata_moeda($conf['estoque']['antes']))); ?></td></tr>
        <tr><td>confirmado por Finanças <small class="text-muted">&mdash; paga o produtor</small></td>
            <td class="text-right"><?php echo(h(formata_moeda($conf['confirmado']))); ?></td></tr>
        <tr><td>entregue aos cestantes <small class="text-muted">&mdash; cobra o cestante</small></td>
            <td class="text-right"><?php echo(h(formata_moeda($conf['total']['distribuiu']))); ?></td></tr>
        <tr><td>estoque no fim</td><td class="text-right"><?php echo(h(formata_moeda($conf['estoque']['depois']))); ?></td></tr>
        <tr class="active">
          <th>pago e não cobrado</th>
          <th class="text-right<?php echo(abs($conf['nao_cobrado']) > 0.005 ? ' text-danger' : ''); ?>">
            <?php echo(h(formata_moeda($conf['nao_cobrado']))); ?>
          </th>
        </tr>
      </tbody>
    </table>
  </div>
  <div class="col-sm-5">
    <p class="small text-muted">
      <strong>Pago e não cobrado</strong> é o que a Rede pagou ao produtor e ninguém foi
      cobrado — já descontado o que ficou guardado em estoque. Sobrou, foi doado, estragou
      depois de aceito, ou a entrega não foi anotada.
      <br><br>
      Os núcleos confirmaram receber <strong><?php echo(h(formata_moeda($conf['total']['recebeu']))); ?></strong>,
      e Finanças confirmou <strong><?php echo(h(formata_moeda($conf['confirmado']))); ?></strong> —
      uma diferença de <strong><?php echo(h(formata_moeda($conf['abatido']))); ?></strong>,
      que é o que ela abateu ao ler as justificativas.
      <br><br>
      Tudo a <strong>preço de venda</strong>: a pergunta aqui é quanto disto virou dívida de
      alguém.
    </p>
  </div>
</div>

<legend style="font-size:medium;">Por núcleo</legend>

<table class="table table-bordered table-condensed table-striped">
  <thead>
    <tr>
      <th>Núcleo</th>
      <th class="text-right">Confirmou receber</th>
      <th class="text-right">Entregou aos cestantes</th>
      <th class="text-right">Diferença</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($conf['nucleos'] as $n) { ?>
    <tr>
      <td><?php echo(h($n['nome'])); ?></td>
      <td class="text-right"><?php echo(h(formata_moeda($n['recebeu']))); ?></td>
      <td class="text-right"><?php echo(h(formata_moeda($n['distribuiu']))); ?></td>
      <td class="text-right<?php echo(abs($n['diferenca']) > 0.005 ? ' text-danger' : ''); ?>">
        <?php echo(h(formata_moeda($n['diferenca']))); ?>
      </td>
      <td>
        <?php
          // O aviso é o que impede de cobrar do núcleo um erro de digitação: sem ele, a
          // diferença acima seria lida como perda quando pode ser entrega não anotada.
          if ($n['sem_registro'] > 0) { ?>
          <span class="label label-warning"><?php echo(h($n['sem_registro'])); ?> sem entrega registrada</span>
          <small class="text-muted">&nbsp;a diferença pode ser só isto</small>
        <?php } ?>
      </td>
    </tr>
  <?php } ?>
  <?php if (!count($conf['nucleos'])) { ?>
    <tr><td colspan="5">Nenhum núcleo movimentou esta chamada.</td></tr>
  <?php } else { ?>
    <tr class="active">
      <th>total</th>
      <th class="text-right"><?php echo(h(formata_moeda($conf['total']['recebeu']))); ?></th>
      <th class="text-right"><?php echo(h(formata_moeda($conf['total']['distribuiu']))); ?></th>
      <th class="text-right"><?php echo(h(formata_moeda($conf['total']['diferenca']))); ?></th>
      <th></th>
    </tr>
  <?php } ?>
  </tbody>
</table>

<p class="small text-muted">
  Diferença <strong>positiva</strong>: o núcleo confirmou receber mais do que entregou.
  <strong>Negativa</strong>: entregou sem ter confirmado o recebimento — a conta não fecha
  por falta de registro, não por falta de mercadoria.
  <br>Para ver produto a produto e a justificativa de cada divergência, use
  <a href="entrega_divergencias.php">Divergências</a>.
</p>

<?php } ?>

<?php footer(); ?>
