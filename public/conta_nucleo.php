<?php
  require  "common.inc.php";
  // require_once e __DIR__ pelo mesmo motivo do conta_cestante.php: a página que
  // alcançar o menu antes daqui morreria por redeclaração de função.
  require_once(__DIR__ . "/financeiro.inc.php");

  verifica_seguranca();

  // O núcleo em foco sai de nucleo_do_caixa_em_foco(), e não de um bloco escrito aqui:
  // a spec exige o escopo IMPOSTO e não sugerido, e esta tela e a do fluxo fariam duas
  // cópias da mesma regra. A função devolve "" quando não há núcleo alcançável.
  //
  // A trava do módulo tampouco passa por verifica_seguranca(): aquela função valida
  // qualquer chamada de PAP_ADM sem olhar o parâmetro (common.inc.php:103-110). Quem
  // recusa é pode_lancar_no_caixa(), lá dentro, que começa por pode_ver_financeiro().
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

  // Campo de texto vindo do POST. request_get devolve ARRAY quando alguém manda
  // `mv_valor[]`, e converter array em string emite warning na tela; aqui vira "".
  function campo_texto($nome)
  {
      $v = request_get($nome, "");
      return (is_string($v) || is_int($v) || is_float($v)) ? trim((string)$v) : "";
  }

  if (request_get("action", "") == ACAO_SALVAR)
  {
      // O nuc_id do POST NÃO é consultado aqui: o núcleo é o que já foi imposto e
      // autorizado lá em cima. O campo escondido do formulário serve só para o
      // responsável de finanças não perder o núcleo em foco ao gravar.
      $tipo = request_get("mv_tipo", "");
      if (!is_string($tipo)) $tipo = "";

      // A conta só é lida nos dois lançamentos que TÊM conta do outro lado. Em despesa
      // e outra receita quem recebe é o motorista ou o doador, que não têm cadastro —
      // a função usa a conta de contrapartida e ignora o que vier aqui.
      $contraparte = ($tipo === 'pagamento_produtor') ? request_get("mv_produtor", "")
                   : (($tipo === 'repasse')           ? request_get("mv_rede", "") : null);

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
                  'favorecido'  => request_get("mv_favorecido", ""),
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

      // O POST-redirect-GET existe para um F5 não relançar — numa tela de dinheiro
      // isso não é incômodo, é dano. Mas RECUSA não gravou nada, então não há reenvio
      // a temer, e devolver o formulário em branco cobra o preço do redirect sem a
      // contrapartida: quem esqueceu o valor perdia junto a data, a categoria, a conta
      // e a descrição que já tinha digitado.
      //
      // O rascunho atravessa o redirect pela sessão, do mesmo jeito que a mensagem de
      // status (common.inc.php:401), e é lido UMA vez só — senão reaparece na visita
      // seguinte, quando já não é rascunho de ninguém.
      if ($tra) unset($_SESSION['caixa.rascunho']);
      else $_SESSION['caixa.rascunho'] = array(
          'nuc'         => $nuc_id,
          'tipo'        => $tipo,
          'dt'          => campo_texto("mv_dt"),
          'valor'       => campo_texto("mv_valor"),
          'categoria'   => campo_texto("mv_categoria"),
          'rede'        => campo_texto("mv_rede"),
          'produtor'    => campo_texto("mv_produtor"),
          'favorecido'  => campo_texto("mv_favorecido"),
          'historico'   => campo_texto("mv_historico"),
          'comprovante' => campo_texto("mv_comprovante"),
      );

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

  // Rascunho de uma tentativa recusada, se houver. O nuc_id faz parte da guarda: um
  // rascunho reaparecendo no caixa de OUTRO núcleo levaria alguém a lançar ali sem
  // perceber que os campos vieram de outra tela.
  $rascunho = array();
  if (isset($_SESSION['caixa.rascunho']) && is_array($_SESSION['caixa.rascunho'])
      && isset($_SESSION['caixa.rascunho']['nuc'])
      && (int)$_SESSION['caixa.rascunho']['nuc'] === $nuc_id)
      $rascunho = $_SESSION['caixa.rascunho'];

  unset($_SESSION['caixa.rascunho']);   // lido uma vez só, como a mensagem de status

  function rasc($rascunho, $chave, $padrao = "")
  {
      return (isset($rascunho[$chave]) && $rascunho[$chave] !== "") ? $rascunho[$chave] : $padrao;
  }

  $rotulo_tipo = array(
      'despesa'            => 'despesa',
      'repasse'            => 'repasse à Rede',
      'pagamento_produtor' => 'pagamento a produtor',
      'receita'            => 'outra receita (doação, rendimento)',
  );

  // Agrupados por dinheiro entrando e saindo, que é como quem cuida do caixa pensa —
  // e não pelo efeito no saldo, que fazia "despesa" e "repasse" parecerem a mesma coisa.
  $grupos_tipo = array(
      'Saiu dinheiro do caixa' => array('despesa', 'repasse', 'pagamento_produtor'),
      'Entrou dinheiro'        => array('receita'),
  );

  escreve_mensagem_status();
?>

<legend>Caixa do núcleo <?php echo(h($nucleo['nuc_nome_curto'])); ?></legend>

<?php if ($manda_em_todos) { ?>
<form class="form-inline hidden-print" method="get" action="conta_nucleo.php" name="frm_nucleo">
  <div class="form-group">
    <label for="nuc_id">Núcleo:&nbsp;</label>
    <select id="nuc_id" name="nuc_id" class="form-control" onchange="this.form.submit();">
      <?php foreach ((array)nucleos_com_caixa() as $id_nuc => $nome_nuc) { ?>
      <option value="<?php echo(h($id_nuc)); ?>"<?php echo(((string)$id_nuc === (string)$nuc_id) ? ' selected' : ''); ?>><?php echo(h($nome_nuc)); ?></option>
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
    <span class="label label-danger" style="font-size:larger;">em caixa: R$ <?php echo(formata_moeda(-$saldo)); ?></span>
    <span class="text-muted">&nbsp;dinheiro da Rede que ainda não saiu — vai virar repasse ou despesa</span>
  <?php } else if ($saldo > 0.005) { ?>
    <span class="label label-info" style="font-size:larger;">a receber da Rede: R$ <?php echo(formata_moeda($saldo)); ?></span>
    <span class="text-muted">&nbsp;o núcleo gastou mais do que arrecadou</span>
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
            <?php foreach ($grupos_tipo as $titulo => $chaves) { ?>
            <optgroup label="<?php echo(h($titulo)); ?>">
              <?php foreach ($chaves as $chave) { ?>
              <option value="<?php echo(h($chave)); ?>"<?php echo(rasc($rascunho, 'tipo') === $chave ? ' selected' : ''); ?>><?php echo(h($rotulo_tipo[$chave])); ?></option>
              <?php } ?>
            </optgroup>
            <?php } ?>
          </select>
          <span class="help-block small">
            Despesa e repasse mexem o saldo do mesmo jeito; o que muda é para onde o dinheiro
            foi, e é isso que o relatório separa.
          </span>
        </div>
        <div class="col-sm-3">
          <label for="mv_dt">Data</label>
          <input type="text" id="mv_dt" class="form-control data" name="mv_dt" required="required"
                 value="<?php echo(h(rasc($rascunho, 'dt', $hoje))); ?>" />
        </div>
        <div class="col-sm-3">
          <label for="mv_valor">Valor</label>
          <input type="text" id="mv_valor" class="form-control numero" name="mv_valor" required="required"
                 value="<?php echo(h(rasc($rascunho, 'valor'))); ?>" autofocus />
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
            <option value="<?php echo(h($chave)); ?>"<?php echo($chave === rasc($rascunho, 'categoria', 'outros') ? ' selected' : ''); ?>><?php echo(h($rotulo)); ?></option>
            <?php } ?>
          </select>
        </div>

        <div class="col-sm-4" id="bloco_favorecido">
          <label for="mv_favorecido" id="rotulo_favorecido">Quem recebeu</label>
          <input type="text" id="mv_favorecido" name="mv_favorecido" class="form-control" maxlength="120"
                 value="<?php echo(h(rasc($rascunho, 'favorecido'))); ?>" placeholder="nome do motorista, da loja…" />
        </div>

        <div class="col-sm-4" id="bloco_rede">
          <label for="mv_rede">Conta da Rede</label>
          <select id="mv_rede" name="mv_rede" class="form-control">
            <?php foreach ($contas_rede as $con_id => $rotulo) { ?>
            <option value="<?php echo(h($con_id)); ?>"<?php echo((string)$con_id === (string)rasc($rascunho, 'rede') ? ' selected' : ''); ?>><?php echo(h($rotulo)); ?></option>
            <?php } ?>
          </select>
        </div>

        <div class="col-sm-4" id="bloco_produtor">
          <label for="mv_produtor">Produtor</label>
          <?php if (is_array($contas_forn) && count($contas_forn)) { ?>
          <select id="mv_produtor" name="mv_produtor" class="form-control">
            <?php foreach ($contas_forn as $con_id => $rotulo) { ?>
            <option value="<?php echo(h($con_id)); ?>"<?php echo((string)$con_id === (string)rasc($rascunho, 'produtor') ? ' selected' : ''); ?>><?php echo(h($rotulo)); ?></option>
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
          <input type="text" id="mv_historico" class="form-control" name="mv_historico" maxlength="200" value="<?php echo(h(rasc($rascunho, 'historico'))); ?>" />
        </div>
        <div class="col-sm-6">
          <label for="mv_comprovante">Comprovante <small class="text-muted">(opcional)</small></label>
          <input type="text" id="mv_comprovante" class="form-control" name="mv_comprovante" maxlength="300" value="<?php echo(h(rasc($rascunho, 'comprovante'))); ?>" placeholder="link do recibo, ou uma referência" />
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
// Esconde o campo que não vale para o tipo escolhido. É CONVENIÊNCIA: quem decide o que
// conta é o servidor — ele lê mv_rede só em repasse, mv_produtor só em pagamento a
// produtor, categoria e favorecido só onde cabem, e IGNORA o resto. Sem JavaScript a
// tela continua inteira: aparecem todos os campos e o lançamento sai igual.
(function () {
  var tipo = document.getElementById('mv_tipo');
  if (!tipo) return;

  function mostra(id, sim) {
    var el = document.getElementById(id);
    if (el) el.style.display = sim ? '' : 'none';
  }

  var rotulo = document.getElementById('rotulo_favorecido');

  function ajusta() {
    var v = tipo.value;
    // Cada campo aparece só onde significa alguma coisa. Conta da Rede em despesa era
    // uma escolha que não mudava nada — o dinheiro foi para o motorista, não para lá.
    mostra('bloco_categoria',   v === 'despesa');
    mostra('bloco_favorecido',  v === 'despesa' || v === 'receita');
    mostra('bloco_rede',        v === 'repasse');
    mostra('bloco_produtor',    v === 'pagamento_produtor');

    if (rotulo) rotulo.innerHTML = (v === 'receita') ? 'De quem veio' : 'Quem recebeu';
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

<p class="small text-muted">
  O caixa mostra quanto do dinheiro <strong>da Rede</strong> ainda está com o núcleo — não
  quanto o núcleo tem de seu.
  Os pagamentos que os cestantes fizeram no caixa aparecem aqui sozinhos: eles se lançam em
  <strong>Pagamentos</strong>, não nesta tela. Junto com a outra receita, eles
  <strong>põem</strong> dinheiro no caixa, e por isso aparecem com valor negativo — é o que
  ainda falta sair. Despesa, repasse e pagamento a produtor <strong>tiram</strong>, e
  aparecem positivos.
  Quando tudo que entrou já saiu, o saldo fica zero.
</p>

<table class="table table-striped table-bordered table-condensed extrato-caixa">
  <thead>
    <tr>
      <th>Data</th>
      <th>Lançamento</th>
      <th class="text-right">Valor<?php adiciona_popover_descricao("Valor",
        "Negativo = <b>entrou</b> dinheiro no caixa (pagamento de cestante, outra receita).<br>Positivo = <b>saiu</b> (despesa, repasse, pagamento a produtor)."); ?></th>
      <th class="text-right">Saldo<?php adiciona_popover_descricao("Saldo",
        "Quanto do dinheiro <b>da Rede</b> ainda está com o núcleo.<br>Negativo: falta sair. Positivo: o núcleo gastou mais do que arrecadou. Zero: tudo acertado."); ?></th>
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
        <?php
          // Em despesa e outra receita a contraparte é a conta de encanamento, cujo nome
          // não diz nada a ninguém: ali o que interessa é QUEM recebeu.
          if ($linha['favorecido'] !== '') { ?>
          <small class="text-muted">&middot; <?php echo(h($linha['favorecido'])); ?></small>
        <?php } else if (trim((string)$linha['contraparte']) !== ''
                         && $linha['tipo'] !== 'despesa' && $linha['tipo'] !== 'receita') { ?>
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

<?php } ?>

<?php footer(); ?>
