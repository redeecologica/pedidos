<?php
  require  "common.inc.php";
  require_once(__DIR__ . "/financeiro.inc.php");

  verifica_seguranca();

  // Mesma regra da tela de caixa, e pela mesma função: a spec exige o escopo por núcleo
  // IMPOSTO e não sugerido, e duas cópias dela divergiriam. Devolve "" quando não há
  // núcleo alcançável — inclusive quando falta o papel Beta Tester, porque
  // pode_lancar_no_caixa() começa por pode_ver_financeiro().
  //
  // Antes de top(), para a recusa sair com o cabeçalho ainda não enviado.
  $manda_em_todos = (!empty($_SESSION[PAP_RESP_FINANCAS]) || !empty($_SESSION[PAP_ADM]));
  $nuc_id = nucleo_do_caixa_em_foco(request_get("nuc_id", ""));

  if ($nuc_id === "")
  {
      adiciona_mensagem_status(MSG_TIPO_ERRO, "Usuário não possui permissão para a ação executada.");
      redireciona(PAGINAPRINCIPAL);
      exit();
  }

  $res_nuc = executa_sql("SELECT nuc_nome_curto FROM nucleos WHERE nuc_id = " . prep_para_bd($nuc_id));
  $nucleo  = $res_nuc ? mysqli_fetch_array($res_nuc, MYSQLI_ASSOC) : null;

  if (!$nucleo)
  {
      adiciona_mensagem_status(MSG_TIPO_ERRO, "Núcleo não encontrado.");
      redireciona(PAGINAPRINCIPAL);
      exit();
  }

  $ano = request_get("ano", "");
  if (!is_string($ano) && !is_int($ano)) $ano = "";
  if (!ctype_digit((string)$ano) || (int)$ano < 2000 || (int)$ano > 2200) $ano = date('Y');
  $ano = (int)$ano;

  top();
  abas_financeiras('nucleo', 'fluxo');

  $fluxo = fluxo_de_caixa_mensal($nuc_id, $ano);

  // Anos que têm movimento, para o seletor não oferecer vinte anos vazios. O ano
  // corrente entra sempre: é onde se lança hoje, mesmo que ainda não haja nada nele.
  $anos = array((int)date('Y') => true, $ano => true);
  $res_anos = executa_sql(
      "SELECT DISTINCT YEAR(t.tra_dt) ano FROM lancamentos l "
    . "JOIN transacoes t ON t.tra_id = l.lan_tra "
    . "JOIN contas c ON c.con_id = l.lan_con AND c.con_tipo = 'nucleo' "
    . "WHERE c.con_nuc = " . prep_para_bd($nuc_id) . " ORDER BY ano DESC");
  while ($res_anos && $r = mysqli_fetch_array($res_anos, MYSQLI_ASSOC)) $anos[(int)$r['ano']] = true;
  $anos = array_keys($anos);
  rsort($anos);

  $nome_mes = array(1=>'jan',2=>'fev',3=>'mar',4=>'abr',5=>'mai',6=>'jun',
                    7=>'jul',8=>'ago',9=>'set',10=>'out',11=>'nov',12=>'dez');

  // Zero em 12 colunas × 15 linhas vira ruído que esconde o que tem valor. Traço no
  // lugar do zero deixa o olho achar o número que existe.
  //
  // NAS LINHAS DE SALDO, NÃO. Ali zero é informação — "o mês fechou em dia", "o caixa
  // ficou vazio" —, e um traço no mesmo lugar se leria como "não há dado". A distinção
  // entre "nada aconteceu" e "aconteceu e deu zero" é justamente a que este módulo
  // existe para não borrar.
  function celula($v, $zero_conta = false)
  {
      if (!$zero_conta && abs((float)$v) < 0.005) { echo('<span class="text-muted">&ndash;</span>'); return; }
      echo(h(formata_moeda($v)));
  }

  escreve_mensagem_status();
?>

<legend>Fluxo de caixa &middot; <?php echo(h($nucleo['nuc_nome_curto'])); ?></legend>

<form class="form-inline hidden-print" method="get" action="fluxo_caixa.php" name="frm_fluxo">
  <?php if ($manda_em_todos) { ?>
  <div class="form-group">
    <label for="nuc_id">Núcleo:&nbsp;</label>
    <select id="nuc_id" name="nuc_id" class="form-control" onchange="this.form.submit();">
      <?php foreach ((array)nucleos_com_caixa() as $id_nuc => $nome_nuc) { ?>
      <option value="<?php echo(h($id_nuc)); ?>"<?php echo(((string)$id_nuc === (string)$nuc_id) ? ' selected' : ''); ?>><?php echo(h($nome_nuc)); ?></option>
      <?php } ?>
    </select>
  </div>
  &nbsp;&nbsp;
  <?php } ?>
  <div class="form-group">
    <label for="ano">Ano:&nbsp;</label>
    <select id="ano" name="ano" class="form-control" onchange="this.form.submit();">
      <?php foreach ($anos as $a) { ?>
      <option value="<?php echo(h($a)); ?>"<?php echo($a === $ano ? ' selected' : ''); ?>><?php echo(h($a)); ?></option>
      <?php } ?>
    </select>
  </div>
</form>
<br>

<?php if ($fluxo === null) { ?>

  <div class="alert alert-danger">
    <strong>Não foi possível montar o fluxo de caixa.</strong><br>
    Nenhum número pode ser mostrado agora. Tente de novo daqui a alguns minutos.
  </div>

<?php } else { ?>

<p class="small text-muted">
  Agrupado pela <strong>data do fato</strong>, não pela data em que alguém digitou: o
  lançamento de segunda referente à sexta conta na semana em que aconteceu.
  <strong>Repasse à Rede e pagamento a produtor ficam fora das despesas</strong> — não são
  custo do núcleo, é dinheiro da Rede mudando de mãos, e somá-los tornaria a linha de
  despesa incomparável entre núcleos.
</p>

<div class="tabela-rolante">
<table class="table table-bordered table-condensed fluxo-caixa">
  <thead>
    <tr>
      <th class="coluna-rotulo">&nbsp;</th>
      <?php foreach ($fluxo['meses'] as $m) { ?>
      <th class="text-right"><?php echo(h($nome_mes[$m])); ?></th>
      <?php } ?>
      <th class="text-right">TOTAL</th>
    </tr>
  </thead>
  <tbody>
  <?php
    $blocos = array(
        'entradas' => 'Entradas',
        'despesas' => 'Despesas',
        'repasses' => 'Repasses e pagamentos',
        'outros'   => 'Outros lançamentos',
    );

    foreach ($blocos as $bloco => $titulo)
    {
        $linhas_do_bloco = array();
        foreach ($fluxo['linhas'] as $l) if ($l['bloco'] === $bloco) $linhas_do_bloco[] = $l;

        // 'outros' só existe quando há tipo fora das linhas fixas; os demais aparecem
        // sempre, mesmo zerados, para a tabela não mudar de forma de um ano para outro.
        if (!count($linhas_do_bloco)) continue;
  ?>
    <tr class="active">
      <th class="coluna-rotulo" colspan="14"><?php echo(h($titulo)); ?></th>
    </tr>
    <?php foreach ($linhas_do_bloco as $l) { ?>
    <tr>
      <td class="coluna-rotulo">&nbsp;&nbsp;<?php echo(h($l['rotulo'])); ?></td>
      <?php foreach ($fluxo['meses'] as $m) { ?>
      <td class="text-right"><?php celula($l['meses'][$m]); ?></td>
      <?php } ?>
      <td class="text-right"><strong><?php celula($l['total']); ?></strong></td>
    </tr>
    <?php } ?>

    <?php if ($bloco === 'entradas' || $bloco === 'despesas') {
            $totais = ($bloco === 'entradas') ? $fluxo['entradas'] : $fluxo['total_despesas']; ?>
    <tr>
      <td class="coluna-rotulo"><strong>&nbsp;&nbsp;total de <?php echo($bloco === 'entradas' ? 'entradas' : 'despesas'); ?></strong></td>
      <?php foreach ($fluxo['meses'] as $m) { ?>
      <td class="text-right"><strong><?php celula($totais[$m]); ?></strong></td>
      <?php } ?>
      <td class="text-right"><strong><?php celula(array_sum($totais)); ?></strong></td>
    </tr>
    <?php } ?>
  <?php } ?>

    <tr class="active">
      <th class="coluna-rotulo">Saldo do mês</th>
      <?php foreach ($fluxo['meses'] as $m) { ?>
      <th class="text-right<?php echo($fluxo['saldo_mes'][$m] < -0.005 ? ' text-danger' : ''); ?>"><?php celula($fluxo['saldo_mes'][$m], true); ?></th>
      <?php } ?>
      <th class="text-right"><?php celula(array_sum($fluxo['saldo_mes']), true); ?></th>
    </tr>
    <tr class="active">
      <th class="coluna-rotulo">Em caixa ao fim do mês</th>
      <?php foreach ($fluxo['meses'] as $m) { ?>
      <th class="text-right<?php echo($fluxo['saldo_acumulado'][$m] < -0.005 ? ' text-danger' : ''); ?>"><?php celula($fluxo['saldo_acumulado'][$m], true); ?></th>
      <?php } ?>
      <th class="text-right">&nbsp;</th>
    </tr>
  </tbody>
</table>
</div>

<p class="small text-muted">
  <strong>Em caixa</strong> é quanto dinheiro estava com o núcleo ao fim daquele mês — a
  mesma conta do extrato do caixa, vista mês a mês. Começa o ano em
  <strong>R$ <?php echo(h(formata_moeda($fluxo['saldo_anterior']))); ?></strong>, que é o
  que sobrou de <?php echo(h($ano - 1)); ?>.
  Negativo aqui quer dizer que o núcleo gastou mais do que recebeu e tem a receber da Rede.
  <br>As <strong>outras receitas</strong> — doação, rendimento — são do próprio núcleo e
  entram no resultado dele; as demais entradas passam adiante.
</p>

<?php } ?>

<?php footer(); ?>
