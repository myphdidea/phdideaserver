<?php
$verify=$conn->real_escape_string(test($_GET['verify']));
$sql="SELECT user_id, user_email_auth_time FROM user WHERE user_email_token LIKE '".$conn->real_escape_string($verify)."'";

$result=$conn->query($sql);
if(!$result)
	echo 'Could not run query for e-mail token.';
else if($result->num_rows==0)
	header('Location: index.php?confirm=invalid_token');
else
	{
		$row=$result->fetch_assoc();
		if(empty($row['user_email_auth_time']))
		{
			$sql="UPDATE user SET user_email_auth_time=NOW(), user_email_token=NULL WHERE user_email_token LIKE '".$conn->real_escape_string($verify)."'";
			if(!$conn->query($sql))
				echo 'Insert error';
		}
		else $conn->query("UPDATE user SET user_email=user_email_new, user_email_auth_time=NOW(), user_email_token=NULL WHERE user_email_token LIKE '".$conn->real_escape_string($verify)."'");
		
		$result=$conn->query("SELECT guest_key FROM guest WHERE guest_user='".$row['user_id']."'");
		if($result->num_rows > 0)
			$row2=$result->fetch_assoc();
	}
?>
<div id="centerpage" style="text-align: center">
<br>
Your e-mail account has been verified! Please login on the left.
<?php if(!empty($row2)) echo ' Please also note down the following key for communicating to your 3 existing student member sponsors: '.$row2['guest_key']; ?>
<br><br><img title="Seal of approval" alt="Approval seal"
          src="images/authenticated.jpg">
</div>