<?php
error_reporting(E_ALL);
require_once("autoloader.php");

$data = "";
$friendCount = 0;
$username = isset($_POST['username']) ? $API->secureString($_POST['username']) : "";
if(isset($_POST['submit'])){
	if(!$username == ""){
		$resp = $SmiteAPI->makeRequest("getfriends", "/DEV_ID/DEV_SIG/DEV_SES/TIMESTAMP/".$username);
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