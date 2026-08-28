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


// Identidade estável da conta principal da Rede. Constante para o literal viver
// num lugar só: o mapa de reservadas abaixo e conta_da_rede() têm de falar da
// MESMA chave — separados, a reserva passaria a proteger uma string que ninguém
// usa, e em silêncio.
if (!defined('CONTA_CHAVE_REDE')) define('CONTA_CHAVE_REDE', 'rede_principal');


// Chaves estáveis reservadas: chave => único con_tipo que pode carregá-la.
//
// A busca de conta_da_rede() é por con_chave e NÃO filtra con_tipo — de
// propósito, para o tipo, que é editável, não virar identidade. O preço disso é
// que a coluna de identidade precisa da sua própria regra de coerência, senão
// 'rede_principal' cabe numa conta de núcleo e conta_da_rede() passa a devolver
// essa linha como a conta principal da Rede. É esta a regra.
//
// ATENÇÃO: esta guarda compara em PHP, byte a byte. Ela só vale se o `=` do SQL
// e a UNIQUE KEY concordarem com esse critério — por isso con_chave é
// `COLLATE utf8_bin` na DDL. Enquanto a coluna era utf8_general_ci, que dobra
// caixa E acento, as duas comparações discordavam sobre o que é "a mesma
// chave", e o exploit passava pela fresta: 'REDE_PRINCIPAL' numa conta de
// núcleo escapava desta guarda e mesmo assim era achado pela busca. strtolower()
// não resolveria — o acento também dobra. Trocar a colação da coluna faz as três
// comparações concordarem por construção, e não por convenção.
//
// O trim() abaixo cobre a única folga que sobra no utf8_bin: em MySQL 5.6 a
// comparação de VARCHAR ignora espaço no fim (PAD SPACE), então 'rede_principal '
// ainda casaria na busca. Trimando antes de comparar, a guarda recusa a mais,
// nunca a menos.
function chaves_reservadas()
{
	return array(CONTA_CHAVE_REDE => 'rede');
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
// Exigir não é excluir só para os campos livres. con_nome (rótulo de exibição) e
// con_chave (identidade estável) servem a qualquer tipo, então um núcleo com
// con_nome é legítimo. Já con_usr/con_nuc/con_forn dizem o que a conta é —
// vínculo de outro tipo é recusado, e todo campo informado passa pelo mesmo
// crivo, não apenas o exigido.
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

	// Chave reservada só nasce no tipo dono dela. con_chave é campo livre quanto
	// à forma — qualquer tipo pode ter uma chave —, mas não quanto ao conteúdo:
	// uma chave reservada carrega a identidade de uma conta específica, e quem a
	// busca não filtra con_tipo.
	$reservadas = chaves_reservadas();
	$chave_pedida = isset($campos['con_chave']) ? trim((string)$campos['con_chave']) : '';
	if (isset($reservadas[$chave_pedida]) && $reservadas[$chave_pedida] !== $tipo) return null;

	// Texto que qualquer tipo pode ter: con_nome rotula, con_chave identifica.
	$livres = array('con_nome', 'con_chave');

	$colunas = array('con_tipo');
	$valores = array(prep_para_bd($tipo));
	foreach (array('con_usr', 'con_nuc', 'con_forn', 'con_nome', 'con_chave') as $col)
	{
		if (!isset($campos[$col])) continue;

		// Vínculo de OUTRO tipo não entra. Os campos livres servem a qualquer
		// tipo, mas con_usr/con_nuc/con_forn dizem o que a conta é — um cestante
		// com con_forn mentiria sobre isso e ainda queimaria a UNIQUE KEY
		// conta_fornecedor, estourando depois num INSERT alheio.
		if (!in_array($col, $livres) && $col !== $campo) return null;

		// O mesmo crivo para TODO campo informado, não só o exigido: sem
		// sql_mode estrito, um '' em con_nuc viraria 0 e queimaria a UNIQUE
		// KEY conta_nucleo do mesmo jeito.
		if (in_array($col, $livres)) { if (trim((string)$campos[$col]) === '') return null; }
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
// A busca é só por con_usr: nem con_archive, nem con_tipo. A UNIQUE KEY
// conta_usuario é sobre con_usr sozinho, então a linha que tiver aquele con_usr
// É a conta daquele cestante — não existe segunda para desempatar.
//
// Filtrar por qualquer coluna editável reintroduz o defeito que a conta da Rede
// já teve: arquivada ou com o con_tipo trocado, a conta deixaria de ser achada,
// o INSERT seguinte bateria na UNIQUE, e conta_do_cestante devolveria null — o
// cestante ficaria SEM CONTA para sempre, com lanca_transacao recebendo null e
// deixando de gravar calado. Achar a linha, qualquer que seja o estado dela, é o
// comportamento certo.
//
// Consulta que FALHA sai por null e NÃO passa pelo cria_conta: não saber se a
// conta existe é diferente de saber que ela não existe, e criar por cima de uma
// pergunta sem resposta é pedir uma segunda conta para quem já tem uma. Hoje a
// UNIQUE KEY conta_usuario recusaria esse INSERT, mas contar com uma rede que o
// código não sabe que tem é o defeito, não a proteção.
//
// Esta guarda é DEFENSIVA e NÃO TEM TESTE. Quebrar só este SELECT deixando o
// INSERT irmão de pé exigiria DDL (que faz COMMIT implícito e derrubaria o
// rollback da suíte); prep_para_bd cita e escapa, então pelo argumento também
// não dá. A suíte cobre achou / não achou / criou — o ramo do erro fica sem
// prova, e quem mexer aqui não tem rede.
function conta_do_cestante($usr_id, $criar = false)
{
	$res = executa_sql("SELECT con_id FROM contas WHERE con_usr = " . prep_para_bd($usr_id));
	if (!$res) return null;              // a consulta não rodou: não dá para concluir nada
	if ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) return (int)$row['con_id'];

	if (!$criar) return null;

	return cria_conta('cestante', array('con_usr' => $usr_id));
}


// A Rede tem uma conta principal, que é a contraparte dos débitos de entrega.
// Contas pessoais que consolidam a Rede (con_tipo='rede' com con_nome próprio)
// são outras linhas, criadas pela administração.
//
// A busca é por con_chave, NÃO por con_nome. Rótulo é para ler na tela e a
// administração pode mudar; se a identidade dependesse dele, renomear a conta
// faria a chamada seguinte não achar nada e criar uma SEGUNDA conta principal —
// e aí os débitos de entrega passariam a ter duas contrapartes, sem nada no
// banco reclamando. A UNIQUE KEY conta_chave é o que garante que só existe uma.
//
// Pelo mesmo motivo a busca não filtra con_tipo: o tipo também é editável, e
// amarrar a identidade a ele traria de volta o defeito por outra porta. A chave
// é única na tabela inteira e basta sozinha.
//
// A consulta que falha sai por null sem passar pelo cria_conta, pelo mesmo motivo
// detalhado em conta_do_cestante — aqui quem seguraria o estrago seria a UNIQUE
// KEY conta_chave, e o ponto é justamente não depender dela.
function conta_da_rede()
{
	$chave = CONTA_CHAVE_REDE;
	$nome  = 'Rede Ecológica';

	$res = executa_sql("SELECT con_id FROM contas WHERE con_chave = " . prep_para_bd($chave));
	if (!$res) return null;              // idem conta_do_cestante: guarda defensiva, sem teste
	if ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) return (int)$row['con_id'];

	return cria_conta('rede', array('con_nome' => $nome, 'con_chave' => $chave));
}


// O débito é derivado enquanto a chamada pode mudar — é a mesma conta que o
// Quadro de Cestantes já faz (cestantes_quadro.php:356-369 monta o valor,
// :496-506 aplica a taxa). Não existe cópia gravada que possa divergir da
// entrega.
//
// O preço vem casado pela janela de validade do produto com a data da ENTREGA:
// produtos tem prod_auto_inc como chave e prod_id como chave NÃO única, ou seja,
// editar um produto cria linha nova e a antiga guarda o preço da época. Cobrar
// pelo preço de hoje uma entrega de 2014 seria cobrar outro valor.
//
// A passagem por chamadaprodutos não é decoração: o Quadro exige
// chaprod_disponibilidade <> '0' e a base de produção tem 75 linhas entregues
// sobre produto marcado indisponível na chamada. Sem este filtro o débito
// derivado divergiria da tela para esses cestantes — e a tela é a conferência
// que a Rede já faz. O JOIN aqui é interno onde o Quadro usa LEFT: nenhuma linha
// entregue da base está sem par em chamadaprodutos, e o próprio filtro de
// disponibilidade no WHERE já reduz o LEFT do Quadro a interno.
//
// GROUP BY cha_id agrupa por pedido sem precisar dizê-lo: a UNIQUE KEY
// (ped_usr, ped_cha) garante um pedido por cestante em cada chamada, então
// ped_usr_associado é constante dentro do grupo.
//
// 'congelavel' diz se o prazo contábil já passou. Quando passa, os insumos estão
// travados (entrega_cestante.php:22 e :44 recusam gravação fora do prazo) e o
// número pode virar lançamento sem risco de divergir depois.
//
// CONTRATO — null e array() NÃO são a mesma coisa aqui:
//   array()  a consulta rodou e o cestante não deve nada
//   null     a consulta NÃO rodou (servidor recusou, ou não há conexão)
// Quem chama tem de tratar os dois diferente. Vazio é uma afirmação sobre a
// dívida; null é a ausência de resposta. Num módulo de dinheiro, responder "não
// deve nada" a uma pergunta que não chegou a ser feita é a resposta mais perigosa
// que existe — a tela diria ao cestante que ele está quite. Diante de null a tela
// mostra erro, nunca zero. (Prova em test-financeiro.php: com ONLY_FULL_GROUP_BY
// esta consulta é recusada e a função devolve null.)
function debitos_derivados($usr_id)
{
	$usr_bd = prep_para_bd($usr_id);

	$sql = "SELECT c.cha_id, pt.prodt_nome, c.cha_dt_entrega, c.cha_taxa_percentual, p.ped_usr_associado, ";
	$sql.= "SUM(IF(p.ped_usr_associado = '0', pr.prod_valor_venda_margem, pr.prod_valor_venda) * pp.pedprod_entregue) valor_entregue, ";
	$sql.= "((c.cha_dt_prazo_contabil IS NOT NULL) AND (c.cha_dt_prazo_contabil <= NOW())) congelavel ";
	$sql.= "FROM pedidos p ";
	$sql.= "JOIN chamadas c ON c.cha_id = p.ped_cha ";
	$sql.= "JOIN pedidoprodutos pp ON pp.pedprod_ped = p.ped_id ";
	$sql.= "JOIN chamadaprodutos cp ON cp.chaprod_cha = c.cha_id AND cp.chaprod_prod = pp.pedprod_prod ";
	$sql.= "JOIN produtos pr ON pr.prod_id = pp.pedprod_prod ";
	$sql.= "  AND pr.prod_ini_validade <= c.cha_dt_entrega AND pr.prod_fim_validade >= c.cha_dt_entrega ";
	$sql.= "LEFT JOIN produtotipos pt ON pt.prodt_id = c.cha_prodt ";
	$sql.= "WHERE p.ped_usr = $usr_bd AND p.ped_fechado = 1 ";
	$sql.= "AND cp.chaprod_disponibilidade <> '0' ";
	$sql.= "AND pp.pedprod_entregue > 0 ";
	$sql.= "GROUP BY c.cha_id ";
	$sql.= "ORDER BY c.cha_dt_entrega";

	$res = executa_sql($sql);
	if (!$res) return null;              // consulta não rodou — ver o CONTRATO acima

	$linhas = array();

	while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))
	{
		$entregue = round((float)$row['valor_entregue'], 2);
		// não-associado já paga o preço com margem embutido; taxa só para associado
		$taxa = ($row['ped_usr_associado'] == '0') ? 0.0
		      : round($entregue * (float)$row['cha_taxa_percentual'], 2);

		$linhas[] = array(
			'cha_id'         => $row['cha_id'],
			'prodt_nome'     => $row['prodt_nome'],
			'cha_dt_entrega' => $row['cha_dt_entrega'],
			'valor_entregue' => $entregue,
			'taxa'           => $taxa,
			'valor'          => round($entregue + $taxa, 2),
			'congelavel'     => ((int)$row['congelavel'] === 1),
		);
	}

	return $linhas;
}
