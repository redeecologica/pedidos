<?php
// Razão do módulo financeiro: contas, transações e saldo.
//
// Regra de sinal: saldo de uma conta é a soma de lan_valor.
//   negativo = deve ao sistema · positivo = tem a receber
// É a mesma convenção da planilha que este módulo substitui.

// O BALANÇO DA CHAMADA MORA EM OUTRO ARQUIVO, e subiu para produção antes deste
// módulo — ele só lê o que Entregas já registra, e por isso não precisou de tabela
// nova. As funções que os dois usam (abas_entregas, o balanço da chamada e o detalhe
// por núcleo) vivem lá, e aqui só se carrega.
//
// require_once, e não require: menu.inc.php carrega este arquivo em toda página, e as
// telas de Entregas carregam o balanco.inc.php por conta própria. Sem o _once, as duas
// vias juntas redeclarariam as funções e derrubariam a página.
require_once(__DIR__ . "/balanco.inc.php");


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
	// só o caixa do núcleo usa categoria, e só em despesa; nas demais fica NULL, que é
	// a verdade — pagamento e repasse não têm o que classificar
	$categoria    = isset($extras['categoria'])    ? prep_para_bd($extras['categoria'])    : "NULL";
	// quem recebeu, quando o outro lado não tem conta — o motorista, quem levou a carga
	$favorecido   = isset($extras['favorecido'])   ? prep_para_bd($extras['favorecido'])   : "NULL";
	$usr          = isset($_SESSION['usr.id'])     ? prep_para_bd($_SESSION['usr.id'])     : "0";

	// MySQL não aninha transação: um BEGIN dentro de outra faz COMMIT implícito
	// da externa. Os testes envolvem o fixture numa transação para desfazer no
	// fim, então só abrimos a nossa quando não há uma aberta — em produção
	// ninguém envolve, e as duas pernas seguem atômicas.
	global $conn_link, $financeiro_em_transacao;
	$nossa = empty($financeiro_em_transacao);
	if ($nossa) { mysqli_begin_transaction($conn_link); $financeiro_em_transacao = true; }

	$sql = "INSERT INTO transacoes (tra_dt, tra_tipo, tra_cha, tra_historico, tra_comprovante, tra_obs, tra_categoria, tra_favorecido, tra_usr_registro) ";
	$sql.= "VALUES (" . prep_para_bd($dt) . ", " . prep_para_bd($tipo) . ", $cha, ";
	$sql.= prep_para_bd($historico) . ", $comprovante, $obs, $categoria, $favorecido, $usr)";

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

// A conta que recebe a OUTRA perna dos movimentos cujo outro lado não tem conta no
// sistema — a despesa que sai para o motorista, a doação que entra de um cestante.
// Toda transação soma zero e precisa de duas pernas; esta é a segunda quando não há
// ninguém cadastrado do outro lado. É encanamento: ninguém escolhe esta conta numa
// tela, e ela não é destino de pagamento (contas_de_destino filtra por tipo).
if (!defined('CONTA_CHAVE_CONTRAPARTIDA')) define('CONTA_CHAVE_CONTRAPARTIDA', 'contrapartida');

// O estoque de secos que a Rede guarda entre uma chamada e outra. Mercadoria parada é
// ATIVO — a Rede pagou o produtor por ela e ainda não vendeu —, e não prejuízo.
if (!defined('CONTA_CHAVE_ESTOQUE')) define('CONTA_CHAVE_ESTOQUE', 'estoque');

// Data em que o módulo entra em operação. Entrega anterior a ela NÃO vira débito
// derivado: fica na planilha e entra depois como uma linha de reconciliação por
// cestante, com o saldo que o núcleo informar.
//
// Sem esse piso o extrato mostra a dívida desde 2013 e a soma não quer dizer nada:
// um cestante aparece devendo uma fortuna, e o painel de um núcleo, um negativo enorme.
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
	return array(CONTA_CHAVE_REDE => 'rede', CONTA_CHAVE_CONTRAPARTIDA => 'sistema',
	             CONTA_CHAVE_ESTOQUE => 'estoque');
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
		// 'sistema' é conta de encanamento, não de gente: contas_de_destino_por_grupo
		// filtra por tipo e nunca a oferece como destino de pagamento.
		'sistema'  => 'con_nome',
		// 'estoque' é ativo da Rede, não encanamento — merece tipo próprio para se
		// distinguir na lista de contas. Fica fora dos destinos pelo mesmo filtro.
		'estoque'  => 'con_nome',
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

// A conta de contrapartida. Mesma forma de conta_da_rede(): busca por con_chave, cria
// se não houver, e a UNIQUE da chave garante que só existe uma.
//
// Ela existe porque o razão exige duas pernas somando zero, e metade dos movimentos do
// caixa do núcleo não tem ninguém cadastrado do outro lado: quem recebe a despesa é o
// motorista, quem faz a doação é alguém sem conta. Antes, essas pernas iam para a conta
// da Rede — o que dizia que a Rede assumira aquele custo, e deixou de ser verdade
// quando a despesa virou custo do próprio núcleo, medido no resultado.
//
// NINGUÉM ESCOLHE ESTA CONTA numa tela, e é de propósito: ela não carrega informação. O
// que interessa — qual núcleo, que tipo, que categoria, quanto, quando — está todo na
// perna do CAIXA, que é a que o resultado e o fluxo de caixa leem.
function conta_de_contrapartida()
{
	$chave = CONTA_CHAVE_CONTRAPARTIDA;

	$res = executa_sql("SELECT con_id FROM contas WHERE con_chave = " . prep_para_bd($chave));
	if (!$res) return null;
	if ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) return (int)$row['con_id'];

	return cria_conta('sistema', array(
		'con_nome'  => 'Contrapartida (despesas e receitas fora do sistema)',
		'con_chave' => $chave,
	));
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

// Rótulo da conta que está do OUTRO lado de um lançamento, a partir de uma linha que já
// trouxe con_tipo, con_nome e os nomes de núcleo e fornecedor.
//
// Mora numa função porque o extrato do cestante e o do núcleo montam o mesmo rótulo, e
// duas cópias divergiriam no primeiro dia em que uma mudasse. O prefixo é o mesmo de
// contas_de_destino_por_grupo(), para "Núcleo Urca" não se ler como uma conta da Rede
// chamada "Urca".
function rotulo_de_contraparte($row)
{
	// Cada consulta traz só as colunas de que precisa — o extrato do cestante nunca tem
	// outro cestante do outro lado, e não seleciona usr_nome_curto. Ler chave ausente é
	// warning na tela, então cada leitura passa por isset.
	$campo = function ($nome) use ($row) {
		return isset($row[$nome]) ? trim((string)$row[$nome]) : '';
	};

	switch ($campo('contra_tipo'))
	{
		case 'nucleo':   return 'Núcleo '   . $campo('nuc_nome_curto');
		case 'produtor': return 'Produtor ' . $campo('forn_nome_curto');
		case 'cestante': return $campo('usr_nome_curto');
	}

	return $campo('contra_nome');   // conta da Rede, e o que não se reconhecer
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
	// A CONTRAPARTE sai da OUTRA perna da mesma transação. Sem ela o extrato diz que
	// houve um pagamento de 1.500 e não diz para onde o dinheiro foi — e é justamente
	// isso que a tela de edição precisa mostrar, já que a conta não se edita: quem
	// corrige a descrição tem de poder conferir se o destino está certo antes de
	// decidir se o caso é de ajuste.
	$sql = "SELECT t.tra_id, t.tra_dt, t.tra_tipo, t.tra_cha, t.tra_historico, t.tra_comprovante, t.tra_dt_alteracao, l.lan_valor, ";
	$sql.= "co.con_tipo contra_tipo, co.con_nome contra_nome, n.nuc_nome_curto, f.forn_nome_curto ";
	$sql.= "FROM contas c ";
	$sql.= "JOIN lancamentos l ON l.lan_con = c.con_id ";
	$sql.= "JOIN transacoes t ON t.tra_id = l.lan_tra ";
	$sql.= "LEFT JOIN lancamentos l2 ON l2.lan_tra = t.tra_id AND l2.lan_con <> l.lan_con ";
	$sql.= "LEFT JOIN contas co       ON co.con_id  = l2.lan_con ";
	$sql.= "LEFT JOIN nucleos n       ON n.nuc_id   = co.con_nuc ";
	$sql.= "LEFT JOIN fornecedores f  ON f.forn_id  = co.con_forn ";
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
			// tra_comprovante é nullable; string vazia e null viram a mesma coisa aqui,
			// porque para quem lê a tela "não tem comprovante" é um estado só
			'comprovante' => trim((string)$row['tra_comprovante']),
			// null enquanto ninguém editou a descrição — a tela usa isso para dizer
			// que o texto mudou depois do lançamento
			'editado_em' => $row['tra_dt_alteracao'],
			'tra_id'    => (int)$row['tra_id'],
			'cha'       => ($row['tra_cha'] === null) ? null : (int)$row['tra_cha'],
			// Rótulo da conta do outro lado, com o mesmo prefixo da lista de destinos,
			// para "Núcleo Urca" não se confundir com uma conta da Rede chamada "Urca".
			// '' quando a transação não tem outra perna legível — a tela mostra "—" em
			// vez de afirmar um destino que não conferiu.
			'contraparte' => rotulo_de_contraparte($row),
			'tipo'        => (string)$row['tra_tipo'],
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
			// entrega não é lançamento e não tem comprovante — a chave existe para quem
			// consome não precisar testar a situação antes de ler
			'comprovante' => '',
			// idem: entrega derivada ainda não tem contraparte, porque ainda não é
			// lançamento. Quando a materialização chegar, ela passa a ter.
			'contraparte' => '',
			'tipo'        => '',
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
	// a cópia de produção tem) e também um teto folgado, com magnitude até 99.999.999,99.
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
// O comprovante vira link, ou não. Devolve a URL quando ela é http/https, e "" para
// qualquer outra coisa — inclusive texto comum, que a tela mostra escapado.
//
// A validação de ESQUEMA é o ponto. O comprovante é texto que alguém digitou, e virar
// href sem conferir aceitaria javascript:… — um clique executando script escolhido por
// quem lançou o pagamento. Lista de permissão, não de bloqueio: o que não é http nem
// https não é link, sem exceção.
function comprovante_como_link($comprovante)
{
	$url = trim((string)$comprovante);
	if ($url === '') return '';
	if (!preg_match('#^https?://[^\s<>"\']+$#i', $url)) return '';

	return $url;
}


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
// $nuc_prioritario: o núcleo que a tela está mostrando. A ordem da lista é a ordem
// em que a pessoa procura — contas da Rede primeiro, depois o caixa DAQUELE núcleo,
// depois os outros núcleos, e produtores por último. Numa lista de quinze destinos,
// o certo estar em terceiro ou em décimo é a diferença entre conferir e rolar.
//
// $nuc_prioritario muda só a ORDEM, nunca o conteúdo. É condição de a lista exibida
// ser a MESMA que valida: registra_pagamento() chama esta função sem núcleo nenhum,
// e uma tela que oferecesse destino que a fronteira recusa seria pior que destino de
// menos. Por isso caixa de núcleo arquivado sai para todo mundo — quem está num
// núcleo assim paga para a Rede ou direto ao produtor.
// Edita a DESCRIÇÃO de uma transação: histórico e comprovante. Devolve true quando
// gravou, false quando não.
//
// O que NÃO se edita aqui: valor, contas e data. Esses estão na aritmética — o saldo
// é SUM(lan_valor) e o invariante conta pernas — e em partidas dobradas o certo é
// lançar um ajuste, não reescrever o passado. Histórico e comprovante são
// descritivos: nenhuma conta os lê, então mudá-los não move um centavo.
//
// O rastro é obrigatório, e é a razão de esta função existir em vez de um UPDATE na
// tela. transacoes guarda quem CRIOU e quando; sem carimbar quem alterou, a edição
// ficaria invisível e a linha continuaria dizendo que foi registrada por quem a
// criou, na data original.
//
// Autorização é do CHAMADOR: esta função não sabe qual tela a invocou. Quem chama
// tem de ter passado por pode_ver_conta_de() do dono da conta — conta_cestante.php
// faz isso antes de qualquer saída.
function edita_descricao_transacao($tra_id, $historico, $comprovante)
{
	if (!is_numeric($tra_id) || (int)$tra_id <= 0) return false;

	$usr = isset($_SESSION['usr.id']) ? prep_para_bd($_SESSION['usr.id']) : "0";

	$sql = "UPDATE transacoes SET ";
	$sql.= "tra_historico = "     . prep_para_bd(trim((string)$historico))   . ", ";
	$sql.= "tra_comprovante = "   . prep_para_bd(trim((string)$comprovante)) . ", ";
	$sql.= "tra_usr_alteracao = " . $usr . ", ";
	$sql.= "tra_dt_alteracao = NOW() ";
	$sql.= "WHERE tra_id = " . prep_para_bd((int)$tra_id);

	// `=== true`, e não `!== false`. São coisas diferentes aqui: executa_sql()
	// (common.inc.php:389-390) devolve o INTEIRO 0 quando não há conexão, e `0 !== false`
	// é verdadeiro — a função relataria sucesso com nada gravado, e a tela diria
	// "Descrição atualizada" sobre um UPDATE que nunca rodou.
	//
	// mysqli_query devolve exatamente true para UPDATE que deu certo, então é isso que
	// se exige. Sucesso relatado sem gravação é a mesma família de "consulta que falha
	// vira não deve nada" que este módulo já corrigiu em quatro funções.
	return executa_sql($sql) === true;
}


// De quem é a conta que esta transação movimenta, para o chamador poder aplicar
// pode_ver_conta_de(). Devolve o usr_id, ou null quando a transação não toca conta de
// cestante nenhuma (transferência entre núcleo e Rede, por exemplo) ou não existe.
function cestante_da_transacao($tra_id)
{
	$sql = "SELECT c.con_usr FROM lancamentos l ";
	$sql.= "JOIN contas c ON c.con_id = l.lan_con AND c.con_tipo = 'cestante' ";
	$sql.= "WHERE l.lan_tra = " . prep_para_bd($tra_id) . " AND c.con_usr IS NOT NULL ";
	$sql.= "LIMIT 1";

	$res = executa_sql($sql);
	if (!$res) return null;
	$row = mysqli_fetch_array($res, MYSQLI_ASSOC);

	return $row ? (int)$row['con_usr'] : null;
}


// Cria a conta que falta para cada núcleo e cada produtor ATIVO. Devolve quantas
// criou, ou null se a consulta não rodou.
//
// Existe porque conta de núcleo e de produtor não deve ser digitada uma a uma: o
// vínculo já está no cadastro, e o nome sai dele. Só a conta da Rede é criada à mão,
// porque é a única cujo rótulo é uma decisão ("Rede (conta Fulana)").
//
// É idempotente por construção: cria_conta() esbarra na UNIQUE de con_nuc/con_forn
// quando a conta já existe, então rodar de novo não duplica nada. Arquivadas ficam de
// fora — não são destino válido, e criar conta para elas seria criar lixo.
function cria_contas_que_faltam()
{
	$criadas = 0;

	$res = executa_sql("SELECT nuc_id, nuc_nome_curto FROM nucleos WHERE nuc_archive = 0 "
	                 . "AND nuc_id NOT IN (SELECT COALESCE(con_nuc,0) FROM contas WHERE con_tipo = 'nucleo') "
	                 . "ORDER BY nuc_nome_curto");
	if (!$res) return null;
	while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))
		if (cria_conta('nucleo', array('con_nuc' => $row['nuc_id'],
		                               'con_nome' => 'Caixa ' . $row['nuc_nome_curto']))) $criadas++;

	$res = executa_sql("SELECT forn_id, forn_nome_curto FROM fornecedores WHERE forn_archive = 0 "
	                 . "AND forn_id NOT IN (SELECT COALESCE(con_forn,0) FROM contas WHERE con_tipo = 'produtor') "
	                 . "ORDER BY forn_nome_curto");
	if (!$res) return null;
	while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))
		if (cria_conta('produtor', array('con_forn' => $row['forn_id'],
		                                 'con_nome' => $row['forn_nome_curto']))) $criadas++;

	return $criadas;
}


// Renomeia uma conta. Só o rótulo: tipo, vínculo e chave não se editam.
//
// O tipo e o vínculo dizem o que a conta É; trocá-los numa conta que já tem
// lançamento mudaria, em silêncio, de quem é aquele dinheiro. A chave é identidade —
// foi a lição da Task 3, e amarrar identidade a algo editável faz renomear criar uma
// segunda conta sem ninguém perceber.
function renomeia_conta($con_id, $nome)
{
	$nome = trim((string)$nome);
	if (!is_numeric($con_id) || (int)$con_id <= 0) return false;
	if ($nome === '') return false;                 // rótulo em branco vira <option> invisível

	return executa_sql("UPDATE contas SET con_nome = " . prep_para_bd($nome)
	                 . " WHERE con_id = " . prep_para_bd((int)$con_id)) === true;
}


// Arquiva ou desarquiva. Arquivar tira a conta de contas_de_destino() sem apagar
// nada: conta com lançamento não pode ser apagada, e o histórico dela continua
// valendo no extrato de quem movimentou.
function arquiva_conta($con_id, $arquivada)
{
	if (!is_numeric($con_id) || (int)$con_id <= 0) return false;

	return executa_sql("UPDATE contas SET con_archive = " . ($arquivada ? "1" : "0")
	                 . " WHERE con_id = " . prep_para_bd((int)$con_id)) === true;
}


// A lista PLANA, que é o contrato que registra_pagamento() valida. Derivada da
// agrupada de propósito: uma segunda consulta seria uma segunda cópia da regra, e as
// duas poderiam discordar sobre o que é destino válido — a tela oferecendo o que a
// fronteira recusa, ou o contrário.
function contas_de_destino($nuc_prioritario = null)
{
	$grupos = contas_de_destino_por_grupo($nuc_prioritario);
	if ($grupos === null) return null;

	$plana = array();
	foreach ($grupos as $grupo)
		foreach ($grupo['contas'] as $con_id => $rotulo) $plana[$con_id] = $rotulo;

	return $plana;
}


// A mesma lista, repartida para a tela: array de grupos, cada um com 'titulo' e
// 'contas'. Grupo vazio não entra. Mesmos contrato de erro e mesmas regras de
// filtragem da versão plana, porque é ela quem chama esta.
function contas_de_destino_por_grupo($nuc_prioritario = null)
{
	$nuc_bd = (is_numeric($nuc_prioritario) && (int)$nuc_prioritario > 0)
	        ? (int)$nuc_prioritario : 0;

	$sql = "SELECT c.con_id, c.con_tipo, c.con_nome, n.nuc_nome_curto, f.forn_nome_curto, ";
	// a ordem vive no SQL para o desempate de rótulo, abaixo, receber a lista já pronta
	$sql.= "CASE c.con_tipo ";
	// O NÚCLEO DA PESSOA VEM PRIMEIRO, quando há um em foco. É para onde o cestante
	// paga na esmagadora maioria das vezes — entrega o dinheiro a quem responde pelo
	// núcleo dele —, e deixar as contas da Rede no topo punha a opção mais rara acima
	// da mais comum. Sem núcleo em foco a lista começa pelas contas da Rede, que aí é
	// o destino mais provável.
	$sql.= "  WHEN 'nucleo' THEN IF(c.con_nuc = $nuc_bd, " . ($nuc_bd ? "0" : "2") . ", 2) ";
	$sql.= "  WHEN 'rede' THEN 1 ";
	$sql.= "  ELSE 3 END grupo ";
	$sql.= "FROM contas c ";
	$sql.= "LEFT JOIN nucleos n ON n.nuc_id = c.con_nuc ";
	$sql.= "LEFT JOIN fornecedores f ON f.forn_id = c.con_forn ";
	// A CONTA DE RESULTADO DA REDE NÃO É DESTINO DE NADA. Ela é do tipo 'rede', então
	// entrava na lista — e vinha PRIMEIRO, o que a tornava a opção pré-selecionada de
	// todo pagamento de cestante e de todo repasse de núcleo. Ela não é um lugar onde
	// dinheiro fica: é a contraparte que acumula o que a Rede absorveu de custo e o que
	// os cestantes lhe devem. Dinheiro registrado ali entraria no resultado sem passar
	// por caixa nenhum, e o saldo de quem de fato está com o dinheiro nunca fecharia.
	//
	// Excluída pela CHAVE, e não chamando conta_da_rede(): aquela função CRIA a conta
	// quando não existe, e uma lista não pode gravar nada por ser aberta.
	//
	// As contas de contrapartida e de estoque já ficavam de fora por serem de outro
	// tipo — esta era a única a vazar.
	$sql.= "WHERE c.con_archive = 0 AND c.con_tipo IN ('nucleo','rede','produtor') ";
	$sql.= "AND (c.con_chave IS NULL OR c.con_chave <> " . prep_para_bd(CONTA_CHAVE_REDE) . ") ";
	$sql.= "AND (c.con_tipo <> 'nucleo' OR n.nuc_archive = 0) ";
	$sql.= "AND (c.con_tipo <> 'produtor' OR f.forn_archive = 0) ";
	$sql.= "ORDER BY grupo, c.con_nome, n.nuc_nome_curto, f.forn_nome_curto";

	$res = executa_sql($sql);
	if (!$res) return null;              // ver o CONTRATO acima

	$prefixo  = array('nucleo' => 'Núcleo ', 'produtor' => 'Produtor ', 'rede' => '');
	$rotulos  = array();
	$grupo_de = array();

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
		$grupo_de[(int)$row['con_id']] = (int)$row['grupo'];
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

	// Reparte na ordem em que já vieram do SQL. Sem núcleo em foco os grupos 1 e 2 são
	// a mesma coisa — "o núcleo desta tela" não existe — e viram um bloco só.
	$em_foco = (is_numeric($nuc_prioritario) && (int)$nuc_prioritario > 0);
	$titulos = array(
		0 => 'Núcleo deste painel',
		1 => 'Contas da Rede',
		2 => $em_foco ? 'Outros núcleos' : 'Núcleos',
		3 => 'Produtores',
	);

	$grupos = array();
	foreach ($destinos as $con_id => $rotulo)
	{
		$g = isset($grupo_de[$con_id]) ? $grupo_de[$con_id] : 3;
		if (!isset($grupos[$g])) $grupos[$g] = array('titulo' => $titulos[$g], 'contas' => array());
		$grupos[$g]['contas'][$con_id] = $rotulo;
	}

	// Grupo vazio não entra: um <optgroup> sem opção vira um rótulo solto na lista.
	return array_values($grupos);
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


// ============================================================================
// CAIXA DO NÚCLEO (spec §4)
//
// O núcleo recebe dinheiro de cestante e gasta parte dele antes de repassar. Sem
// registrar o que sai, o saldo do caixa afirma que o núcleo deve tudo que recebeu —
// e quem prestou contas de verdade fica devendo no papel.
//
// A REGRA DE SINAL, que vale para o módulo inteiro: negativo = deve, positivo = tem
// a receber. Quando um cestante paga no caixa, o caixa fica NEGATIVO: segura dinheiro
// que não é dele. Os lançamentos daqui empurram esse saldo de volta para zero.
// ============================================================================


// As seis categorias de despesa DO NÚCLEO — motorista, passagens e as demais da folha
// que o núcleo já preenche. Para as áreas da REDE, ver categorias_de_despesa_da_rede().
//
// Chave curta para o banco, rótulo para a tela: o rótulo pode mudar de redação sem
// invalidar o que já foi classificado, e é por isso que não se grava o texto.
function categorias_de_despesa()
{
	return array(
		'passagens'  => 'passagens',
		'expediente' => 'material de expediente e despesas administrativas',
		'motorista'  => 'motorista',
		'entregas'   => 'resp. entregas',
		'bancarias'  => 'despesas bancárias',
		'outros'     => 'outros',
	);
}


// A conta-caixa do núcleo. NÃO cria: contas de núcleo nascem em
// cria_contas_que_faltam(), pelo botão da tela de contas. Criar aqui faria um POST
// inventar caixa para um núcleo que a administração ainda não abriu.
//
// Mesmo contrato de conta_do_cestante(): null tanto para "a consulta não rodou"
// quanto para "não existe". Quem chama trata os dois igual — sem conta não há
// lançamento — e um contrato a mais aqui seria distinção sem consumidor.
function conta_do_nucleo($nuc_id)
{
	if (!is_string($nuc_id) && !is_int($nuc_id)) return null;

	$res = executa_sql("SELECT con_id FROM contas WHERE con_tipo = 'nucleo' AND con_nuc = " . prep_para_bd($nuc_id));
	if (!$res) return null;

	$row = mysqli_fetch_array($res, MYSQLI_ASSOC);
	return $row ? (int)$row['con_id'] : null;
}


// O tipo de uma conta, para conferir se ela cabe no lançamento pedido.
//
// CONTRATO: null = a consulta não rodou · '' = não existe conta com esse id ·
// senão o tipo. Aqui a distinção MORDE, ao contrário de conta_do_nucleo(): quem
// compara `tipo_de_conta($x) !== 'rede'` trataria null como "tipo errado" e recusaria
// um lançamento legítimo por causa de um servidor fora do ar — recusa, que é o lado
// seguro, mas dita por outra razão que a mensagem de erro esconderia.
function tipo_de_conta($con_id)
{
	if (!is_string($con_id) && !is_int($con_id)) return '';

	$res = executa_sql("SELECT con_tipo FROM contas WHERE con_id = " . prep_para_bd($con_id));
	if (!$res) return null;

	$row = mysqli_fetch_array($res, MYSQLI_ASSOC);
	return $row ? (string)$row['con_tipo'] : '';
}


// As contas de destino de um tipo só, para a tela do caixa poder oferecer contas da
// Rede num lançamento e produtores noutro.
//
// Filtra a lista de contas_de_destino() em vez de repetir o SELECT dela. Repetir traria
// junto as regras de exclusão — arquivada, núcleo arquivado, produtor arquivado — e
// duas cópias delas ficariam diferentes no primeiro dia em que uma mudasse. Também
// herda de graça o desempate de rótulo repetido, que é o que impede dois <option> com
// texto idêntico numa tela que move dinheiro.
//
// Mesmo contrato: null quando a consulta não rodou, array() quando não há conta daquele
// tipo — e a tela precisa dos dois separados, porque o segundo é "cadastre um produtor"
// e o primeiro é "tente de novo mais tarde".
function contas_de_destino_do_tipo($tipo)
{
	$todas = contas_de_destino();
	if ($todas === null) return null;

	$res = executa_sql("SELECT con_id FROM contas WHERE con_tipo = " . prep_para_bd($tipo));
	if (!$res) return null;

	$do_tipo = array();
	while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))
	{
		$id = (int)$row['con_id'];
		if (isset($todas[$id])) $do_tipo[$id] = $todas[$id];
	}

	return $do_tipo;
}


// Um lançamento do caixa do núcleo. Devolve tra_id, ou null quando não pôde virar
// lançamento — e recusar em silêncio é de propósito: quem chama é a tela, que sabe
// dizer o que faltou; a função não tem como saber se a recusa é engano de quem digitou
// ou POST forjado, e as duas se tratam igual.
//
// AS QUATRO FORMAS, e por que três delas têm as mesmas pernas:
//
//   despesa             núcleo +X · rede     −X   gastou o dinheiro que segurava
//   repasse             núcleo +X · rede     −X   entregou o dinheiro que segurava
//   pagamento_produtor  núcleo +X · produtor −X   pagou direto, encurtando a transferência
//   receita             núcleo −X · rede     +X   entrou dinheiro que pertence à Rede
//
// Despesa e repasse são o MESMO movimento de dinheiro: em ambos o núcleo se livra do
// que devia. A diferença é para onde o valor foi — consumo ou caixa da Rede — e essa
// diferença não está nas pernas, está no tra_tipo e na categoria. É por isso que
// tra_categoria é coluna: é por ela que o fluxo de caixa mensal (spec §5) agrupa.
//
// A CONTRAPARTE É A FRONTEIRA. contas_de_destino() é a lista do que existe como
// destino legítimo, e o tipo confere se aquela conta cabe NESTE lançamento. Sem a
// segunda guarda, um POST trocando o id lançaria "despesa" contra a conta de um
// cestante, tirando dele dinheiro que ele não gastou — a conta de cestante nem está na
// lista, e é a primeira guarda que a barra; a segunda barra o produtor recebendo
// repasse, que está na lista e mesmo assim não é aquele movimento.
function lanca_movimento_nucleo($nuc_id, $tipo, $dt, $valor, $con_contraparte, $extras = array())
{
	// O QUE ESTÁ DO OUTRO LADO DE CADA LANÇAMENTO — e em metade deles não é conta de
	// ninguém. Quem recebe uma despesa é o motorista; quem faz uma doação é alguém sem
	// cadastro. Essas duas vão para a conta de contrapartida, que é encanamento.
	//
	// As outras duas têm conta de verdade, e ali a escolha decide quem recebeu: existem
	// duas contas da Rede a distinguir, e o produtor pago é um entre muitos.
	//
	// Antes, despesa e receita também pediam conta da Rede. Era coerente enquanto a Rede
	// absorvia o custo do núcleo; deixou de ser quando a despesa virou custo do próprio
	// núcleo, medido no resultado. E na tela pedia uma escolha que não significava nada.
	$contraparte_de = array(
		'despesa'            => 'contrapartida',
		'repasse'            => 'rede',
		'pagamento_produtor' => 'produtor',
		'receita'            => 'contrapartida',
	);
	if (!isset($contraparte_de[$tipo])) return null;

	// Categoria só existe em despesa. Gravá-la num repasse afirmaria uma classificação
	// que ninguém fez, e o relatório somaria como gasto dinheiro que só mudou de mãos.
	//
	// Fora de despesa a categoria é IGNORADA, não recusada — e a diferença importa. O
	// formulário mostra o campo só quando é despesa, mas esconder com display:none NÃO
	// impede o envio: o navegador manda mv_categoria em todo lançamento. Recusando, um
	// repasse feito pela tela era rejeitado sempre, e a mensagem ainda mandava conferir
	// campos que estavam certos. O que a guarda precisa garantir é que categoria não seja
	// GRAVADA fora de despesa, e zerá-la aqui garante isso melhor do que recusar.
	$categorias = categorias_de_despesa();
	$categoria  = isset($extras['categoria']) && (is_string($extras['categoria']) || is_int($extras['categoria']))
	            ? trim((string)$extras['categoria']) : '';

	if ($tipo === 'despesa') { if (!isset($categorias[$categoria])) return null; }
	else $categoria = '';

	if ($contraparte_de[$tipo] === 'contrapartida')
	{
		// A conta mandada é IGNORADA, não recusada. A tela não oferece o campo nestes
		// dois tipos, mas um POST antigo ou forjado ainda pode trazer um — e recusar
		// repetiria o erro do mv_categoria, em que um campo que a tela escondia
		// derrubava todo lançamento legítimo feito por ela.
		$con_contraparte = conta_de_contrapartida();
		if (!$con_contraparte) return null;
	}
	else
	{
		// Array onde se espera escalar é TypeError dentro do isset() no PHP 8: a tela
		// inteira cairia por causa de um `con_contraparte[]` no POST. Mesma guarda de
		// registra_pagamento().
		if (!is_string($con_contraparte) && !is_int($con_contraparte)) return null;

		$destinos = contas_de_destino();
		if ($destinos === null)                 return null;   // sem lista não há o que conferir
		if (!isset($destinos[$con_contraparte])) return null;   // conta que a lista não oferece

		if (tipo_de_conta($con_contraparte) !== $contraparte_de[$tipo]) return null;
	}

	$con_caixa = conta_do_nucleo($nuc_id);
	if (!$con_caixa) return null;
	if ((int)$con_caixa === (int)$con_contraparte) return null;   // caixa contra si mesmo

	$rotulo_padrao = array(
		'despesa'            => $categorias[$categoria !== '' ? $categoria : 'outros'],
		'repasse'            => 'repasse à Rede',
		'pagamento_produtor' => 'pagamento a produtor',
		'receita'            => 'outra receita',
	);
	$historico = isset($extras['historico']) && (is_string($extras['historico']) || is_int($extras['historico']))
	           ? trim((string)$extras['historico']) : '';
	if ($historico === '') $historico = $rotulo_padrao[$tipo];

	$campos = array(
		'comprovante' => isset($extras['comprovante']) ? $extras['comprovante'] : null,
		'obs'         => isset($extras['obs'])         ? $extras['obs']         : null,
	);
	if ($tipo === 'despesa') $campos['categoria'] = $categoria;

	// Quem recebeu, quando o outro lado não tem conta. Só faz sentido nos dois tipos que
	// usam a contrapartida: em repasse e pagamento a produtor o favorecido É a conta, e
	// gravar os dois diria a mesma coisa duas vezes — com risco de divergirem.
	$favorecido = isset($extras['favorecido']) && (is_string($extras['favorecido']) || is_int($extras['favorecido']))
	            ? trim((string)$extras['favorecido']) : '';
	if ($favorecido !== '' && $contraparte_de[$tipo] === 'contrapartida')
		$campos['favorecido'] = $favorecido;

	// Só 'receita' inverte: é o único dos quatro em que dinheiro ENTRA no caixa, e
	// portanto o único que aumenta o que o núcleo deve.
	if ($tipo === 'receita')
		return lanca_transacao($dt, $tipo, $con_caixa, $con_contraparte, $valor, $historico, $campos);

	return lanca_transacao($dt, $tipo, $con_contraparte, $con_caixa, $valor, $historico, $campos);
}


// O extrato do caixa, no mesmo formato do extrato do cestante: uma linha por
// lançamento, em ordem cronológica, com o saldo somado linha a linha na exibição —
// nunca gravado, pelo motivo que a spec detalha (lançamento retroativo obrigaria a
// reescrever todas as linhas seguintes).
//
// CONTRATO — null e array() NÃO são a mesma coisa, como no resto do módulo:
//   array  a consulta rodou; array() quer dizer caixa sem movimento
//   null   a consulta NÃO rodou, ou o núcleo não tem conta
// Numa tela de caixa, "não deu para perguntar" mostrado como "não há movimento" é a
// mesma mentira que o módulo existe para não contar.
function extrato_do_nucleo($nuc_id)
{
	$con = conta_do_nucleo($nuc_id);
	if (!$con) return null;

	// A contraparte sai do JOIN com a OUTRA perna da mesma transação. Sem ela a linha
	// diria "despesa 45,00" sem dizer contra quem, e duas contas da Rede tornam isso
	// ambíguo justamente onde a prestação de contas precisa ser específica.
	$sql = "SELECT t.tra_id, t.tra_dt, t.tra_tipo, t.tra_categoria, t.tra_historico, ";
	$sql.= "t.tra_comprovante, t.tra_dt_alteracao, t.tra_favorecido, l.lan_valor, ";
	$sql.= "co.con_tipo contra_tipo, co.con_nome contra_nome, ";
	$sql.= "n.nuc_nome_curto, f.forn_nome_curto, u.usr_nome_curto ";
	$sql.= "FROM lancamentos l ";
	$sql.= "JOIN transacoes t   ON t.tra_id = l.lan_tra ";
	$sql.= "LEFT JOIN lancamentos l2 ON l2.lan_tra = t.tra_id AND l2.lan_con <> l.lan_con ";
	$sql.= "LEFT JOIN contas co  ON co.con_id  = l2.lan_con ";
	$sql.= "LEFT JOIN nucleos n      ON n.nuc_id  = co.con_nuc ";
	$sql.= "LEFT JOIN fornecedores f ON f.forn_id = co.con_forn ";
	$sql.= "LEFT JOIN usuarios u     ON u.usr_id  = co.con_usr ";
	$sql.= "WHERE l.lan_con = " . prep_para_bd($con) . " ";
	$sql.= "ORDER BY t.tra_dt, t.tra_id";

	$res = executa_sql($sql);
	if (!$res) return null;              // ver o CONTRATO acima

	$categorias = categorias_de_despesa();
	$linhas = array();

	while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))
	{
		$cat = trim((string)$row['tra_categoria']);

		$contraparte = rotulo_de_contraparte($row);

		$linhas[] = array(
			'tra_id'      => (int)$row['tra_id'],
			'dt'          => $row['tra_dt'],
			'tipo'        => (string)$row['tra_tipo'],
			'categoria'   => $cat,
			// rótulo legível da categoria; '' quando o lançamento não tem categoria,
			// para a tela não precisar consultar o mapa a cada linha
			'categoria_rotulo' => isset($categorias[$cat]) ? $categorias[$cat] : '',
			'historico'   => (string)$row['tra_historico'],
			'contraparte' => $contraparte,
			// Quem recebeu, nos lançamentos cuja contraparte é a conta de encanamento:
			// ali "Contrapartida (despesas e receitas fora do sistema)" não diz nada a
			// ninguém, e o nome do motorista diz tudo.
			'favorecido'  => trim((string)$row['tra_favorecido']),
			'valor'       => round((float)$row['lan_valor'], 2),
			'comprovante' => (string)$row['tra_comprovante'],
			'editado_em'  => $row['tra_dt_alteracao'],
		);
	}

	// Mesma política do extrato do cestante: arredonda a cada passo, para todo saldo
	// exibido cair na grade de centavos.
	$saldo = 0.0;
	foreach ($linhas as $i => $linha)
	{
		$saldo = round($saldo + $linha['valor'], 2);
		$linhas[$i]['saldo'] = $saldo;
	}

	return $linhas;
}


// Quem pode lançar NO CAIXA de um núcleo — pergunta diferente de quem pode lançar
// pagamento. Responsável de núcleo lança no PRÓPRIO núcleo e em nenhum outro; finanças
// e administração, em qualquer um.
//
// A restrição ao próprio núcleo é o que separa esta função de pode_lancar_pagamento(),
// que não tem por onde ser específica — lá quem discrimina por núcleo é
// pode_ver_conta_de(), cestante a cestante. Aqui o alvo É o núcleo, então a regra mora
// junto dele.
function pode_lancar_no_caixa($nuc_id)
{
	if (!pode_ver_financeiro()) return false;

	if (!empty($_SESSION[PAP_RESP_FINANCAS]) || !empty($_SESSION[PAP_ADM])) return true;

	if (empty($_SESSION[PAP_RESP_NUCLEO]))       return false;
	if (!isset($_SESSION['usr.nuc']))            return false;
	if (!is_string($nuc_id) && !is_int($nuc_id)) return false;
	if (!ctype_digit((string)$nuc_id) || (int)$nuc_id <= 0) return false;

	return (int)$_SESSION['usr.nuc'] === (int)$nuc_id;
}


// Os núcleos ativos que já têm caixa aberto. Núcleo sem conta não tem extrato nem
// fluxo, e oferecê-lo no seletor levaria a uma tela que não faz nada.
//
// Devolve array de (nuc_id => nome), ou null quando a consulta não roda.
function nucleos_com_caixa()
{
	$sql = "SELECT n.nuc_id, n.nuc_nome_curto FROM nucleos n ";
	$sql.= "JOIN contas c ON c.con_nuc = n.nuc_id AND c.con_tipo = 'nucleo' AND c.con_archive = 0 ";
	$sql.= "WHERE n.nuc_archive = 0 ORDER BY n.nuc_nome_curto";

	$res = executa_sql($sql);
	if (!$res) return null;

	$lista = array();
	while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))
		$lista[(int)$row['nuc_id']] = (string)$row['nuc_nome_curto'];

	return $lista;
}


// O núcleo cujo caixa esta sessão pode operar nesta requisição. Devolve int, ou ""
// quando não há nenhum — e a tela é que decide o que fazer com o "".
//
// A REGRA MORA AQUI, E NUMA FUNÇÃO SÓ, porque a spec a exige IMPOSTA e não sugerida:
// quem responde por um núcleo não lê o pedido da URL, ponto. Com duas telas de caixa
// — o extrato e o fluxo — essa regra passaria a existir em duas cópias, e uma delas
// ficaria para trás no primeiro dia em que a outra mudasse. O padrão que a spec
// rejeita (cestantes.php:18, núcleo vindo de request_get) nasceu assim.
//
// $nuc_pedido é o que veio da URL, cru. Só string e int passam: ?nuc_id[]=1 entrega
// array, e comparar array com int é TypeError no PHP 8.
function nucleo_do_caixa_em_foco($nuc_pedido)
{
	$manda_em_todos = (!empty($_SESSION[PAP_RESP_FINANCAS]) || !empty($_SESSION[PAP_ADM]));
	$nuc_sessao     = isset($_SESSION['usr.nuc']) ? $_SESSION['usr.nuc'] : "";

	if (!is_string($nuc_pedido) && !is_int($nuc_pedido)) $nuc_pedido = "";

	// quem responde por um núcleo só nem olha o pedido
	$nuc = $manda_em_todos
	     ? (trim((string)$nuc_pedido) !== "" ? $nuc_pedido : $nuc_sessao)
	     : $nuc_sessao;

	if (!is_string($nuc) && !is_int($nuc)) $nuc = "";
	if (!ctype_digit((string)$nuc) || (int)$nuc <= 0) $nuc = "";

	// Finanças e administração sem núcleo próprio na sessão — ou com um arquivado —
	// cairiam numa recusa vinda do item de menu, que se leria como falta de permissão
	// e não é. Para quem responde por um só isto NÃO roda: escolher um núcleo por ela
	// seria abrir o caixa de outro.
	if ($nuc === "" && $manda_em_todos)
	{
		$lista = nucleos_com_caixa();
		if (is_array($lista) && count($lista))
		{
			$ids = array_keys($lista);
			$nuc = (string)$ids[0];
		}
	}

	if ($nuc === "") return "";

	// A escolha ainda passa pela regra de acesso, que começa por pode_ver_financeiro():
	// sem o papel Beta Tester o módulo inteiro continua fechado.
	if (!pode_lancar_no_caixa($nuc)) return "";

	return (int)$nuc;
}


// Fluxo de caixa mensal do núcleo (spec §5): quanto entrou, quanto saiu por categoria
// e o saldo do período, agrupado pela DATA DO FATO (tra_dt) e não pela data de
// registro — o núcleo lança na segunda o que aconteceu na sexta, e um relatório por
// data de registro jogaria o gasto no mês errado.
//
// SINAL. No razão, a perna do caixa é negativa quando dinheiro ENTRA (o núcleo passa a
// segurar dinheiro da Rede) e positiva quando sai. O relatório inverte isso na
// apresentação: quem lê uma prestação de contas lê "entrou 1.000", não "-1.000". Por
// isso 'entradas' e 'saidas' saem os dois positivos, e o sinal reaparece só no saldo.
//
// A classificação NÃO deduz o sentido a partir do tipo: soma entradas e saídas
// separadas no próprio SQL. Assim um 'ajuste' — que pode ir para os dois lados e chega
// na fatia seguinte — já cai no lugar certo, e nenhum lançamento some do relatório por
// não ter sido previsto aqui. Relatório que engole linha é a mentira que este módulo
// existe para não contar.
//
// CONTRATO — array, ou null quando a consulta não rodou ou o núcleo não tem caixa. Ano
// sem movimento devolve doze meses zerados, que é diferente de null.
function fluxo_de_caixa_mensal($nuc_id, $ano)
{
	$con = conta_do_nucleo($nuc_id);
	if (!$con) return null;

	$ano = (int)$ano;
	if ($ano < 2000 || $ano > 2200) return null;

	$de  = sprintf('%04d-01-01 00:00:00', $ano);
	$ate = sprintf('%04d-01-01 00:00:00', $ano + 1);

	// O que sobrou dos anos anteriores. Sem isto o acumulado de janeiro começaria do
	// zero e não bateria com o saldo do caixa — dois números para o mesmo dinheiro.
	$sql = "SELECT SUM(-l.lan_valor) abertura FROM lancamentos l ";
	$sql.= "JOIN transacoes t ON t.tra_id = l.lan_tra ";
	$sql.= "WHERE l.lan_con = " . prep_para_bd($con) . " AND t.tra_dt < " . prep_para_bd($de);

	$res = executa_sql($sql);
	if (!$res) return null;
	$row = mysqli_fetch_array($res, MYSQLI_ASSOC);
	$abertura = ($row && $row['abertura'] !== null) ? round((float)$row['abertura'], 2) : 0.0;

	$sql = "SELECT MONTH(t.tra_dt) mes, t.tra_tipo tipo, IFNULL(t.tra_categoria,'') categoria, ";
	$sql.= "SUM(CASE WHEN l.lan_valor < 0 THEN -l.lan_valor ELSE 0 END) entrou, ";
	$sql.= "SUM(CASE WHEN l.lan_valor > 0 THEN  l.lan_valor ELSE 0 END) saiu ";
	$sql.= "FROM lancamentos l JOIN transacoes t ON t.tra_id = l.lan_tra ";
	$sql.= "WHERE l.lan_con = " . prep_para_bd($con) . " ";
	$sql.= "AND t.tra_dt >= " . prep_para_bd($de) . " AND t.tra_dt < " . prep_para_bd($ate) . " ";
	$sql.= "GROUP BY mes, tipo, categoria";

	$res = executa_sql($sql);
	if (!$res) return null;

	$meses = range(1, 12);
	$zeros = array_fill_keys($meses, 0.0);

	// As linhas fixas nascem TODAS, mesmo zeradas: uma categoria que some do relatório
	// no mês em que ninguém gastou faria a tabela mudar de forma mês a mês, e quem
	// compara dois meses lado a lado perderia a referência.
	$categorias = categorias_de_despesa();
	$linhas = array();

	$linhas['pagamento'] = array('bloco' => 'entradas', 'chave' => 'pagamento',
		'rotulo' => 'pagamentos de cestantes', 'lado' => 'entrou', 'meses' => $zeros);
	$linhas['receita']   = array('bloco' => 'entradas', 'chave' => 'receita',
		'rotulo' => 'outras receitas', 'lado' => 'entrou', 'meses' => $zeros);

	foreach ($categorias as $chave => $rotulo)
		$linhas['despesa:' . $chave] = array('bloco' => 'despesas', 'chave' => 'despesa:' . $chave,
			'rotulo' => $rotulo, 'lado' => 'saiu', 'meses' => $zeros);

	$linhas['repasse'] = array('bloco' => 'repasses', 'chave' => 'repasse',
		'rotulo' => 'repasse à Rede', 'lado' => 'saiu', 'meses' => $zeros);
	$linhas['pagamento_produtor'] = array('bloco' => 'repasses', 'chave' => 'pagamento_produtor',
		'rotulo' => 'pagamento a produtor', 'lado' => 'saiu', 'meses' => $zeros);

	$entradas       = $zeros;
	$saidas         = $zeros;
	$total_despesas = $zeros;

	while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))
	{
		$mes    = (int)$row['mes'];
		$tipo   = (string)$row['tipo'];
		$cat    = (string)$row['categoria'];
		$entrou = round((float)$row['entrou'], 2);
		$saiu   = round((float)$row['saiu'], 2);

		if (!isset($zeros[$mes])) continue;   // MONTH() fora de 1..12 não existe, mas o laço não depende disso

		$entradas[$mes] = round($entradas[$mes] + $entrou, 2);
		$saidas[$mes]   = round($saidas[$mes] + $saiu, 2);

		// despesa com categoria que não existe mais no código não vira "outros" em
		// silêncio: vira linha própria, com o rótulo que está gravado
		$chave = ($tipo === 'despesa') ? 'despesa:' . $cat : $tipo;

		if ($tipo === 'despesa')
			$total_despesas[$mes] = round($total_despesas[$mes] + $saiu, 2);

		// Linha que este código não previu entra assim mesmo — nenhum lançamento some do
		// relatório por não ter sido antecipado aqui. Onde ela entra é que depende:
		//
		//   despesa de categoria APOSENTADA fica no bloco de DESPESAS. A lista de
		//   categorias é do código; a categoria fica gravada no banco. No dia em que
		//   alguém renomear ou remover uma das seis, as despesas antigas passam a ter
		//   categoria desconhecida — e mandá-las para 'outros' deixaria o valor delas
		//   dentro do total de despesas (somado logo acima) e fora das linhas que o
		//   compõem. Detalhe que não soma o próprio total é o defeito que faz um
		//   relatório de dinheiro perder a confiança de quem o lê.
		//
		//   tipo desconhecido — 'ajuste', quando chegar — vai para bloco próprio, para
		//   ficar visível que existe algo fora das linhas fixas.
		if (!isset($linhas[$chave]))
			$linhas[$chave] = array(
				'bloco'  => ($tipo === 'despesa') ? 'despesas' : 'outros',
				'chave'  => $chave,
				'rotulo' => ($tipo === 'despesa') ? ($cat !== '' ? $cat : 'sem categoria') : $chave,
				'lado'   => ($entrou > $saiu) ? 'entrou' : 'saiu',
				'meses'  => $zeros);

		$valor = ($linhas[$chave]['lado'] === 'entrou') ? $entrou : $saiu;
		$linhas[$chave]['meses'][$mes] = round($linhas[$chave]['meses'][$mes] + $valor, 2);
	}

	// totais de linha e saldos, na ordem em que a tela lê
	foreach ($linhas as $k => $linha)
		$linhas[$k]['total'] = round(array_sum($linha['meses']), 2);

	$saldo_mes = array();
	$acumulado = array();
	$corrente  = $abertura;

	foreach ($meses as $m)
	{
		$saldo_mes[$m] = round($entradas[$m] - $saidas[$m], 2);
		$corrente      = round($corrente + $saldo_mes[$m], 2);
		$acumulado[$m] = $corrente;
	}

	return array(
		'ano'             => $ano,
		'meses'           => $meses,
		'saldo_anterior'  => $abertura,
		'linhas'          => array_values($linhas),
		'entradas'        => $entradas,
		'saidas'          => $saidas,
		'total_despesas'  => $total_despesas,
		'saldo_mes'       => $saldo_mes,
		'saldo_acumulado' => $acumulado,
	);
}


// ============================================================================
// RATEIO DAS DESPESAS DA REDE
//
// A Rede tem custos que nenhum núcleo paga sozinho — hospedagem, quem responde por
// pedidos, quem responde por finanças. Para medir se um núcleo se sustenta, uma parte
// desses custos é carimbada nele.
//
// RATEIO É ATRIBUIÇÃO, NÃO DÍVIDA. Ninguém transfere dinheiro por causa dele: é um
// custo apontado ao núcleo para o relatório de resultado poder dizer "este núcleo se
// paga" ou "não se paga". Por isso NÃO é lançamento — mora em `rateios`, tabela
// própria, e o saldo de caixa de núcleo nenhum se mexe. Foi decisão explícita: o caixa
// do núcleo tem de continuar significando "quanto dinheiro está comigo".
//
// A despesa da Rede em si É lançamento, porque ali dinheiro saiu de verdade.
// ============================================================================


// As áreas da Rede. NÃO são as categorias do núcleo — para aquelas, ver
// categorias_de_despesa(). As duas listas têm seis itens e nomes parecidos, e passar uma
// no lugar da outra não daria erro nenhum: a despesa apenas ficaria com uma categoria
// que a tela não sabe rotular.
//
// A categoria aqui não classifica a NATUREZA do gasto (pessoal, infraestrutura,
// serviço) — classifica a ÁREA a que ele pertence, que é como a planilha da Rede já
// organiza: "Resp pedidos" é Pedidos, "Resp financeiro" é Finanças. A pergunta que ela
// responde é "de quem é este custo?". Por isso são diferentes das do núcleo: hospedagem
// não é "passagens".
function categorias_de_despesa_da_rede()
{
	return array(
		'mutirao'   => 'Mutirão',
		'logistica' => 'Logística',
		'pedidos'   => 'Pedidos',
		'financas'  => 'Finanças',
		'sistemas'  => 'Sistemas',
		'admin'     => 'Administrativo',
	);
}


// A ORDEM DAS DESPESAS DA REDE, como fragmento de SQL: por área, na sequência que a
// caixa de seleção oferece, e depois pela descrição.
//
// Numa função porque duas telas ordenam a mesma coisa — a lista de despesas e o rateio
// aberto no equilíbrio do núcleo. Duas cópias divergiriam na primeira área nova, e aí as
// mesmas linhas apareceriam em ordens diferentes em telas que se conferem uma contra a
// outra.
//
// A ordem das áreas é a de categorias_de_despesa_da_rede(), e não a alfabética: é a mesma
// que a caixa de seleção oferece. Categoria gravada que saiu do código cai no FIM —
// FIELD() devolve 0 para quem não está na lista, e sem a primeira cláusula ela viria
// antes de todas. Linha órfã tem de ser visível, não discreta.
function ordem_por_area_e_descricao($col_categoria, $col_historico)
{
	$ordem = array();
	foreach (array_keys(categorias_de_despesa_da_rede()) as $ck) $ordem[] = prep_para_bd($ck);
	$lista = implode(', ', $ordem);

	return "FIELD($col_categoria, $lista) = 0, FIELD($col_categoria, $lista), $col_historico";
}


// Quanto cada núcleo pesa no rateio por entrega. Núcleo semanal entrega 4 vezes ao mês
// e pesa 4; quinzenal 2; mensal 1.
//
// A quota sai de DADO em dois níveis, e não de lista escrita aqui: o padrão vem do tipo
// (`nucleotipos.nuct_quota_rateio`) e a exceção vem do próprio núcleo
// (`nucleos.nuc_quota_rateio`). Quota 0 fica de fora — é como Logística e Mutirão, que
// são núcleos sentinela: existem, recebem entrega e não rateiam. Escrever esses dois no
// código faria o terceiro sentinela nascer sem ninguém lembrar de incluí-lo.
//
// CONTRATO: array nuc_id => quota, ou null quando a consulta não roda.
function quotas_de_rateio()
{
	$sql = "SELECT n.nuc_id, IFNULL(n.nuc_quota_rateio, t.nuct_quota_rateio) quota ";
	$sql.= "FROM nucleos n JOIN nucleotipos t ON t.nuct_id = n.nuc_nuct ";
	$sql.= "WHERE n.nuc_archive = 0 AND IFNULL(n.nuc_quota_rateio, t.nuct_quota_rateio) > 0 ";
	$sql.= "ORDER BY n.nuc_nome_curto";

	$res = executa_sql($sql);
	if (!$res) return null;

	$quotas = array();
	while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))
		$quotas[(int)$row['nuc_id']] = (float)$row['quota'];

	return $quotas;
}


// A sugestão de rateio. É SUGESTÃO: quem lança confirma linha a linha e pode ajustar —
// foi decisão explícita, e é o que impede o rateio de virar um número que apareceu do
// nada na conta do núcleo.
//
// DUAS REGRAS, e a categoria não escolhe entre elas. Medido na planilha da Rede:
// `Logística` aparece nas duas listas — "retorno das embalagens" é custo fixo, "apoio
// logístico" escala com o número de entregas. Quem escolhe a regra é quem lança, olhando
// o item.
//
//   igual  divide pelo NÚMERO de núcleos; a quota não entra. Hospedagem custa o mesmo
//          tendo o núcleo uma ou quatro entregas por mês.
//   quota  proporcional às entregas: semanal paga 4x o que o mensal paga.
//
// A SOBRA DE CENTAVOS FICA COM A REDE, por decisão registrada — e é por isso que aqui
// se TRUNCA em vez de arredondar. Arredondando, a soma atribuída poderia ultrapassar o
// que a Rede gastou, e algum núcleo seria cobrado por centavo que a divisão não
// produziu. Truncando, a soma nunca passa do total e a diferença sobra para quem pagou.
//
// CONTRATO: array nuc_id => valor · array() quando não há núcleo que rateie ·
// null quando a regra não existe, o valor não é positivo, ou a consulta não roda.
function sugere_rateio($valor, $regra)
{
	$valor = round((float)$valor, 2);
	if ($valor <= 0) return null;

	$quotas = quotas_de_rateio();
	if ($quotas === null) return null;
	if (!count($quotas)) return array();

	// 1e-6 antes do truncamento: 2530 * 0.5 / 17 em ponto flutuante pode cair em
	// 74.40999999 em vez de 74.41, e truncar aí tiraria um centavo de quem não devia.
	$trunca = function ($x) { return floor($x * 100 + 1e-6) / 100; };

	$rateio = array();

	if ($regra === 'igual')
	{
		$n = count($quotas);
		foreach ($quotas as $nuc => $q) $rateio[$nuc] = $trunca($valor / $n);
		return $rateio;
	}

	if ($regra === 'quota')
	{
		$soma = array_sum($quotas);
		if ($soma <= 0) return array();
		foreach ($quotas as $nuc => $q) $rateio[$nuc] = $trunca($valor * $q / $soma);
		return $rateio;
	}

	return null;
}


// Lança uma despesa da Rede e grava, na mesma transação, a atribuição já confirmada.
//
// As duas coisas juntas de propósito: despesa gravada sem rateio ficaria invisível para
// os núcleos, e rateio gravado sem despesa seria custo sem origem — e é justamente a
// origem que o núcleo precisa ver para o rateio não parecer imposto.
//
// $rateio é array nuc_id => valor, JÁ CONFIRMADO por quem lança. Pode ser menor que a
// despesa (a sobra fica com a Rede) e pode ser array() (a Rede absorve tudo). O que não
// pode é somar MAIS do que a Rede gastou: isso criaria custo do nada, e o resultado do
// núcleo passaria a acusar prejuízo que ninguém teve.
//
// CONTRATO: tra_id, ou null quando não pôde virar lançamento.
function lanca_despesa_da_rede($dt, $categoria, $valor, $con_origem, $historico, $rateio,
                              $comprovante = null)
{
	$categorias = categorias_de_despesa_da_rede();
	$categoria  = (is_string($categoria) || is_int($categoria)) ? trim((string)$categoria) : '';
	if (!isset($categorias[$categoria])) return null;

	$valor = round((float)$valor, 2);
	if ($valor <= 0) return null;

	// A conta de origem é de onde o dinheiro saiu, e tem de ser da Rede: uma despesa da
	// Rede paga da conta de um cestante tiraria dele dinheiro que ele não gastou.
	if (!is_string($con_origem) && !is_int($con_origem)) return null;
	$destinos = contas_de_destino();
	if ($destinos === null)                return null;
	if (!isset($destinos[$con_origem]))    return null;
	if (tipo_de_conta($con_origem) !== 'rede') return null;

	$con_rede = conta_da_rede();
	if (!$con_rede) return null;

	if (!is_array($rateio)) return null;

	// Toda a conferência ANTES de escrever: recusa que já gravou metade deixaria
	// despesa sem rateio, que é pior do que recusa nenhuma.
	$quotas = quotas_de_rateio();
	if ($quotas === null) return null;

	$soma = 0.0;
	$limpo = array();
	foreach ($rateio as $nuc => $v)
	{
		if (!isset($quotas[(int)$nuc])) return null;   // inclusive sentinela e arquivado
		$v = round((float)$v, 2);
		if ($v < 0) return null;
		if ($v == 0) continue;                        // linha zerada não vira registro
		$soma += $v;
		$limpo[(int)$nuc] = $v;
	}
	if ($soma > $valor + 0.0001) return null;

	global $conn_link, $financeiro_em_transacao;
	$nossa = empty($financeiro_em_transacao);
	if ($nossa) { mysqli_begin_transaction($conn_link); $financeiro_em_transacao = true; }

	// A conta de origem entrega o dinheiro; a conta principal da Rede assume o custo.
	// É a mesma forma da despesa de núcleo, e por isso mesmo motivo: quem segurava o
	// dinheiro passa a segurar menos, e quem arca fica com o custo no saldo.
	// COMPROVANTE OPCIONAL, e opcional de verdade: a despesa costuma ser lançada quando
	// se monta o mês, e o extrato aparece depois. Exigir na criação faria a pessoa
	// inventar algo para o campo, ou adiar o lançamento — e lançamento adiado é o que
	// não acontece. Preenche-se depois, pela correção.
	$extras = array('categoria' => $categoria);
	$comprovante = (is_string($comprovante) || is_int($comprovante)) ? trim((string)$comprovante) : '';
	if ($comprovante !== '') $extras['comprovante'] = $comprovante;

	$tra = lanca_transacao($dt, 'despesa_rede', $con_rede, $con_origem, $valor, $historico, $extras);

	if (!$tra)
	{
		if ($nossa) { mysqli_rollback($conn_link); $financeiro_em_transacao = false; }
		return null;
	}

	foreach ($limpo as $nuc => $v)
	{
		$sql = "INSERT INTO rateios (rat_tra, rat_nuc, rat_valor) VALUES (";
		$sql.= prep_para_bd($tra) . ", " . prep_para_bd($nuc) . ", " . prep_para_bd($v) . ")";
		if (!executa_sql($sql))
		{
			if ($nossa) { mysqli_rollback($conn_link); $financeiro_em_transacao = false; }
			return null;
		}
	}

	if ($nossa) { mysqli_commit($conn_link); $financeiro_em_transacao = false; }

	return $tra;
}


// A Rede paga um produtor, direto da conta dela.
//
// NÃO É DESPESA, e por isso não passa por lanca_despesa_da_rede(). Despesa da Rede é
// custo de manter a Rede de pé, e por isso se rateia entre os núcleos. Pagar produtor é
// quitar o que a Rede JÁ DEVE pela mercadoria que ele entregou — o custo daquela
// mercadoria já foi para quem a recebeu, no débito do cestante. Rateando, cada núcleo
// seria cobrado de novo pelo mesmo produto, agora pela conta do produtor.
//
// Por isso não grava linha em `rateios`, e por isso o tipo continua sendo
// 'pagamento_produtor' — o mesmo do caixa do núcleo. O TIPO diz o que o dinheiro é; as
// CONTAS dizem quem o moveu. despesas_da_rede() filtra por tra_tipo = 'despesa_rede' e
// não o enxerga; rateios_do_nucleo() lê de `rateios` e também não; fluxo_de_caixa_mensal()
// e extrato_do_nucleo() são presos à conta do núcleo. Nada disto precisou mudar.
//
// A POSIÇÃO DO PRODUTOR pega o lançamento de graça: posicao_dos_produtores() soma o que
// caiu na conta dele sem olhar tipo nenhum, então pagamento da Rede e pagamento de núcleo
// abatem o mesmo saldo — que é como o dinheiro se comporta.
//
// AS PERNAS: produtor −X, conta de origem +X. Idênticas às do caixa do núcleo, trocando
// o caixa pela conta da Rede de onde saiu — quem segurava o dinheiro passa a segurar
// menos, e a conta do produtor registra o que ele recebeu.
//
// CONTRATO: tra_id, ou null quando não pôde virar lançamento. A recusa é silenciosa pelo
// mesmo motivo de lanca_movimento_nucleo(): quem sabe dizer o que faltou é a tela.
function lanca_pagamento_a_produtor_da_rede($dt, $con_produtor, $valor, $con_origem, $historico,
                                            $comprovante = null)
{
	$valor = round((float)$valor, 2);
	if ($valor <= 0) return null;

	// Array onde se espera escalar é TypeError dentro do isset() no PHP 8 — a tela
	// inteira cairia por causa de um `origem[]` no POST. Mesma guarda das irmãs.
	if (!is_string($con_origem)   && !is_int($con_origem))   return null;
	if (!is_string($con_produtor) && !is_int($con_produtor)) return null;

	$destinos = contas_de_destino();
	if ($destinos === null) return null;

	// As duas contas têm de estar na lista de destinos, e ser do tipo que se espera. A
	// origem da Rede pelo motivo de lanca_despesa_da_rede(): pagar produtor da conta de
	// um cestante tiraria dele dinheiro que ele não gastou.
	if (!isset($destinos[$con_origem]))   return null;
	if (!isset($destinos[$con_produtor])) return null;
	if (tipo_de_conta($con_origem)   !== 'rede')     return null;
	if (tipo_de_conta($con_produtor) !== 'produtor') return null;

	$historico = (is_string($historico) || is_int($historico)) ? trim((string)$historico) : '';
	if ($historico === '') $historico = 'pagamento a produtor';

	// COMPROVANTE, como no pagamento de cestante: link do extrato, ou o que baste para
	// achar a transferência depois. É o que transforma "consta que pagamos" em "aqui
	// está" — e quem cobra de novo por engano é justamente quem não achou o registro.
	// Opcional: pagamento em dinheiro não tem link, e exigir um faria inventar.
	$extras = array();
	$comprovante = (is_string($comprovante) || is_int($comprovante)) ? trim((string)$comprovante) : '';
	if ($comprovante !== '') $extras['comprovante'] = $comprovante;

	return lanca_transacao($dt, 'pagamento_produtor', $con_produtor, $con_origem, $valor,
		$historico, $extras);
}


// O que foi carimbado num núcleo no período, com a despesa que o originou.
//
// A origem vem junto porque é ela que faz o rateio ser lido como conta e não como
// imposto: o núcleo vê que aquela parcela é hospedagem mais papéis centrais, item por
// item, e não um número que apareceu.
//
// CONTRATO: array (vazio quando não houve rateio), ou null quando a consulta não roda.
function rateios_do_nucleo($nuc_id, $de, $ate)
{
	$con_rede_rat = conta_da_rede();
	if (!$con_rede_rat) return null;

	// O TOTAL DA DESPESA VEM JUNTO. Sem ele o núcleo vê o pedaço que lhe coube e não tem
	// como julgá-lo: "R$ 82,00 de hospedagem" não diz se é a conta inteira ou uma fatia,
	// e é a fatia que faz a conversa ser outra. Sai da perna de custo — a conta principal
	// da Rede —, que é o mesmo lugar de onde despesas_da_rede() lê o valor.
	$sql = "SELECT t.tra_id, t.tra_dt, t.tra_categoria, t.tra_historico, r.rat_valor, ";
	$sql.= "ABS(l.lan_valor) total ";
	$sql.= "FROM rateios r JOIN transacoes t ON t.tra_id = r.rat_tra ";
	$sql.= "LEFT JOIN lancamentos l ON l.lan_tra = t.tra_id AND l.lan_con = " . prep_para_bd($con_rede_rat) . " ";
	$sql.= "WHERE r.rat_nuc = " . prep_para_bd($nuc_id) . " ";
	$sql.= "AND t.tra_dt >= " . prep_para_bd($de) . " AND t.tra_dt < " . prep_para_bd($ate) . " ";
	// MESMA ORDEM da lista de despesas da Rede: as duas telas se conferem uma contra a
	// outra, e ordens diferentes fariam procurar linha a linha.
	$sql.= "ORDER BY " . ordem_por_area_e_descricao('t.tra_categoria', 't.tra_historico');
	$sql.= ", t.tra_dt, t.tra_id";

	$res = executa_sql($sql);
	if (!$res) return null;

	$categorias = categorias_de_despesa_da_rede();
	$linhas = array();

	while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))
	{
		$cat = trim((string)$row['tra_categoria']);
		$linhas[] = array(
			'tra_id'    => (int)$row['tra_id'],
			'dt'        => $row['tra_dt'],
			'categoria' => $cat,
			'categoria_rotulo' => isset($categorias[$cat]) ? $categorias[$cat] : $cat,
			'historico' => (string)$row['tra_historico'],
			'valor'     => round((float)$row['rat_valor'], 2),
			// null quando a perna de custo não está lá — transação meia gravada. A tela
			// mostra travessão em vez de inventar um total.
			'total'     => ($row['total'] === null) ? null : round((float)$row['total'], 2),
		);
	}

	return $linhas;
}


// Resultado mensal do núcleo — o "ponto de equilíbrio" da planilha. Responde a única
// pergunta que o caixa não responde: este núcleo se paga?
//
// RECEITA é o que o núcleo gera de próprio. NÃO é o que os cestantes pagam: quase tudo
// que eles pagam é do produtor e passa direto. Sobra o que a Rede cobra a mais —
//
//   associação          a anuidade, que entra como chamada de tipo Associação
//   taxa                o percentual da chamada sobre o pedido do associado
//   margem não assoc.   prod_valor_venda_margem menos a compra, para quem não é sócio
//   margem no produto   venda menos compra; quase sempre zero, porque a Rede repassa a custo
//
// Medido na base em 12 meses: associação e taxa sustentam o sistema; as duas margens
// de produto somam quase nada no ano inteiro.
//
// CUSTO tem duas metades: as despesas que o próprio núcleo lançou (motorista,
// passagens) e o RATEIO — a parte dos custos fixos da Rede carimbada nele.
//
// A conta é POR ENTREGA, não por pagamento: usa o que foi entregue no mês, e não o que
// foi recebido. Assim o resultado não oscila por causa de quem pagou atrasado, que é
// exatamente o que a aba de ponto de equilíbrio da planilha já faz.
//
// CONTRATO: array, ou null quando o núcleo não existe, o mês é inválido, ou a consulta
// não roda. Mês sem movimento devolve zeros — que é diferente de null.
function resultado_do_nucleo($nuc_id, $ano, $mes)
{
	$ano = (int)$ano;
	$mes = (int)$mes;
	if ($mes < 1 || $mes > 12)      return null;
	if ($ano < 2000 || $ano > 2200) return null;

	$res = executa_sql("SELECT nuc_id FROM nucleos WHERE nuc_id = " . prep_para_bd($nuc_id));
	if (!$res) return null;
	if (!mysqli_fetch_array($res, MYSQLI_ASSOC)) return null;

	$de  = sprintf('%04d-%02d-01 00:00:00', $ano, $mes);
	$ate = ($mes == 12) ? sprintf('%04d-01-01 00:00:00', $ano + 1)
	                    : sprintf('%04d-%02d-01 00:00:00', $ano, $mes + 1);

	// ---- receita, da mesma varredura de pedidos que o débito derivado usa ----
	$sql = "SELECT ";
	$sql.= "SUM(CASE WHEN pt.prodt_nome LIKE 'Associa%' ";
	$sql.= "     THEN pr.prod_valor_venda * pp.pedprod_entregue ELSE 0 END) associacao, ";
	$sql.= "SUM(CASE WHEN pt.prodt_nome NOT LIKE 'Associa%' AND p.ped_usr_associado <> '0' ";
	$sql.= "     THEN pr.prod_valor_venda * pp.pedprod_entregue * c.cha_taxa_percentual ELSE 0 END) taxa, ";
	$sql.= "SUM(CASE WHEN pt.prodt_nome NOT LIKE 'Associa%' AND p.ped_usr_associado = '0' ";
	$sql.= "     THEN (pr.prod_valor_venda_margem - pr.prod_valor_compra) * pp.pedprod_entregue ELSE 0 END) margem_nao_assoc, ";
	$sql.= "SUM(CASE WHEN pt.prodt_nome NOT LIKE 'Associa%' AND p.ped_usr_associado <> '0' ";
	$sql.= "     THEN (pr.prod_valor_venda - pr.prod_valor_compra) * pp.pedprod_entregue ELSE 0 END) margem_produto ";
	$sql.= "FROM pedidos p ";
	$sql.= "JOIN pedidoprodutos pp  ON pp.pedprod_ped = p.ped_id ";
	$sql.= "JOIN chamadas c         ON c.cha_id = p.ped_cha ";
	$sql.= "JOIN produtotipos pt    ON pt.prodt_id = c.cha_prodt ";
	$sql.= "JOIN chamadaprodutos cp ON cp.chaprod_cha = p.ped_cha AND cp.chaprod_prod = pp.pedprod_prod ";
	$sql.= "JOIN produtos pr        ON pr.prod_id = pp.pedprod_prod ";
	// O recorte é ped_nuc, e NÃO usr_nuc: quem troca de núcleo deixa os pedidos antigos
	// no núcleo antigo, e a receita daquela entrega é de quem a fez. Medido: juntar pelo
	// núcleo atual dá números absurdos — um núcleo apareceu com 95% de quebra.
	$sql.= "WHERE p.ped_nuc = " . prep_para_bd($nuc_id) . " AND p.ped_fechado = 1 ";
	$sql.= "AND cp.chaprod_disponibilidade <> '0' ";
	$sql.= "AND pr.prod_ini_validade <= c.cha_dt_entrega AND pr.prod_fim_validade >= c.cha_dt_entrega ";
	$sql.= "AND c.cha_dt_entrega >= " . prep_para_bd($de) . " AND c.cha_dt_entrega < " . prep_para_bd($ate);

	$res = executa_sql($sql);
	if (!$res) return null;
	$row = mysqli_fetch_array($res, MYSQLI_ASSOC);

	$v = function ($x) { return ($x === null) ? 0.0 : round((float)$x, 2); };
	$receita = array(
		'associacao'           => $v($row ? $row['associacao'] : null),
		'taxa'                 => $v($row ? $row['taxa'] : null),
		'margem_nao_associado' => $v($row ? $row['margem_nao_assoc'] : null),
		'margem_produto'       => $v($row ? $row['margem_produto'] : null),
		// doação, rendimento de conta: receita do PRÓPRIO núcleo, por decisão do time.
		// Preenchida logo abaixo, junto das despesas, porque sai da mesma varredura do
		// caixa — e a chave nasce aqui para a ordem das linhas na tela ser esta.
		'outras'               => 0.0,
	);

	// ---- o que o próprio núcleo lançou no caixa: despesas e outras receitas ----
	$proprias = array();
	$con = conta_do_nucleo($nuc_id);

	if ($con)
	{
		// Na conta-caixa a perna da despesa é POSITIVA — o núcleo deixou de segurar
		// aquele dinheiro — e a da receita é NEGATIVA, porque entrou. Aqui as duas viram
		// número positivo: custo e receita, que é o que as palavras querem dizer.
		$sql = "SELECT t.tra_tipo tipo, IFNULL(t.tra_categoria,'') cat, SUM(l.lan_valor) v ";
		$sql.= "FROM lancamentos l JOIN transacoes t ON t.tra_id = l.lan_tra ";
		$sql.= "WHERE l.lan_con = " . prep_para_bd($con) . " AND t.tra_tipo IN ('despesa','receita') ";
		$sql.= "AND t.tra_dt >= " . prep_para_bd($de) . " AND t.tra_dt < " . prep_para_bd($ate) . " ";
		$sql.= "GROUP BY tipo, cat";

		$res = executa_sql($sql);
		if (!$res) return null;

		while ($r = mysqli_fetch_array($res, MYSQLI_ASSOC))
		{
			if ((string)$r['tipo'] === 'receita')
				$receita['outras'] = round($receita['outras'] - (float)$r['v'], 2);
			else
				$proprias[(string)$r['cat']] = round((float)$r['v'], 2);
		}
	}

	$receita['total'] = round(array_sum($receita), 2);

	// ---- rateio: a parte dos custos da Rede carimbada neste núcleo ----
	$rateio = rateios_do_nucleo($nuc_id, $de, $ate);
	if ($rateio === null) return null;

	$total_rateio = 0.0;
	foreach ($rateio as $l) $total_rateio = round($total_rateio + $l['valor'], 2);

	$total_proprias = round(array_sum($proprias), 2);
	$custo_total    = round($total_proprias + $total_rateio, 2);
	$resultado      = round($receita['total'] - $custo_total, 2);

	// Estado, e não só o número: `if ($resultado > 0)` numa tela trataria null como
	// deficitário, e null aqui não acontece — mas o estado deixa a tela dizer a palavra
	// sem refazer a comparação, que é onde os três casos costumam virar dois.
	$situacao = ($resultado < -0.005) ? 'deficitario'
	          : (($resultado > 0.005) ? 'superavitario' : 'equilibrio');

	return array(
		'ano' => $ano, 'mes' => $mes,
		'receita' => $receita,
		'custo'   => array(
			'proprias'       => $proprias,
			'total_proprias' => $total_proprias,
			'rateio'         => $rateio,
			'total_rateio'   => $total_rateio,
			'total'          => $custo_total,
		),
		'resultado' => $resultado,
		'situacao'  => $situacao,
	);
}


// As despesas da Rede de um período, com quanto de cada uma já foi carimbado nos
// núcleos. A diferença entre o valor e o rateado é o que a Rede absorveu — e ela
// aparece na tela de propósito: rateio silenciosamente incompleto viraria custo que
// ninguém vê e resultado de núcleo bom demais.
//
// CONTRATO: array (vazio quando não houve despesa), ou null quando a consulta não roda.
function despesas_da_rede($de, $ate)
{
	// O valor da despesa é lido da perna da CONTA PRINCIPAL da Rede — a que carrega o
	// custo —, e a junção é por conta e não por sinal.
	//
	// Por sinal funcionaria hoje: lanca_transacao (:50) é o único a escrever em
	// `lancamentos`, e sempre escreve duas pernas, uma negativa e uma positiva. Mas o
	// schema aceita três, e uma transação de três pernas somando zero pode ter DUAS
	// negativas — aí a junção duplicaria a linha e o valor sairia errado. Este número é
	// o TETO de todo rateio (redefine_rateio confere a soma contra ele), então um valor
	// errado aqui deixaria carimbar nos núcleos mais do que a Rede gastou.
	$con_rede = conta_da_rede();
	if (!$con_rede) return null;

	$sql = "SELECT t.tra_id, t.tra_dt, t.tra_categoria, t.tra_historico, t.tra_comprovante, ";
	$sql.= "ABS(l.lan_valor) valor, ";
	$sql.= "(SELECT IFNULL(SUM(r.rat_valor),0) FROM rateios r WHERE r.rat_tra = t.tra_id) rateado, ";
	// DE ONDE O DINHEIRO SAIU: é a outra perna, a que não é a conta principal da Rede.
	// Quem repete o mês anterior precisa dela — a mesma despesa costuma sair sempre da
	// mesma conta, e reescolher doze vezes é convite a errar numa.
	$sql.= "(SELECT l2.lan_con FROM lancamentos l2 WHERE l2.lan_tra = t.tra_id ";
	$sql.= "  AND l2.lan_con <> " . prep_para_bd($con_rede) . " LIMIT 1) origem ";
	$sql.= "FROM transacoes t ";
	$sql.= "JOIN lancamentos l ON l.lan_tra = t.tra_id AND l.lan_con = " . prep_para_bd($con_rede) . " ";
	$sql.= "WHERE t.tra_tipo = 'despesa_rede' ";
	$sql.= "AND t.tra_dt >= " . prep_para_bd($de) . " AND t.tra_dt < " . prep_para_bd($ate) . " ";

	// POR ÁREA, DEPOIS POR DESCRIÇÃO. A lista é conferida contra a planilha da Rede, que
	// agrupa por área — e por data as mesmas catorze linhas apareciam embaralhadas todo
	// mês, obrigando a procurar cada uma. Dentro da área, a descrição põe lado a lado a
	// mesma despesa de meses diferentes quando se olha um período maior.
	//
	// A ordem das áreas é a que categorias_de_despesa_da_rede() define, e não a
	// alfabética: é a mesma que a caixa de seleção oferece, e duas ordens diferentes para
	// a mesma lista fazem quem confere perder o lugar. Categoria gravada que saiu do
	// código cai no fim, em vez de sumir no meio.
	$sql.= "ORDER BY " . ordem_por_area_e_descricao('t.tra_categoria', 't.tra_historico');
	$sql.= ", t.tra_dt, t.tra_id";

	$res = executa_sql($sql);
	if (!$res) return null;

	$categorias = categorias_de_despesa_da_rede();
	$linhas = array();

	while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))
	{
		$cat     = trim((string)$row['tra_categoria']);
		$valor   = round((float)$row['valor'], 2);
		$rateado = round((float)$row['rateado'], 2);

		$linhas[] = array(
			'tra_id'    => (int)$row['tra_id'],
			'dt'        => $row['tra_dt'],
			'categoria' => $cat,
			'categoria_rotulo' => isset($categorias[$cat]) ? $categorias[$cat] : $cat,
			'historico' => (string)$row['tra_historico'],
			'comprovante' => (string)$row['tra_comprovante'],
			// null quando a outra perna não existe — transação de três pernas, ou meia
			// gravada. A tela cai no primeiro destino em vez de mostrar campo vazio.
			'origem'    => ($row['origem'] === null) ? null : (int)$row['origem'],
			'valor'     => $valor,
			'rateado'   => $rateado,
			// o que a Rede absorveu: sobra de centavos, ou parte que ninguém carimbou
			'sobra'     => round($valor - $rateado, 2),
		);
	}

	return $linhas;
}


// O rateio de uma despesa já lançada, como nuc_id => valor.
function rateio_da_despesa($tra_id)
{
	$res = executa_sql("SELECT rat_nuc, rat_valor FROM rateios WHERE rat_tra = " . prep_para_bd($tra_id));
	if (!$res) return null;

	$r = array();
	while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))
		$r[(int)$row['rat_nuc']] = round((float)$row['rat_valor'], 2);

	return $r;
}


// Qual regra de rateio produziu esta atribuição.
//
// A REGRA NÃO FICA GRAVADA — só o resultado dela. Guardá-la exigiria uma coluna nova, e
// não é preciso: sugere_rateio() é determinística, então basta perguntar a ela o que cada
// regra daria para este valor e ver qual bate com o que está gravado.
//
// DEVOLVE '' QUANDO NÃO DÁ PARA SABER, e isso não é falha: acontece quando alguém
// ajustou o rateio à mão, quando as quotas mudaram desde o lançamento, ou quando a
// despesa não foi rateada. Nesses casos a regra de fato se perdeu, e chutar uma faria a
// tela afirmar uma escolha que ninguém fez — o mesmo defeito da área que vinha
// pré-selecionada só por ser a primeira da lista.
//
// Quando as duas regras dão o mesmo resultado — todos os núcleos com a mesma quota — a
// primeira ganha. As duas estariam certas, e a diferença não existe.
function regra_do_rateio($valor, $rateio)
{
	if (!is_array($rateio) || !count($rateio)) return '';

	foreach (array('igual', 'quota') as $regra)
	{
		$esperado = sugere_rateio($valor, $regra);
		if (!is_array($esperado)) continue;

		$bate = true;
		foreach ($esperado as $nuc => $v)
		{
			$gravado = isset($rateio[$nuc]) ? (float)$rateio[$nuc] : 0.0;
			if (abs($gravado - (float)$v) > 0.005) { $bate = false; break; }
		}
		// atribuição que carrega núcleo fora da sugestão não veio da sugestão
		if ($bate && count($rateio) === count(array_filter($esperado, function ($v) { return $v > 0; })))
			return $regra;
	}

	return '';
}


// Até quando uma despesa da Rede ainda pode ser corrigida no lugar.
//
// DO PRIMEIRO DIA DO MÊS ANTERIOR EM DIANTE. A janela existe porque as duas situações
// são diferentes de verdade: despesa deste mês ou do passado ainda está sendo trabalhada,
// e um erro de digitação nela se conserta digitando de novo. Despesa velha já entrou em
// resultado que o núcleo leu, e reescrevê-la mudaria em silêncio um número sobre o qual
// alguém já conversou — ali o certo é lançar um ajuste, que aparece.
//
// Dois meses, e não trinta dias: quem fecha o mês trabalha nos primeiros dias do
// seguinte, e uma janela de trinta dias fecharia a porta no dia 1º, justamente quando a
// pessoa senta para conferir o mês que acabou.
function despesa_da_rede_editavel($tra_dt)
{
	$t = strtotime((string)$tra_dt);
	if ($t === false) return false;

	return date('Y-m', $t) >= date('Y-m', strtotime('first day of last month'));
}


// Corrige uma despesa da Rede no lugar: valor, data, área, conta de origem, descrição, e
// o rateio junto.
//
// POR QUE ESTA REESCREVE O QUE redefine_rateio() NÃO REESCREVE. A regra do módulo é que
// dinheiro lançado não se reescreve, e ela continua valendo para o passado — é o que a
// janela de despesa_da_rede_editavel() protege. Dentro da janela a despesa ainda está
// sendo montada, e obrigar um lançamento de ajuste para consertar um zero a mais
// produziria duas linhas onde houve um erro de digitação, mais um rateio de ajuste
// negativo para cada núcleo. O remédio seria pior.
//
// O RATEIO É REFEITO JUNTO, e tem de ser: ele é uma fração do valor, e deixar o antigo
// depois de mudar o valor daria uma despesa cujo rateio não fecha com ela — que é
// exatamente o estado que a coluna "fica com a Rede" existe para denunciar.
//
// CARIMBA QUEM ALTEROU, como edita_descricao_transacao(): sem isso a linha continuaria
// dizendo que foi registrada por quem a criou, com o valor de outra pessoa.
//
// CONTRATO: true, ou false quando não pôde — fora da janela, transação que não é despesa
// da Rede, valor não positivo, conta de origem inválida, rateio maior que o valor.
function edita_despesa_da_rede($tra_id, $dt, $categoria, $valor, $con_origem, $historico, $rateio,
                              $comprovante = null)
{
	if (!is_numeric($tra_id) || (int)$tra_id <= 0) return false;
	$tra_id = (int)$tra_id;

	$categorias = categorias_de_despesa_da_rede();
	$categoria  = (is_string($categoria) || is_int($categoria)) ? trim((string)$categoria) : '';
	if (!isset($categorias[$categoria])) return false;

	$valor = round((float)$valor, 2);
	if ($valor <= 0) return false;

	if (!is_string($con_origem) && !is_int($con_origem)) return false;
	$destinos = contas_de_destino();
	if ($destinos === null)             return false;
	if (!isset($destinos[$con_origem])) return false;
	if (tipo_de_conta($con_origem) !== 'rede') return false;

	$con_rede = conta_da_rede();
	if (!$con_rede) return false;
	if ((int)$con_rede === (int)$con_origem) return false;

	if (!is_array($rateio)) return false;

	// A DESPESA TEM DE SER O QUE SE DIZ QUE É, e estar na janela. Os dois conferidos no
	// banco, e não no que a tela mandou: a tela esconde o botão, o POST não.
	$res = executa_sql("SELECT tra_dt, tra_tipo FROM transacoes WHERE tra_id = " . prep_para_bd($tra_id));
	if (!$res) return false;
	$row = mysqli_fetch_array($res, MYSQLI_ASSOC);
	if (!$row) return false;
	if ((string)$row['tra_tipo'] !== 'despesa_rede') return false;
	if (!despesa_da_rede_editavel($row['tra_dt']))   return false;

	// EXATAMENTE DUAS PERNAS. O invariante do módulo é esse, e reescrever pelo valor uma
	// transação com três deixaria as outras intactas e a soma fora de zero.
	$res = executa_sql("SELECT COUNT(*) n FROM lancamentos WHERE lan_tra = " . prep_para_bd($tra_id));
	if (!$res) return false;
	$rw = mysqli_fetch_array($res, MYSQLI_ASSOC);
	if (!$rw || (int)$rw['n'] !== 2) return false;

	// Toda a conferência do rateio ANTES de escrever, como em lanca_despesa_da_rede().
	$quotas = quotas_de_rateio();
	if ($quotas === null) return false;

	$soma = 0.0; $limpo = array();
	foreach ($rateio as $nuc => $v)
	{
		if (!isset($quotas[(int)$nuc])) return false;
		$v = round((float)$v, 2);
		if ($v < 0) return false;
		if ($v == 0) continue;
		$soma += $v;
		$limpo[(int)$nuc] = $v;
	}
	if ($soma > $valor + 0.0001) return false;

	global $conn_link, $financeiro_em_transacao;
	$nossa = empty($financeiro_em_transacao);
	if ($nossa) { mysqli_begin_transaction($conn_link); $financeiro_em_transacao = true; }

	$usr = isset($_SESSION['usr.id']) ? prep_para_bd($_SESSION['usr.id']) : "0";

	$falhou = false;

	// O comprovante costuma chegar DEPOIS da despesa, e é por aqui que ele entra. Vazio
	// grava NULL, e não string vazia: "ainda não veio" e "veio em branco" seriam a mesma
	// coisa na tela, mas só a primeira é verdade.
	$comprovante = (is_string($comprovante) || is_int($comprovante)) ? trim((string)$comprovante) : '';

	$sql = "UPDATE transacoes SET tra_dt = " . prep_para_bd($dt) . ", ";
	$sql.= "tra_historico = " . prep_para_bd(trim((string)$historico)) . ", ";
	$sql.= "tra_comprovante = " . ($comprovante === '' ? "NULL" : prep_para_bd($comprovante)) . ", ";
	$sql.= "tra_categoria = " . prep_para_bd($categoria) . ", ";
	$sql.= "tra_usr_alteracao = " . $usr . ", tra_dt_alteracao = NOW() ";
	$sql.= "WHERE tra_id = " . prep_para_bd($tra_id);
	if (executa_sql($sql) !== true) $falhou = true;

	// As duas pernas são reescritas por inteiro — conta e valor —, porque a conta de
	// origem também pode ter mudado. A perna do custo é a da conta principal da Rede; a
	// outra é a de onde o dinheiro saiu.
	if (!$falhou)
	{
		if (executa_sql("DELETE FROM lancamentos WHERE lan_tra = " . prep_para_bd($tra_id)) !== true)
			$falhou = true;
	}
	if (!$falhou)
	{
		foreach (array(array($con_rede, -$valor), array($con_origem, $valor)) as $perna)
		{
			$sql = "INSERT INTO lancamentos (lan_tra, lan_con, lan_valor) VALUES (";
			$sql.= prep_para_bd($tra_id) . ", " . prep_para_bd($perna[0]) . ", " . prep_para_bd($perna[1]) . ")";
			if (!executa_sql($sql)) { $falhou = true; break; }
		}
	}

	if (!$falhou)
	{
		if (executa_sql("DELETE FROM rateios WHERE rat_tra = " . prep_para_bd($tra_id)) !== true)
			$falhou = true;
	}
	if (!$falhou)
	{
		foreach ($limpo as $nuc => $v)
		{
			$sql = "INSERT INTO rateios (rat_tra, rat_nuc, rat_valor) VALUES (";
			$sql.= prep_para_bd($tra_id) . ", " . prep_para_bd($nuc) . ", " . prep_para_bd($v) . ")";
			if (!executa_sql($sql)) { $falhou = true; break; }
		}
	}

	if ($falhou)
	{
		if ($nossa) { mysqli_rollback($conn_link); $financeiro_em_transacao = false; }
		return false;
	}

	if ($nossa) { mysqli_commit($conn_link); $financeiro_em_transacao = false; }
	return true;
}


// Refaz a atribuição de uma despesa já lançada. A despesa em si — valor, data, conta —
// NÃO muda por AQUI: quem corrige isso é edita_despesa_da_rede(), e só dentro da janela
// em que a despesa ainda está sendo trabalhada. O que se corrige aqui é para quem o custo
// foi apontado, que é decisão e não fato consumado — e isso vale em qualquer data.
//
// Substitui em bloco em vez de editar linha a linha: o conjunto tem de continuar
// somando no máximo o valor da despesa, e conferir isso linha a linha deixaria estados
// intermediários inválidos gravados.
//
// CONTRATO: true, ou false quando não pôde.
function redefine_rateio($tra_id, $rateio)
{
	if (!is_array($rateio)) return false;

	// Pela CONTA e não pelo sinal, pelo motivo detalhado em despesas_da_rede(): este
	// valor é o teto que a soma do rateio não pode passar, e lê-lo errado deixaria
	// carimbar nos núcleos mais do que a Rede gastou.
	$con_rede_teto = conta_da_rede();
	if (!$con_rede_teto) return false;

	$res = executa_sql("SELECT tra_id, tra_tipo, ABS(lan_valor) valor FROM transacoes "
	     . "JOIN lancamentos ON lan_tra = tra_id AND lan_con = " . prep_para_bd($con_rede_teto) . " "
	     . "WHERE tra_id = " . prep_para_bd($tra_id));
	if (!$res) return false;

	$row = mysqli_fetch_array($res, MYSQLI_ASSOC);
	if (!$row || $row['tra_tipo'] !== 'despesa_rede') return false;

	$valor  = round((float)$row['valor'], 2);
	$quotas = quotas_de_rateio();
	if ($quotas === null) return false;

	$soma  = 0.0;
	$limpo = array();
	foreach ($rateio as $nuc => $v)
	{
		if (!isset($quotas[(int)$nuc])) return false;
		$v = round((float)$v, 2);
		if ($v < 0) return false;
		if ($v == 0) continue;
		$soma += $v;
		$limpo[(int)$nuc] = $v;
	}
	if ($soma > $valor + 0.0001) return false;

	global $conn_link, $financeiro_em_transacao;
	$nossa = empty($financeiro_em_transacao);
	if ($nossa) { mysqli_begin_transaction($conn_link); $financeiro_em_transacao = true; }

	if (!executa_sql("DELETE FROM rateios WHERE rat_tra = " . prep_para_bd($tra_id)))
	{
		if ($nossa) { mysqli_rollback($conn_link); $financeiro_em_transacao = false; }
		return false;
	}

	foreach ($limpo as $nuc => $v)
	{
		$sql = "INSERT INTO rateios (rat_tra, rat_nuc, rat_valor) VALUES (";
		$sql.= prep_para_bd($tra_id) . ", " . prep_para_bd($nuc) . ", " . prep_para_bd($v) . ")";
		if (!executa_sql($sql))
		{
			if ($nossa) { mysqli_rollback($conn_link); $financeiro_em_transacao = false; }
			return false;
		}
	}

	if ($nossa) { mysqli_commit($conn_link); $financeiro_em_transacao = false; }

	return true;
}


// Os núcleos e suas quotas, para a tela em que Finanças as edita. Traz os ARQUIVADOS
// também, marcados: um núcleo que volta a ativo precisa reaparecer com a quota que
// tinha, e escondê-lo faria a quota ressurgir sem ninguém ter olhado para ela.
//
// Devolve, por núcleo: o tipo, a quota SUGERIDA pelo tipo e a quota que VALE hoje. As
// duas separadas de propósito — a tela mostra a sugestão ao lado do campo para quem
// edita saber do que está discordando.
//
// CONTRATO: array, ou null quando a consulta não roda.
function nucleos_e_quotas()
{
	$sql = "SELECT n.nuc_id, n.nuc_nome_curto, n.nuc_archive, t.nuct_nome, ";
	$sql.= "t.nuct_quota_rateio sugerida, n.nuc_quota_rateio propria ";
	$sql.= "FROM nucleos n JOIN nucleotipos t ON t.nuct_id = n.nuc_nuct ";
	$sql.= "ORDER BY n.nuc_archive, n.nuc_nome_curto";

	$res = executa_sql($sql);
	if (!$res) return null;

	$lista = array();
	while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))
	{
		$sugerida = (float)$row['sugerida'];
		$propria  = ($row['propria'] === null) ? null : (float)$row['propria'];

		$lista[] = array(
			'nuc_id'    => (int)$row['nuc_id'],
			'nome'      => (string)$row['nuc_nome_curto'],
			'tipo'      => (string)$row['nuct_nome'],
			'arquivado' => ((int)$row['nuc_archive'] === 1),
			'sugerida'  => $sugerida,
			// null quando ninguém definiu ainda: aí vale a do tipo, e a tela mostra isso
			'propria'   => $propria,
			'vale'      => ($propria === null) ? $sugerida : $propria,
		);
	}

	return $lista;
}


// Grava a quota de cada núcleo. Recebe nuc_id => quota e escreve TODAS de uma vez.
//
// Depois disto a quota deixa de ser derivada: fica gravada, e é dela que o rateio parte.
// A sugestão do tipo continua valendo só para núcleo que nunca passou por aqui — o que
// nasce amanhã em nucleo.php, onde ninguém pensa em rateio.
//
// Quota 0 não é ausência de quota: é "este núcleo NÃO rateia", que é o caso de
// Logística e Mutirão. Por isso zero é valor válido, e não motivo de recusa.
//
// CONTRATO: true, ou false quando alguma quota é inválida — e aí NENHUMA é gravada.
function define_quotas_de_rateio($quotas)
{
	if (!is_array($quotas)) return false;

	// Lista VAZIA é recusa, não sucesso silencioso. Vinda da tela ela significa que o
	// formulário chegou truncado — e responder "quotas gravadas" a um POST que não
	// gravou nada é a mentira que este módulo existe para não contar. Não há ação
	// legítima de "gravar zero quotas": para tirar um núcleo do rateio grava-se 0.
	if (!count($quotas)) return false;

	// Confere tudo antes de escrever: metade gravada deixaria o rateio partir de uma
	// divisão que ninguém escolheu, e a soma das quotas é o divisor de todo mundo.
	$limpo = array();
	foreach ($quotas as $nuc => $q)
	{
		if (!ctype_digit((string)$nuc) || (int)$nuc <= 0) return false;
		if (!is_numeric($q)) return false;

		$q = round((float)$q, 1);
		if ($q < 0 || $q > 99) return false;

		$limpo[(int)$nuc] = $q;
	}

	global $conn_link, $financeiro_em_transacao;
	$nossa = empty($financeiro_em_transacao);
	if ($nossa) { mysqli_begin_transaction($conn_link); $financeiro_em_transacao = true; }

	foreach ($limpo as $nuc => $q)
	{
		$sql = "UPDATE nucleos SET nuc_quota_rateio = " . prep_para_bd($q);
		$sql.= " WHERE nuc_id = " . prep_para_bd($nuc);

		if (executa_sql($sql) !== true)
		{
			if ($nossa) { mysqli_rollback($conn_link); $financeiro_em_transacao = false; }
			return false;
		}
	}

	if ($nossa) { mysqli_commit($conn_link); $financeiro_em_transacao = false; }

	return true;
}


// ============================================================================
// ESTOQUE
//
// Em Secos a Rede guarda mercadoria entre uma chamada e outra — e ao montar o pedido
// seguinte abate da demanda o que já tem. A tabela `estoque` registra isso por chamada
// e produto, com a quantidade ANTES e DEPOIS.
//
// POR QUE ISSO PRECISA ESTAR NO RAZÃO. Mercadoria parada é ATIVO: a Rede pagou o
// produtor por ela e ainda não vendeu. Sem essa conta, o resultado da Rede oscila por
// causa de mercadoria que só mudou de lugar — no mês em que ela estoca, paga ao produtor
// e não cobra de ninguém, e parece prejuízo; no mês em que consome, cobra sem pagar, e
// parece lucro. Nenhuma das duas leituras é verdade.
//
// Medido na cópia de produção, chamada 1159: sem o estoque a conta acusava R$ 3.741 de
// "perda"; com ele, uma fração pequena do recebido. O resto era mercadoria guardada.
//
// A PREÇO DE COMPRA, por decisão da Rede: é o que ela desembolsou, que é o que ativo
// significa. A preço de venda embutiria margem ainda não realizada.
// ============================================================================


// A conta de estoque. Mesma forma de conta_da_rede(): busca por con_chave, cria se não
// houver, e a UNIQUE da chave garante que só existe uma.
function conta_de_estoque()
{
	$chave = CONTA_CHAVE_ESTOQUE;

	$res = executa_sql("SELECT con_id FROM contas WHERE con_chave = " . prep_para_bd($chave));
	if (!$res) return null;
	if ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) return (int)$row['con_id'];

	return cria_conta('estoque', array(
		'con_nome'  => 'Estoque da Rede',
		'con_chave' => $chave,
	));
}


// Quanto valia o estoque desta chamada antes e depois dela, a preço de COMPRA.
//
// CONTRATO: array com 'antes', 'depois' e 'variacao', ou null quando a chamada não
// existe ou a consulta não roda. Chamada sem linha de estoque devolve zeros — é o caso
// normal de Frescos, que não se guarda.
function valor_do_estoque_da_chamada($cha_id)
{
	$res = executa_sql("SELECT cha_id, cha_dt_entrega FROM chamadas WHERE cha_id = " . prep_para_bd($cha_id));
	if (!$res) return null;
	$cha = mysqli_fetch_array($res, MYSQLI_ASSOC);
	if (!$cha) return null;

	// A janela de validade casa pela data de entrega, como no resto do módulo: o preço
	// de um produto muda ao longo do tempo e `produtos` guarda uma linha por janela.
	$sql = "SELECT SUM(IFNULL(e.est_prod_qtde_antes,0)  * p.prod_valor_compra) antes, ";
	$sql.= "SUM(IFNULL(e.est_prod_qtde_depois,0) * p.prod_valor_compra) depois ";
	$sql.= "FROM estoque e ";
	$sql.= "JOIN chamadas c ON c.cha_id = e.est_cha ";
	$sql.= "JOIN produtos p ON p.prod_id = e.est_prod ";
	$sql.= "WHERE e.est_cha = " . prep_para_bd($cha_id) . " ";
	$sql.= "AND p.prod_ini_validade <= c.cha_dt_entrega AND p.prod_fim_validade >= c.cha_dt_entrega";

	$res = executa_sql($sql);
	if (!$res) return null;

	$row    = mysqli_fetch_array($res, MYSQLI_ASSOC);
	$antes  = ($row && $row['antes']  !== null) ? round((float)$row['antes'], 2)  : 0.0;
	$depois = ($row && $row['depois'] !== null) ? round((float)$row['depois'], 2) : 0.0;

	return array(
		'antes'    => $antes,
		'depois'   => $depois,
		'variacao' => round($depois - $antes, 2),
		'dt'       => $cha['cha_dt_entrega'],
	);
}


// Quanto esta chamada abriria de estoque, se alguém mandasse. Zero quando a corrente dela
// já foi aberta, ou quando não havia nada guardado no ponto de partida.
//
// A PERGUNTA É POR CORRENTE, e não pela conta inteira. Secos e Secos Bimestral são duas
// correntes independentes: get_chamada_anterior() (common.inc.php:568) filtra por
// cha_prodt, então o estoque anterior de uma Secos vem da Secos anterior, nunca da
// Bimestral. Perguntando pela conta, abrir a primeira corrente trancava a segunda para
// sempre, e o estoque inicial dela nunca entrava — a conta ficava a menos, em silêncio.
//
// Mora numa função porque a TELA faz a mesma pergunta para decidir se pede a abertura, e
// duas cópias dela divergiriam — foi exatamente o que aconteceu na primeira versão.
//
// CONTRATO: float (0 quando não há o que abrir), ou null quando a chamada não existe ou a
// consulta não roda.
function abertura_pendente_da_chamada($cha_id)
{
	$v = valor_do_estoque_da_chamada($cha_id);
	if ($v === null) return null;
	if ($v['antes'] <= 0) return 0.0;

	$sql = "SELECT COUNT(*) n FROM transacoes t ";
	$sql.= "JOIN chamadas c ON c.cha_id = t.tra_cha ";
	$sql.= "WHERE t.tra_tipo = 'estoque_abertura' ";
	$sql.= "AND c.cha_prodt = (SELECT cha_prodt FROM chamadas WHERE cha_id = " . prep_para_bd($cha_id) . ")";

	$res = executa_sql($sql);
	if (!$res) return null;
	$row = mysqli_fetch_array($res, MYSQLI_ASSOC);
	if (!$row) return null;

	return ((int)$row['n'] > 0) ? 0.0 : $v['antes'];
}


// A ABERTURA do estoque: o que já estava guardado antes de o módulo começar a lançar.
//
// Sem ela a conta guarda só a SOMA DAS VARIAÇÕES, que é o quanto o estoque mudou desde
// que se começou a lançar — não quanto ele vale. As duas coisas só coincidem se o
// estoque partiu de zero, e ele não parte: quando o módulo entrar em operação já haverá
// secos guardados.
//
// É a mesma necessidade que a spec registra para o cestante, com outro nome: alguém
// informa uma vez o ponto de partida, e daí em diante o razão acompanha sozinho.
//
// Lança o `antes` da chamada indicada — normalmente a primeira que se vai lançar.
//
// CONTRATO: tra_id · 0 quando a conta JÁ tem lançamento (abrir duas vezes dobraria o
// estoque, e em silêncio) · null quando a chamada não existe ou a consulta não roda.
function lanca_abertura_do_estoque($cha_id)
{
	$v = valor_do_estoque_da_chamada($cha_id);
	if ($v === null) return null;

	$con_estoque = conta_de_estoque();
	$con_rede    = conta_da_rede();
	if (!$con_estoque || !$con_rede) return null;

	$pend = abertura_pendente_da_chamada($cha_id);
	if ($pend === null) return null;
	if ($pend <= 0)     return 0;                   // já aberta, ou nada a abrir

	// TIPO PRÓPRIO, e não 'estoque'. A abertura carrega o mesmo tra_cha do lançamento de
	// variação daquela chamada — sem tipos distintos, a consulta de idempotência somaria
	// as duas e lançaria a variação a menos, exatamente pelo valor da abertura.
	return lanca_transacao($v['dt'], 'estoque_abertura', $con_estoque, $con_rede, $v['antes'],
		'estoque de abertura', array('cha' => $cha_id));
}


// O que ainda falta lançar de estoque nesta chamada. Existe separado do lançador porque
// a tela de fechamento precisa MOSTRAR o que vai acontecer antes de alguém confirmar —
// ritual em que se aperta um botão sem ver o número é ritual que se aperta no automático.
//
// CONTRATO: array com 'variacao' (o que a chamada mexeu), 'lancado' (o que já foi ao
// razão) e 'falta' (a diferença), ou null quando a chamada não existe ou a consulta não
// roda. falta = 0 quer dizer fechada quanto ao estoque.
function estoque_pendente_da_chamada($cha_id)
{
	$v = valor_do_estoque_da_chamada($cha_id);
	if ($v === null) return null;

	$con_estoque = conta_de_estoque();
	if (!$con_estoque) return null;

	$sql = "SELECT IFNULL(SUM(l.lan_valor),0) ja FROM lancamentos l ";
	$sql.= "JOIN transacoes t ON t.tra_id = l.lan_tra ";
	$sql.= "WHERE l.lan_con = " . prep_para_bd($con_estoque) . " ";
	// tra_tipo EXATAMENTE 'estoque': a abertura é 'estoque_abertura' e não entra nesta
	// conta, senão a variação sairia a menos pelo valor dela.
	$sql.= "AND t.tra_tipo = 'estoque' AND t.tra_cha = " . prep_para_bd($cha_id);

	$res = executa_sql($sql);
	if (!$res) return null;
	$row = mysqli_fetch_array($res, MYSQLI_ASSOC);

	// a perna guardada é negativa; o valor lançado é o oposto dela
	$lancado = $row ? round(-(float)$row['ja'], 2) : 0.0;

	return array(
		'variacao' => $v['variacao'],
		'lancado'  => $lancado,
		'falta'    => round($v['variacao'] - $lancado, 2),
		'antes'    => $v['antes'],
		'depois'   => $v['depois'],
		'dt'       => $v['dt'],
	);
}


// As chamadas do período que Finanças pode fechar, com o que falta lançar em cada uma.
//
// Só entram as que têm ALGO a lançar ou que já foram fechadas: chamada de Frescos, que
// não guarda estoque, não tem o que fechar hoje e ficaria na lista só fazendo volume.
// Quando a materialização do débito chegar, ela entra aqui do mesmo jeito e a lista
// passa a incluir todas — a forma da tela não muda.
//
// CONTRATO: array (vazio quando não há chamada no período), ou null quando a consulta
// não roda.
function chamadas_a_fechar($de, $ate)
{
	$sql = "SELECT c.cha_id, c.cha_dt_entrega, c.cha_dt_prazo_contabil, pt.prodt_nome, ";
	$sql.= "pt.prodt_mutirao ";
	$sql.= "FROM chamadas c JOIN produtotipos pt ON pt.prodt_id = c.cha_prodt ";
	$sql.= "WHERE c.cha_dt_entrega >= " . prep_para_bd($de) . " ";
	$sql.= "AND c.cha_dt_entrega < " . prep_para_bd($ate) . " ";
	$sql.= "ORDER BY c.cha_dt_entrega, c.cha_id";

	$res = executa_sql($sql);
	if (!$res) return null;

	$lista = array();
	while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))
	{
		$id = (int)$row['cha_id'];

		$pend = estoque_pendente_da_chamada($id);
		if ($pend === null) return null;

		$deb = debitos_a_materializar($id);
		if ($deb === null) return null;

		$abertura = abertura_pendente_da_chamada($id);
		if ($abertura === null) return null;

		$a_lancar = 0; $ja = 0; $valor = 0.0;
		foreach ($deb as $d)
		{
			if ($d['ja_lancado'])  { $ja++; continue; }
			if ($d['valor'] <= 0)  { continue; }
			$a_lancar++;
			$valor = round($valor + $d['valor'], 2);
		}

		$tem_estoque = (abs($pend['variacao']) > 0.005 || abs($pend['lancado']) > 0.005);
		$tem_debito  = ($a_lancar > 0 || $ja > 0);

		// Chamada que não guarda estoque nem tem entrega registrada não tem o que fechar,
		// e listá-la seria fila só fazendo volume.
		if (!$tem_estoque && !$tem_debito) continue;

		$lista[] = array(
			'cha_id'     => $id,
			'tipo'       => (string)$row['prodt_nome'],
			// chamada que não passa pelo mutirão não guarda estoque: o produtor entrega
			// direto no núcleo. As colunas de estoque dela não são zero medido, são
			// pergunta que não se faz — e a tela mostra travessão, não 0,00.
			'tem_mutirao' => ((int)$row['prodt_mutirao'] === 1),
			'dt'         => $row['cha_dt_entrega'],
			// o prazo contábil é o que autoriza congelar: antes dele os insumos ainda
			// podem mudar, e lançar cedo grava um retrato que a entrega ainda desmente
			'congelavel' => ($row['cha_dt_prazo_contabil'] !== null
			                 && strtotime($row['cha_dt_prazo_contabil']) <= time()),
			// a data em si, para a tela mostrar e deixar mexer: quem fecha é quem precisa
			// saber até quando a entrega ainda pode mudar debaixo do fechamento
			'prazo'      => $row['cha_dt_prazo_contabil'],
			'estoque'    => $pend,
			// quanto esta chamada abriria, se for a primeira da corrente dela
			'abertura'   => $abertura,
			'debitos'    => array('a_lancar' => $a_lancar, 'ja_lancados' => $ja, 'valor' => $valor),
			// fechada só quando NÃO SOBRA NADA dos dois lados: estoque conciliado e
			// nenhum cestante por congelar
			'fechada'    => (abs($pend['falta']) < 0.005 && $a_lancar === 0),
		);
	}

	return $lista;
}


// Lança no razão o que o estoque desta chamada mudou.
//
// IDEMPOTENTE POR DIFERENÇA, e não por "já rodou": olha quanto já foi lançado para esta
// chamada e lança só o que falta. Rodar duas vezes sem mudança não faz nada; rodar
// depois de alguém corrigir o estoque lança a correção, sem reescrever o lançamento
// anterior — que talvez já tenha sido conferido num fechamento.
//
// A DATA é a da entrega da chamada, e não a de hoje: é o dia em que a mercadoria de fato
// ficou parada, e é por essa data que o fluxo de caixa e o resultado agrupam.
//
// CONTRATO: tra_id quando lançou · 0 quando não havia o que lançar · null quando a
// chamada não existe ou a consulta não roda. Os três são coisas diferentes, e "não havia
// o que lançar" não pode se confundir com falha.
function lanca_estoque_da_chamada($cha_id)
{
	$v = valor_do_estoque_da_chamada($cha_id);
	if ($v === null) return null;

	$con_estoque = conta_de_estoque();
	$con_rede    = conta_da_rede();
	if (!$con_estoque || !$con_rede) return null;

	// A perna que o estoque DEVERIA ter, somadas todas as chamadas... não: apenas esta.
	// Guardar valor é segurar algo que não é seu para gastar, e na régua do módulo isso
	// é negativo — a mesma leitura do caixa do núcleo.
	$pend = estoque_pendente_da_chamada($cha_id);
	if ($pend === null) return null;

	$falta = round(-$pend['falta'], 2);
	if (abs($falta) < 0.005) return 0;          // nada a lançar — ver o CONTRATO

	$historico = ($falta < 0) ? 'mercadoria que ficou em estoque'
	                          : 'estoque consumido na entrega';

	// falta < 0: o estoque passa a segurar mais valor, e a posição da Rede melhora —
	// ela não perdeu aquele dinheiro, tem mercadoria. falta > 0: o estoque devolve o
	// valor e o custo se realiza contra a venda.
	if ($falta < 0)
		return lanca_transacao($v['dt'], 'estoque', $con_estoque, $con_rede, -$falta,
			$historico, array('cha' => $cha_id));

	return lanca_transacao($v['dt'], 'estoque', $con_rede, $con_estoque, $falta,
		$historico, array('cha' => $cha_id));
}


// O que a materialização desta chamada vai gravar, por cestante — sem gravar nada.
//
// As REGRAS SÃO AS MESMAS de debitos_derivados(): mesmo filtro de disponibilidade, mesma
// exigência de entrega registrada, mesmo casamento de preço pela janela de validade,
// mesma taxa só para associado. Se aquela mudar, esta tem de mudar junto — e o teste que
// compara as duas para o mesmo cestante é o que faz a divergência aparecer.
//
// A diferença é o recorte: lá é um cestante em todas as chamadas, aqui é uma chamada com
// todos os cestantes.
//
// CONTRATO: array de linhas (vazio quando não há o que materializar), ou null quando a
// chamada não existe ou a consulta não roda.
function debitos_a_materializar($cha_id)
{
	$res = executa_sql("SELECT cha_id, cha_dt_entrega, cha_dt_prazo_contabil FROM chamadas "
	     . "WHERE cha_id = " . prep_para_bd($cha_id));
	if (!$res) return null;
	$cha = mysqli_fetch_array($res, MYSQLI_ASSOC);
	if (!$cha) return null;

	$sql = "SELECT p.ped_usr usr, u.usr_nome_curto nome, c.cha_taxa_percentual taxa, ";
	$sql.= "p.ped_usr_associado assoc, ";
	$sql.= "SUM(IF(p.ped_usr_associado = '0', pr.prod_valor_venda_margem, pr.prod_valor_venda) ";
	$sql.= "    * pp.pedprod_entregue) entregue ";
	$sql.= "FROM pedidos p ";
	$sql.= "JOIN chamadas c        ON c.cha_id = p.ped_cha ";
	$sql.= "JOIN usuarios u        ON u.usr_id = p.ped_usr ";
	$sql.= "JOIN pedidoprodutos pp ON pp.pedprod_ped = p.ped_id ";
	$sql.= "JOIN chamadaprodutos cp ON cp.chaprod_cha = c.cha_id AND cp.chaprod_prod = pp.pedprod_prod ";
	$sql.= "JOIN produtos pr       ON pr.prod_id = pp.pedprod_prod ";
	$sql.= "  AND pr.prod_ini_validade <= c.cha_dt_entrega AND pr.prod_fim_validade >= c.cha_dt_entrega ";
	$sql.= "WHERE p.ped_cha = " . prep_para_bd($cha_id) . " AND p.ped_fechado = 1 ";
	$sql.= "AND cp.chaprod_disponibilidade <> '0' ";
	$sql.= "AND pp.pedprod_entregue > 0 ";
	$sql.= "GROUP BY p.ped_usr ";
	$sql.= "ORDER BY u.usr_nome_curto";

	$res = executa_sql($sql);
	if (!$res) return null;

	// Quem já foi materializado nesta chamada. A trava é a EXISTÊNCIA do lançamento, e
	// não um sinalizador na chamada: sinalizador pode discordar dos fatos, o lançamento é
	// o fato. Rodar duas vezes não pode dobrar dívida de ninguém.
	$sql_ja = "SELECT c.con_usr usr FROM transacoes t ";
	$sql_ja.= "JOIN lancamentos l ON l.lan_tra = t.tra_id ";
	$sql_ja.= "JOIN contas c ON c.con_id = l.lan_con AND c.con_tipo = 'cestante' ";
	$sql_ja.= "WHERE t.tra_tipo = 'debito_entrega' AND t.tra_cha = " . prep_para_bd($cha_id);

	$res_ja = executa_sql($sql_ja);
	if (!$res_ja) return null;

	$ja = array();
	while ($r = mysqli_fetch_array($res_ja, MYSQLI_ASSOC)) $ja[(int)$r['usr']] = true;

	$linhas = array();
	while ($r = mysqli_fetch_array($res, MYSQLI_ASSOC))
	{
		$entregue = round((float)$r['entregue'], 2);
		// não-associado já paga o preço com margem embutido; taxa só para associado —
		// a mesma regra de debitos_derivados()
		$taxa = ((string)$r['assoc'] === '0') ? 0.0
		      : round($entregue * (float)$r['taxa'], 2);

		$linhas[] = array(
			'usr_id'    => (int)$r['usr'],
			'nome'      => (string)$r['nome'],
			'entregue'  => $entregue,
			'taxa'      => $taxa,
			'valor'     => round($entregue + $taxa, 2),
			'ja_lancado' => isset($ja[(int)$r['usr']]),
		);
	}

	return $linhas;
}


// Congela o débito desta chamada: o que era derivado da entrega vira lançamento.
//
// POR QUE ISSO É SEGURO no prazo contábil: os insumos já congelaram sozinhos.
// entrega_cestante.php:44 recusa gravação depois do prazo, e o preço está preso à janela
// de validade do produto. O retrato é tirado depois que o modelo parou de se mexer — e a
// tela só oferece o botão quando o prazo passou.
//
// CORREÇÃO DEPOIS DO CONGELAMENTO NÃO ACONTECE AQUI. A spec é explícita: não se apaga nem
// se reescreve; entra uma transação de ajuste, com valor, motivo e autor. Por isso quem
// já tem lançamento é PULADO, e não recalculado — ao contrário do estoque, onde a
// correção é o próprio número e não há dívida de ninguém no meio.
//
// CONTRATO: array com 'lancados', 'pulados' e 'valor' · null quando a chamada não existe,
// não é congelável, ou a consulta não roda.
function materializa_debitos_da_chamada($cha_id)
{
	$res = executa_sql("SELECT cha_id, cha_dt_entrega, cha_dt_prazo_contabil FROM chamadas "
	     . "WHERE cha_id = " . prep_para_bd($cha_id));
	if (!$res) return null;
	$cha = mysqli_fetch_array($res, MYSQLI_ASSOC);
	if (!$cha) return null;

	// Antes do prazo contábil a entrega ainda muda, e um débito gravado cedo é um retrato
	// que a realidade desmente — sem ninguém perceber, porque ele deixa de ser derivado.
	if ($cha['cha_dt_prazo_contabil'] === null
	    || strtotime($cha['cha_dt_prazo_contabil']) > time()) return null;

	// A data de corte é onde a contabilidade começa: materializar entrega de 2013 criaria
	// dívida que ninguém conferiu e que a Rede já considera resolvida.
	if ($cha['cha_dt_entrega'] < DATA_CORTE_FINANCEIRO) return null;

	$linhas = debitos_a_materializar($cha_id);
	if ($linhas === null) return null;

	$con_rede = conta_da_rede();
	if (!$con_rede) return null;

	$lancados = 0; $pulados = 0; $valor = 0.0;

	global $conn_link, $financeiro_em_transacao;
	$nossa = empty($financeiro_em_transacao);
	if ($nossa) { mysqli_begin_transaction($conn_link); $financeiro_em_transacao = true; }

	foreach ($linhas as $l)
	{
		if ($l['ja_lancado']) { $pulados++; continue; }
		if ($l['valor'] <= 0) { $pulados++; continue; }

		$con = conta_do_cestante($l['usr_id'], true);
		if (!$con)
		{
			if ($nossa) { mysqli_rollback($conn_link); $financeiro_em_transacao = false; }
			return null;
		}

		// cestante −valor · rede +valor: quem recebeu passa a dever, e a Rede a receber
		$tra = lanca_transacao($cha['cha_dt_entrega'], 'debito_entrega', $con, $con_rede,
			$l['valor'], 'entrega', array('cha' => $cha_id));

		if (!$tra)
		{
			if ($nossa) { mysqli_rollback($conn_link); $financeiro_em_transacao = false; }
			return null;
		}

		$lancados++;
		$valor = round($valor + $l['valor'], 2);
	}

	if ($nossa) { mysqli_commit($conn_link); $financeiro_em_transacao = false; }

	return array('lancados' => $lancados, 'pulados' => $pulados, 'valor' => $valor);
}


// ============================================================================
// AS DUAS SEQUÊNCIAS DE ABAS DO MÓDULO
//
// O módulo tem duas audiências que não se misturam: quem cuida das finanças de UM
// NÚCLEO e quem cuida das finanças DA REDE. Não é a mesma pessoa — a Rede confirmou
// isso —, e as telas já dizem o mesmo: metade exige RESP_NÚCLEO e a outra metade
// RESP_FINANÇAS.
//
// Num menu só, quem responde por um núcleo via "Despesas da Rede" e "Quotas de rateio",
// telas que iam recusá-lo. Menu que oferece o que não se pode abrir ensina a ignorar o
// menu.
//
// A sequência mora AQUI, e não copiada em cada tela: com oito cópias, a nona tela
// nasceria fora da barra, ou a barra ficaria diferente numa delas — e ninguém notaria.
// ============================================================================


// As telas de cada grupo, na ordem em que se usa.
function abas_financeiras_do_grupo($grupo)
{
	if ($grupo === 'nucleo')
		return array(
			'hub'        => array('financas_nucleo.php',  'Finanças do núcleo', ''),
			'pagamentos' => array('conta_pagamentos.php', 'Pagamentos',         'glyphicon-piggy-bank'),
			'caixa'      => array('conta_nucleo.php',     'Caixa',              'glyphicon-inbox'),
			'fluxo'      => array('fluxo_caixa.php',      'Fluxo de caixa',     'glyphicon-stats'),
			// "Equilíbrio", e não "Resultado": a pergunta que o núcleo faz é se ele se
			// paga, não quanto lucrou. O ícone é o ponteiro, e não a balança — esta é
			// da Conferência em R$, e duas abas com o mesmo desenho no mesmo sistema
			// deixam de identificar qualquer uma das duas.
			'resultado'  => array('equilibrio.php',      'Equilíbrio',         'glyphicon-dashboard'),
		);

	// AS TRÊS PRIMEIRAS SÃO ANTERIORES AO MÓDULO, e quem tem RESP_FINANÇAS as alcança
	// mesmo sem Beta Tester. Por isso a barra se divide: as antigas sempre, as novas só
	// para quem chega nelas. Oferecer link que a tela vai recusar é pior do que não
	// oferecer — a pessoa clica, leva "sem permissão" e volta para a página inicial.
	$abas = array(
		'hub'        => array('financas.php',            'Finanças',           ''),
		'recebimento'=> array('recebimento.php?action=0&recebimento=final',
		                                                 'Recebido dos produtores', 'glyphicon-road'),
	);

	// PRAZOS SÓ PARA QUEM AINDA NÃO TEM O FECHAMENTO. O prazo de registro de entrega
	// passou a ser editado dentro do Fechamento contábil, junto da decisão que ele
	// autoriza — mas o Fechamento é invisível para quem não alcança o módulo, e para
	// essa pessoa a tela antiga continua sendo a única forma de definir o prazo.
	// Tirá-la da barra dos dois lados deixaria RESP_FINANÇAS sem Beta Tester sem
	// caminho nenhum até ela.
	if (!pode_ver_financas_da_rede())
		$abas['prazos'] = array('financas_prazos.php', 'Prazos', 'glyphicon-calendar');

	if (pode_ver_financas_da_rede())
	{
		$abas['caixa']      = array('caixa_rede.php',         'Caixa da Rede',      'glyphicon-briefcase');
		$abas['fechamento'] = array('fechamento_chamada.php', 'Fechamento contábil', 'glyphicon-lock');
		$abas['despesas']   = array('despesas_rede.php',      'Despesas da Rede',   'glyphicon-globe');
		$abas['quotas']     = array('quotas_rateio.php',      'Quotas de rateio',   'glyphicon-equalizer');
		$abas['produtores'] = array('contas_produtores.php',  'Caixa Produtores',   'glyphicon-leaf');
	}

	return $abas;
}


// Imprime a barra de abas do grupo, marcando a ativa. Mesmo formato de entregas.php e
// mutirao.php, que é o que o time já reconhece.
function abas_financeiras($grupo, $ativa)
{
	$abas = abas_financeiras_do_grupo($grupo);

	echo('<ul class="nav nav-tabs">' . "\n");

	foreach ($abas as $chave => $aba)
	{
		list($url, $rotulo, $icone) = $aba;
		$e_ativa = ($chave === $ativa);

		echo('  <li' . ($e_ativa ? ' class="active"' : '') . '>');
		echo('<a href="' . ($e_ativa ? '#' : h($url)) . '">');
		if ($icone !== '') echo('<i class="glyphicon ' . h($icone) . '"></i> ');
		echo(h($rotulo) . '</a></li>' . "\n");
	}

	echo('</ul>' . "\n<br>\n");
}


// Quem alcança cada grupo. É a mesma pergunta que as telas do grupo fazem — repetida
// aqui só para o menu não oferecer o que vai recusar.
function pode_ver_financas_do_nucleo()
{
	return pode_lancar_pagamento();
}

function pode_ver_financas_da_rede()
{
	return pode_ver_financeiro()
	    && (!empty($_SESSION[PAP_RESP_FINANCAS]) || !empty($_SESSION[PAP_ADM]));
}


// O RESULTADO DA REDE, mês a mês do ano: a operação inteira se paga?
//
// É o equilíbrio do núcleo olhado de cima. A receita é a MESMA — associação, taxa e
// margem —, só que de todos os núcleos somados; não é dinheiro novo, é o mesmo dinheiro
// visto de outro lugar, e a tela precisa dizer isso ou alguém soma os dois.
//
// O CUSTO TEM DUAS METADES que o núcleo não vê juntas: as despesas que os núcleos
// lançaram no caixa deles, e as despesas centrais da Rede ANTES do rateio. O rateio não
// entra: ele é atribuição interna, move custo da Rede para os núcleos no papel e não
// muda um centavo do total. Somá-lo contaria o mesmo gasto duas vezes.
//
// A CONFERÊNCIA QUE SÓ EXISTE AQUI: a soma dos resultados dos núcleos, menos o que a Rede
// deixou de ratear, tem de dar este resultado. Vem da álgebra — o resultado do núcleo é
// receita menos despesa própria menos rateio, e o da Rede é receita menos despesa própria
// menos despesa da Rede; a diferença entre os dois é exatamente a sobra. Quando não bate,
// há rateio apontando para núcleo que não entra na conta, ou despesa sem perna de custo.
//
// UMA CONSULTA POR ANO, e não doze: a receita sai agrupada por mês numa varredura só —
// medido em cerca de um segundo sobre a base inteira. Doze chamadas da versão do núcleo
// levariam doze vezes isso, e a tela é de olhar o ano.
//
// CONTRATO: array, ou null quando alguma consulta não roda.
function resultado_da_rede($ano)
{
	$ano = (int)$ano;
	if ($ano < 2000 || $ano > 2200) return null;

	$de  = sprintf('%04d-01-01 00:00:00', $ano);
	$ate = sprintf('%04d-01-01 00:00:00', $ano + 1);

	$zeros = array();
	for ($m = 1; $m <= 12; $m++)
		$zeros[$m] = array(
			'associacao' => 0.0, 'taxa' => 0.0, 'margem_nao_associado' => 0.0,
			'margem_produto' => 0.0, 'outras' => 0.0, 'receita' => 0.0,
			'despesas_nucleos' => 0.0, 'despesas_rede' => 0.0, 'custo' => 0.0,
			'rateado' => 0.0, 'sobra' => 0.0, 'resultado' => 0.0,
		);

	$mes = $zeros;
	$v = function ($x) { return ($x === null) ? 0.0 : round((float)$x, 2); };

	// ---- receita: a mesma conta do equilíbrio do núcleo, sem o recorte de ped_nuc ----
	$sql = "SELECT MONTH(c.cha_dt_entrega) m, ";
	$sql.= "SUM(CASE WHEN pt.prodt_nome LIKE 'Associa%' ";
	$sql.= "     THEN pr.prod_valor_venda * pp.pedprod_entregue ELSE 0 END) associacao, ";
	$sql.= "SUM(CASE WHEN pt.prodt_nome NOT LIKE 'Associa%' AND p.ped_usr_associado <> '0' ";
	$sql.= "     THEN pr.prod_valor_venda * pp.pedprod_entregue * c.cha_taxa_percentual ELSE 0 END) taxa, ";
	$sql.= "SUM(CASE WHEN pt.prodt_nome NOT LIKE 'Associa%' AND p.ped_usr_associado = '0' ";
	$sql.= "     THEN (pr.prod_valor_venda_margem - pr.prod_valor_compra) * pp.pedprod_entregue ELSE 0 END) margem_na, ";
	$sql.= "SUM(CASE WHEN pt.prodt_nome NOT LIKE 'Associa%' AND p.ped_usr_associado <> '0' ";
	$sql.= "     THEN (pr.prod_valor_venda - pr.prod_valor_compra) * pp.pedprod_entregue ELSE 0 END) margem_a ";
	$sql.= "FROM pedidos p ";
	$sql.= "JOIN pedidoprodutos pp  ON pp.pedprod_ped = p.ped_id ";
	$sql.= "JOIN chamadas c         ON c.cha_id = p.ped_cha ";
	$sql.= "JOIN produtotipos pt    ON pt.prodt_id = c.cha_prodt ";
	$sql.= "JOIN chamadaprodutos cp ON cp.chaprod_cha = p.ped_cha AND cp.chaprod_prod = pp.pedprod_prod ";
	$sql.= "JOIN produtos pr        ON pr.prod_id = pp.pedprod_prod ";
	$sql.= "WHERE p.ped_fechado = 1 AND cp.chaprod_disponibilidade <> '0' ";
	$sql.= "AND pr.prod_ini_validade <= c.cha_dt_entrega AND pr.prod_fim_validade >= c.cha_dt_entrega ";
	$sql.= "AND c.cha_dt_entrega >= " . prep_para_bd($de) . " AND c.cha_dt_entrega < " . prep_para_bd($ate) . " ";
	$sql.= "GROUP BY m";

	$res = executa_sql($sql);
	if (!$res) return null;
	while ($r = mysqli_fetch_array($res, MYSQLI_ASSOC))
	{
		$m = (int)$r['m'];
		if (!isset($mes[$m])) continue;
		$mes[$m]['associacao']           = $v($r['associacao']);
		$mes[$m]['taxa']                 = $v($r['taxa']);
		$mes[$m]['margem_nao_associado'] = $v($r['margem_na']);
		$mes[$m]['margem_produto']       = $v($r['margem_a']);
	}

	// ---- despesas e outras receitas lançadas nos caixas dos núcleos ----
	$sql = "SELECT MONTH(t.tra_dt) m, t.tra_tipo tipo, SUM(ABS(l.lan_valor)) v ";
	$sql.= "FROM lancamentos l ";
	$sql.= "JOIN transacoes t ON t.tra_id = l.lan_tra ";
	$sql.= "JOIN contas c     ON c.con_id = l.lan_con AND c.con_tipo = 'nucleo' ";
	$sql.= "WHERE t.tra_tipo IN ('despesa','receita') ";
	$sql.= "AND t.tra_dt >= " . prep_para_bd($de) . " AND t.tra_dt < " . prep_para_bd($ate) . " ";
	$sql.= "GROUP BY m, tipo";

	$res = executa_sql($sql);
	if (!$res) return null;
	while ($r = mysqli_fetch_array($res, MYSQLI_ASSOC))
	{
		$m = (int)$r['m'];
		if (!isset($mes[$m])) continue;
		if ((string)$r['tipo'] === 'receita') $mes[$m]['outras']           = $v($r['v']);
		else                                  $mes[$m]['despesas_nucleos'] = $v($r['v']);
	}

	// ---- despesas centrais da Rede, e quanto delas foi rateado ----
	$con_rede_r = conta_da_rede();
	if (!$con_rede_r) return null;

	$sql = "SELECT MONTH(t.tra_dt) m, SUM(ABS(l.lan_valor)) v, ";
	$sql.= "IFNULL((SELECT SUM(r.rat_valor) FROM rateios r ";
	$sql.= "        WHERE r.rat_tra IN (SELECT t2.tra_id FROM transacoes t2 ";
	$sql.= "          WHERE t2.tra_tipo = 'despesa_rede' AND MONTH(t2.tra_dt) = MONTH(t.tra_dt) ";
	$sql.= "            AND t2.tra_dt >= " . prep_para_bd($de) . " AND t2.tra_dt < " . prep_para_bd($ate) . ")),0) rateado ";
	$sql.= "FROM lancamentos l ";
	$sql.= "JOIN transacoes t ON t.tra_id = l.lan_tra ";
	$sql.= "WHERE t.tra_tipo = 'despesa_rede' AND l.lan_con = " . prep_para_bd($con_rede_r) . " ";
	$sql.= "AND t.tra_dt >= " . prep_para_bd($de) . " AND t.tra_dt < " . prep_para_bd($ate) . " ";
	$sql.= "GROUP BY m";

	$res = executa_sql($sql);
	if (!$res) return null;
	while ($r = mysqli_fetch_array($res, MYSQLI_ASSOC))
	{
		$m = (int)$r['m'];
		if (!isset($mes[$m])) continue;
		$mes[$m]['despesas_rede'] = $v($r['v']);
		$mes[$m]['rateado']       = $v($r['rateado']);
	}

	// ---- fecha cada mês, e o ano ----
	$ano_tot = $zeros[1];
	foreach ($mes as $m => $x)
	{
		$mes[$m]['receita'] = round($x['associacao'] + $x['taxa'] + $x['margem_nao_associado']
		                          + $x['margem_produto'] + $x['outras'], 2);
		$mes[$m]['custo']   = round($x['despesas_nucleos'] + $x['despesas_rede'], 2);
		$mes[$m]['sobra']   = round($x['despesas_rede'] - $x['rateado'], 2);
		$mes[$m]['resultado'] = round($mes[$m]['receita'] - $mes[$m]['custo'], 2);

		foreach ($ano_tot as $k => $_) $ano_tot[$k] = round($ano_tot[$k] + $mes[$m][$k], 2);
	}

	return array('ano' => $ano, 'meses' => $mes, 'ano_total' => $ano_tot);
}


// A POSIÇÃO DA REDE, num retrato só: onde o dinheiro está agora, e o que está pendurado.
//
// É a pergunta que nenhuma tela respondia. Havia o caixa de cada núcleo, a conta de cada
// cestante e a fila dos produtores — cada um o seu pedaço, e nada que somasse. Quem cuida
// do dinheiro da Rede precisa dos dois números antes de qualquer outro: quanto há, e
// quanto está comprometido.
//
// O SINAL VIRA PALAVRA, como no resto do módulo. No razão, conta que SEGURA dinheiro tem
// saldo negativo — dinheiro que entra soma negativo, dinheiro que sai soma positivo. As
// três telas que já mostram saldo (caixa do núcleo, conta do cestante, estoque no
// fechamento) exibem todas `-saldo`, e esta faz o mesmo: os números que saem daqui já
// vêm no sentido em que se fala deles.
//
// A CONTA DE RESULTADO FICA FORA DO CAIXA, e é o ponto inteiro da separação. Ela é do
// tipo 'rede' como as outras, mas não guarda dinheiro: acumula o que a Rede absorveu de
// custo e o que os cestantes lhe devem. Somá-la ao caixa misturaria "quanto temos" com
// "como estamos", que são perguntas diferentes e se respondem em blocos diferentes.
//
// CONTRATO: array, ou null quando alguma das consultas não roda. Zero é resposta; falha
// não pode virar zero numa tela que resume dinheiro.
function posicao_da_rede()
{
	$saldos = array();
	$sql = "SELECT c.con_id, c.con_tipo, c.con_nome, c.con_chave, n.nuc_nome_curto, ";
	$sql.= "IFNULL((SELECT SUM(l.lan_valor) FROM lancamentos l WHERE l.lan_con = c.con_id),0) saldo ";
	$sql.= "FROM contas c LEFT JOIN nucleos n ON n.nuc_id = c.con_nuc ";
	$sql.= "WHERE c.con_archive = 0";

	$res = executa_sql($sql);
	if (!$res) return null;
	while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) $saldos[] = $row;

	$contas_caixa = array();
	$em_nucleos = 0.0;
	$cestantes_devem = 0.0;   $cestantes_quantos = 0;
	$cestantes_credito = 0.0; $credito_quantos = 0;
	$estoque = 0.0;
	$resultado = 0.0;

	foreach ($saldos as $c)
	{
		// -saldo: o sentido em que se fala do número
		$tem = round(-(float)$c['saldo'], 2);

		if ($c['con_tipo'] === 'rede')
		{
			if ((string)$c['con_chave'] === CONTA_CHAVE_REDE) { $resultado = $tem; continue; }
			$contas_caixa[] = array(
				'con_id'   => (int)$c['con_id'],
				'nome'     => (string)$c['con_nome'],
				'em_caixa' => $tem,
			);
		}
		else if ($c['con_tipo'] === 'nucleo')   $em_nucleos = round($em_nucleos + $tem, 2);
		else if ($c['con_tipo'] === 'estoque')  $estoque    = round($estoque + $tem, 2);
		else if ($c['con_tipo'] === 'cestante')
		{
			// devedores e credores contados SEPARADAMENTE: a soma líquida esconderia
			// que há gente devendo enquanto outra tem crédito, e são duas conversas
			if ($tem > 0.005)       { $cestantes_devem   = round($cestantes_devem + $tem, 2);   $cestantes_quantos++; }
			else if ($tem < -0.005) { $cestantes_credito = round($cestantes_credito - $tem, 2); $credito_quantos++; }
		}
	}

	usort($contas_caixa, function ($a, $b) { return strcasecmp($a['nome'], $b['nome']); });

	$em_contas = 0.0;
	foreach ($contas_caixa as $c) $em_contas = round($em_contas + $c['em_caixa'], 2);

	// O A PAGAR AOS PRODUTORES NÃO SAI DO RAZÃO: o que eles têm a receber é derivado da
	// confirmação de Finanças, e só o que já foi pago vira lançamento. posicao_dos_produtores()
	// é quem junta os dois lados, e é dela que este número tem de vir — dois caminhos para
	// o mesmo número divergiriam no primeiro ajuste.
	$prod = posicao_dos_produtores(DATA_CORTE_FINANCEIRO, '2200-01-01 00:00:00');
	if ($prod === null) return null;

	$a_pagar = 0.0; $prod_quantos = 0;
	$adiantado = 0.0; $adiantado_quantos = 0;
	foreach ($prod as $x)
	{
		if ($x['saldo'] > 0.005)       { $a_pagar   = round($a_pagar + $x['saldo'], 2);   $prod_quantos++; }
		else if ($x['saldo'] < -0.005) { $adiantado = round($adiantado - $x['saldo'], 2); $adiantado_quantos++; }
	}

	return array(
		'caixa' => array(
			'contas'   => $contas_caixa,
			'em_contas'=> $em_contas,
			'nucleos'  => $em_nucleos,
			'total'    => round($em_contas + $em_nucleos, 2),
		),
		'pendurado' => array(
			'cestantes_devem'    => $cestantes_devem,    'cestantes_quantos' => $cestantes_quantos,
			'cestantes_credito'  => $cestantes_credito,  'credito_quantos'   => $credito_quantos,
			'produtores_a_pagar' => $a_pagar,            'produtores_quantos'=> $prod_quantos,
			'produtores_adiantado' => $adiantado,        'adiantado_quantos' => $adiantado_quantos,
			'estoque'            => $estoque,
		),
		// não entra no caixa: é como a Rede está, não quanto ela tem
		'resultado' => $resultado,
	);
}


// A posição de cada produtor no período: quanto a Rede lhe deve pelo que ele entregou, e
// quanto já foi pago.
//
// O A RECEBER É DERIVADO, como o débito do cestante era antes da materialização: sai de
// `chaprod_recebido_confirmado × prod_valor_compra`, que é o mesmo número que
// rel_previsao_pagamento.php:26 já calcula — e que Finanças confirma depois de ler as
// justificativas de divergência. Não há cópia gravada que possa divergir dele.
//
// O PAGO vem do razão: pagamento de cestante direto ao produtor, e pagamento do núcleo ao
// produtor, os dois deixam a conta dele negativa. Aqui o sinal se inverte, porque quem lê
// quer saber "quanto já pagamos", não "quanto ele deve".
//
// É esta tela que a conta do produtor sempre precisou. Em contas.php o saldo dele aparece
// como "—", porque lá só o lado do PAGO existe e um número isolado diria o inverso da
// verdade: que o produtor deve à Rede.
//
// CONTRATO: array (vazio quando não há produtor com movimento), ou null quando a consulta
// não roda.
function posicao_dos_produtores($de, $ate)
{
	// ---- o que ele tem a receber pelo que entregou ----
	$sql = "SELECT f.forn_id, f.forn_nome_curto nome, f.forn_archive, ";
	$sql.= "SUM(cp.chaprod_recebido_confirmado * p.prod_valor_compra) v ";
	$sql.= "FROM chamadaprodutos cp ";
	$sql.= "JOIN chamadas c   ON c.cha_id = cp.chaprod_cha ";
	$sql.= "JOIN produtos p   ON p.prod_id = cp.chaprod_prod ";
	$sql.= "  AND p.prod_ini_validade <= c.cha_dt_entrega AND p.prod_fim_validade >= c.cha_dt_entrega ";
	$sql.= "JOIN fornecedores f ON f.forn_id = p.prod_forn ";
	$sql.= "WHERE cp.chaprod_disponibilidade <> '0' ";
	$sql.= "AND c.cha_dt_entrega >= " . prep_para_bd($de) . " ";
	$sql.= "AND c.cha_dt_entrega <  " . prep_para_bd($ate) . " ";
	$sql.= "GROUP BY f.forn_id";

	$res = executa_sql($sql);
	if (!$res) return null;

	$linhas = array();
	while ($r = mysqli_fetch_array($res, MYSQLI_ASSOC))
		$linhas[(int)$r['forn_id']] = array(
			'forn_id'   => (int)$r['forn_id'],
			'nome'      => (string)$r['nome'],
			'arquivado' => ((int)$r['forn_archive'] === 1),
			'a_receber' => ($r['v'] === null) ? 0.0 : round((float)$r['v'], 2),
			'pago'      => 0.0);

	// ---- o que já foi pago a ele, pelo razão ----
	$sql = "SELECT c.con_forn forn, f.forn_nome_curto nome, f.forn_archive, ";
	$sql.= "SUM(-l.lan_valor) v ";
	$sql.= "FROM lancamentos l ";
	$sql.= "JOIN transacoes t   ON t.tra_id = l.lan_tra ";
	$sql.= "JOIN contas c       ON c.con_id = l.lan_con AND c.con_tipo = 'produtor' ";
	$sql.= "JOIN fornecedores f ON f.forn_id = c.con_forn ";
	$sql.= "WHERE t.tra_dt >= " . prep_para_bd($de) . " AND t.tra_dt < " . prep_para_bd($ate) . " ";
	$sql.= "GROUP BY c.con_forn";

	$res = executa_sql($sql);
	if (!$res) return null;

	while ($r = mysqli_fetch_array($res, MYSQLI_ASSOC))
	{
		$id = (int)$r['forn'];
		// produtor pago sem ter entregue no período também aparece: é justamente o caso
		// em que alguém quer olhar, e escondê-lo esconderia o pagamento adiantado ou o
		// pagamento lançado no produtor errado
		if (!isset($linhas[$id]))
			$linhas[$id] = array('forn_id' => $id, 'nome' => (string)$r['nome'],
			                     'arquivado' => ((int)$r['forn_archive'] === 1),
			                     'a_receber' => 0.0, 'pago' => 0.0);

		$linhas[$id]['pago'] = round((float)$r['v'], 2);
	}

	foreach ($linhas as $id => $x)
		$linhas[$id]['saldo'] = round($x['a_receber'] - $x['pago'], 2);

	// maior saldo primeiro: é a fila de quem esperar receber
	uasort($linhas, function ($a, $b) {
		if (abs($a['saldo'] - $b['saldo']) < 0.005) return strcasecmp($a['nome'], $b['nome']);
		return ($a['saldo'] > $b['saldo']) ? -1 : 1;
	});

	return array_values($linhas);
}


