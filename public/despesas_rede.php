<?php
  require  "common.inc.php";
  require_once(__DIR__ . "/financeiro.inc.php");

  verifica_seguranca();

  // Dinheiro da Rede, e decisão sobre quanto cada núcleo carrega: é ato de quem cuida
  // das finanças, não de quem lança no caixa do núcleo. Por isso RESP_FINANÇAS ou ADM,
  // e não pode_lancar_no_caixa().
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

  function campo($nome, $i = null)
  {
      $v = request_get($nome, "");
      if ($i !== null) $v = (is_array($v) && isset($v[$i])) ? $v[$i] : "";
      return (is_string($v) || is_int($v) || is_float($v)) ? trim((string)$v) : "";
  }

  // valor digitado como 1.234,56 vira float
  function valor_digitado($txt)
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

  $volta = "despesas_rede.php?ano=" . urlencode($ano) . "&mes=" . urlencode($mes);

  if (request_get("action", "") == ACAO_SALVAR)
  {
      $o_que = campo("o_que");

      if ($o_que === "nova")
      {
          // SÓ DESPESA A RATEAR. O pagamento a produtor esteve aqui e saiu para a tela de
          // Produtores: lá a pessoa já vê quanto falta para cada um, que é a informação
          // sem a qual não se decide pagar. Aqui ele obrigava a chegar com nome e valor
          // trazidos de outra tela, e ainda precisava de um parágrafo explicando que não
          // era despesa e não se rateava — item que precisa disso está na tela errada.
          $valor    = valor_digitado(campo("valor"));
          $data     = date_create_from_format('d/m/Y', campo("dt"));
          $sugerido = $data ? sugere_rateio($valor, campo("regra")) : null;

          $tra = ($sugerido === null) ? null
               : lanca_despesa_da_rede(date_format($data, 'Y-m-d'), campo("categoria"), $valor,
                     campo("origem"), campo("historico"), $sugerido);

          adiciona_mensagem_status($tra ? MSG_TIPO_SUCESSO : MSG_TIPO_ERRO,
              $tra ? "Despesa lançada e rateada. Confira o rateio antes de fechar o mês."
                   : "Não foi possível lançar. Confira a data, o valor, a área, a conta de origem e a regra de rateio.");
      }
      else if ($o_que === "rateio")
      {
          // Substitui a atribuição inteira: os campos vêm todos, e o que veio vazio
          // significa "este núcleo não carrega nada desta despesa".
          $tra_id = campo("tra_id");
          $nucs   = request_get("rat_nuc", array());
          $vals   = request_get("rat_valor", array());
          $novo   = array();

          if (is_array($nucs))
              foreach ($nucs as $i => $n)
                  $novo[(int)$n] = valor_digitado(is_array($vals) && isset($vals[$i]) ? $vals[$i] : "");

          $ok = redefine_rateio($tra_id, $novo);

          adiciona_mensagem_status($ok ? MSG_TIPO_SUCESSO : MSG_TIPO_ERRO,
              $ok ? "Rateio atualizado."
                  : "Não foi possível atualizar o rateio. A soma não pode passar do valor da despesa.");
      }
      else if ($o_que === "corrigir")
      {
          // CORREÇÃO NO LUGAR, e só dentro da janela. Quem decide se pode é
          // edita_despesa_da_rede(), que confere a data GRAVADA — a tela esconde o botão
          // fora da janela, mas o POST chega igual.
          $valor    = valor_digitado(campo("valor"));
          $data     = date_create_from_format('d/m/Y', campo("dt"));
          $sugerido = $data ? sugere_rateio($valor, campo("regra")) : null;

          $ok = ($sugerido === null) ? false
              : edita_despesa_da_rede(campo("tra_id"), date_format($data, 'Y-m-d'),
                    campo("categoria"), $valor, campo("origem"), campo("historico"), $sugerido);

          adiciona_mensagem_status($ok ? MSG_TIPO_SUCESSO : MSG_TIPO_ERRO,
              $ok ? "Despesa corrigida, e o rateio refeito com o valor novo."
                  : "Não foi possível corrigir. Despesa de mais de um mês atrás não muda"
                  . " mais — para corrigi-la, lance um ajuste.");
      }
      else if ($o_que === "repetir")
      {
          // O mês anterior inteiro, com o que a pessoa ajustou. São as mesmas catorze
          // linhas todo mês; digitá-las do zero é o tipo de tarefa que se abandona.
          $cats  = request_get("r_categoria", array());
          $criadas = 0; $recusadas = 0;

          if (is_array($cats))
              foreach ($cats as $i => $c)
              {
                  if (campo("r_marcada", $i) !== "1") continue;

                  $valor    = valor_digitado(campo("r_valor", $i));
                  $regra    = campo("r_regra", $i);
                  $sugerido = sugere_rateio($valor, $regra);

                  // A CONTA É POR LINHA. Era uma só para todas, e a pessoa que tinha uma
                  // despesa saindo de outra conta precisava lançá-la à parte — na
                  // prática, ou lançava tudo errado ou refazia uma no formulário de
                  // baixo. Cada linha vem preenchida com a conta do mês anterior, que é
                  // quase sempre a certa.
                  $tra = ($sugerido === null) ? null
                       : lanca_despesa_da_rede(sprintf('%04d-%02d-01', $ano, $mes), $c, $valor,
                             campo("r_origem", $i), campo("r_historico", $i), $sugerido);

                  if ($tra) $criadas++; else $recusadas++;
              }

          // As duas contas saem juntas: um "12 lançadas" que engole as outras duas é a
          // mentira que este módulo existe para não contar.
          adiciona_mensagem_status($recusadas ? MSG_TIPO_AVISO : MSG_TIPO_SUCESSO,
              "$criadas despesa(s) lançada(s)."
              . ($recusadas ? " $recusadas linha(s) NÃO foram lançadas — confira valor e regra." : ""));
      }

      redireciona($volta);
      exit();
  }

  top();
  abas_financeiras('rede', 'despesas');

  $despesas  = despesas_da_rede($de, $ate);
  $cats      = categorias_de_despesa_da_rede();
  $quotas    = quotas_de_rateio();
  $origens   = contas_de_destino_do_tipo('rede');
  // Produtor só entra na lista se tiver conta: sem conta não há perna onde lançar. As
  // contas nascem em Contas, no botão "criar as contas que faltam".

  $nomes_nuc = array();
  $res_n = executa_sql("SELECT nuc_id, nuc_nome_curto FROM nucleos");
  while ($res_n && $rn = mysqli_fetch_array($res_n, MYSQLI_ASSOC))
      $nomes_nuc[(int)$rn['nuc_id']] = (string)$rn['nuc_nome_curto'];

  $editar = request_get("editar", "");
  if (!is_string($editar) && !is_int($editar)) $editar = "";
  if (!ctype_digit((string)$editar)) $editar = "";

  $repetir = (request_get("repetir", "") === "1");

  // qual despesa está aberta para correção (diferente de `editar`, que abre só o rateio)
  $corrigir = request_get("corrigir", "");
  if (!is_string($corrigir) && !is_int($corrigir)) $corrigir = "";
  if (!ctype_digit((string)$corrigir)) $corrigir = "";

  $nome_mes = array(1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',
                    7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro');

  $regras = array('igual' => 'igual entre os núcleos', 'quota' => 'por quota de entrega');

  // Exemplo por área, tirado da planilha da Rede. Placeholder genérico numa tela de
  // catorze linhas repetidas todo mês não ajuda ninguém; o que ajuda é lembrar o nome
  // que aquela área costuma ter, porque é o mesmo nome do mês passado.
  $exemplos = array(
      'mutirao'   => 'Apoio CAC, Apoio mutirão logística, Auxílio mutirão',
      'logistica' => 'Apoio Logística, Retorno das embalagens, Despesa Logística',
      'pedidos'   => 'Resp. pedidos',
      'financas'  => 'Resp. financeiro',
      'sistemas'  => 'Resp. sistemas, Hospedagem Site e Sistema, Registro do domínio',
      'admin'     => 'Despesas bancárias',
  );

  // O ", ..." mora AQUI, e não dentro de cada string: é convenção de exibição — "há
  // outros além destes" —, e repetida seis vezes a sétima área nasceria sem ela.
  foreach ($exemplos as $chave_ex => $texto_ex) $exemplos[$chave_ex] = $texto_ex . ', ...';


  escreve_mensagem_status();
?>

<legend>Despesas da Rede &middot; <?php echo(h($nome_mes[$mes] . ' de ' . $ano)); ?></legend>

<form class="form-inline hidden-print" method="get" action="despesas_rede.php">
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

<?php if (!is_array($despesas) || !is_array($quotas) || !is_array($origens)) { ?>
  <div class="alert alert-danger">
    <strong>Não foi possível carregar as despesas da Rede.</strong><br>
    Tente de novo daqui a alguns minutos.
  </div>
<?php } else if (!count($origens)) { ?>
  <div class="alert alert-warning">
    <strong>Nenhuma conta da Rede cadastrada.</strong><br>
    Toda despesa sai de uma conta, e sem nenhuma cadastrada não há de onde lançar.
    Cadastre em <a href="contas.php">Contas</a>.
  </div>
<?php } else { ?>

<?php
  $total_mes = 0.0; $total_rateado = 0.0;
  foreach ($despesas as $d) { $total_mes += $d['valor']; $total_rateado += $d['rateado']; }
?>

<table class="table table-bordered table-condensed table-striped">
  <thead>
    <tr>
      <th>Data</th><th>Área</th><th>Descrição</th>
      <th class="text-right">Valor</th>
      <th class="text-right">Rateado</th>
      <th class="text-right">Fica com a Rede</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($despesas as $d) {
        $em_edicao   = ((string)$d['tra_id'] === (string)$editar);
        $em_correcao = ((string)$d['tra_id'] === (string)$corrigir);
        // A JANELA, decidida pela mesma função que a gravação confere. Despesa recente
        // ainda está sendo trabalhada e se conserta digitando de novo; velha já entrou em
        // resultado que o núcleo leu, e ali o certo é lançar um ajuste, que aparece.
        $pode_corrigir = despesa_da_rede_editavel($d['dt']);
        $rat_atual = ($em_edicao || $em_correcao) ? (array)rateio_da_despesa($d['tra_id']) : array(); ?>
    <tr<?php echo(($em_edicao || $em_correcao) ? ' class="info"' : ''); ?>>
      <td><?php echo(h(date('d/m/Y', strtotime($d['dt'])))); ?></td>
      <td><span class="label label-default"><?php echo(h($d['categoria_rotulo'])); ?></span></td>
      <td><?php echo(h($d['historico'])); ?></td>
      <td class="text-right"><?php echo(h(formata_moeda($d['valor']))); ?></td>
      <td class="text-right"><?php echo(h(formata_moeda($d['rateado']))); ?></td>
      <td class="text-right<?php echo($d['sobra'] > 0.005 ? ' text-danger' : ''); ?>"><?php echo(h(formata_moeda($d['sobra']))); ?></td>
      <td class="text-right text-nowrap">
        <?php if (!$em_edicao && !$em_correcao) { ?>
        <a class="btn btn-default btn-xs" href="<?php echo(h($volta)); ?>&amp;editar=<?php echo(h($d['tra_id'])); ?>#d<?php echo(h($d['tra_id'])); ?>"
           title="conferir e ajustar para quem o custo foi apontado"><i class="glyphicon glyphicon-equalizer"></i> rateio</a>
        <?php if ($pode_corrigir) { ?>
        <a class="btn btn-default btn-xs" href="<?php echo(h($volta)); ?>&amp;corrigir=<?php echo(h($d['tra_id'])); ?>#d<?php echo(h($d['tra_id'])); ?>"
           title="corrigir valor, data, área ou descrição desta despesa"><i class="glyphicon glyphicon-pencil"></i> corrigir</a>
        <?php } else { ?>
        <span class="text-muted small" title="despesa de mais de um mês atrás: para corrigir, lance um ajuste">congelada</span>
        <?php } ?>
        <?php } ?>
      </td>
    </tr>

    <?php if ($em_correcao) { ?>
    <tr class="info" id="d<?php echo(h($d['tra_id'])); ?>">
      <td colspan="7">
        <form method="post" action="despesas_rede.php">
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />
          <input type="hidden" name="o_que" value="corrigir" />
          <input type="hidden" name="tra_id" value="<?php echo(h($d['tra_id'])); ?>" />
          <input type="hidden" name="ano" value="<?php echo(h($ano)); ?>" />
          <input type="hidden" name="mes" value="<?php echo(h($mes)); ?>" />

          <p class="small text-muted" style="margin-bottom:8px;">
            Corrigindo <strong><?php echo(h($d['historico'] !== '' ? $d['historico'] : 'despesa sem descrição')); ?></strong>.
            O rateio é <strong>refeito</strong> com o valor novo, pela regra escolhida — e
            depois pode ser conferido no botão de rateio, como sempre.
          </p>

          <div class="row">
            <div class="col-sm-3">
              <label for="c_categoria">Área</label>
              <select id="c_categoria" name="categoria" class="form-control" required="required">
                <option value="">escolha a área</option>
                <?php foreach ($cats as $ck => $cr) { ?>
                <option value="<?php echo(h($ck)); ?>"<?php echo($ck === $d['categoria'] ? ' selected' : ''); ?>><?php echo(h($cr)); ?></option>
                <?php } ?>
              </select>
            </div>
            <div class="col-sm-3">
              <label for="c_dt">Data</label>
              <input type="text" id="c_dt" name="dt" class="form-control data" required="required"
                     value="<?php echo(h(date('d/m/Y', strtotime($d['dt'])))); ?>" />
            </div>
            <div class="col-sm-3">
              <label for="c_valor">Valor</label>
              <input type="text" id="c_valor" name="valor" class="form-control numero" required="required"
                     value="<?php echo(h(formata_moeda($d['valor']))); ?>" />
            </div>
            <div class="col-sm-3">
              <label for="c_regra">Rateio</label>
              <?php
                // Herdada do rateio atual, pelo mesmo caminho de "repetir": corrigir o
                // valor de uma despesa não costuma vir junto com mudar a regra, e fazer a
                // pessoa reescolher convida a trocar sem querer.
                $regra_atual = regra_do_rateio($d['valor'], $rat_atual);
              ?>
              <select id="c_regra" name="regra" class="form-control" required="required">
                <?php if ($regra_atual === '') { ?>
                <option value="">escolha</option>
                <?php } ?>
                <?php foreach ($regras as $rk => $rr) { ?>
                <option value="<?php echo(h($rk)); ?>"<?php echo($rk === $regra_atual ? ' selected' : ''); ?>><?php echo(h($rr)); ?></option>
                <?php } ?>
              </select>
              <?php if ($regra_atual === '') { ?>
              <span class="help-block small">
                O rateio atual não veio de nenhuma das regras — foi ajustado à mão. Escolher
                uma aqui vai <strong>refazê-lo</strong>.
              </span>
              <?php } ?>
            </div>
          </div>

          <div class="row" style="margin-top:10px;">
            <div class="col-sm-6">
              <label for="c_historico">Descrição</label>
              <input type="text" id="c_historico" name="historico" class="form-control" maxlength="200"
                     value="<?php echo(h($d['historico'])); ?>" />
            </div>
            <div class="col-sm-6">
              <label for="c_origem">Sai da conta</label>
              <select id="c_origem" name="origem" class="form-control">
                <?php foreach ($origens as $cid => $rot) { ?>
                <option value="<?php echo(h($cid)); ?>"><?php echo(h($rot)); ?></option>
                <?php } ?>
              </select>
            </div>
          </div>

          <div class="text-right" style="margin-top:12px;">
            <a class="btn btn-link" href="<?php echo(h($volta)); ?>">cancelar</a>
            &nbsp;<button class="btn btn-success" type="submit">
              <i class="glyphicon glyphicon-ok glyphicon-white"></i> corrigir
            </button>
          </div>
        </form>
      </td>
    </tr>
    <?php } ?>

    <?php if ($em_edicao) { ?>
    <tr class="info" id="d<?php echo(h($d['tra_id'])); ?>">
      <td colspan="7">
        <form method="post" action="despesas_rede.php">
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />
          <input type="hidden" name="o_que" value="rateio" />
          <input type="hidden" name="ano" value="<?php echo(h($ano)); ?>" />
          <input type="hidden" name="mes" value="<?php echo(h($mes)); ?>" />
          <input type="hidden" name="tra_id" value="<?php echo(h($d['tra_id'])); ?>" />

          <p class="small text-muted">
            Quanto desta despesa cada núcleo carrega. Campo em branco quer dizer que aquele
            núcleo não carrega nada. A soma não pode passar de
            <strong><?php echo(h(formata_moeda($d['valor']))); ?></strong>; o que sobrar fica com a Rede.
          </p>

          <div class="row">
            <?php foreach ($quotas as $nuc => $q) { ?>
            <div class="col-sm-3" style="margin-bottom:8px;">
              <label class="small" for="rn<?php echo(h($nuc)); ?>">
                <?php echo(h(isset($nomes_nuc[$nuc]) ? $nomes_nuc[$nuc] : '#' . $nuc)); ?>
                <span class="text-muted">(<?php echo(h(rtrim(rtrim(number_format($q, 1, ',', ''), '0'), ','))); ?> quota)</span>
              </label>
              <input type="hidden" name="rat_nuc[]" value="<?php echo(h($nuc)); ?>" />
              <input type="text" id="rn<?php echo(h($nuc)); ?>" name="rat_valor[]" class="form-control input-sm numero"
                     value="<?php echo(h(isset($rat_atual[$nuc]) ? formata_moeda($rat_atual[$nuc]) : '')); ?>" />
            </div>
            <?php } ?>
          </div>

          <div class="text-right" style="margin-top:8px;">
            <a class="btn btn-link" href="<?php echo(h($volta)); ?>">cancelar</a>
            &nbsp;<button class="btn btn-success" type="submit"><i class="glyphicon glyphicon-ok glyphicon-white"></i> salvar rateio</button>
          </div>
        </form>
      </td>
    </tr>
    <?php } ?>
  <?php } ?>

  <?php if (!count($despesas)) { ?>
    <tr><td colspan="7">Nenhuma despesa da Rede lançada neste mês.</td></tr>
  <?php } else { ?>
    <tr>
      <th colspan="3" class="text-right">total do mês</th>
      <th class="text-right"><?php echo(h(formata_moeda($total_mes))); ?></th>
      <th class="text-right"><?php echo(h(formata_moeda($total_rateado))); ?></th>
      <th class="text-right"><?php echo(h(formata_moeda($total_mes - $total_rateado))); ?></th>
      <th></th>
    </tr>
  <?php } ?>
  </tbody>
</table>

<p>
  <a class="btn btn-default btn-sm" href="<?php echo(h($volta)); ?>&amp;repetir=1">
    <i class="glyphicon glyphicon-repeat"></i> repetir as despesas do mês anterior
  </a>
</p>

<?php
  if ($repetir)
  {
      $mes_ant = ($mes == 1) ? 12 : $mes - 1;
      $ano_ant = ($mes == 1) ? $ano - 1 : $ano;
      $de_ant  = sprintf('%04d-%02d-01 00:00:00', $ano_ant, $mes_ant);
      $anteriores = despesas_da_rede($de_ant, $de);
?>
<div class="panel panel-default">
  <div class="panel-heading">Repetir <?php echo(h($nome_mes[$mes_ant])); ?> em <?php echo(h($nome_mes[$mes])); ?></div>
  <div class="panel-body">
  <?php if (!is_array($anteriores) || !count($anteriores)) { ?>
    <p class="text-muted">Não há despesas em <?php echo(h($nome_mes[$mes_ant] . ' de ' . $ano_ant)); ?> para repetir.</p>
  <?php } else { ?>
    <form method="post" action="despesas_rede.php">
      <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />
      <input type="hidden" name="o_que" value="repetir" />
      <input type="hidden" name="ano" value="<?php echo(h($ano)); ?>" />
      <input type="hidden" name="mes" value="<?php echo(h($mes)); ?>" />

      <table class="table table-condensed">
        <thead><tr><th></th><th>Área</th><th>Descrição</th><th>Valor</th><th>Sai da conta</th><th>Rateio</th></tr></thead>
        <tbody>
        <?php foreach ($anteriores as $i => $a) { ?>
          <tr>
            <td><input type="checkbox" name="r_marcada[<?php echo($i); ?>]" value="1" checked /></td>
            <td>
              <select name="r_categoria[<?php echo($i); ?>]" class="form-control input-sm">
                <?php foreach ($cats as $ck => $cr) { ?>
                <option value="<?php echo(h($ck)); ?>"<?php echo($ck === $a['categoria'] ? ' selected' : ''); ?>><?php echo(h($cr)); ?></option>
                <?php } ?>
              </select>
            </td>
            <td><input type="text" name="r_historico[<?php echo($i); ?>]" class="form-control input-sm" maxlength="200" value="<?php echo(h($a['historico'])); ?>" /></td>
            <td><input type="text" name="r_valor[<?php echo($i); ?>]" class="form-control input-sm numero" value="<?php echo(h(formata_moeda($a['valor']))); ?>" /></td>
            <td>
              <?php
                // pré-selecionada com a do mês anterior, que é quase sempre a mesma; se a
                // conta de então tiver sido arquivada, cai na primeira da lista
              ?>
              <select name="r_origem[<?php echo($i); ?>]" class="form-control input-sm">
                <?php foreach ($origens as $cid => $rot) { ?>
                <option value="<?php echo(h($cid)); ?>"<?php echo(((int)$cid === (int)$a['origem']) ? ' selected' : ''); ?>><?php echo(h($rot)); ?></option>
                <?php } ?>
              </select>
            </td>
            <td>
              <?php
                // A REGRA VEM HERDADA, descoberta a partir do rateio gravado — ela não
                // fica guardada na despesa, só o resultado dela, e sugere_rateio() é
                // determinística o bastante para o caminho de volta.
                //
                // Quando não dá para saber — rateio ajustado à mão, ou quotas que mudaram
                // desde então — a linha vem SEM escolha, e o `required` obriga a decidir.
                // É o mesmo princípio da área: campo que parece respondido sem ninguém
                // ter respondido é pior do que campo em branco.
                $regra_herdada = regra_do_rateio($a['valor'], (array)rateio_da_despesa($a['tra_id']));
              ?>
              <select name="r_regra[<?php echo($i); ?>]" class="form-control input-sm" required="required">
                <?php if ($regra_herdada === '') { ?>
                <option value="">escolha</option>
                <?php } ?>
                <?php foreach ($regras as $rk => $rr) { ?>
                <option value="<?php echo(h($rk)); ?>"<?php echo($rk === $regra_herdada ? ' selected' : ''); ?>><?php echo(h($rr)); ?></option>
                <?php } ?>
              </select>
            </td>
          </tr>
        <?php } ?>
        </tbody>
      </table>

      <p class="small text-muted">
        A regra de rateio vem <strong>descoberta</strong> a partir do rateio do mês
        anterior: ela não fica gravada na despesa, só o resultado dela, e daí se deduz qual
        regra o produziu. Confira mesmo assim — na planilha da Rede a mesma área aparece
        nas duas regras, então a área não decide sozinha.
        <br>Linha cujo rateio foi ajustado à mão vem <strong>sem regra escolhida</strong>:
        ali ela se perdeu de verdade, e escolher uma por você seria afirmar o que ninguém
        decidiu.
      </p>

      <div class="text-right">
        <a class="btn btn-link" href="<?php echo(h($volta)); ?>">cancelar</a>
        &nbsp;<button class="btn btn-success" type="submit"><i class="glyphicon glyphicon-ok glyphicon-white"></i> lançar as marcadas</button>
      </div>
    </form>
  <?php } ?>
  </div>
</div>
<?php } ?>

<div class="panel panel-default">
  <div class="panel-heading">Nova despesa da Rede</div>
  <div class="panel-body">
    <form method="post" action="despesas_rede.php">
      <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />
      <input type="hidden" name="o_que" value="nova" />
      <input type="hidden" name="ano" value="<?php echo(h($ano)); ?>" />
      <input type="hidden" name="mes" value="<?php echo(h($mes)); ?>" />

      <div class="row">
        <div class="col-sm-3">
          <label for="categoria">Área</label>
          <?php
            // SEM ÁREA PRÉ-ESCOLHIDA. O primeiro item da lista vinha selecionado só por
            // ser o primeiro, e quem não reparasse lançava tudo naquela área — o campo
            // parecia respondido sem ninguém ter respondido. Agora a escolha é explícita,
            // e o `required` recusa o envio sem ela.
          ?>
          <select id="categoria" name="categoria" class="form-control" required="required">
            <option value="">escolha a área</option>
            <?php foreach ($cats as $ck => $cr) { ?>
            <option value="<?php echo(h($ck)); ?>"><?php echo(h($cr)); ?></option>
            <?php } ?>
          </select>
        </div>
        <div class="col-sm-3">
          <label for="dt">Data</label>
          <input type="text" id="dt" name="dt" class="form-control data" required="required"
                 value="<?php echo(h(sprintf('01/%02d/%04d', $mes, $ano))); ?>" />
        </div>
        <div class="col-sm-3">
          <label for="valor">Valor</label>
          <input type="text" id="valor" name="valor" class="form-control numero" required="required" value="" />
        </div>
        <div class="col-sm-3">
          <label for="regra">Rateio</label>
          <select id="regra" name="regra" class="form-control">
            <?php foreach ($regras as $rk => $rr) { ?>
            <option value="<?php echo(h($rk)); ?>"><?php echo(h($rr)); ?></option>
            <?php } ?>
          </select>
        </div>
      </div>

      <div class="row" style="margin-top:10px;">
        <div class="col-sm-6">
          <label for="historico">Descrição</label>
          <input type="text" id="historico" name="historico" class="form-control" maxlength="200"
                 placeholder="o que foi pago — escolha a área para ver exemplos" />
        </div>
        <div class="col-sm-6">
          <label for="origem">Sai da conta</label>
          <select id="origem" name="origem" class="form-control">
            <?php foreach ($origens as $cid => $rot) { ?>
            <option value="<?php echo(h($cid)); ?>"><?php echo(h($rot)); ?></option>
            <?php } ?>
          </select>
        </div>
      </div>

      <div class="text-right" style="margin-top:12px;">
        <button class="btn btn-success" type="submit"><i class="glyphicon glyphicon-ok glyphicon-white"></i> lançar</button>
      </div>
    </form>
  </div>
</div>

<script type="text/javascript">
// Troca o exemplo do campo Descrição conforme a área escolhida. É CONVENIÊNCIA: o
// placeholder inicial já vem certo do servidor, então sem JavaScript a tela continua
// inteira — só deixa de acompanhar a troca de área.
(function () {
  var exemplos = <?php echo(json_encode($exemplos)); ?>;
  var area = document.getElementById('categoria');
  var desc = document.getElementById('historico');
  if (!area || !desc) return;

  // Sem área escolhida o campo volta a pedir a escolha, em vez de ficar mudo: o
  // placeholder é a única pista de que a área muda o que se espera aqui.
  var sem_area = desc.placeholder;
  area.onchange = function () { desc.placeholder = exemplos[area.value] || sem_area; };
})();

</script>

<p class="small text-muted">
  O rateio é <strong>sugerido</strong> ao lançar e fica sempre conferível no botão da linha —
  nunca é a última palavra. Ele <strong>não</strong> vira dívida do núcleo: é custo apontado,
  para o resultado dele poder dizer se se paga. Nenhum saldo de caixa se mexe.
  A sobra de centavos da divisão fica com a Rede.
  <br>A <strong>data da despesa</strong> é o que decide em que mês o custo aparece para o
  núcleo — lançar em setembro uma despesa datada de agosto a faz cair no resultado de
  agosto, que o núcleo talvez já tenha conferido.
</p>

<?php } ?>

<?php footer(); ?>
