<?php

date_default_timezone_set("Europe/Amsterdam");

$servername = "localhost";
$username = "INSERT_DBSERVER_USERNAME";
$pw_sql = "INSERT_DBSERVER_PASSWORD";

// Create connection
$conn = new mysqli($servername, $username, $pw_sql);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

//$error_msg="";
//$cmpgn_id=$conn->real_escape_string(test($_GET['cmpgn']));

$conn->query("SET NAMES utf8");
//$conn->query("SET time_zone='Europe/Berlin'");
$sql = "USE phdideastore";
$result = $conn->query($sql);
if(empty($result)) echo "Can't use phdideastore?";

include("includes/cron.php");

function test($data) {
  $data = trim($data);
  $data = stripslashes($data);
  $data = htmlspecialchars($data);
  return $data;
}

function create_verdict($conn, $cmpgn_id, $type)
{
	switch($type)
	{
		case 'UPLOAD':
			$inxdays="7 days";
			$subject="upload";
			$witharticle="an upload request";
			$urgency=1;
			break;
		case 'RVW':
			$inxdays="7 days";
			$subject="review";
			$witharticle="a review submission";
			$urgency=1;
			break;
		case 'SEND':
			$inxdays="3 days";
			$subject="send";
			$witharticle="a send request";
			$urgency=2;
			break;
		case 'FTR':
			$inxdays="7 days";
			$subject="feature";
			$witharticle="a feature request";
			$urgency=1;
			break;
	}
	//NOT OKd, DEMAND OK FOR THIS ONE!
	//CREATE TASK, ASSEMBLE VERDICT & NOTIFY MODERATORS
	$sql="INSERT INTO task (task_time_created) VALUES (NOW())";
	$conn->query($sql);
	
	$sql="SELECT LAST_INSERT_ID();";
	$task_id=$conn->query($sql)->fetch_assoc();
	$task_id=$task_id['LAST_INSERT_ID()'];

	if($type!='FTR') $sql="INSERT INTO verdict (verdict_moderators, verdict_task, verdict_type)
			  	SELECT MAX(m.moderators_id), '$task_id', '$type' FROM moderators m
			  	JOIN cmpgn c ON (m.moderators_group=c.cmpgn_moderators_group) WHERE c.cmpgn_id='$cmpgn_id'";
	else $sql="INSERT INTO verdict (verdict_moderators , verdict_task, verdict_type)
					SELECT MAX(m.moderators_id), '$task_id', 'FTR' FROM moderators m JOIN feature f ON (m.moderators_group=f.feature_moderators_group)
					WHERE f.feature_id='$cmpgn_id'";
	$conn->query($sql);

	$sql="SELECT LAST_INSERT_ID();";
	$verdict_id=$conn->query($sql)->fetch_assoc();
	$verdict_id=$verdict_id['LAST_INSERT_ID()'];

	if($type!='FTR') $sql="SELECT m.moderators_id, m.moderators_first_user, m.moderators_second_user, m.moderators_third_user
	  	FROM moderators m JOIN cmpgn c ON (c.cmpgn_moderators_group=m.moderators_group) WHERE c.cmpgn_id='$cmpgn_id'
	  	ORDER BY m.moderators_id DESC";
	else $sql="SELECT m.moderators_id, m.moderators_first_user, m.moderators_second_user, m.moderators_third_user
	  	FROM moderators m JOIN feature f ON (f.feature_moderators_group=m.moderators_group) WHERE f.feature_id='$cmpgn_id'
	  	ORDER BY m.moderators_id DESC";
	$row=$conn->query($sql)->fetch_assoc();
	$student_array=array($row['moderators_first_user'],$row['moderators_second_user'],$row['moderators_third_user']);
	
	//HOW TO HANDLE APOSTATE STUDENTS?
	foreach($student_array as $student_item)
	if(!empty($student_item))
	{
		//IF USER SETTINGS ALLOW, SEND EMAIL
		if($type!='FTR') $link='https://www.myphdidea.org/index.php?workbench='.$subject.'verdict&cmpgn='.$cmpgn_id.'&verdict='.$verdict_id;
		else $link='https://www.myphdidea.org/index.php?workbench=featverdict&cmpgn='.$cmpgn_id.'&verdict='.$verdict_id;
		send_notification($conn, $student_item, 2, "Verdict on ".$subject, "You have been asked to render a verdict on ".$witharticle." as below:\n\n",
			$link,
			"\n\n Please complete this task within the next ".$inxdays.".");
/*		$sql="SELECT s.student_id, u.user_turnoff_notifications, u.user_email, s.student_givenname,
				s.student_institution_email, s.student_sendto_instmail
			    FROM student s JOIN user u
	  			ON (s.student_user_id=u.user_id) WHERE u.user_id='$student_item'";
		$row=$conn->query($sql)->fetch_assoc();

		if($row['student_sendto_instmail'])
			$email_choice=$row['student_institution_email'];
		else $email_choice=$row['user_email'];
		if($row['user_turnoff_notifications'] >=2)
				send_mail($email_choice,$subject,'https://www.myphdidea.org/index.php?workbench=newverdict&cmpgn='.$cmpgn_id.'&verdict='.$verdict_id,"Dear ".$row['student_givenname'].",\n\n".
				"You have been asked to render a verdict on ".$witharticle." request as below:\n\n",'\n\n Please complete this task within the next '.$inxdays.'.\n\n
				 The myphdidea team');*/

		$sql="INSERT INTO taskentrusted (taskentrusted_to,taskentrusted_task,taskentrusted_timestamp, taskentrusted_urgency)
				  SELECT student_id,'$task_id',NOW(),'$urgency' FROM student WHERE student_user_id='$student_item'";
		$conn->query($sql);
	}

	return $verdict_id;
}

function send_notification($conn, $user_id, $threshold, $subject, $text1, $link, $text2)
{
	$sql="SELECT s.student_id, u.user_turnoff_notifications, u.user_email, s.student_givenname,
			s.student_institution_email, s.student_sendto_instmail
		    FROM student s JOIN user u
			ON (s.student_user_id=u.user_id) WHERE u.user_id='$user_id'";
	$row=$conn->query($sql)->fetch_assoc();

	if($row['student_sendto_instmail'])
		$email_choice=$row['student_institution_email'];
	else $email_choice=$row['user_email'];
	$notif_success=TRUE;
	if($row['user_turnoff_notifications'] >= $threshold)
		if(!empty($link))
			$notif_success=send_mail($email_choice,$subject,$link,"Dear ".$row['student_givenname'].",\n\n".
			$text1,$text2."\n\n
			 The myphdidea team");
		else $notif_success=send_mail($email_choice,$subject,$link,"Dear ".$row['student_givenname'].",\n\n".
			$text1."\n\nThe myphdidea team",'');

	$text1=str_replace("\n","<br>",$text1);
	$text2=str_replace("\n","<br>",$text2);
	if(!empty($link))
		$text=$text1.'<a href="'.$link.'">'.$link.'</a>'.$text2;
	else $text=$text1;
	
	if($notif_success)
	{
		$text=$conn->real_escape_string($text);
		$sql="INSERT INTO notification (notification_user, notification_object, notification_text, notification_time,
			notification_urgency) VALUES ('$user_id','$subject','$text',NOW(),'$threshold')";
		$conn->query($sql);
	}
	return $notif_success;//$row['student_id'];
}

function send_profnotif($conn, $prof_id, $threshold, $subject, $text1, $link, $text2)
{
	//SEND MAIL (INCLUDING TO AUTOEDIT)
	$sql="SELECT prof_familyname, prof_email, prof_email_alt
		FROM prof WHERE prof_id='$prof_id'";
	$row=$conn->query($sql)->fetch_assoc();
	$email1=$row['prof_email'];
	$email2=$row['prof_email_alt'];
	$prof_familyname=$row['prof_familyname'];

	$sql="SELECT autoedit_email, autoedit_notification_frequency, autoedit_email_auth
		FROM autoedit WHERE autoedit_prof='$prof_id'";
	$result=$conn->query($sql);
	if($result->num_rows > 0)
	{
		$row=$result->fetch_assoc();
		if(!empty($row['autoedit_email']) && !empty($row['autoedit_email_auth'])
			&& $row['autoedit_email']!=$email1 && $row['autoedit_email']!=$email2)
			$email3=$row['autoedit_email'];
		$notif_freq=$row['autoedit_notification_frequency'];
	}
	else $notif_freq=1;
	//FINALLY, SEND MAILS
	if($notif_freq >= $threshold)
	{
		if(!empty($link))
		{
			$text1_ext="Dear Prof. ".$prof_familyname.",\n\n".$text1;
			$text2_ext=$text2."\n\nThe myphdidea team";
		}
		else
		{
			$text1_ext="Dear Prof. ".$prof_familyname.",\n\n".$text1."\n\nThe myphdidea team";
			$text2_ext="";
		}
		
		if(1 >= $threshold)
		{
			$mail_success=true;
			if(!empty($email1)) $mail_success=$mail_success && send_mail($email1, $subject, $link, $text1_ext, $text2_ext);
			if(!empty($email2)) $mail_success=$mail_success && send_mail($email2, $subject, $link, $text1_ext, $text2_ext);
			if(!empty($email3)) $mail_success=$mail_success && send_mail($email3, $subject, $link, $text1_ext, $text2_ext);
		}
		elseif(!empty($row['autoedit_email']))
			send_mail($row['autoedit_email'], $subject, $link, $text1_ext, $text2_ext);
	}
	
	$text1=str_replace("\n","<br>",$text1);
	$text2=str_replace("\n","<br>",$text2);
	if(!empty($link))
		$text=$text1.'<a href="'.$link.'">'.$link.'</a>'.$text2;
	else $text=$text1;

	$text=$conn->real_escape_string($text);
	$sql="INSERT INTO profnotif (profnotif_prof, profnotif_object, profnotif_text, profnotif_time,
		profnotif_urgency) VALUES ('$prof_id','$subject','$text',NOW(),'$threshold')";
	$conn->query($sql);

	if(isset($mail_success)) return $mail_success; else return true;
}

function send_mail($to, $about, $link, $text1, $text2)
{
//$to=strstr($to,'@','true').'@mailinator.com';

require_once 'includes/PHPMailer/PHPMailerAutoload.php';

$mail = new PHPMailer;

$mail->isSMTP();                                      // Set mailer to use SMTP
$mail->Host = 'mail.myphdidea.org';  // Specify main and backup SMTP servers
$mail->SMTPAuth = true;                               // Enable SMTP authentication
$mail->Username = 'INSERT_MAILSERVER_EMAIL_HERE';                // SMTP username
$mail->Password = 'INSERT_MAILSERVER_PASSWORD_HERE';                           // SMTP password
$mail->SMTPSecure = 'ssl';                            // Enable encryption, 'ssl' also accepted
$mail->Port = 465;

$mail->From = 'donotreply@myphdidea.org';//'donotreply@myphdidea.org';
$mail->FromName = 'myphdidea.org';
$mail->addAddress($to);     // Add a recipient

$mail->WordWrap = 50;                                 // Set word wrap to 50 characters
$mail->isHTML(true);                                  // Set email format to HTML

$mail->Subject = $about;
$mail->AltBody = $text1.$link.$text2;
$text1=str_replace("\n","<br>",$text1);
$text2=str_replace("\n","<br>",$text2);
if(!empty($link))
	$mail->Body    = $text1.'<a href="'.$link.'">'.$link.'</a>'.$text2;
else $mail->Body = $text1;

if(!$mail->send()) {
    echo 'Message could not be sent.';
    echo 'Mailer Error: ' . $mail->ErrorInfo;
	$success=false;
} else {
//    echo 'Message has been sent';
}

/*return true;*/if(isset($success)) return $success; else return true;

}

function prof_label($prof_row/*, $conn*/)
{
	$printname=$prof_row['prof_givenname']." ".$prof_row['prof_familyname'];
	
//	$sql="SELECT 1 FROM autoedit WHERE autoedit_prof='".$prof_row['prof_id']."' AND autoedit_image='TRUE'";
	if(!empty($prof_row['prof_image'])/*$conn->query($sql)->num_rows > 0*/)
		$image_path="user_data/researcher_pictures/".$prof_row['prof_id']."_small.png";
	else $image_path="images/default.png";

	$image_path='<img alt="" src="'.$image_path.'">';
	$image_path='<a href="index.php?prof='.$prof_row['prof_id'].'">'.$image_path.'</a>';
	$image_path='<div class="icon">'.$image_path.'</div>';
	
	return $image_path.$printname.'<br>';
}

function addto_watchlists($conn,$verdict_moderators,$verdict_type,$verdict_task, $makeexclusiveoffers, $limitinitial/*, $avoiduser1, $avoiduser2, $avoiduser3*/)
{
	$row=$conn->query("SELECT student_institution FROM student s JOIN moderators m ON (s.student_user_id=m.moderators_first_user) WHERE moderators_id='$verdict_moderators'")->fetch_assoc(); $avoidinstitution1=$row['student_institution'];
	$row=$conn->query("SELECT student_institution FROM student s JOIN moderators m ON (s.student_user_id=m.moderators_second_user) WHERE moderators_id='$verdict_moderators'")->fetch_assoc(); $avoidinstitution2=$row['student_institution'];
	$row=$conn->query("SELECT student_institution FROM student s JOIN moderators m ON (s.student_user_id=m.moderators_third_user) WHERE moderators_id='$verdict_moderators'")->fetch_assoc(); $avoidinstitution3=$row['student_institution'];

	switch($verdict_type)
	{
		case 'RVW':
		case 'SEND':
		case 'UPLOAD':
			//GET SUBJECTS OF CAMPAIGN OWNER, LOOK FOR SAME SUBJECT
			$sql="SELECT u.user_id, u.user_subject1, u.user_subject2, s.student_institution, c.cmpgn_id, c.cmpgn_visibility_blocked,
				g.moderators_group_hashcode, i.institution_country, c.cmpgn_type_isarchivized FROM user u 
				JOIN cmpgn c ON (c.cmpgn_user=u.user_id)
				JOIN moderators m ON (m.moderators_group=c.cmpgn_moderators_group)
				JOIN moderators_group g ON (g.moderators_group=m.moderators_group)
				JOIN student s ON (s.student_user_id=u.user_id)
				JOIN institution i ON (i.institution_id=s.student_institution)
				WHERE m.moderators_id='$verdict_moderators'";
			$row=$conn->query($sql)->fetch_assoc();
			if($row['cmpgn_visibility_blocked']=='1')
			{
				$conn->query("DELETE FROM watchlist WHERE watchlist_moderators='$verdict_moderators' AND watchlist_enrolled='0'");
				return 0;
			}
			$owner_id=$row['user_id'];
			if(!empty($row['user_subject1']))
				$user_subject1="'".$row['user_subject1']."'";
			else $user_subject1="NULL";
			if(!empty($row['user_subject2']))
				$user_subject2="'".$row['user_subject2']."'";
			else $user_subject2="NULL";
			$student_institution=$row['student_institution'];
			$student_country=$row['institution_country'];
			$moderators_hashcode=$row['moderators_group_hashcode'];

			//FOR MATCHING WITH EXCLUSIVE CAMPAIGNS
			$sql="SELECT r1.resbox_impl_researcher AS res1, r2.resbox_impl_researcher AS res2, r3.resbox_impl_researcher AS res3 FROM pubreg p
				JOIN resbox_impl r1 ON (p.pubreg_resbox1=r1.resbox_impl_id)
				JOIN resbox_impl r2 ON (p.pubreg_resbox2=r2.resbox_impl_id)
				JOIN resbox_impl r3 ON (p.pubreg_resbox3=r3.resbox_impl_id)
				WHERE pubreg_cmpgn='".$row['cmpgn_id']."' ORDER BY r1.resbox_impl_rel_nb DESC, r2.resbox_impl_rel_nb DESC, r3.resbox_impl_rel_nb DESC";
			$owner_pubreg_row=$conn->query($sql)->fetch_assoc();
			
//			$sql2="AND s.student_cmpgn_shadowed_latest IS NULL AND (u.user_subject1={$user_subject1} OR u.user_subject2={$user_subject2} OR u.user_subject1={$user_subject2} OR u.user_subject2={$user_subject1}) ";
//			$sql1="AND s.student_cmpgn_shadowed_latest IS NULL AND ((u.user_subject1={$user_subject1} AND u.user_subject2={$user_subject2}) OR (u.user_subject1={$user_subject2} AND u.user_subject2={$user_subject1}) OR
//				(u.user_subject1={$user_subject1} AND u.user_subject2 IS NULL) OR (u.user_subject1={$user_subject2} AND u.user_subject2 IS NULL)) ";
			$sql2="AND (u.user_subject1={$user_subject1} OR u.user_subject2={$user_subject2} OR u.user_subject1={$user_subject2} OR u.user_subject2={$user_subject1}) ";
			$sql1="AND ((u.user_subject1={$user_subject1} AND u.user_subject2={$user_subject2}) OR (u.user_subject1={$user_subject2} AND u.user_subject2={$user_subject1}) OR
				(u.user_subject1={$user_subject1} AND u.user_subject2 IS NULL) OR (u.user_subject1={$user_subject2} AND u.user_subject2 IS NULL)) ";

			
			if($row['cmpgn_type_isarchivized'])
			{
				$sql_type="mg.moderators_group_type='ARCHV' ";
				$max_proposal_nb=2;
				$makeexclusiveoffers=FALSE;
				if($limitinitial)
//				{
					$sql1="AND EXISTS (SELECT 1 FROM cmpgn WHERE cmpgn_time_launched + INTERVAL 2 MONTH > NOW() AND cmpgn_type_isarchivized='1' AND cmpgn_user=u.user_id) ".$sql1;
//					$sql2="AND EXISTS (SELECT 1 FROM cmpgn WHERE cmpgn_time_launched + INTERVAL 2 MONTH > NOW() AND cmpgn_type_isarchivized='1' AND cmpgn_user=u.user_id) ".$sql2;
//				}
			}
			else
			{
				$sql_type="mg.moderators_group_type='CMPGN' ";
				$max_proposal_nb=4;
				$sql1="AND s.student_cmpgn_shadowed_latest IS NULL ".$sql1;
				$sql2="AND s.student_cmpgn_shadowed_latest IS NULL ".$sql2;
			}
//			$sql_pre="AND (i.institution_country!='$student_country' OR RAND() < 0.5) AND (s.student_cmpgn_own_latest IS NOT NULL OR RAND() < 0.5) ";
			$sql_end="ORDER BY IF(i.institution_country!='$student_country',1,0) DESC, IF(s.student_cmpgn_own_latest IS NOT NULL,1,0) DESC, IF(s.student_taskexcl_cmpgn='1',1,0) DESC, u.user_time_created ASC LIMIT 101";
			break;
		case 'FTR':
			$sql_type="mg.moderators_group_type='FEAT' ";
			$max_proposal_nb=2;
			//GET SUBJECTS OF FEATURE OWNER, LOOK FOR SAME SUBJECT
			$sql="SELECT u.user_id, u.user_subject1, u.user_subject2, s.student_institution,
				g.moderators_group_hashcode, i.institution_country FROM user u
				JOIN student s ON (s.student_user_id=u.user_id)
				JOIN feature f ON (f.feature_student=s.student_id)
				JOIN featuretext ft ON (ft.featuretext_feature=f.feature_id)
				JOIN verdict v ON (ft.featuretext_verdict=v.verdict_id)
				JOIN moderators m ON (m.moderators_id=v.verdict_moderators)
				JOIN moderators_group g ON (g.moderators_group=m.moderators_group)
				JOIN institution i ON (i.institution_id=s.student_institution)
				WHERE m.moderators_id='$verdict_moderators'";
			$row=$conn->query($sql)->fetch_assoc();
			$owner_id=$row['user_id'];
			if(!empty($row['user_subject1']))
				$user_subject1="'".$row['user_subject1']."'";
			else $user_subject1="NULL";
			if(!empty($row['user_subject2']))
				$user_subject2="'".$row['user_subject2']."'";
			else $user_subject2="NULL";
			$student_institution=$row['student_institution'];
			$student_country=$row['institution_country'];
			$moderators_hashcode=$row['moderators_group_hashcode'];
			$sql2="AND s.student_feat_shadowed_latest IS NULL ";
			$sql1="AND s.student_feat_shadowed_latest IS NULL AND (u.user_subject1={$user_subject1} OR u.user_subject2={$user_subject2} OR u.user_subject1={$user_subject2} OR u.user_subject2={$user_subject1}) ";
/*			$sql2="AND s.student_feat_shadowed_latest IS NULL AND (u.user_subject1={$user_subject1} OR u.user_subject2={$user_subject2} OR u.user_subject1={$user_subject2} OR u.user_subject2={$user_subject1}) ";
			$sql1="AND s.student_feat_shadowed_latest IS NULL AND ((u.user_subject1={$user_subject1} AND u.user_subject2={$user_subject2}) OR (u.user_subject1={$user_subject2} AND u.user_subject2={$user_subject1}) OR
				(u.user_subject1={$user_subject1} AND u.user_subject2 IS NULL) OR (u.user_subject1={$user_subject2} AND u.user_subject2 IS NULL)) ";*/
			$sql_end="ORDER BY u.user_time_created ASC LIMIT 101";
			break;
		case 'USER':
			$sql_type="mg.moderators_group_type='USER' ";
			$max_proposal_nb=0;
			//GET SUBJECTS OF CAMPAIGN OWNER, LOOK FOR SAME SUBJECT
			$sql="SELECT u.user_id, u.user_subject1, u.user_subject2, s.student_institution,
				g.moderators_group_hashcode, i.institution_country FROM user u
				JOIN student s ON (s.student_user_id=u.user_id)
				JOIN verdict v ON (s.student_initauth_verdict=v.verdict_id)
				JOIN moderators m ON (m.moderators_id=v.verdict_moderators)
				JOIN moderators_group g ON (g.moderators_group=m.moderators_group)
				JOIN institution i ON (i.institution_id=s.student_institution)
				WHERE m.moderators_id='$verdict_moderators'";
			$row=$conn->query($sql)->fetch_assoc();
			$owner_id=$row['user_id'];
			if(!empty($row['user_subject1']))
				$user_subject1="'".$row['user_subject1']."'";
			else $user_subject1="NULL";
			if(!empty($row['user_subject2']))
				$user_subject2="'".$row['user_subject2']."'";
			else $user_subject2="NULL";
			$student_institution=$row['student_institution'];
			$student_country=$row['institution_country'];
			$moderators_hashcode=$row['moderators_group_hashcode'];
			$sql2=" ";//"AND (u.user_subject1={$user_subject1} OR u.user_subject2={$user_subject2} OR u.user_subject1={$user_subject2} OR u.user_subject2={$user_subject1}) ";
			//$sql1=" ";
//			$sql1="AND (i.institution_country='$student_country' OR RAND() < 0.25) ";
			$sql1=" ";
			if($user_subject1!="NULL")
				$sql1="AND (u.user_subject1!={$user_subject1} OR u.user_subject1 IS NULL) AND (u.user_subject2!={$user_subject1} OR u.user_subject2 IS NULL)".$sql1;
			if($user_subject2!="NULL")
				$sql1="AND (u.user_subject1!={$user_subject2} OR u.user_subject1 IS NULL) AND (u.user_subject2!={$user_subject2} OR u.user_subject2 IS NULL)".$sql1;
			$sql_end="ORDER BY IF(i.institution_country!='$student_country',0,1) DESC, u.user_time_created ASC LIMIT 101";

//			$sql_end="ORDER BY IF(i.institution_country!='$student_country',0,1) DESC, u.user_time_lastaccess DESC LIMIT 101";
			break;
	}

	$sql_begin="SELECT u.user_id, u.user_time_lastaccess, u.user_time_created, s.student_id, i.institution_country,
		s.student_cmpgn_own_latest, s.student_feat_own_latest, s.student_taskexcl_cmpgn, s.student_taskexcl_feat,
		s.student_cmpgn_shadowed_latest, s.student_feat_shadowed_latest, s.student_institution
		FROM user u JOIN student s ON (s.student_user_id=u.user_id)
		JOIN institution i ON (i.institution_id=s.student_institution)
		WHERE (SELECT COUNT(*) FROM moderators_group mg JOIN moderators m ON (mg.moderators_group=m.moderators_group)
		WHERE ".$sql_type."AND EXISTS (SELECT 1 FROM moderators m
		JOIN watchlist w ON (w.watchlist_moderators=m.moderators_id)
		WHERE (m.moderators_first_user IS NULL OR m.moderators_second_user IS NULL OR m.moderators_third_user IS NULL)
		AND w.watchlist_enrolled='0' AND w.watchlist_user=u.user_id AND m.moderators_group=mg.moderators_group)) <= {$max_proposal_nb}
		AND s.student_email_auth IS NOT NULL AND s.student_verdict_summary='1'
		AND s.student_institution!='$student_institution' ";
/*	$sql_begin="SELECT u.user_id, u.user_time_lastaccess, u.user_time_created, s.student_id, i.institution_country,
		s.student_cmpgn_own_latest, s.student_feat_own_latest, s.student_taskexcl_cmpgn, s.student_taskexcl_feat,
		s.student_cmpgn_shadowed_latest, s.student_feat_shadowed_latest, s.student_institution
		FROM user u JOIN student s ON (s.student_user_id=u.user_id)
		JOIN institution i ON (i.institution_id=s.student_institution)
		WHERE NOT EXISTS (SELECT COUNT(*) AS watchlist_nb
		FROM watchlist w JOIN moderators m ON (w.watchlist_moderators=m.moderators_id)
		JOIN moderators_group mg ON (mg.moderators_group=m.moderators_group)
		WHERE (m.moderators_first_user IS NULL OR m.moderators_second_user IS NULL OR m.moderators_third_user IS NULL)
		AND w.watchlist_enrolled='0' AND w.watchlist_user=u.user_id ".$sql_type."
		GROUP BY w.watchlist_user HAVING watchlist_nb > {$max_proposal_nb})
		AND s.student_email_auth IS NOT NULL AND s.student_verdict_summary='1'
		AND s.student_institution!='$student_institution' ";*/
//	echo $sql_begin.$sql1.$sql_end;
	$result=$conn->query($sql_begin.$sql1.$sql_end);
	if($verdict_type != 'USER' && $verdict_type !='FTR') $sql_excl_add="OR (s.student_taskexcl_cmpgn='1' AND u.user_time_created < NOW() - INTERVAL 3 MONTH)"; else $sql_excl_add="";
	$i=0;
	if($result->num_rows < 100 && (!empty($sql2) && $sql2!=" ")) //TRY OTHER OPTION
	{
		$i=0;
		$result=$conn->query($sql_begin.$sql2.$sql_end);
		
		while($result->num_rows > 100 && $limitinitial)
		{
			$result=$conn->query($sql_begin.$sql2."AND u.user_time_lastaccess > NOW() - INTERVAL 3 MONTH
				AND (( (u.user_time_created < NOW() - INTERVAL {$i}+1 MONTH ".$sql_excl_add.")
				AND RIGHT(CONV(u.user_id,10,2),{$i})=RIGHT(CONV({$moderators_hashcode},10,2),{$i})
				) OR (u.user_time_created > NOW() - INTERVAL {$i}+1 MONTH
				AND RIGHT(CONV(u.user_id,10,2),{$i}+1)=RIGHT(CONV({$moderators_hashcode},10,2),{$i}+1))) ".$sql_end);
//				AND RIGHT(CONV(u.user_id,10,4),{$i})=RIGHT(CONV({$moderators_hashcode},10,4),{$i}) ".$sql_end);
			$i++;
		}
	}
	elseif($result->num_rows > 100 && $limitinitial)
	{
		//whittle down using different hashcodes
		$i=0;
		
		do {
			$result=$conn->query($sql_begin.$sql1."AND u.user_time_lastaccess > NOW() - INTERVAL 3 MONTH
				AND (( (u.user_time_created < NOW() - INTERVAL {$i}+1 MONTH ".$sql_excl_add.")
				AND RIGHT(CONV(u.user_id,10,2),{$i})=RIGHT(CONV({$moderators_hashcode},10,2),{$i}) 
				) OR (u.user_time_created > NOW() - INTERVAL {$i}+1 MONTH
				AND RIGHT(CONV(u.user_id,10,2),{$i}+1)=RIGHT(CONV({$moderators_hashcode},10,2),{$i}+1))) ".$sql_end);
			$i++;
		} while($result->num_rows > 100);
	}

	$row_array1=array(); $i=0;
	while(($row=$result->fetch_assoc()) && $i < 20)
	{
		if($row['user_id']==$owner_id || (isset($avoidinstitution1) && $row['student_institution']==$avoidinstitution1)
			|| (isset($avoidinstitution2) && $row['student_institution']==$avoidinstitution2)
			|| (isset($avoidinstitution3) && $row['student_institution']==$avoidinstitution3))
			continue;
		
		if($conn->query("SELECT 1 FROM moderators m1 JOIN moderators m2 ON (m1.moderators_group=m2.moderators_group)
			WHERE (m1.moderators_first_user='".$row['user_id']."' OR m1.moderators_second_user='".$row['user_id']."' OR m1.moderators_third_user='".$row['user_id']."') AND m2.moderators_id='$verdict_moderators'")->num_rows > 0)
			continue;

		//PREVENT FROM DISTRIBUTING TO THOSE WHO HAVE RECENTLY (NOT) COMPLETED VERDICT
/*		if($verdict_type=='USER' && $conn->query("SELECT 1 FROM verdict v
			JOIN moderators m1 ON (v.verdict_moderators=m1.moderators_id)
			JOIN moderators m2 ON (m1.moderators_group=m2.moderators_group)
			WHERE v.verdict_type='USER' AND ((m2.moderators_first_user='".$row['user_id']."' AND m2.moderators_time_joined1 + INTERVAL 1 DAY > NOW())
			OR (m2.moderators_second_user='".$row['user_id']."' AND m2.moderators_time_joined2 + INTERVAL 1 DAY > NOW())
			OR (m2.moderators_third_user='".$row['user_id']."' AND m2.moderators_time_joined3 + INTERVAL 1 DAY > NOW()))
			AND (v.verdict_time1 IS NULL OR v.verdict_time2 IS NULL OR v.verdict_time3 IS NULL)")->num_rows > 0)
			continue;*/
		
		//EXCLUSIVE OFFERS GO IN PRIORITY TO THOSE WHO HAVE GOT THEIR OWN CAMPAIGN ... ?
		//EXCLUSIVITY NOT USED WHEN 'FIRED' FROM JOB

		//CHECK WHETHER ALREADY GIVEN TASK TO SAME (INSTITUTION) BEFORE
		if($makeexclusiveoffers && $conn->query("SELECT 1 FROM taskentrusted t
				JOIN student s1 ON (t.taskentrusted_to=s1.student_id)
				JOIN student s2 ON (s1.student_institution=s2.student_institution)
				WHERE s2.student_id='".$row['student_id']."'
				AND t.taskentrusted_task='$verdict_task'
				AND (t.taskentrusted_completed IS NULL OR t.taskentrusted_completed='1'
				OR s1.student_id=s2.student_id)")->num_rows==0)
		{
			if((($verdict_type=='RVW' || $verdict_type=='UPLOAD' || $verdict_type=='SEND')
				&& !empty($row['student_taskexcl_cmpgn']) /*&& !empty($row['student_cmpgn_own_latest']) && rand (0,1)*/)
				|| ($verdict_type=='FTR' && !empty($row['student_taskexcl_feat'])/* && !empty($row['student_feat_own_latest'])*/))
			{
				unset($dist_problem);
				if($verdict_type!='FTR')
				{
					$sql="SELECT r1.resbox_impl_researcher AS res1, r2.resbox_impl_researcher AS res2, r3.resbox_impl_researcher AS res3 FROM pubreg p
						JOIN resbox_impl r1 ON (p.pubreg_resbox1=r1.resbox_impl_id)
						JOIN resbox_impl r2 ON (p.pubreg_resbox2=r2.resbox_impl_id)
						JOIN resbox_impl r3 ON (p.pubreg_resbox3=r3.resbox_impl_id)
						WHERE p.pubreg_cmpgn IS NOT NULL AND p.pubreg_student='".$row['student_id']."'
						ORDER BY p.pubreg_cmpgn DESC, r1.resbox_impl_rel_nb DESC, r2.resbox_impl_rel_nb DESC, r3.resbox_impl_rel_nb DESC";
					$modcand_pubreg_row=$conn->query($sql)->fetch_assoc();

					if(!empty($modcand_pubreg_row))
					{
						foreach($modcand_pubreg_row as $modcand_item)
						{
							foreach($owner_pubreg_row as $owner_item)
							{
								$distance=virtuoso_dist($owner_item,$modcand_item);
								if($distance <= 4 || is_null($distance))
								{
									$dist_problem=TRUE;
									break;
								}
							}
						}
					}
				}
				
				if(empty($dist_problem) || $verdict_type=='FTR')
				{
					$nb_required=3-!empty($avoidinstitution1)-!empty($avoidinstitution2)-!empty($avoidinstitution3);

					if((int)$makeexclusiveoffers==2)
						$nb_required=1;
					//CHECK WITH MATCHING ENGINE
					if(!isset($priority1)) $priority1=$row;
					elseif($nb_required >= 2 && !isset($priority2) && isset($priority1))
						if($conn->query("SELECT 1 FROM student s1
							JOIN student s2 ON (s1.student_institution=s2.student_institution)
							WHERE s1.student_id='".$row['student_id']."'
							AND s2.student_id='".$priority1['student_id']."'")->num_rows==0) $priority2=$row;
					elseif($nb_required >= 3 && !isset($priority3) && isset($priority2) && isset($priority1))
						if($conn->query("SELECT 1 FROM student s1
							JOIN student s2 ON (s1.student_institution=s2.student_institution)
							WHERE s1.student_id='".$row['student_id']."'
							AND (s2.student_id='".$priority1['student_id']."'
							OR s2.student_id='".$priority2['student_id']."')")->num_rows==0) $priority3=$row;
				}

				if(!isset($priority1_test)) $priority1_test=$row;
				elseif($nb_required >= 2 && !isset($priority2_test) && isset($priority1_test))
					if($conn->query("SELECT 1 FROM student s1
						JOIN student s2 ON (s1.student_institution=s2.student_institution)
						WHERE s1.student_id='".$row['student_id']."'
						AND s2.student_id='".$priority1_test['student_id']."'")->num_rows==0) $priority2_test=$row;
				elseif($nb_required >= 3 && !isset($priority3_test) && isset($priority2_test) && isset($priority1_test))
					if($conn->query("SELECT 1 FROM student s1
						JOIN student s2 ON (s1.student_institution=s2.student_institution)
						WHERE s1.student_id='".$row['student_id']."'
						AND (s2.student_id='".$priority1_test['student_id']."'
						OR s2.student_id='".$priority2_test['student_id']."')")->num_rows==0) $priority3_test=$row;
			}
		}
		
		//MATCHING ENGINE: ELIDE TOO CLOSE, PRIORITY IN ARRAY2, REST IN ARRAY1
		$row_array1[]=$row;
		$i++;
//		$row_array2[]=;
	}

	if(!isset($priority1) && isset($priority1_test)) $priority1=$priority1_test;
	if(!isset($priority2) && isset($priority2_test)
		&& $conn->query("SELECT 1 FROM student s1
		JOIN student s2 ON (s1.student_institution=s2.student_institution)
		WHERE s1.student_id='".$priority2_test['student_id']."'
		AND s2.student_id='".$priority1['student_id']."'")->num_rows==0) $priority2=$priority2_test;
	if(!isset($priority3) && isset($priority3_test)
		&& $conn->query("SELECT 1 FROM student s1
		JOIN student s2 ON (s1.student_institution=s2.student_institution)
		WHERE s1.student_id='".$priority3_test['student_id']."'
		AND (s2.student_id='".$priority1['student_id']."'
		OR s2.student_id='".$priority2['student_id']."')")->num_rows==0) $priority3=$priority3_test;

	if(!empty($priority1))
	{
		$conn->query("INSERT INTO watchlist (watchlist_user,watchlist_moderators) VALUES ('".$priority1['user_id']."','$verdict_moderators')");
		$conn->query("INSERT INTO taskentrusted (taskentrusted_to,taskentrusted_task,taskentrusted_timestamp,taskentrusted_urgency,taskentrusted_completed) VALUES ('".$priority1['student_id']."','$verdict_task',NOW(),3,NULL)");

		send_notification($conn, $priority1['user_id'], 2, "Exclusive proposal", "An exclusive task proposal has been made to you!", '', '');
		
		if(!empty($priority2))
		{
			$conn->query("INSERT INTO watchlist (watchlist_user,watchlist_moderators) VALUES ('".$priority2['user_id']."','$verdict_moderators')");
			$conn->query("INSERT INTO taskentrusted (taskentrusted_to,taskentrusted_task,taskentrusted_timestamp,taskentrusted_urgency,taskentrusted_completed) VALUES ('".$priority2['student_id']."','$verdict_task',NOW(),3,NULL)");
			send_notification($conn, $priority2['user_id'], 2, "Exclusive proposal", "An exclusive task proposal has been made to you!", '', '');
		}

		if(!empty($priority3))
		{
			$conn->query("INSERT INTO watchlist (watchlist_user,watchlist_moderators) VALUES ('".$priority3['user_id']."','$verdict_moderators')");
			$conn->query("INSERT INTO taskentrusted (taskentrusted_to,taskentrusted_task,taskentrusted_timestamp,taskentrusted_urgency,taskentrusted_completed) VALUES ('".$priority3['student_id']."','$verdict_task',NOW(),3,NULL)");
			send_notification($conn, $priority3['user_id'], 2, "Exclusive proposal", "An exclusive task proposal has been made to you!", '', '');
		}
	}
	else foreach($row_array1 as $row)
	{
		if($verdict_type=='USER') $notif_tasktype="user "; elseif($verdict_type=='FTR') $notif_tasktype="feature "; else $notif_tasktype="";
		if($conn->query("SELECT 1 FROM watchlist WHERE watchlist_user='".$row['user_id']."' AND watchlist_moderators='$verdict_moderators'")->num_rows==0)
		{
			send_notification($conn, $row['user_id'], 5, "Task proposal", "A ".$notif_tasktype."task proposal has been made to you!", '', '');
			$conn->query("INSERT INTO watchlist (watchlist_user,watchlist_moderators) VALUES ('".$row['user_id']."','$verdict_moderators')");
		}
	}
}

function update_watchlist($conn,$verdict_type)
{
	if(!empty($verdict_type)) $sql="AND v.verdict_type='$verdict_type' "; else $sql="";
	$sql="SELECT max(m2.moderators_id) AS max_mod, v.verdict_id, v.verdict_task, v.verdict_type FROM verdict v
		JOIN moderators m1 ON (v.verdict_moderators=m1.moderators_id)
		JOIN moderators m2 ON (m1.moderators_group=m2.moderators_group)
		JOIN task t ON (t.task_id=v.verdict_task)
		WHERE t.task_time_completed IS NULL ".$sql."
		AND (m1.moderators_first_user IS NULL OR m1.moderators_second_user IS NULL OR m1.moderators_third_user IS NULL)
		AND NOT EXISTS (SELECT COUNT(*) AS usercnt, watchlist_user FROM watchlist
		WHERE watchlist_moderators=v.verdict_moderators AND watchlist_enrolled='0'
		GROUP BY watchlist_moderators HAVING usercnt > 100)
		AND NOT EXISTS (SELECT 1 FROM taskentrusted WHERE taskentrusted_completed IS NULL AND taskentrusted_task=v.verdict_task)
		GROUP BY m2.moderators_group";
	//LAST LINE EXCLUDES THOSE WITH STILL ACTIVE PRIORITY OFFERS
	$result=$conn->query($sql);
	while($row=$result->fetch_assoc())
		if($row['verdict_type']!='USER' || $conn->query("SELECT 1 FROM student WHERE student_initauth_verdict='".$row['verdict_id']."' AND student_verdict_summary IS NOT NULL")->num_rows==0)
			addto_watchlists($conn, $row['max_mod'], $row['verdict_type'], $row['verdict_task'], 0,1);
}

function gen_pubsel($conn,$prof_fname,$prof_gname,$sel_id,$pub_confirm,$disable)
{
	if(!empty($prof_fname))
	{
		if($disable) $disable="disabled"; else $disable="";
		$conn->query("SET NAMES utf8");
		$sql="SELECT rs.researcher_id, rs.researcher_familyname, rs.researcher_givenname,
			p.publication_title, p.publication_date FROM publication p, record rc,
			researcher rs WHERE (rs.researcher_id=rc.record_researcher_id) AND (p.publication_id=rc.record_publication)
			AND rs.researcher_familyname LIKE '".$prof_fname."%' ORDER BY IF(rs.researcher_givenname LIKE '".$prof_gname."%',0,1), IF(rs.researcher_givenname LIKE '".substr($prof_gname,0,1).".%',0,1), p.publication_date DESC, rs.researcher_id LIMIT 20";
		$result=$conn->query($sql);
		if($result->num_rows > 0)
		{
			$pub_selector="";
			while($row=$result->fetch_assoc())
			{
				if(!empty($pub_confirm) && in_array($row['researcher_id'],$pub_confirm))
					$pubischecked="checked";
				else $pubischecked="";
//				if(isset($_POST['pub_confirm'])) echo var_dump($_POST['pub_confirm'])." ".$row['researcher_id']."<br>";
				$pub_selector=$pub_selector.'<tr>
             			<td>'.$row['researcher_givenname'].' '.$row['researcher_familyname'].'<br>
             			</td>
            			<td>'.$row['publication_title'].'<br>
             			</td>
             			<td>'.$row['publication_date'].'<br>
             			</td>
             			<td><input name="pub_confirm'.$sel_id.'[]" type="checkbox" value="'.$row['researcher_id'].'" '.$disable.' '.$pubischecked.'><br>
             			</td>
           		</tr>';
           	}
		}
		else return "";
	}
	else return "";
	return $pub_selector;
}

function rvw_img_latest($conn,$prof_id)
{
	$sql="SELECT review_id, review_grade, review_time_aborted, review_agreed FROM review WHERE review_prof='$prof_id'
		AND (review_time_submit IS NOT NULL OR review_time_aborted IS NOT NULL OR review_time_tgth_passedon IS NOT NULL) ORDER BY review_id DESC";
	$row=$conn->query($sql)->fetch_assoc();
	if(!is_null($row['review_grade']))
		$review_grade=$row['review_grade'];
	elseif(!empty($row['review_time_aborted']) && $row['review_agreed']=='0')
		$review_grade=0;
	elseif(!empty($row['review_time_aborted']) && $row['review_agreed']=='1')
		$review_grade=-1;

	if(!isset($review_grade))
		$rvw_img_latest="no";
	else switch($review_grade)
	{
		case 2: $rvw_img_latest="green"; break;
		case 1: $rvw_img_latest="orange"; break;
		case 0: $rvw_img_latest="red"; break;
		case -1: $rvw_img_latest="skull"; break;
	}
	return $rvw_img_latest;
}

function curl_get_contents($url)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $url);

    $data = curl_exec($ch);
    curl_close($ch);

    return $data;
}

function sparqlQuery($query, $baseURL, $format="application/json")
{
	$params=array(
		"default-graph" =>  "",
		"should-sponge" =>  "soft",
		"query" =>  $query,
		"debug" =>  "on",
		"timeout" =>  "5000",
		"format" =>  $format,
		"save" =>  "display",
		"fname" =>  ""
	);

	$querypart="?";
	foreach($params as $name => $value) 
    {
		$querypart=$querypart . $name . '=' . urlencode($value) . "&";
	}
	
	$sparqlURL=$baseURL . $querypart;

	$ctx = stream_context_create(array('http'=>
    array(
        'timeout' => 7, 
    	)
	));
	
//	ini_set('default_socket_timeout', 3);
	$result=curl_get_contents($sparqlURL,false,$ctx);
	
	if(!empty($result))
		return json_decode($result,true);
}

function virtuoso_nb($researcher_id)
{
	# Setting Data Source Name (DSN)
	$dsn="http://dbpedia.org/resource/DBpedia";

	#Virtuoso pragmas for instructing SPARQL engine to perform an HTTP GET
	#using the IRI in FROM clause as Data Source URL

	$query="select ?dist
	{
	<https://w3id.org/oc/corpus/ra/".$researcher_id."> foaf:knows ?c option (transitive,t_distinct,t_min(5),t_max(5),t_direction 3, t_no_cycles, t_step ('step_no') as ?dist,t_shortest_only).
	}"; 

	$data=sparqlQuery($query, "http://localhost:8890/sparql/");

//	echo $researcher_id." ".sizeof($data['results']['bindings'])." ";
	return sizeof($data['results']['bindings']);
}

function virtuoso_dist($researcher_id1, $researcher_id2)
{
	# Setting Data Source Name (DSN)
	$dsn="http://dbpedia.org/resource/DBpedia";

	#Virtuoso pragmas for instructing SPARQL engine to perform an HTTP GET
	#using the IRI in FROM clause as Data Source URL

	$query="select ?dist
	{
	<https://w3id.org/oc/corpus/ra/".$researcher_id1."> foaf:knows <https://w3id.org/oc/corpus/ra/".$researcher_id2."> option (transitive,t_distinct,t_min(4),t_max(10),t_direction 3, t_no_cycles, t_step ('step_no') as ?dist,t_shortest_only).
	}"; 

	$data=sparqlQuery($query, "http://localhost:8890/sparql/");

	if(!empty($data['results']['bindings']['0'])) return $data['results']['bindings']['0']['dist']['value'];
}

function check_collocs($error_msg, $item_max1, $item_max2, $item_max3)
{
	if(!empty($item_max1) && !empty($item_max2))
	{
		 $dist_1and2=virtuoso_dist($item_max1,$item_max2); //echo " 1and2 ".$dist_1and2;
		 if(empty($dist_1and2) || $dist_1and2 >= 10)
		 	$error_msg=$error_msg."Prof 1 and 2 too far!<br>";
		 elseif($dist_1and2 <= 4)
		 	$error_msg=$error_msg."Prof 1 and 2 too close!<br>";
	}
			
	if(!empty($item_max2) && !empty($item_max3))
	{
		 $dist_2and3=virtuoso_dist($item_max2,$item_max3); //echo " 2and3 ".$dist_2and3;
		 if(empty($dist_2and3) || $dist_2and3 >= 10)
		 	$error_msg=$error_msg."Prof 2 and 3 too far!<br>";
		 elseif($dist_2and3 <= 4)
		 	$error_msg=$error_msg."Prof 2 and 3 too close!<br>";
	}

	if(!empty($item_max1) && !empty($item_max3))
	{
		 $dist_1and3=virtuoso_dist($item_max1,$item_max3); //echo " 1and3 ".$dist_1and3;
		 if(empty($dist_1and3) || $dist_1and3 >= 10)
		 	$error_msg=$error_msg."Prof 1 and 3 too far!<br>";
		 elseif($dist_1and3 <= 4)
		 	$error_msg=$error_msg."Prof 1 and 3 too close!<br>";
	}
	
	return $error_msg;
}

function login_callback($url,$fields)
{
	$fields_string="";
	foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
	rtrim($fields_string, '&');
	
	$header = array();
	$header[0] = "accept:application/json";

	$ch = curl_init();

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  2);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
	curl_setopt($ch, CURLOPT_AUTOREFERER, true); 
	curl_setopt($ch, CURLOPT_TIMEOUT, 15);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, count($fields));
	curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
	curl_setopt($ch, CURLOPT_HEADER, 1);
	curl_setopt($ch, CURLOPT_ENCODING, "");
	$text = curl_exec($ch);
	curl_close($ch);
	
	preg_match('/{(.*?)}/s', $text, $match);
	$auth_array=json_decode($match[0],true);
	
	return $auth_array;
}

function gen_orcidquery($code)
{
	$fields=array('client_id' => 'INSERT_ORCIDAPP_LOGIN_HERE',
    	'client_secret' => 'INSERT_ORCIDAPP_SECRET_HERE',
    	'grant_type' => 'authorization_code',
    	'code' => $code,
    	'redirect_uri' => 'https://www.myphdidea.org/login_orcid.php');

    return $fields;
}

function gen_fbquery($code)
{
	$fields=array('client_id' => 'INSERT_FBAPP_LOGIN_HERE',
    	'client_secret' => 'INSERT_FBAPP_SECRET_HERE',
    	'code' => $code,
    	'redirect_uri' => 'https://www.myphdidea.org/login_facebook.php');

    return $fields;
}

function gen_googlequery($code)
{
	$fields=array('client_id' => 'INSERT_GOOGLEAPP_LOGIN_HERE.apps.googleusercontent.com',
    	'client_secret' => 'INSERT_GOOGLEAPP_LOGIN_HERE',
    	'grant_type' => 'authorization_code',
    	'code' => $code,
    	'redirect_uri' => 'https://www.myphdidea.org/login_google.php');

    return $fields;
}

function gen_twitterquery($code)
{
	$fields=array('client_id' => 'INSERT_TWITTERAPP_LOGIN_HERE',
    	'client_secret' => 'INSERT_TWITTERAPP_SECRET_HERE',
    	'grant_type' => 'authorization_code',
    	'code' => $code,
    	'redirect_uri' => 'https://www.myphdidea.org/login_twitter.php');

    return $fields;
}

function post_tweet($tweet_string, $media_files)
{
	// require codebird
	require_once('libs/codebird/codebird.php');
 
	\Codebird\Codebird::setConsumerKey("INSERT_TWITTERAPI_KEY_HERE", "INSERT_TWITTERAPI_SECRET_HERE");
	$cb = \Codebird\Codebird::getInstance();
	$cb->setToken("INSERT_TWITTERACCESS_TOKEN_HERE", "INSERT_TWITTERACCESS_TOKENSECRET_HERE");

	if(!empty($media_files))
	{
		// will hold the uploaded IDs
		$media_ids = array();

		foreach ($media_files as $file) {
  			// upload all media files
  			$reply = $cb->media_upload(array(
    			'media' => $file
  			));
  			// and collect their IDs
  			$media_ids[] = $reply->media_id_string;
		}
		
		$media_ids = implode(',', $media_ids);
		
		$params = array(
 	 		'status' => $tweet_string,
 	 		'media_ids' => $media_ids
		);
	}
	else $params = array(
 	 'status' => $tweet_string
	);
	
	$reply = $cb->statuses_update($params);
}

function rvw_to_newsfeeds($conn,$cmpgn_id)
{
	$result=$conn->query("SELECT p.prof_id, p.prof_givenname, p.prof_familyname, p.prof_image, c.cmpgn_title FROM prof p JOIN review r ON (r.review_prof=p.prof_id)
		JOIN cmpgn c ON (c.cmpgn_rvw_favourite=r.review_id) WHERE c.cmpgn_id='$cmpgn_id'");
	if($result->num_rows > 0)
	{
		$row=$result->fetch_assoc();
		$review_string=$row['prof_givenname']." ".$row['prof_familyname']." reviewed ".$row['cmpgn_title'];
		
		if(strlen($review_string) >= 140)
			$review_string=substr($review_string,0,135)." ...";
		
		if(!empty($row['prof_image']))
/*		{
			putenv('PATH=C:/Program Files/ImageMagick-7.0.5-Q16/');
			exec('magick images/tilde.png user_data/researcher_pictures/'.$row['prof_id'].'.png images/tilde.png +append user_data/tmp/proftweet_'.$row['prof_id'].".png");*/

			post_tweet($review_string,array('user_data/researcher_pictures/'.$row['prof_id'].'.png'));

/*			post_tweet($review_string,array('https://www.myphdidea.org/user_data/tmp/proftweet_'.$row['prof_id'].".png"));
			unlink('user_data/tmp/proftweet_'.$row['prof_id'].".png");
		}*/
		else post_tweet($review_string,'');
	}
}

function tsa_callmultiple($conn,$cmpgn_id,$upload_id)
{
	$file1=file_get_contents('user_data/uploads/'.$cmpgn_id.'_'.$upload_id.'_1.pdf');
	$file2=file_get_contents('user_data/uploads/'.$cmpgn_id.'_'.$upload_id.'_2.pdf');
	$file3=file_get_contents('user_data/uploads/'.$cmpgn_id.'_'.$upload_id.'_3.pdf');
	
	contact_tsa($conn,$upload_id,'1',$file1,'freetsa');
	if(!empty($file2)) contact_tsa($conn,$upload_id,'2',$file2,'freetsa');
	if(!empty($file3)) contact_tsa($conn,$upload_id,'3',$file3,'freetsa');
	
	contact_tsa($conn,$upload_id,'1',$file1,'dfn');
	if(!empty($file2)) contact_tsa($conn,$upload_id,'2',$file2,'dfn');
	if(!empty($file3)) contact_tsa($conn,$upload_id,'3',$file3,'dfn');
	
	contact_tsa($conn,$upload_id,'1',$file1,'safestamper');
	if(!empty($file2)) contact_tsa($conn,$upload_id,'2',$file2,'safestamper');
	if(!empty($file3)) contact_tsa($conn,$upload_id,'3',$file3,'safestamper');
}

function contact_tsa($conn,$upload_id,$file_nb,$data,$authority)
{
	require_once("libs/TrustedTimestamps.php");

	switch($authority)
	{
		case 'freetsa':
			$tsa_url='https://freetsa.org/tsr';
			$tsa_cert_file='other/tsa_certificates/freetsa_cacert.cer';
			$username_password="";
			break;
		case 'dfn':
			$tsa_url='http://zeitstempel.dfn.de';
			$tsa_cert_file='other/tsa_certificates/dfn_cert.txt';
			$username_password="";
			break;
		case 'safestamper':
			$tsa_url='https://www.safestamper.com/tsa';
			$tsa_cert_file='other/tsa_certificates/SafeCreative_TSA.cer';
			$username_password="INSERT_SAFESTAMPER_EMAIL_HERE:INSERT_SAFESTAMPER_PASSWORD_HERE";
			break;
		case 'globaltrust':
			$tsa_url='https://timestamp.globaltrust.eu:10080';
			break;
	}

	$my_hash = hash("sha512",$data);

	$requestfile_path = TrustedTimestamps::createRequestfile($my_hash);

	$response = TrustedTimestamps::signRequestfile($requestfile_path, $tsa_url,$username_password);

	if(!empty($response)/* && ($authority=='dfn' || TrustedTimestamps::validate($my_hash, $response['response_string'], $response['response_time'], $tsa_cert_file))*/)
	{
		$conn->query("INSERT INTO timestamp (timestamp_upload, timestamp_file_nb, timestamp_authority, timestamp_time, timestamp_hash)
			VALUES ('$upload_id','$file_nb','$authority','".$response['response_time']."','$my_hash')");
			
		$sql="SELECT LAST_INSERT_ID();";
		$timestamp_id=$conn->query($sql)->fetch_assoc();
		$timestamp_id=$timestamp_id['LAST_INSERT_ID()'];
		
		file_put_contents('user_data/timestamps/'.$upload_id.'_'.$file_nb.'_'.$timestamp_id.'.tsr',$response['response_string']);
	}
	else echo $authority." timestamp for file ".$file_nb." failed!";//var_dump($validate); //bool(true)
}

?>