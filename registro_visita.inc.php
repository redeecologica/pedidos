 <?php 
 if (GA_ACCOUNT!="")
 {
 ?>
  	 <script type="text/javascript">    
      var _gaq = _gaq || [];
      _gaq.push(['_setAccount', '<?php echo(GA_ACCOUNT);?>']);
      _gaq.push(['_setDomainName', '<?php echo(GA_DOMAIN);?>']);      
      _gaq.push(['_setCustomVar',
          1,        
          'usuario',
          '<?php echo( isset($_SESSION["usr.id"]) ? $_SESSION["usr.id"] : "nao logado"  ); ?>',	  
       ]);    
      _gaq.push(['_setCustomVar',
          2,        
          'acao',
          '<?php echo( (isset($action) && $action!="" ) ?  $action : "padrao"  ); ?>',	  
       ]);          
      _gaq.push(['_trackPageview']);    
      (function() {
        var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
        ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
        var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
      })();    
    </script>
 <?php  
 } 
 ?>