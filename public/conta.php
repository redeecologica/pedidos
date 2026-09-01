<?php
  require  "common.inc.php";
  require  "financeiro.inc.php";

  // Mesma trava de contas.php, e pelo mesmo motivo: verifica_seguranca() valida
  // qualquer chamada de PAP_ADM sem olhar o parâmetro (common.inc.php:103-110).
  // SÓ ADMINISTRADOR, como em contas.php. As duas telas eram a mesma decisão com travas
  // diferentes: a lista exigia ADM e o editor aceitava RESP_FINANÇAS, então bastava
  // chegar aqui pela URL para criar uma conta da Rede sem passar pela lista. Criar conta
  // é decisão de quem administra o sistema, e quase nunca muda.
  if (!pode_ver_financeiro() || empty($_SESSION[PAP_ADM]))
  {
      adiciona_mensagem_status(MSG_TIPO_ERRO, "Usuário não possui permissão para a ação executada.");
      redireciona(PAGINAPRINCIPAL);
      exit();
  }

  $action = request_get("action", ACAO_EXIBIR_EDICAO);

  $con_id = request_get("con_id", "");
  if (!is_string($con_id) && !is_int($con_id)) $con_id = "";
  if (!ctype_digit((string)$con_id) || (int)$con_id <= 0) $con_id = "";

  if ($action == ACAO_SALVAR)
  {
      if ($con_id === "")
      {
          // Criar: só conta da REDE. Núcleo, produtor e cestante nascem sozinhos — o
          // vínculo delas já está no cadastro e o nome sai dele. A da Rede é a única
          // cujo rótulo é uma decisão ("Rede (conta Adelina)").
          $nome  = trim((string)request_get("con_nome", ""));
          $chave = trim((string)request_get("con_chave", ""));

          $novo = cria_conta('rede', array('con_nome' => $nome, 'con_chave' => $chave));

          adiciona_mensagem_status($novo ? MSG_TIPO_SUCESSO : MSG_TIPO_ERRO,
              $novo ? "Conta criada."
                    : "Não foi possível criar a conta. Confira o nome e a chave — a chave não"
                    . " pode repetir a de outra conta.");
      }
      else
      {
          // Editar: nome e arquivamento. Tipo, vínculo e chave dizem o que a conta É;
          // trocá-los numa conta que já tem lançamento mudaria em silêncio de quem é
          // aquele dinheiro, e a chave ainda é a identidade que a Task 3 fixou.
          $ok_nome = renomeia_conta($con_id, request_get("con_nome", ""));
          $ok_arq  = arquiva_conta($con_id, request_get("con_archive", "") === "1");

          adiciona_mensagem_status(($ok_nome && $ok_arq) ? MSG_TIPO_SUCESSO : MSG_TIPO_ERRO,
              ($ok_nome && $ok_arq) ? "Conta atualizada."
                                    : "Não foi possível atualizar a conta. O nome não pode ficar em branco.");
      }

      // POST-redirect-GET: volta para a lista com o estado novo, e um F5 não repete.
      redireciona("contas.php");
      exit();
  }

  top();

  $conta = null;
  if ($con_id !== "")
  {
      $sql = "SELECT c.con_id, c.con_tipo, c.con_nome, c.con_chave, c.con_archive, ";
      $sql.= "n.nuc_nome_curto, f.forn_nome_curto, u.usr_nome_curto ";
      $sql.= "FROM contas c ";
      $sql.= "LEFT JOIN nucleos n      ON n.nuc_id  = c.con_nuc ";
      $sql.= "LEFT JOIN fornecedores f ON f.forn_id = c.con_forn ";
      $sql.= "LEFT JOIN usuarios u     ON u.usr_id  = c.con_usr ";
      $sql.= "WHERE c.con_id = " . prep_para_bd($con_id);

      $res = executa_sql($sql);

      // "a consulta não rodou" e "não existe conta com esse id" são coisas diferentes,
      // e nenhuma das duas pode virar um formulário em branco que grava por cima.
      if ($res) $conta = mysqli_fetch_array($res, MYSQLI_ASSOC);

      if (!$conta)
      {
          adiciona_mensagem_status(MSG_TIPO_ERRO, "Conta não encontrada.");
          redireciona("contas.php");
          exit();
      }
  }

  $criando = ($conta === null);
  escreve_mensagem_status();
?>

<legend><?php echo($criando ? "Nova conta da Rede" : "Conta"); ?></legend>

<form method="post" action="conta.php" class="form-horizontal">
  <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />
  <?php if (!$criando) { ?>
  <input type="hidden" name="con_id" value="<?php echo(h($conta['con_id'])); ?>" />
  <?php } ?>

  <?php if (!$criando) { ?>
  <div class="form-group">
    <label class="control-label col-sm-2">Tipo</label>
    <div class="col-sm-6"><p class="form-control-static"><?php echo(h($conta['con_tipo'])); ?></p></div>
  </div>
  <div class="form-group">
    <label class="control-label col-sm-2">Vínculo</label>
    <div class="col-sm-6">
      <p class="form-control-static">
        <?php
          $vinc = trim((string)$conta['nuc_nome_curto'])
                . trim((string)$conta['forn_nome_curto'])
                . trim((string)$conta['usr_nome_curto']);
          echo(h($vinc !== '' ? $vinc : '—'));
        ?>
      </p>
    </div>
  </div>
  <div class="form-group">
    <label class="control-label col-sm-2">Chave</label>
    <div class="col-sm-6">
      <p class="form-control-static"><small class="text-muted"><?php echo(h(trim((string)$conta['con_chave']) !== '' ? $conta['con_chave'] : '—')); ?></small></p>
    </div>
  </div>
  <?php } ?>

  <div class="form-group">
    <label class="control-label col-sm-2" for="con_nome">Nome</label>
    <div class="col-sm-6">
      <input type="text" id="con_nome" name="con_nome" class="form-control" maxlength="120" required="required"
             value="<?php echo(h($criando ? "" : $conta['con_nome'])); ?>"
             placeholder="<?php echo($criando ? "Rede (conta Fulana)" : ""); ?>" autofocus />
      <span class="help-block small">O nome é o que aparece na lista de destinos de pagamento, e pode mudar quando quiser.</span>
    </div>
  </div>

  <?php if ($criando) { ?>
  <div class="form-group">
    <label class="control-label col-sm-2" for="con_chave">Chave</label>
    <div class="col-sm-6">
      <input type="text" id="con_chave" name="con_chave" class="form-control" maxlength="30"
             placeholder="rede_ecologica_3" />
      <span class="help-block small">
        Identifica a conta para sempre, e <strong>não muda depois</strong>. É por ela que o
        sistema reconhece a conta quando o nome for editado — sem isso, renomear criaria uma
        segunda conta em silêncio. Pode ficar em branco se esta conta não precisar ser
        encontrada por código.
      </span>
    </div>
  </div>
  <?php } else { ?>
  <div class="form-group">
    <label class="control-label col-sm-2" for="con_archive">Arquivada</label>
    <div class="col-sm-6">
      <select id="con_archive" name="con_archive" class="form-control">
        <option value="0"<?php echo($conta['con_archive'] ? '' : ' selected'); ?>>não</option>
        <option value="1"<?php echo($conta['con_archive'] ? ' selected' : ''); ?>>sim</option>
      </select>
      <span class="help-block small">
        Conta arquivada sai da lista de destinos de pagamento, mas não é apagada: o que já foi
        lançado nela continua valendo no extrato de quem movimentou.
      </span>
    </div>
  </div>
  <?php } ?>

  <div class="form-group">
    <div class="col-sm-offset-2 col-sm-6 text-right">
      <a class="btn btn-link" href="contas.php">cancelar</a>
      &nbsp;<button class="btn btn-success" type="submit"><i class="glyphicon glyphicon-ok glyphicon-white"></i> salvar</button>
    </div>
  </div>
</form>

<?php footer(); ?>
