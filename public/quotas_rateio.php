<?php
  require  "common.inc.php";
  require_once(__DIR__ . "/financeiro.inc.php");

  verifica_seguranca();

  // Quem define quanto cada núcleo pesa no rateio decide, na prática, quanto cada um
  // paga dos custos da Rede. É ato de quem cuida das finanças, não de quem lança no
  // caixa do núcleo — e por isso a mesma trava de despesas_rede.php.
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

  if (request_get("action", "") == ACAO_SALVAR)
  {
      $ids    = request_get("q_nuc", array());
      $vals   = request_get("q_valor", array());
      $quotas = array();

      if (is_array($ids))
          foreach ($ids as $i => $n)
          {
              $v = (is_array($vals) && isset($vals[$i])) ? $vals[$i] : "";
              if (!is_string($v) && !is_int($v) && !is_float($v)) $v = "";
              // vírgula é como se escreve meia quota em português
              $quotas[(int)$n] = str_replace(',', '.', trim((string)$v));
          }

      $ok = define_quotas_de_rateio($quotas);

      adiciona_mensagem_status($ok ? MSG_TIPO_SUCESSO : MSG_TIPO_ERRO,
          $ok ? "Quotas gravadas. Elas valem para os rateios lançados de agora em diante."
              : "Não foi possível gravar. Toda quota tem de ser um número de 0 a 99 — nenhuma"
              . " foi alterada.");

      // POST-redirect-GET: um F5 depois de gravar não regrava.
      redireciona("quotas_rateio.php");
      exit();
  }

  top();

  $lista = nucleos_e_quotas();

  // Arquivados de fora por padrão: são 19 contra 11 ativos nesta base, e afogariam
  // justamente as linhas em que alguém precisa mexer. Continuam alcançáveis por um
  // link, porque núcleo que volta a ativo tem de reaparecer com a quota que tinha —
  // some-los de vez faria a quota ressurgir sem ninguém ter olhado para ela.
  $ver_arquivados = (request_get("arquivados", "") === "1");

  $quantos_arq = 0;
  foreach ((array)$lista as $l) if ($l['arquivado']) $quantos_arq++;

  escreve_mensagem_status();
?>

<legend>Quotas de rateio</legend>

<?php if (!is_array($lista)) { ?>

  <div class="alert alert-danger">
    <strong>Não foi possível carregar os núcleos.</strong><br>
    Tente de novo daqui a alguns minutos.
  </div>

<?php } else { ?>

<p class="small text-muted">
  A quota diz quanto cada núcleo pesa quando um custo da Rede é rateado
  <strong>por entrega</strong> — um núcleo semanal entrega quatro vezes ao mês e pesa
  quatro; quinzenal, dois; mensal, um. Nos custos rateados <strong>igualmente</strong> a
  quota não entra: hospedagem custa o mesmo tendo o núcleo uma ou quatro entregas.
  <br><strong>Quota 0</strong> tira o núcleo do rateio — é o caso de Logística e Mutirão,
  que existem como núcleo e não rateiam.
</p>

<form method="post" action="quotas_rateio.php">
  <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />

  <table class="table table-bordered table-condensed table-striped">
    <thead>
      <tr>
        <th>Núcleo</th>
        <th>Tipo</th>
        <th class="text-right">Sugestão do tipo</th>
        <th class="text-right">Quota</th>
      </tr>
    </thead>
    <tbody>
    <?php
      // A soma é o divisor de todo mundo: mostrar o total é o que deixa perceber que
      // mexer numa quota muda o quanto TODOS os outros pagam.
      $soma = 0.0; $quantos = 0;

      function quota_legivel($q) { return rtrim(rtrim(number_format($q, 1, ',', ''), '0'), ','); }

      foreach ($lista as $l)
      {
          if (!$l['arquivado'] && $l['vale'] > 0) { $soma += $l['vale']; $quantos++; }
          if ($l['arquivado'] && !$ver_arquivados) continue;
    ?>
      <tr<?php echo($l['arquivado'] ? ' class="text-muted"' : ''); ?>>
        <td>
          <?php echo(h($l['nome'])); ?>
          <?php if ($l['arquivado']) { ?>&nbsp;<span class="label label-default">arquivado</span><?php } ?>
          <?php if (!$l['arquivado'] && $l['vale'] == 0) { ?>&nbsp;<span class="label label-default">não rateia</span><?php } ?>
        </td>
        <td><?php echo(h($l['tipo'])); ?></td>
        <td class="text-right">
          <span class="text-muted"><?php echo(h(quota_legivel($l['sugerida']))); ?></span>
          <?php
            // Só marca quando DIVERGE: marcar sempre viraria ruído, e o ponto é ver de
            // relance quais núcleos alguém decidiu tratar diferente do próprio tipo.
            if ($l['propria'] !== null && abs($l['propria'] - $l['sugerida']) > 0.001) { ?>
            &nbsp;<span class="label label-info" title="alguém definiu diferente da sugestão do tipo">ajustada</span>
          <?php } ?>
        </td>
        <td class="text-right" style="width:120px;">
          <input type="hidden" name="q_nuc[]" value="<?php echo(h($l['nuc_id'])); ?>" />
          <input type="text" name="q_valor[]" class="form-control input-sm text-right"
                 value="<?php echo(h(quota_legivel($l['vale']))); ?>" />
        </td>
      </tr>
    <?php } ?>
      <tr>
        <th colspan="3" class="text-right">
          total das quotas — é por este número que os custos por entrega são divididos
        </th>
        <th class="text-right"><?php echo(h(quota_legivel($soma))); ?></th>
      </tr>
      <tr>
        <td colspan="3" class="text-right text-muted">núcleos que rateiam</td>
        <td class="text-right text-muted"><?php echo(h($quantos)); ?></td>
      </tr>
    </tbody>
  </table>

  <div class="text-right">
    <?php if ($quantos_arq) { ?>
      <a class="btn btn-link" href="quotas_rateio.php<?php echo($ver_arquivados ? '' : '?arquivados=1'); ?>">
        <?php echo($ver_arquivados ? 'esconder' : 'mostrar'); ?> os <?php echo(h($quantos_arq)); ?> núcleos arquivados
      </a>
      &nbsp;
    <?php } ?>
    <a class="btn btn-link" href="despesas_rede.php">voltar às despesas da Rede</a>
    &nbsp;<button class="btn btn-success" type="submit"><i class="glyphicon glyphicon-ok glyphicon-white"></i> gravar quotas</button>
  </div>
</form>

<p class="small text-muted" style="margin-top:12px;">
  Gravar torna a quota <strong>explícita</strong>: a partir daí é ela que manda, e a
  sugestão do tipo passa a valer só para núcleo que ainda não passou por aqui — o que
  nascer amanhã no cadastro, onde ninguém pensa em rateio.
  <br>As quotas valem para os rateios lançados <strong>de agora em diante</strong>. Rateio
  já lançado não muda sozinho: ele guarda o valor que coube a cada núcleo naquele dia,
  e mudá-lo depois reescreveria um mês que talvez já tenha sido conferido — para
  corrigir, abra a despesa em Despesas da Rede e ajuste o rateio dela.
</p>

<?php } ?>

<?php footer(); ?>
