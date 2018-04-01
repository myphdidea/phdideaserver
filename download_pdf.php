<?php
include("includes/header_functions.php");

$upload_id=$conn->real_escape_string(test($_GET['upload']));
$file_nb=$conn->real_escape_string(test($_GET['nb']));

session_start();
if(isset($_SESSION['user']))
	$_SESSION['upload_'.$upload_id."_".$file_nb]=TRUE;

$sql="SELECT c.cmpgn_id, c.cmpgn_blanknames, c.cmpgn_revealrealname, c.cmpgn_time_finalized, c.cmpgn_revealtoprof, c.cmpgn_visibility_blocked,
	u.upload_file1, u.upload_file2, u.upload_file3 FROM cmpgn c JOIN upload u ON (c.cmpgn_id=u.upload_cmpgn) WHERE u.upload_id='".$upload_id."'";
$row=$conn->query($sql)->fetch_assoc();
$filename=$row['cmpgn_id'].'_'.$upload_id.'_'.$file_nb.'.pdf';
$output_name=$row['upload_file'.$file_nb];

if(!empty($row['cmpgn_visibility_blocked']))
	header("Location: index.php?confirm=blocked");

if(isset($_SESSION['prof']) && $conn->query("SELECT 1 FROM review r JOIN upload u ON (r.review_upload=u.upload_id)
	WHERE u.upload_cmpgn='".$row['cmpgn_id']."' AND r.review_prof='".$_SESSION['prof']."'")->num_rows > 0 && !empty($row['cmpgn_revealtoprof']))
	$visitor_isreviewprof=TRUE;

if(!$row['cmpgn_blanknames'] || !empty($visitor_isreviewprof) || (!empty($row['cmpgn_time_finalized']) && $row['cmpgn_revealrealname']==1))
{
	header("Content-type: application/pdf");
	header("filename=".$output_name);
	readfile('user_data/uploads/'.$filename);
}
else
{
	if(file_exists('user_data/uploads_redacted/'.$filename.".rdc"))
	{
		header("Content-type: application/pdf");
		header("filename=".$output_name);
		readfile('user_data/uploads_redacted/'.$filename.".rdc");
	}
	else
		header("Location: https://s3-eu-west-1.amazonaws.com/phdideardc/".$filename);
}

$conn->close();
?>