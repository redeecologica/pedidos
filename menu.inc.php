
 <nav class="navbar navbar-default" role="navigation">

	<div class="container-fluid">
      <div class="navbar-header">      
        <span class="navbar-brand">Bem-vindo(a), <?php echo($_SESSION["usr.nome"]); ?></span>
       </div>

      <div class="collapse navbar-collapse">                
          <ul class="nav navbar-nav navbar-left">
          
            <li><a href="index.php"><i class="glyphicon glyphicon-home"></i> Início</a></li>
            <li><a href="meuspedidos.php"><i class="icon-pedidos-shopping-bag"></i> Meus Pedidos</a></li>
            		  
		  <?php  
		  
		   // menu de administração
			if($_SESSION[PAP_ADM] || $_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO]  || $_SESSION[PAP_RESP_MUTIRAO] )			  
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
			  		if($_SESSION[PAP_ADM] || $_SESSION[PAP_RESP_PEDIDO]  )			  
					{
			   ?>
                    <li><a href="produtores.php"><i class="glyphicon glyphicon-picture"></i> Produtores</a></li>                
                    <li><a href="produtos.php"><i class="glyphicon glyphicon-leaf"></i> Produtos</a></li>
	               	<li class="divider"></li>                    

	          <?php 
			  		} 			  
			  ?>

    		  <?php 
			  		if($_SESSION[PAP_ADM] || $_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_MUTIRAO] )			  
					{
			   ?>

                    <li><a href="mutirao.php"><i class="glyphicon glyphicon-wrench"></i> Mutirão</a></li>  
	               	<li class="divider"></li>
              <?php 
			  		} 			  
			  ?>
              
              
    
    		  <?php 
			  		if($_SESSION[PAP_ADM] || $_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO] )			  
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
            <li><a href="ajuda.php"><i class="glyphicon glyphicon-question-sign"></i> Ajuda</a></li>
<!--			<li><a href="contato.php"><i class="glyphicon glyphicon-comment"></i> Contato</a></li>-->
            <li class="divider-vertical"></li>
            <li class="dropdown">
              <a href="" class="dropdown-toggle" data-toggle="dropdown"><i class="glyphicon glyphicon-user"></i> Minha Conta <b class="caret"></b></a>
              <ul class="dropdown-menu">
                <li><a href="meusdados.php"><i class="glyphicon glyphicon-star"></i> Meus Dados</a></li>
			   <li><a href="senha_altera.php"><i class="glyphicon glyphicon-lock"></i> Alterar Senha</a></li>                
                <li class="divider"></li>
                <li><a href="login.php?logoff=sim"><i class="glyphicon glyphicon-arrow-left"></i> Sair (fazer logoff)</a></li>
              </ul>
          </ul>
        </div> <!-- /navbar-collapse -->  
      </div> <!-- /container -->
      
  </nav><!-- /navbar -->      