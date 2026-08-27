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


// Invariante do razão: toda transação tem exatamente DUAS pernas E elas somam
// zero. Devolve os tra_id que violam. Uma perna só, três pernas ou nenhuma são
// tão quebradas quanto um par que não fecha — por isso a contagem entra junto
// com a soma.
//
// A varredura parte de transacoes, não de lancamentos: agrupando as pernas, uma
// transação sem perna nenhuma não formaria grupo e escaparia calada — e esse
// estado é alcançável de verdade (INSERT da transação que comita antes de a perna
// falhar). O LEFT JOIN faz ela aparecer com COUNT 0 e SUM NULL, daí o COALESCE.
//
// O caminho contrário — perna apontando para transação inexistente — não precisa
// de varredura: a FK lan_tra -> transacoes já o impede.
//
// Se esta lista não estiver vazia, dinheiro vazou em algum lugar.
function transacoes_desbalanceadas()
{
	$sql = "SELECT t.tra_id FROM transacoes t LEFT JOIN lancamentos l ON l.lan_tra = t.tra_id ";
	$sql.= "GROUP BY t.tra_id ";
	$sql.= "HAVING COUNT(l.lan_id) <> 2 OR ROUND(COALESCE(SUM(l.lan_valor),0),2) <> 0";
	$fora = array();
	$res = executa_sql($sql);
	if ($res) while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) $fora[] = $row['tra_id'];

	return $fora;
}


// Toda conta nasce por aqui. A coerência entre con_tipo e o campo de vínculo é
// regra que o banco não consegue impor: o MySQL 5.6 aceita CHECK na DDL e o
// ignora em silêncio, e com sql_mode vazio um '' vira 0 sem reclamar. Porta
// única, então, em vez da mesma checagem repetida em cada chamador.
//
// Cada tipo exige o seu vínculo — e 'rede' exige con_nome porque, sem rótulo,
// duas contas da Rede ficam indistinguíveis, e são justamente as contas
// pessoais que consolidam a Rede.
//
// Exigir não é excluir só para o rótulo: con_nome serve a qualquer tipo, então
// um núcleo com con_nome é legítimo. Já con_usr/con_nuc/con_forn dizem o que a
// conta é — vínculo de outro tipo é recusado, e todo campo informado passa pelo
// mesmo crivo, não apenas o exigido.
//
// Devolve o con_id, ou null se o tipo for desconhecido, o vínculo faltar ou o
// INSERT falhar — nunca o id_inserido() de um INSERT que não aconteceu.
function cria_conta($tipo, $campos = array())
{
	$vinculo = array(
		'cestante' => 'con_usr',
		'nucleo'   => 'con_nuc',
		'produtor' => 'con_forn',
		'rede'     => 'con_nome',
	);

	if (!isset($vinculo[$tipo])) return null;

	$campo = $vinculo[$tipo];
	if (!isset($campos[$campo])) return null;

	$colunas = array('con_tipo');
	$valores = array(prep_para_bd($tipo));
	foreach (array('con_usr', 'con_nuc', 'con_forn', 'con_nome') as $col)
	{
		if (!isset($campos[$col])) continue;

		// Vínculo de OUTRO tipo não entra. con_nome é rótulo e serve a qualquer
		// tipo, mas con_usr/con_nuc/con_forn dizem o que a conta é — um cestante
		// com con_forn mentiria sobre isso e ainda queimaria a UNIQUE KEY
		// conta_fornecedor, estourando depois num INSERT alheio.
		if ($col !== 'con_nome' && $col !== $campo) return null;

		// O mesmo crivo para TODO campo informado, não só o exigido: sem
		// sql_mode estrito, um '' em con_nuc viraria 0 e queimaria a UNIQUE
		// KEY conta_nucleo do mesmo jeito.
		if ($col === 'con_nome') { if (trim((string)$campos[$col]) === '') return null; }
		else if (!is_numeric($campos[$col]) || $campos[$col] <= 0)  return null;

		$colunas[] = $col;
		$valores[] = prep_para_bd($campos[$col]);
	}

	$sql = "INSERT INTO contas (" . implode(", ", $colunas) . ") VALUES (" . implode(", ", $valores) . ")";
	if (!executa_sql($sql)) return null;

	return id_inserido();
}


// A conta nasce no primeiro lançamento, não ao abrir a tela: assim a tabela não
// enche de contas vazias de gente que nunca movimentou. As telas listam todos os
// cestantes do núcleo, tenham conta ou não — quem não tem aparece com saldo zero.
//
// A busca não filtra con_archive de propósito: a UNIQUE KEY conta_usuario é só
// sobre con_usr, então passar por cima de uma conta arquivada levaria a um
// INSERT que a chave recusa. Achar a arquivada é o comportamento certo.
function conta_do_cestante($usr_id, $criar = false)
{
	$res = executa_sql("SELECT con_id FROM contas WHERE con_tipo = 'cestante' AND con_usr = " . prep_para_bd($usr_id));
	if ($res && $row = mysqli_fetch_array($res, MYSQLI_ASSOC)) return (int)$row['con_id'];

	if (!$criar) return null;

	return cria_conta('cestante', array('con_usr' => $usr_id));
}


// A Rede tem uma conta principal, que é a contraparte dos débitos de entrega.
// Contas pessoais que consolidam a Rede (con_tipo='rede' com con_nome próprio)
// são outras linhas, criadas pela administração.
//
// O nome fica numa variável só, usada na busca e na criação: literal repetido
// deixaria os dois se separarem, e aí a conta principal nasceria de novo a cada
// chamada.
function conta_da_rede()
{
	$nome = 'Rede Ecológica';

	$res = executa_sql("SELECT con_id FROM contas WHERE con_tipo = 'rede' AND con_nome = " . prep_para_bd($nome));
	if ($res && $row = mysqli_fetch_array($res, MYSQLI_ASSOC)) return (int)$row['con_id'];

	return cria_conta('rede', array('con_nome' => $nome));
}
