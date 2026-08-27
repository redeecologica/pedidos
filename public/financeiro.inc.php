<?php
// Razão do módulo financeiro: contas, transações e saldo.
//
// Regra de sinal: saldo de uma conta é a soma de lan_valor.
//   negativo = deve ao sistema · positivo = tem a receber
// É a mesma convenção da planilha que este módulo substitui.


// Grava uma transação com as duas pernas. A conta debitada recebe -valor, a
// creditada +valor, de modo que toda transação soma zero.
//
// $extras aceita: cha (chamada de procedência), comprovante, obs.
// Devolve o tra_id, ou null se algo falhar — nesse caso nada é gravado.
function lanca_transacao($dt, $tipo, $con_debitada, $con_creditada, $valor, $historico, $extras = array())
{
	$valor = round((float)$valor, 2);
	if ($valor <= 0) return null;                       // valor sempre positivo; o sinal vem das pernas
	if ($con_debitada == $con_creditada) return null;   // transferir para si mesmo não é movimento

	$cha          = isset($extras['cha'])          ? prep_para_bd($extras['cha'])          : "NULL";
	$comprovante  = isset($extras['comprovante'])  ? prep_para_bd($extras['comprovante'])  : "NULL";
	$obs          = isset($extras['obs'])          ? prep_para_bd($extras['obs'])          : "NULL";
	$usr          = isset($_SESSION['usr.id'])     ? prep_para_bd($_SESSION['usr.id'])     : "0";

	// MySQL não aninha transação: um BEGIN dentro de outra faz COMMIT implícito
	// da externa. Os testes envolvem o fixture numa transação para desfazer no
	// fim, então só abrimos a nossa quando não há uma aberta — em produção
	// ninguém envolve, e as duas pernas seguem atômicas.
	global $conn_link, $financeiro_em_transacao;
	$nossa = empty($financeiro_em_transacao);
	if ($nossa) { mysqli_begin_transaction($conn_link); $financeiro_em_transacao = true; }

	$sql = "INSERT INTO transacoes (tra_dt, tra_tipo, tra_cha, tra_historico, tra_comprovante, tra_obs, tra_usr_registro) ";
	$sql.= "VALUES (" . prep_para_bd($dt) . ", " . prep_para_bd($tipo) . ", $cha, ";
	$sql.= prep_para_bd($historico) . ", $comprovante, $obs, $usr)";

	if (!executa_sql($sql)) { if ($nossa) { mysqli_rollback($conn_link); $financeiro_em_transacao = false; } return null; }
	$tra_id = id_inserido();

	// as duas pernas: quem é debitada perde, quem é creditada ganha
	$pernas = array(
		array($con_debitada,  -$valor),
		array($con_creditada,  $valor),
	);
	foreach ($pernas as $perna)
	{
		$sql = "INSERT INTO lancamentos (lan_tra, lan_con, lan_valor) VALUES (";
		$sql.= prep_para_bd($tra_id) . ", " . prep_para_bd($perna[0]) . ", " . prep_para_bd($perna[1]) . ")";
		if (!executa_sql($sql)) { if ($nossa) { mysqli_rollback($conn_link); $financeiro_em_transacao = false; } return null; }
	}

	if ($nossa) { mysqli_commit($conn_link); $financeiro_em_transacao = false; }

	return $tra_id;
}


// Saldo é sempre somado, nunca guardado: o núcleo lança na segunda um pagamento
// feito na sexta, e saldo gravado obrigaria a reescrever tudo que veio depois.
function saldo_da_conta($con_id)
{
	$sql = "SELECT COALESCE(SUM(lan_valor),0) saldo FROM lancamentos WHERE lan_con = " . prep_para_bd($con_id);
	$res = executa_sql($sql);
	if (!$res) return 0.0;
	$row = mysqli_fetch_array($res, MYSQLI_ASSOC);

	return (float)$row['saldo'];
}


// Invariante do razão: toda transação soma zero. Devolve os tra_id que violam.
// Se esta lista não estiver vazia, dinheiro vazou em algum lugar.
function transacoes_desbalanceadas()
{
	$sql = "SELECT lan_tra FROM lancamentos GROUP BY lan_tra HAVING ROUND(SUM(lan_valor),2) <> 0";
	$fora = array();
	$res = executa_sql($sql);
	if ($res) while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) $fora[] = $row['lan_tra'];

	return $fora;
}
