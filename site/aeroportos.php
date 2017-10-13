<?php

$xmlstr = file_get_contents("aeroportos.xml");
$xml = new SimpleXMLElement($xmlstr);

//print_r($xml);
$sql = "";
/* For each <movie> node, we echo a separate <plot>. */
foreach ($xml->array->dict as $aeroporto) {
	//echo $aeroporto->string[0], '<br />';
	
	$aeroporto->string[4] = str_replace("-", " - ", $aeroporto->string[4]);
	$aeroporto->string[7] = str_replace("-", " - ", $aeroporto->string[7]);
	$aeroporto->string[8] = str_replace(",", ", ", $aeroporto->string[8]);
	
	echo "<option value='".$aeroporto->string[1]."'>".$aeroporto->string[4]."</option>\n";
	
	/*
	$sql .= "INSERT INTO `infraero`.`aeroportos` (`cod_aeroporto`, `cod_iata`, `cod_icao`, `loc`, `nom_aeroporto`, `nom_cidade`, `possui_siv`, `sig_uf`, `vnom_curto`, `weather`) VALUES (NULL,
			'".$aeroporto->string[0]."',
			'".$aeroporto->string[1]."',
			'".$aeroporto->string[2]."',
			'".$aeroporto->string[3]."',
			'".$aeroporto->string[4]."',
			'".$aeroporto->string[5]."',
			'".$aeroporto->string[6]."',
			'".$aeroporto->string[7]."',
			'".$aeroporto->string[8]."'); <br>";
	*/
}

echo $sql;

?>