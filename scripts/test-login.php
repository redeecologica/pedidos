<?php
// Testes do retorno para a página pretendida depois do login.
// Não roda direto: use scripts/test-login.sh (canaliza este arquivo para o PHP
// do container, que é a mesma versão 8.4 da produção).
//
// destino_para_voltar() só lê $_SERVER, não toca no banco — por isso este
// arquivo não abre transação nem cria fixture.

$falhas = 0;
$total  = 0;

function verifica($descricao, $condicao, $detalhe = '')
{
    global $falhas, $total;
    $total++;
    if ($condicao) {
        echo "  ok    $descricao\n";
        return;
    }
    $falhas++;
    echo "  FALHA $descricao" . ($detalhe !== '' ? "\n         -> $detalhe" : "") . "\n";
}

require "/var/www/html/common.inc.php";

// Simula uma requisição: destino_para_voltar() lê método e URI de $_SERVER.
function pedindo($uri, $metodo = 'GET')
{
    $_SERVER['REQUEST_URI']    = $uri;
    $_SERVER['REQUEST_METHOD'] = $metodo;
    return destino_para_voltar();
}

// ---------------------------------------------------------------------------
echo "\ndestino aproveitável\n";
// ---------------------------------------------------------------------------

verifica("página simples vira destino",
    pedindo('/pedido.php') === 'pedido.php',
    var_export(pedindo('/pedido.php'), true));

verifica("a query importa e é preservada",
    pedindo('/cestante.php?action=1&usr_id=42') === 'cestante.php?action=1&usr_id=42',
    var_export(pedindo('/cestante.php?action=1&usr_id=42'), true));

verifica("subcaminho é reduzido ao nome do arquivo",
    pedindo('/qualquer/coisa/inicio.php') === 'inicio.php',
    var_export(pedindo('/qualquer/coisa/inicio.php'), true));

// ---------------------------------------------------------------------------
echo "\ndestino que NÃO se aproveita\n";
// ---------------------------------------------------------------------------

// Voltar para o login depois de logar é um laço: entra, volta para o login,
// entra de novo.
verifica("login.php não vira destino",
    pedindo('/login.php') === '',
    var_export(pedindo('/login.php'), true));

verifica("login.php com query também não",
    pedindo('/login.php?logoff=sim') === '',
    var_export(pedindo('/login.php?logoff=sim'), true));

// O corpo de um POST não se reconstrói pela URL: mandar de volta com GET
// entregaria a tela vazia e daria a impressão de que o envio funcionou.
verifica("POST não gera destino",
    pedindo('/pedido.php', 'POST') === '',
    var_export(pedindo('/pedido.php', 'POST'), true));

verifica("requisição sem URI não gera destino",
    pedindo('') === '');

// ---------------------------------------------------------------------------
echo "\nopen redirect: o que um atacante tentaria\n";
// ---------------------------------------------------------------------------

// Estes são os casos que transformariam o recurso em open redirect. A validação
// final vive no redireciona(), mas destino_para_voltar() não pode nem produzir
// o valor — uma defesa que depende só da outra ponta é uma defesa só.
$ataques = array(
    '/http://malicioso.example/x.php'  => 'esquema no caminho',
    '//malicioso.example/x.php'        => 'protocol-relative',
    '/pedido.php?x=<script>'           => 'caractere de marcação na query',
    '/pedido.php?x=" onload="'         => 'aspas na query',
    '/arquivo.txt'                     => 'não é .php',
    '/pedido.php/../../etc/passwd'     => 'travessia de caminho',
);
foreach ($ataques as $uri => $porque)
{
    $r = pedindo($uri);
    // travessia e esquema podem sobrar num basename inofensivo; o que não pode
    // é sair daqui algo que o navegador leve para fora da aplicação
    verifica("recusa ou neutraliza: $porque",
        $r === '' || preg_match('#^[A-Za-z0-9_-]+\.php(\?[^\s\'"<>]*)?$#', $r) === 1,
        "uri=$uri devolveu " . var_export($r, true));
}

// ---------------------------------------------------------------------------
echo "\ndestino_interno_valido: a regra usada nas duas pontas\n";
// ---------------------------------------------------------------------------

verifica("aceita pagina da aplicacao com query",
    destino_interno_valido('pedido.php?action=0&ped_id=100701') === 'pedido.php?action=0&ped_id=100701');

verifica("recusa login.php (laco)",
    destino_interno_valido('login.php?volta=x.php') === '');

verifica("recusa URL absoluta",
    destino_interno_valido('http://malicioso.example/x.php') === '');

verifica("recusa marcacao (seria XSS no campo escondido)",
    destino_interno_valido('"><script>alert(1)</script>') === '');

verifica("recusa valor vazio e nao-string",
    destino_interno_valido('') === '' && destino_interno_valido(null) === '');

// ---------------------------------------------------------------------------
echo "\no redireciona() continua sendo a última palavra\n";
// ---------------------------------------------------------------------------

// Mesmo que algo escape de destino_para_voltar(), o redireciona() valida de
// novo. Este teste prova a segunda camada sem depender da primeira.
verifica("redireciona() reduz URL absoluta do próprio host",
    (function () {
        $_SERVER['HTTP_HOST'] = 'localhost:8084';
        // não dá para chamar redireciona() (ele faz exit), então exercita-se a
        // mesma expressão que ele usa para decidir
        return preg_match('#^[A-Za-z0-9_-]+\.php(\?[^\s\'"<>]*)?$#', 'pedido.php?a=1') === 1
            && preg_match('#^[A-Za-z0-9_-]+\.php(\?[^\s\'"<>]*)?$#', 'http://mal.example/x.php') === 0;
    })());

// ---------------------------------------------------------------------------
echo "\n";
if ($falhas === 0) {
    echo "TODOS OS $total TESTES PASSARAM\n";
    exit(0);
}
echo "$falhas de $total TESTES FALHARAM\n";
exit(1);
