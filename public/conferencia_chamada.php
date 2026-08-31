<?php
  require  "common.inc.php";
  require_once(__DIR__ . "/financeiro.inc.php");

  verifica_seguranca();

  // Mesma audiência de entrega_divergencias.php — é a continuação daquela tela, com o
  // dinheiro ao lado das quantidades. Mais a trava do módulo, que não passa por
  // verifica_seguranca(): aquela função valida qualquer chamada de PAP_ADM sem olhar o
  // parâmetro (common.inc.php:103-110).
  if (!pode_ver_conferencia())
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

  // Chamada que não passa pelo mutirão não tem etapa intermediária nenhuma: o produtor
  // entrega direto no núcleo. Sem contagem central, sem remessa do mutirão, sem estoque
  // guardado entre chamadas — e nenhuma linha, coluna ou explicação sobre eles. Mostrar
  // "estoque no começo 0,00" numa chamada de Frescos inventa uma etapa que não existe e
  // faz quem lê procurar o que nunca foi registrado.
  $tem_mutirao = ($conf !== null && $conf['tem_mutirao']);

  // Núcleo aberto: o número sozinho manda procurar no lugar errado. Em Santa, "6 sem
  // entrega registrada" ao lado de R$ 49,00 — e das seis, uma era a diferença.
  $nuc_id = request_get("nuc_id", "");
  if (!is_string($nuc_id) && !is_int($nuc_id)) $nuc_id = "";
  if (!ctype_digit((string)$nuc_id) || (int)$nuc_id <= 0) $nuc_id = "";

  $detalhe = ($conf !== null && $nuc_id !== "")
           ? detalhe_do_nucleo_na_chamada($cha_id, $nuc_id) : null;

  $nome_nuc = '';
  foreach ((array)($conf ? $conf['nucleos'] : array()) as $x)
      if ((string)$x['nuc_id'] === (string)$nuc_id) $nome_nuc = $x['nome'];

  // Diz quando um número é PISO e não total. As duas colunas do mutirão são preenchidas
  // em uma fração das linhas — 26% e 19% no último ano —, e sem isso "enviou 4.171" ao
  // lado de "recebeu 6.584" parece a corrente quebrada, quando é só coluna vazia.
  function marca_parcial($linhas, $de)
  {
      if ($de <= 0 || $linhas >= $de) return;
      echo(" <span class=\"label label-default\" title=\"preenchido em " . h($linhas) . " de "
         . h($de) . " linhas — o valor é um piso, não o total\">parcial</span>");
  }

  escreve_mensagem_status();
?>

<?php abas_entregas('conferencia'); ?>
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
        <?php if ($tem_mutirao) { ?>
        <tr><td>estoque no começo</td><td class="text-right"><?php echo(h(formata_moeda($conf['estoque']['antes']))); ?></td></tr>
        <?php } ?>
        <?php
          // A DEMANDA, e o único número da tabela que não depende de alguém conferir
          // mercadoria — todos os outros são contagem posterior. Vem crua: o que foi
          // pedido, sem nada abatido de estoque. Sem ela a corrente começa pelo meio,
          // e não dá para ver se a chamada atendeu o que pediram.
          //
          // Fora do bloco do mutirão de propósito: chamada de Frescos também tem pedido,
          // e nela esta é a primeira linha.
        ?>
        <tr><td>pedido pelos cestantes <small class="text-muted">&mdash; a demanda, sem nada abatido</small></td>
            <td class="text-right"><?php echo(h(formata_moeda($conf['total']['pediu']))); ?></td></tr>
        <?php if ($tem_mutirao) { ?>
        <tr><td>recebido pelo mutirão <small class="text-muted">&mdash; a contagem no dia</small>
              <?php marca_parcial($conf["mutirao_linhas"], $conf["confirmado_linhas"]); ?></td>
            <td class="text-right"><?php echo(h(formata_moeda($conf['mutirao']))); ?></td></tr>
        <tr><td>enviado aos núcleos <small class="text-muted">&mdash; o que saiu do mutirão</small>
              <?php marca_parcial($conf["total"]["enviou_linhas"], $conf["total"]["recebeu_linhas"]); ?></td>
            <td class="text-right"><?php echo(h(formata_moeda($conf['total']['enviou']))); ?></td></tr>
        <tr><td>estoque no fim</td><td class="text-right"><?php echo(h(formata_moeda($conf['estoque']['depois']))); ?></td></tr>
        <?php } ?>
        <tr><td>confirmado pelos núcleos <small class="text-muted">&mdash; o que chegou lá</small></td>
            <td class="text-right"><?php echo(h(formata_moeda($conf['total']['recebeu']))); ?></td></tr>
        <tr><td>entregue aos cestantes <small class="text-muted">&mdash; cobra o cestante</small></td>
            <td class="text-right"><?php echo(h(formata_moeda($conf['total']['distribuiu']))); ?></td></tr>
        <?php
          // Finanças confirma POR ÚLTIMO, e a ordem da tabela diz isso: ela olha as
          // justificativas que os núcleos escreveram depois da entrega, e só então fecha o
          // número que paga o produtor. Posta no meio, parecia etapa do caminho da
          // mercadoria; no fim, é o julgamento que ela de fato é.
        ?>
        <tr><td>confirmado por Finanças <small class="text-muted">&mdash; paga o produtor</small></td>
            <td class="text-right"><?php echo(h(formata_moeda($conf['confirmado']))); ?></td></tr>
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
    <?php
      // A CONTA, aberta. "Pago e não cobrado 5.036,80" ao lado de uma diferença direta de
      // 2.776,80 faz quem lê desconfiar do número — e a explicação em prosa dizia só
      // metade da verdade: que o estoque desconta. Ele entra NOS DOIS SENTIDOS, e nesta
      // chamada somou, porque a entrega consumiu mercadoria que já estava guardada.
      $dif_direta  = round($conf['confirmado'] - $conf['total']['distribuiu'], 2);
      // positivo = o estoque encolheu, ou seja, saiu mercadoria guardada sem ser cobrada
      $mov_estoque = round($conf['estoque']['antes'] - $conf['estoque']['depois'], 2);
    ?>
    <table class="table table-condensed" style="margin-bottom:10px;">
      <tbody>
        <tr>
          <td class="small">a Rede pagou ao produtor</td>
          <td class="text-right small"><?php echo(h(formata_moeda($conf['confirmado']))); ?></td>
        </tr>
        <tr>
          <td class="small">menos o que foi cobrado dos cestantes</td>
          <td class="text-right small"><?php echo(h(formata_moeda($conf['total']['distribuiu']))); ?></td>
        </tr>
        <tr>
          <td class="small"><em>diferença</em></td>
          <td class="text-right small"><em><?php echo(h(formata_moeda($dif_direta))); ?></em></td>
        </tr>
        <?php if ($tem_mutirao && abs($mov_estoque) > 0.005) { ?>
        <tr>
          <td class="small">
            <?php echo($mov_estoque > 0 ? 'mais o estoque que a entrega <strong>consumiu</strong>'
                                        : 'menos o estoque que a entrega <strong>guardou</strong>'); ?>
          </td>
          <td class="text-right small"><?php echo(h(formata_moeda(abs($mov_estoque)))); ?></td>
        </tr>
        <?php } ?>
        <tr class="active">
          <th class="small">pago e não cobrado</th>
          <th class="text-right small"><?php echo(h(formata_moeda($conf['nao_cobrado']))); ?></th>
        </tr>
      </tbody>
    </table>

    <?php
      // O QUE FICA À VISTA são as duas frases que quem abre a tela precisa: o que o número
      // é, e a que preço tudo está. O resto explica POR QUE ele se forma assim — vale
      // muito quando surge a dúvida, e é parede de texto quando não surge. Vai para o
      // popover, atrás de "Mais detalhes", em vez de sumir.
      $detalhes = array();

      if ($tem_mutirao)
      {
          // A versão anterior dizia que a mercadoria consumida "saiu sem ninguém ser
          // cobrado por ela", e isso se lê de dois jeitos: como fato da conta, ou como
          // acusação de que alguém deixou de cobrar. Agora o texto fala do que a conta
          // faz — de que lado cada parcela entra — e não do que teria acontecido.
          $detalhes[] = 'O <strong>estoque entra nos dois sentidos</strong>, e é aí que o'
                      . ' número costuma surpreender.'
                      . '<br><br>Se o estoque <strong>encolheu</strong> nesta chamada, saiu'
                      . ' mercadoria do que estava guardado. A Rede pagou por ela numa'
                      . ' chamada anterior, e ela está entre a mercadoria desta aqui — então'
                      . ' entra do lado do que foi pago, e <strong>soma</strong>.'
                      . '<br><br>Se o estoque <strong>cresceu</strong>, ficou mercadoria'
                      . ' guardada para a próxima chamada. A Rede pagou por ela, mas ela não'
                      . ' foi entregue agora e continua sendo dela — então sai da conta desta'
                      . ' chamada, e <strong>desconta</strong>.'
                      . '<br><br>As duas primeiras linhas sozinhas respondem "<em>nesta'
                      . ' chamada, pagamos mais do que cobramos?</em>". Com o estoque, a'
                      . ' pergunta passa a ser "<em>de tudo que estava disponível para'
                      . ' entregar, quanto não virou cobrança?</em>".';

          $detalhes[] = 'A mesma mercadoria é contada <strong>cinco vezes</strong>, por gente'
                      . ' diferente, e cada distância entre duas contagens significa uma'
                      . ' coisa. Mutirão contra Finanças é o que ela <strong>abateu</strong>'
                      . ' ao ler as justificativas — produto vencido sai da conta do produtor.'
                      . ' Enviado contra confirmado é o que se perdeu <strong>no caminho</strong>'
                      . ' até o núcleo. Confirmado contra entregue é o que ficou'
                      . ' <strong>no núcleo</strong>.';

          $detalhes[] = 'As duas contagens do <strong>mutirão</strong> vêm marcadas como'
                      . ' <span class="label label-default">parcial</span> quando não estão'
                      . ' preenchidas em toda linha — e hoje quase nunca estão. Enquanto isso'
                      . ' o número delas é <strong>piso</strong>, não total, e não vale'
                      . ' compará-lo com os outros.';
      }
      else
      {
          $detalhes[] = 'Nesta chamada o produtor entrega <strong>direto no núcleo</strong>:'
                      . ' não há contagem do mutirão, remessa a caminho nem estoque guardado'
                      . ' entre chamadas, e por isso essas linhas não aparecem. A mercadoria'
                      . ' é contada <strong>três vezes</strong> — o núcleo confirma o que'
                      . ' chegou, o cestante recebe, e Finanças fecha o que paga o produtor.'
                      . ' Confirmado contra entregue é o que ficou <strong>no núcleo</strong>;'
                      . ' confirmado contra Finanças é o que ela <strong>abateu</strong> ao ler'
                      . ' as justificativas.';
      }
    ?>
    <p class="small text-muted">
      <strong>Pago e não cobrado</strong> é o que a Rede pagou ao produtor e ninguém foi
      cobrado. Sobrou, foi doado, estragou depois de aceito, ou a entrega não foi anotada.
      <?php
        // .btn-popover é a classe que pedido.js:389 inicializa em toda página. O gatilho é
        // CLIQUE, e não o hover de adiciona_popover_descricao(): este texto é longo, e um
        // balão que some quando o mouse escapa não se termina de ler.
        //
        // data-html com a marcação escapada pelo h(): o navegador desfaz o escape ao ler o
        // atributo, e o Bootstrap injeta como HTML. Escapar é o que impede a marcação de
        // fechar o atributo aqui.
      ?>
      <a href="#" onclick="return false;" class="btn-popover"
         data-trigger="click" data-placement="left" data-container="body" data-html="true"
         data-title="Mais detalhes"
         data-content="<?php echo(h(implode('<br><br>', $detalhes))); ?>">
        <i class="glyphicon glyphicon-info-sign"></i> Mais detalhes</a>
      <br><br>
      Tudo a <strong>preço de venda</strong>: a pergunta aqui é quanto disto virou dívida de
      alguém.
    </p>
  </div>
</div>

<legend style="font-size:medium;" id="porNucleo">Por núcleo</legend>

<table class="table table-bordered table-condensed table-striped">
  <thead>
    <tr>
      <th>Núcleo</th>
      <?php if ($tem_mutirao) { ?><th class="text-right">Mutirão enviou</th><?php } ?>
      <th class="text-right">Núcleo confirmou receber</th>
      <th class="text-right">Entregou aos cestantes</th>
      <th class="text-right">Diferença</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($conf['nucleos'] as $n) {
        // O NOME DEIXOU DE SER LINK. Em todo o resto do sistema um nome sublinhado leva a
        // outra página; aqui ele abria uma seção mais abaixo NESTA, e a promessa não
        // batia com o que acontecia. Agora o nome é texto e quem convida a abrir é um
        // botão com seta para baixo — o desenho que já significa "expande" em qualquer
        // lugar. Aberto, a seta inverte e o botão passa a fechar.
        $aberto = ((string)$n['nuc_id'] === (string)$nuc_id);
        $url_abre  = 'conferencia_chamada.php?cha_id=' . h($conf['cha_id'])
                   . '&amp;nuc_id=' . h($n['nuc_id']) . '#detalhe';
        // fechar volta para a tabela, e não para o topo: quem fecha quer seguir lendo a
        // linha em que estava
        $url_fecha = 'conferencia_chamada.php?cha_id=' . h($conf['cha_id']) . '#porNucleo';
  ?>
    <tr<?php echo($aberto ? ' class="info"' : ''); ?>>
      <td>
        <?php if ($aberto) { ?><strong><?php } ?><?php echo(h($n['nome'])); ?><?php if ($aberto) { ?></strong><?php } ?>
        <a class="btn btn-default btn-xs hidden-print" style="margin-left:6px;"
           href="<?php echo($aberto ? $url_fecha : $url_abre); ?>"
           title="<?php echo($aberto ? 'fechar o detalhe deste núcleo'
                                     : 'abrir o detalhe deste núcleo, produto a produto, mais abaixo'); ?>">
          <i class="glyphicon glyphicon-<?php echo($aberto ? 'chevron-up' : 'chevron-down'); ?>"></i>
          <?php echo($aberto ? 'fechar' : 'detalhes'); ?>
        </a>
      </td>
      <?php if ($tem_mutirao) { ?>
      <td class="text-right">
        <?php
          // SEM destaque vermelho e SEM o selo "parcial". dist_quantidade é preenchida em
          // 26% das linhas em que dist_quantidade_recebido é, então qualquer marca aqui
          // apareceria em quase todo núcleo — vira ruído numa coluna inteira de números, e
          // suja o texto de quem copia a tabela para uma planilha. A ressalva fica dita uma
          // vez, na tabela de cima, onde é uma linha só.
          echo(h(formata_moeda($n['enviou'])));
        ?>
      </td>
      <?php } ?>
      <td class="text-right"><?php echo(h(formata_moeda($n['recebeu']))); ?></td>
      <td class="text-right"><?php echo(h(formata_moeda($n['distribuiu']))); ?></td>
      <td class="text-right<?php echo(abs($n['diferenca']) > 0.005 ? ' text-danger' : ''); ?>">
        <?php echo(h(formata_moeda($n['diferenca']))); ?>
      </td>
      <td>
        <?php
          // O aviso conta as linhas que PODEM explicar a conta: pedido feito, entrega não
          // anotada, num produto que o núcleo confirmou ter recebido. Antes contava toda
          // linha sem entrega, inclusive de produto que o núcleo nunca recebeu — e essas
          // contribuem zero dos dois lados, o que dava a um núcleo com diferença 0,00 um
          // aviso de "29 sem entrega registrada" ao lado.
          //
          // O texto muda com a diferença, porque as duas situações são diferentes. SEM
          // diferença a conta do núcleo fecha, e as duas causas comuns são repasse entre
          // cestantes (alguém desiste e outro leva) e entrega parcial anotada em outra
          // linha. Isso é normal e não se acusa — apenas se diz o que aconteceu, para
          // quem confere decidir se vale olhar.
          if ($n['sem_registro'] > 0) {
              $tem_dif = (abs($n['diferenca']) > 0.005); ?>
          <span class="label label-warning"><?php echo(h($n['sem_registro'])); ?> em branco</span>
          <small class="text-muted">&nbsp;<?php
            echo($tem_dif ? 'abra os detalhes para ver quais'
                          : '(mas a conta fecha, então pode ser repasse entre cestantes'
                          . ' ou entrega parcial)'); ?></small>
        <?php }
          // O contrapeso do aviso: uma coisa é o núcleo dever explicação, outra é ele já
          // ter explicado. Sem este número as duas apareciam iguais aqui, e o núcleo que
          // escreveu cada justificativa ficava indistinguível do que não escreveu nenhuma.
          if ($n['justificativas'] > 0) { ?>
          <?php if ($n['sem_registro'] > 0) { ?><br><?php } ?>
          <span class="label label-info"><?php echo(h($n['justificativas'])); ?> justificada<?php echo($n['justificativas'] > 1 ? 's' : ''); ?></span>
          <small class="text-muted">&nbsp;divergência<?php echo($n['justificativas'] > 1 ? 's' : ''); ?> que o núcleo já explicou por escrito</small>
        <?php } ?>
      </td>
    </tr>
  <?php } ?>
  <?php if (!count($conf['nucleos'])) { ?>
    <tr><td colspan="<?php echo($tem_mutirao ? 6 : 5); ?>">Nenhum núcleo movimentou esta chamada.</td></tr>
  <?php } else { ?>
    <tr class="active">
      <th>total</th>
      <?php if ($tem_mutirao) { ?><th class="text-right"><?php echo(h(formata_moeda($conf['total']['enviou']))); ?></th><?php } ?>
      <th class="text-right"><?php echo(h(formata_moeda($conf['total']['recebeu']))); ?></th>
      <th class="text-right"><?php echo(h(formata_moeda($conf['total']['distribuiu']))); ?></th>
      <th class="text-right"><?php echo(h(formata_moeda($conf['total']['diferenca']))); ?></th>
      <th><small class="text-muted"><?php
        $partes_tot = array();
        if ($conf['total']['sem_registro'] > 0)   $partes_tot[] = h($conf['total']['sem_registro']) . ' em branco';
        if ($conf['total']['justificativas'] > 0) $partes_tot[] = h($conf['total']['justificativas']) . ' justificada(s)';
        echo(implode(' &middot; ', $partes_tot));
      ?></small></th>
    </tr>
  <?php } ?>
  </tbody>
</table>

<?php if ($detalhe !== null) { ?>

<legend style="font-size:medium;" id="detalhe">
  <?php echo(h($nome_nuc !== '' ? $nome_nuc : 'Núcleo')); ?> &middot; produto a produto
</legend>

<?php if (!count($detalhe)) { ?>
  <p class="text-muted">Nada a explicar neste núcleo: nenhuma diferença, nenhuma linha em branco.</p>
<?php } else {
  // Colunas da tabela, para o colspan das linhas de registro: sem mutirão a coluna
  // Enviado não existe.
  $cols_det = $tem_mutirao ? 7 : 6;
?>

<div class="checkbox hidden-print" style="margin-top:0;">
  <label>
    <input type="checkbox" id="ver_registros" />
    <strong>ver os registros</strong>
    <small class="text-muted">&mdash; abre, sob cada produto, as linhas de cestante que deram origem à nota</small>
  </label>
</div>

<table class="table table-bordered table-condensed table-striped">
  <thead>
    <tr>
      <th>Produto</th>
      <?php if ($tem_mutirao) { ?><th class="text-right">Enviado</th><?php } ?>
      <th class="text-right">Núcleo confirmou receber</th>
      <th class="text-right">Entregue</th>
      <th class="text-right">Diferença</th>
      <th>Justificativa</th>
      <th>Linhas em branco</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($detalhe as $d) { ?>
    <tr>
      <td><?php echo(h($d['nome'])); ?> <small class="text-muted"><?php echo(h($d['unidade'])); ?></small></td>
      <?php if ($tem_mutirao) { ?>
      <td class="text-right">
        <?php echo($d['enviou'] > 0
                   ? h(rtrim(rtrim(number_format($d['enviou'], 2, ',', '.'), '0'), ','))
                   : '<span class="text-muted" title="o mutirão não informou o enviado deste produto">&mdash;</span>'); ?>
      </td>
      <?php } ?>
      <td class="text-right"><?php echo(h(rtrim(rtrim(number_format($d['recebeu'], 2, ',', '.'), '0'), ','))); ?></td>
      <td class="text-right"><?php echo(h(rtrim(rtrim(number_format($d['entregue'], 2, ',', '.'), '0'), ','))); ?></td>
      <td class="text-right<?php echo(abs($d['diferenca']) > 0.005 ? ' text-danger' : ''); ?>">
        <?php echo(h(formata_moeda($d['diferenca']))); ?>
      </td>
      <td>
        <?php if ($d['justificativa'] !== '') { ?>
          <?php echo(h($d['justificativa'])); ?>
        <?php } else if (abs($d['diferenca']) > 0.005) { ?>
          <span class="label label-danger">sem justificativa</span>
        <?php } else { ?>
          <span class="text-muted">&mdash;</span>
        <?php } ?>
      </td>
      <td>
        <?php
          // Nomear quem ficou em branco é o que faz a linha ser investigável. Sem os
          // nomes, "4 em branco" manda abrir outra tela e procurar.
          if (!count($d['em_branco'])) { ?>
          <span class="text-muted">&mdash;</span>
        <?php } else {
          // escapa CADA parte e junta com a marcação depois: h() sobre a string já
          // juntada escaparia o próprio separador, e o leitor via "&middot;" literal
          $partes = array();
          foreach ($d['em_branco'] as $b)
              $partes[] = h($b['nome']) . ' <span class="text-muted">('
                        . h(rtrim(rtrim(number_format($b['pediu'], 2, ',', '.'), '0'), ',')) . ')</span>';
          echo('<small>' . implode(' &middot; ', $partes) . '</small>');
        } ?>
      </td>
    </tr>
    <?php
      // Os registros que deram origem à nota. Ficam ESCONDIDOS por padrão porque um
      // núcleo grande traz centenas de linhas e a tabela deixa de ser legível — mas
      // ficam AQUI, coladas ao produto, e não em outra tela: a justificativa e o que a
      // sustenta se leem juntas ou não se leem.
      if (count($d['cestantes'])) { ?>
    <tr class="registros" style="display:none;">
      <td colspan="<?php echo(h($cols_det)); ?>" style="background:#fbfbfb;">
        <small>
          <strong><?php echo(h($d['nome'] . ' ' . $d['unidade'])); ?></strong>
          <span class="text-muted">&middot; <?php echo(h(count($d['cestantes']))); ?> cestante(s) &middot; pediu &rarr; entregue</span>
          <br>
          <?php
            $regs = array();
            foreach ($d['cestantes'] as $c)
            {
                $pediu = rtrim(rtrim(number_format($c['pediu'], 2, ',', '.'), '0'), ',');

                if ($c['entregue'] === null)
                    // em branco não é zero: ninguém anotou. É o que o aviso da tabela
                    // de cima conta, e o que quem confere precisa ver primeiro.
                    $ent = '<span class="label label-warning">em branco</span>';
                else if (abs($c['entregue'] - $c['pediu']) < 0.005)
                    $ent = h(rtrim(rtrim(number_format($c['entregue'], 2, ',', '.'), '0'), ','));
                else
                    $ent = '<strong class="text-danger">'
                         . h(rtrim(rtrim(number_format($c['entregue'], 2, ',', '.'), '0'), ','))
                         . '</strong>';

                $regs[] = h($c['nome']) . ' <span class="text-muted">' . h($pediu) . ' &rarr;</span> ' . $ent;
            }
            echo(implode(' &nbsp;&middot;&nbsp; ', $regs));
          ?>
        </small>
      </td>
    </tr>
    <?php } ?>
  <?php } ?>
  </tbody>
</table>

<script>
  // Sem reload: quem confere alterna a visão dezenas de vezes enquanto lê, e recarregar
  // a página a cada clique perderia a rolagem e a chamada em foco.
  $('#ver_registros').on('change', function () {
      $('tr.registros').toggle(this.checked);
  });
</script>

<p class="small text-muted">
  Só aparece o produto que tem algo a dizer: diferença, linha em branco, ou justificativa
  escrita. <strong>Diferença com justificativa</strong> está explicada e não precisa de mais
  nada. <strong>Sem justificativa</strong> é o que vale investigar — e se escreve em
  <a href="entrega_divergencias.php">Divergências</a>.
  <br>Linha em branco num produto que fechou em zero não muda a conta: alguém desistiu e
  outro levou.
</p>
<?php } ?>

<?php } ?>

<p class="small text-muted">
  O aviso <strong>sem entrega registrada</strong> conta só as linhas que podem explicar a
  conta: pedido feito, entrega não anotada, num produto que o núcleo confirmou ter recebido.
  Havendo diferença, ela pode ser só isso — não cobre do núcleo antes de conferir. Sem
  diferença, a conta fecha, e aí as causas comuns são <strong>repasse entre cestantes</strong>
  — alguém desiste e outro leva — e <strong>entrega parcial</strong> anotada em outra linha.
  <br>Diferença <strong>positiva</strong>: o núcleo confirmou receber mais do que entregou.
  <strong>Negativa</strong>: entregou sem ter confirmado o recebimento — a conta não fecha
  por falta de registro, não por falta de mercadoria.
</p>

<?php } ?>

<?php footer(); ?>
