<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO]);
  top();
?>


<div class="panel panel-default">
  <div class="panel-heading">
     <strong>Lista de Chamadas</strong>
        <span class="pull-right">
        <div class="btn-group">
          <a class="btn btn-default dropdown-toggle btn-xs" data-toggle="dropdown" href="#"><i class="glyphicon glyphicon-plus"></i> adicionar nova chamada <span class="caret"></span></a>
          <ul class="dropdown-menu">
           <?php
                $res = executa_sql("SELECT prodt_id, prodt_nome FROM produtotipos ORDER BY prodt_nome");
                if($res)
                { 
                 while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
                 {
                     echo("<li><a href='chamada.php?action=" . ACAO_INCLUIR . "&cha_prodt=" . $row["prodt_id"] . "'>" . $row["prodt_nome"] . "</a></li>");
                  }
                }
            ?>   
          </ul>
        </div>
        </span>
  </div>
 <div class="panel-body">

<form class="form-inline" action="chamadas.php" method="post" name="frm_filtro" id="frm_filtro">

	<?php  
		$cha_prodt = request_get("cha_prodt",-1);
	?>
     <fieldset>
                 <div class="form-group">
  				<label for="cha_prodt">Tipo: </label>&nbsp;     
                <select name="cha_prodt" id="cha_prodt" onchange="javascript:frm_filtro.submit();" class="form-control">
                    <option value="-1" <?php echo(($cha_prodt==-1)?" selected" : "");?> >TODOS</option>
                    <?php
                        
                        $sql = "SELECT prodt_id, prodt_nome FROM produtotipos ORDER BY prodt_nome ";
                        $res = executa_sql($sql);
                        if($res)
                        {
                          while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
                          {
                             echo("<option value='" . $row['prodt_id'] . "'");
                             if($row['prodt_id']==$cha_prodt) echo(" selected");
                             echo (">" . $row['prodt_nome'] . "</option>");
                          }
                        }
                    ?>                        
                </select>                           
                 </div>     
                                           
                  
     </fieldset>
</form>
    </div>      
        
	<table class="table table-striped table-bordered">
		<thead>
			<tr>
            
				<th>#</th>
				<th>Tipo</th>
        		<th>Data de Entrega</th>
				<th>Início Pedido</th>
				<th>Término Pedido</th>
				<th>Pedidos</th>
			</tr>
		</thead>
		<tbody>
				<?php
					$sql = "SELECT cha_id, cha_prodt, cha_dt_entrega cha_dt_entrega_original, date_format(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, date_format(cha_dt_min,'%d/%m/%Y %H:%i') cha_dt_min, cha_dt_max cha_dt_max_original, date_format(cha_dt_max,'%d/%m/%Y %H:%i') cha_dt_max, count(ped_id) as cha_qtde_pedidos, prodt_nome ";
					$sql.= "FROM chamadas ";
					$sql.= "LEFT JOIN pedidos ON cha_id = ped_cha ";	
					$sql.= "LEFT JOIN produtotipos ON cha_prodt = prodt_id ";	
					$sql.= "WHERE 1 ";
					if($cha_prodt!=-1) 	 $sql.= " AND cha_prodt = '" . $cha_prodt .  "' ";						
					$sql.= "GROUP BY cha_id ";
					$sql.= "ORDER BY cha_dt_entrega_original DESC, prodt_nome, cha_dt_max_original DESC ";
					$sql.= "LIMIT 10 ";
													
					$res = executa_sql($sql);
					$contador = 0;
				    if($res)
					{
					 while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
				     {
				?>				 
				  <tr>
                  	 <td><?php echo(++$contador);?></td>               
					 <td><?php echo($row['prodt_nome']);?></td>               
					 <td><a href="chamada.php?action=0&amp;cha_id=<?php echo($row['cha_id']);?>"><?php echo($row['cha_dt_entrega']);?></a></td>
                     <td><?php echo($row['cha_dt_min']);?></td> 
					 <td><?php echo($row['cha_dt_max']);?> </td>                     
					 <td>&nbsp;<?php echo($row['cha_qtde_pedidos']);?>  &nbsp; <a class="btn btn-default btn-xs" href="pedidos.php?ped_cha=<?php echo($row['cha_id']);?>"><i class="glyphicon glyphicon-search"></i> consultar</a></td>     
                     
				  </tr>
				<?php 
				     }
				   }
				?>
		</tbody>
	</table>
   </div>


<span class="pull-right">
<div class="btn-group">
  <a class="btn btn-default dropdown-toggle" data-toggle="dropdown" href="#"><i class="glyphicon glyphicon-plus"></i> adicionar nova chamada <span class="caret"></span></a>
  <ul class="dropdown-menu">
   <?php
		$res = executa_sql("SELECT prodt_id, prodt_nome FROM produtotipos ORDER BY prodt_nome");
		if($res)
		{ 
		 while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		 {
			 echo("<li><a href='chamada.php?action=" . ACAO_INCLUIR . "&cha_prodt=" . $row["prodt_id"] . "'>" . $row["prodt_nome"] . "</a></li>");
		  }
		}
    ?>   
  </ul>
</div>
</span>

<?php 
 
	footer();
?>