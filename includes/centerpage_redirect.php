<?php
$link_url=urldecode($_GET['link']);
if(!empty($link_url))
{
	$_SESSION['link']=$link_url;
	if(substr($link_url,0,4)!="http")
		$link_url="http://".$link_url;
	header("Location: ".$link_url);
}
else echo "Not a valid link!";
?>