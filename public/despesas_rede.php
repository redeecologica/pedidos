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
          $regra   = campo("regra");
          $valor   = valor_digitado(campo("valor"));
          $data    = date_create_from_format('d/m/Y', campo("dt"));
          $sugerido = $data ? sugere_rateio($valor, $regra) : null;

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

                  $tra = ($sugerido === null) ? null
                       : lanca_despesa_da_rede(sprintf('%04d-%02d-01', $ano, $mes), $c, $valor,
                             campo("r_origem"), campo("r_historico", $i), $sugerido);

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

  $despesas = despesas_da_rede($de, $ate);
  $cats     = categorias_de_despesa_da_rede();
  $quotas   = quotas_de_rateio();
  $origens  = contas_de_destino_do_tipo('rede');

  $nomes_nuc = array();
  $res_n = executa_sql("SELECT nuc_id, nuc_nome_curto FROM nucleos");
  while ($res_n && $rn = mysqli_fetch_array($res_n, MYSQLI_ASSOC))
      $nomes_nuc[(int)$rn['nuc_id']] = (string)$rn['nuc_nome_curto'];

  $editar = request_get("editar", "");
  if (!is_string($editar) && !is_int($editar)) $editar = "";
  if (!ctype_digit((string)$editar)) $editar = "";

  $repetir = (request_get("repetir", "") === "1");

  $nome_mes = array(1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',
                    7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro');

  $regras = array('igual' => 'igual entre os núcleos', 'quota' => 'por quota de entrega');

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
        $em_edicao = ((string)$d['tra_id'] === (string)$editar);
        $rat_atual = $em_edicao ? (array)rateio_da_despesa($d['tra_id']) : array(); ?>
    <tr<?php echo($em_edicao ? ' class="info"' : ''); ?>>
      <td><?php echo(h(date('d/m/Y', strtotime($d['dt'])))); ?></td>
      <td><span class="label label-default"><?php echo(h($d['categoria_rotulo'])); ?></span></td>
      <td><?php echo(h($d['historico'])); ?></td>
      <td class="text-right"><?php echo(h(formata_moeda($d['valor']))); ?></td>
      <td class="text-right"><?php echo(h(formata_moeda($d['rateado']))); ?></td>
      <td class="text-right<?php echo($d['sobra'] > 0.005 ? ' text-danger' : ''); ?>"><?php echo(h(formata_moeda($d['sobra']))); ?></td>
      <td class="text-right">
        <?php if (!$em_edicao) { ?>
        <a class="btn btn-default btn-xs" href="<?php echo(h($volta)); ?>&amp;editar=<?php echo(h($d['tra_id'])); ?>#d<?php echo(h($d['tra_id'])); ?>"
           title="conferir e ajustar o rateio"><i class="glyphicon glyphicon-pencil"></i> rateio</a>
        <?php } ?>
      </td>
    </tr>

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

      <div class="form-group">
        <label for="r_origem">Sai da conta</label>
        <select id="r_origem" name="r_origem" class="form-control">
          <?php foreach ($origens as $cid => $rot) { ?>
          <option value="<?php echo(h($cid)); ?>"><?php echo(h($rot)); ?></option>
          <?php } ?>
        </select>
      </div>

      <table class="table table-condensed">
        <thead><tr><th></th><th>Área</th><th>Descrição</th><th>Valor</th><th>Rateio</th></tr></thead>
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
              <select name="r_regra[<?php echo($i); ?>]" class="form-control input-sm">
                <?php foreach ($regras as $rk => $rr) { ?>
                <option value="<?php echo(h($rk)); ?>"><?php echo(h($rr)); ?></option>
                <?php } ?>
              </select>
            </td>
          </tr>
        <?php } ?>
        </tbody>
      </table>

      <p class="small text-muted">
        A regra de rateio <strong>não</strong> vem do mês anterior: ela não fica gravada na
        despesa, só o resultado dela. Confira linha a linha — na planilha da Rede a mesma
        área aparece nas duas regras, então a área não decide sozinha.
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
          <select id="categoria" name="categoria" class="form-control">
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
                 placeholder="Resp. sistemas, Hospedagem Site e Sistema…" />
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

<p class="small text-muted">
  O rateio é <strong>sugerido</strong> ao lançar e fica sempre conferível no botão da linha —
  nunca é a última palavra. Ele <strong>não</strong> vira dívida do núcleo: é custo apontado,
  para o resultado dele poder dizer se se paga. Nenhum saldo de caixa se mexe.
  A sobra de centavos da divisão fica com a Rede.
</p>

<?php } ?>

<?php footer(); ?>
