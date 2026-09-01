<?php
  require  "common.inc.php";
  require_once(__DIR__ . "/financeiro.inc.php");

  verifica_seguranca();

  // Mesma regra de escopo do caixa e do fluxo, e pela mesma função: a spec exige o
  // núcleo IMPOSTO e não sugerido, e três cópias dela divergiriam.
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

  $mes = (int)request_get("mes", date('n'));
  $ano = (int)request_get("ano", date('Y'));
  if ($mes < 1 || $mes > 12)      $mes = (int)date('n');
  if ($ano < 2000 || $ano > 2200) $ano = (int)date('Y');

  top();
  abas_financeiras('nucleo', 'resultado');

  $r          = resultado_do_nucleo($nuc_id, $ano, $mes);
  $categorias = categorias_de_despesa();
  $quota      = null;
  $quotas     = quotas_de_rateio();
  if (is_array($quotas) && isset($quotas[$nuc_id])) $quota = $quotas[$nuc_id];

  $nome_mes = array(1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',
                    7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro');

  escreve_mensagem_status();
?>

<legend>Equilíbrio do núcleo <?php echo(h($nucleo['nuc_nome_curto'])); ?></legend>

<form class="form-inline hidden-print" method="get" action="equilibrio.php">
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

<?php if ($r === null) { ?>

  <div class="alert alert-danger">
    <strong>Não foi possível calcular o equilíbrio.</strong><br>
    Nenhum número pode ser mostrado agora. Tente de novo daqui a alguns minutos.
  </div>

<?php } else { ?>

<?php
  // TOM. Este número é lido por quem cuida de um núcleo que muitas vezes está no
  // vermelho — e, como a Rede diz, estar no vermelho aqui não é erro nem falha: é o
  // ponto de partida do trabalho.
  //
  // O que pesava era a TARJA, não a cor: um bloco vermelho preenchido transforma um
  // dado em acusação. O número colorido em texto diz a mesma coisa sem levantar a voz.
?>
<p style="font-size:larger; margin-bottom:14px;">
  Equilíbrio de <?php echo(h($nome_mes[$mes] . ' de ' . $ano)); ?>:
  <?php if ($r['situacao'] === 'deficitario') { ?>
    <strong class="text-danger">&minus;R$ <?php echo(h(formata_moeda(-$r['resultado']))); ?></strong>
    <small class="text-muted">&nbsp;&mdash;&nbsp;os custos do núcleo passaram o que ele contribuiu neste mês</small>
  <?php } else if ($r['situacao'] === 'superavitario') { ?>
    <strong class="text-success">R$ <?php echo(h(formata_moeda($r['resultado']))); ?></strong>
    <small class="text-muted">&nbsp;&mdash;&nbsp;o núcleo contribuiu mais do que custou neste mês</small>
  <?php } else { ?>
    <strong>R$ 0,00</strong>
    <small class="text-muted">&nbsp;&mdash;&nbsp;o mês fechou em equilíbrio</small>
  <?php } ?>
</p>

<div class="row">
  <div class="col-sm-6">
    <table class="table table-bordered table-condensed">
      <thead><tr><th colspan="2">Quanto o núcleo contribuiu</th></tr></thead>
      <tbody>
        <?php
          // PERCORRE o que a função devolveu, em vez de listar as linhas à mão. Listadas
          // à mão, a linha de "outras receitas" nasceu na função e não chegou aqui: o
          // total já a somava e nenhuma linha visível a explicava. Detalhe que não fecha
          // com o próprio total é o defeito que faz um relatório de dinheiro perder a
          // confiança de quem o lê — e foi o mesmo que o fluxo de caixa teve.
          //
          // Assim, chave nova aparece sempre. Sem rótulo ela sai com o próprio nome, que
          // é feio e visível — melhor do que bonita e ausente.
          $rotulos_receita = array(
              'associacao'           => 'associação',
              'taxa'                 => 'taxa sobre pedidos de associados',
              'margem_nao_associado' => 'margem de não associados',
              'margem_produto'       => 'margem no produto',
              'outras'               => 'doações e outras receitas do núcleo',
          );

          foreach ($r['receita'] as $chave_r => $valor_r)
          {
              if ($chave_r === 'total') continue;
              // linha zerada some quando é rara; as três primeiras ficam sempre, porque
              // sumir e voltar faria a tabela mudar de forma de um mês para outro
              $sempre = in_array($chave_r, array('associacao', 'taxa', 'margem_nao_associado'));
              if (!$sempre && abs($valor_r) < 0.005) continue;
        ?>
        <tr>
          <td><?php echo(h(isset($rotulos_receita[$chave_r]) ? $rotulos_receita[$chave_r] : $chave_r)); ?></td>
          <td class="text-right"><?php echo(h(formata_moeda($valor_r))); ?></td>
        </tr>
        <?php } ?>
        <tr class="active"><th>total</th><th class="text-right"><?php echo(h(formata_moeda($r['receita']['total']))); ?></th></tr>
      </tbody>
    </table>
  </div>

  <div class="col-sm-6">
    <table class="table table-bordered table-condensed">
      <thead><tr><th colspan="2">Custos do núcleo</th></tr></thead>
      <tbody>
        <?php
          // Todas as seis, mesmo zeradas: categoria que some no mês em que ninguém
          // gastou faria a tabela mudar de forma de um mês para outro.
          foreach ($categorias as $ck => $cr) {
              $v = isset($r['custo']['proprias'][$ck]) ? $r['custo']['proprias'][$ck] : 0.0; ?>
        <tr><td><?php echo(h($cr)); ?></td><td class="text-right"><?php echo(h(formata_moeda($v))); ?></td></tr>
        <?php } ?>
        <?php foreach ($r['custo']['proprias'] as $ck => $v) { if (isset($categorias[$ck])) continue; ?>
        <tr><td><?php echo(h($ck !== '' ? $ck : 'sem categoria')); ?></td><td class="text-right"><?php echo(h(formata_moeda($v))); ?></td></tr>
        <?php } ?>
        <tr><td><strong>rateio dos custos da Rede</strong></td><td class="text-right"><strong><?php echo(h(formata_moeda($r['custo']['total_rateio']))); ?></strong></td></tr>
        <tr class="active"><th>total</th><th class="text-right"><?php echo(h(formata_moeda($r['custo']['total']))); ?></th></tr>
      </tbody>
    </table>
  </div>
</div>

<legend style="font-size:medium;">De onde vem o rateio</legend>

<?php
  // O rateio ABERTO, despesa por despesa. Sem isto ele é um número que apareceu, e
  // número que aparece sem explicação se lê como imposto.
  if (!count($r['custo']['rateio'])) { ?>
  <p class="text-muted">Nenhum custo da Rede foi rateado para este núcleo em <?php echo(h($nome_mes[$mes] . ' de ' . $ano)); ?>.</p>
<?php } else { ?>
<table class="table table-bordered table-condensed table-striped">
  <?php
    // A DESPESA INTEIRA E A FATIA, lado a lado. Só o pedaço não deixa julgar nada:
    // "R$ 82,00 de hospedagem" não diz se é a conta toda ou um oitavo dela, e é a fatia
    // que faz a conversa ser outra. Com o total ao lado, o núcleo vê o tamanho do custo
    // da Rede e o quanto dele lhe coube — que é a pergunta que o rateio levanta.
  ?>
  <thead><tr>
    <th>Data</th><th>Área</th><th>Despesa da Rede</th>
    <th class="text-right">Total da despesa</th>
    <th class="text-right">Coube a este núcleo</th>
    <th class="text-right">%</th>
  </tr></thead>
  <tbody>
    <?php
      $soma_total_desp = 0.0;
      foreach ($r['custo']['rateio'] as $l) {
          if ($l['total'] !== null) $soma_total_desp = round($soma_total_desp + $l['total'], 2);
    ?>
    <tr>
      <td><?php echo(h(date('d/m/Y', strtotime($l['dt'])))); ?></td>
      <td><?php echo(h($l['categoria_rotulo'])); ?></td>
      <td><?php echo(h($l['historico'])); ?></td>
      <td class="text-right">
        <?php echo($l['total'] === null
                   ? '<span class="text-muted">&mdash;</span>'
                   : h(formata_moeda($l['total']))); ?>
      </td>
      <td class="text-right"><?php echo(h(formata_moeda($l['valor']))); ?></td>
      <td class="text-right">
        <?php
          // Sem total não há percentual — e travessão é mais honesto que 0%, que se
          // leria como "não coube nada".
          echo(($l['total'] === null || $l['total'] <= 0.005)
               ? '<span class="text-muted">&mdash;</span>'
               : h(number_format(100 * $l['valor'] / $l['total'], 1, ',', '.')) . '%');
        ?>
      </td>
    </tr>
    <?php } ?>
    <tr class="active">
      <th colspan="3" class="text-right">total</th>
      <th class="text-right"><?php echo(h(formata_moeda($soma_total_desp))); ?></th>
      <th class="text-right"><?php echo(h(formata_moeda($r['custo']['total_rateio']))); ?></th>
      <th class="text-right">
        <?php echo($soma_total_desp > 0.005
                   ? h(number_format(100 * $r['custo']['total_rateio'] / $soma_total_desp, 1, ',', '.')) . '%'
                   : '<span class="text-muted">&mdash;</span>'); ?>
      </th>
    </tr>
  </tbody>
</table>
<?php } ?>

<p class="small text-muted">
  Este quadro <strong>não é o caixa</strong>. Ele não diz quanto dinheiro está com o núcleo —
  diz se o núcleo se paga. O que ele <strong>contribui</strong> é só o que a Rede cobra a
  mais: quase tudo que o cestante paga é do produtor e passa direto.
  O rateio é a parte dos custos fixos da Rede apontada para este núcleo<?php if ($quota !== null) { ?>,
  que entra na divisão por entrega com <strong><?php echo(h(rtrim(rtrim(number_format($quota, 1, ',', ''), '0'), ','))); ?> quota(s)</strong><?php } ?>.
  Ele <strong>não</strong> é dívida: nenhum saldo de caixa se mexe por causa dele.
  Fechar o mês no negativo não é erro — é o sinal de que aquele núcleo precisa de mais
  associados ou de menos custo.
  A conta é por <strong>entrega</strong>, não por pagamento, então quem pagou atrasado não
  muda este número.
</p>

<?php } ?>

<?php footer(); ?>
