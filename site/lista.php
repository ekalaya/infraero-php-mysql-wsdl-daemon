<?php

error_reporting(E_ERROR | E_PARSE);

require_once('lib/nusoap.php');
$wsdl = 'http://voos.infraero.gov.br/wsvoosmobile/ConsultaVoos.svc?wsdl'; 

$client = new nusoap_client($wsdl, true);
$client->soap_defencoding = 'UTF-8';

$err = $client->getError();

if ($err){
	echo "Erro no construtor<pre>".$err."</pre>";
}


$params = array(
	'icao'=> $_REQUEST["icao"],
	'idioma'=> $_REQUEST["idioma"],
	'partida'=> $_REQUEST["partida"],
	'exibirFinalizados'=> $_REQUEST["exibirFinalizados"],
	'registrosPagina'=> $_REQUEST["registrosPagina"],
	'pagina'=> $_REQUEST["pagina"]
);


$result = $client->call('ConsultarVoosSentido', $params);



if ($client->fault){
	echo "Falha<pre>".print_r($result)."</pre>";
}else{
	$err = $client->getError();
	if ($err){
		echo "Erro: <pre>".$err."</pre>";
	} else{
		
		$xml = $result["ConsultarVoosSentidoResult"];
		$json = json_encode(simplexml_load_string($xml));
		echo $json;
		
	}
}

?>

