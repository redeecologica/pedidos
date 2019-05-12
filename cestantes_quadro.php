<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO] || $_SESSION[PAP_RESP_ENTREGA]  || $_SESSION[PAP_RESP_FINANCAS]);  
  top();
 

 $dt_ini=request_get("dt_ini",date('01/m/Y')); 
 $dt_fim=request_get("dt_fim",date('d/m/Y', strtotime('last day of this month'))); 
 
 $nuc_id=request_get("nuc_id",$_SESSION['usr.nuc']); 



?>

  
  <div class="panel panel-default">
  <div class="panel-heading">
     <strong>Quadro de Cestantes</strong>

  </div>

  	  
 <div class="panel-body">
 
 <form class="form-inline"  method="get" name="frm_filtro" id="frm_filtro">
	<?php  

	?>
     <fieldset>

          <div class="form-group">          
  				<label for="nuc_id">Núcleo: </label>            
                <select name="nuc_id" id="nuc_id" onchange="javascript:frm_filtro.submit();" class="form-control">
                    <option value="-1" <?php echo(($nuc_id==-1)?" selected" : ""); ?> >SELECIONAR</option>
                    <option value="-1">-------------</option>                     
                    <?php
                        
                        $sql = "SELECT nuc_id, nuc_nome_curto, nuc_archive ";
                        $sql.= "FROM nucleos ";
                        $sql.= "ORDER BY nuc_archive, nuc_nome_curto ";
                        $res = executa_sql($sql);
                        if($res)
                        {
						  $arquivados=0;
                          while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
                          {
							 if(!$arquivados)
							 {
								 if($row["nuc_archive"]==1) 
								 {
									 echo("<option value='-1'>-------------</option>");									 
									 $arquivados=1;
								 }
							 }
                             echo("<option value='" . $row['nuc_id'] . "'");
                             if($row['nuc_id']==$nuc_id) echo(" selected");
                             echo (">" . $row['nuc_nome_curto'] . "</option>");
                          }
                        }
                    ?>                        
                </select>  
           </div>
                
		&nbsp;&nbsp;

     	<div class="form-group">
  			   
            	<label for="dt_ini" class="control-label">Período:  De </label> 
                 <input type="text" name="dt_ini" id="dt_ini"  class="data form-control" value="<?php echo($dt_ini);?>">
				 <label for="dt_fim" class="control-label">Até </label>       
                 <input type="text" name="dt_fim" id="dt_fim"  class="data form-control" value="<?php echo($dt_fim);?>">                 
		</div>                 


          
          
        <div class="form-group">           	
                 <input type="submit" name="btn_consultar" id="btn_consultar"  class="form-control btn btn-default" value="consultar">
		</div>      
        
           
         </fieldset>
    </form>
    
    </div>
    
   </div> 
    
    
      	
  
    

<?php 
//date_format(usr_desde,'%d/%m/%Y') usr_desde
if($nuc_id!=-1) //checar parametros; data inicial e final
{

	$sql="	SELECT usr_id ";
	$sql.="	FROM  ";
	$sql.="	(  ";
	$sql.="	SELECT usr_id, usr_archive ";
	$sql.="	FROM usuarios u LEFT JOIN nucleos ON usr_nuc = nuc_id  ";
	$sql.="	WHERE ";
	if($nuc_id!=-1) $sql.= "usr_nuc =  " . prep_para_bd($nuc_id) . " AND " ;
	$sql.="	usr_dt_atualizacao <=  " . prep_para_bd(formata_data_para_mysql($dt_fim)) . "  ";
	$sql.="	UNION  ";
	$sql.="	SELECT a.usrlog_usr usr_id, usrlog_archive usr_archive ";
	$sql.="	FROM usuarios_changelog a  ";
	$sql.="	 INNER JOIN  ";
	$sql.="	  (SELECT usrlog_usr, MAX(usrlog_dt_atualizacao) AS ult_atualizacao  ";
	$sql.="	  FROM usuarios_changelog WHERE usrlog_dt_atualizacao <=  " . prep_para_bd(formata_data_para_mysql($dt_ini)) . "  ";
	$sql.="	  GROUP BY usrlog_usr ) AS b  ";
	$sql.="	 ON a.usrlog_usr = b.usrlog_usr AND a.usrlog_dt_atualizacao = b.ult_atualizacao  ";
	$sql.="	 LEFT JOIN nucleos ON usrlog_nuc = nuc_id ";
	if($nuc_id!=-1) $sql.= " WHERE usrlog_nuc =  " . prep_para_bd($nuc_id) . "  ";
	$sql.="	UNION ";
	$sql.="	SELECT usrlog_usr usr_id, usrlog_archive usr_archive ";
	$sql.="	FROM usuarios_changelog  ";
	$sql.="	LEFT JOIN nucleos ON usrlog_nuc = nuc_id  ";
	$sql.="	WHERE ";
	if($nuc_id!=-1) $sql.=" usrlog_nuc =  " . prep_para_bd($nuc_id) . " AND ";
	$sql.=" usrlog_dt_atualizacao >  " . prep_para_bd(formata_data_para_mysql($dt_ini)) . " AND usrlog_dt_atualizacao <=  " . prep_para_bd(formata_data_para_mysql($dt_fim)) . "  ";
	$sql.="	) uniao_final  ";
	$sql.="	GROUP BY usr_id  ";
	$sql.="	HAVING MIN(usr_archive)=0  ";
	
	$res = executa_sql($sql);	
	$cestantes_ativos = array();	
	if($res)
	{
		while($row = mysqli_fetch_array($res,MYSQLI_ASSOC))
		{
			$cestantes_ativos[] = $row["usr_id"];
		}
	}
	


	
	if(count($cestantes_ativos)==0)
	{
		echo("Nenhum cestante para o período/núcleo informado.");
	}
	else
	{
		$sql = "";
		$sql .= "SELECT usr_id, ";
		$sql .= "       usr_nome_curto, ";
		$sql .= "       usr_nome_completo, ";
		$sql .= "       usr_archive, ";
		$sql .= "       usr_associado, ";
		$sql .= "       usr_asso, ";		
		$sql .= "       usr_nuc, ";
		$sql .= "       dados.nuc_nome_curto, ";
		$sql .= "       dados.asso_nome ";		
		$sql .= "FROM   (SELECT usr_id, ";
		$sql .= "               usr_nome_curto, ";
		$sql .= "               usr_nome_completo, ";
		$sql .= "               usr_archive, ";
		$sql .= "               usr_associado, ";
		$sql .= "               usr_asso, ";		
		$sql .= "               usr_nuc, ";
		$sql .= "               nuc_nome_curto, ";
		$sql .= "               asso_nome, ";	
		$sql .= "               usr_dt_atualizacao ";
		$sql .= "        FROM   usuarios u ";
		$sql .= "               LEFT JOIN nucleos ";
		$sql .= "                      ON usr_nuc = nuc_id ";
		$sql .= "               LEFT JOIN associacaotipos ";
		$sql .= "                      ON usr_asso = asso_id ";		
		$sql .= "        WHERE  usr_dt_atualizacao <= " . prep_para_bd(formata_data_para_mysql($dt_fim)) . "  ";
		$sql .= "        UNION ";
		$sql .= "        SELECT a.usrlog_usr          usr_id, ";
		$sql .= "               usrlog_nome_curto     usr_nome_curto, ";
		$sql .= "               usrlog_nome_completo  usr_nome_completo, ";
		$sql .= "               usrlog_archive        usr_archive, ";
		$sql .= "               usrlog_associado      usr_associado, ";
		$sql .= "               usrlog_asso           usr_asso, ";		
		$sql .= "               usrlog_nuc            usr_nuc, ";
		$sql .= "               nuc_nome_curto, ";
		$sql .= "               asso_nome, ";		
		$sql .= "               usrlog_dt_atualizacao usr_dt_atualizacao ";
		$sql .= "        FROM   usuarios_changelog a ";
		$sql .= "               INNER JOIN (SELECT usrlog_usr, ";
		$sql .= "                                  Max(usrlog_dt_atualizacao) AS ult_atualizacao ";
		$sql .= "                           FROM   usuarios_changelog ";
		$sql .= "                           WHERE  usrlog_dt_atualizacao <=  " . prep_para_bd(formata_data_para_mysql($dt_fim)) . "  ";
		$sql .= "                           GROUP  BY usrlog_usr) AS b ";
		$sql .= "                       ON a.usrlog_usr = b.usrlog_usr ";
		$sql .= "                          AND a.usrlog_dt_atualizacao = b.ult_atualizacao ";
		$sql .= "               LEFT JOIN associacaotipos ";
		$sql .= "                      ON usrlog_asso = asso_id ";			
		$sql .= "               LEFT JOIN nucleos ";
		$sql .= "                      ON usrlog_nuc = nuc_id) AS dados ";
		$sql .= "       INNER JOIN (SELECT usrlog_usr, ";
		$sql .= "                          Max(usrlog_dt_atualizacao) AS ult_atualizacao ";
		$sql .= "                   FROM   usuarios_changelog ";
		$sql .= "                   WHERE  usrlog_dt_atualizacao <=  " . prep_para_bd(formata_data_para_mysql($dt_fim)) . "  ";
		$sql .= "                   GROUP  BY usrlog_usr) AS c ";
		$sql .= "               ON dados.usr_id = c.usrlog_usr ";
		$sql .= "                  AND dados.usr_dt_atualizacao = c.ult_atualizacao ";
		$sql .= "       LEFT JOIN nucleos ";
		$sql .= "              ON usr_nuc = nuc_id ";
		$sql .= "       LEFT JOIN associacaotipos ";
		$sql .= "              ON usr_asso = asso_id ";			
		$sql .= "WHERE  usr_id IN  ( " . implode(",",$cestantes_ativos) . ") ";
		$sql .= "GROUP  BY usr_id, ";
		$sql .= "          usr_nome_curto, ";	
		$sql .= "          usr_nome_completo, ";
		$sql .= "          usr_archive, ";
		$sql .= "          usr_associado, ";
		$sql .= "          usr_asso, ";		
		$sql .= "          usr_nuc, ";
		$sql .= "          nuc_nome_curto, ";
		$sql .= "          asso_nome ";		
		$sql .= "ORDER  BY usr_nome_curto " ;

		$res_cestantes = executa_sql($sql);
					
		
		
		$sql = "SELECT usr_id, ";
		$sql .= "       usr_nome_curto, ";
		$sql .= "       usr_nome_completo, ";
		$sql .= "       usr_archive, ";
		$sql .= "       usr_associado, ";
		$sql .= "       usr_asso, ";		
		$sql .= "       usr_nuc, ";
		$sql .= "       nuc_nome_curto, ";
		$sql .= "       asso_nome, ";		
		$sql .= "       usr_dt_atualizacao, ";		
		$sql .= "       DATE_FORMAT(usr_dt_atualizacao,'%d/%m/%Y %H:%i:%s') usr_dt_atualizacao_formatada ";
		$sql .= "FROM   (SELECT usr_id, ";
		$sql .= "               usr_nome_curto, ";
		$sql .= "               usr_nome_completo, ";
		$sql .= "               usr_archive, ";
		$sql .= "               usr_associado, ";
		$sql .= "               usr_asso, ";		
		$sql .= "               usr_nuc, ";
		$sql .= "               nuc_nome_curto, ";
		$sql .= "               asso_nome, ";		
		$sql .= "               usr_dt_atualizacao, ";		
		$sql .= "               DATE_FORMAT(usr_dt_atualizacao,'%d/%m/%Y %H:%i:%s') usr_dt_atualizacao_formatada ";
		$sql .= "        FROM   usuarios u ";
		$sql .= "               LEFT JOIN nucleos ";
		$sql .= "                      ON usr_nuc = nuc_id ";
		$sql .= "               LEFT JOIN associacaotipos ";
		$sql .= "                      ON usr_asso = asso_id ";			
		$sql .= "        WHERE  usr_dt_atualizacao <= " . prep_para_bd(formata_data_para_mysql($dt_fim)) . "  ";
		$sql .= "        UNION ";
		$sql .= "        SELECT a.usrlog_usr          usr_id, ";
		$sql .= "               usrlog_nome_curto     usr_nome_curto, ";
		$sql .= "               usrlog_nome_completo  usr_nome_completo, ";
		$sql .= "               usrlog_archive        usr_archive, ";
		$sql .= "               usrlog_associado      usr_associado, ";
		$sql .= "               usrlog_asso           usr_asso, ";		
		$sql .= "               usrlog_nuc            usr_nuc, ";
		$sql .= "               nuc_nome_curto, ";
		$sql .= "               asso_nome, ";		
		$sql .= "               usrlog_dt_atualizacao usr_dt_atualizacao, ";		
		$sql .= "               DATE_FORMAT(usrlog_dt_atualizacao,'%d/%m/%Y %H:%i:%s') usr_dt_atualizacao_formatada ";
		$sql .= "        FROM   usuarios_changelog a ";
		$sql .= "               INNER JOIN (SELECT usrlog_usr, ";
		$sql .= "                                  Max(usrlog_dt_atualizacao) AS ult_atualizacao ";
		$sql .= "                           FROM   usuarios_changelog ";
		$sql .= "                           WHERE  usrlog_dt_atualizacao <=  " . prep_para_bd(formata_data_para_mysql($dt_ini)) . "  ";
		$sql .= "                           GROUP  BY usrlog_usr) AS b ";
		$sql .= "                       ON a.usrlog_usr = b.usrlog_usr ";
		$sql .= "                          AND a.usrlog_dt_atualizacao = b.ult_atualizacao ";
		$sql .= "               LEFT JOIN nucleos ";
		$sql .= "                      ON usrlog_nuc = nuc_id ";
		$sql .= "               LEFT JOIN associacaotipos ";
		$sql .= "                      ON usrlog_asso = asso_id ";			
		$sql .= "        UNION ";
		$sql .= "        SELECT usrlog_usr            usr_id, ";
		$sql .= "               usrlog_nome_curto     usr_nome_curto, ";
		$sql .= "               usrlog_nome_completo  usr_nome_completo, ";
		$sql .= "               usrlog_archive        usr_archive, ";
		$sql .= "               usrlog_associado      usr_associado, ";
		$sql .= "               usrlog_asso           usr_asso, ";		
		$sql .= "               usrlog_nuc            usr_nuc, ";
		$sql .= "               nuc_nome_curto, ";
		$sql .= "               asso_nome, ";		
		$sql .= "               usrlog_dt_atualizacao usr_dt_atualizacao, ";		
		$sql .= "               DATE_FORMAT(usrlog_dt_atualizacao,'%d/%m/%Y %H:%i:%s') usr_dt_atualizacao_formatada ";
		$sql .= "        FROM   usuarios_changelog ";
		$sql .= "               LEFT JOIN nucleos ";
		$sql .= "                      ON usrlog_nuc = nuc_id ";
		$sql .= "               LEFT JOIN associacaotipos ";
		$sql .= "                      ON usrlog_asso = asso_id ";			
		$sql .= "        WHERE  usrlog_dt_atualizacao > " . prep_para_bd(formata_data_para_mysql($dt_ini));
		$sql .= "               AND usrlog_dt_atualizacao <=  " . prep_para_bd(formata_data_para_mysql($dt_fim)) . " ) dados ";
		$sql .= "WHERE  usr_id IN  ( " . implode(",",$cestantes_ativos) . ") ";
		$sql .= "GROUP  BY usr_id, ";
		$sql .= "          usr_nome_curto, ";
		$sql .= "          usr_nome_completo, ";
		$sql .= "          usr_archive, ";
		$sql .= "          usr_associado, ";
		$sql .= "          usr_asso, ";		
		$sql .= "          usr_nuc, ";
		$sql .= "          nuc_nome_curto, ";
		$sql .= "          asso_nome ";		
		$sql .= "ORDER  BY usr_id, ";
		$sql .= "          usr_dt_atualizacao DESC, usr_nome_curto DESC " ;		
		
		$res_cestantes_versoes = executa_sql($sql); 			
		$cestantes_versoes = array();	
		if($res_cestantes_versoes)
		{
			$ult_usr=-1;
			while($row = mysqli_fetch_array($res_cestantes_versoes,MYSQLI_ASSOC))
			{				
				if($row["usr_id"]!=$ult_usr)
				{
					$cestantes_versoes[$row["usr_id"]]=array();
				}
				$cestantes_versoes[$row["usr_id"]][] = $row;
				$ult_usr = $row["usr_id"];
			}
		}





		$sql="SELECT cha_id, cha_dt_entrega cha_dt_entrega_original, DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, prodt_nome ";
		$sql.="FROM chamadanucleos ";
		$sql.="LEFT JOIN chamadas ON cha_id = chanuc_cha ";
		$sql.="LEFT JOIN produtotipos ON prodt_id = cha_prodt ";		
		$sql.="WHERE ";
		if($nuc_id!=-1) $sql.=" chanuc_nuc = " . prep_para_bd($nuc_id) . " AND ";
		$sql.=" cha_dt_entrega>= " . prep_para_bd(formata_data_para_mysql($dt_ini)  . " 00:00:00");
		$sql.=" AND cha_dt_entrega<= " . prep_para_bd(formata_data_para_mysql($dt_fim) . " 23:59:59");			
		$sql.=" GROUP BY cha_id ";
		$sql.=" ORDER BY cha_dt_entrega_original, prodt_nome ";

		$res_chamadas_validas = executa_sql($sql); 
		$chamadas_validas = array();	
		$chamadas_validas_id = array();
		if($res_chamadas_validas)
		{
			while($row = mysqli_fetch_array($res_chamadas_validas,MYSQLI_ASSOC))
			{	
				$chamadas_validas[] = $row;
				$chamadas_validas_id[] = $row["cha_id"];
			}
		}
			

		


		/*primeiro: pegar lista de chamadas pertinentes*/
		$sql="SELECT usr_nome_curto, usr_nome_completo, ped_usr, ped_usr_associado, ped_id, cha_id, cha_taxa_percentual, ";
		$sql.="FORMAT(IF(ped_usr_associado='0', SUM(prod_valor_venda_margem * pedprod_quantidade),SUM(prod_valor_venda * pedprod_quantidade)),2) AS valor_pedido, ";
		$sql.="FORMAT(IF(ped_usr_associado='0', SUM(prod_valor_venda_margem * pedprod_entregue),SUM(prod_valor_venda * pedprod_entregue)),2) AS valor_entregue, ";
		$sql.="FORMAT(IF(ped_usr_associado='0', SUM(prod_valor_venda_margem * (pedprod_entregue - pedprod_quantidade)),SUM(prod_valor_venda * (pedprod_entregue - pedprod_quantidade)) ),2) AS valor_extra ";
		$sql.="FROM chamadaprodutos ";
		$sql.="LEFT JOIN chamadas on cha_id = chaprod_cha LEFT JOIN produtos on prod_id = chaprod_prod ";
		$sql.="LEFT JOIN pedidos ON ped_cha = cha_id LEFT JOIN usuarios on ped_usr = usr_id ";
		$sql.="LEFT JOIN pedidoprodutos ON pedprod_ped = ped_id AND pedprod_prod=chaprod_prod ";
		
		$sql.="WHERE ped_usr IN ("  . implode(",",$cestantes_ativos) . ") ";
		$sql.=" AND ped_cha IN ("  . implode(",",$chamadas_validas_id) . ") ";		
		if($nuc_id!=-1) $sql.=" AND ped_nuc = " . prep_para_bd($nuc_id) . " ";
		$sql.=" AND ped_fechado = '1' AND chaprod_disponibilidade <> '0' ";
		$sql.=" AND prod_ini_validade<=cha_dt_entrega AND prod_fim_validade>=cha_dt_entrega ";
		$sql.="GROUP BY usr_id, ped_id ";
		$sql.="ORDER BY usr_id";
		
		$res_chamadas_pedidos = executa_sql($sql); 
		$chamadas_pedidos = array();	
		if($res_chamadas_pedidos)
		{
			while($row = mysqli_fetch_array($res_chamadas_pedidos,MYSQLI_ASSOC))
			{	
				$chamadas_pedidos[$row["ped_usr"]][$row["cha_id"]] = $row;
			}
		}
			

		
		if($res_cestantes) 
		{	
		
			$total_chamada=array();
			?>		
			
				 <input class="btn btn-success" type="button" value="selecionar tabela para copiar"  onclick="selectElementContents( document.getElementById('selectable') );"> 
                 &nbsp;&nbsp;&nbsp;
		        <button type="button" class="btn btn-info" name="btnAlternarDetalheCestante" id="btnAlternarDetalheCestante"><i class="glyphicon glyphicon-pushpin"></i> mostrar/ocultar atualizações cestantes</button> 
                 &nbsp;&nbsp;  
	            <button type="button" class="btn btn-info" name="btnAlternarDetalheChamada" id="btnAlternarDetalheChamada"><i class="glyphicon glyphicon-pushpin"></i> mostrar/ocultar detalhamento taxa</button>                   
                <!--
                 <input class="btn btn-info" type="button"  id="btnAlternarDetalheCestante" value="mostrar / ocultar atualizações cestantes"> 
                 &nbsp;&nbsp;&nbsp;
                 <input class="btn btn-info" type="button"  id="btnAlternarDetalheChamada" value="mostrar / ocultar detalhamento taxa">                  
                 -->
				 <p />
	
			
					<table id="selectable" class="table table-striped table-bordered table-condensed table-hover">
					<thead>
                    <!--
						<tr>
							<th colspan="6">Quadro Cestantes - Núcleo</th>
						</tr>
                    -->    
                        
						<tr>
							<th rowspan="2">#</th>
							<th rowspan="2">Cód.</th>
							<th rowspan="2">Núcleo</th>
							<th rowspan="2">Nome</th>
							<th rowspan="2">Nome Completo</th>                                          
							<th rowspan="2">Ativo</th>
							<th rowspan="2">Associado</th>
							<th rowspan="2">Tipo Associação</th>                            
							<th rowspan="2" class="alterna_visivel_cestante">Data Atualização</th>

							 <?php
							 foreach($chamadas_validas as $chamada)
							 {
								$total_chamada[$chamada["cha_id"]]["entregue"]=0;
								$total_chamada[$chamada["cha_id"]]["taxa"]=0;
								echo("<th colspan='3' class='colspan_alterna_visivel_chamada'>" . $chamada["prodt_nome"] . "<br>" . $chamada["cha_dt_entrega"] . "</th>");
							 }							 
							 ?>
                             <th colspan="3" class='colspan_alterna_visivel_chamada'>TOTAL</th>
						</tr>
                         <tr>                         	
                        	 <?php
							 
							 for ($i=0;$i<=count($chamadas_validas);$i++)
							 {
		                        echo("<th class='alterna_visivel_chamada'>entregue</th><th class='alterna_visivel_chamada'>taxa</th><th>total c/ taxa</th>");
							 }							 
							 ?>
                             
                         </tr>   
                         
                     

					</thead>
					
					<tbody>
					<?php
	
					$cont=0;	   			 
				   while ($row = mysqli_fetch_array($res_cestantes,MYSQLI_ASSOC)) 
				   {
					   $cont++;
					   $primeiro=true;					   
					   foreach ($cestantes_versoes[$row["usr_id"]] as $cestante) 
					   {
						   if($primeiro)
						   {
							   echo("<tr>");					
							   echo("<td>" .  $cont . "</td>");
							   echo("<td>" .  $cestante["usr_id"] . "</td>");
							   
							   $total_cestante_entregue=0;
							   $total_cestante_taxa=0;
						   }
						   else
						   {
							   echo("<tr class='alterna_visivel_cestante'>");
							   echo("<td>&nbsp;</td>");
							   echo("<td>&nbsp;</td>");							   
						   }
						   ?>							                                                          
							<td><?php echo($cestante["nuc_nome_curto"]);?></td>
							<td><?php echo($cestante["usr_nome_curto"]);?></td>
							<td><?php echo($cestante["usr_nome_completo"]);?></td>
							<td class="<?php echo($cestante["usr_archive"]=='1'?"label-warning":""); ?>">
								<?php echo($cestante["usr_archive"]=='1'?"Não":"Sim"); ?>
							</td> 
							<td class="<?php echo($cestante["usr_associado"]=='0'?"label-warning":""); ?>">
								<?php echo($cestante["usr_associado"]=='0'?"Não":"Sim"); ?>
							 </td> 
                             <td><?php echo($cestante["asso_nome"]);?></td>
							<td class="alterna_visivel_cestante">
								<?php echo(count($cestantes_versoes[$row["usr_id"]])>1 ? $cestante["usr_dt_atualizacao_formatada"] : "&nbsp;");?>                      
                            </td>
                             
 							 <?php
							 if($primeiro)
							 {
								 foreach($chamadas_validas as $chamada)
								 {	
							 									
									if(isset($chamadas_pedidos[$row["usr_id"]][$chamada["cha_id"]]["valor_entregue"]))
									{
										$valor_taxa = $chamadas_pedidos[$row["usr_id"]][$chamada["cha_id"]]["ped_usr_associado"] =='0'? '0.0' : $chamadas_pedidos[$row["usr_id"]][$chamada["cha_id"]]["valor_entregue"] * $chamadas_pedidos[$row["usr_id"]][$chamada["cha_id"]]["cha_taxa_percentual"];
										$valor_taxa = round($valor_taxa,2);

										 echo("<td class='alterna_visivel_chamada'>");
										 echo(formata_moeda($chamadas_pedidos[$row["usr_id"]][$chamada["cha_id"]]["valor_entregue"])) ;
										 echo("</td>");
										 echo("<td class='alterna_visivel_chamada'>");
										 echo($valor_taxa > 0 ? formata_moeda($valor_taxa) : "&nbsp;") ;									
										 echo("</td>");
										 echo("<td>");
										 echo(formata_moeda($chamadas_pedidos[$row["usr_id"]][$chamada["cha_id"]]["valor_entregue"] + $valor_taxa));
										 echo("</td>");						
										 				 
									     $total_cestante_entregue+=$chamadas_pedidos[$row["usr_id"]][$chamada["cha_id"]]["valor_entregue"];
									     $total_cestante_taxa+=$valor_taxa;										 
										 
										 $total_chamada[$chamada["cha_id"]]["entregue"]+=$chamadas_pedidos[$row["usr_id"]][$chamada["cha_id"]]["valor_entregue"];
									     $total_chamada[$chamada["cha_id"]]["taxa"]+=$valor_taxa;										 
										 								 

									}
									else
									{
										echo("<td class='alterna_visivel_chamada'>&nbsp;</td><td class='alterna_visivel_chamada'>&nbsp;</td><td>&nbsp;</td>");
									}
								 }
								 ?>
								  
								  <th class="alterna_visivel_chamada"><?php echo(formata_moeda($total_cestante_entregue));?></th>
								  <th class="alterna_visivel_chamada"><?php echo(formata_moeda($total_cestante_taxa));?></th>
								  <th><?php echo(formata_moeda($total_cestante_entregue + $total_cestante_taxa));?></th>
								
								</tr>				                
							   <?php
							 }
						  $primeiro=false;
					   }	
				   }
			  ?>   
              
              
              <tr>
              <th colspan="8" class="col_alterna_visivel_cestante">TOTAL</th>
			 <?php		 
                 $total_geral_entregue=0;
				 $total_geral_taxa=0;
				 for($i = 0; $i < count($chamadas_validas); $i++)
				 {
					$total_geral_entregue+=$total_chamada[$chamadas_validas[$i]["cha_id"]]["entregue"];
					$total_geral_taxa+=$total_chamada[$chamadas_validas[$i]["cha_id"]]["taxa"];
					echo("<th class='alterna_visivel_chamada'>" . formata_moeda($total_chamada[$chamadas_validas[$i]["cha_id"]]["entregue"]) . "</th>");
					echo("<th class='alterna_visivel_chamada'>" . formata_moeda($total_chamada[$chamadas_validas[$i]["cha_id"]]["taxa"]) . "</th>");
					echo("<th>" . formata_moeda($total_chamada[$chamadas_validas[$i]["cha_id"]]["entregue"] + $total_chamada[$chamadas_validas[$i]["cha_id"]]["taxa"]) . "</th>");
				 }	                   
				 				 
					echo("<th class='alterna_visivel_chamada'>" . formata_moeda($total_geral_entregue) . "</th>");
					echo("<th class='alterna_visivel_chamada'>" . formata_moeda($total_geral_taxa) . "</th>");
					echo("<th>" . formata_moeda($total_geral_entregue + $total_geral_taxa) . "</th>");
				 

                 ?>

                
    							
			  </tr>

							 
			  </tbody></table>
		   
	
			<?php 
		}
	} // if count(cestantes)> 0 

}
	
	

 ?>             
                         
          </div>
       

<script type="text/javascript">
	$(function() {
		$(".data").datepicker({
			format: 'dd/mm/yyyy',
			language: 'pt-BR',
			autoclose: true
		})
	}); 
	
	$('#btnAlternarDetalheCestante').on('click', function (e) {
        e.preventDefault();
        $('.alterna_visivel_cestante').toggle();

		if($('.col_alterna_visivel_cestante').attr('colspan')==8)
		{
			$('.col_alterna_visivel_cestante').attr('colspan','9');
		}
		else
		{
			$('.col_alterna_visivel_cestante').attr('colspan','8');
		}
		
    });
	
	$('#btnAlternarDetalheChamada').on('click', function (e) {
        e.preventDefault();
        $('.alterna_visivel_chamada').toggle();
		if($('.colspan_alterna_visivel_chamada').attr('colspan')==1)
		{
			$('.colspan_alterna_visivel_chamada').attr('colspan','3');
		}
		else
		{
			$('.colspan_alterna_visivel_chamada').attr('colspan','1');
		}

    });
	
		
	$('.alterna_visivel_cestante').toggle();
		
    $('.alterna_visivel_chamada').toggle();
	$('.colspan_alterna_visivel_chamada').attr('colspan','1');
	
</script>    
    

 <?php 


footer();

?>