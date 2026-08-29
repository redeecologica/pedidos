<?php
  require  "common.inc.php";
  require_once(__DIR__ . "/financeiro.inc.php");

  verifica_seguranca();

  // Mesma pergunta que as quatro telas do grupo fazem. A trava do módulo não passa por
  // verifica_seguranca(): aquela função valida qualquer chamada de PAP_ADM sem olhar o
  // parâmetro (common.inc.php:103-110), e pode_lancar_pagamento() começa por
  // pode_ver_financeiro().
  if (!pode_ver_financas_do_nucleo())
  {
      adiciona_mensagem_status(MSG_TIPO_ERRO, "Usuário não possui permissão para a ação executada.");
      redireciona(PAGINAPRINCIPAL);
      exit();
  }

  top();
  abas_financeiras('nucleo', 'hub');
?>

<div class="panel panel-primary">
  <div class="panel-heading">O dinheiro que passa pelo núcleo</div>
  <div class="panel-body">
    <ul>
      <li>
        <strong>Pagamentos</strong> — registre o que cada cestante pagou e para onde o dinheiro
        foi: o caixa do núcleo, uma conta da Rede, ou direto a um produtor. É daqui que sai
        o saldo que cada pessoa vê em Meu Saldo.
      </li>
      <br>
      <li>
        <strong>Caixa</strong> — o extrato do dinheiro que está com o núcleo, e onde se lançam
        despesa, repasse à Rede, pagamento a produtor e outras receitas. O pagamento de
        cestante aparece aqui sozinho, vindo da aba anterior.
      </li>
      <br>
      <li>
        <strong>Fluxo de caixa</strong> — o ano inteiro, mês a mês: quanto entrou, quanto saiu
        por categoria, e quanto ficou em caixa ao fim de cada mês.
      </li>
      <br>
      <li>
        <strong>Resultado</strong> — a pergunta que o caixa não responde: <em>este núcleo se
        paga?</em> De um lado o que ele contribui (associação, taxa, doações); de outro os
        custos dele, incluindo a parte que lhe cabe dos custos da Rede.
        Fechar um mês no negativo não é erro — é o sinal de onde trabalhar.
      </li>
    </ul>
  </div>
</div>

<?php footer(); ?>
