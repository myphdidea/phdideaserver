<?php
include("../includes/header_functions.php");
session_start();

if($_SESSION['user']=='1')
{
	$question=$conn->real_escape_string(test($_GET['question']));
	$answers=array();
	foreach($_GET['answers'] as $answer)
		$answers[]=$conn->real_escape_string(test($answer));
	echo $question."<br>";
	var_dump($answers);

	if(isset($_POST['submit']))
	{
		$sql="INSERT INTO ratebox VALUES ()";
		$conn->query($sql);
		$sql="SELECT LAST_INSERT_ID();";
		$newratebox=$conn->query($sql)->fetch_assoc();
		$newratebox=$newratebox['LAST_INSERT_ID()'];

		$sql="INSERT INTO poll (poll_ratebox,poll_question) VALUES ('$newratebox','$question')";
		$conn->query($sql);
		$sql="SELECT LAST_INSERT_ID();";
		$poll_id=$conn->query($sql)->fetch_assoc();
		$poll_id=$poll_id['LAST_INSERT_ID()'];
		
		foreach($answers as $answer)
			$conn->query("INSERT INTO pollanswer (pollanswer_poll,pollanswer_text) VALUES ('$poll_id','$answer')");
	}
}
$conn->close();
?>
<form method="post" action="">
	<button name="submit">Add to poll</button>
</form>