<?php
include("includes/header_functions.php");

$student_id=$conn->real_escape_string(test($_GET['student']));

session_start();
if(isset($_SESSION['user']) && ($conn->query("SELECT 1 FROM student s
	JOIN verdict v ON (s.student_initauth_verdict=v.verdict_id)
	JOIN moderators m ON (v.verdict_moderators=m.moderators_id)
	WHERE s.student_id='$student_id' AND (m.moderators_first_user='".$_SESSION['user']."' OR m.moderators_second_user='".$_SESSION['user']."' OR m.moderators_third_user='".$_SESSION['user']."')
	AND (v.verdict_time1 IS NULL OR v.verdict_time2 IS NULL OR v.verdict_time3 IS NULL)")->num_rows > 0
	|| $_SESSION['user']==1))
{
	$_SESSION['studentpdf_'.$student_id]=TRUE;
	$filename='user_data/transcripts/'.$student_id.'.pdf';
	
	header("Content-type: application/pdf");
	header("filename=transcript.pdf");
	readfile($filename);
}
else echo "Not a moderator cannot download pdf!";

$conn->close();
?>