<?php
error_reporting(E_ALL);
require_once("autoloader.php");

$data = $SmiteAPI->makeRequest("getdataused", "/DEV_ID/DEV_SIG/DEV_SES/TIMESTAMP");
if(!empty($data[0])){
	$q = $SmiteAPI->dbc->prepare("SELECT * FROM sessions WHERE expired = ?");
	$q->bindValue(1, 0);
	$q->execute();

	$session_id = NULL;
	$timestamp = NULL;
	foreach($q->fetchAll() as $row){
		$session_id = $row["session_id"];
		$timestamp = $row["timestamp"];
	}

	$dailyRequestLimit = $data[0]->Request_Limit_Daily;
	$requestsSoFar = $data[0]->Total_Requests_Today;
	$totalRequestsLeft = $dailyRequestLimit-$requestsSoFar;

	$activeSessions = $data[0]->Active_Sessions;
	$sessionCap = $data[0]->Session_Cap;
	$totalSessionsSoFar = $data[0]->Total_Sessions_Today;
	$totalSessionsLeft = $sessionCap-$totalSessionsSoFar;

	echo "Requests used: ".$requestsSoFar."/".$dailyRequestLimit."<br />Total requests left: ".$totalRequestsLeft."<br /><br />";
	echo "Sessions used: ".$totalSessionsSoFar."/".$sessionCap."<br />Active sessions: ".$activeSessions."<br />Total sessions left: ".$totalSessionsLeft."<br /><br />";
	echo "Time left on active session: ".round((($timestamp-time())/60), 2)." minutes.";
}