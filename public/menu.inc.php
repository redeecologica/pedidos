<?php
  // O módulo financeiro precisa estar carregado AQUI, e não só na tela dele. Este
  // arquivo é incluído pelo header.inc.php, que top() carrega em TODA página, e
  // conta_cestante.php era o único lugar de public/ a carregar o financeiro — então o
  // item do menu só aparecia na página para a qual ele aponta. Medido com Administrador
  // + Beta Tester antes deste require: "Meu Extrato" saía 0 vezes em inicio.php,
  // meuspedidos.php e contatos.php, e 1 vez em conta_cestante.php. Menu que só se mostra
  // a quem já chegou não é menu.
  //
  // O arquivo é só definição de função mais um define guardado (financeiro.inc.php:102):
  // sem require, sem session_start, sem saída.
  //
  // __DIR__ porque include relativo depende do diretório de trabalho, e em CLI ele não é
  // este — mesmo padrão do common.inc.php:3 e do cron_lembrete.php:29.
  //
  // Custo medido, porque agora as 77 páginas da varredura carregam o arquivo. Ele cresce
  // a cada tarefa e o número aqui envelhece junto — já esteve em 641 (velho no mesmo
  // commit em que foi medido), 643 e 870. Em 2026-08-28, depois do fix round final:
  // 935 linhas e 47.815 bytes.
  //
  // Pior caso, com opcache DESLIGADO (CLI do container, 300 processos por rodada, duas
  // rodadas): +215 ms e +141 ms no total, ou seja 0,5 a 0,7 ms por processo. Com o opcache
  // LIGADO, que é como o container web roda: inicio.php por HTTP saiu 9,2 ms ± 1,2 sem o
  // require e 9,2 ms ± 1,2 com ele (hyperfine, 150 execuções cada) — as duas médias
  // coincidiram, e a diferença segue sem se separar do ruído. Medido aqui, não no
  // servidor da Locaweb.
  require_once(__DIR__ . "/financeiro.inc.php");
?>
 <nav class="navbar navbar-default hidden-print" role="navigation">

	<div class="container-fluid">
      <div class="navbar-header">        	   
        <span class="navbar-brand"><small>Bem-vindo(a), <strong><?php echo(h(strtok((string)$_SESSION["usr.nome"], " "))); ?></strong></small></span>
       </div>

      <div class="collapse navbar-collapse">                
          <ul class="nav navbar-nav navbar-left">
          
            <li><a href="index.php"><i class="glyphicon glyphicon-home"></i> Início</a></li>
            <li><a href="meuspedidos.php"><i class="icon-pedidos-shopping-bag"></i> Meus Pedidos</a></li>

		  <?php
		   // Módulo financeiro, ainda atrás do papel Beta Tester.
		   //
		   // Esconder o item não tranca nada: quem tranca é a MESMA pergunta refeita
		   // dentro do conta_cestante.php, antes de qualquer saída. Aqui não há segunda
		   // cópia da regra — só a chamada.
			if(pode_ver_financeiro())
			{
		   ?>
            <li><a href="conta_cestante.php"><i class="glyphicon glyphicon-list"></i> Meu Saldo</a></li>
		  <?php
			}
		   ?>

		    <li><a href="contatos.php"><i class="glyphicon glyphicon-phone-alt"></i> Contatos</a></li>
          
            		  
		  <?php  
		  
		   // menu de administração
			if($_SESSION[PAP_ADM] || $_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO]  || $_SESSION[PAP_RESP_MUTIRAO] || $_SESSION[PAP_ACOMPANHA_PRODUTOR] || $_SESSION[PAP_ACOMPANHA_RELATORIOS] || $_SESSION[PAP_RESP_ENTREGA] || $_SESSION[PAP_RESP_FINANCAS] )			  
			{
           ?>
            <li class="dropdown">
              <a href="" class="dropdown-toggle" data-toggle="dropdown"><i class="glyphicon glyphicon-lock"></i> ADM <b class="caret"></b></a>
              <ul class="dropdown-menu">
              
              
			  <?php 
			  		if($_SESSION[PAP_ADM] || $_SESSION[PAP_RESP_PEDIDO])			  
					{
			   ?>
                        <li><a href="chamadas.php"><i class="glyphicon glyphicon-bell"></i> Chamadas</a></li>
                        <li><a href="pedidos.php"><i class="glyphicon glyphicon-shopping-cart"></i> Pedidos</a></li>
                        <li class="divider"></li>

              <?php 
			  		} 			  
			  ?>
              
              
                    
			  <?php 
			  		if($_SESSION[PAP_ADM] || $_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO] )			  
					{
			   ?>
                    <li><a href="nucleos.php"><i class="glyphicon glyphicon-th"></i> Núcleos</a></li>                
                    <li><a href="cestantes.php"><i class="glyphicon glyphicon-user"></i> Cestantes</a></li>
                    
					  <?php 
                            if($_SESSION[PAP_ADM] || $_SESSION[PAP_RESP_PEDIDO] )			  
                            {
                       ?>
                            <li><a href="cestantes_email.php"><i class="glyphicon glyphicon-envelope"></i> Emails</a></li> 
                      <?php 
                            } 			  
                      ?>
                                  
                    <li class="divider"></li>
              <?php 
			  		} 			  
			  ?>
                                                


			  <?php 
			  		if($_SESSION[PAP_ADM] || $_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_ACOMPANHA_PRODUTOR] )			  
					{
			   ?>
                    <li><a href="produtores.php"><i class="glyphicon glyphicon-picture"></i> Produtores</a></li>                
                    <li><a href="produtos.php"><i class="glyphicon glyphicon-leaf"></i> Produtos</a></li>
	               	<li class="divider"></li>                    

	          <?php 
			  		} 			  
			  ?>

    		  <?php 
			  		if($_SESSION[PAP_ADM] || $_SESSION[PAP_RESP_MUTIRAO] || $_SESSION[PAP_RESP_NUCLEO]   || $_SESSION[PAP_RESP_ENTREGA]  || $_SESSION[PAP_RESP_FINANCAS])			  
					{
			   ?>               		
                    
					  <?php 
                            if($_SESSION[PAP_ADM] ||  $_SESSION[PAP_RESP_MUTIRAO] )			  
                            {
                       ?>
                               <li><a href="mutirao.php"><i class="glyphicon glyphicon-wrench"></i> Mutirão</a></li>  
                      <?php 
                            } 			  
                      ?>   
                      
                      
					  <?php 
                            if( $_SESSION[PAP_ADM] || $_SESSION[PAP_RESP_ENTREGA]  || $_SESSION[PAP_RESP_FINANCAS] )			  
                            {
                       ?>
			                    <li><a href="entregas.php"><i class="glyphicon glyphicon-apple"></i> Entregas</a></li>   
                                
                      <?php 
                            } 			  
                      ?> 
                      
                                                          

					  <?php 
                            // FINANÇAS, EM DOIS GRUPOS. O módulo tem duas audiências que
                            // não se misturam — a Rede confirmou que quem cuida das
                            // finanças de um núcleo não é quem cuida das da Rede —, e as
                            // telas já diziam o mesmo: metade exige RESP_NÚCLEO, a outra
                            // metade RESP_FINANÇAS.
                            //
                            // Antes eram sete itens soltos aqui, e um responsável de
                            // núcleo via "Despesas da Rede" e "Quotas de rateio", telas
                            // que iam recusá-lo. Menu que oferece o que não se pode abrir
                            // ensina a ignorar o menu.
                            //
                            // Cada hub refaz a própria pergunta por dentro, antes de
                            // qualquer saída: esconder item é conveniência, não trava.
                            if (pode_ver_financas_do_nucleo())
                            {
                       ?>
			                    <li><a href="financas_nucleo.php"><i class="glyphicon glyphicon-piggy-bank"></i> Finanças do núcleo</a></li>
                      <?php 
                            } 			  
                      ?> 

					  <?php 
                            // A tela de Finanças existia antes do módulo, com duas abas.
                            // Quem não tem o papel Beta Tester continua vendo aquelas duas
                            // e mais nada — o módulo segue invisível até estar pronto.
                            if( $_SESSION[PAP_ADM] || $_SESSION[PAP_RESP_FINANCAS] )			  
                            {
                       ?>
			                    <li><a href="financas.php"><i class="glyphicon glyphicon-usd"></i> Finanças da Rede</a></li>    
                      <?php 
                            } 			  
                      ?> 


					  <?php 
                            if( $_SESSION[PAP_ADM] || $_SESSION[PAP_RESP_NUCLEO] || $_SESSION[PAP_RESP_ENTREGA] || $_SESSION[PAP_RESP_FINANCAS] )			  
                            {
                       ?>
                                <li><a href="cestantes_quadro.php"><i class="glyphicon glyphicon-th-list"></i> Quadro de Cestantes</a></li> 
                                
                      <?php 
                            } 			  
                      ?>                                                           


	               	<li class="divider"></li>
              <?php 
			  		} 			  
			  ?>
              
              
    
    		  <?php 
			  		if($_SESSION[PAP_ADM] || $_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO] || $_SESSION[PAP_ACOMPANHA_PRODUTOR] || $_SESSION[PAP_ACOMPANHA_RELATORIOS] || $_SESSION[PAP_RESP_ENTREGA] || $_SESSION[PAP_RESP_FINANCAS] )			  
					{
			   ?>

                    <li><a href="relatorios.php"><i class="glyphicon glyphicon-list-alt"></i> Relatórios</a></li>  
	               	<li class="divider"></li>
              <?php 
			  		} 			  
			  ?>
              
                    
			  <?php 
			  		if($_SESSION[PAP_ADM])			  
					{
			   ?>
  					 <li><a href="administracao.php"><i class="glyphicon glyphicon-lock"></i> Administração</a></li>  

              <?php 
			  		} 			  
			  ?>
              
                   
                    
                    
              </ul>
            </li>
          <?php 

                } 	// fim menu administração
          ?>
          </ul>


          <ul class="nav navbar-nav navbar-right">
            <li class="divider-vertical"></li>
            <li class="dropdown">
              <a href="" class="dropdown-toggle" data-toggle="dropdown"><i class="glyphicon glyphicon-user"></i> Minha Conta <b class="caret"></b></a>
              <ul class="dropdown-menu">
                <?php
                  // Três grupos, separados: os dados da própria conta, depois Ajuda, e
                  // Sair por último.
                  //
                  // Ajuda desceu do primeiro nível para cá porque dentro dos 940px do
                  // .container (complemento.css:5, a largura exata do logo) a faixa não
                  // comportava a saudação mais sete itens — e Ajuda é o de menor uso.
                ?>
                <li><a href="meusdados.php"><i class="glyphicon glyphicon-star"></i> Meus Dados</a></li>
                <li><a href="senha_altera.php"><i class="glyphicon glyphicon-lock"></i> Alterar Senha</a></li>
                <li class="divider"></li>
                <li><a href="ajuda.php"><i class="glyphicon glyphicon-question-sign"></i> Ajuda</a></li>
                <li class="divider"></li>
                <li><a href="login.php?logoff=sim"><i class="glyphicon glyphicon-arrow-left"></i> Sair (fazer logoff)</a></li>
              </ul>
          </ul>
        </div> <!-- /navbar-collapse -->  
      </div> <!-- /container -->
      
  </nav><!-- /navbar -->      