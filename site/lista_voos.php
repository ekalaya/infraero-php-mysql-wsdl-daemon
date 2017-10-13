<?php

require_once('lib/nusoap.php');
$client = new nusoap_client("http://voos.infraero.gov.br/wsvoosmobile/ConsultaVoos.svc?wsdl", false);
$err = $client->getError();

if ($err) {
	echo '<h2>Constructor error</h2><pre>' . $err . '</pre>';
	echo '<h2>Debug</h2><pre>' . htmlspecialchars($client->getDebug(), ENT_QUOTES) . '</pre>';
	exit();
}

/*
 * 
 * <tem:ConsultarVoosSentido>
         <!--Optional:-->
         <tem:icao>SBKP</tem:icao>
         <!--Optional:-->
         <tem:idioma>bra</tem:idioma>
         <!--Optional:-->
         <tem:partida>true</tem:partida>
         <!--Optional:-->
         <tem:exibirFinalizados>false</tem:exibirFinalizados>
         <!--Optional:-->
         <tem:registrosPagina>20</tem:registrosPagina>
         <!--Optional:-->
         <tem:pagina>1</tem:pagina>
      </tem:ConsultarVoosSentido>
 */

$client->soap_defencoding = 'UTF-8';

//echo 'You must set your own Google key in the source code to run this client!'; exit();
$params = array(
	'icao'=>'SBKP',
	'idioma'=>'bra',
	'partida'=>true,
	'exibirFinalizados'=>false,
	'registrosPagina'=>20,
	'pagina'=>1
);
$result = $client->call("ConsultarVoosSentido", $params, "ConsultarVoosSentido", "ConsultarVoosSentido");

if ($client->fault) {
	echo '<h2>Fault</h2><pre>'; print_r($result); echo '</pre>';
} else {
	$err = $client->getError();
	if ($err) {
		echo '<h2>Error</h2><pre>' . $err . '</pre>';
	} else {
		echo '<h2>Result</h2><pre>'; print_r($result); echo '</pre>';
	}
}
echo '<h2>Request</h2><pre>' . htmlspecialchars($client->request, ENT_QUOTES) . '</pre>';
echo '<h2>Response</h2><pre>' . htmlspecialchars($client->response, ENT_QUOTES) . '</pre>';
echo '<h2>Debug</h2><pre>' . htmlspecialchars($client->getDebug(), ENT_QUOTES) . '</pre>';

?>
