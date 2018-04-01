<?php
$verify=$conn->real_escape_string(test($_GET['verify']));
$sql="SELECT autoedit_email, autoedit_prof FROM autoedit WHERE autoedit_email_token LIKE '".$conn->real_escape_string($verify)."'";

$result=$conn->query($sql);
if(!$result)
	echo 'Could not run query for e-mail token.';
else if($result->num_rows==0)
	header('Location: index.php?confirm=invalid_token');
else
	{
		$row=$result->fetch_assoc();
		$sql="UPDATE autoedit SET autoedit_email=autoedit_email_new, autoedit_email_auth=NOW(), autoedit_email_token=NULL WHERE autoedit_email_token LIKE '".$conn->real_escape_string($verify)."'";
		if(!$conn->query($sql))
			echo 'Insert error';
		if(empty($row['autoedit_email'])) $conn->query("UPDATE prof SET prof_hasactivity='1' WHERE prof_id='".$row['autoedit_prof']."'");
	}
?>
<div id="centerpage" style="text-align: center">
<br>
Your e-mail account has been verified! Login and go visit the 'ideas' page to start rating.
<br><br><img title="Grade inflation" alt="Grade photo"
          src="images/grading.jpg">
</div>