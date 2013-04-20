	</div> <!-- end #mainContent -->
    
    
<!-- ***** FOOTER ***** -->

<div class="container" align="right">
    <hr>
    Copyright © 2013 - <a href="http://redeecologicario.org">Rede Ecológica</a> 
</div>  
  
     
   
  <?php require_once("registro_visita.inc.php"); ?>
  
  
  
  </body>
</html>


<?php 
	global $res;
	global $conn_link;
	
	if (isset($res) && !is_bool($res)) 		mysqli_free_result($res);
	if (isset($conn_link) && $conn_link)	mysqli_close($conn_link);
 ?>