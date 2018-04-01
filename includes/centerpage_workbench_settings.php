<?php
$error_msg="";
if(isset($_SESSION['user']) && isset($_SESSION['isstudent']))
{
	$sql="SELECT s.student_givenname, s.student_institution_email, s.student_email_auth, s.student_sendto_instmail, s.student_auth_guest, u.user_password, s.student_institution,
		u.user_email, u.user_email_new, u.user_email_auth_time, u.user_pseudonym, u.user_turnoff_notifications, u.user_forgotpw_question, u.user_forgotpw_answer,
		u.user_sociallogin_google, u.user_sociallogin_fb
		FROM student s JOIN user u ON (s.student_user_id=u.user_id) WHERE u.user_id='".$_SESSION['user']."'";
	$row=$conn->query($sql)->fetch_assoc();
	$inst_mail=$row['student_institution_email'];
	$already_auth_guest=$conn->query("SELECT 1 FROM student s1 JOIN student s2 ON (s1.student_institution=s2.student_institution)
		JOIN guest g ON (s1.student_user_id=g.guest_user)
		WHERE s1.student_initauth_verdict IS NULL AND g.guest_id='".$row['student_auth_guest']."' AND s2.student_user_id='".$_SESSION['user']."'")->num_rows > 0;
	if(isset($_POST['savesettings']))
	{
		$priv_mail=test($_POST['priv_mail']);
		if(isset($_POST['whichmail']))
			$sendto_instmail=test($_POST['whichmail'])!="privmail";
		else $sendto_instmail=$row['student_sendto_instmail'];
		if(!empty($_POST['auth_guest'])) $auth_guest=$conn->real_escape_string(test($_POST['auth_guest'])); else $auth_guest="";
		$turnoff_notif=test($_POST['freq_emails']);
		$user_pseudonym=test($_POST['pseudonym']);
		$fpw_question=test($_POST['forgotpw_question']);
		$fpw_answer=test($_POST['forgotpw_answer']);
		if(!empty($_POST['google_id'])) $google=test($_POST['google_id']);
		if(!empty($_POST['fb_id'])) $facebook=test($_POST['fb_id']);
		if(!empty($_POST['subject1'])) $subject1=$conn->real_escape_string(test($_POST['subject1'])); else $subject1=0;
		if(!empty($_POST['subject2'])) $subject2=$conn->real_escape_string(test($_POST['subject2'])); else $subject2=0;
		
		if(!empty($google)&& $row['user_sociallogin_google']!=$google && (empty($_SESSION['google']) || $_SESSION['google']!=$google))
			$error_msg=$error_msg."Please confirm google ID by logging in!<br>";
		if(!empty($facebook)&& $row['user_sociallogin_fb']!=$facebook && (empty($_SESSION['facebook']) || $_SESSION['facebook']!=$facebook))
			$error_msg=$error_msg."Please confirm facebook ID by logging in!<br>";
		
//		if(!strpos($user_pseudonym,'guest') && (!empty($_POST['forgotpw_question']) || !empty($_POST['forgotpw_answer'])))
//				$error_msg=$error_msg."Cannot set forgotten password question for trial account!<br>";
		
		if($turnoff_notif < 2 && empty($_POST['confirm_turnoff_notif']))
			$error_msg=$error_msg.'Minimum level of 2 recommended for receiving task notifications please confirm:<input type="checkbox" name="confirm_turnoff_notif"><br>';
		if(empty($_POST['pw_confirm']))
			$error_msg=$error_msg."Please re-enter password!<br>";
		elseif(crypt($_POST['pw_confirm'],$row['user_pseudonym'])!=$row['user_password'])
			$error_msg=$error_msg."Wrong password!<br>";
		elseif($user_pseudonym!=$row['user_pseudonym'] && !empty($user_pseudonym) && $conn->query("SELECT 1 FROM user WHERE user_pseudonym LIKE '$user_pseudonym'")->num_rows > 0)
			$error_msg=$error_msg."Pseudonym already taken!<br>";
		elseif($priv_mail!=$row['user_email'] && !empty($priv_mail) && !filter_var($priv_mail, FILTER_VALIDATE_EMAIL))
			$error_msg=$error_msg."Not a valid e-mail format!<br>";
		elseif($priv_mail!=$row['user_email'] && !empty($priv_mail) && ($conn->query("SELECT 1 FROM user WHERE user_email LIKE '$priv_mail' AND user_email_auth_time IS NOT NULL")->num_rows > 0
			|| $conn->query("SELECT 1 FROM student WHERE student_institution_email LIKE '$priv_mail' AND student_email_auth IS NOT NULL")->num_rows > 0 
			|| $conn->query("SELECT 1 FROM autoedit WHERE autoedit_email LIKE '$priv_mail' AND autoedit_email_auth IS NOT NULL")->num_rows > 0))
			$error_msg=$error_msg."E-mail seems to be already registered!<br>";
		elseif(!empty($_POST['newpw']) && $_POST['newpw']!=$_POST['newpw_confirm'])	
			$error_msg=$error_msg."New password mismatch!<br>";
		elseif(!empty($_POST['newpw']) && strlen($_POST['newpw']) < 6)
			$error_msg=$error_msg."Minimum 6 characters for password please!<br>";
		elseif((empty($fpw_question) && !empty($fpw_answer)) || (!empty($fpw_question) && empty($fpw_answer)))
			$error_msg=$error_msg."Must have <i>both</i> security question and answer!<br>";
		elseif($conn->query("SELECT 1 FROM guest WHERE guest_key='$auth_guest' AND guest_id!='".$row['student_auth_guest']."'")->num_rows > 0 && $conn->query("SELECT 1 FROM student s JOIN guest g ON (s.student_user_id=g.guest_user) WHERE g.guest_key='$auth_guest'")->num_rows > 0)
			$error_msg=$error_msg."Student already authenticated!<br>";
		elseif($conn->query("SELECT 1 FROM guest g JOIN user u ON (u.user_id=g.guest_user) WHERE guest_key='$auth_guest' AND guest_id!='".$row['student_auth_guest']."' AND u.user_subject1!='$subject1' OR (u.user_subject2 IS NOT NULL AND u.user_subject2!='$subject2')")->num_rows > 0)
			$error_msg=$error_msg."Academic subject for guest wrong?";
/*		elseif($conn->query("SELECT 1 FROM student s JOIN guest g ON (s.student_auth_guest=g.guest_id) WHERE g.guest_key='$auth_guest' AND s.student_institution='".$row['student_institution']."'")->num_rows > 2)
			$error_msg=$error_msg."Already enough students for authenticating this guest!<br>";*/
		elseif($already_auth_guest && $conn->query("SELECT 1 FROM guest WHERE guest_key='$auth_guest' AND guest_id!='".$row['student_auth_guest']."'")->num_rows > 0)
			$error_msg=$error_msg."You have now authenticated a guest and cannot authenticate another one!";
		elseif(empty($_POST['confirm_auth_name']) && $conn->query("SELECT 1 FROM guest WHERE guest_key='$auth_guest' AND guest_id!='".$row['student_auth_guest']."'")->num_rows > 0)
		{
			$row_guest=$conn->query("SELECT guest_givenname, guest_familyname FROM guest WHERE guest_key='$auth_guest'")->fetch_assoc();
			if(empty($_POST['confirm_authguest']))
				$error_msg=$error_msg."Please tick authenticate guest box!<br>";
			else $error_msg=$error_msg."Authenticate ".substr($row_guest['guest_givenname'],0,1).". ".substr($row_guest['guest_familyname'],0,1).'.? <input type="checkbox" name="confirm_auth_name">';
		}
		elseif(!empty($_POST['confirm_auth_name']) && empty($_POST['confirm_authguest']))
			$error_msg=$error_msg."Please tick authenticate guest box!<br>";
		elseif(empty($error_msg))
		{
			if(!empty($_POST['google_id']))
				$google="'".$conn->real_escape_string(test($_POST['google_id']))."'";
			else $google="NULL";
			if(!empty($_POST['fb_id']))
				$facebook="'".$conn->real_escape_string(test($_POST['fb_id']))."'";
			else $facebook="NULL";
						
			$priv_mail=$conn->real_escape_string(test($_POST['priv_mail']));
//			$sendto_instmail=$conn->real_escape_string(test($_POST['whichmail']))!="privmail";
			if(!empty($_POST['auth_guest']))
			{
				$row_guest=$conn->query("SELECT guest_id FROM guest WHERE guest_key='".$conn->real_escape_string(test($_POST['auth_guest']))."'")->fetch_assoc();
				if(!empty($row_guest['guest_id']))
					$auth_guest="'".$row_guest['guest_id']."'";
				else $auth_guest="NULL";
			}
			else $auth_guest="NULL";
			$turnoff_notif=$conn->real_escape_string(test($_POST['freq_emails']));
			$user_pseudonym=$conn->real_escape_string(test($_POST['pseudonym']));
			if(!empty($_POST['forgotpw_question']))
				$fpw_question="'".$conn->real_escape_string(test($_POST['forgotpw_question']))."'";
			else $fpw_question="NULL";
			if(!empty($_POST['forgotpw_answer']))
				$fpw_answer="'".$conn->real_escape_string(test($_POST['forgotpw_answer']))."'";
			else $fpw_answer="NULL";
			if(!empty($_POST['newpw'])) $newpw=$conn->real_escape_string(test($_POST['newpw']));

			$conn->query("UPDATE user SET user_turnoff_notifications='$turnoff_notif', user_forgotpw_question={$fpw_question}, user_forgotpw_answer={$fpw_answer},
				user_sociallogin_google={$google}, user_sociallogin_fb={$facebook} WHERE user_id='".$_SESSION['user']."'");
			if(!$already_auth_guest) $conn->query("UPDATE student SET student_auth_guest=$auth_guest, student_sendto_instmail='$sendto_instmail' WHERE student_user_id='".$_SESSION['user']."'");
			
			if(!empty($_POST['auth_guest']) && $row['student_auth_guest']!=$row_guest['guest_id']
				&& $conn->query("SELECT 1 FROM student s JOIN guest g ON (s.student_auth_guest=g.guest_id) WHERE g.guest_id=$auth_guest AND s.student_institution='".$row['student_institution']."'")->num_rows == 3)
			{
				$conn->query("INSERT INTO student (student_user_id, student_institution, student_givenname, student_familyname, student_email_auth, student_sendto_instmail, student_time_created, student_verdict_summary)
					SELECT guest_user,'".$row['student_institution']."',guest_givenname,guest_familyname,NOW(),FALSE,NOW(),TRUE FROM guest WHERE guest_id=$auth_guest");
				
				$row_guest=$conn->query("SELECT u.user_email, g.guest_givenname FROM user u JOIN guest g ON (u.user_id=g.guest_user) WHERE guest_id=$auth_guest")->fetch_assoc();
				$conn->query("UPDATE guest SET guest_key=NULL WHERE guest_id=$auth_guest");
				send_mail($row_guest['user_email'],'Authentication success!','',"Dear ".$row_guest['guest_givenname'].",\n\n".
					"Your 3 sponsors have successfully verified your account and you should now be able to login.\n\nThe myphdidea team",'');
			}
				
			if($user_pseudonym!=$row['user_pseudonym'] && !empty($user_pseudonym))
			{
				$update_pw=crypt($_POST['pw_confirm'],$user_pseudonym);
				$conn->query("UPDATE user SET user_pseudonym='$user_pseudonym', user_password='$update_pw' WHERE user_id='".$_SESSION['user']."'");
				$row['user_pseudonym']=$user_pseudonym;
			}
			if(isset($newpw))
			{
				$update_pw=crypt($newpw,$row['user_pseudonym']);
				$conn->query("UPDATE user SET user_password='$update_pw' WHERE user_id='".$_SESSION['user']."'");
			}
			if($priv_mail!=$row['user_email'] && !empty($priv_mail))
			{
				$private_email_token=md5( rand(0,10000000) );
				send_mail($priv_mail,'Confirm your credentials','https://www.myphdidea.org/index.php?confirm=verify_user&verify='.$private_email_token,
					"Dear ".$row['student_givenname'].",\n\n".
					"You have registered this auxiliary (recovery) mail to myphdidea.org, in order to confirm it,
					 please click on or copy-paste the link below:\n\n","\n\nPlease also check whether you have chosen the correct address for receiving e-mails.\n\n
					 The myphdidea team");
				$private_email_token=$conn->real_escape_string($private_email_token);
				if(empty($row['user_email']) || is_null($row['user_email_auth_time']))
					$conn->query("UPDATE user SET user_email='$priv_mail', user_email_token='$private_email_token', user_email_token_time=NOW() WHERE user_id='".$_SESSION['user']."'");
				else $conn->query("UPDATE user SET user_email_new='$priv_mail', user_email_token='$private_email_token', user_email_token_time=NOW() WHERE user_id='".$_SESSION['user']."'");

				header("Location: index.php?confirm=register_student");
			}
			else header("Location: index.php");
		}
	}
	elseif(isset($_POST['savesettings']) && empty($_POST['pw_confirm']))
		$error_msg=$error_msg."Please re-enter password!<br>";
	elseif(isset($_POST['savesettings']))
		$error_msg=$error_msg."Wrong password!<br>";
	elseif(isset($_POST['social_g']))
		header('Location: https://accounts.google.com/o/oauth2/v2/auth?scope=profile&access_type=offline&include_granted_scopes=true&state=state_parameter_passthrough_value&redirect_uri=https%3A%2F%2Fwww.myphdidea.org%2Flogin_google.php&response_type=code&client_id=INSERT_GOOGLEAPP_LOGIN_HERE.apps.googleusercontent.com');
	else if(isset($_POST['social_fb']))
		header("Location: https://www.facebook.com/v2.9/dialog/oauth?client_id=INSERT_FBAPP_LOGIN_HERE&redirect_uri=https://www.myphdidea.org/login_facebook.php");
	else
	{
		if(!empty($row['user_email_new']))
			$priv_mail=$row['user_email_new'];
		else $priv_mail=$row['user_email'];
		$sendto_instmail=$row['student_sendto_instmail'];
		$user_pseudonym=$row['user_pseudonym'];
		if(!empty($row['student_auth_guest']))
		{
			$row2=$conn->query("SELECT g.guest_key, u.user_subject1, u.user_subject2 FROM guest g JOIN user u ON (g.guest_user=u.user_id) WHERE g.guest_id='".$row['student_auth_guest']."'")->fetch_assoc();
			if(!empty($row2['guest_key']))
			{
				$auth_guest=$row2['guest_key'];
				$subject1=$row2['user_subject1'];
				$subject2=$row2['user_subject2'];
			}
		}
		$turnoff_notif=$row['user_turnoff_notifications'];
		$fpw_question=$row['user_forgotpw_question'];
		$fpw_answer=$row['user_forgotpw_answer'];
		$google=$row['user_sociallogin_google'];
		$facebook=$row['user_sociallogin_fb'];
	}	
}
else echo "Please login!";
?>
      <div id="centerpage">
      	<form method="post" action="">
        <h2>Account settings</h2>
        <?php if(!empty($error_msg)) echo '<div style="color: red; text-align: center">'.$error_msg.'</div><br>';?>
        <div class="indentation"> <label>Private e-mail address:</label><input            style="width: 150px" name="priv_mail" type="text" <?php if(!empty($priv_mail)) echo 'value="'.$priv_mail.'"'; ?> ></div>
        Choose which address to use for sending notifications:<br>
        <p class="indentation"> <input value="privmail" name="whichmail" type="radio" <?php if(empty($sendto_instmail)) echo 'checked'; ?> <?php if(is_null($row['user_email_auth_time']) || is_null($row['student_email_auth'])) echo 'disabled'; ?>>Private
          e-mail: <?php if(!empty($row['user_email']) && !is_null($row['user_email_auth_time'])) echo $row['user_email'].' (recommended)'; else echo '<i>Not yet authenticated.</i>' ?><br>
          <input value="instmail" name="whichmail" type="radio" <?php if(!empty($sendto_instmail)) echo 'checked'; ?> <?php if(is_null($row['user_email_auth_time']) || is_null($row['student_email_auth'])) echo 'disabled'; ?> >Institutional
          e-mail: <?php if(!empty($inst_mail)) echo $inst_mail; ?><br>
        </p>
        Choose frequency of notifications (dialogue, tasks, proposals etc.):<br>
        <div class="indentation"> <label>E-mail frequency (<?php echo $row['user_turnoff_notifications'] ?>):</label><input name="freq_emails"
            min="0" max="5" value="<?php if(isset($turnoff_notif)) echo $turnoff_notif; ?>" type="range"> </div>
        You can also enable social network login (after getting your ID, please hit 'save'):<br>
        <div class="indentation"> <label>Google ID:</label><input name="google_id" style="width: 150px"            type="text" value="<?php if(!empty($google)) echo $google; elseif(!empty($_SESSION['google'])) echo $_SESSION['google']; ?>" > <button name="social_g"> Go to google </button><br>
		</div>
        <div class="indentation"> <label>facebook ID:</label><input name="fb_id" disabled style="width: 150px"
            type="text" placeholder="Coming soon" value="<?php if(!empty($facebook)) echo $facebook; elseif(!empty($_SESSION['facebook'])) echo $_SESSION['facebook']; ?>" > <button name="social_fb" disabled> Go to fb </button><br>
		</div>
        <h3>Password change</h3>
        <div class="indentation"> <label>New password:</label><input style="width: 150px" type="password" name="newpw"><br>
          <label>New password (confirm):</label><input style="width: 150px" type="password" name="newpw_confirm"><br>
          <br>
        </div>
        Optionally, upon account recovery, you can set a security question to answer:
        <div class="indentation">
          <label>Forgotten password question:</label><input style="width: 200px" name="forgotpw_question"
            type="text" value="<?php if(!empty($fpw_question)) echo $fpw_question; ?>"><br>
          <label>Answer:</label><input style="width: 150px" name="forgotpw_answer" type="text" value="<?php if(!empty($fpw_answer)) echo $fpw_answer; ?>"></div>
        <h3>Pseudonym change</h3>
        <div class="indentation">
          <label>New pseudonym:</label><input style="width: 200px"

            type="text" name="pseudonym" value="<?php if(!empty($user_pseudonym)) echo $user_pseudonym ?>"><br>
        </div>
		<h3> Subject of study </h3>
		<?php 
			$row_subject=$conn->query("SELECT user_subject1, user_subject2 FROM user WHERE user_id='".$_SESSION['user']."'")->fetch_assoc();
			$user_subject1=$row_subject['user_subject1'];
			$user_subject2=$row_subject['user_subject2'];
		?>
        <div class="indentation">
       	<label>Subject 1:</label>
          <select name="subject1" disabled>
          	<?php $subject=$user_subject1; include("includes/subject_selector.php") ?>
          </select><br>
		<label>Subject 2 (optional):</label>
		  <select name="subject2" disabled>
          	<?php $subject=$user_subject2; include("includes/subject_selector.php") ?>
          </select>
        </div>

        <h3>Authenticate guest user</h3>
        Membership of <i>myphdidea.org</i> is normally contingent upon having access to an institutional e-mail account.
        However, another way is through creation of a dummy ('guest') account, which can be upgraded
        to full student status if 3 members of the same institution vouch for it (this
        can be only done once by every student). Please enter the key of the member you wish to authenticate here:
        <div class="indentation">
        	<label>User to authenticate:</label><input style="width: 150px" name="auth_guest" type="text" value="<?php if(!empty($auth_guest)) echo $auth_guest;?>" <?php if(!empty($already_auth_guest)) echo "disabled"; ?> ><br>
			<br>
       		<label>Subject 1:</label>
          		<select name="subject1" style="width: 150px" <?php if(!empty($already_auth_guest)) echo "disabled"; ?> >
          		<?php $subject=$subject1; include("includes/subject_selector.php") ?>
          		</select><br>
			<label>Subject 2 (optional):</label>
		  		<select name="subject2" style="width: 150px" <?php if(!empty($already_auth_guest)) echo "disabled"; ?> >
          		<?php $subject=$subject2; include("includes/subject_selector.php") ?>
      			</select><br><br>

        	<input type="checkbox" name="confirm_authguest" <?php if(!empty($_POST['confirm_authguest'])) echo 'checked'; ?> <?php if(!empty($already_auth_guest)) echo "disabled"; ?> >I hereby confirm that the
        	person who gave me the above <br>key is indeed a recent graduate of my institution.<br>
        </div>
        <hr>
        <div class="indentation"><label>Re-type password:</label><input style="width: 150px"
            type="password" name="pw_confirm"><button name="savesettings" style="float: right">Save
            all changes</button><br>
</div>
        <p style="text-align: right;" class="indentation"></p>
      </form>
      </div>