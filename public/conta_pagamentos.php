<?php
  require  "common.inc.php";
  // require_once e __DIR__ pelo mesmo motivo do conta_cestante.php:7 — o menu.inc.php
  // também carrega o módulo, e um `require` simples morreria por redeclaração no dia em
  // que uma página alcançasse o menu antes daqui.
  require_once(__DIR__ . "/financeiro.inc.php");

  // Primeiro a sessão: logado, não arquivado. Sem parâmetro, porque a regra deste
  // módulo não cabe aqui dentro — ver logo abaixo.
  verifica_seguranca();

  // A trava do módulo NÃO passa por verifica_seguranca(). Aquela função valida qualquer
  // chamada vinda de PAP_ADM sem sequer olhar o parâmetro (common.inc.php:103-110),
  // então `verifica_seguranca(pode_lancar_pagamento())` — a forma que o brief propõe —
  // deixaria ESTA tela, que grava dinheiro, aberta para todo administrador, com ou sem o
  // papel Beta Tester. É a mesma recusa explícita do conta_cestante.php:27, e fica antes
  // de top() para acontecer com o cabeçalho ainda não enviado.
  if (!pode_lancar_pagamento())
  {
    adiciona_mensagem_status(MSG_TIPO_ERRO, "Usuário não possui permissão para a ação executada.");
    redireciona(PAGINAPRINCIPAL);
  }

  $action = request_get("action", ACAO_EXIBIR_EDICAO);

  // Lançamento avulso: com usr_id na URL a tela vira o MESMO formulário com uma linha
  // só, e não uma segunda tela para manter. O id chega da URL e passa por
  // pode_ver_conta_de() ANTES de virar listagem — é ela que confere no banco o vínculo
  // de núcleo e que recusa o que não for inteiro positivo, inclusive ?usr_id[]=1, que
  // chega como array.
  $usr_url = request_get("usr_id", "");
  $um_so   = ($usr_url !== "" && $usr_url !== null);

  if ($um_so && !pode_ver_conta_de($usr_url))
  {
    adiciona_mensagem_status(MSG_TIPO_ERRO, "Usuário não possui permissão para a ação executada.");
    redireciona(PAGINAPRINCIPAL);
  }

  // pode_ver_conta_de() já recusou o que não fosse inteiro positivo: daqui para baixo o
  // cast é exato, e nada de texto da URL chega às consultas.
  $usr_id = $um_so ? (int)$usr_url : 0;

  // Núcleo do painel. O responsável de núcleo NÃO escolhe: é sempre o dele. Sem esta
  // linha, trocar nuc_id na URL abriria o painel de saldos de outro núcleo — e este
  // painel mostra quanto cada pessoa deve.
  //
  // Ela protege a LISTAGEM. Quem protege a gravação é pode_ver_conta_de(), chamada linha
  // a linha lá embaixo: o formulário não é fonte de verdade sobre a quem o responsável
  // alcança, e um POST não precisa ter vindo desta listagem.
  $nuc_id = request_get("nuc_id", isset($_SESSION['usr.nuc']) ? $_SESSION['usr.nuc'] : "");
  if (empty($_SESSION[PAP_RESP_FINANCAS]) && empty($_SESSION[PAP_ADM]))
      $nuc_id = isset($_SESSION['usr.nuc']) ? $_SESSION['usr.nuc'] : "";

  // nuc_id vira consulta: inteiro positivo ou nada. Com o sql_mode vazio deste servidor
  // o banco NÃO recusa texto colado num id — é a mesma guarda de entrada que
  // pode_ver_conta_de() faz, e pelo mesmo motivo. Só string e int passam: ?nuc_id[]=1
  // entrega array a request_get, e converter array em string emitiria aviso na tela.
  if (!is_string($nuc_id) && !is_int($nuc_id)) $nuc_id = "";
  if (!ctype_digit((string)$nuc_id) || (int)$nuc_id <= 0) $nuc_id = "";

  $escolhe_nucleo = (!empty($_SESSION[PAP_RESP_FINANCAS]) || !empty($_SESSION[PAP_ADM]));

  top();

  if ($action == ACAO_SALVAR)
  {
      // A leitura do POST mora em linhas_de_pagamento() para poder ter teste: escrita
      // aqui, nenhuma asserção da suíte a alcançaria. Ela devolve só as linhas que dão
      // para ler, e conta à parte as que estavam preenchidas e não deram.
      //
      // $_POST, e não $_REQUEST: o formulário é method="post", e a composição do
      // $_REQUEST depende do request_order do servidor.
      $lote      = linhas_de_pagamento($_POST);
      $gravados  = 0;
      $recusados = $lote['ignoradas'];

      foreach ($lote['linhas'] as $linha)
      {
          // O formulário NÃO é fonte de verdade sobre a quem o responsável alcança:
          // cada usr_id recebido passa pela regra de acesso antes de virar lançamento.
          if (!pode_ver_conta_de($linha['usr'])) { $recusados++; continue; }

          // O destino também não vem conferido do formulário — quem o confere contra
          // contas_de_destino() é registra_pagamento(), e é lá que tem de ser, porque um
          // POST carrega qualquer con_id.
          $tra = registra_pagamento($linha['usr'], $linha['dt'], $linha['valor'],
                                    $linha['destino'], $linha['comprovante'], "");

          if ($tra) $gravados++;
          else      $recusados++;
      }

      // As duas contas saem juntas, sempre. Um "3 pagamentos registrados" que engole as
      // outras duas linhas é a mesma mentira que este módulo inteiro existe para não
      // contar: quem lançou precisa saber que ficou faltando, e agora, não no fecho do
      // mês. Linha em branco não entra nesta conta — no painel de 35 cestantes, 34
      // linhas vazias são o caso normal.
      $aviso = "$gravados pagamento(s) registrado(s).";
      if ($recusados)
          $aviso .= " $recusados linha(s) preenchida(s) NÃO foram gravadas — confira a data,"
                 .  " o valor e o destino de cada uma.";

      adiciona_mensagem_status($recusados ? MSG_TIPO_AVISO : MSG_TIPO_SUCESSO, $aviso);

      // top() já imprimiu as mensagens pendentes antes de este bloco rodar; a desta
      // gravação precisa da segunda chamada para aparecer.
      escreve_mensagem_status();
  }

  // CONTRATO de contas_de_destino(): null é "a consulta não rodou", array() é "não há
  // destino cadastrado". São coisas diferentes e a tela diz as duas de forma diferente —
  // hoje, com `contas` zerada na base, o certo é a segunda.
  $destinos     = contas_de_destino();
  $sem_destinos = !is_array($destinos) || !count($destinos);

  // Todos os cestantes ATIVOS do núcleo, tenham conta ou não: a conta nasce no primeiro
  // lançamento, e quem nunca pagou nada é justamente quem mais deve aparecer aqui.
  //
  // No caminho avulso é só o cestante pedido, e sem filtro de usr_archive — quem saiu da
  // Rede pode continuar devendo, e quem autorizou a tela a olhar para ele foi
  // pode_ver_conta_de(), que já conferiu o vínculo de núcleo no banco. Repetir aqui um
  // recorte por nuc_id seria uma segunda cópia, mais fraca, da mesma regra.
  if ($um_so)
      $sql = "SELECT usr_id, usr_nome_curto FROM usuarios WHERE usr_id = " . prep_para_bd($usr_id);
  else if ($nuc_id !== "")
      $sql = "SELECT usr_id, usr_nome_curto FROM usuarios "
           . "WHERE usr_archive = 0 AND usr_nuc = " . prep_para_bd($nuc_id) . " "
           . "ORDER BY usr_nome_curto";
  else
      $sql = "";

  $res = ($sql === "") ? false : executa_sql($sql);

  // executa_sql() não aborta: devolve false quando o servidor recusa e 0 sem conexão.
  // Lista que não veio não pode virar tabela vazia, que se leria como "não há cestante".
  $lista_falhou = ($sql !== "" && !$res);
?>

  <legend>Registro de pagamentos</legend>

<?php if ($escolhe_nucleo && !$um_so) { ?>
  <form class="form-inline hidden-print" method="get" action="conta_pagamentos.php" name="frm_nucleo">
    <div class="form-group">
      <label for="nuc_id">Núcleo:</label>&nbsp;
      <select name="nuc_id" id="nuc_id" class="form-control" onchange="javascript:frm_nucleo.submit();">
        <option value="">-- escolha o núcleo --</option>
      <?php
        $res_nuc = executa_sql("SELECT nuc_id, nuc_nome_curto FROM nucleos WHERE nuc_archive = 0 ORDER BY nuc_nome_curto");
        while ($res_nuc && $row_nuc = mysqli_fetch_array($res_nuc, MYSQLI_ASSOC))
        {
      ?>
        <option value="<?php echo(h($row_nuc['nuc_id'])); ?>"<?php echo(((string)$row_nuc['nuc_id'] === (string)$nuc_id) ? ' selected' : ''); ?>><?php echo(h($row_nuc['nuc_nome_curto'])); ?></option>
      <?php } ?>
      </select>
    </div>
  </form>
  <br>
<?php } ?>

<?php
  // Uma tela que grava dinheiro sem destino cadastrado não tem o que fazer, e calar
  // sobre isso deixaria o <select> vazio e ninguém entendendo por quê. As duas causas
  // saem separadas porque pedem providências diferentes.
  if (!is_array($destinos)) { ?>

  <div class="alert alert-danger">
    <strong>Não foi possível carregar as contas de destino.</strong><br>
    Nada pode ser lançado agora. Tente de novo daqui a alguns minutos e, se continuar
    assim, avise a coordenação.
  </div>

<?php } else if (!count($destinos)) { ?>

  <div class="alert alert-warning">
    <strong>Nenhuma conta de destino cadastrada.</strong><br>
    O pagamento precisa dizer para onde o dinheiro foi — o caixa do núcleo, uma conta da
    Rede ou um produtor. Enquanto não houver nenhuma dessas contas, não há como registrar
    pagamento.
  </div>

<?php } ?>

<?php if ($lista_falhou) { ?>

  <div class="alert alert-danger">
    <strong>Não foi possível carregar a lista de cestantes.</strong><br>
    Nenhum saldo pode ser mostrado agora. Tente de novo daqui a alguns minutos.
  </div>

<?php } else if ($sql === "") { ?>

  <div class="alert alert-info">Escolha um núcleo para ver os saldos e lançar pagamentos.</div>

<?php } else { ?>

<form method="post" action="conta_pagamentos.php" name="form_pagamentos">
  <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />
  <input type="hidden" name="nuc_id" value="<?php echo(h($nuc_id)); ?>" />
  <?php if ($um_so) { ?><input type="hidden" name="usr_id" value="<?php echo(h($usr_id)); ?>" /><?php } ?>

  <table class="table table-striped table-bordered table-condensed">
    <thead>
      <tr>
        <th>Cestante</th>
        <th class="text-right">Saldo</th>
        <th>Data</th>
        <th>Pagou</th>
        <th>Destino</th>
        <th>Comprovante</th>
      </tr>
    </thead>
    <tbody>
    <?php
      $hoje          = date('d/m/Y');
      $total_aberto  = 0.0;
      $sem_saldo     = 0;   // linhas cujo extrato não pôde ser calculado
      $cestantes     = 0;

      while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))
      {
          $cestantes++;

          // O saldo é o MESMO número da tela de extrato: derivado da entrega mais o que
          // já está gravado no razão. saldo_da_conta() sozinha soma apenas `lancamentos`
          // e mostraria 0,00 para todo cestante que ainda não teve pagamento — as duas
          // telas do mesmo módulo discordariam sobre quanto alguém deve, justamente na
          // que existe para dizer isso.
          //
          // resumo_do_extrato() devolve ESTADO, e não um saldo que possa vir nulo: em
          // PHP `null < -0.005` e `null > 0.005` são os dois falsos, então saldo nulo
          // descendo a cadeia de comparações sairia pelo ramo do "em dia".
          $resumo = resumo_do_extrato(extrato_do_cestante($row['usr_id']));
          $nao_deu = ($resumo['estado'] === 'indisponivel');

          if ($nao_deu) $sem_saldo++;
          else if ($resumo['saldo'] < -0.005) $total_aberto += $resumo['saldo'];
    ?>
      <tr>
        <td>
          <a href="conta_cestante.php?usr_id=<?php echo(h($row['usr_id'])); ?>"><?php echo(h($row['usr_nome_curto'])); ?></a>
          <input type="hidden" name="pg_usr[]" value="<?php echo(h($row['usr_id'])); ?>" />
        </td>
        <td class="text-right<?php echo((!$nao_deu && $resumo['saldo'] < -0.005) ? ' text-danger' : ''); ?>">
          <?php if ($nao_deu) { ?>
            <span class="label label-danger" title="A consulta deste extrato não rodou">não foi possível calcular</span>
          <?php } else { ?>
            <?php echo(h(formata_moeda($resumo['saldo']))); ?>
          <?php } ?>
        </td>
        <td><input type="text" class="form-control data" name="pg_dt[]" value="<?php echo(h($hoje)); ?>" size="10" /></td>
        <td><input type="text" class="form-control numero" name="pg_valor[]" value="" /></td>
        <td>
          <select class="form-control pg-destino" name="pg_destino[]">
            <?php
              // A primeira opção é vazia de propósito. Com um destino real em primeiro
              // lugar, quem esquecesse de escolher lançaria para ele sem perceber — e
              // pagamento com destino errado é dinheiro debitado de quem não recebeu.
              // Linha sem destino é recusada por registra_pagamento() e entra na conta
              // das não gravadas, que a mensagem mostra.
            ?>
            <option value="">-- escolha o destino --</option>
          <?php
            // $destinos pode ser NULL — a consulta dos destinos falhando não impede a dos
            // cestantes, e a tabela de saldos continua valendo. Sem este cast, o foreach
            // emitiria aviso na tela justamente no caminho de erro; e aviso é o que o
            // smoke.sh reprova.
            foreach ((is_array($destinos) ? $destinos : array()) as $con_id => $rotulo) { ?>
            <option value="<?php echo(h($con_id)); ?>"><?php echo(h($rotulo)); ?></option>
          <?php } ?>
          </select>
        </td>
        <td><input type="text" class="form-control" name="pg_comprovante[]" value="" maxlength="300" /></td>
      </tr>
    <?php } ?>
      <?php if (!$cestantes) { ?>
      <tr><td colspan="6">Nenhum cestante nesta lista.</td></tr>
      <?php } ?>
      <tr>
        <th>TOTAL EM ABERTO<?php if ($sem_saldo) { ?> (parcial)<?php } ?></th>
        <th class="text-right"><?php echo(h(formata_moeda($total_aberto))); ?></th>
        <th colspan="4">
          <?php
            // Total que ignora linha quebrada em silêncio é pior que total nenhum: quem
            // olha para ele acha que está vendo a dívida do núcleo inteira.
            if ($sem_saldo) { ?>
          <span class="text-danger">
            <?php echo(h($sem_saldo)); ?> cestante(s) fora desta soma — o extrato deles não pôde ser calculado.
          </span>
          <?php } ?>
        </th>
      </tr>
    </tbody>
  </table>

  <p class="small text-muted">Saldo negativo: o cestante deve. Saldo positivo: tem a receber.</p>

  <?php if (!$sem_destinos && $cestantes) { ?>
  <div align="right">
    <button class="btn btn-success btn-lg" type="submit"><i class="glyphicon glyphicon-ok glyphicon-white"></i> registrar pagamentos</button>
  </div>
  <?php } ?>
</form>

<script type="text/javascript">
	$(function() {
		$(".data").datepicker({
			format: 'dd/mm/yyyy',
			language: 'pt-BR',
			autoclose: true
		});
	});
</script>

<?php } ?>

<?php footer(); ?>
