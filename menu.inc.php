  <div class="navbar">
    <div class="navbar-inner">
    
	<div class="container">
        <span class="brand">Bem-vindo(a), <?php echo($_SESSION["usr.nome"]); ?></span>
        <div class="nav-collapse">
          <ul class="nav nav-pills pull-left">
            <li><a href="index.php"><i class="icon-home"></i> Início</a></li>
            <li><a href="meuspedidos.php"><i class="icon-pedidos-shopping-bag"></i> Meus Pedidos</a></li>
            		  
		  <?php 
		  
		   // menu de administração
			if($_SESSION[PAP_ADM] || $_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO]  || $_SESSION[PAP_RESP_MUTIRAO] )			  
			{
           ?>
            <li class="dropdown">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="icon-lock"></i> ADM <b class="caret"></b></a>
              <ul class="dropdown-menu">
              
              
			  <?php 
			  		if($_SESSION[PAP_ADM] || $_SESSION[PAP_RESP_PEDIDO])			  
					{
			   ?>
                        <li><a href="chamadas.php"><i class="icon-bell"></i> Chamadas</a></li>
                        <li><a href="pedidos.php"><i class="icon-shopping-cart"></i> Pedidos</a></li>
                        <li class="divider"></li>

              <?php 
			  		} 			  
			  ?>
              
              
                    
			  <?php 
			  		if($_SESSION[PAP_ADM] || $_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO] )			  
					{
			   ?>
                    <li><a href="nucleos.php"><i class="icon-th"></i> Núcleos</a></li>                
                    <li><a href="cestantes.php"><i class="icon-user"></i> Cestantes</a></li>
                    <li class="divider"></li>
              <?php 
			  		} 			  
			  ?>
                                                


			  <?php 
			  		if($_SESSION[PAP_ADM] || $_SESSION[PAP_RESP_PEDIDO]  )			  
					{
			   ?>
                    <li><a href="produtores.php"><i class="icon-picture"></i> Produtores</a></li>                
                    <li><a href="produtos.php"><i class="icon-leaf"></i> Produtos</a></li>
	               	<li class="divider"></li>                    

	          <?php 
			  		} 			  
			  ?>

    		  <?php 
			  		if($_SESSION[PAP_ADM] || $_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_MUTIRAO] )			  
					{
			   ?>

                    <li><a href="mutirao.php"><i class="icon-wrench"></i> Mutirão</a></li>  
	               	<li class="divider"></li>
              <?php 
			  		} 			  
			  ?>
              
              
    
    		  <?php 
			  		if($_SESSION[PAP_ADM] || $_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO] )			  
					{
			   ?>

                    <li><a href="relatorios.php"><i class="icon-list-alt"></i> Relatórios</a></li>  
	               	<li class="divider"></li>
              <?php 
			  		} 			  
			  ?>
              
                    
			  <?php 
			  		if($_SESSION[PAP_ADM])			  
					{
			   ?>
  					 <li><a href="administracao.php"><i class="icon-lock"></i> Administração</a></li>  

              <?php 
			  		} 			  
			  ?>
              
                   
                    
                    
              </ul>
            </li>
          <?php 

                } 	// fim menu administração
          ?>
          </ul>


          <ul class="nav nav-pills pull-right">
            <li class="divider-vertical"></li>
            <li><a href="ajuda.php"><i class="icon-question-sign"></i> Ajuda</a></li>
<!--			<li><a href="contato.php"><i class="icon-comment"></i> Contato</a></li>-->
            <li class="divider-vertical"></li>
            <li class="dropdown">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="icon-user"></i> Minha Conta <b class="caret"></b></a>
              <ul class="dropdown-menu">
                <li><a href="meusdados.php"><i class="icon-star"></i> Meus Dados</a></li>
			   <li><a href="senha_altera.php"><i class="icon-lock"></i> Alterar Senha</a></li>                
                <li class="divider"></li>
                <li><a href="login.php?logoff=sim"><i class="icon-arrow-left"></i> Sair (fazer logoff)</a></li>
              </ul>
          </ul>
        </div><!-- /.nav-collapse -->
      </div> <!-- /container -->
      
    </div><!-- /navbar-inner -->
  </div><!-- /navbar -->      