<?php
// Regras de estado do pedido usadas por pedido.php. Separado da página para
// poder ser testado sem passar por HTTP.


// Quantidade do histórico em forma curta: "4,00" vira "4", "0,50" vira "0,5".
// O texto do histórico se repete em uma linha por produto — e a chamada de Secos
// chega a 181 produtos —, então decimal à toa é ruído multiplicado por 181.
// Só para exibição: o valor do campo em si é normalizado pelo formataInput do JS.
function formata_qtde_curta($valor)
{
	$s = formata_numero_de_mysql($valor);
	if (strpos($s, ',') === false) return $s;

	return rtrim(rtrim($s, '0'), ',');
}


// O que este cestante pediu nas últimas chamadas do mesmo tipo, para sugerir
// quantidades e apontar o que é novidade.
//
// Devolve:
//   quantidades[prod_id] = array('max' => ..., 'ultimo' => ...)
//   ofertados[prod_id]   = true   (estava à venda em alguma das chamadas usadas;
//                                  o que NÃO está aqui é novidade)
//   pedidos              = quantos pedidos anteriores entraram na conta
//
// Com pedidos = 0 a tela não mostra nada: sem histórico, todo produto pareceria
// novidade e a página viraria só ruído.
function historico_do_cestante($ped_usr, $cha_prodt, $cha_dt_entrega, $limite = 4)
{
	$vazio = array('quantidades' => array(), 'ofertados' => array(), 'pedidos' => 0);

	$usr_bd     = prep_para_bd($ped_usr);
	$prodt_bd   = prep_para_bd($cha_prodt);
	$entrega_bd = prep_para_bd($cha_dt_entrega);
	$limite     = (int)$limite;

	// Consulta à parte porque o Percona 5.6 não aceita LIMIT dentro de IN(...).
	// Só pedido enviado conta: rascunho não é compra.
	$sql = "SELECT p.ped_id, p.ped_cha FROM pedidos p ";
	$sql.= "JOIN chamadas c ON c.cha_id = p.ped_cha ";
	$sql.= "WHERE p.ped_usr = $usr_bd AND c.cha_prodt = $prodt_bd ";
	$sql.= "AND p.ped_fechado = 1 AND c.cha_dt_entrega < $entrega_bd ";
	$sql.= "ORDER BY c.cha_dt_entrega DESC LIMIT $limite";

	$res = executa_sql($sql);
	if (!$res) return $vazio;

	$peds = array(); $chas = array(); $ped_mais_recente = null;
	while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))
	{
		if ($ped_mais_recente === null) $ped_mais_recente = (int)$row['ped_id'];
		$peds[] = (int)$row['ped_id'];
		$chas[] = (int)$row['ped_cha'];
	}
	if (!$peds) return $vazio;

	$lista_peds = implode(",", $peds);
	$lista_chas = implode(",", $chas);

	// máximo e última quantidade na mesma passada
	$quantidades = array();
	$sql = "SELECT pedprod_prod, MAX(pedprod_quantidade) qtde_max, ";
	$sql.= "MAX(CASE WHEN pedprod_ped = $ped_mais_recente THEN pedprod_quantidade END) qtde_ultimo ";
	$sql.= "FROM pedidoprodutos WHERE pedprod_ped IN ($lista_peds) AND pedprod_quantidade > 0 ";
	$sql.= "GROUP BY pedprod_prod";
	$res = executa_sql($sql);
	if ($res) while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))
	{
		$quantidades[$row['pedprod_prod']] = array(
			'max'    => $row['qtde_max'],
			'ultimo' => $row['qtde_ultimo'],
		);
	}

	// o que estava à venda nessas chamadas; o que não estava, é novidade
	$ofertados = array();
	$sql = "SELECT DISTINCT chaprod_prod FROM chamadaprodutos ";
	$sql.= "WHERE chaprod_cha IN ($lista_chas) AND chaprod_disponibilidade <> '0'";
	$res = executa_sql($sql);
	if ($res) while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))
	{
		$ofertados[$row['chaprod_prod']] = true;
	}

	return array('quantidades' => $quantidades, 'ofertados' => $ofertados, 'pedidos' => count($peds));
}


// Marca o pedido como enviado.
//
// Devolve:
//   true  - esta gravação foi a transição de "não enviado" para "enviado"
//   false - o pedido já estava enviado (edição de pedido enviado)
//   null  - falha ao gravar
//
// A distinção importa porque, sem o botão "somente salvar", toda gravação passa
// pelo envio: se o e-mail de confirmação não ficar preso à transição, quem edita
// um pedido enviado recebe uma confirmação a cada vez que salva.
function marca_pedido_enviado($ped_id)
{
	$ped_id_bd = prep_para_bd($ped_id);

	$era_enviado = false;
	$res = executa_sql("SELECT ped_fechado FROM pedidos WHERE ped_id = $ped_id_bd");
	if ($res && $row = mysqli_fetch_array($res, MYSQLI_ASSOC))
	{
		$era_enviado = ($row['ped_fechado'] == 1);
	}

	// COALESCE: ped_dt_envio guarda o PRIMEIRO envio e não é reescrito depois.
	// É o que permite distinguir, num pedido não enviado, quem nunca enviou de
	// quem enviou e cancelou — os dois casos que a mudança do botão separa.
	$sql = "UPDATE pedidos SET ped_fechado = '1', ped_dt_envio = COALESCE(ped_dt_envio, NOW()) ";
	$sql.= "WHERE ped_id = $ped_id_bd";

	if (!executa_sql($sql)) return null;

	return !$era_enviado;
}
