<?php
// Seleção e montagem do lembrete de pedidos salvos e não enviados.
// Usado pelo cron (cron_lembrete.php). Mantido fora de common.inc.php porque
// nenhuma página web precisa disso.


// Pedidos que merecem um lembrete agora: têm produto pedido, não foram enviados
// e a chamada fecha dentro da janela.
//
// A janela é a mesma usada para decidir se o lembrete já foi dado: um pedido é
// lembrado no máximo uma vez por chamada, e um prazo esticado pelo administrador
// rearma o lembrete sozinho (porque a comparação é relativa a cha_dt_max, não a
// um intervalo fixo a partir de agora).
function pedidos_a_lembrar($janela_horas)
{
	$janela = (int)$janela_horas;

	$sql = "SELECT p.ped_id, p.ped_usr, u.usr_email, u.usr_nome_curto, pt.prodt_nome, ";
	$sql.= "DATE_FORMAT(c.cha_dt_max,'%d/%m/%Y %H:%i') cha_dt_max, ";
	$sql.= "DATE_FORMAT(c.cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, ";
	$sql.= "SUM(pp.pedprod_quantidade * IF(p.ped_usr_associado=1, pr.prod_valor_venda, pr.prod_valor_venda_margem)) valor ";
	$sql.= "FROM pedidos p ";
	$sql.= "JOIN chamadas c ON c.cha_id = p.ped_cha ";
	$sql.= "JOIN usuarios u ON u.usr_id = p.ped_usr ";
	// INNER JOIN com quantidade > 0: quem não pediu nada simplesmente não aparece
	$sql.= "JOIN pedidoprodutos pp ON pp.pedprod_ped = p.ped_id AND pp.pedprod_quantidade > 0 ";
	// LEFT JOIN no produto: item órfão zera a parcela do valor, mas não faz o
	// pedido inteiro sumir do lembrete
	$sql.= "LEFT JOIN produtos pr ON pr.prod_id = pp.pedprod_prod ";
	$sql.= "LEFT JOIN produtotipos pt ON pt.prodt_id = c.cha_prodt ";
	$sql.= "WHERE (p.ped_fechado = 0 OR p.ped_fechado IS NULL) ";
	$sql.= "AND c.cha_dt_max > NOW() ";
	$sql.= "AND c.cha_dt_max <= NOW() + INTERVAL $janela HOUR ";
	$sql.= "AND (p.ped_dt_lembrete IS NULL OR p.ped_dt_lembrete < c.cha_dt_max - INTERVAL $janela HOUR) ";
	$sql.= "AND u.usr_archive = 0 ";
	$sql.= "AND u.usr_email IS NOT NULL AND u.usr_email <> '' ";
	$sql.= "GROUP BY p.ped_id ";

	$linhas = array();
	$res = executa_sql($sql);
	if (!$res) return $linhas;

	while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))
	{
		$linhas[] = $row;
	}
	return $linhas;
}


// Texto do lembrete. Em texto puro, como os demais e-mails do sistema.
function monta_mensagem_lembrete($linha)
{
	$msg = "Olá, " . $linha['usr_nome_curto'] . ".\n\n";
	$msg.= "Você tem um pedido salvo no sistema que ainda não foi enviado.\n";
	$msg.= "Pedidos não enviados não entram na chamada e não chegam aos produtores.\n\n";
	$msg.= "Pedido: " . $linha['prodt_nome'] . " - entrega em " . $linha['cha_dt_entrega'] . "\n";
	$msg.= "Valor salvo: R$ " . formata_moeda($linha['valor']) . "\n";
	$msg.= "PRAZO PARA ENVIAR: " . $linha['cha_dt_max'] . "\n\n";
	$msg.= "Para enviar, acesse o sistema, abra o pedido e clique em \"salvar e ENVIAR pedido\":\n";
	$msg.= URL_ABSOLUTA . "/pedido.php?action=" . ACAO_EXIBIR_LEITURA . "&ped_id=" . $linha['ped_id'] . "\n\n";
	$msg.= "Se você não deseja pedir nesta chamada, pode ignorar este aviso.\n";

	return $msg;
}


// Aviso imediato de cancelamento. O cron roda de madrugada e não alcança quem
// cancela durante o dia do fechamento; este e-mail cobre essa janela e serve de
// registro de que a pessoa foi avisada de que o pedido deixou de valer.
function monta_mensagem_cancelamento($nome_curto, $prodt_nome, $cha_dt_entrega, $cha_dt_max, $ped_id)
{
	$msg = "Olá, " . $nome_curto . ".\n\n";
	$msg.= "Você cancelou o envio do seu pedido de " . $prodt_nome . " (entrega em " . $cha_dt_entrega . ").\n\n";
	$msg.= "Os produtos que você tinha escolhido seguem salvos, mas o pedido não será considerado\n";
	$msg.= "nesta chamada enquanto não for enviado de novo.\n\n";
	$msg.= "Se o cancelamento foi sem querer, ainda dá tempo: abra o pedido e clique em\n";
	$msg.= "\"salvar e ENVIAR pedido\" até " . $cha_dt_max . ".\n";
	$msg.= URL_ABSOLUTA . "/pedido.php?action=" . ACAO_EXIBIR_LEITURA . "&ped_id=" . $ped_id . "\n\n";
	$msg.= "Se você cancelou de propósito, não precisa fazer nada.\n";

	return $msg;
}


// Registra que o lembrete foi dado, para que a segunda passada da madrugada (e
// qualquer execução manual) não reenvie.
function marca_lembrete_enviado($ped_id)
{
	// ped_dt_atualizacao é ON UPDATE CURRENT_TIMESTAMP: sem reatribuir o valor
	// atual, registrar o lembrete faria o pedido aparecer para o cestante como
	// editado de madrugada.
	$sql = "UPDATE pedidos SET ped_dt_lembrete = NOW(), ped_dt_atualizacao = ped_dt_atualizacao ";
	$sql.= "WHERE ped_id = " . prep_para_bd($ped_id);

	return executa_sql($sql) ? true : false;
}
