<?php
// CARGA DE ABERTURA — passada única, para rodar à mão.
//
// Lê um CSV com o saldo de cada conta na data de corte e lança uma transação de tipo
// 'abertura' por conta, com a conta de Sistema do outro lado. O CSV NÃO mora neste
// repositório: ele tem nome e saldo de gente de verdade, e este repositório é público.
//
// SEM CONFIRMAÇÃO O SCRIPT SÓ ENSAIA: casa os nomes, mostra o que faria e não grava nada.
// É essa saída que a Rede confere antes de autorizar a carga — em especial a lista de
// nomes, porque lançar a dívida de uma pessoa na conta de outra é o pior erro possível
// aqui, e o único que ninguém percebe olhando o total.
//
// A ENTRADA VEM POR VARIÁVEL DE AMBIENTE, e não por argumento: o jeito como este projeto
// roda PHP dentro do container é `docker compose exec -T web-modern php < script.php`,
// que manda o script pela entrada padrão e deixa o $argv vazio.
//
//   CARGA_CSV=/tmp/abertura.csv                     → ensaio, nada é gravado
//   CARGA_CSV=/tmp/abertura.csv CARGA_CONFIRMAR=1   → lança de verdade
//
// O CSV, com cabeçalho e ponto e vírgula:
//
//   tipo;chave;nome;valor
//   rede;Rede (Sandra Bradesco);;4800.00
//   nucleo;Santa;;3644.06
//   cestante;Santa;Pedro;-24.28
//
//   tipo    rede, nucleo ou cestante
//   chave   o nome da conta da Rede, ou o nome curto do núcleo
//   nome    só em cestante — o nome como está na planilha do núcleo
//   valor   positivo é dinheiro em caixa (rede e núcleo) ou crédito (cestante);
//           negativo é dívida do cestante. O script converte para o movimento do saldo.
//
// Idempotente: conta que já tem abertura é pulada, e o script diz quantas pulou.

// O CAMINHO DO APP muda de máquina para máquina. No container é /var/www/html; rodando do
// repositório é ../public; e no servidor o script fica na pasta pessoal, fora do docroot —
// porque o CSV que ele lê tem nome e saldo de gente, e nada disso pode ficar num diretório
// que o servidor web serve. Lá o caminho vem por CARGA_APP.
$app = (string)getenv('CARGA_APP');
if ($app === '')
	$app = is_readable('/var/www/html/common.inc.php')
	     ? '/var/www/html'
	     : dirname(dirname(__FILE__)) . '/public';

require_once $app . "/common.inc.php";
require_once $app . "/financeiro.inc.php";

$arquivo   = (string)getenv('CARGA_CSV');
$confirmar = (getenv('CARGA_CONFIRMAR') === '1');

if ($arquivo === '' || !is_readable($arquivo))
{
	fwrite(STDERR, "uso: CARGA_CSV=<arquivo.csv> [CARGA_CONFIRMAR=1] php carga-abertura.php\n");
	exit(2);
}

$fh  = fopen($arquivo, 'r');
$cab = fgetcsv($fh, 0, ';');

if ($cab === false || count($cab) < 4)
{
	fwrite(STDERR, "ERRO: o CSV precisa do cabeçalho tipo;chave;nome;valor\n");
	exit(2);
}

$linhas = array();
while (($l = fgetcsv($fh, 0, ';')) !== false)
{
	if (count($l) < 4 || trim($l[0]) === '') continue;
	$linhas[] = array(
		'tipo'  => trim($l[0]),
		'chave' => trim($l[1]),
		'nome'  => trim($l[2]),
		'valor' => round((float)str_replace(',', '.', trim($l[3])), 2),
	);
}
fclose($fh);

function escalar_carga($sql)
{
	$res = executa_sql($sql);
	if (!$res) return '';
	$row = mysqli_fetch_array($res, MYSQLI_NUM);
	return $row ? $row[0] : '';
}

// ---- resolve a conta de CADA linha antes de gravar qualquer coisa ----
//
// Nada é lançado enquanto todas as linhas não tiverem sido resolvidas: a lista completa,
// com as pendências, é o que a Rede confere. Resolver e gravar ao mesmo tempo deixaria
// meia carga no banco quando a décima linha não casasse.
$resolvidas = array();
$pendencias = array();

foreach ($linhas as $l)
{
	$con = null; $quem = '';

	if ($l['tipo'] === 'rede')
	{
		$res = executa_sql("SELECT con_id FROM contas WHERE con_tipo = 'rede' AND con_archive = 0 "
		                 . "AND con_nome = " . prep_para_bd($l['chave']));
		$row = $res ? mysqli_fetch_array($res, MYSQLI_ASSOC) : null;
		if ($row) { $con = (int)$row['con_id']; $quem = $l['chave']; }
		else $pendencias[] = "conta da Rede não encontrada pelo nome: " . $l['chave'];
	}
	else
	{
		$res = executa_sql("SELECT nuc_id FROM nucleos WHERE nuc_archive = 0 "
		                 . "AND nuc_nome_curto = " . prep_para_bd($l['chave']));
		$row = $res ? mysqli_fetch_array($res, MYSQLI_ASSOC) : null;

		if (!$row)
			$pendencias[] = "núcleo não encontrado: " . $l['chave'];
		else if ($l['tipo'] === 'nucleo')
		{
			$con  = conta_do_nucleo((int)$row['nuc_id']);
			$quem = "caixa do núcleo " . $l['chave'];
			if (!$con) $pendencias[] = "núcleo sem conta — rode \"criar as contas que faltam\": " . $l['chave'];
		}
		else if ($l['tipo'] === 'cestante')
		{
			$usr = casa_cestante_por_nome((int)$row['nuc_id'], $l['nome']);
			if (!$usr)
				$pendencias[] = "cestante não casou, ou casou mais de um: " . $l['chave'] . " / " . $l['nome'];
			else
			{
				$completo = escalar_carga("SELECT usr_nome_completo FROM usuarios WHERE usr_id = " . (int)$usr);
				// no ensaio a conta NÃO é criada: ensaio que escreve não é ensaio
				$con  = $confirmar ? conta_do_cestante($usr, true) : conta_do_cestante($usr, false);
				$quem = $l['nome'] . " → " . $completo . " (usuário " . $usr . ")";
				if (!$con && !$confirmar) $quem .= " [a conta nasce na carga]";
			}
		}
		else $pendencias[] = "tipo desconhecido: " . $l['tipo'];
	}

	$resolvidas[] = array('linha' => $l, 'con' => $con, 'quem' => $quem);
}

// ---- mostra o que faria ----
printf("%-9s %-52s %12s\n", 'tipo', 'conta', 'valor');
foreach ($resolvidas as $r)
	printf("%-9s %-52s %12s\n", $r['linha']['tipo'], mb_substr_carga($r['quem'], 52),
	       number_format($r['linha']['valor'], 2, ',', '.'));

// substr sem mbstring: corta por bytes respeitando o começo de caractere UTF-8, para a
// tabela não sair com meio caractere no fim de uma linha
function mb_substr_carga($t, $n)
{
	if (strlen($t) <= $n) return $t;
	$corte = substr($t, 0, $n);
	while (strlen($corte) && (ord($corte[strlen($corte) - 1]) & 0xC0) === 0x80)
		$corte = substr($corte, 0, -1);
	return $corte;
}

$soma = 0.0;
foreach ($resolvidas as $r) $soma = round($soma + $r['linha']['valor'], 2);
printf("%-9s %-52s %12s\n", '', 'soma das linhas', number_format($soma, 2, ',', '.'));

if (count($pendencias))
{
	echo "\nPENDÊNCIAS — nenhuma destas será lançada:\n";
	foreach ($pendencias as $p) echo "  - $p\n";
}

if (!$confirmar)
{
	echo "\nEnsaio. Nada foi gravado. Para lançar de verdade, repita com CARGA_CONFIRMAR=1.\n";
	exit(count($pendencias) ? 1 : 0);
}

// ---- lança ----
$lancadas = 0; $puladas = 0; $falhas = 0;

foreach ($resolvidas as $r)
{
	if (!$r['con']) continue;

	// O SINAL DA PLANILHA E O DO RAZÃO NÃO SÃO O MESMO. Na planilha, positivo é dinheiro
	// em caixa (rede e núcleo) ou crédito do cestante. No razão, a conta que segura
	// dinheiro tem saldo negativo, e a de quem deve também — então o caixa inverte e o
	// cestante não.
	$delta = ($r['linha']['tipo'] === 'cestante') ? $r['linha']['valor'] : -$r['linha']['valor'];

	$tra = lanca_abertura_da_conta($r['con'], $delta);

	if ($tra === 'ja_tinha') { $puladas++; echo "  já tinha abertura: " . $r['quem'] . "\n"; }
	else if ($tra === null)  { $falhas++;  echo "  FALHOU: " . $r['quem'] . "\n"; }
	else $lancadas++;
}

echo "\nlançadas: $lancadas | puladas: $puladas | falhas: $falhas | pendências: " . count($pendencias) . "\n";
exit(($falhas || count($pendencias)) ? 1 : 0);
