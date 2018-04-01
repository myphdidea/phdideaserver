<?php
include("includes/header_functions.php");

$code=$_GET['code'];

$fields=gen_googlequery($code);//CONTAINS PASSWORD FOR GOOGLE

$callback_array=login_callback("https://accounts.google.com/o/oauth2/token",$fields);

list($header, $payload, $signature) = explode (".", $callback_array['id_token']);
$jwt_decoded=json_decode(base64_decode($payload),'true');
if(!empty($jwt_decoded['sub']))
{
	session_start();
	
	$result=$conn->query("SELECT user_id FROM user WHERE user_sociallogin_google='".$conn->real_escape_string($jwt_decoded['sub'])."'");
	if($result->num_rows > 0)
	{
		$row=$result->fetch_assoc();
		$_SESSION['user']=$row['user_id'];
		$_SESSION['isstudent']=TRUE;
	}
	else $_SESSION['google']=$conn->real_escape_string(test($jwt_decoded['sub']));
}

header("Location: index.php");

?>