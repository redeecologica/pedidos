<?php
// Prazo contábil da chamada: o padrão sugerido na criação e a validação da data.
//
// O prazo contábil é o que fecha a chamada para registro de entrega
// (entrega_cestante.php:20 lê "(cha_dt_prazo_contabil is null) OR
// (cha_dt_prazo_contabil > now())"). Nulo significa DENTRO DO PRAZO PARA SEMPRE:
// medido em 2026-08-28 na cópia de produção, 148 chamadas com entrega real estão
// abertas para edição até hoje, algumas de 2019.
//
// A causa não é desleixo. Até agora o campo só existia em financas_prazo.php,
// tela do time de finanças, e nada na criação da chamada pedia por ele — quem
// abre a chamada frequentemente não sabe ainda quando ela fecha. Preencher um
// padrão na criação tira a decisão do caminho crítico sem tirar a palavra final
// de quem sabe: financas_prazo.php continua podendo trocar a data.


// Dias entre a entrega e o fechamento, por tipo de chamada.
//
// Números medidos em 2026-08-28 sobre os últimos 12 meses de dados (entregas
// após 2025-06-13, âncora na última entrega da base), já sem o typo de ano do
// cha 1177:
//
//   Frescos   n=46  mediana 4  p80  6  p90  8  max 13
//   Secos     n=11  mediana 8  p80 10  p90 15  max 46
//   Bimestral n=5   mediana 38 p80 39 p90 52  max 52
//
// Os valores abaixo são a decisão da Rede, não a mediana: 4 para Frescos (bate
// com a mediana) e 6 para o resto. Para Secos Bimestral 6 é bem mais curto que a
// prática medida, e é escolha consciente — com n=5 não há amostra para fixar
// número, e encurtar erra para o lado de quem confere, não para o de deixar a
// chamada aberta.
//
// A busca é por prodt_nome porque é o que identifica o tipo para quem edita.
// Renomear "Frescos" faria o padrão dele virar 6 em vez de 4 — dois dias, que
// financas_prazo.php corrige. Renomear "Associação" custa mais: derruba a regra
// de copiar o prazo do Secos e cai no padrão. Nenhum dos dois erra dinheiro, e o
// conserto de verdade é uma coluna prodt_dias_prazo_contabil em produtotipos,
// que fica para quando a Rede quiser mexer nos números sem mexer no código.
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
// data de entrega para contar a partir de — nesse caso quem chama não grava nada
// e o campo segue nulo, que é honesto.
function prazo_contabil_padrao($cha_prodt, $cha_dt_entrega)
{
	$entrega = strtotime((string)$cha_dt_entrega);
	if (empty($cha_dt_entrega) || $entrega === false) return null;

	$prodt_nome = "";
	$res = executa_sql("SELECT prodt_nome FROM produtotipos WHERE prodt_id = " . prep_para_bd($cha_prodt));
	if ($res && $row = mysqli_fetch_array($res, MYSQLI_ASSOC)) $prodt_nome = $row['prodt_nome'];

	// A Associação é mensalidade rodando na máquina de pedidos, e fecha junto com
	// o Secos do mesmo ciclo — a entrega dela é marcada um dia antes da de Secos.
	// Conferido em 2026-08-28: das 17 Associações desde 2025, 15 entregam
	// exatamente 1 dia antes, 1 entrega 2 dias antes, e a chamada de Secos sempre
	// tem cha_id menor, ou seja, já existe quando a Associação é criada.
	if ($prodt_nome === 'Associação')
	{
		$prazo_secos = prazo_contabil_do_secos_pareado($cha_dt_entrega);

		// "Achei a chamada" e "achei prazo aproveitável" são coisas diferentes. Se
		// o Secos pareado ainda não tem prazo, copiar devolveria nulo — justamente
		// o que esta função existe para não fazer. Cai no padrão.
		if ($prazo_secos !== null) return $prazo_secos;
	}

	$dias = prazo_contabil_dias_por_tipo($prodt_nome);

	return date('Y-m-d H:i:s', strtotime("+$dias days", $entrega));
}


// Prazo contábil da chamada de Secos do mesmo ciclo, ou null se não houver uma
// pareável ou se ela ainda não tiver prazo.
//
// A janela é de 1 a 3 dias depois da entrega da Associação: cobre o caso comum
// (1 dia) e o de 2 dias já observado, sem alcançar o Secos do ciclo seguinte.
// Empate resolve pelo mais próximo.
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


// O prazo contábil tem de cair num dia POSTERIOR ao da entrega.
//
// A comparação é por dia, não por datetime, porque chamada.php grava a entrega
// às 23:59:59 — qualquer horário do mesmo dia seria "antes" e a regra viraria
// uma sobre relógio em vez de sobre calendário.
//
// Isto recusa prazo no mesmo dia da entrega, que hoje existe em 2 chamadas da
// base (cha 254 e cha 1130). É aperto deliberado: no mesmo dia o time de
// entrega fica sem janela nenhuma para registrar.
function prazo_contabil_valido($cha_dt_prazo_contabil, $cha_dt_entrega)
{
	$prazo   = strtotime((string)$cha_dt_prazo_contabil);
	$entrega = strtotime((string)$cha_dt_entrega);
	if (empty($cha_dt_prazo_contabil) || empty($cha_dt_entrega)) return false;
	if ($prazo === false || $entrega === false) return false;

	return date('Y-m-d', $prazo) > date('Y-m-d', $entrega);
}
