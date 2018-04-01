<?php

$verify=$conn->real_escape_string(test($_GET['verify']));
$sql="SELECT student_id, student_email_auth, student_verdict_summary FROM student WHERE student_email_token LIKE '".$conn->real_escape_string($verify)."'";

$result=$conn->query($sql);
if(!$result)
	echo 'Could not run query for e-mail token.';
else if($result->num_rows==0)
	header('Location: index.php?confirm=invalid_token');
else 
	{
		$row=$result->fetch_assoc();
		if(empty($row['student_email_auth']) && empty($row['student_verdict_summary']))
		{
			$sql="INSERT INTO moderators_group (moderators_group_type,moderators_group_hashcode) VALUES ('USER','".rand(0,9999)."')";
			$conn->query($sql);

			$sql="SELECT LAST_INSERT_ID();";
			$moderators_group=$conn->query($sql)->fetch_assoc();
			$moderators_group=$moderators_group['LAST_INSERT_ID()'];

			$sql="INSERT INTO moderators (moderators_group) VALUES ('$moderators_group')";
			$result=$conn->query($sql);

			$sql="SELECT LAST_INSERT_ID();";
			$moderators_id=$conn->query($sql)->fetch_assoc();
			$moderators_id=$moderators_id['LAST_INSERT_ID()'];
				
			//CREATE TASK, ASSEMBLE VERDICT (NO NOTIFYING MODERATORS FOR THIS ONE)
			$sql="INSERT INTO task (task_time_created) VALUES (NOW())";
			$conn->query($sql);
				
			$sql="SELECT LAST_INSERT_ID();";
			$task_id=$conn->query($sql)->fetch_assoc();
			$task_id=$task_id['LAST_INSERT_ID()'];
				
			$sql="INSERT INTO verdict (verdict_moderators,verdict_task,verdict_type) VALUES ('$moderators_id','$task_id','USER')";
			$conn->query($sql);

			$sql="SELECT LAST_INSERT_ID();";
			$verdict_id=$conn->query($sql)->fetch_assoc();
			$verdict_id=$verdict_id['LAST_INSERT_ID()'];

			$sql="UPDATE student SET student_email_auth=NOW(), student_initauth_verdict='$verdict_id', student_email_token=NULL WHERE student_email_token LIKE '".$conn->real_escape_string($verify)."'";
			if(!$conn->query($sql))
				echo 'Insert error';
			
			addto_watchlists($conn,$moderators_id,'USER',$task_id, '0', '1'/*, '', '', ''*/);
		}
	}

//$conn->close();
?>
<div id="centerpage" style="text-align: center">
<br>Your institutional mail account has been verified! Please await decision by volunteers on your membership application before your account will be enabled (you will receive a notification mail).
<br><br><img title="Wait until your number is called" alt="Draw number photo"
          src="images/drawnumber.png">
</div>
