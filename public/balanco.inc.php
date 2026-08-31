<?php
// BALANÇO DA CHAMADA — onde a mercadoria entrou e onde saiu, em dinheiro.
//
// A tela lê o que o time JÁ REGISTRA hoje — distribuição, entrega ao cestante, recebimento
// confirmado, estoque — e traduz em reais. Não grava nada, e não depende de tabela nova:
// todas as que consulta existem desde sempre. É por isso que ela sobe antes do módulo
// financeiro, e não junto dele.
//
// AS TABELAS QUE ELA LÊ, todas pré-existentes:
//   chamadas · chamadaprodutos · distribuicao · estoque · nucleos
//   pedidos · pedidoprodutos · produtos · produtotipos · usuarios
//
// CONTRATO DA FAMÍLIA — null e array() NÃO são a mesma coisa:
//   array   a consulta rodou; array() quer dizer "nada a mostrar"
//   null    a consulta NÃO rodou, ou o que se pediu não existe
// Numa tela de dinheiro, "não deu para perguntar" mostrado como "não há nada" é
// exatamente a mentira que ela existe para não contar. executa_sql() não aborta —
// devolve false quando o servidor recusa —, então cada chamada é conferida.
//
// QUEM VÊ é quem já cuida de entrega — a mesma audiência das outras abas, pela mesma
// linha que elas usam: verifica_seguranca($_SESSION[PAP_RESP_ENTREGA] ||
// $_SESSION[PAP_RESP_FINANCAS]). Sem regra própria: o Balanço é a continuação de
// entrega_divergencias.php, com o dinheiro ao lado das quantidades, e uma regra só dele
// é o que faria duas telas irmãs divergirem sem ninguém decidir que deviam.
//
// O aviso de "funcionalidade em teste" fica na própria tela, ao lado do seletor de
// chamada, onde quem for usar já está olhando.


// A barra de abas de ENTREGAS, com o Balanço no fim quando quem olha o alcança.
//
// Ela estava copiada em OITO telas, cada uma com a sua cópia da lista. Numa barra assim, a
// nona tela nasce fora da sequência ou uma das oito fica para trás e ninguém nota — foi o
// que aconteceu quando o Balanço entrou: ele existia em uma tela só, e clicar em
// Divergências fazia a aba nova sumir, como se não existisse. Aqui a lista mora num
// lugar só, e quem decide o que entra é a permissão, não a memória de quem editou.
//
// A ABA ATIVA MANTÉM O LINK, de propósito. Metade destas telas é detalhe de outra —
// entrega_nucleo.php fica sob "Recebido pelo Núcleo" —, e ali clicar na aba marcada é
// como se volta para a lista. Trocar por `#` tiraria essa saída.
function abas_entregas($ativa)
{
	$abas = array(
		'hub'          => array('entregas.php',                       'Entregas',               ''),
		'nucleos'      => array('entrega_nucleos_consolidado.php',    'Recebido pelo Núcleo',   'glyphicon-road'),
		'cestantes'    => array('entrega_cestantes_consolidado.php',  'Entregue aos Cestantes', 'glyphicon-grain'),
		'divergencias' => array('entrega_divergencias.php',           'Divergências',           'glyphicon-eye-open'),
		// SEM CONDIÇÃO. Toda tela que desenha esta barra já passou pela mesma
		// verifica_seguranca() que o Balanço exige, então quem chega aqui alcança as
		// cinco abas. Uma condição a mais só criaria a chance de a barra mostrar
		// aba que a tela recusa, ou de esconder aba que ela aceita.
		'balanco'      => array('balanco.php',                        'Balanço',                'glyphicon-scale'),
	);

	echo('<ul class="nav nav-tabs">' . "\n");

	foreach ($abas as $chave => $aba)
	{
		list($url, $rotulo, $icone) = $aba;

		echo('  <li' . ($chave === $ativa ? ' class="active"' : '') . '>');
		echo('<a href="' . h($url) . '">');
		if ($icone !== '') echo('<i class="glyphicon ' . h($icone) . '"></i> ');
		echo(h($rotulo) . '</a></li>' . "\n");
	}

	echo('</ul>' . "\n");
}


// Compara dois nomes de núcleo como o BANCO os compararia.
//
// POR QUE NÃO strcasecmp() DIRETO: a conexão é utf8 (common.inc.php:76), então "Grajaú"
// chega com o ú em dois bytes começando por 0xC3 — maior que qualquer letra ASCII. Em
// ordem de byte todo nome acentuado cairia depois de "z" a partir do acento, e a lista
// deixaria de bater com a caixa de seleção, que vem ordenada pelo MySQL.
//
// latin1_swedish_ci, a collation da base, trata á como a e ç como c. É isso que a tabela
// abaixo faz — só as letras que o português usa, que são as que aparecem em nome de
// núcleo. Letra fora dela passa intacta e cai onde o byte mandar, que é o comportamento
// de antes e não uma regressão nova.
//
// SEM mb_*: nenhum outro arquivo deste sistema usa mbstring, então não há evidência de
// que a extensão esteja ligada na Locaweb — e mb_strtolower ausente é fatal, não aviso:
// derrubaria a tela inteira por causa de uma ordenação. A tabela cobre maiúscula e
// minúscula acentuadas, e depois dela só sobra ASCII, onde strtolower é seguro.
function compara_nome_de_nucleo($a, $b)
{
	static $acentos = array(
		'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
		'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
		'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
		'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
		'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
		'ç'=>'c','ñ'=>'n',
		'Á'=>'a','À'=>'a','Ã'=>'a','Â'=>'a','Ä'=>'a',
		'É'=>'e','È'=>'e','Ê'=>'e','Ë'=>'e',
		'Í'=>'i','Ì'=>'i','Î'=>'i','Ï'=>'i',
		'Ó'=>'o','Ò'=>'o','Õ'=>'o','Ô'=>'o','Ö'=>'o',
		'Ú'=>'u','Ù'=>'u','Û'=>'u','Ü'=>'u',
		'Ç'=>'c','Ñ'=>'n',
	);

	$sa = strtolower(strtr((string)$a, $acentos));
	$sb = strtolower(strtr((string)$b, $acentos));

	$c = strcmp($sa, $sb);
	// empate depois de tirar acento — "Sao" e "São" — não pode virar 0: uasort com 0
	// deixa a ordem por conta do algoritmo, e ela mudaria entre chamadas. O nome cru
	// desempata e a lista fica estável.
	return ($c !== 0) ? $c : strcmp((string)$a, (string)$b);
}


// O balanço de uma chamada: onde a mercadoria entrou e onde saiu, em dinheiro.
//
// A mesma mercadoria é medida em três lugares diferentes, e cada distância entre eles
// significa uma coisa:
//
//   A  o que os NÚCLEOS confirmaram receber   (distribuicao.dist_quantidade_recebido)
//   B  o que FINANÇAS confirmou para a Rede   (chaprod_recebido_confirmado) → paga o produtor
//   C  o que os CESTANTES receberam           (pedprod_entregue)            → cobra o cestante
//
//   A − B  o que Finanças abateu ao ler as justificativas: produto vencido sai da conta
//          do produtor. É julgamento humano, caso a caso, e já acontece hoje.
//   B − C  o que a Rede pagou e ninguém foi cobrado. Sobrou, foi doado, estragou depois
//          de aceito — ou a entrega não foi anotada.
//
// TUDO A PREÇO DE VENDA, que é o preço pelo qual o cestante é cobrado: a pergunta aqui é
// "quanto disto virou dívida de alguém". No razão o estoque é avaliado a preço de COMPRA,
// porque lá a pergunta é outra — quanto a Rede desembolsou por um ativo que ainda tem.
//
// O CONTADOR DE ENTREGAS NÃO REGISTRADAS vem junto de propósito. Sem ele, alguém leria
// como perda do núcleo o que é apenas entrega que ninguém anotou — e cobraria do núcleo
// por um erro de digitação. Medido na base: linha pedida sem entrega registrada é
// ordem de grandeza mais comum do que quebra de verdade.
//
// CONTRATO: array, ou null quando a chamada não existe ou a consulta não roda.
function balanco_da_chamada($cha_id)
{
	$res = executa_sql("SELECT c.cha_id, c.cha_dt_entrega, pt.prodt_nome, pt.prodt_mutirao "
	     . "FROM chamadas c "
	     . "JOIN produtotipos pt ON pt.prodt_id = c.cha_prodt "
	     . "WHERE c.cha_id = " . prep_para_bd($cha_id));
	if (!$res) return null;
	$cha = mysqli_fetch_array($res, MYSQLI_ASSOC);
	if (!$cha) return null;

	$id = (int)$cha['cha_id'];

	// prodt_mutirao diz se este tipo de chamada passa pelo mutirão. Quando NÃO passa
	// (Frescos e afins), o produtor entrega direto no núcleo: não há contagem central,
	// não há remessa do mutirão para o núcleo e não há estoque entre chamadas. Medido:
	// em toda a história das chamadas sem mutirão não existe UMA linha de estoque. Mostrar
	// essas medidas zeradas ali seria inventar etapa que não aconteceu.
	$tem_mutirao = ((int)$cha['prodt_mutirao'] === 1);

	// ---- o que o mutirão ENVIOU e o que o núcleo CONFIRMOU receber ----
	//
	// São duas colunas diferentes da mesma tabela, e a distância entre elas é o que se
	// perdeu no caminho — antes de o núcleo sequer abrir a caixa. dist_quantidade é o
	// que saiu do mutirão; dist_quantidade_recebido é o que o núcleo confirmou.
	// A COBERTURA vem junto, e não é detalhe. dist_quantidade é preenchida numa fração
	// das linhas em que dist_quantidade_recebido é — medido em doze meses, cerca de um
	// quarto. Sem dizer isso, um "enviou" bem abaixo do "recebeu" parece a corrente
	// quebrada, quando é só coluna não preenchida. O número é PISO, não total.
	$sql = "SELECT d.dist_nuc nuc, n.nuc_nome_curto nome, ";
	$sql.= "SUM(d.dist_quantidade * p.prod_valor_venda) e, ";
	$sql.= "SUM(d.dist_quantidade > 0) e_linhas, ";
	$sql.= "SUM(d.dist_quantidade_recebido > 0) v_linhas, ";
	// quantas divergências deste núcleo alguém já explicou por escrito. É o contrapeso
	// do aviso de linhas em branco: uma coisa é o núcleo dever explicação, outra é ele
	// já ter explicado — e sem este número as duas apareciam iguais na tela.
	$sql.= "SUM(d.dist_just_dif_entrega IS NOT NULL AND TRIM(d.dist_just_dif_entrega) <> '') just, ";
	$sql.= "SUM(d.dist_quantidade_recebido * p.prod_valor_venda) v ";
	$sql.= "FROM distribuicao d ";
	$sql.= "JOIN chamadas c ON c.cha_id = d.dist_cha ";
	$sql.= "JOIN nucleos n  ON n.nuc_id  = d.dist_nuc ";
	$sql.= "JOIN produtos p ON p.prod_id = d.dist_prod ";
	$sql.= "WHERE d.dist_cha = " . prep_para_bd($id) . " ";
	$sql.= "AND p.prod_ini_validade <= c.cha_dt_entrega AND p.prod_fim_validade >= c.cha_dt_entrega ";
	$sql.= "GROUP BY d.dist_nuc";

	$res = executa_sql($sql);
	if (!$res) return null;

	$nucleos = array();
	while ($r = mysqli_fetch_array($res, MYSQLI_ASSOC))
		$nucleos[(int)$r['nuc']] = array(
			'nuc_id' => (int)$r['nuc'], 'nome' => (string)$r['nome'],
			'enviou'  => ($r['e'] === null) ? 0.0 : round((float)$r['e'], 2),
			'recebeu' => ($r['v'] === null) ? 0.0 : round((float)$r['v'], 2),
			// quantas linhas trazem cada número: o "enviou" costuma ser bem menor
			'enviou_linhas'  => (int)$r['e_linhas'],
			'recebeu_linhas' => (int)$r['v_linhas'],
			'justificativas' => (int)$r['just'],
			'pediu' => 0.0, 'distribuiu' => 0.0, 'sem_registro' => 0);

	// ---- C: o que os cestantes de cada núcleo receberam ----
	//
	// O recorte é ped_nuc, e NÃO usr_nuc: quem troca de núcleo deixa os pedidos antigos
	// no núcleo antigo. Medido — juntar pelo núcleo atual dá números absurdos.
	$sql = "SELECT ped.ped_nuc nuc, n.nuc_nome_curto nome, ";
	$sql.= "SUM(pp.pedprod_entregue * p.prod_valor_venda) v, ";
	// A DEMANDA CRUA: o que os cestantes pediram, sem nada abatido. É o primeiro número
	// da corrente e o único que não depende de ninguém conferir nada — todos os outros
	// são alguém contando mercadoria depois. Sem ele a tabela começa pelo estoque, e não
	// dá para ver se a chamada atendeu o que foi pedido.
	$sql.= "SUM(pp.pedprod_quantidade * p.prod_valor_venda) q, ";
	// SÓ AS LINHAS QUE PODEM EXPLICAR A DIFERENÇA. Contar toda linha pedida sem entrega
	// registrada incluía produtos que o núcleo nunca confirmou ter recebido — e essas
	// contribuem zero dos dois lados da conta. O resultado era um núcleo com diferença
	// 0,00 e "29 sem entrega registrada" ao lado, o que faz quem lê procurar problema
	// onde não há.
	//
	// A linha só entra quando o núcleo CONFIRMOU RECEBER aquele produto: aí o recebido
	// entrou na soma, a entrega não, e a diferença pode ser só o registro faltando.
	$sql.= "SUM(pp.pedprod_quantidade > 0 AND pp.pedprod_entregue IS NULL ";
	$sql.= "    AND EXISTS (SELECT 1 FROM distribuicao d ";
	$sql.= "                WHERE d.dist_cha = ped.ped_cha AND d.dist_nuc = ped.ped_nuc ";
	$sql.= "                  AND d.dist_prod = pp.pedprod_prod ";
	$sql.= "                  AND d.dist_quantidade_recebido > 0)) sem ";
	$sql.= "FROM pedidos ped ";
	$sql.= "JOIN pedidoprodutos pp ON pp.pedprod_ped = ped.ped_id ";
	$sql.= "JOIN chamadas c  ON c.cha_id  = ped.ped_cha ";
	$sql.= "JOIN nucleos n   ON n.nuc_id  = ped.ped_nuc ";
	$sql.= "JOIN produtos p  ON p.prod_id = pp.pedprod_prod ";
	$sql.= "WHERE ped.ped_cha = " . prep_para_bd($id) . " AND ped.ped_fechado = 1 ";
	$sql.= "AND p.prod_ini_validade <= c.cha_dt_entrega AND p.prod_fim_validade >= c.cha_dt_entrega ";
	$sql.= "GROUP BY ped.ped_nuc";

	$res = executa_sql($sql);
	if (!$res) return null;

	while ($r = mysqli_fetch_array($res, MYSQLI_ASSOC))
	{
		$n = (int)$r['nuc'];
		// núcleo que entregou sem ter confirmado recebimento também precisa aparecer:
		// é justamente o caso em que a conta não fecha
		if (!isset($nucleos[$n]))
			$nucleos[$n] = array('nuc_id' => $n, 'nome' => (string)$r['nome'],
			                     'enviou' => 0.0, 'recebeu' => 0.0,
			                     'enviou_linhas' => 0, 'recebeu_linhas' => 0,
			                     'justificativas' => 0, 'pediu' => 0.0,
			                     'distribuiu' => 0.0, 'sem_registro' => 0);

		$nucleos[$n]['pediu']        = ($r['q'] === null) ? 0.0 : round((float)$r['q'], 2);
		$nucleos[$n]['distribuiu']   = round((float)$r['v'], 2);
		$nucleos[$n]['sem_registro'] = (int)$r['sem'];
	}

	$total = array('enviou' => 0.0, 'recebeu' => 0.0, 'distribuiu' => 0.0, 'sem_registro' => 0,
	               'enviou_linhas' => 0, 'recebeu_linhas' => 0, 'justificativas' => 0,
	               'pediu' => 0.0);
	foreach ($nucleos as $n => $x)
	{
		$nucleos[$n]['diferenca'] = round($x['recebeu'] - $x['distribuiu'], 2);
		// o que saiu do mutirão e o núcleo não confirmou: perdido no caminho, ou não conferido
		$nucleos[$n]['no_caminho'] = round($x['enviou'] - $x['recebeu'], 2);
		$total['pediu']        = round($total['pediu'] + $x['pediu'], 2);
		$total['enviou']       = round($total['enviou'] + $x['enviou'], 2);
		$total['recebeu']      = round($total['recebeu'] + $x['recebeu'], 2);
		$total['enviou_linhas']  += $x['enviou_linhas'];
		$total['recebeu_linhas'] += $x['recebeu_linhas'];
		$total['justificativas'] += $x['justificativas'];
		$total['distribuiu']   = round($total['distribuiu'] + $x['distribuiu'], 2);
		$total['sem_registro'] += $x['sem_registro'];
	}
	$total['diferenca']  = round($total['recebeu'] - $total['distribuiu'], 2);
	$total['no_caminho'] = round($total['enviou'] - $total['recebeu'], 2);

	// ORDEM ALFABÉTICA, a mesma da caixa de seleção de núcleo em toda tela do sistema
	// (ORDER BY nuc_nome_curto, em vinte lugares). Ordenava por diferença — o maior
	// primeiro —, e o argumento era "é o que se quer investigar". Mas quem confere volta
	// a esta tabela chamada após chamada procurando o SEU núcleo, e ele mudava de lugar a
	// cada vez. A diferença já salta pelo vermelho da coluna; a ordem serve para achar.
	uasort($nucleos, function ($a, $b) {
		return compara_nome_de_nucleo($a['nome'], $b['nome']);
	});

	// ---- B e o estoque, que são da chamada inteira e não de um núcleo ----
	// As duas confirmações da mesma entrega do produtor: chaprod_recebido é a contagem do
	// MUTIRÃO no dia; chaprod_recebido_confirmado é o que FINANÇAS aceitou depois de ler
	// as justificativas. recebimento.php:27 escolhe entre as duas pelo modo da tela.
	$sql = "SELECT SUM(cp.chaprod_recebido * p.prod_valor_venda) m, ";
	$sql.= "SUM(cp.chaprod_recebido > 0) m_linhas, ";
	$sql.= "SUM(cp.chaprod_recebido_confirmado > 0) b_linhas, ";
	$sql.= "SUM(cp.chaprod_recebido_confirmado * p.prod_valor_venda) b ";
	$sql.= "FROM chamadaprodutos cp ";
	$sql.= "JOIN chamadas c ON c.cha_id = cp.chaprod_cha ";
	$sql.= "JOIN produtos p ON p.prod_id = cp.chaprod_prod ";
	$sql.= "WHERE cp.chaprod_cha = " . prep_para_bd($id) . " AND cp.chaprod_disponibilidade <> '0' ";
	$sql.= "AND p.prod_ini_validade <= c.cha_dt_entrega AND p.prod_fim_validade >= c.cha_dt_entrega";

	$res = executa_sql($sql);
	if (!$res) return null;
	$r = mysqli_fetch_array($res, MYSQLI_ASSOC);
	$confirmado = ($r && $r['b'] !== null) ? round((float)$r['b'], 2) : 0.0;
	$mutirao    = ($r && $r['m'] !== null) ? round((float)$r['m'], 2) : 0.0;
	// guardados AGORA porque $r é reaproveitado pela consulta de estoque logo abaixo
	$mutirao_linhas    = $r ? (int)$r['m_linhas'] : 0;
	$confirmado_linhas = $r ? (int)$r['b_linhas'] : 0;

	$estoque = array('antes' => 0.0, 'depois' => 0.0);

	if ($tem_mutirao)
	{
		$sql = "SELECT SUM(IFNULL(e.est_prod_qtde_antes,0)  * p.prod_valor_venda) antes, ";
		$sql.= "SUM(IFNULL(e.est_prod_qtde_depois,0) * p.prod_valor_venda) depois ";
		$sql.= "FROM estoque e ";
		$sql.= "JOIN chamadas c ON c.cha_id = e.est_cha ";
		$sql.= "JOIN produtos p ON p.prod_id = e.est_prod ";
		$sql.= "WHERE e.est_cha = " . prep_para_bd($id) . " ";
		$sql.= "AND p.prod_ini_validade <= c.cha_dt_entrega AND p.prod_fim_validade >= c.cha_dt_entrega";

		$res = executa_sql($sql);
		if (!$res) return null;
		$r = mysqli_fetch_array($res, MYSQLI_ASSOC);

		$estoque = array(
			'antes'  => ($r && $r['antes']  !== null) ? round((float)$r['antes'], 2)  : 0.0,
			'depois' => ($r && $r['depois'] !== null) ? round((float)$r['depois'], 2) : 0.0,
		);
	}

	return array(
		'cha_id'     => $id,
		'tipo'       => (string)$cha['prodt_nome'],
		// quem lê a tela decide por aqui o que sequer existe nesta chamada:
		// sem mutirão não há contagem central, remessa aos núcleos, nem estoque
		'tem_mutirao' => $tem_mutirao,
		'dt'         => $cha['cha_dt_entrega'],
		'nucleos'    => array_values($nucleos),
		'total'      => $total,
		'confirmado' => $confirmado,
		// o que o mutirão contou chegar dos produtores, antes do julgamento de Finanças.
		// Preenchido numa minoria das linhas que Finanças confirma — é piso, não total.
		'mutirao'    => $mutirao,
		'mutirao_linhas'    => $mutirao_linhas,
		'confirmado_linhas' => $confirmado_linhas,
		'estoque'    => $estoque,
		// o que a Rede pagou e ninguém foi cobrado, já descontado o que ficou guardado
		'nao_cobrado' => round($confirmado + $estoque['antes'] - $total['distribuiu'] - $estoque['depois'], 2),
		// o julgamento de Finanças ao ler as justificativas
		'abatido'     => round($total['recebeu'] - $confirmado, 2),
	);
}


// O detalhe de um núcleo numa chamada: produto a produto, com a justificativa que alguém
// já escreveu e as linhas em branco nomeadas.
//
// EXISTE PORQUE O NÚMERO SOZINHO MANDA PROCURAR NO LUGAR ERRADO. Visto numa chamada de
// secos: o resumo apontava meia dúzia de linhas sem entrega registrada ao lado de uma
// diferença pequena. Só UMA delas era a diferença — um produto que chegou quebrado, com
// a justificativa já escrita. As outras eram produto inteiramente distribuído com a
// linha de alguém em branco, que não muda a conta.
//
// A JUSTIFICATIVA VEM JUNTO, e não é enfeite: ela responde a pergunta antes de alguém
// abrir outra tela. Está em distribuicao.dist_just_dif_entrega, preenchida na grande
// maioria das divergências do último ano — o hábito existe e funciona; faltava trazê-lo
// para cá.
//
// CONTRATO: array de produtos (vazio quando nada diverge), ou null quando a consulta não
// roda.
function detalhe_do_nucleo_na_chamada($cha_id, $nuc_id)
{
	$sql = "SELECT p.prod_id, p.prod_nome nome, p.prod_unidade unidade, ";
	$sql.= "p.prod_valor_venda preco, d.dist_quantidade enviou, ";
	$sql.= "d.dist_quantidade_recebido recebeu, ";
	$sql.= "d.dist_just_dif_entrega justificativa ";
	$sql.= "FROM distribuicao d ";
	$sql.= "JOIN chamadas c ON c.cha_id = d.dist_cha ";
	$sql.= "JOIN produtos p ON p.prod_id = d.dist_prod ";
	$sql.= "  AND p.prod_ini_validade <= c.cha_dt_entrega AND p.prod_fim_validade >= c.cha_dt_entrega ";
	$sql.= "WHERE d.dist_cha = " . prep_para_bd($cha_id) . " ";
	$sql.= "AND d.dist_nuc = " . prep_para_bd($nuc_id) . " ";
	// Recebido, OU explicado por escrito. Exigir só o recebido deixava de fora a
	// justificativa que existe justamente porque não houve recebimento — e há dezenas
	// dessas na base, com texto explicando que a mercadoria foi para outro núcleo. A tela
	// contava essas no aviso de justificativas e não mostrava nenhuma ao clicar: o mesmo
	// defeito do aviso de linhas em branco, um nível abaixo.
	//
	// entrega_divergencia_justificativa.php:65 é quem cria a linha assim — o INSERT dela
	// grava só a justificativa, e dist_quantidade_recebido fica NULL.
	$sql.= "AND (d.dist_quantidade_recebido > 0 ";
	$sql.= "     OR (d.dist_just_dif_entrega IS NOT NULL AND TRIM(d.dist_just_dif_entrega) <> '')) ";
	$sql.= "ORDER BY p.prod_nome";

	$res = executa_sql($sql);
	if (!$res) return null;

	$produtos = array();
	while ($r = mysqli_fetch_array($res, MYSQLI_ASSOC))
		$produtos[(int)$r['prod_id']] = array(
			'prod_id'      => (int)$r['prod_id'],
			'nome'         => (string)$r['nome'],
			'unidade'      => (string)$r['unidade'],
			'preco'        => round((float)$r['preco'], 2),
			'enviou'       => ($r['enviou'] === null) ? 0.0 : round((float)$r['enviou'], 2),
			// NULL quando a linha só existe pela justificativa: o núcleo não confirmou
			// recebimento nenhum daquele produto, e 0,00 diria que confirmou zero.
			'recebeu'      => ($r['recebeu'] === null) ? 0.0 : round((float)$r['recebeu'], 2),
			'entregue'     => 0.0,
			'justificativa'=> trim((string)$r['justificativa']),
			'em_branco'    => array(),
			// TODA linha de cestante deste produto, e não só as em branco: é o registro
			// que deu origem à nota. Quem lê "chegou quebrado no núcleo · R$ 49,00"
			// precisa poder ver de quem era o mel, quem pediu e quem levou — sem isso a
			// justificativa é palavra sem lastro, e conferir vira abrir outra tela.
			'cestantes'    => array());

	if (!count($produtos)) return array();

	// quanto os cestantes deste núcleo receberam de cada produto, e quem ficou em branco
	$sql = "SELECT pp.pedprod_prod prod, u.usr_nome_curto nome, ";
	$sql.= "pp.pedprod_quantidade pediu, pp.pedprod_entregue entregue ";
	$sql.= "FROM pedidos ped ";
	$sql.= "JOIN pedidoprodutos pp ON pp.pedprod_ped = ped.ped_id ";
	$sql.= "JOIN usuarios u        ON u.usr_id = ped.ped_usr ";
	$sql.= "WHERE ped.ped_cha = " . prep_para_bd($cha_id) . " ";
	$sql.= "AND ped.ped_nuc = " . prep_para_bd($nuc_id) . " AND ped.ped_fechado = 1 ";
	$sql.= "ORDER BY u.usr_nome_curto";

	$res = executa_sql($sql);
	if (!$res) return null;

	while ($r = mysqli_fetch_array($res, MYSQLI_ASSOC))
	{
		$id = (int)$r['prod'];
		if (!isset($produtos[$id])) continue;   // produto que o núcleo não recebeu

		$pediu    = round((float)$r['pediu'], 2);
		$entregue = ($r['entregue'] === null) ? null : round((float)$r['entregue'], 2);

		// cestante que não pediu e nada recebeu não é registro, é linha de grade
		if ($pediu > 0 || ($entregue !== null && $entregue > 0))
			$produtos[$id]['cestantes'][] = array(
				'nome'     => (string)$r['nome'],
				'pediu'    => $pediu,
				// null é "ninguém anotou", e 0 é "anotaram que não levou". A tela
				// distingue os dois, e é a distinção que o aviso em branco conta.
				'entregue' => $entregue);

		if ($entregue === null)
		{
			if ($pediu > 0)
				$produtos[$id]['em_branco'][] = array('nome' => (string)$r['nome'], 'pediu' => $pediu);
		}
		else
			$produtos[$id]['entregue'] = round($produtos[$id]['entregue'] + $entregue, 2);
	}

	// só o que tem algo a dizer: diferença, ou linha em branco, ou justificativa escrita
	$linhas = array();
	foreach ($produtos as $x)
	{
		$x['diferenca'] = round(($x['recebeu'] - $x['entregue']) * $x['preco'], 2);

		if (abs($x['diferenca']) < 0.005 && !count($x['em_branco']) && $x['justificativa'] === '')
			continue;

		$linhas[] = $x;
	}

	// maior diferença primeiro: é o que explica a conta
	usort($linhas, function ($a, $b) {
		if (abs(abs($a['diferenca']) - abs($b['diferenca'])) < 0.005) return strcasecmp($a['nome'], $b['nome']);
		return (abs($a['diferenca']) > abs($b['diferenca'])) ? -1 : 1;
	});

	return $linhas;
}
