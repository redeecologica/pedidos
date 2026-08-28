<?php
  require  "common.inc.php";
  // require_once e __DIR__, por simetria com o menu.inc.php:26, que também carrega o
  // módulo. Hoje esta linha roda antes do top(), então um `require` simples ainda seria
  // seguro; a simetria é para a página que vier depois e alcançar o menu antes daqui —
  // essa morreria por redeclaração de função.
  require_once(__DIR__ . "/financeiro.inc.php");

  $usr_id = request_get("usr_id", isset($_SESSION['usr.id']) ? $_SESSION['usr.id'] : "");

  // Primeiro a sessão: logado, não arquivado. Sem parâmetro, porque a regra deste
  // módulo não cabe aqui dentro — ver logo abaixo.
  verifica_seguranca();

  // A trava do módulo NÃO passa por verifica_seguranca(). Aquela função valida
  // qualquer chamada vinda de PAP_ADM sem sequer olhar o parâmetro
  // (common.inc.php:103-110), então `verifica_seguranca(pode_ver_conta_de($usr_id))`
  // — a forma que o brief propõe — deixaria a tela aberta para todo administrador,
  // com ou sem o papel Beta Tester, que é justo o contrário de "o módulo fica
  // invisível até estar pronto".
  //
  // Medido por curl nesta cópia, com o mesmo administrador SEM o papel Beta Tester:
  // pela forma do brief a página respondeu HTTP 200 com a legenda "Conta de" e a
  // situação impressas; com a recusa abaixo responde HTTP 302 para inicio.php e
  // corpo nenhum. Fica antes de top() para a recusa acontecer com o cabeçalho ainda
  // não enviado.
  if (!pode_ver_conta_de($usr_id))
  {
    adiciona_mensagem_status(MSG_TIPO_ERRO, "Usuário não possui permissão para a ação executada.");
    redireciona(PAGINAPRINCIPAL);
  }

  // pode_ver_conta_de() já recusou o que não fosse inteiro positivo: daqui para
  // baixo o cast é exato, e nada de texto da URL chega às consultas.
  $usr_id = (int)$usr_id;

  // Conta que a tela não confirmou existir não recebe afirmação nenhuma sobre dinheiro.
  // Um id que não existe não tem lançamento, o saldo sai zero e a tela afirmava "em dia".
  // Medido tirando esta recusa e pedindo ?usr_id=9999999: HTTP 200,
  // `<legend>Conta de </legend>` sem nome nenhum e o rótulo verde `em dia`. "Está quite"
  // e "não há esse alguém" não são a mesma frase.
  //
  // A recusa é a mesma dos outros alvos inválidos (-1, 0, texto): id que não designa
  // cestante não abre tela. Também não conta a ninguém quais ids existem: quem não é ADM
  // nem Responsável Finanças já foi barrado antes daqui — o ramo do próprio exige
  // igualdade e o de núcleo exige a linha do cestante no banco, então um id inexistente
  // não chega a este ponto por nenhum dos dois.
  //
  // O ramo 'indisponivel' NÃO cai aqui: a consulta que não roda é tratada com o extrato,
  // lá embaixo, porque "não sei se existe" não pode virar "não existe".
  $cestante = cestante_da_conta($usr_id);

  if ($cestante['estado'] === 'inexistente')
  {
    adiciona_mensagem_status(MSG_TIPO_ERRO, "Conta não encontrada.");
    redireciona(PAGINAPRINCIPAL);
  }

  // Qual lançamento abre em edição, e o POST que a grava. Os dois passam pelo MESMO
  // dono: cestante_da_transacao() tem de devolver o usr_id desta tela.
  //
  // Sem essa conferência, quem pode ver a própria conta editaria a descrição de um
  // lançamento de outra pessoa só passando o tra_id na URL — pode_ver_conta_de()
  // autorizou a CONTA, não a transação.
  $editar_tra = request_get("editar_tra", "");
  if (!is_string($editar_tra) && !is_int($editar_tra)) $editar_tra = "";
  if (!ctype_digit((string)$editar_tra) || (int)$editar_tra <= 0) $editar_tra = "";
  if ($editar_tra !== "" && cestante_da_transacao($editar_tra) !== (int)$usr_id) $editar_tra = "";

  if (request_get("action", "") == ACAO_SALVAR)
  {
      $tra_salvar = request_get("tra_id", "");
      if (ctype_digit((string)$tra_salvar) && (int)$tra_salvar > 0
          && cestante_da_transacao($tra_salvar) === (int)$usr_id)
      {
          $ok = edita_descricao_transacao($tra_salvar,
                    request_get("tra_historico", ""), request_get("tra_comprovante", ""));

          adiciona_mensagem_status($ok ? MSG_TIPO_SUCESSO : MSG_TIPO_ERRO,
              $ok ? "Descrição do lançamento atualizada."
                  : "Não foi possível atualizar a descrição do lançamento.");
      }
      else
      {
          adiciona_mensagem_status(MSG_TIPO_ERRO, "Lançamento não pertence a esta conta.");
      }

      // POST-redirect-GET, pelo mesmo motivo da tela de pagamentos: volta para leitura
      // com o texto novo, e um F5 não repete a gravação.
      redireciona("conta_cestante.php?usr_id=" . urlencode($usr_id));
      exit();
  }

  top();

  // A tela não decide nada sobre o saldo por conta própria: resumo_do_extrato()
  // traduz o extrato — inclusive o null de "a consulta não rodou" — num estado, e
  // aqui só se escolhe o que desenhar para cada estado.
  $extrato = extrato_do_cestante($usr_id);
  $resumo  = resumo_do_extrato($extrato);

  // Editar descrição é ato de quem administra, não do cestante olhando a própria
  // conta: é a mesma regra de quem lança pagamento.
  $pode_editar = pode_lancar_pagamento();

  // Uma consulta recusada basta para a tela calar sobre dinheiro, seja a do nome, seja a
  // do extrato: as duas são "não deu para perguntar".
  $indisponivel = ($cestante['estado'] === 'indisponivel') || ($resumo['estado'] === 'indisponivel');
?>

	<legend>
	  Conta de <?php echo(h($cestante['nome'])); ?><?php
	    // O núcleo é o de HOJE (usuarios.usr_nuc). Quem trocou de núcleo tem entregas
	    // de mais de um no extrato, então isto identifica a pessoa, não a procedência
	    // das linhas. Some quando não há núcleo, em vez de imprimir um travessão solto.
	    if ($cestante['nucleo'] !== null) { ?><small class="text-muted"> · núcleo <?php echo(h($cestante['nucleo'])); ?></small><?php } ?>
	</legend>

<?php
  // O botão do lançamento avulso. A pergunta é pode_lancar_pagamento(), e não uma lista
  // de papéis escrita aqui: a mesma regra vive no menu e na tela de pagamentos, e três
  // cópias dela seriam duas a mais do que dá para manter de acordo.
  //
  // Fica FORA do ramo do extrato de propósito. Ele é navegação para outra tela, que faz
  // as próprias conferências; escondê-lo quando a consulta do extrato não roda tiraria o
  // caminho justamente de quem foi ali para resolver alguma coisa.
  if (pode_lancar_pagamento()) { ?>

	<div class="hidden-print" style="margin-bottom:10px;">
	  <a class="btn btn-info" href="conta_pagamentos.php?usr_id=<?php echo(h($usr_id)); ?>">
		<i class="glyphicon glyphicon-plus"></i> registrar pagamento deste cestante
	  </a>
	</div>

<?php }

  // Consulta que não rodou não vira número. A tela para aqui: sem situação, sem tabela
  // e, principalmente, sem dizer que está quite quem não chegou a ser consultado — a
  // mentira que o contrato de null de extrato_do_cestante() existe para impedir. Erro
  // visível é o resultado certo; silêncio não é.
  //
  // Comentário de PHP, e não de HTML: comentário de HTML é enviado ao navegador. O
  // primeiro rascunho desta tela explicava o ramo num <!-- --> que citava a expressão
  // "em dia", e ela saía no corpo da página justamente no caso em que a tela não pode
  // dizer isso. Raciocínio fica no arquivo.
  if ($indisponivel) { ?>

	<div class="alert alert-danger">
	  <strong>Não foi possível carregar o extrato desta conta.</strong><br>
	  Nenhum valor pode ser mostrado agora — inclusive o saldo. Tente de novo daqui a
	  alguns minutos e, se continuar assim, avise a coordenação.
	</div>

<?php } else { ?>

	<div class="row">
	  <div class="col-md-6">
		<strong>Situação:</strong>
		<?php if ($resumo['estado'] === 'devedor') { ?>
		  <span class="label label-danger" style="font-size:larger;">em aberto: R$ <?php echo(formata_moeda(-$resumo['saldo'])); ?></span>
		<?php } else if ($resumo['estado'] === 'credor') { ?>
		  <span class="label label-info" style="font-size:larger;">crédito: R$ <?php echo(formata_moeda($resumo['saldo'])); ?></span>
		<?php } else { ?>
		  <span class="label label-success" style="font-size:larger;">em dia</span>
		<?php } ?>
	  </div>
	</div>

	<?php
	  // O módulo entra em operação com o débito DERIVADO da entrega, que existe desde
	  // 2013, e sem os pagamentos que a Rede recebeu nesses anos — eles vivem na planilha
	  // e viram saldo de abertura no plano seguinte. Sem esta linha, o rótulo vermelho
	  // acima é lido como dívida atual, e não é. Medido em 2026-08-28 com a aritmética
	  // desta tela, núcleo 5 (Santa, 32 ativos): total em aberto R$ 1.237.019,51, maior
	  // devedor R$ 144.285,55. No núcleo 7 (Urca, 35 ativos), R$ 1.566.447,61 e
	  // R$ 120.071,96. Os números estão certos pelo contrato do módulo e errados como
	  // frase sobre a vida de alguém.
	  //
	  // Fica FORA do ramo do saldo e antes da tabela, para valer também para quem está
	  // "em dia" — um zero sem esta ressalva também mente.
	  //
	  // E NÃO leva hidden-print: o rótulo vermelho de :114 não tem, então esconder a
	  // ressalva na impressão entregaria ao cestante justamente o papel com o número
	  // grande e sem a frase que o explica.
	?>
	<div class="alert alert-warning" style="margin-top:10px;">
	  <strong>Estes valores ainda não incluem pagamentos anteriores.</strong><br>
	  O extrato mostra as entregas registradas no sistema desde o início e os pagamentos
	  lançados aqui. O que foi pago antes de o módulo entrar em operação ainda está na
	  planilha e será lançado como saldo de abertura. Até lá, o valor acima não é a
	  dívida desta pessoa.
	</div>

	<br>

	<table class="table table-striped table-bordered table-condensed">
	  <thead>
		<tr><th>Data</th><th>Histórico</th><th class="text-right">Valor</th><th class="text-right">Saldo</th></tr>
	  </thead>
	  <tbody>
	  <?php
	    // Mais recente em cima. A inversão é SÓ aqui: o saldo de cada linha é um
	    // acumulado somado em ordem crescente, e o resumo_do_extrato() lê o saldo
	    // atual como end($extrato)['saldo']. Inverter no modelo faria o resumo ler
	    // a linha mais antiga como se fosse a de hoje — e em silêncio, porque a
	    // lista continuaria ordenada, só que ao contrário.
	    foreach (array_reverse($extrato) as $linha) {
	      $em_edicao_tra = ($editar_tra !== "" && (int)$linha['tra_id'] === (int)$editar_tra);
	    ?>
		<tr<?php echo($em_edicao_tra ? ' class="info"' : ''); ?>>
		  <td><?php echo(date('d/m/Y', strtotime($linha['dt']))); ?></td>
		  <td>
			<?php echo(h($linha['historico'])); ?>
			<?php
			  // O aviso é sobre o valor PODER MUDAR, não sobre estar lançado no razão.
			  // Enquanto o prazo contábil não vence, a entrega ainda pode ser corrigida
			  // e o valor acompanha; depois disso ele está fechado, mesmo que ninguém
			  // tenha lançado nada ainda. Marcar toda linha derivada avisaria sobre 606
			  // das 679 chamadas do cestante 101, todas já encerradas.
			  if ($linha['situacao'] === 'derivado' && empty($linha['congelavel'])) { ?>
			  &nbsp;<span class="label label-default">a confirmar</span>
			<?php } ?>

			<?php
			  // Comprovante do lançamento. Vira link só quando é http/https —
			  // comprovante_como_link() recusa o resto, inclusive javascript:.
			  $comp      = trim((string)$linha['comprovante']);
			  $comp_link = comprovante_como_link($comp);
			  if ($comp_link !== '') { ?>
			  <br><small><a href="<?php echo(h($comp_link)); ?>" target="_blank" rel="noopener noreferrer"><i class="glyphicon glyphicon-link"></i> comprovante</a></small>
			<?php } else if ($comp !== '') { ?>
			  <br><small class="text-muted"><i class="glyphicon glyphicon-paperclip"></i> <?php echo(h($comp)); ?></small>
			<?php } ?>

			<?php
			  // O rastro. Só aparece quando alguém editou — e é a razão de a edição
			  // existir com carimbo: sem isto, o texto mudaria e a linha continuaria
			  // dizendo que foi registrada por quem a criou, na data original.
			  if (!empty($linha['editado_em'])) { ?>
			  <br><small class="text-muted"><em>descrição editada em <?php echo(h(date('d/m/Y', strtotime($linha['editado_em'])))); ?></em></small>
			<?php } ?>

			<?php
			  // Só lançamento gravado se edita. Linha derivada não é lançamento: ela
			  // existe porque a entrega existe, e não há texto próprio para corrigir.
			  if ($linha['situacao'] === 'gravado' && !$em_edicao_tra && $pode_editar) { ?>
			  &nbsp;<a class="btn btn-default btn-xs" href="conta_cestante.php?usr_id=<?php echo(h($usr_id)); ?>&amp;editar_tra=<?php echo(h($linha['tra_id'])); ?>" title="editar descrição e comprovante"><i class="glyphicon glyphicon-pencil"></i></a>
			<?php } ?>
		  </td>
		  <td class="text-right"><?php echo(formata_moeda($linha['valor'])); ?></td>
		  <td class="text-right"><?php echo(formata_moeda($linha['saldo'])); ?></td>
		</tr>

		<?php if ($em_edicao_tra) { ?>
		<tr class="info">
		  <td colspan="4">
		    <form method="post" action="conta_cestante.php">
		      <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />
		      <input type="hidden" name="usr_id" value="<?php echo(h($usr_id)); ?>" />
		      <input type="hidden" name="tra_id" value="<?php echo(h($linha['tra_id'])); ?>" />
		      <div class="row">
		        <div class="col-sm-12">
		          <label for="tra_historico">Descrição</label>
		          <input type="text" id="tra_historico" class="form-control" name="tra_historico" maxlength="200" value="<?php echo(h($linha['historico'])); ?>" autofocus />
		        </div>
		      </div>
		      <div class="row" style="margin-top:8px;">
		        <div class="col-sm-12">
		          <label for="tra_comprovante">Comprovante</label>
		          <input type="text" id="tra_comprovante" class="form-control" name="tra_comprovante" maxlength="300" value="<?php echo(h($comp)); ?>" placeholder="link do comprovante, ou como identificá-lo" />
		        </div>
		      </div>
		      <div class="row" style="margin-top:12px;">
		        <div class="col-sm-12 text-right">
		          <a class="btn btn-link" href="conta_cestante.php?usr_id=<?php echo(h($usr_id)); ?>">cancelar</a>
		          &nbsp;<button class="btn btn-success" type="submit"><i class="glyphicon glyphicon-ok glyphicon-white"></i> salvar descrição</button>
		        </div>
		      </div>
		      <p class="small text-muted" style="margin-top:8px;">
		        Só a descrição e o comprovante mudam. Valor, data e contas não se editam —
		        para corrigir dinheiro, lance um ajuste.
		      </p>
		    </form>
		  </td>
		</tr>
		<?php } ?>
	  <?php } ?>
	  <?php if (!count($extrato)) { ?>
		<tr><td colspan="4">Nenhum lançamento nesta conta.</td></tr>
	  <?php } ?>
	  </tbody>
	</table>

<?php } ?>

<?php footer(); ?>
