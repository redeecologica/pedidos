<?php
  require  "common.inc.php";
  // require_once e __DIR__ pelo mesmo motivo do conta_cestante.php: a página que
  // alcançar o menu antes daqui morreria por redeclaração de função.
  require_once(__DIR__ . "/financeiro.inc.php");

  verifica_seguranca();

  // O NÚCLEO EM FOCO É IMPOSTO, NÃO SUGERIDO.
  //
  // A spec é explícita: o escopo por núcleo sai de $_SESSION['usr.nuc'], e o padrão
  // do cestantes.php:18 — ler o núcleo de request_get — não serve numa tela de
  // dinheiro, porque outro núcleo na URL o contorna. Aqui quem responde por um núcleo
  // só nunca lê a URL: o valor vem da sessão e ponto. Quem responde por todos escolhe,
  // e pode_lancar_no_caixa() confere a escolha de novo, logo abaixo.
  $manda_em_todos = (!empty($_SESSION[PAP_RESP_FINANCAS]) || !empty($_SESSION[PAP_ADM]));
  $nuc_sessao     = isset($_SESSION['usr.nuc']) ? $_SESSION['usr.nuc'] : "";

  $nuc_id = $manda_em_todos ? request_get("nuc_id", $nuc_sessao) : $nuc_sessao;

  if (!is_string($nuc_id) && !is_int($nuc_id)) $nuc_id = "";
  if (!ctype_digit((string)$nuc_id) || (int)$nuc_id <= 0) $nuc_id = "";

  // Quem responde por todos e não tem núcleo próprio na sessão — ou tem um arquivado —
  // cairia numa recusa vinda do item de menu, o que se lê como falta de permissão e não
  // é. Para essa pessoa qualquer núcleo serve de ponto de partida, e o seletor logo
  // abaixo troca. Para quem responde por um só isto NÃO roda: lá o núcleo é imposto, e
  // escolher um por ela seria abrir o caixa de outro núcleo.
  if ($nuc_id === "" && $manda_em_todos)
  {
      $res_1o = executa_sql(
          "SELECT n.nuc_id FROM nucleos n "
        . "JOIN contas c ON c.con_nuc = n.nuc_id AND c.con_tipo = 'nucleo' AND c.con_archive = 0 "
        . "WHERE n.nuc_archive = 0 ORDER BY n.nuc_nome_curto LIMIT 1");
      $row_1o = $res_1o ? mysqli_fetch_array($res_1o, MYSQLI_ASSOC) : null;
      if ($row_1o) $nuc_id = (string)$row_1o['nuc_id'];
  }

  // A trava do módulo NÃO passa por verifica_seguranca(): aquela função valida qualquer
  // chamada de PAP_ADM sem olhar o parâmetro (common.inc.php:103-110), então o módulo
  // ficaria aberto para administrador sem Beta Tester — o contrário de "invisível até
  // estar pronto". pode_lancar_no_caixa() começa por pode_ver_financeiro().
  //
  // Antes de top(), para a recusa sair com o cabeçalho ainda não enviado.
  if ($nuc_id === "" || !pode_lancar_no_caixa($nuc_id))
  {
      adiciona_mensagem_status(MSG_TIPO_ERRO, "Usuário não possui permissão para a ação executada.");
      redireciona(PAGINAPRINCIPAL);
      exit();
  }

  $nuc_id = (int)$nuc_id;

  // Núcleo que a tela não confirmou existir não recebe afirmação nenhuma sobre dinheiro:
  // um id sem núcleo não tem lançamento, o saldo sairia zero e a tela diria "em dia".
  $res_nuc = executa_sql("SELECT nuc_id, nuc_nome_curto FROM nucleos WHERE nuc_id = " . prep_para_bd($nuc_id));
  $nucleo  = $res_nuc ? mysqli_fetch_array($res_nuc, MYSQLI_ASSOC) : null;

  if (!$nucleo)
  {
      adiciona_mensagem_status(MSG_TIPO_ERRO, "Núcleo não encontrado.");
      redireciona(PAGINAPRINCIPAL);
      exit();
  }

  if (request_get("action", "") == ACAO_SALVAR)
  {
      // O nuc_id do POST NÃO é consultado aqui: o núcleo é o que já foi imposto e
      // autorizado lá em cima. O campo escondido do formulário serve só para o
      // responsável de finanças não perder o núcleo em foco ao gravar.
      $tipo = request_get("mv_tipo", "");
      if (!is_string($tipo)) $tipo = "";

      // Duas listas de contraparte, e o TIPO escolhe qual vale. Renderizar as duas e
      // decidir no servidor mantém a tela utilizável sem JavaScript — e é o servidor
      // que decide qual campo conta, então trocar o outro no POST não muda nada.
      $contraparte = ($tipo === 'pagamento_produtor')
                   ? request_get("mv_produtor", "")
                   : request_get("mv_rede", "");

      // date_create_from_format devolve FALSE para texto que não é data, e
      // date_format(false, ...) é TypeError no PHP 8 — a página inteira cairia por
      // causa de um campo digitado torto.
      $data  = date_create_from_format('d/m/Y', trim((string)request_get("mv_dt", "")));
      $valor = request_get("mv_valor", "");
      $valor = is_string($valor) || is_int($valor) || is_float($valor)
             ? (float)str_replace(',', '.', str_replace('.', '', trim((string)$valor))) : 0;

      $tra = null;
      if ($data)
          $tra = lanca_movimento_nucleo($nuc_id, $tipo, date_format($data, 'Y-m-d'), $valor,
              $contraparte, array(
                  'categoria'   => request_get("mv_categoria", ""),
                  'historico'   => request_get("mv_historico", ""),
                  'comprovante' => request_get("mv_comprovante", ""),
              ));

      // A recusa não diz QUAL guarda barrou, de propósito: lanca_movimento_nucleo()
      // não distingue engano de quem digitou e POST forjado, e as duas se tratam igual.
      // A mensagem lista o que a tela sabe conferir, que é o que ajuda quem errou.
      // "Confira a conta de destino" manda conferir um campo que a tela nem mostrou
      // quando não há produtor com conta aberta — e aí a recusa parece defeito.
      $sem_produtor = ($tipo === 'pagamento_produtor')
                   && !count((array)contas_de_destino_do_tipo('produtor'));

      adiciona_mensagem_status($tra ? MSG_TIPO_SUCESSO : MSG_TIPO_ERRO,
          $tra ? "Lançamento registrado."
               : ($sem_produtor
                   ? "Nenhum produtor tem conta aberta, então não há como registrar pagamento"
                   . " a produtor. Quem abre essas contas é a administração, na tela de Contas."
                   : "Não foi possível registrar. Confira a data, o valor, a conta de destino"
                   . " e — se for despesa — a categoria."));

      // POST-redirect-GET: um F5 depois de gravar não relança. Numa tela de dinheiro
      // isso não é incômodo, é dano.
      redireciona("conta_nucleo.php?nuc_id=" . urlencode($nuc_id));
      exit();
  }

  top();

  $extrato = extrato_do_nucleo($nuc_id);
  $nao_deu = ($extrato === null);
  $saldo   = $nao_deu ? null : (count($extrato) ? end($extrato)['saldo'] : 0.0);

  $contas_rede = contas_de_destino_do_tipo('rede');
  $contas_forn = contas_de_destino_do_tipo('produtor');
  $categorias  = categorias_de_despesa();
  $hoje        = date('d/m/Y');

  $rotulo_tipo = array(
      'despesa'            => 'despesa',
      'repasse'            => 'repasse à Rede',
      'pagamento_produtor' => 'pagamento a produtor',
      'receita'            => 'outra receita',
  );

  escreve_mensagem_status();
?>

<legend>Caixa do núcleo <?php echo(h($nucleo['nuc_nome_curto'])); ?></legend>

<?php if ($manda_em_todos) { ?>
<form class="form-inline hidden-print" method="get" action="conta_nucleo.php" name="frm_nucleo">
  <div class="form-group">
    <label for="nuc_id">Núcleo:&nbsp;</label>
    <select id="nuc_id" name="nuc_id" class="form-control" onchange="this.form.submit();">
      <?php
        // Só núcleo ativo E com caixa aberto: sem conta não há extrato nem lançamento,
        // e oferecer o núcleo assim mesmo levaria a uma tela que não faz nada.
        $res_lista = executa_sql(
            "SELECT n.nuc_id, n.nuc_nome_curto FROM nucleos n "
          . "JOIN contas c ON c.con_nuc = n.nuc_id AND c.con_tipo = 'nucleo' AND c.con_archive = 0 "
          . "WHERE n.nuc_archive = 0 ORDER BY n.nuc_nome_curto");
        while ($res_lista && $linha_nuc = mysqli_fetch_array($res_lista, MYSQLI_ASSOC))
        {
      ?>
      <option value="<?php echo(h($linha_nuc['nuc_id'])); ?>"<?php echo(((string)$linha_nuc['nuc_id'] === (string)$nuc_id) ? ' selected' : ''); ?>><?php echo(h($linha_nuc['nuc_nome_curto'])); ?></option>
      <?php } ?>
    </select>
  </div>
</form>
<br>
<?php } ?>

<p>
  <?php
    // saldo null é "a consulta não rodou", e não "o caixa está zerado". Numa tela de
    // caixa a segunda leitura vira "não deve nada", que é a mentira mais cara do módulo.
    if ($nao_deu) { ?>
    <span class="label label-danger" style="font-size:larger;">não foi possível calcular o saldo</span>
  <?php } else if ($saldo < -0.005) { ?>
    <span class="label label-danger" style="font-size:larger;">deve à Rede: R$ <?php echo(formata_moeda(-$saldo)); ?></span>
    <span class="text-muted">&nbsp;dinheiro que entrou no caixa e ainda não saiu</span>
  <?php } else if ($saldo > 0.005) { ?>
    <span class="label label-info" style="font-size:larger;">a receber da Rede: R$ <?php echo(formata_moeda($saldo)); ?></span>
    <span class="text-muted">&nbsp;o núcleo adiantou mais do que arrecadou</span>
  <?php } else { ?>
    <span class="label label-success" style="font-size:larger;">em dia</span>
  <?php } ?>
</p>

<?php
  // Sem contraparte cadastrada não existe lançamento válido — contas_de_destino_do_tipo()
  // É a validação de lanca_movimento_nucleo(). As duas causas saem separadas porque
  // pedem providências diferentes: uma é esperar, a outra é cadastrar.
  $sem_rede = !is_array($contas_rede) || !count($contas_rede);
  if (!is_array($contas_rede)) { ?>
  <div class="alert alert-danger">
    <strong>Não foi possível carregar as contas de destino.</strong><br>
    Nada pode ser lançado agora. Tente de novo daqui a alguns minutos.
  </div>
<?php } else if (!count($contas_rede)) { ?>
  <div class="alert alert-warning">
    <strong>Nenhuma conta da Rede cadastrada.</strong><br>
    Despesa, repasse e outra receita precisam dizer contra qual conta da Rede foram —
    sem nenhuma cadastrada, não há como lançar. Quem cria essas contas é a administração,
    na tela de Contas.
  </div>
<?php } ?>

<?php if (!$sem_rede) { ?>
<form method="post" action="conta_nucleo.php" name="form_caixa" class="hidden-print">
  <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />
  <input type="hidden" name="nuc_id" value="<?php echo(h($nuc_id)); ?>" />

  <div class="panel panel-default">
    <div class="panel-heading">Novo lançamento</div>
    <div class="panel-body">

      <div class="row">
        <div class="col-sm-4">
          <label for="mv_tipo">O que foi</label>
          <select id="mv_tipo" name="mv_tipo" class="form-control">
            <?php foreach ($rotulo_tipo as $chave => $rotulo) { ?>
            <option value="<?php echo(h($chave)); ?>"><?php echo(h($rotulo)); ?></option>
            <?php } ?>
          </select>
          <span class="help-block small">
            Despesa e repasse mexem o saldo do mesmo jeito — o que muda é para onde o
            dinheiro foi, e é isso que o relatório separa.
            <strong>Outra receita aumenta o que o núcleo deve</strong>, como o pagamento de
            um cestante: o dinheiro entrou no caixa e pertence à Rede.
          </span>
        </div>
        <div class="col-sm-3">
          <label for="mv_dt">Data</label>
          <input type="text" id="mv_dt" class="form-control data" name="mv_dt" value="<?php echo(h($hoje)); ?>" />
        </div>
        <div class="col-sm-3">
          <label for="mv_valor">Valor</label>
          <input type="text" id="mv_valor" class="form-control numero" name="mv_valor" value="" autofocus />
        </div>
      </div>

      <div class="row" style="margin-top:10px;">
        <div class="col-sm-4" id="bloco_categoria">
          <label for="mv_categoria">Categoria <small class="text-muted">(só despesa)</small></label>
          <select id="mv_categoria" name="mv_categoria" class="form-control">
            <?php
              // Sem opção vazia: despesa SEM categoria é recusada, e uma primeira opção
              // em branco convidaria justamente a recusa. 'outros' existe para o gasto
              // que não cabe nas outras cinco, e é o padrão honesto.
              foreach ($categorias as $chave => $rotulo) { ?>
            <option value="<?php echo(h($chave)); ?>"<?php echo($chave === 'outros' ? ' selected' : ''); ?>><?php echo(h($rotulo)); ?></option>
            <?php } ?>
          </select>
        </div>

        <div class="col-sm-4" id="bloco_rede">
          <label for="mv_rede">Conta da Rede</label>
          <select id="mv_rede" name="mv_rede" class="form-control">
            <?php foreach ($contas_rede as $con_id => $rotulo) { ?>
            <option value="<?php echo(h($con_id)); ?>"><?php echo(h($rotulo)); ?></option>
            <?php } ?>
          </select>
        </div>

        <div class="col-sm-4" id="bloco_produtor">
          <label for="mv_produtor">Produtor</label>
          <?php if (is_array($contas_forn) && count($contas_forn)) { ?>
          <select id="mv_produtor" name="mv_produtor" class="form-control">
            <?php foreach ($contas_forn as $con_id => $rotulo) { ?>
            <option value="<?php echo(h($con_id)); ?>"><?php echo(h($rotulo)); ?></option>
            <?php } ?>
          </select>
          <?php } else { ?>
          <p class="form-control-static text-muted"><small>Nenhum produtor com conta aberta.</small></p>
          <?php } ?>
        </div>
      </div>

      <div class="row" style="margin-top:10px;">
        <div class="col-sm-6">
          <label for="mv_historico">Descrição <small class="text-muted">(opcional)</small></label>
          <input type="text" id="mv_historico" class="form-control" name="mv_historico" maxlength="200" value="" />
        </div>
        <div class="col-sm-6">
          <label for="mv_comprovante">Comprovante <small class="text-muted">(opcional)</small></label>
          <input type="text" id="mv_comprovante" class="form-control" name="mv_comprovante" maxlength="300" value="" placeholder="link do recibo, ou uma referência" />
        </div>
      </div>

      <div class="row" style="margin-top:12px;">
        <div class="col-sm-12 text-right">
          <button class="btn btn-success" type="submit"><i class="glyphicon glyphicon-ok glyphicon-white"></i> lançar</button>
        </div>
      </div>

    </div>
  </div>
</form>

<script type="text/javascript">
// Esconde o campo que não vale para o tipo escolhido. É CONVENIÊNCIA: quem decide
// qual contraparte conta é o servidor, que lê mv_produtor só em pagamento a produtor,
// e categoria só em despesa. Sem JavaScript a tela continua inteira e funcionando —
// aparecem os três campos e o lançamento sai igual.
(function () {
  var tipo = document.getElementById('mv_tipo');
  if (!tipo) return;

  function mostra(id, sim) {
    var el = document.getElementById(id);
    if (el) el.style.display = sim ? '' : 'none';
  }

  function ajusta() {
    var v = tipo.value;
    mostra('bloco_categoria', v === 'despesa');
    mostra('bloco_produtor',  v === 'pagamento_produtor');
    mostra('bloco_rede',      v !== 'pagamento_produtor');
  }

  tipo.onchange = ajusta;
  ajusta();
})();
</script>
<?php } ?>

<legend style="font-size:medium;">Extrato</legend>

<?php if ($nao_deu) { ?>
  <div class="alert alert-danger">
    <strong>Não foi possível carregar o extrato.</strong><br>
    Nenhum saldo pode ser mostrado agora. Tente de novo daqui a alguns minutos.
  </div>
<?php } else { ?>

<table class="table table-striped table-bordered table-condensed">
  <thead>
    <tr>
      <th>Data</th>
      <th>Lançamento</th>
      <th class="text-right">Valor</th>
      <th class="text-right">Saldo</th>
    </tr>
  </thead>
  <tbody>
  <?php
    // Mais recente primeiro na EXIBIÇÃO, como no extrato do cestante. O saldo corrente
    // de cada linha já veio somado em ordem cronológica por extrato_do_nucleo(); inverter
    // aqui não o recalcula — e é por isso que a soma acontece lá, e não na tela.
    foreach (array_reverse($extrato) as $linha)
    {
        $comp      = trim((string)$linha['comprovante']);
        $comp_link = comprovante_como_link($comp);
  ?>
    <tr>
      <td><?php echo(h(date('d/m/Y', strtotime($linha['dt'])))); ?></td>
      <td>
        <?php echo(h(isset($rotulo_tipo[$linha['tipo']]) ? $rotulo_tipo[$linha['tipo']] : $linha['tipo'])); ?>
        <?php if ($linha['categoria_rotulo'] !== '') { ?>
          <span class="label label-default"><?php echo(h($linha['categoria_rotulo'])); ?></span>
        <?php } ?>
        <?php if (trim((string)$linha['contraparte']) !== '') { ?>
          <small class="text-muted">&middot; <?php echo(h($linha['contraparte'])); ?></small>
        <?php } ?>
        <?php if (trim((string)$linha['historico']) !== '') { ?>
          <br><small><?php echo(h($linha['historico'])); ?></small>
        <?php } ?>

        <?php
          // Comprovante vira link só quando é http/https — lista de permissão, não de
          // bloqueio, para um javascript:… digitado aqui não virar clique executável.
          if ($comp_link !== '') { ?>
          <br><small><a href="<?php echo(h($comp_link)); ?>" target="_blank" rel="noopener noreferrer"><i class="glyphicon glyphicon-link"></i> comprovante</a></small>
        <?php } else if ($comp !== '') { ?>
          <br><small class="text-muted"><i class="glyphicon glyphicon-paperclip"></i> <?php echo(h($comp)); ?></small>
        <?php } ?>

        <?php if (!empty($linha['editado_em'])) { ?>
          <br><small class="text-muted"><em>descrição editada em <?php echo(h(date('d/m/Y', strtotime($linha['editado_em'])))); ?></em></small>
        <?php } ?>
      </td>
      <td class="text-right<?php echo($linha['valor'] < 0 ? ' text-danger' : ''); ?>"><?php echo(formata_moeda($linha['valor'])); ?></td>
      <td class="text-right"><?php echo(formata_moeda($linha['saldo'])); ?></td>
    </tr>
  <?php } ?>
  <?php if (!count($extrato)) { ?>
    <tr><td colspan="4">Nenhum lançamento neste caixa ainda.</td></tr>
  <?php } ?>
  </tbody>
</table>

<p class="small text-muted">
  Negativo é o que o núcleo <strong>deve</strong> à Rede; positivo é o que tem a receber.
  Entram negativo o pagamento de cestante e a <strong>outra receita</strong> — nos dois o
  dinheiro chega ao caixa pertencendo à Rede, e o núcleo passa a dever esse tanto. Despesa,
  repasse e pagamento a produtor empurram o saldo de volta para zero.
</p>

<?php } ?>

<?php footer(); ?>
