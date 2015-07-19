<?php
error_reporting(E_ALL);
session_start();
include_once("config.php");
include_once("API.php");
$API = new SmiteApi($smiteConfig);

$data = "";
$friendCount = 0;
$username = isset($_POST['username']) ? $API->secureString($_POST['username']) : "";
if(isset($_POST['submit'])){
	if(!$username == ""){
		$resp = $API->makeRequest("getfriends", "/DEV_ID/DEV_SIG/DEV_SES/TIMESTAMP/".$username, "JSON");
		foreach($resp as $row){
			$data .= $row->name."<br />";
			$friendCount += 1;
		}
	}
}
?>
	<center>
		<form action="" method="POST">
			<table>
				<tr>
					<td>Username</td>
					<td><input type="text" name="username"></td>
				</tr>
			</table>
			<input type="submit" name="submit" value="See user's friends!">
		</form>
	</center>
<?php
if(!$username == ""){
	if($friendCount == 1){
		echo $username." has ".$friendCount." friend!<br />".$data;
	}else{
		echo $username." has ".$friendCount." friends!<br />".$data;
	}
}
?>