<?php
error_reporting(E_ALL);
include_once("config.php");
include_once("API.php");
$API = new SmiteApi($config);

$data = "";
$friendCount = 0;
$username = $API->secureString($_POST['username']);
if(isset($_POST['submit'])){
	$resp = $API->makeRequest("getfriends", "/DEV_ID/DEV_SIG/DEV_SES/TIMESTAMP/".$username, "JSON");
	foreach($resp as $row){
		$data .= $row->name."<br />";
		$friendCount += 1;
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
if($friendCount == 1){
	echo $username." has ".$friendCount." friend!<br />".$data;
}else{
	echo $username." has ".$friendCount." friends!<br />".$data;
}
?>