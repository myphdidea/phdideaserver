<?php
$inst_mail=$family_name=$given_name=$annuary_link=$annuary_instructions=$private_mail=$pseudonym="";
$password=$password_confirm=$forgot_pw_quest=$forgot_pw_answ="";
if($_SERVER['REQUEST_METHOD']=='POST')
{
	$inst_mail=test($_POST['inst_mail']); $family_name=test($_POST['family_name']); $given_name=test($_POST['given_name']);
	$annuary_link=test($_POST['annuary_link']); $private_mail=test($_POST['private_mail']); $pseudonym=test($_POST['pseudonym']);
	$password=test($_POST['password']); $password_confirm=test($_POST['password_confirm']); $annuary_instructions=test($_POST['annuary_instructions']);
	if(!empty($forgot_pw_quest)) $forgot_pw_quest=test($_POST['forgot_pw_quest']);
	if(!empty($forgot_pw_answ)) $forgot_pw_answ=test($_POST['forgot_pw_answ']);
//	if(isset($_POST['socialenable'])) $socialenable=test($_POST['socialenable']);
//	if(isset($_POST['third_year'])) $third_year=test($_POST['third_year']);
	if(isset($_POST['whichmail'])) $whichmail=test($_POST['whichmail']);
	if(isset($_POST['subject1'])) $subject1=$conn->real_escape_string(test($_POST['subject1']));
	if(isset($_POST['subject2'])) $subject2=$conn->real_escape_string(test($_POST['subject2']));
	
	$error_msg="";
	
	if(empty($_POST['inst_mail']))
		$error_msg='Cannot have empty institutional mail.<br>';
	if(empty($_POST['family_name']) || empty($_POST['given_name']))
		$error_msg='Cannot have empty name.<br>';
	if(empty($_POST['annuary_link']))
		$error_msg="Cannot have empty annuary link (be creative!).<br>";
	if(empty($_POST['pseudonym']))
		$error_msg="Cannot have empty pseudonym.";
	if(empty($_POST['password']) || empty($_POST['password_confirm'])) 
		$error_msg='Cannot have empty password.<br>';
	else {
	if(empty($_POST['whichmail']) && empty($_POST['private_mail']))
		$whichmail='inst';
	elseif(empty($_POST['whichmail']) && !empty($_POST['private_mail']))
		$whichmail='private';
	if(!filter_var($inst_mail, FILTER_VALIDATE_EMAIL))
		$error_msg='Institutional mail not a valid format.<br>';
//	if(!empty($private_mail) && !filter_var($private_mail, FILTER_VALIDATE_EMAIL))
//		$error_msg='Institutional mail not a valid format.<br>';
	if($password!=$password_confirm)
		$error_msg=$error_msg.'Typing error in password?<br>';
	if(strlen($password) < 6)
		$error_msg=$error_msg.'At least 6 characters required<br>.';
	if(empty($_POST['forgot_pw_quest']) != empty($_POST['forgot_pw_answ']))
		$error_msg=$error_msg.'Must have both security question and answer.<br>';
	if(empty($subject1))
		$error_msg=$error_msg.'Please choose academic subject or combination thereof.<br>';
	if($whichmail=='private' && (empty($_POST['private_mail']) || !filter_var($private_mail, FILTER_VALIDATE_EMAIL)))
		$error_msg=$error_msg.'Ticked private mail but empty or invalid mail field.<br>';
/*		if(empty($_POST['social_login']) && !empty($_POST['socialenable']))
		$error_msg=$error_msg.'Ticked social login but empty username.<br>';*/
	if (!preg_match("/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i",$annuary_link))
		$error_msg=$error_msg.'Annuary link not a valid URL.<br>';
	}
	if(empty($error_msg))
	{
		list($part1,$part2)=explode('@',$inst_mail);

		$sql = "SELECT institution_id, institution_annuary_base, institution_isuniversity, curl_works FROM institution WHERE institution_emailsuffix LIKE '".$conn->real_escape_string($part2)."' OR '".$conn->real_escape_string($part2)."' LIKE CONCAT('%.',institution_emailsuffix) ORDER BY IF(institution_emailsuffix LIKE '".$conn->real_escape_string($part2)."',0,1)";
		$result = $conn->query($sql);

		if ($result->num_rows > 0)
		{
    		$row = $result->fetch_assoc();
			$inst_id=$row['institution_id'];
			if(empty($row['institution_isuniversity']))
				$error_msg=$error_msg."Institution found but not on recognized university list.<br>";
		}
		else $error_msg=$error_msg."Not a recognized e-mail suffix, please check list of supported universities!<br>";

		$sql = "SELECT 1 FROM user WHERE user_pseudonym LIKE '".$conn->real_escape_string($pseudonym)."'";
		if($conn->query($sql)->num_rows > 0)
			$error_msg=$error_msg."Pseudonym seems to be already taken!";
		$sql = "SELECT 1 FROM user WHERE user_email LIKE \"".$private_mail."\"";
		if($conn->query($sql)->num_rows > 0)
			$error_msg=$error_msg."Private email seems to be already registered.";

		$result_resend=$conn->query("SELECT student_email_token FROM student WHERE student_email_auth IS NULL AND student_institution_email LIKE '".$conn->real_escape_string($inst_mail)."'");
		if($result_resend->num_rows > 0)
		{
			if(isset($_POST['resend_mail'])) 
			{
				$row_resend=$result_resend->fetch_assoc();
				send_mail($inst_mail,'Confirm your credentials','https://www.myphdidea.org/index.php?confirm=verify_student&verify='.$row_resend['student_email_token'],
				"Dear ".$given_name.",\n\n".
				"Welcome to myphdidea.org, in order to confirm your e-mail address, please click on or copy-paste the link below:\n\n",
				"\n\n After confirmation, you will still have to wait for volunteers to check your annuary link before you can use your workdesk.\n\n
				 The myphdidea team");
			}
			else $error_msg='Not yet authenticated resend mail? <input type="checkbox" name="resend_mail"><br>';
		}

		if(empty($error_msg) && function_exists('finfo_open'))
		{
			$finfo = finfo_open(FILEINFO_MIME);
    		if(!empty($_FILES["transcript"]["tmp_name"]))
			{
				if ($_FILES["transcript"]["size"] > 5000000)
    				$error_msg=$error_msg."File too large!<br>";
				elseif(strtolower(pathinfo(test($_FILES["transcript"]["name"]),PATHINFO_EXTENSION))!="pdf")
					$error_msg=$error_msg."File not a pdf file?<br>";
				elseif(strpos(finfo_file($finfo, $_FILES["transcript"]["tmp_name"]),'application/pdf')===false)
					$error_msg=$error_msg."File not a valid pdf file!<br>";
				else
					move_uploaded_file($_FILES["transcript"]["tmp_name"], 'user_data/tmp/'.md5($inst_mail).'_t.pdf');
			}
		}

			
		if(empty($error_msg)  && !empty($annuary_link))
		{
			if(!empty($row["institution_annuary_base"]) && !stripos($annuary_link,$row["institution_annuary_base"]))
			{
				if(!stripos($annuary_link,$part2)) $error_msg='Annuary link and e-mail don\'t seem to belong to same server?<br>';
				else if(empty($_POST['annuary_base_ok']) && empty($_POST['g-recaptcha-response']))
				{
					$error_msg=$error_msg.'Annuary link does not seem to contain suggested domain '.$row["institution_annuary_base"].' please correct or confirm: <input type="checkbox" name="annuary_base_ok"><br>';//echo "id: " . $row["institution_id"]. " - Name: " . $row["institution_annuary_base"]. " " . $row["curl_works"]. "<br>";
				}
			}
			elseif(empty($_POST['annuary_base_ok']) && !stripos($annuary_link,$part2) && empty($_POST['g-recaptcha-response'])) $error_msg=$error_msg.'Annuary and e-mail do not seem to belong to same server? Please confirm: <input type="checkbox" name="annuary_base_ok"><br>';
			
			if(stripos($annuary_link,$part2) || (!empty($row["institution_annuary_base"]) && stripos($annuary_link,$row["institution_annuary_base"])))
				if(!empty($error_msg) || (!empty($row["curl_works"]) && $row["curl_works"]==TRUE))
				{
					$annuary_test=check_annuary(html_entity_decode($annuary_link),$family_name,$part1);
					if(strlen($annuary_test)>0 && empty($_POST['annuary_confirm']) && empty($_POST['g-recaptcha-response']))
						$error_msg=$error_msg.'Could not find your '.$annuary_test.' on annuary page please confirm: <input type="checkbox" name="annuary_confirm"><br>';
				}
		}

		if(empty($error_msg) && !empty($_POST['g-recaptcha-response']))
        {
        	$secret = 'INSERT_GOOGLE_RECAPTCHA_SECRET_HERE';
        	//get verify response data
        	$verifyResponse = curl_get_contents('https://www.google.com/recaptcha/api/siteverify?secret='.$secret.'&response='.$_POST['g-recaptcha-response']);
        	var_dump($verifyResponse);
			$responseData = json_decode($verifyResponse);
        	if(!$responseData->success)
				$error_msg="reCaptcha failed please try again!";
		}
//		else $error_msg="Please click on the reCaptcha box";
				
		//finally, the main block!
		if(empty($error_msg) && !empty($_POST['g-recaptcha-response']))
		{
			$inst_mail=$conn->real_escape_string($inst_mail);
			if($conn->query("SELECT 1 FROM student WHERE student_institution_email LIKE '$inst_mail'")->num_rows > 0
				|| $conn->query("SELECT 1 FROM user WHERE user_email LIKE '$inst_mail'")->num_rows > 0
				|| $conn->query("SELECT 1 FROM autoedit WHERE autoedit_email LIKE '$inst_mail'")->num_rows > 0)
				$error_msg=$error_msg."Institutional mail already taken!<br>";
			else
			{
				//FIRST, CREATE USER TABLE ENTRY
				$pseudonym=$conn->real_escape_string($pseudonym);
				$password=crypt($password,$pseudonym);
				if(!empty($private_mail) && $whichmail=='private')
				{
					$private_email_token=md5( rand(0,10000000) );
					$private_mail=$conn->real_escape_string($private_mail);
					
					if($conn->query("SELECT 1 FROM student WHERE student_institution_email LIKE '$private_mail'")->num_rows > 0
						|| $conn->query("SELECT 1 FROM user WHERE user_email LIKE '$private_mail'")->num_rows > 0
						|| $conn->query("SELECT 1 FROM autoedit WHERE autoedit_email LIKE '$private_mail'")->num_rows > 0)
						$error_msg=$error_msg."Private mail already taken!<br>";
					elseif(!send_mail($private_mail,'Confirm your credentials','https://www.myphdidea.org/index.php?confirm=verify_user&verify='.$private_email_token,
							"Dear ".$given_name.",\n\n".
							"You have registered this auxiliary (recovery) mail to myphdidea.org, in order to confirm it,
							 please click on or copy-paste the link below:\n\n","\n\nNote that a separate mail has been sent for you to confirm your institutional e-mail, and activate your account.\n\n
							 The myphdidea team"))
						$error_msg=$error_msg."Private mail send failed!";

					$private_email_token="'".$conn->real_escape_string($private_email_token)."'";
					$private_mail="'$private_mail'";
				}
				else
				{
					$private_email_token="NULL";
					$private_mail="NULL";
				}
				
				$student_email_token=md5( rand(0,1000) );
				if(empty($error_msg) && send_mail($inst_mail,'Confirm your credentials','https://www.myphdidea.org/index.php?confirm=verify_student&verify='.$student_email_token,
							"Dear ".$given_name.",\n\n".
							"Welcome to myphdidea.org, in order to confirm your e-mail address, please click on or copy-paste the link below:\n\n",
							"\n\n After confirmation, you will still have to wait for volunteers to check your annuary link before you can use your workdesk.\n\n
							 The myphdidea team"))
				{
					//TREAT SOCIAL LOGIN
					$forgot_pw_quest=empty($forgot_pw_quest) ? "NULL" : "'".$conn->real_escape_string($forgot_pw_quest)."'";
					$forgot_pw_answ=empty($forgot_pw_answ) ? "NULL" : "'".$conn->real_escape_string($forgot_pw_answ)."'";
					$subject2=empty($subject2) ? "NULL" : "'".$conn->real_escape_string($subject2)."'";
					$turnoff_notif=!empty($_POST['turnoff_notif']) ? '5' : '2';
					$sql= "INSERT INTO user (user_pseudonym, user_password, user_forgotpw_question, user_forgotpw_answer,
										user_email, user_email_token, user_email_token_time, user_time_created, user_hasinstmail,
										user_subject1, user_subject2,user_pts_misc,user_turnoff_notifications,user_rank)
										VALUES ('$pseudonym', '$password', $forgot_pw_quest, $forgot_pw_answ,
										$private_mail, $private_email_token, NULL, NOW(), TRUE, '$subject1', $subject2,'3','$turnoff_notif','0');";
					if(!$conn->query($sql)) echo "Insert into user failed!";
					$sql="SELECT LAST_INSERT_ID();";
					$user_id=$conn->query($sql)->fetch_assoc();
					$user_id=$user_id['LAST_INSERT_ID()'];

					$student_email_token="'".$conn->real_escape_string($student_email_token)."'";
					$annuary_link=$conn->real_escape_string(test($annuary_link));
					if(!empty($annuary_instructions))
						$annuary_instructions="'".$conn->real_escape_string($annuary_instructions)."'";
					else $annuary_instructions="NULL";
					$given_name=$conn->real_escape_string($given_name);
					$family_name=$conn->real_escape_string($family_name);
				
					//THEN, STUDENT TABLE
					$sql= "INSERT INTO student (student_user_id, student_givenname, student_familyname,
											student_institution_email, student_email_token,
											student_institution, student_annuary_link,student_annuary_instructions)
											VALUES ('$user_id', '$given_name', '$family_name',
											'$inst_mail', $student_email_token,
											'$inst_id', '$annuary_link',$annuary_instructions);";
					if(!$conn->query($sql)) echo "Insert into student failed!";
					else
					{
						$student_id=$conn->query("SELECT LAST_INSERT_ID();")->fetch_assoc();
						$student_id=$student_id['LAST_INSERT_ID()'];
					
						rename('user_data/tmp/'.md5($inst_mail).'_t.pdf','user_data/transcripts/'.$student_id.'.pdf');

						$misc='register_student';
						header('Location: index.php?confirm='.$misc);
					}
				}
				else $error_msg=$error_msg."Send mail failed please try again!<br>";
			}
		}
	}
}


function check_annuary($link, $name, $email)
{
    	$header = array();
    	$header[0] = "Accept: text/xml,application/xml,application/xhtml+xml,";
    	$header[0] .= "text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
    	$header[] =  "Cache-Control: max-age=0";
    	$header[] =  "Connection: keep-alive";
    	$header[] = "Keep-Alive: 300";
    	$header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
    	$header[] = "Accept-Language: en-us,en;q=0.5";
    	$header[] = "Pragma: "; // browsers keep this blank.
		
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.2; en-US; rv:1.8.1.7) Gecko/20070914 Firefox/2.0.0.7');
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  2);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true); 
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		curl_setopt($ch, CURLOPT_URL, str_replace(' ','',$link));
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_ENCODING, "");
		$text = curl_exec($ch);
		curl_close($ch);
		$test1 = stripos($text, $name);
		$test2 = stripos($text, $email);
		if($test1==TRUE && $test2==FALSE) return "email";
		else if($test1==FALSE && $test2==TRUE) return "(sur)name";
		else if($test1==FALSE && $test2==FALSE) return "(sur)name nor email";
		else return "";
}
?>
      <div id="centerpage">
        <h2>Registration (student account)</h2>
        <form method="post" action="" enctype="multipart/form-data">
        <?php 
        	if(!empty($error_msg)) echo '<div style="color: red; text-align: center">'.$error_msg.'</div><br>';
			elseif($_SERVER['REQUEST_METHOD']=='POST') echo '<div style="text-align: center"><div style="display: inline-block" class="g-recaptcha" data-sitekey="INSERT_GOOGLERECAPTCHA_KEY_HERE"></div></div>';
			else echo "<div style=\"text-align: center\"><img title=\"Papers please!\" alt=\"Checkpoint Charlie photo\"
          		src=\"images/checkpoint_charlie.png\"></div>";
        ?>
        As a student member, you will be able to launch campaigns, write
        features, request reviews from professors, and contribute by guiding
        others in the same activities. In order to register, you need
        <ul><li>2 years of study but no PhD yet (Master level recommended)</li><li>an
        e-mail address from a <a href="index.php?page=institutionlist">recognized institution</a>, matching your identity</li></ul> (without
        institutional e-mail, please consider registration as a guest).<br>
        <div class="indentation"> <label>Institutional e-mail address:</label><input            style="width: 150px" type="text" name="inst_mail" placeholder="E.g. p.parker@esu.edu" value="<?php echo $inst_mail;?>"><br>
            <label>Given name:</label><input style="width: 150px" type="text" name="given_name" value="<?php echo $given_name;?>"><br>
          <label>Family name:</label><input style="width: 150px"
            type="text" name="family_name" value="<?php echo $family_name;?>"></div>
        Also, please provide us with a <a href="index.php?page=faq#annuarylink">record on the server</a> of the
        institution that states your name and e-mail address, so we know your identity is not fake.
        Ideally, the record should also show your current year of study
        (volunteers will check the record, to make sure you have completed
        at least 2 years of study, else we may reject your request). Since this part may
        require you to be a little creative, you can also
        enter instructions to the volunteers for finding or interpreting your record.<br>
        <div class="indentation"> <label>Annuary record link:</label><input style="width: 200px"            type="text" name="annuary_link" value="<?php echo $annuary_link;?>"><br>
          <label>Instructions to volunteers:</label><textarea style="width: 200px; height: 50px; vertical-align: middle"
            type="text" name="annuary_instructions"><?php echo $annuary_instructions;?></textarea><br></div>
        In practice, not all institutions have got an annuary, or the annuary may be incomplete, hence 
        you can also upload a supporting pdf file showing that you have passed
        the second year of study, such as an excerpt from your transcripts:<br>
        <div class="indentation">
        <input name="transcript" type="file"><?php if(!empty($inst_mail) && file_exists('user_data/tmp/'.md5($inst_mail).'_t.pdf')) echo " Upload OK!"; ?>
        </div>
        The link and documents should also show your subject of study, and will be examined for overlap with what you enter below (please use subject 2 to write e.g. biochemistry = biology + chemistry).
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
        In addition to your institutional mail, you may want to register a private
        e-mail address, to be used for correspondence once your institutional
        account expires:
        <div class="indentation"> <input value="private" name="whichmail" type="radio" <?php if(isset($whichmail) && $whichmail=="private") echo "checked"?>><label>My
            private e-mail address is:</label><input style="width: 150px" type="text" name="private_mail" placeholder="E.g. blabla@gmail.com" value="<?php echo $private_mail;?>">
          <br>
          <input value="inst" name="whichmail" type="radio" <?php if(isset($whichmail) && $whichmail=="inst") echo "checked"?>>I do not have
          a private e-mail address yet.
		</div>
        Finally, please provide a pseudonym, to replace your real name when
        publishing:
        <div class="indentation"> <label>Pseudonym:</label><input style="width: 150px"
            type="text" name="pseudonym" value="<?php echo $pseudonym;?>"></div>
        Obviously, a password is required for login (you can configure social login later):
        <div class="indentation"> <label>Password:</label><input style="width: 150px"
            type="password" name="password" value="<?php echo $password_confirm;?>"><br>
          <label>Password (confirm):</label><input style="width: 150px" type="password" name="password_confirm" value="<?php echo $password_confirm;?>"><br>
          <br>
<!--          <label>Forgotten password question:</label><input style="width: 200px"
            type="text" name="forgot_pw_quest" placeholder="Your first ... was ... ?" value="<?php echo $forgot_pw_quest;?>"><br>
          <label>Answer:</label><input style="width: 150px" type="text" name="forgot_pw_answ" value="<?php echo $forgot_pw_answ;?>"><br>-->
<!--      <label>Username 'facebook':</label><input style="width: 150px" type="text" name="social_login" value="<?php echo $social_login;?>"><br>
          <input type="checkbox" name="socialenable" <?php if(!empty($socialenable)) echo "checked"?>>Enable social login (currently, only facebook is supported). </div>-->
		<input type="checkbox" name="turnoff_notif" <?php if(!empty($_POST['turnoff_notif']) || $_SERVER['REQUEST_METHOD']!='POST') echo "checked" ?>> Notify me via e-mail about new task proposals (recommended).
		  <p style="text-align: right;" class="indentation">
        	<button type="submit">Send e-mail registration token</button></p>
      </div>
    </form>
   </div>
           	<script src='https://www.google.com/recaptcha/api.js'></script>
