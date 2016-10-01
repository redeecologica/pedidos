Sistema de Pedidos
==================

O sistema atende às necessidades de consolidação de pedidos de GCRs (grupos de consumo responsável). Este sistema foi desenvolvido inicialmente para atender às especificidades da [Rede Ecológica] (http://www.redeecologicario.org/). O código fonte foi disponibilizado neste repositório para que outros GCRs possam reutilizá-lo.


Instalação
----------
Pré-requisito: ambiente com PHP e banco de dados MySQL, com as seguintes configurações no arquivo php.ini:
 register_globals = Off
 session.auto_start = 1

A instalação consiste em:
1) disponibilizar os arquivos desta distribuição no diretório destinado à aplicação (não copiar o diretório "bd").
2) renomear o arquivo settings.php.sample para settings.php e editá-lo de acordo com as configurações do GCR associado.
3) criar o banco de dados inicial conforme descrito em (bd/leiame.txt).
4) para alterar a imagem que aparece no cabeçalho, para uma representativa do GCR associado, alterar o arquivo (img/logo_sistema.png).


Licença
----------

	Esta declaração é pertinente a todos os arquivos fontes do projeto, 
	exceto os que possuem cabeçalho de copyright específico. 
	
    Copyright 2013- Rede Ecológica (redeecologicario.org)
	
	This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details:
    http://www.gnu.org/licenses/


    Tradução não-oficial:
    Este programa é um software livre; você pode redistribuí-lo e/ou 
    modificá-lo dentro dos termos da Licença Pública Geral GNU como 
    publicada pela Fundação do Software Livre (FSF); na versão 3 da 
    Licença ou qualquer versão futura.

    Este programa é distribuído na esperança de que possa ser útil, 
    mas SEM NENHUMA GARANTIA; sem uma garantia implícita de ADEQUAÇÃO
    a qualquer MERCADO ou APLICAÇÃO EM PARTICULAR. Veja a
    Licença Pública Geral GNU para mais detalhes:
    http://www.gnu.org/licenses/
