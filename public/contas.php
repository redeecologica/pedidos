<?php
  require  "common.inc.php";
  require  "financeiro.inc.php";

  // A trava do módulo NÃO passa por verifica_seguranca(): aquela função valida
  // qualquer chamada de PAP_ADM sem olhar o parâmetro (common.inc.php:103-110), então
  // `verifica_seguranca(...)` abriria a tela para administrador sem Beta Tester.
  //
  // Criar conta é ato de quem cuida do dinheiro da Rede, não de quem lança pagamento
  // no núcleo: por isso RESP_FINANÇAS ou ADM, e não pode_lancar_pagamento().
  if (!pode_ver_financeiro()
      || (empty($_SESSION[PAP_RESP_FINANCAS]) && empty($_SESSION[PAP_ADM])))
  {
      adiciona_mensagem_status(MSG_TIPO_ERRO, "Usuário não possui permissão para a ação executada.");
      redireciona(PAGINAPRINCIPAL);
      exit();
  }

  if (request_get("action", "") == ACAO_SALVAR)
  {
      // Cria a conta que falta para cada núcleo e produtor ativo. Idempotente: rodar
      // de novo esbarra na UNIQUE de con_nuc/con_forn e cria zero.
      $criadas = cria_contas_que_faltam();

      if ($criadas === null)
          adiciona_mensagem_status(MSG_TIPO_ERRO, "Não foi possível verificar as contas que faltam.");
      else if ($criadas === 0)
          adiciona_mensagem_status(MSG_TIPO_SUCESSO, "Nenhuma conta faltando: todo núcleo e produtor ativo já tem a sua.");
      else
          adiciona_mensagem_status(MSG_TIPO_SUCESSO, "$criadas conta(s) criada(s).");

      // POST-redirect-GET: um F5 depois disto não repete a criação.
      redireciona("contas.php");
      exit();
  }

  top();

  // Lista TODAS, inclusive arquivadas e de cestante: esta é a tela de quem precisa
  // enxergar o conjunto. O que muda por tipo é o que dá para fazer, não o que dá para ver.
  $sql = "SELECT c.con_id, c.con_tipo, c.con_nome, c.con_chave, c.con_archive, ";
  $sql.= "n.nuc_nome_curto, f.forn_nome_curto, u.usr_nome_curto ";
  $sql.= "FROM contas c ";
  $sql.= "LEFT JOIN nucleos n       ON n.nuc_id  = c.con_nuc ";
  $sql.= "LEFT JOIN fornecedores f  ON f.forn_id = c.con_forn ";
  $sql.= "LEFT JOIN usuarios u      ON u.usr_id  = c.con_usr ";
  $sql.= "ORDER BY FIELD(c.con_tipo,'rede','nucleo','produtor','cestante'), c.con_nome, c.con_id";

  $res = executa_sql($sql);

  // executa_sql() não aborta: devolve false quando o servidor recusa. Lista que não
  // veio não pode virar tabela vazia, que se leria como "não há conta nenhuma".
  $lista_falhou = !$res;

  escreve_mensagem_status();
?>

<legend>Contas do módulo financeiro</legend>

<?php if ($lista_falhou) { ?>
  <div class="alert alert-danger">Não foi possível carregar a lista de contas.</div>
<?php } else { ?>

<div style="margin-bottom:12px;">
  <a href="conta.php?action=<?php echo(ACAO_INCLUIR); ?>" class="btn btn-default btn-xs">
    <i class="glyphicon glyphicon-plus"></i> nova conta da Rede
  </a>
  &nbsp;
  <form method="post" action="contas.php" style="display:inline;">
    <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />
    <button type="submit" class="btn btn-default btn-xs">
      <i class="glyphicon glyphicon-refresh"></i> criar as contas que faltam
    </button>
  </form>
  <span class="small text-muted">
    &nbsp;uma por núcleo e por produtor ativo — só a conta da Rede se cria à mão, porque só o nome dela é uma decisão.
  </span>
</div>

<table class="table table-striped table-bordered table-condensed">
  <thead>
    <tr>
      <th>Tipo</th>
      <th>Nome</th>
      <th>Vínculo</th>
      <th>Chave</th>
      <th class="text-right">Saldo</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
  <?php
    $quantas = 0;
    $rotulo_tipo = array('rede' => 'Rede', 'nucleo' => 'Núcleo',
                         'produtor' => 'Produtor', 'cestante' => 'Cestante');

    while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))
    {
        $quantas++;

        $vinculo = trim((string)$row['nuc_nome_curto'])
                 . trim((string)$row['forn_nome_curto'])
                 . trim((string)$row['usr_nome_curto']);

        // Saldo de PRODUTOR não se exibe nesta entrega, e a spec é explícita quanto
        // a isso. A conta dele só recebe o lado do débito — o que já foi pago —
        // porque o crédito ("tem a receber pelo que entregou") é a fatia seguinte.
        // Lido isolado, o saldo diz "o produtor deve", que é o inverso da verdade.
        // Esta é justamente a tela onde alguém iria perguntar quanto se deve a ele.
        $mostra_saldo = ($row['con_tipo'] !== 'produtor');
        $saldo = $mostra_saldo ? saldo_da_conta($row['con_id']) : null;
  ?>
    <tr<?php echo($row['con_archive'] ? ' class="text-muted"' : ''); ?>>
      <td><?php echo(h(isset($rotulo_tipo[$row['con_tipo']]) ? $rotulo_tipo[$row['con_tipo']] : $row['con_tipo'])); ?></td>
      <td>
        <?php echo(h(trim((string)$row['con_nome']) !== '' ? $row['con_nome'] : '#' . $row['con_id'])); ?>
        <?php if ($row['con_archive']) { ?>
          &nbsp;<span class="label label-default">arquivada</span>
        <?php } ?>
      </td>
      <td><?php echo(h($vinculo !== '' ? $vinculo : '—')); ?></td>
      <td><small class="text-muted"><?php echo(h(trim((string)$row['con_chave']) !== '' ? $row['con_chave'] : '—')); ?></small></td>
      <td class="text-right">
        <?php if (!$mostra_saldo) { ?>
          <span class="text-muted" title="A conta do produtor registra só o que já foi pago a ele; o que ele tem a receber ainda não é lançado. Use Previsão de Pagamento.">&mdash;</span>
        <?php } else {
          // saldo_da_conta() devolve null quando a consulta falha, e null não pode
          // virar 0,00 numa coluna de dinheiro.
          if ($saldo === null) { ?>
          <span class="label label-danger" title="A consulta deste saldo não rodou">não foi possível calcular</span>
        <?php } else { ?>
          <?php echo(h(formata_moeda($saldo))); ?>
        <?php } } ?>
      </td>
      <td class="text-right">
        <a class="btn btn-default btn-xs" href="conta.php?action=<?php echo(ACAO_EXIBIR_EDICAO); ?>&amp;con_id=<?php echo(h($row['con_id'])); ?>">
          <i class="glyphicon glyphicon-pencil"></i>
        </a>
      </td>
    </tr>
  <?php } ?>
  <?php if (!$quantas) { ?>
    <tr><td colspan="6">Nenhuma conta cadastrada ainda.</td></tr>
  <?php } ?>
  </tbody>
</table>

<p class="small text-muted">
  Conta de cestante, de núcleo e de produtor nasce sozinha — a de cestante no primeiro
  pagamento, as outras duas pelo botão acima. O que se edita aqui é o nome e o arquivamento;
  tipo, vínculo e chave são o que a conta é, e não mudam depois de criada.
</p>

<p class="small text-muted">
  O saldo do produtor aparece como &mdash; de propósito: por ora só se lança o que já foi
  <em>pago</em> a ele. Enquanto o que ele tem a receber não for lançado, um saldo aqui diria
  o contrário do que é verdade. Para saber quanto pagar, use a Previsão de Pagamento.
</p>

<?php } ?>

<?php footer(); ?>
