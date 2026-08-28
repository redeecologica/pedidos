<?php
// Prazo contábil da chamada: o padrão sugerido na criação e a validação da data.
//
// Prazo nulo deixa a chamada aberta para registro de entrega para sempre —
// entrega_cestante.php lê "(cha_dt_prazo_contabil is null) OR (... > now())".
// O campo só existia em financas_prazo.php, tela do time de finanças, e nada na
// criação da chamada pedia por ele. O padrão tira a decisão do caminho crítico
// sem tirar a palavra final de quem sabe.
//
// Os dias por tipo são decisão da Rede. A busca é por prodt_nome: renomear um
// tipo faz o padrão dele cair no genérico, e quem quiser mexer nos números sem
// mexer no código precisa de uma coluna em produtotipos.
define('PRAZO_CONTABIL_DIAS_PADRAO', 6);

function prazo_contabil_dias_por_tipo($prodt_nome)
{
	$por_tipo = array(
		'Frescos'         => 4,
		'Secos'           => 6,
		'Secos Bimestral' => 6,
	);

	return isset($por_tipo[$prodt_nome]) ? $por_tipo[$prodt_nome] : PRAZO_CONTABIL_DIAS_PADRAO;
}


// Prazo sugerido para uma chamada nova. Devolve datetime, ou null quando não há
// entrega de onde contar.
function prazo_contabil_padrao($cha_prodt, $cha_dt_entrega)
{
	$entrega = strtotime((string)$cha_dt_entrega);
	if (empty($cha_dt_entrega) || $entrega === false) return null;

	$prodt_nome = "";
	$res = executa_sql("SELECT prodt_nome FROM produtotipos WHERE prodt_id = " . prep_para_bd($cha_prodt));
	if ($res && $row = mysqli_fetch_array($res, MYSQLI_ASSOC)) $prodt_nome = $row['prodt_nome'];

	// A Associação é mensalidade rodando na máquina de pedidos e fecha junto com o
	// Secos do mesmo ciclo — a entrega dela é marcada um dia antes da de Secos.
	if ($prodt_nome === 'Associação')
	{
		$prazo_secos = prazo_contabil_do_secos_pareado($cha_dt_entrega);

		// "Achei a chamada" e "achei prazo aproveitável" são coisas diferentes: o
		// Secos pode não ter prazo, e a base tem prazo inválido (há chamada com
		// fechamento anterior à própria entrega). Copiar qualquer um dos dois faria
		// a Associação nascer trancada para registro de entrega.
		if ($prazo_secos !== null && prazo_contabil_valido($prazo_secos, $cha_dt_entrega)) return $prazo_secos;
	}

	$dias = prazo_contabil_dias_por_tipo($prodt_nome);

	return date('Y-m-d H:i:s', strtotime("+$dias days", $entrega));
}


// Prazo do Secos do mesmo ciclo, ou null se não houver par ou ele não tiver
// prazo. A janela de 1 a 3 dias cobre o caso comum sem alcançar o ciclo seguinte;
// empate resolve pelo mais próximo.
function prazo_contabil_do_secos_pareado($dt_entrega_assoc)
{
	$sql = "SELECT c.cha_dt_prazo_contabil FROM chamadas c ";
	$sql.= "JOIN produtotipos pt ON pt.prodt_id = c.cha_prodt AND pt.prodt_nome = 'Secos' ";
	$sql.= "WHERE c.cha_dt_prazo_contabil IS NOT NULL ";
	$sql.= "AND DATEDIFF(c.cha_dt_entrega, " . prep_para_bd($dt_entrega_assoc) . ") BETWEEN 1 AND 3 ";
	$sql.= "ORDER BY DATEDIFF(c.cha_dt_entrega, " . prep_para_bd($dt_entrega_assoc) . ") ";
	$sql.= "LIMIT 1";

	$res = executa_sql($sql);
	if (!$res) return null;
	$row = mysqli_fetch_array($res, MYSQLI_ASSOC);

	return $row ? $row['cha_dt_prazo_contabil'] : null;
}


// O prazo tem de cair num dia POSTERIOR ao da entrega.
//
// Compara por dia, não por datetime, porque a entrega é gravada às 23:59:59 —
// qualquer horário do mesmo dia seria "antes". Isso recusa também o mesmo dia,
// de propósito: ali o time de entrega ficaria sem janela nenhuma.
function prazo_contabil_valido($cha_dt_prazo_contabil, $cha_dt_entrega)
{
	$prazo   = strtotime((string)$cha_dt_prazo_contabil);
	$entrega = strtotime((string)$cha_dt_entrega);
	if (empty($cha_dt_prazo_contabil) || empty($cha_dt_entrega)) return false;
	if ($prazo === false || $entrega === false) return false;

	return date('Y-m-d', $prazo) > date('Y-m-d', $entrega);
}
