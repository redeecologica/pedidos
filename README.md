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
4) para alterar a imagem que aparece no cabeçalho, para uma representativa do GCR associado, alterar o arquivo (img/logo_sistema.gif).

