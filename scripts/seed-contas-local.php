<?php
// Cria contas de destino no banco LOCAL, para dar para registrar pagamento na tela.
// Não roda direto: use scripts/seed-contas-local.sh
//
// POR QUE ISTO EXISTE
// A tela de pagamentos esconde o botão de gravar quando não há conta de destino —
// de propósito: contas_de_destino() É a validação de registra_pagamento(), e sem
// destino cadastrado não existe pagamento válido para registrar. Em produção essas
// contas nascem no plano seguinte (caixa de núcleo, conta da Rede, produtores).
// Aqui elas são criadas à mão só para poder exercitar a tela.
//
// SÓ LOCAL. O runner recusa rodar fora do container de desenvolvimento.
//
// É idempotente: cria_conta() devolve null quando a conta já existe (as UNIQUE de
// con_nuc, con_forn e con_chave garantem isso), e o script apenas relata.

require "/var/www/html/common.inc.php";
require "/var/www/html/financeiro.inc.php";

// --limpar apaga o que este seed cria. Existe porque scripts/test-financeiro.sh
// pressupõe `contas` VAZIA: vários testes criam conta de núcleo e batem na UNIQUE de
// con_nuc quando já há uma. É limitação da suíte, não do seed — em produção `contas`
// também não estará vazia — e está anotada no docs/backlog.md.
if (in_array('--limpar', $argv, true))
{
    executa_sql("DELETE FROM lancamentos");
    executa_sql("DELETE FROM transacoes");
    executa_sql("DELETE FROM contas");
    $r = executa_sql("SELECT COUNT(*) n FROM contas");
    $row = $r ? mysqli_fetch_array($r, MYSQLI_ASSOC) : null;
    echo "apagado. contas agora: " . ($row ? $row['n'] : '?') . "\n";
    echo "(lancamentos e transacoes tambem, porque conta com lancamento nao se apaga)\n";
    exit(0);
}

$criadas = 0;
$havia   = 0;

function valor_escalar_seed($sql)
{
    $res = executa_sql($sql);
    if (!$res) return null;
    $row = mysqli_fetch_array($res, MYSQLI_NUM);
    return $row ? $row[0] : null;
}

function conta($rotulo, $con_id)
{
    global $criadas, $havia;
    if ($con_id) { $criadas++; echo "  criada   $rotulo (con_id $con_id)\n"; }
    else         { $havia++;   echo "  ja havia $rotulo\n"; }
}

echo "contas da Rede\n";
// Duas, numeradas, porque a Rede recebe em mais de uma conta — na prática a conta
// pessoal de quem está com o dinheiro. O nome é editável em ADM e vira algo como
// "Rede (conta Adelina)"; a con_chave é que não muda, e é ela que identifica a linha
// se alguém renomear. Foi essa a lição da Task 3: identidade em rótulo editável faz
// renomear criar uma segunda conta em silêncio.
//
// Note que NÃO chamamos conta_da_rede() aqui. Aquela função devolve a conta de
// con_chave 'rede_principal', que é a contraparte dos débitos de entrega no plano
// seguinte — outro papel, e não um destino de pagamento. Ela nasce quando a
// materialização precisar dela.
foreach (array('rede_ecologica_1' => 'Rede Ecológica 1',
               'rede_ecologica_2' => 'Rede Ecológica 2') as $chave => $nome)
{
    conta($nome, cria_conta('rede', array('con_nome' => $nome, 'con_chave' => $chave)));
}

echo "\ncaixa de cada núcleo ativo\n";
$res = executa_sql("SELECT nuc_id, nuc_nome_curto FROM nucleos WHERE nuc_archive = 0 ORDER BY nuc_nome_curto");
if (!$res) { fwrite(STDERR, "ERRO: nao consegui listar os nucleos.\n"); exit(2); }
while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))
{
    conta("Caixa " . $row['nuc_nome_curto'],
          cria_conta('nucleo', array('con_nuc' => $row['nuc_id'], 'con_nome' => 'Caixa ' . $row['nuc_nome_curto'])));
}

echo "\nalguns produtores, para o caso de pagamento direto\n";
$res = executa_sql("SELECT forn_id, forn_nome_curto FROM fornecedores WHERE forn_archive = 0 ORDER BY forn_nome_curto LIMIT 3");
if ($res) while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))
{
    conta("Produtor " . $row['forn_nome_curto'],
          cria_conta('produtor', array('con_forn' => $row['forn_id'], 'con_nome' => $row['forn_nome_curto'])));
}

echo "\n$criadas criada(s), $havia ja existia(m).\n";
echo "total de contas de destino agora: " . count((array)contas_de_destino()) . "\n";
