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

  top();

  // A tela não decide nada sobre o saldo por conta própria: resumo_do_extrato()
  // traduz o extrato — inclusive o null de "a consulta não rodou" — num estado, e
  // aqui só se escolhe o que desenhar para cada estado.
  $extrato = extrato_do_cestante($usr_id);
  $resumo  = resumo_do_extrato($extrato);

  // Uma consulta recusada basta para a tela calar sobre dinheiro, seja a do nome, seja a
  // do extrato: as duas são "não deu para perguntar".
  $indisponivel = ($cestante['estado'] === 'indisponivel') || ($resumo['estado'] === 'indisponivel');
?>

	<legend>Conta de <?php echo(h($cestante['nome'])); ?></legend>

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
	?>
	<div class="alert alert-warning hidden-print" style="margin-top:10px;">
	  <strong>Estes valores ainda não incluem pagamentos anteriores.</strong><br>
	  O extrato mostra as entregas registradas no sistema desde o início e os pagamentos
	  lançados aqui. O que foi pago antes de o módulo entrar em operação ainda está na
	  planilha e será lançado como saldo de abertura. Até lá, o saldo abaixo não é o que
	  você deve.
	</div>

	<br>

	<table class="table table-striped table-bordered table-condensed">
	  <thead>
		<tr><th>Data</th><th>Histórico</th><th class="text-right">Valor</th><th class="text-right">Saldo</th></tr>
	  </thead>
	  <tbody>
	  <?php foreach ($extrato as $linha) { ?>
		<tr>
		  <td><?php echo(date('d/m/Y', strtotime($linha['dt']))); ?></td>
		  <td>
			<?php echo(h($linha['historico'])); ?>
			<?php
			  // entrega ainda não lançada no razão: o valor sai da entrega registrada
			  if ($linha['situacao'] === 'derivado') { ?>
			  &nbsp;<span class="label label-default">a confirmar</span>
			<?php } ?>
		  </td>
		  <td class="text-right"><?php echo(formata_moeda($linha['valor'])); ?></td>
		  <td class="text-right"><?php echo(formata_moeda($linha['saldo'])); ?></td>
		</tr>
	  <?php } ?>
	  <?php if (!count($extrato)) { ?>
		<tr><td colspan="4">Nenhum lançamento nesta conta.</td></tr>
	  <?php } ?>
	  </tbody>
	</table>

<?php } ?>

<?php footer(); ?>
