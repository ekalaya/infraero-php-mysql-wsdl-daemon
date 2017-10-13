<?php
require_once('lib/nusoap.php');
error_reporting(E_ALL ^E_NOTICE);
ini_set('display_errors', 1);
date_default_timezone_set("America/Sao_Paulo");
set_time_limit(0);



// dados conexao bd
$host	  = "localhost";
$username = "root";
$password = "vertrigo";
$database = "infraero";


$mtime = microtime();
$mtime = explode(" ",$mtime);
$mtime = $mtime[1] + $mtime[0];
$starttime = $mtime;

// execucao
$daemon = new infraero_daemon($host, $database, $username, $password);
$daemon->init();
$daemon->process_all_airports();


/*
// Criar um vetor com 10 threads do mesmo tipo
$vetor = array();
for ($id = 0; $id < 10; $id++) {
	$vetor[] = new InfraeroThread($id);
}

// Iniciar a execucao das threads
foreach ($vetor as $thread) {
	$thread->start();
}
*/

// processar 1 aeroporto em 1 sentido em 1 pagina
//$daemon->fetch_data("SBGR", "bra", false, true, 50, 1);
//$daemon->process();
//$daemon->save2db();




$mtime = microtime();
$mtime = explode(" ",$mtime);
$mtime = $mtime[1] + $mtime[0];
$endtime = $mtime;
$totaltime = ($endtime - $starttime);
echo "<br>Tempo de processamento: ".round($totaltime, 4)." segundos";





class infraero_daemon {
	
	private $client;
	private $result;
	public  $flights;
	
	private $db_conn;
	private $host;
	private $database;
	private $username;
	private $password;
	
	public function __construct($host, $database, $username, $password) {
		$this->host = $host;
		$this->database = $database;
		$this->username = $username;
		$this->password = $password;
	}
	
	public function init() {
	
		$wsdl = 'http://voos.infraero.gov.br/wsvoosmobile/ConsultaVoos.svc?wsdl'; 
		
		
		$this->client = new nusoap_client($wsdl, true);
		$this->client->soap_defencoding = 'UTF-8';
		
		$err = $this->client->getError();
		
		if ($err){
			die("Erro no construtor: ".$err);
		}
		
		$this->db_connect();
	
	}
	
	public function report_offline_airport($cod_icao, $offline) {
		
		if ($offline)
			$result = mysqli_query($this->db_conn, "UPDATE aeroportos SET datahora_offline = NOW() WHERE cod_icao = '".$cod_icao."';");
		else
			$result = mysqli_query($this->db_conn, "UPDATE aeroportos SET datahora_offline = NULL WHERE cod_icao = '".$cod_icao."';");

	}
	
	public function process_all_airports() {
		
		$result = mysqli_query($this->db_conn, "SELECT * FROM aeroportos WHERE 1 ORDER BY cod_icao;");
		$itens = 50;
		
		while ($airport = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
			
			// roda 8 paginas de 50 voos
			for ($pag = 1; $pag <= 8; $pag++) {
						
						echo "Pagina: ".$pag." - ".$itens." por Pagina<br>";
						
						// 50 primeiros voos - ida - pagina 1
						$this->fetch_data($airport["cod_icao"], "bra", true, true, $itens, $pag);
						$this->process();
						if (!is_null($this->flights)) {
							$this->save2db();
							echo "[PARTIDAS $pag] Aeroporto de ".$airport['vnom_curto']." (".$airport['cod_icao'].") processado!<br>";
						} else {
							echo "[PARTIDAS $pag] ERRO ao processar aeroporto de ".$airport['vnom_curto']." (".$airport['cod_icao'].")!<br>";
						}
						
						// 50 primeiros voos - volta - pagina 1
						$this->fetch_data($airport["cod_icao"], "bra", false, true, $itens, $pag);
						$this->process();
						if (!is_null($this->flights)) {
							$this->save2db();
							echo "[CHEGADAS $pag] Aeroporto de ".$airport['vnom_curto']." (".$airport['cod_icao'].") processado!<br>";
							$this->report_offline_airport($airport['cod_icao'], false);
						} else {
							echo "[CHEGADAS $pag] ERRO ao processar aeroporto de ".$airport['vnom_curto']." (".$airport['cod_icao'].")!<br>";
							$this->report_offline_airport($airport['cod_icao'], true);
						}
						
						// verifica se a 1a pagina ta offline, se tiver é o aeroporto todo que esta off
						if ($pag==1) {
							if (is_null($this->flights))
								$this->report_offline_airport($airport['cod_icao'], true);
							else
								$this->report_offline_airport($airport['cod_icao'], false);
						}
			
			}
			
			flush();
		}
		
	}
	
	public function fetch_data($icao, $idioma, $partida, $exibirFinalizados, $registrosPagina, $pagina) {
		$params = array(
			'icao'=> $icao,
			'idioma'=> $idioma,
			'partida'=> $partida,
			'exibirFinalizados'=> $exibirFinalizados,
			'registrosPagina'=> $registrosPagina,
			'pagina'=> $pagina
		);
		$this->result = $this->client->call('ConsultarVoosSentido', $params);
	}
	
	public function process() {

		if ($this->client->fault){
			echo "[FALHA]";
		}else{
			$err = $this->client->getError();
			if ($err){
				echo "Erro: ".$err."";
			} else{
				
				$xml = $this->result["ConsultarVoosSentidoResult"];
				$data = json_decode(json_encode(simplexml_load_string($xml)), true);
				if (isset($data["VOO"])) {
					$this->flights = $data["VOO"];
				} else {
					$this->flights = null;
					
					// salva data-hora atuais em que esse aeroporto ficou sem dados (offline)
					
				}
			}
		}
	
	}

	public function save2db() {
		
		// exclui todos voos que ja finalizaram 
		mysqli_query($this->db_conn, "DELETE FROM voos WHERE HOR_CONF_DT < now() - interval 48 hour;");
		
		if (!is_null($this->flights)) {
			
			foreach ($this->flights as $flight) {
			
			if (isset($flight["NUM_VOO"])) {
			
				$flight = $this->utf8_converter($flight);
				
				// arruma dados que talvez nao tenham chegado
				if (!isset($flight["TXT_OBS"])) $flight["TXT_OBS"] = "";
				if (!isset($flight["DAT_VOO"])) $flight["DAT_VOO"] = "";
				if (!isset($flight["HOR_CONF"])) $flight["HOR_CONF"] = "";
				if (!isset($flight["DSC_EQUIPAMENTO"])) $flight["DSC_EQUIPAMENTO"] = "";
				
	
				// verifica se o voo ja nao existe (num_voo e data)
					// se ja existe, atualiza
					// se nao existe
						// cria cia_aerea e aeronave (caso não existam ainda)
						// cria voo
						
				$result = mysqli_num_rows(mysqli_query($this->db_conn, "SELECT * FROM voos WHERE NUM_VOO = '".$flight["NUM_VOO"]."' AND DAT_VOO = '".$flight["DAT_VOO"]."';"));
				
				$data = explode("/", $flight["DAT_VOO"]); // i.e. 14/01
				
				$DAT_VOO_DT  = date('Y').'-'.$data[1].'-'.$data[0];
				$HOR_PREV_DT = date('Y').'-'.$data[1].'-'.$data[0].' '.$flight['HOR_PREV'].':00';
				$HOR_CONF_DT = date('Y').'-'.$data[1].'-'.$data[0].' '.$flight['HOR_CONF'].':00';
				if (is_array($flight["NUM_GATE"])) $flight["NUM_GATE"] = "";
				
				if ($result > 0) {
					
					// check for flight update
					if ($this->check_flight_changes($flight)) {
					
						// voo existe
						$sql = "UPDATE voos SET
																				DAT_VOO_DT 		= '".$DAT_VOO_DT."',	
																				HOR_PREV 		= '".$flight["HOR_PREV"]."',
																				HOR_PREV_DT 	= '".$HOR_PREV_DT."',
																				HOR_CONF 		= '".$flight["HOR_CONF"]."',
																				HOR_CONF_DT 	= '".$HOR_CONF_DT."',	
																				NUM_TPS 		= '".$flight["NUM_TPS"]."',
																				NUM_GATE 		= '".$flight["NUM_GATE"]."',
																				TXT_OBS 		= '".$flight["TXT_OBS"]."',
																				NOM_CIA 		= '".$flight["NOM_CIA"]."',
																				SIG_CIA_AEREA 	= '".$flight["SIG_CIA_AEREA"]."',
																				DSC_EQUIPAMENTO = '".$flight["DSC_EQUIPAMENTO"]."',
																				NOM_AEROPORTO 	= '".$flight["NOM_AEROPORTO"]."',
																				COD_IATA 		= '".$flight["COD_IATA"]."',
																				COD_ICAO 		= '".$flight["COD_ICAO"]."',
																				NOM_LOCALIDADE 	= '".$flight["NOM_LOCALIDADE"]."',
																				SIG_UF 			= '".$flight["SIG_UF"]."',
																				NOM_PAIS 		= '".$flight["NOM_PAIS"]."',
																				DSC_NATUREZA 	= '".$flight["DSC_NATUREZA"]."',
																				DSC_STATUS 		= '".$flight["DSC_STATUS"]."',
							
																				ULT_ATUALIZACAO = NOW()
																				WHERE NUM_VOO = '".$flight["NUM_VOO"]."' AND DAT_VOO = '".$flight["DAT_VOO"]."';";
						//echo $sql;
						$result = mysqli_query($this->db_conn, $sql);
					
					}
					
				} else {
					
					// verifica se existe aeronave
					$result = mysqli_num_rows(mysqli_query($this->db_conn, "SELECT * FROM aeronaves WHERE DSC_EQUIPAMENTO = '".$flight["DSC_EQUIPAMENTO"]."';"));
					if ($result == 0) {
						
						// insere a aeronave
						$sql = "INSERT INTO aeronaves(DSC_EQUIPAMENTO,
													  COD_FABRICANTE,
													  MODELO,
													  DESCRICAO)
													  VALUES(
													  '".$flight["DSC_EQUIPAMENTO"]."',
													  3,
													  '".$flight["DSC_EQUIPAMENTO"]."',
													  ''
													  )";
						$result = mysqli_query($this->db_conn, $sql);
						
					}
					
					// verifica se existe cia aerea
					$result = mysqli_num_rows(mysqli_query($this->db_conn, "SELECT * FROM cias_aereas WHERE SIG_CIA_AEREA = '".$flight["SIG_CIA_AEREA"]."';"));
					if ($result == 0) {
						
						// insere a cia aerea
						$sql = "INSERT INTO cias_aereas(NOM_CIA,
													  SIG_CIA_AEREA,
													  NOME,
													  DESCRICAO,
													  SITE)
													  VALUES(
													  '".$flight["NOM_CIA"]."',
													  '".$flight["SIG_CIA_AEREA"]."',
													  '".$flight["NOM_CIA"]."',
													  '',
													  ''
													  )";
						$result = mysqli_query($this->db_conn, $sql);
						
					}
					
					// voo nao existe
					$sql = "INSERT INTO voos(NUM_VOO,
											 DAT_VOO,
											 DAT_VOO_DT,
											 HOR_PREV,
											 HOR_PREV_DT,
											 HOR_CONF,
											 HOR_CONF_DT,
											 NUM_TPS,
											 NUM_GATE,
											 TXT_OBS,
											 NOM_CIA,
											 SIG_CIA_AEREA,
											 DSC_EQUIPAMENTO,
											 NOM_AEROPORTO,
											 COD_IATA,
											 COD_ICAO,
											 NOM_LOCALIDADE,
											 SIG_UF,
											 NOM_PAIS,
											 DSC_NATUREZA,
											 DSC_STATUS,
											 ULT_ATUALIZACAO
											) VALUES(
											'".$flight["NUM_VOO"]."',
											'".$flight["DAT_VOO"]."',
											'".$DAT_VOO_DT."',
											'".$flight["HOR_PREV"]."',
											'".$HOR_PREV_DT."',
											'".$flight["HOR_CONF"]."',
											'".$HOR_CONF_DT."',
											'".$flight["NUM_TPS"]."',
											'".$flight["NUM_GATE"]."',
											'".$flight["TXT_OBS"]."',
											'".$flight["NOM_CIA"]."',
											'".$flight["SIG_CIA_AEREA"]."',
											'".$flight["DSC_EQUIPAMENTO"]."',
											'".$flight["NOM_AEROPORTO"]."',
											'".$flight["COD_IATA"]."',
											'".$flight["COD_ICAO"]."',
											'".$flight["NOM_LOCALIDADE"]."',
											'".$flight["SIG_UF"]."',
											'".$flight["NOM_PAIS"]."',
											'".$flight["DSC_NATUREZA"]."',
											'".$flight["DSC_STATUS"]."',
											NOW()
											);";
					//echo $sql;
					$result = mysqli_query($this->db_conn, $sql);
				}
			}
		
		} // is_array
		
		} // !is_null
		
	}
	
	private function db_connect() {
		$this->db_conn = mysqli_connect($this->host, $this->username, $this->password, $this->database);
		if (mysqli_connect_errno())
		{
			die("Failed to connect to MySQL: " . mysqli_connect_error());
		}
	}
	
	private function db_disconnect() {
		mysqli_close($this->db_conn);
	}
	
	private function check_flight_changes($compare) {
		$result = mysqli_query($this->db_conn, "SELECT * FROM voos WHERE NUM_VOO = '".$flight["NUM_VOO"]."' AND DAT_VOO = '".$flight["DAT_VOO"]."';");
		$flight = mysqli_fetch_array($result, MYSQLI_ASSOC);
		
		if (	$flight["HOR_PREV"]   != $compare["HOR_PREV"] ||
				$flight["HOR_CONF"]   != $compare["HOR_CONF"] ||
				$flight["NUM_TPS"] 	  != $compare["NUM_TPS"]  ||
				$flight["NUM_GATE"]   != $compare["NUM_GATE"] ||
				$flight["TXT_OBS"] 	  != $compare["TXT_OBS"]  ||
				$flight["DSC_STATUS"] != $compare["DSC_STATUS"]
			) {
				
			/////////////
			// dispara os PUSH pra interessados neste voo	
			/////////////
				
			return true;
		} else {
			return false;
		}
	}
	
	private function utf8_converter($array)
	{	
		if (is_array($array)) {
			array_walk_recursive($array, function(&$item, $key){
				if (!is_array($item))
							 $item = utf8_decode($item);
			});
			return $array;
		} else {
			return $array;
		}
	}
	
} // class end




// THREAD
/*
class InfraeroThread extends Thread {

	// ID da thread (usado para identificar a ordem que as threads terminaram)
	protected $id;

	// Construtor que apenas atribui um ID para identificar a thread
	public function __construct($id) {
		$this->id = $id;
	}

	// Metodo principal da thread, que sera acionado quando chamarmos "start"
	public function run() {
		
		$daemon = new infraero_daemon($host, $database, $username, $password);
		$daemon->init();
		$daemon->process_all_airports();
		
	}
}
*/


?>

