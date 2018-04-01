<?php
	if(isset($_POST['ORCID_login']))// {$_SESSION['prof']=$_POST['prof_id']; header("Location: index.php");}
		{ header("Location: https://orcid.org/oauth/authorize?client_id=INSERT_ORCIDAPP_LOGIN_HERE&response_type=code&scope=/authenticate&redirect_uri=https://www.myphdidea.org/login_orcid.php"); }
	
	if(isset($_POST['pw_login']))
	{
		if(!empty($_POST['myid']) && !empty($_POST['pword']))
		{
			$myid=test($_POST['myid']);
			$pword=test($_POST['pword']);

			$sql="SELECT user_id, user_password, user_pseudonym, user_hasinstmail, user_email_auth_time FROM user WHERE user_email LIKE '".$conn->real_escape_string($myid)."'";
			$result=$conn->query($sql);
			if($result->num_rows == 1)
			{
				$row=$result->fetch_assoc();
				$user_id=$row['user_id'];
				$_SESSION['isstudent']=!empty($conn->query("SELECT 1 FROM student WHERE student_email_auth IS NOT NULL AND student_user_id='$user_id'")->num_rows);
				if(empty($_SESSION['isstudent']))
					$row_auth=$conn->query("SELECT COUNT(*) AS auth_nb, s.student_institution FROM student s JOIN guest g ON (s.student_auth_guest=g.guest_id) WHERE guest_user='$user_id' GROUP BY s.student_institution ORDER BY auth_nb DESC")->fetch_assoc();

//				if($user_id!=1 && $conn->query("SELECT 1 FROM student WHERE student_email_auth IS NOT NULL AND student_verdict_summary='1'")->num_rows < 100)
//	 				$login_err="Less than 100 registered students, please wait (or sign up friends!)";
				if(empty($row['user_email_auth_time']))
					$login_err="Email record found but not yet authenticated apparently.";
				else if(crypt($pword,$row['user_pseudonym'])==$row['user_password'])
				{
					//AUTHENTICATE GUEST USER
/*					if(empty($_SESSION['isstudent']) && !empty($row_auth['auth_nb']) && $row_auth['auth_nb'] == 3)
					{
						$conn->query("INSERT INTO student (student_user_id, student_institution, student_givenname, student_familyname, student_email_auth, student_sendto_instmail, student_time_created)
							SELECT '$user_id','".$row_auth['student_institution']."',guest_givenname,guest_familyname,NOW(),FALSE,NOW() FROM guest WHERE guest_user='$user_id'");
						$_SESSION['isstudent']=TRUE;
					}
					else*/if(empty($_SESSION['isstudent']) && !empty($row_auth['auth_nb']) && $row_auth['auth_nb'] < 3)
						$login_err="Only ".$row_auth['auth_nb']." sponsors signed up need 3!";
					elseif(empty($_SESSION['isstudent']))
						$login_err="No student account or not authenticated yet!";
					elseif($conn->query("SELECT 1 FROM student WHERE student_user_id='$user_id' AND student_verdict_summary='1'")->num_rows==0)
						$login_err="Account not authenticated!";

					//$conn->real_escape_string(test($_POST['user_id']));
					//CHECK FIRST NUMBER OF MINUS POINTS
					if(!empty($login_err)) {}
					elseif($conn->query("SELECT 1 FROM user WHERE user_rank=0 AND user_id='$user_id'")->num_rows
						|| $conn->query("SELECT 1 FROM user WHERE user_rank=1 AND user_lastblock + INTERVAL 2 WEEK < NOW() AND user_id='$user_id'")->num_rows
						|| $conn->query("SELECT 1 FROM user WHERE user_rank=2 AND user_lastblock + INTERVAL 2 MONTH < NOW() AND user_id='$user_id'")->num_rows)
					{
						$_SESSION['user']=$user_id;

						$conn->query("UPDATE user SET user_time_lastaccess=NOW() WHERE user_id='".$_SESSION['user']."'");
						if(isset($_SESSION['after_login']))
						{
							header('Location: '.$_SESSION['after_login']);
						}
						else header('Location: index.php');
					}
					elseif($conn->query("SELECT 1 FROM user WHERE user_rank=1 AND user_lastblock + INTERVAL 2 WEEK > NOW() AND user_id='$user_id'")->num_rows)
						$login_err="Blocked for 2 weeks because points too low.";
					elseif($conn->query("SELECT 1 FROM user WHERE user_rank=2 AND user_lastblock + INTERVAL 2 MONTH > NOW() AND user_id='$user_id'")->num_rows)
						$login_err="Blocked for 2 months because points too low.";
					else $login_err="It seems your account has been blocked permanently.";
				}
				else
					$login_err="Wrong password (or username).";
			}
			else
			{
				//LOOK FOR STUDENT EMAIL
				$sql="SELECT student_user_id, student_email_auth, student_verdict_summary FROM student WHERE student_institution_email LIKE '".$conn->real_escape_string($myid)."'";
				$result=$conn->query($sql);
				if($result->num_rows == 1)
				{
					$row=$result->fetch_assoc();
					if(empty($row['student_email_auth']))
						$login_err="Email record found but not yet authenticated apparently.";
					else if($row['student_verdict_summary']==NULL || $row['student_verdict_summary']==FALSE)
						$login_err="Apparently still awaiting volunteer verdict ...";
					else//FOUND AUTHENTICATED EMAIL -> ASK PASSWORD
					{
						$sql="SELECT user_id, user_password, user_pseudonym FROM user WHERE user_id LIKE '".$row['student_user_id']."'";
						$result=$conn->query($sql);
						$row=$result->fetch_assoc();
						if($row['user_password']==crypt($pword,$row['user_pseudonym']))
						{
							$user_id=$row['user_id'];
							/*if($user_id!=1 && $conn->query("SELECT 1 FROM student WHERE student_email_auth IS NOT NULL AND student_verdict_summary='1'")->num_rows < 100)
	 							$login_err="Less than 100 registered students, please wait (or sign up friends!)";
							else*/if($conn->query("SELECT 1 FROM user WHERE user_rank=0 AND user_id='$user_id'")->num_rows
								|| $conn->query("SELECT 1 FROM user WHERE user_rank=1 AND user_lastblock + INTERVAL 2 WEEK < NOW() AND user_id='$user_id'")->num_rows
								|| $conn->query("SELECT 1 FROM user WHERE user_rank=2 AND user_lastblock + INTERVAL 2 MONTH < NOW() AND user_id='$user_id'")->num_rows)
							{
								$_SESSION['user']=$row['user_id'];
								$_SESSION['isstudent']=TRUE;
								$conn->query("UPDATE user SET user_time_lastaccess=NOW() WHERE user_id='".$_SESSION['user']."'");
								if(isset($_SESSION['after_login']))
								{
									header('Location: '.$_SESSION['after_login']);
								}
								else header('Location: index.php');
							}
							elseif($conn->query("SELECT 1 FROM user WHERE user_rank=1 AND user_lastblock + INTERVAL 2 WEEK > NOW() AND user_id='$user_id'")->num_rows)
								$login_err="Blocked for 2 weeks because points too low.";
							elseif($conn->query("SELECT 1 FROM user WHERE user_rank=2 AND user_lastblock + INTERVAL 2 MONTH > NOW() AND user_id='$user_id'")->num_rows)
								$login_err="Blocked for 2 months because points too low.";
							else $login_err="It seems your account has been blocked permanently.";
						}
						else
							$login_err='Wrong password (or studentname).';
					}
				}
				else $login_err='Wrong password or email.';
			}
//			$conn->close();
		}
		else $login_err="Please enter login details";
	}
	else if(isset($_POST['social_fb']))
/*		$fb = new Facebook\Facebook(['app_id' => '{app-id}',
		'app_secret' => '{app-secret}',
		'default_graph_version' => 'v2.2',]);
 
		$helper = $fb->getRedirectLoginHelper();
 
		$permissions = []; // Optional information that your app can access, such as 'email'
		$loginUrl = $helper->getLoginUrl('https://example.com/fb-callback.php', $permissions);*/
		header("Location: https://www.facebook.com/v2.9/dialog/oauth?client_id=INSERT_FBAPP_LOGIN_HERE&redirect_uri=https://www.myphdidea.org/login_facebook.php");
	elseif(isset($_POST['social_g']))
		header('Location: https://accounts.google.com/o/oauth2/v2/auth?scope=profile&access_type=offline&include_granted_scopes=true&state=state_parameter_passthrough_value&redirect_uri=https%3A%2F%2Fwww.myphdidea.org%2Flogin_google.php&response_type=code&client_id=INSERT_GOOGLEAPP_LOGIN_HERE.apps.googleusercontent.com');

?>
      <div class="leftmargin">
        <div id="loginprompt" class="widgets">
          <div style="text-align: center; padding-top: 15px; padding-bottom: 15px">
            <b>Access your workdesk</b></div>
          <?php if(isset($login_err)) echo '<div style="color: red; font-size: small; text-align: center">'.$login_err.'</div><br>'?>
          <form method="post" action="">
          Student/guest ID: <input autocomplete="off" name="myid" placeholder="Your email" <?php if(isset($_POST['myid'])) echo 'value='.$_POST['myid']?>
            type="text"> Password: <input autocomplete="off" name="pword" placeholder="Or choose &quot;social login&quot;"
            type="password"> <!--<button name="social_g"> google </button><button name="social_fb"> fb </button>-->
          <div style="padding-top: 5px; padding-bottom: 3px">
          <div style="text-align: center; padding: 3px; width: 105px; float: left"> <a style="font-size: small; vertical-align: text-top"

              href="index.php?register=forgotpw">Forgot password?</a> </div>

          <button name="pw_login">Validate</button></div>
          </form><hr style="border-width: 0px; height: 1px; color: #1ae61a; background-color: #1ae61a; margin-left: 5px; margin-right: 5px">
          	<form method="post" action="">
          <div style="text-align: center; padding-bottom: 5px"><button name="social_g"> google </button><!--<button name="social_fb"> fb </button>-->
        </div></form></div>
        <form method="post" action="">
        <div id="loginprompt" class="widgets">
          <div style="text-align: center; padding-top: 15px; padding-bottom: 15px">
            <b>Researcher login</b></div>
            <?php if(!empty($_SESSION['orcid'])) { echo '<div style="text-align: center; color: red; font-size: smaller">'.$_SESSION['orcid']."<br> not in database!</div><br>"; unset($_SESSION['orcid']);} ?>
<!--          Researcher ID: <input name="prof_id" placeholder="Email or ORCID"
            type="text"> --><button style="display: block; margin: 0px auto; margin-top: 0px; margin-bottom: 10px; float: center;"
            name="ORCID_login">Go to ORCID</button> </div></form>
        <div id="registration" class="widgets" style="border-color: red">
          <div style="text-align: center; padding-top: 15px; padding-bottom: 15px">
            <b>Registration</b></div>
          Not yet a member? Choose account type: <br>
          <form method="get" action="">
          <input style="margin-top: 10px" value="student" name="register" type="radio">Student<br>
          <input value="prof" name="register" type="radio">Researcher<br>
          <input style="margin-bottom: 10px" value="guest" name="register" type="radio">Guest<br>
          <button type="submit" style="display: block; margin: 0px auto; margin-top: 5px; margin-bottom: 5px; float: center;"            name="Registration">Start registration</button>

          <div class="ifstudent"> Note: Student account requires institutional
            email. </div>
          <div class="ifprof"> Note: Researcher account requires ORCID. </div>
          </form>
        </div><br>
		<a href="https://demo.myphdidea.org"><img src="images/logo_demo_small.png"></a>
      </div>