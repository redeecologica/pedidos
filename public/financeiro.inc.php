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
//
// CONTRATO — null e 0.0 NÃO são a mesma coisa, como no resto do módulo:
//   float  a consulta rodou; este é o saldo, e 0.0 quer dizer conta zerada
//   null   a consulta NÃO rodou (servidor recusou, ou não há conexão)
// Esta função era a última da família ainda devolvendo 0.0 no erro — o mesmo
// anti-padrão que debitos_derivados(), extrato_do_cestante() e contas_de_destino() já
// tinham perdido. Num módulo de dinheiro, "não deu para perguntar" respondido como
// "está zerado" é a mentira mais cara que existe: numa tela de caixa ela vira
// "não deve nada".
//
// Trocado agora porque agora é barato: conferido em 2026-08-28, a função NÃO tem
// chamador vivo fora da suíte — a única menção em public/ é o comentário do
// conta_pagamentos.php explicando por que ela não é usada lá (o saldo daquela tela
// precisa do débito derivado, que não está em `lancamentos`). As telas de caixa do
// plano seguinte serão as primeiras chamadoras, e elas já nascem tendo de tratar o
// null. Depois delas, mudar o contrato custaria reescrever consumidor.
//
// Quem chamar precisa distinguir os dois: `if (!$saldo)` trata null e 0.0 igual e
// devolve o defeito por outra porta. A guarda tem teste, com a consulta quebrada de
// propósito pela mesma sombra de TEMPORARY TABLE que o resto da suíte usa.
function saldo_da_conta($con_id)
{
	$sql = "SELECT COALESCE(SUM(lan_valor),0) saldo FROM lancamentos WHERE lan_con = " . prep_para_bd($con_id);
	$res = executa_sql($sql);
	if (!$res) return null;              // ver o CONTRATO acima
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

// Data em que o módulo entra em operação. Entrega anterior a ela NÃO vira débito
// derivado: fica na planilha e entra depois como uma linha de reconciliação por
// cestante, com o saldo que o núcleo informar.
//
// Sem esse piso o extrato mostra a dívida desde 2013 e a soma não quer dizer nada:
// o cestante 101 aparece devendo R$ 120.071,96, e o painel de um núcleo, -1,2 milhão.
// Números certos pelo contrato do módulo e falsos como frase sobre a vida de alguém.
//
// !!! PROVISÓRIA !!! 2026-05-01 é valor de TESTE LOCAL, escolhido por cair limpo
// no calendário (o ciclo de abril fecha em 28/04 e o de maio só entrega em 07/05;
// conferido em produção: zero chamadas entregues antes do dia 1º ainda abertas
// depois dele). A data de produção é decisão da Rede e ainda não foi tomada —
// NÃO SUBIR sem trocar isto.
//
// Trocar a data é livre enquanto a reconciliação não for lançada: o débito é
// derivado, nada fica gravado, e o número se refaz sozinho. Depois de lançada, mover
// o piso abre buraco ou sobreposição.
if (!defined('DATA_CORTE_FINANCEIRO')) define('DATA_CORTE_FINANCEIRO', '2026-05-01 00:00:00');


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
// A guarda tem teste, e ele quebra o SELECT de propósito: a suíte sombreia
// `contas` com uma TEMPORARY TABLE sem a coluna con_id, o que faz o servidor
// recusar esta busca (ERROR 1054) e deixa de pé o INSERT do cria_conta, que não
// menciona con_id. CREATE e DROP TEMPORARY TABLE não fazem COMMIT implícito
// (ALTER numa temporária faz — só estes dois é que são exceção), então a
// transação da suíte sobrevive. A mesma sombra cobre conta_da_rede().
//
// Sem esta guarda o INSERT entra e a função devolve 0, não null — id_inserido()
// numa tabela sem AUTO_INCREMENT devolve 0. Por isso a asserção lá é `=== null`:
// escrita como `!$conta` ela passaria também na versão defeituosa.
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
// KEY conta_chave, e o ponto é justamente não depender dela. A sombra sem con_id
// descrita lá recusa esta busca também, e o teste confere as duas de uma vez.
function conta_da_rede()
{
	$chave = CONTA_CHAVE_REDE;
	$nome  = 'Rede Ecológica';

	$res = executa_sql("SELECT con_id FROM contas WHERE con_chave = " . prep_para_bd($chave));
	if (!$res) return null;              // idem conta_do_cestante, e coberta pelo mesmo teste
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
	$sql.= "AND c.cha_dt_entrega >= " . prep_para_bd(DATA_CORTE_FINANCEIRO) . " ";
	$sql.= "GROUP BY c.cha_id ";
	// O desempate por cha_id não é enfeite. Sem ele o servidor devolve os empates de
	// data na ordem que quiser, e ele exerce essa liberdade: na cópia de produção o
	// cestante 379 tem 411 linhas derivadas com 88 datas empatadas, e a mesma consulta
	// devolveu três ordens diferentes em três corridas sobre dados que não mudaram.
	// Quem lê isso no extrato vê a coluna de saldo mudar entre um carregamento e outro,
	// para o mesmo cestante e os mesmos dados. O saldo final comuta, então não é erro
	// de dinheiro — é um extrato que não se repete, e vai para uma tela.
	//
	// O conserto mora AQUI, e não no comparador de extrato_do_cestante(): assim vale
	// para todo chamador de uma vez, e a estabilidade do usort de lá carrega o extrato
	// de graça. cha_id é a PK, então o desempate é total.
	$sql.= "ORDER BY c.cha_dt_entrega, c.cha_id";

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


// Extrato do cestante: uma lista só, com o débito que ainda é DERIVADO da entrega
// e o lançamento que já está GRAVADO no razão, em ordem de data e com o saldo
// somado linha a linha.
//
// Cada linha traz:
//   dt         data do fato (cha_dt_entrega no derivado, tra_dt no gravado)
//   historico  o texto que o cestante lê
//   valor      com sinal, na regra do módulo: negativo deve, positivo tem a receber
//   saldo      o acumulado até esta linha, inclusive
//   situacao   'derivado' ou 'gravado'
//   tra_id     a transação, null quando derivado
//   cha        a chamada de procedência, null quando não há
//
// O SALDO É SOMADO AQUI, na leitura, e nunca gravado. O núcleo lança na segunda um
// pagamento feito na sexta; com saldo gravado, cada lançamento retroativo obrigaria
// a reescrever todas as linhas seguintes — e uma reescrita que falhasse no meio
// deixaria o extrato mentindo em silêncio, que é pior do que não ter extrato. O
// caso do lançamento retroativo tem teste.
//
// CONTRATO — null e array() NÃO são a mesma coisa, pelo mesmo motivo de
// debitos_derivados():
//   array()  as consultas rodaram e não há nada a mostrar
//   null     alguma consulta NÃO rodou
// São DUAS consultas, e as DUAS saem por null: a do débito derivado e a de
// lançamentos. Cada uma tem a sua alavanca na suíte, porque uma guarda sem teste é
// uma guarda que alguém apaga. Diante de null a tela diz "não deu para carregar";
// extrato vazio diria ao cestante que ele está quite, e um extrato só com os
// derivados — se a segunda consulta falhasse calada — cobraria dele o que já foi
// pago. Nenhuma das duas mentiras é aceitável num módulo de dinheiro.
function extrato_do_cestante($usr_id)
{
	$derivados = debitos_derivados($usr_id);
	if ($derivados === null) return null;        // a consulta não rodou — ver o CONTRATO

	// Lançamento gravado e débito já materializado saem da MESMA varredura. Separá-las
	// em duas consultas abriria a chance de as duas discordarem sobre o que já virou
	// lançamento — e a que perdesse a discussão duplicaria ou sumiria com uma linha.
	//
	// O recorte é por c.con_usr, com JOIN em contas, e não por um con_id vindo de
	// conta_do_cestante(): o null daquela função junta "não tem conta" com "a
	// consulta foi recusada", e é exatamente essa diferença que o extrato precisa
	// preservar. Cestante sem conta não tem lançamento nenhum e sai daqui com zero
	// linhas, sem erro. A regra de busca é a MESMA de conta_do_cestante() — só
	// con_usr, sem con_tipo e sem con_archive, que são editáveis — e quem garante
	// uma conta só por cestante é a UNIQUE KEY conta_usuario. Se aquela regra mudar,
	// esta tem de mudar junto.
	//
	// O recorte por cestante é o que mantém a consulta barata: conta_usuario resolve
	// contas numa linha, lancamento_conta traz só os lançamentos dela e a PK de
	// transacoes fecha o par.
	$sql = "SELECT t.tra_id, t.tra_dt, t.tra_tipo, t.tra_cha, t.tra_historico, l.lan_valor ";
	$sql.= "FROM contas c ";
	$sql.= "JOIN lancamentos l ON l.lan_con = c.con_id ";
	$sql.= "JOIN transacoes t ON t.tra_id = l.lan_tra ";
	$sql.= "WHERE c.con_usr = " . prep_para_bd($usr_id) . " ";
	$sql.= "ORDER BY t.tra_dt, t.tra_id";

	$res = executa_sql($sql);
	if (!$res) return null;                      // idem — ver o CONTRATO

	$gravadas       = array();
	$materializadas = array();

	while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))
	{
		// A entrega que já virou lançamento sai da lista de derivados: senão o
		// cestante veria a MESMA entrega duas vezes e o saldo dobraria a dívida.
		if ($row['tra_tipo'] === 'debito_entrega' && $row['tra_cha'] !== null)
			$materializadas[(string)$row['tra_cha']] = true;

		$gravadas[] = array(
			'dt'        => $row['tra_dt'],
			// tra_historico é NULLABLE, e o lado derivado sempre monta uma string: sem o
			// cast, o mesmo campo sairia com dois tipos diferentes na mesma lista e quem
			// descobriria seria a tela.
			'historico' => (string)$row['tra_historico'],
			'valor'     => (float)$row['lan_valor'],
			'situacao'  => 'gravado',
			// linha já lançada não tem nada pendente de fechamento
			'congelavel' => true,
			'tra_id'    => (int)$row['tra_id'],
			'cha'       => ($row['tra_cha'] === null) ? null : (int)$row['tra_cha'],
		);
	}

	$linhas = array();

	foreach ($derivados as $d)
	{
		if (isset($materializadas[(string)$d['cha_id']])) continue;   // já é lançamento

		// debitos_derivados() devolve o valor POSITIVO — é quanto a entrega custou.
		// Quem o põe na regra de sinal do razão é o extrato: entrega recebida é
		// dívida, logo negativa. Sem a inversão, entrega e pagamento somariam no
		// mesmo sentido e o saldo cresceria a cada entrega.
		//
		// prodt_nome vem de um LEFT JOIN, mas NULL não chega aqui: cha_prodt é NOT
		// NULL com FK para produtotipos, então o par sempre casa. O que chega é nome
		// EM BRANCO — a coluna é NOT NULL e, com sql_mode vazio, um '' entra sem
		// reclamação. Concatenado seco viraria 'entrega ' com espaço solto e sem
		// dizer de quê; sem nome o extrato diz só 'entrega', e a identidade da
		// chamada fica no campo 'cha', que é onde ela é útil para quem for lançar.
		$nome = trim((string)$d['prodt_nome']);

		$linhas[] = array(
			'dt'        => $d['cha_dt_entrega'],
			'historico' => ($nome === '') ? 'entrega' : 'entrega ' . $nome,
			'valor'     => -$d['valor'],
			'situacao'  => 'derivado',
			'tra_id'    => null,
			'cha'       => (int)$d['cha_id'],
			// Derivado quer dizer "ainda não virou lançamento", NÃO "ainda pode mudar".
			// Quem responde a segunda pergunta é o prazo contábil, e a tela precisa dos
			// dois para não avisar sobre valor que já está fechado.
			'congelavel' => $d['congelavel'],
		);
	}

	$linhas = array_merge($linhas, $gravadas);

	// Ordena por data. No empate entre situações diferentes a derivada vem antes da
	// gravada, que é a leitura natural: a entrega acontece, depois o pagamento.
	//
	// Empate entre linhas da MESMA situação devolve 0, de propósito. usort é estável
	// desde o PHP 8.0, então o empate preserva a ordem de inserção — que para as
	// gravadas é o `ORDER BY t.tra_dt, t.tra_id` da consulta acima. Devolver 1 aqui
	// diria que cada uma das duas linhas é maior que a outra: comparador não
	// antissimétrico, que o PHP não confere e que embaralha justamente a ordem que o
	// SQL acabou de estabelecer. E o empate exato acontece de verdade —
	// cha_dt_entrega e tra_dt são os dois datetime.
	usort($linhas, function($a, $b) {
		if ($a['dt'] !== $b['dt'])             return ($a['dt'] < $b['dt']) ? -1 : 1;
		if ($a['situacao'] === $b['situacao']) return 0;
		return ($a['situacao'] === 'derivado') ? -1 : 1;
	});

	// O acumulado é arredondado a cada passo, e não só na exibição. Isso mantém o saldo
	// de toda linha na grade de centavos, que é o número que a tela mostra.
	//
	// NÃO é guarda contra deriva de ponto flutuante: medido, as duas políticas dão o
	// mesmo resultado. lan_valor é decimal(10,2), prod_valor_venda é decimal(6,2) e o
	// valor derivado já sai de dois round(...,2) — toda parcela entra na soma com no
	// máximo duas casas, e o round de cada passo reencaixa na grade antes que sobre
	// deriva para acumular. Divergência exatamente 0,0 somando 411 parcelas (o cestante
	// 379 da cópia de produção) e também 200.000, com magnitude até 99.999.999,99.
	//
	// Como nenhuma entrada alcançável distingue as duas, nenhum teste desta suíte as
	// distingue. Fica registrado como escolha deliberada e SEM guarda automática, em vez
	// de fingir que há teste segurando — ou de inventar dado impossível para fabricar um.
	$saldo = 0.0;
	foreach ($linhas as $i => $linha)
	{
		$saldo = round($saldo + $linha['valor'], 2);
		$linhas[$i]['saldo'] = $saldo;
	}

	return $linhas;
}


// O módulo inteiro fica atrás do papel Beta Tester até estar pronto, e a trava vale
// por TELA, não só no menu: item escondido é conveniência, não impedimento — quem
// digita o endereço chega igual.
//
// A condição diz o que a função IMPÕE, e nada além: papel Beta Tester mais sessão
// logada. Uma lista de papéis de negócio (ADM, finanças, núcleo) esteve escrita aqui e
// saiu, porque era morta de um jeito perigoso. Morta porque login.php:40 preenche
// usr.id antes de atribuir papel nenhum, então qualquer sessão já alcançava o último
// termo do `||`. Perigosa porque sobreviveria à saída da trava: no dia em que alguém
// apagar a linha do PAP_BETA_TESTER, a função passa a devolver true para toda sessão
// logada CONTINUANDO A PARECER uma checagem de papel. O leitor que este comentário
// protege é justamente quem vai apagar aquela linha.
//
// Quem discrimina por papel é pode_ver_conta_de(), logo abaixo, onde a distinção morde.
function pode_ver_financeiro()
{
	return !empty($_SESSION[PAP_BETA_TESTER]) && !empty($_SESSION['usr.id']);
}


// Quem alcança a conta de quem. Escopo por núcleo IMPOSTO, não sugerido: em
// cestantes.php:18 o núcleo é um padrão vindo de request_get, que outro núcleo na URL
// contorna. Numa tela de dinheiro isso não serve, então o vínculo é conferido no banco.
//
// A POLARIDADE DA FALHA AQUI É A INVERSA da do resto do módulo, e é de propósito.
// debitos_derivados() e extrato_do_cestante() devolvem null quando a consulta não
// roda, porque responder "não deve nada" a uma pergunta que não chegou a ser feita
// engana o cestante. Aqui a consulta que não roda cai no `return false` do fim: acesso
// NEGADO. Falhar para o lado fechado numa checagem de permissão protege; falhar para o
// valor vazio num cálculo de dinheiro mente. As duas regras não se unificam — e a
// desta função tem teste próprio, com a consulta quebrada de propósito.
function pode_ver_conta_de($usr_id)
{
	// usr_id chega da URL. Inteiro positivo ou nada: o que não é id não vira consulta.
	// Não é zelo abstrato — com o sql_mode vazio deste servidor o banco NÃO recusa
	// texto colado num id. Medido nesta cópia de produção: `usr_id = '1 abc'` devolve a
	// linha do usr_id 1, com warning 1292 e mais nada.
	// Só string e int passam: ?usr_id[]=1 entrega array a request_get, e converter
	// array em string emitiria warning na tela.
	if (!is_string($usr_id) && !is_int($usr_id)) return false;
	if (!ctype_digit((string)$usr_id) || (int)$usr_id <= 0) return false;

	if (!pode_ver_financeiro()) return false;

	if (!empty($_SESSION[PAP_ADM]) || !empty($_SESSION[PAP_RESP_FINANCAS])) return true;

	if (isset($_SESSION['usr.id']) && $_SESSION['usr.id'] == $usr_id) return true;   // o próprio

	if (!empty($_SESSION[PAP_RESP_NUCLEO]) && isset($_SESSION['usr.nuc']))
	{
		$sql = "SELECT usr_id FROM usuarios WHERE usr_id = " . prep_para_bd($usr_id);
		$sql.= " AND usr_nuc = " . prep_para_bd($_SESSION['usr.nuc']);
		$res = executa_sql($sql);
		if ($res && mysqli_num_rows($res)) return true;
	}

	return false;
}


// Quem pode LANÇAR pagamento — pergunta diferente de quem pode VER extrato. O cestante
// vê o próprio e não lança nada; quem lança é o responsável de núcleo, o de finanças ou
// a administração. pode_ver_conta_de() continua sendo quem decide PARA QUEM se lança.
//
// A regra mora numa função por dois motivos. O primeiro é que a mesma pergunta é feita
// em dois lugares — o item do menu e a tela de pagamentos — e duas cópias dela é uma a
// mais do que dá para manter de acordo; é a mesma decisão que o menu.inc.php:44-46 já
// registra para pode_ver_financeiro(). O segundo é que condição escrita solta dentro da
// tela não tem alavanca automática nenhuma: esta tem, no bloco "pagamento" da suíte.
//
// PAP_ADM aqui dentro NÃO reabre a porta que a tarefa anterior fechou. O que ficou para
// trás lá foi `verifica_seguranca(<condição>)`, que valida QUALQUER chamada vinda de
// PAP_ADM sem sequer olhar o parâmetro (common.inc.php:103-110) — para o administrador
// a condição inteira era decorativa. Aqui ela é lida de verdade, e pode_ver_financeiro()
// vem antes, ligado por E: administrador sem Beta Tester continua sem alcançar o módulo.
// É a primeira asserção do bloco, justamente porque é o caso que decide isso.
function pode_lancar_pagamento()
{
	if (!pode_ver_financeiro()) return false;

	return !empty($_SESSION[PAP_RESP_NUCLEO])
	    || !empty($_SESSION[PAP_RESP_FINANCAS])
	    || !empty($_SESSION[PAP_ADM]);
}


// Traduz o extrato no que a TELA pode afirmar. Devolve um ESTADO, e não um saldo que
// possa vir nulo: em PHP `null < -0.005` e `null > 0.005` são os dois falsos, então um
// saldo nulo descendo a cadeia de comparações sairia pelo ramo final e a tela
// imprimiria "em dia" para um cestante endividado — exatamente o desastre que o
// contrato de null de extrato_do_cestante() existe para impedir. Com estado, esse ramo
// não é alcançável a partir de null.
//
//   indisponivel  a consulta não rodou; não há o que afirmar, e o saldo vem null
//   devedor       deve
//   credor        tem a receber
//   em_dia        a consulta rodou e o saldo é zero — inclusive com extrato vazio
//
// Lista vazia e null são entradas diferentes e saem por estados diferentes: é a
// distinção que debitos_derivados() e extrato_do_cestante() preservam, chegando
// inteira até a tela, que é onde ela vira palavra lida por gente.
// Último lançamento GRAVADO do extrato, ou null quando ainda não há nenhum.
//
// Recebe o extrato já calculado de propósito: quem chama isto no painel já pediu
// extrato_do_cestante() para o saldo, e uma segunda consulta por linha custaria 35
// varreduras a mais numa tela que já leva mais de um segundo.
//
// Só linha 'gravado' conta. Débito derivado não é lançamento: ele existe porque a
// entrega existe, e dizer "último lançamento: entrega de junho" faria a coluna
// responder outra pergunta — a de quando a pessoa pagou pela última vez.
function ultimo_lancamento($extrato)
{
	if (!is_array($extrato)) return null;

	$ultimo = null;
	foreach ($extrato as $linha)
		if (isset($linha['situacao']) && $linha['situacao'] === 'gravado') $ultimo = $linha;

	return $ultimo;
}


function resumo_do_extrato($extrato)
{
	if ($extrato === null) return array('estado' => 'indisponivel', 'saldo' => null);

	// o acumulado da última linha é o saldo; extrato_do_cestante() já o deixa somado
	$saldo = count($extrato) ? end($extrato)['saldo'] : 0.0;

	// Meio centavo em torno de zero conta como em dia. O saldo chega de round(...,2),
	// então nenhuma linha alcançável cai dentro da faixa: ela cobre o zero, e não
	// arredondamento.
	if ($saldo < -0.005) return array('estado' => 'devedor', 'saldo' => $saldo);
	if ($saldo >  0.005) return array('estado' => 'credor',  'saldo' => $saldo);

	return array('estado' => 'em_dia', 'saldo' => $saldo);
}


// Confirma que o cestante existe e devolve o rótulo que a tela mostra. Três estados, e
// nenhum par deles pode se confundir:
//
//   ok            a linha existe; 'nome' é o rótulo, sempre string. A coluna é NULLABLE
//                 (SHOW COLUMNS: Null=YES) e aceita '', então o cast existe para o nome
//                 chegar à tela com um tipo só — hoje nenhuma das 1210 linhas da cópia
//                 de produção está nula ou vazia, o que torna o caso raro, não impossível
//   inexistente   a consulta rodou e não há linha com esse usr_id
//   indisponivel  a consulta não rodou
//
// Existe porque a tela precisa das três, e o `?:` que ela tinha antes fundia as três num
// nome vazio: com ?usr_id=9999999 a página respondia HTTP 200 e o rótulo "em dia" —
// saldo zero porque não há lançamento de um id que não existe, o que não é a mesma coisa
// que alguém estar quite. É a família do contrato de null de debitos_derivados(), por
// outra porta: lá "a pergunta foi recusada", aqui "a pergunta foi feita e não achou nada".
//
// A busca é só por usr_id — sem usr_archive e sem papel. Quem responde "existe" aqui não
// é quem decide quem pode ver: isso já passou pelo pode_ver_conta_de().
function cestante_da_conta($usr_id)
{
	// LEFT JOIN no núcleo por precaução, não por necessidade: usr_nuc é NOT NULL com
	// FK para nucleos, então hoje ele sempre casa. O LEFT mantém a identificação do
	// cestante de pé se essa garantia mudar — mas ninguém deve contar com o ramo
	// 'nucleo' => null, porque não há como alcançá-lo (o UPDATE que tentaria criá-lo
	// é recusado pela FK).
	$sql = "SELECT u.usr_nome_curto, n.nuc_nome_curto FROM usuarios u ";
	$sql.= "LEFT JOIN nucleos n ON n.nuc_id = u.usr_nuc ";
	$sql.= "WHERE u.usr_id = " . prep_para_bd($usr_id);

	$res = executa_sql($sql);
	if (!$res) return array('estado' => 'indisponivel', 'nome' => null, 'nucleo' => null);

	$row = mysqli_fetch_array($res, MYSQLI_ASSOC);
	if (!$row) return array('estado' => 'inexistente', 'nome' => null, 'nucleo' => null);

	$nucleo = trim((string)$row['nuc_nome_curto']);

	return array(
		'estado' => 'ok',
		'nome'   => (string)$row['usr_nome_curto'],
		// null, e não "", para a tela distinguir "sem núcleo" de núcleo com nome vazio
		'nucleo' => ($nucleo === '') ? null : $nucleo,
	);
}


// Destinos possíveis de um pagamento: o caixa do núcleo, uma conta da Rede ou um
// produtor — quando o cestante paga direto para ele.
//
// A lista não é só apresentação: ela é a REGRA, e registra_pagamento() confere contra
// ela o destino que veio do POST. É por isso que conta de CESTANTE fica de fora. Aceita
// como destino, um con_id forjado debitaria a conta de outro cestante, fazendo parecer
// que ele deve mais — e as duas pernas continuariam somando zero. O razão ficaria
// íntegro e errado, que é o único estado que transacoes_desbalanceadas() não pega.
//
// Pela mesma razão a função não cria conta nenhuma, nem a da Rede: validação que grava
// não é validação. Quem cria é conta_do_cestante()/conta_da_rede(), no lançamento.
//
// CONTRATO — null e array() NÃO são a mesma coisa, como no resto do módulo:
//   array()  a consulta rodou e não há destino cadastrado
//   null     a consulta NÃO rodou
// A distinção não é hipotética: hoje, na cópia de produção, `contas` está zerada, e o
// certo é a tela dizer "nenhuma conta de destino cadastrada" — não "não deu para
// carregar". As contas de caixa de núcleo e da Rede são do plano seguinte.
//
// RECORTE: é a única consulta do módulo que não é por cestante nem por núcleo, e é de
// propósito — produtor e conta da Rede não pertencem a núcleo nenhum, e o destino é
// conferido antes de se saber de quem é o pagamento. O que a mantém barata é o tamanho
// da tabela, não o filtro: `contas` é dimensão, com teto de uma linha por cestante
// (1210 hoje) mais 30 núcleos e 205 produtores, e o WHERE já entra pelo índice
// conta_tipo. Medido nesta cópia em 2026-08-28, média de 30 corridas por rodada, cinco
// rodadas: com a tabela como está hoje, zerada, 0,12 a 0,15 ms; com o TETO carregado —
// 1446 contas, sendo 1210 de cestante, 30 de núcleo, 205 de produtor e a da Rede —, 236
// destinos em 0,92 a 1,09 ms, mediana 0,94. Com o buffer pool FRIO, logo depois de o
// container subir, o mesmo teto sai em 1,70 a 1,97 ms: mesmo código, e a diferença é
// disco, não CPU.
function contas_de_destino()
{
	$sql = "SELECT c.con_id, c.con_tipo, c.con_nome, n.nuc_nome_curto, f.forn_nome_curto ";
	$sql.= "FROM contas c ";
	$sql.= "LEFT JOIN nucleos n ON n.nuc_id = c.con_nuc ";
	$sql.= "LEFT JOIN fornecedores f ON f.forn_id = c.con_forn ";
	$sql.= "WHERE c.con_archive = 0 AND c.con_tipo IN ('nucleo','rede','produtor') ";
	$sql.= "ORDER BY c.con_tipo, c.con_nome, n.nuc_nome_curto, f.forn_nome_curto";

	$res = executa_sql($sql);
	if (!$res) return null;              // ver o CONTRATO acima

	$prefixo = array('nucleo' => 'Núcleo ', 'produtor' => 'Produtor ', 'rede' => '');
	$rotulos = array();

	while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))
	{
		$nome = trim((string)$row['con_nome']);
		if ($row['con_tipo'] === 'nucleo')   $nome = trim((string)$row['nuc_nome_curto']);
		if ($row['con_tipo'] === 'produtor') $nome = trim((string)$row['forn_nome_curto']);

		// Rótulo em branco vira <option> invisível, e escolher para onde vai dinheiro
		// não pode virar sorteio. con_nome é NULLABLE, e nuc_nome_curto/forn_nome_curto
		// aceitam '' com o sql_mode vazio deste servidor. O con_id é feio e serve: a
		// linha continua distinguível das outras.
		if ($nome === '') $nome = '#' . (int)$row['con_id'];

		$rotulos[(int)$row['con_id']] = $prefixo[$row['con_tipo']] . $nome;
	}

	// Rótulo REPETIDO é tão ruim quanto rótulo em branco, e ganha o mesmo desempate.
	// Duas contas com o mesmo texto viram dois <option> idênticos numa tela que move
	// dinheiro: quem escolhe não tem como saber qual é qual, e escolher a errada credita
	// uma conta que não é a contraparte dos débitos de entrega.
	//
	// Não é hipótese. Medido em 2026-08-28, em transação, nesta cópia de produção:
	// conta_da_rede() cria a conta principal com con_chave, e
	// cria_conta('rede', array('con_nome' => 'Rede Ecológica')) logo depois é ACEITA —
	// as duas convivem e saíam daqui com texto idêntico e con_id diferentes. É o minor
	// que a Task 1 adiou dizendo "vira regra em PHP"; a Task 3 fechou a IDENTIDADE
	// (con_chave, utf8_bin) e ninguém tinha fechado o RÓTULO.
	//
	// O desempate é aqui, e não em cria_conta(): con_nome é editável, então proibir nome
	// repetido na criação só empurraria o mesmo estado para o UPDATE seguinte. Aqui a
	// regra vale para toda lista montada, venha a duplicata de onde vier.
	//
	// TODAS as repetidas recebem o sufixo, não só da segunda em diante: marcar uma só
	// deixaria a outra com o texto limpo, e o leitor continuaria sem saber qual das duas
	// é "a" conta.
	//
	// Custo da passada extra, medido em 2026-08-28 alternando as duas versões na mesma
	// máquina quente, com o teto carregado (1446 contas, 236 destinos), 30 corridas por
	// medição e 5 medições de cada lado: mediana 0,94 ms COM o desempate e 0,95 ms SEM.
	// A diferença é −0,01 ms, ou seja não se separa do ruído.
	$quantos  = array_count_values($rotulos);
	$destinos = array();

	foreach ($rotulos as $con_id => $rotulo)
		$destinos[$con_id] = ($quantos[$rotulo] > 1) ? $rotulo . ' #' . $con_id : $rotulo;

	return $destinos;
}


// O pagamento credita o cestante e debita quem recebeu o dinheiro. Não se amarra a
// chamada nenhuma: uma entrega pode reunir três chamadas, e o que importa é que o
// crédito cubra o saldo.
//
// O DESTINO É CONFERIDO AQUI, e não só no <select> da tela. O <select> oferece apenas
// os destinos legítimos, mas um POST carrega qualquer con_id — e o efeito de um destino
// forjado está descrito em contas_de_destino(). A função é a fronteira; a tela é
// conveniência.
//
// Lista indisponível recusa: não se confere destino contra uma lista que não veio. A
// polaridade aqui é a de pode_ver_conta_de(), e não a de debitos_derivados() — diante
// da dúvida, uma checagem fecha. O caminho tem teste, com a consulta quebrada de
// propósito, e a alavanca é a versão que "conserta" a tela deixando passar.
//
// O que esta função NÃO faz é autorizar. Quem alcança a conta de quem é
// pode_ver_conta_de(), chamada linha a linha pela tela. Fundir as duas trocaria uma
// consulta de permissão recusada no meio do lote por um null indistinguível de destino
// inválido, e amarraria a $_SESSION uma função que o script agendado do plano seguinte
// vai querer chamar sem sessão nenhuma.
//
// A conferência do destino vem ANTES de conta_do_cestante($usr_id, true), e a ordem
// importa: o `true` cria conta, e destino forjado não pode fazer nascer conta vazia.
//
// A frase acima vale para o DESTINO, e só. Passada esta linha a conta do cestante já
// nasceu, e um lançamento que falhe depois — valor inválido, INSERT recusado — deixa
// essa conta comitada, vazia, com saldo zero. Conferido em 2026-08-28: a função devolve
// null e a conta fica na tabela.
//
// Fica assim de propósito. Conta de cestante vazia é o mesmo estado que a próxima
// chamada de conta_do_cestante($usr_id, true) criaria de qualquer jeito, e conta sem
// lançamento soma zero — não move o razão nem aparece como destino, porque
// contas_de_destino() não lista cestante. Já mover a criação para dentro da transação
// de lanca_transacao() mexeria justamente na ordem que garante a recusa do destino
// forjado, que é a propriedade cara deste bloco. Artefato benigno preferido a risco na
// fronteira.
//
// Devolve o tra_id, ou null — e no null nenhuma perna é gravada.
function registra_pagamento($usr_id, $dt, $valor, $con_destino, $comprovante, $obs)
{
	// pg_destino[0][] entrega ARRAY onde se espera escalar, e no PHP 8 `isset($a[$k])`
	// com $k array é TypeError — a tela cairia inteira por causa de um nome de campo
	// no POST. Só string e int passam, como em pode_ver_conta_de().
	if (!is_string($con_destino) && !is_int($con_destino)) return null;

	$destinos = contas_de_destino();
	if ($destinos === null)              return null;   // sem lista não há o que conferir
	if (!isset($destinos[$con_destino])) return null;   // destino que a lista não oferece

	$con_cestante = conta_do_cestante($usr_id, true);
	if (!$con_cestante) return null;

	return lanca_transacao($dt, 'pagamento', $con_destino, $con_cestante, $valor,
		'pagamento', array('comprovante' => $comprovante, 'obs' => $obs));
}


// Lê o formulário de lançamento em lote e devolve só as linhas que podem virar
// lançamento, já normalizadas.
//
// Mora aqui, e não solta dentro da tela, porque tela não tem alavanca automática:
// escrita no meio do conta_pagamentos.php nenhuma asserção desta suíte a alcançaria, e
// é justamente esta leitura que transforma texto de POST em dinheiro.
//
// O POST não é fonte de verdade nem sobre a própria forma. sizeof() sobre string é
// TypeError no PHP 8 — um POST com `pg_usr=1`, escalar em vez de array, derrubaria a
// página —, os arrays paralelos podem chegar com tamanhos diferentes, e a data pode não
// ser data: date_format(false, ...) é TypeError pelo mesmo caminho. Linha malformada se
// PULA, não se adivinha.
//
// Devolve array('linhas' => array(...), 'ignoradas' => int). Separar "em branco" de
// "ignorada" é o que deixa a tela dizer a verdade: no painel de 35 cestantes, 34 linhas
// em branco são o caso normal e não são recusa nenhuma, enquanto uma linha PREENCHIDA
// que não pôde ser lida é recusa, e quem lançou precisa saber. Um "3 pagamentos
// registrados" que engole as outras duas é a mesma mentira que o módulo inteiro existe
// para não contar.
//
// O que NÃO passa por aqui: autorização (é pode_ver_conta_de(), chamada linha a linha
// pela tela) e a legitimidade do destino (é registra_pagamento). Os dois campos saem
// daqui como vieram, de propósito — cada guarda confere o seu.
function linhas_de_pagamento($campos)
{
	$vazio = array('linhas' => array(), 'ignoradas' => 0);

	// POST que não tem a forma de lote não tem linha para ignorar: não é formulário
	// meio preenchido, é outra coisa.
	foreach (array('pg_usr', 'pg_dt', 'pg_valor', 'pg_destino') as $c)
		if (!isset($campos[$c]) || !is_array($campos[$c])) return $vazio;

	$linhas    = array();
	$ignoradas = 0;

	// As chaves saem de pg_usr, que é a coluna que identifica a linha; as outras são
	// consultadas por essa chave em vez de por posição, porque um POST forjado não
	// precisa numerar de 0 a n-1.
	foreach (array_keys($campos['pg_usr']) as $i)
	{
		$bruto = array();
		$falta = false;

		foreach (array('pg_usr' => 'usr', 'pg_dt' => 'dt', 'pg_valor' => 'valor',
		               'pg_destino' => 'destino') as $campo => $nome)
		{
			$v = isset($campos[$campo][$i]) ? $campos[$campo][$i] : null;
			if (!is_string($v) && !is_int($v)) { $falta = true; break; }
			$bruto[$nome] = $v;
		}

		if ($falta) { $ignoradas++; continue; }

		// Linha em branco é o caso normal do painel, e não é recusa.
		$txt = trim((string)$bruto['valor']);
		if ($txt === '') continue;

		// '1.234,56' é como a Rede escreve dinheiro. formata_numero_para_mysql() TROCA
		// ponto e vírgula, então sozinho devolveria '1,234.56' — que não é número, e a
		// linha seria recusada com o valor certo digitado. O separador de milhar sai
		// antes, e só quando há vírgula decimal para desambiguar.
		if (strpos($txt, ',') !== false) $txt = str_replace('.', '', $txt);

		$valor = formata_numero_para_mysql($txt);
		if (!is_numeric($valor) || (float)$valor <= 0) { $ignoradas++; continue; }

		// '30/02/2026' PARSEIA e escorrega para 02/03 sem devolver false — por isso a
		// conferência olha os avisos, e não só o retorno. Sem ela, quem digitasse uma
		// data impossível veria o pagamento gravado em outro dia, calado.
		// date_get_last_errors() devolve false quando não há nada a relatar (PHP 8.2+),
		// daí o is_array().
		$data = date_create_from_format('d/m/Y', trim((string)$bruto['dt']));
		$erros = date_get_last_errors();
		if (!$data || (is_array($erros) && ($erros['warning_count'] > 0 || $erros['error_count'] > 0)))
		{
			$ignoradas++;
			continue;
		}

		$comprovante = '';
		if (isset($campos['pg_comprovante'][$i]) && is_string($campos['pg_comprovante'][$i]))
			$comprovante = trim($campos['pg_comprovante'][$i]);

		$linhas[] = array(
			'usr'         => $bruto['usr'],
			'dt'          => date_format($data, 'Y-m-d'),
			'valor'       => (float)$valor,
			'destino'     => $bruto['destino'],
			'comprovante' => $comprovante,
		);
	}

	return array('linhas' => $linhas, 'ignoradas' => $ignoradas);
}
