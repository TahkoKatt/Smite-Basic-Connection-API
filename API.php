<?php
class SmiteAPI{
	private $configData;
	private $dbc;
	private $projectName = "Smite Connection API";

	public function __construct($config){
		$this->configData = new stdClass();
		$this->configData = $config;
		$this->dbc = $this->createDatabaseConnection();
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

		return $resp;
	}

	public function makeRequest($request, $url){
		/* $request = "getfriends";
		 * $url = "/DEV_ID/DEV_SIG/TIMESTAMP/USERNAME";
		 * Example: http://api.smitegame.com/smiteapi.svc/getfriendsJSON/DEV_ID/DEV_SIG/TIMESTAMP/USERNAME
		 */

		$url = str_replace("DEV_ID", $this->configData->DEV_ID, $url);
		$url = str_replace("DEV_SIG", $this->generateSignature($request), $url);
		$url = str_replace("DEV_SES", $this->getSessionID(), $url);
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

	public function getSessionID(){
		$q = $this->dbc->prepare("SELECT * FROM sessions WHERE expired = ?");
		$q->bindValue(1, false);
		$q->execute();

		$sessionID = NULL;
		$timestamp = NULL;
		if($q->rowCount() == 1){
			foreach($q->fetchAll() as $row){
				$sessionID = $row['session_id'];
				$timestamp = $row['expiration_timestamp'];
			}

			//Check if we need a new session made.
			if(time() >= $timestamp){
				//Generate new session as this one has expired
				$q = $this->dbc->prepare("UPDATE sessions SET expired = ? WHERE session_id = ?");
				$q->bindValue(1, 1);
				$q->bindValue(2, $sessionID);
				if($q->execute()){
					return $this->createSession();
				}
			}else{
				//Don't need a new session made so return the current one.
				return $sessionID;
			}
		}else{
			//No active session at the moment, generate one.
			return $this->createSession();
		}
		return false;
	}

	private function isActiveSessionAvailable(){
		$q = $this->dbc->prepare("SELECT * FROM sessions WHERE expired = ?");
		$q->bindValue(1, false);
		$q->execute();

		$sessionID = NULL;
		$timestamp = NULL;
		//Session available, but has it expired?
		if($q->rowCount() == 1){
			foreach($q->fetchAll() as $row){
				$timestamp = $row['expiration_timestamp'];
			}

			//Check if the session has expired.
			if(time() >= $timestamp){
				return false;
			}else{
				return true;
			}
		}else{
			return false;
		}
	}

	private function makeSessionInternally(){
		$resp = $this->makeSessionRequest();
		//echo print_r($resp);
		//die();
		$q = $this->dbc->prepare("INSERT INTO sessions SET session_id = ?, expiration_timestamp = ?, expired = ?");
		$q->bindValue(1, $resp->session_id);
		$q->bindValue(2, time()+$this->configData->SESSION_DURATION);
		$q->bindValue(3, 0);
		if($q->execute()){
			return $resp->session_id;
		}else{
			trigger_error($this->projectName.": Couldn't create a new session.");
		}
	}

	private function createSession(){
		if($this->isActiveSessionAvailable()){
			$q = $this->dbc->prepare("SELECT * FROM sessions WHERE expired = ?");
			$q->bindValue(1, false);
			$q->execute();

			$sessionID = NULL;
			$timestamp = NULL;
			foreach($q->fetchAll() as $row){
				$sessionID = $row['session_id'];
				$timestamp = $row['expiration_timestamp'];
			}

			//Check if we need a new session made.
			if(time() >= $timestamp){
				//Generate new session as this one has expired
				$q = $this->dbc->prepare("UPDATE sessions SET expired = ? WHERE session_id = ?");
				$q->bindValue(1, 1);
				$q->bindValue(2, $sessionID);
				if($q->execute()){
					return $this->makeSessionInternally();
				}else{
					trigger_error($this->projectName.": Couldn't set old session as expired.");
				}
			}else{
				//Don't need a new session made so return the current one.
				return $sessionID;
			}
		}else{
			//No active session at the moment, generate one.
			return $this->makeSessionInternally();
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