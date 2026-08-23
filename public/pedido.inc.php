<?php
// Regras de estado do pedido usadas por pedido.php. Separado da página para
// poder ser testado sem passar por HTTP.


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
