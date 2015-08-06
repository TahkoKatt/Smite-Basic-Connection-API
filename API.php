<?php
class SmiteAPI{
	private $configData;
	public $dbc;
	private $projectName = "Smite Connection API";

	public function __construct($config){
		$this->configData = new stdClass();
		$this->configData = $config;
		$this->dbc = $this->createDatabaseConnection();
		//$this->createSession();
	}

	private function getReturnMessages($request){
		$returnMessageList = [
			"createsession" => [
				"Approved"
			]
		];

		if(isset($returnMessageList[$request])){
			return $returnMessageList[$request];
		}else{
			return [$request => "ERROR"];
		}
	}

	private function makeSessionRequest(){
		$url = $this->configData->SITE_URL."createsessionJSON/DEV_ID/DEV_SIG/TIMESTAMP";
		$url = str_replace("DEV_ID", $this->configData->DEV_ID, $url);
		$url = str_replace("DEV_SIG", $this->generateSignature("createsession"), $url);
		$url = str_replace("TIMESTAMP", $this->getTimestamp(), $url);

		//die($url);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		$resp = curl_exec($ch);
		curl_close($ch);

		//If the second parameter is true, it'll return the results as an array, otherwise a stdClass.
		$resp = json_decode($resp, false);

		//Check for any errors when retrieving data.
		if(isset($resp->ret_msg)){
			if(!in_array($resp->ret_msg, $this->getReturnMessages("createsession"))){
				trigger_error($this->projectName.": An error has occurred while internally making the session.<br />".$resp->ret_msg);
				die(print_r($resp));
			}
		}

		if(empty($resp)){
			return $this->makeSessionRequest();
		}
		
		return $resp;
	}

	public function makeRequest($request, $url){
		/* $request = "getfriends";
		 * $url = "/DEV_ID/DEV_SIG/TIMESTAMP/USERNAME";
		 * Example: http://api.smitegame.com/smiteapi.svc/getfriendsJSON/DEV_ID/DEV_SIG/TIMESTAMP/USERNAME
		 */

		$url = str_replace("DEV_ID", $this->configData->DEV_ID, $url);
		$url = str_replace("DEV_SIG", $this->generateSignature($request), $url);
		$url = str_replace("DEV_SES", $this->getSession(), $url);
		$url = str_replace("TIMESTAMP", $this->getTimestamp(), $url);

		//echo $this->configData->SITE_URL.$request."JSON".$url;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->configData->SITE_URL.$request."JSON".$url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		$resp = curl_exec($ch);
		curl_close($ch);

		//If the second parameter is true, it'll return the results as an array, otherwise a stdClass.
		$resp = json_decode($resp, false);

		//Check for any errors when retrieving data.
		if(isset($resp->ret_msg)){
			if(!in_array($resp->ret_msg, $this->getReturnMessages($request))){
				trigger_error($this->projectName.": An error has occurred while internally making the session.<br />".$resp->ret_msg);
				die(print_r($resp));
			}
		}elseif(empty($resp)){
			return "";
		}

		return $resp;
	}

	public function secureString($str){
		//First it turns data into the HTML equivalent, then removes slashes, then spaces.
		return trim(stripslashes(htmlspecialchars($str)));
	}

	private function getTimestamp(){
		return gmdate('YmdHis');
	}

	private function generateSignature($request){
		return md5($this->configData->DEV_ID.$request.$this->configData->API_KEY.$this->getTimestamp());
	}

	private function isActiveSessionAvailable(){
		$q = $this->dbc->prepare("SELECT * FROM sessions WHERE expired = ?");
		$q->bindValue(1, 0);
		$q->execute();

		$session_id = NULL;
		$timestamp = NULL;
		foreach($q->fetchAll() as $row){
			$session_id = $row["session_id"];
			$timestamp = $row["timestamp"];
		}

		if($q->rowCount() == 1){
			if(time() >= $timestamp){
				//Session has expired
				return ["available" => false, "session_id" => $session_id];
			}else{
				//Session is still active
				return ["available" => true, "session_id" => $session_id];
			}
		}else{
			return ["available" => false, "session_id" => NULL];
		}
	}

	private function makeSessionInternally($resp){
		if(!empty($resp)){
			$timestamp = time() + $this->configData->SESSION_DURATION;
			$q = $this->dbc->prepare("SELECT * FROM sessions");
			$q->execute();
			if($q->rowCount() == 1){
				if($q->fetch()["expired"]){
					$expiration = $q->fetch()["timestamp"];
					if(time() >= $expiration){
						//Session has expired, generate new one.
						$q = $this->dbc->prepare("UPDATE sessions SET session_id = ?, timestamp = ?, expired = ?");
						$q->bindValue(1, $resp->session_id);
						$q->bindValue(2, $timestamp);
						$q->bindValue(3, 0);
						if($q->execute()){
							return $resp->session_id;
						}
					}else{
						//Session is active, return it.
						return $q->fetch()["session_id"];
					}
				}else{
					//Session has expired, generate new session.
					$q = $this->dbc->prepare("UPDATE sessions SET session_id = ?, timestamp = ?, expired = ?");
					$q->bindValue(1, $resp->session_id);
					$q->bindValue(2, $timestamp);
					$q->bindValue(3, 0);
					if($q->execute()){
						return $resp->session_id;
					}
				}
			}else{
				$q = $this->dbc->prepare("INSERT INTO sessions SET session_id = ? AND timestamp = ? AND expired = ?");
				$q->bindValue(1, $resp->session_id);
				$q->bindValue(2, $timestamp);
				$q->bindValue(3, 0);
				if($q->execute()){
					return $resp->session_id;
				}
			}
		}else{
			return false;
		}
	}

	private function getSession(){
		$sessionArr = $this->isActiveSessionAvailable();
		if($sessionArr["available"]){
			return $sessionArr["session_id"];
		}else{
			$resp = $this->makeSessionRequest();
			return $this->makeSessionInternally($resp);
		}
	}

	private function createDatabaseConnection(){
		$dbc = NULL;
		try{
			$dbc = new PDO("mysql:host=localhost;dbname=".$this->configData->database->name, $this->configData->database->username, $this->configData->database->password);
			$dbc->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}catch(PDOException $e){
			echo 'Connection failed: '.$e->getMessage();
		}
		return $dbc;
	}
}