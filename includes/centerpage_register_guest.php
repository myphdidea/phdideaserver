<?php
$inst_mail=$family_name=$given_name=$annuary_link=$annuary_instructions=$private_mail=$pseudonym="";
$password=$password_confirm=$forgot_pw_quest=$forgot_pw_answ="";
if($_SERVER['REQUEST_METHOD']=='POST')
{
	$email=test($_POST['email']); $family_name=test($_POST['family_name']); $given_name=test($_POST['given_name']);
	$pseudonym=test($_POST['pseudonym']);
	$password=test($_POST['password']); $password_confirm=test($_POST['password_confirm']);
	if(isset($_POST['subject1'])) $subject1=$conn->real_escape_string(test($_POST['subject1']));
	if(isset($_POST['subject2'])) $subject2=$conn->real_escape_string(test($_POST['subject2']));
	
	$error_msg="";
	
	if(empty($_POST['email']))
		$error_msg='Cannot have empty e-mail.<br>';
	if(empty($_POST['family_name']) || empty($_POST['given_name']))
		$error_msg='Cannot have empty name.<br>';
	if(empty($_POST['pseudonym']))
		$error_msg="Cannot have empty pseudonym.<br>";
	if(empty($_POST['password']) || empty($_POST['password_confirm'])) 
		$error_msg='Cannot have empty password.<br>';
	else {
	if(!filter_var($email, FILTER_VALIDATE_EMAIL))
		$error_msg='E-mail not a valid format.<br>';
	if($password!=$password_confirm)
		$error_msg=$error_msg.'Typing error in password?<br>';
	if(strlen($password) < 6)
		$error_msg=$error_msg.'At least 6 characters required for password.<br>';
	if(empty($subject1))
		$error_msg=$error_msg.'Please choose academic subject or combination thereof.<br>';
	}
	if(empty($error_msg))
	{
		list($part1,$part2)=explode('@',$email);

		$sql = "SELECT institution_id FROM institution WHERE institution_isuniversity='1' AND (institution_emailsuffix LIKE '".$conn->real_escape_string($part2)."' OR '".$conn->real_escape_string($part2)."' LIKE CONCAT('%.',institution_emailsuffix)) ";
		$result = $conn->query($sql);

		if ($result->num_rows > 0/* && empty($_POST['confirm_instmail'])*/)
			$error_msg=$error_msg.'Institutional e-mail, please register as full user rather than guest!<br>';

		$sql = "SELECT 1 FROM user WHERE user_pseudonym LIKE '".$conn->real_escape_string($pseudonym)."'";
		if($conn->query($sql)->num_rows > 0)
			$error_msg=$error_msg."Pseudonym seems to be already taken!";
		$sql = "";
		if($conn->query("SELECT 1 FROM user WHERE user_email LIKE '$email'")->num_rows > 0
			|| $conn->query("SELECT 1 FROM student WHERE student_institution_email LIKE '$email'")->num_rows > 0
			|| $conn->query("SELECT 1 FROM autoedit WHERE autoedit_email LIKE '$email'")->num_rows > 0)
			$error_msg=$error_msg."E-mail seems to be already registered.<br>";

		if(empty($error_msg) && !empty($_POST['g-recaptcha-response']))
        {
        	//your site secret key
        	$secret = 'INSERT_GOOGLE_RECAPTCHA_SECRET_HERE';
        	//get verify response data
        	$verifyResponse = curl_get_contents('https://www.google.com/recaptcha/api/siteverify?secret='.$secret.'&response='.$_POST['g-recaptcha-response']);
        	$responseData = json_decode($verifyResponse);
        	if(!$responseData->success)
				$error_msg="reCaptcha failed please try again!";
		}

		//finally, the main block!
		if(empty($error_msg) && !empty($_POST['g-recaptcha-response']))
		{
			$email_token=md5( rand(0,10000000) );
			do
			{
			$guest_token=md5( rand(0,10000000) );
			} while($conn->query("SELECT 1 FROM guest WHERE guest_key='".$conn->real_escape_string($guest_token)."'")->num_rows > 0);
			
			if(send_mail($email,'Confirm your credentials','http://localhost//myphdidea/index.php?confirm=verify_user&verify='.$email_token,
					"Dear ".$given_name.",\n\n".
					"You have registered this e-mail address to myphdidea.org in view of opening a guest account, in order to confirm it,
					 please click on or copy-paste the link below:\n\n","\n\nAfter confirmation, you should find 3 friends still at your institution and tell them to authenticate you using your ID code, which is: ".$guest_token."\n\n
					 The myphdidea team"))
			{
				$email_token="'".$email_token."'";
				$given_name=$conn->real_escape_string($given_name);
				$family_name=$conn->real_escape_string($family_name);				

				//FIRST, CREATE USER TABLE ENTRY
				$pseudonym=$conn->real_escape_string($pseudonym);
				$password=crypt($password,$pseudonym);

				//TREAT SOCIAL LOGIN
				$email="'".$conn->real_escape_string($email)."'";
				$email_token=$conn->real_escape_string($email_token);
				$forgot_pw_quest=empty($forgot_pw_quest) ? "NULL" : "'".$conn->real_escape_string($forgot_pw_quest)."'";
				$forgot_pw_answ=empty($forgot_pw_answ) ? "NULL" : "'".$conn->real_escape_string($forgot_pw_answ)."'";
				$subject2=empty($subject2) ? "NULL" : "'".$conn->real_escape_string($subject2)."'";
				$sql= "INSERT INTO user (user_pseudonym, user_password, user_forgotpw_question, user_forgotpw_answer,
									user_email, user_email_token, user_email_token_time, user_time_created, user_hasinstmail,
									user_subject1, user_subject2,user_pts_misc,user_turnoff_notifications,user_rank)
									VALUES ('$pseudonym', '$password', $forgot_pw_quest, $forgot_pw_answ,
									$email, $email_token, NULL, NOW(), FALSE, '$subject1', $subject2,'3','2','0');";
				if(!$conn->query($sql)) echo "Insert into user failed!";
				$sql="SELECT LAST_INSERT_ID();";
				$user_id=$conn->query($sql)->fetch_assoc();
				$user_id=$user_id['LAST_INSERT_ID()'];

				//THEN, STUDENT TABLE
				$guest_token=$conn->real_escape_string($guest_token);
				$sql= "INSERT INTO guest (guest_user, guest_givenname, guest_familyname, guest_key)
										VALUES ('$user_id', '$given_name', '$family_name', '$guest_token');";
				if(!$conn->query($sql)) echo "Insert into guest failed!";
				else
				{
					$misc='register_student';
					header('Location: index.php?confirm='.$misc);
				}
			}
			else $error_msg=$error_msg."Problem with sending mail please retry!";

			
		}
//		else if($student_already_taken > 0) $error_msg="Institutional e-mail seems to be already registered?";
//		else if($user_already_taken > 0) $error_msg="Email seems to be already registered as guest, please upgrade existing account rather than create new.";
	}
}
?>
<div id="centerpage">
	<form method="post" action="">
	<h2>Registration (guest account)</h2>
	<?php
		if(!empty($error_msg)) echo '<div style="color: red; text-align: center">'.$error_msg.'</div>';
		elseif($_SERVER['REQUEST_METHOD']=='POST') echo '<div style="text-align: center"><div style="display: inline-block" class="g-recaptcha" data-sitekey="INSERT_GOOGLERECAPTCHA_KEY_HERE"></div></div>';
	?>
	<p>If you do not have an institutional email address any longer, you can also register by finding 3 existing student
		members from the same institution as yours to vouch for you. To this end, you first create a dummy account,
		receive your registration details which you communicate to the student members, who then authenticate you
		(each existing student member may only do this once).</p>
		<p>We refer to interim members of this kind as <i>guests</i>,
			and we eventually hope to offer core functionalities under this label,
			but for now, guest role is purely transitional.</p>
	<div class="indentation"> <label>E-mail address:</label><input
         style="width: 150px" type="text" name="email"  value="<?php if(!empty($email)) echo $email;?>"><br>
    <label>Given name:</label><input style="width: 150px" type="text" name="given_name" value="<?php echo $given_name;?>"><br>
    <label>Family name:</label><input style="width: 150px"
            type="text" name="family_name" value="<?php echo $family_name;?>"></div>
        We will also ask your 3 'sponsors' to verify your subject of study (please use subject 2 to write e.g. biochemistry = biology + chemistry).
        <div class="indentation">
       	<label>Subject 1:</label>
          <select name="subject1">
          	<?php $subject=$subject1; include("includes/subject_selector.php") ?>
          </select><br>
		<label>Subject 2 (optional):</label>
		  <select name="subject2">
          	<?php $subject=$subject2; include("includes/subject_selector.php") ?>
          </select>
        </div>
        Finally, please provide a pseudonym, to replace your real name when
        publishing:
        <div class="indentation"> <label>Pseudonym:</label><input style="width: 150px"

            type="text" name="pseudonym" value="<?php echo $pseudonym;?>"></div>
        Obviously, a password is required for login (you can configure social login later):
        <div class="indentation"> <label>Password:</label><input style="width: 150px"

            type="password" name="password" value="<?php echo $password_confirm;?>"><br>
          <label>Password (confirm):</label><input style="width: 150px" type="password" name="password_confirm" value="<?php echo $password_confirm;?>"><br>
        <p style="text-align: right;" class="indentation"><!--<div style="float: left" class="g-recaptcha" data-sitekey="6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI"></div>-->
        	<button type="submit">Send e-mail registration token</button></p>
		</div>
</form>
</div>
<script src='https://www.google.com/recaptcha/api.js'></script>