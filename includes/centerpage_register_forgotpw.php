<?php
if(isset($_POST['email'])) $email=$conn->real_escape_string(test($_POST['email']));
if(isset($_POST['recov_code'])) $recov_code=$conn->real_escape_string(test($_POST['recov_code']));
$error_msg="";

if(isset($_POST['send_recov']) && !empty($email))
{
	$temp_pw=bin2hex(openssl_random_pseudo_bytes(32));
	
	$row=$conn->query("SELECT s.student_user_id, s.student_givenname FROM student s
		JOIN user u ON (s.student_user_id=u.user_id) WHERE ((u.user_email='$email' AND u.user_email_auth_time IS NOT NULL) 
		OR (s.student_institution_email='$email' AND s.student_email_auth IS NOT NULL))
		AND (u.user_forgotpw_time IS NULL OR u.user_forgotpw_time + INTERVAL 5 MINUTE < NOW())")->fetch_assoc();
	if(!empty($row['student_user_id']))
		$conn->query("UPDATE user SET user_forgotpw='$temp_pw', user_forgotpw_time=NOW() WHERE user_id='".$row['student_user_id']."'");
	else $conn->query("UPDATE user SET user_forgotpw='$temp_pw', user_forgotpw_time=NOW() WHERE user_email='$email' AND (user_forgotpw_time IS NULL OR user_forgotpw_time + INTERVAL 5 MINUTE < NOW())");
	
	$text="You are requesting a password reset and your recovery code is: ".$temp_pw;
	if(!empty($row['student_user_id']) || $conn->query("SELECT 1 FROM user WHERE user_email='$email' AND (user_forgotpw_time IS NULL OR user_forgotpw_time + INTERVAL 5 MINUTE < NOW())")->num_rows > 0)
	{
		if(!empty($row['student_givenname']))
			send_mail($email,'Recover account','',"Dear ".$row['student_givenname'].",\n\n".
			$text."\n\nThe myphdidea team",'');
		else send_mail($email,'Recover account','',"Dear Guest,\n\n".
			$text."\n\nThe myphdidea team",'');
	}
	
	$confirmation_msg="Request received";
}

if(isset($_POST['submit']))
{
	if(empty($_POST['email']) || empty($_POST['recov_code']) || empty($_POST['newpw']) || empty($_POST['newpw_confirm']))
		$error_msg=$error_msg."All 4 fields required!<br>";
	elseif($_POST['newpw']!=$_POST['newpw_confirm'])
		$error_msg=$error_msg."Passwords mismatch!<br>";
	elseif(strlen($_POST['newpw']) < 6)
		$error_msg=$error_msg."Password minimum 6 characters!<br>";
	elseif($conn->query("SELECT 1 FROM user WHERE user_forgotpw='$recov_code' AND NOW() < user_forgotpw_time + INTERVAL 30 MINUTE")->num_rows==0)
		$error_msg=$error_msg."Recovery code wrong or no longer valid!<br>";
	else
	{
		$sql="SELECT s.student_user_id, u.user_pseudonym, u.user_forgotpw_question, u.user_forgotpw_answer
			FROM student s JOIN user u ON (u.user_id=s.student_user_id)
			WHERE ((u.user_email='$email' AND u.user_email_auth_time IS NOT NULL)
			OR (s.student_institution_email='$email' AND s.student_email_auth IS NOT NULL)) AND user_forgotpw='$recov_code' AND NOW() < user_forgotpw_time + INTERVAL 30 MINUTE";
		$result=$conn->query($sql);
		if($result->num_rows > 0)
		{
			$row=$result->fetch_assoc();
			$user_id=$row['student_user_id'];
		}
		else
		{
			$result=$conn->query("SELECT user_id, user_pseudonym, u.user_forgotpw_question, u.user_forgotpw_answer FROM user WHERE user_email='$email' AND user_forgotpw='$recov_code' AND NOW() < user_forgotpw_time + INTERVAL 30 MINUTE");
			if($result->num_rows > 0)
			{
				$row=$result->fetch_assoc();
				$user_id=$row['user_id'];
			}
			else $error_msg=$error_msg."Recovery code wrong or no longer valid, or e-mail not found!";
		}
	
		if(!empty($row['user_forgotpw_question']) && !empty($row['user_forgotpw_answer']) && (empty($_POST['fpw_answer']) || $_POST['fpw_answer']!=$row['user_forgotpw_answer']))
			$error_msg=$error_msg."Sec question: ".$row['user_forgotpw_question'].' <input type="text" name="fpw_answer">';
		if(empty($error_msg))
		{
			$password=crypt($conn->real_escape_string(test($_POST['newpw'])),$row['user_pseudonym']);
			$conn->query("UPDATE user SET user_password='$password' WHERE user_id='$user_id'");
			$confirmation_msg="Password updated!";
		}
	}
}
?>
<div id="centerpage">
<form method="post" action="">
	<h2>Recover password</h2>
    <?php if(!empty($error_msg)) echo '<div style="color: red; text-align: center">'.$error_msg.'</div><br>';?>
	<?php if(!empty($confirmation_msg)) echo '<div style="color: #1ae61a; text-align: center">'.$confirmation_msg.'</div><br>';?>
	<div class="indentation">
	<label style="width: 120px">e-mail:</label><input type="text" style="width: 200px" name="email" <?php if(isset($email)) echo 'value="'.$email.'""'; ?> >
	<button name="send_recov" style="float: right">Send recovery code</button>
	</div>
	<h3>Reset</h3>
	<div class="indentation">
	<label style="width: 120px">Recovery code:</label><input type="text" style="width: 200px" name="recov_code" <?php if(isset($recov_code)) echo 'value="'.$recov_code.'""'; ?> ><br><br>
	<label style="width: 120px">New password:</label><input type="password" style="width: 200px" name="newpw"><br>
	<label style="width: 120px">Confirm:</label><input type="password" style="width: 200px" name="newpw_confirm">
	<p style="text-align: right"><button name="submit">Submit</button></p>
	</div>
</form>
</div>
