<?php
// Lembrete de pedidos salvos e não enviados, na véspera do fechamento da chamada.
//
// Agendamento (painel da Locaweb; em hospedagem compartilhada o cron só roda
// das 02:00 às 03:00, por isso as duas passadas dentro da mesma hora — a
// segunda só reenvia o que falhou na primeira):
//
//   15,45 2 * * * /usr/bin/php8.4 <DOCROOT>/cron_lembrete.php >/dev/null
//
// (<DOCROOT> = caminho absoluto do docroot no servidor; a linha pronta está no
// runbook de deploy, fora do repositório.)
//
// Use /usr/bin/php8.4: é a versão que a produção serve, e a partir do 8.1 o
// mysqli lança exceção em vez de devolver false — outra versão mudaria o
// contrato de erro deste código.
//
// Conferir a lista sem enviar nada:
//   /usr/bin/php8.4 cron_lembrete.php --simulacao
//
// Silencioso quando dá certo: o crontab tem MAILTO, então qualquer saída vira
// e-mail. Só fala em caso de problema (STDERR) ou em simulação.

// O arquivo mora no docroot: por HTTP ele não existe.
if (PHP_SAPI !== 'cli') {
	header('HTTP/1.1 404 Not Found');
	exit;
}

require __DIR__ . "/common.inc.php";
require __DIR__ . "/lembrete.inc.php";

// Quanto antes do fechamento o lembrete sai. Precisa ser maior que o intervalo
// entre execuções (24h), senão prazos caem no vão entre duas passadas e ninguém
// é avisado.
define('JANELA_HORAS', 30);

$simulacao = isset($argv) && in_array('--simulacao', $argv);

// Em CLI a URL vem do URL_SISTEMA do settings.php, que é por ambiente e não sobe
// no deploy. Sem ela o e-mail sairia com link quebrado — melhor não sair.
if (!preg_match('~^https?://[^/\s]+~', URL_ABSOLUTA)) {
	fwrite(STDERR, "cron_lembrete: URL_SISTEMA não definida em settings.php; nenhum e-mail enviado.\n");
	exit(1);
}

$erros = 0;

foreach (pedidos_a_lembrar(JANELA_HORAS) as $linha)
{
	if ($simulacao) {
		echo "lembraria ped_id=" . $linha['ped_id'] . " " . $linha['usr_email'] .
		     " prazo=" . $linha['cha_dt_max'] . " valor=" . formata_moeda($linha['valor']) . "\n";
		continue;
	}

	$enviou = envia_email_cestante(
		$linha['ped_usr'],
		"Seu pedido de " . $linha['prodt_nome'] . " ainda NÃO foi enviado",
		"",
		monta_mensagem_lembrete($linha));

	if ($enviou) {
		// só marca depois do envio: se o e-mail falhar, a passada seguinte tenta de novo
		marca_lembrete_enviado($linha['ped_id']);
	} else {
		fwrite(STDERR, "cron_lembrete: falha ao enviar para ped_id=" . $linha['ped_id'] .
		               " (" . $linha['usr_email'] . ")\n");
		$erros++;
	}
}

exit($erros > 0 ? 1 : 0);
