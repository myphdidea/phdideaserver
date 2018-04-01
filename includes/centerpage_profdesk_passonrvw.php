<?php
$error_msg="";
if(empty($_SESSION['prof']))
	echo "Transfer review screen only available to researchers please login!<br>";
else
{
	$cmpgn_id=$conn->real_escape_string(test($_GET['cmpgn']));
	
	$sql="SELECT r.review_id, DATE_ADD(r.review_time_requested,INTERVAL 6 WEEK) AS review_time_due, r.review_time_submit,
		  r.review_time_tgth_passedon, r.review_time_aborted, r.review_together_with,
		  r.review_upload FROM review r JOIN upload u ON (r.review_upload=u.upload_id) WHERE u.upload_cmpgn='".$cmpgn_id."' AND r.review_prof='".$_SESSION['prof']."'";
	$result=$conn->query($sql);
	if($result->num_rows==0)
		echo "You don't seem to have been contacted as a reviewer for this campaign.<br>";
	else
	{
		$row=$result->fetch_assoc();
		$review_id=$row['review_id'];
		$review_upload=$row['review_upload'];
		$review_time_due=$row['review_time_due'];
		$review_together_with=$row['review_together_with'];
		if(empty($row['review_together_with']))
			$error_msg=$error_msg."No permission yet to pass on to anyone!";
		else
		{
			$sql="SELECT prof_givenname, prof_familyname FROM prof WHERE prof_id='".$row['review_together_with']."'";
			$row2=$conn->query($sql)->fetch_assoc();
			$prof_familyname=$row2['prof_familyname'];
			$tgth_string='<a href="index.php?prof='.$row['review_together_with'].'">'.$row2['prof_givenname'].' '.$row2['prof_familyname'].'</a>';
		}
		if(!empty($row['review_time_submit']))
			$error_msg="Thank you, your review has now been submitted and will be published soon.<br>";
		elseif(!empty($row['review_time_aborted']))
			$error_msg="Sorry but you have taken too long and can no longer submit a review.<br>";
		elseif(!empty($row['review_time_tgth_passedon']))
			$error_msg='Your reviewer responsibility has now been passed on to '.$row2['prof_givenname'].' '.$row2['prof_familyname'].'<br>';
		elseif(strtotime("now") > strtotime($review_time_due) && $conn->query("SELECT 1 FROM cmpgn c JOIN upload u ON (u.upload_cmpgn=c.cmpgn_id)
			WHERE c.cmpgn_time_firstsend + INTERVAL 8 MONTH < NOW() AND u.upload_id='$review_upload'")->num_rows > 0)
			$error_msg='We are now preparing all reviews for publication and so you cannot pass on your responsibility any longer.<br>.';
			

		$sql="SET @version=0;";
		$result=$conn->query($sql);

		$sql="SELECT @version:=@version+1 AS version, u.upload_id, c.cmpgn_title, c.cmpgn_user, c.cmpgn_time_launched,
				 c.cmpgn_time_firstsend, c.cmpgn_time_finalized, c.cmpgn_type_isarchivized, c.cmpgn_moderators_group
				 FROM cmpgn c JOIN upload u ON (c.cmpgn_id=u.upload_cmpgn) WHERE c.cmpgn_id='".$cmpgn_id."' ORDER BY version DESC";
		$result=$conn->query($sql);
		while($row=$result->fetch_assoc())
		{
			if($row['upload_id']!=$review_upload)
				continue;
			$version=$row['version'];
			$upload_id=$row['upload_id'];
			$cmpgn_title=$row['cmpgn_title'];
			$cmpgn_user=$row['cmpgn_user'];
			$time_launched=$row['cmpgn_time_launched'];
			$time_firstsend=$row['cmpgn_time_firstsend'];
			$time_finalized=$row['cmpgn_time_finalized'];
			$isarchivized=$row['cmpgn_type_isarchivized'];
			$moderators_group=$row['cmpgn_moderators_group'];
			break;
		}
		
		$sql="SELECT a.autoedit_email_auth FROM autoedit a JOIN prof p ON (a.autoedit_prof=p.prof_id) WHERE prof_id='$review_together_with'";
		$result=$conn->query($sql);
		if($result->num_rows==0)
			$passon_disabled="disabled";
		else
		{
			$row=$result->fetch_assoc();
			if(empty($row['autoedit_email_auth']))
				$passon_disabled="disabled";
		}
		
		if(empty($error_msg) && isset($_POST['submit']))
		{
			$tocolleague=$conn->real_escape_string(test($_POST['Tocolleague']));
			if(!empty($_POST['confirm_passon']))
				$confirm_passon=$conn->real_escape_string(test($_POST['confirm_passon']));

			if(empty($confirm_passon))
				$error_msg=$error_msg."Please confirm that your colleague has agreed to write this review!<br>";
			if($conn->query("SELECT 1 FROM review r JOIN upload u ON (r.review_upload=u.upload_id) WHERE r.review_together_with='".$_SESSION['prof']."' AND u.upload_cmpgn='$cmpgn_id'")->num_rows > 0)
				$error_msg=$error_msg."No chaining deferred responsibility please!";
			if(!empty($passon_disabled))
				$error_msg=$error_msg."No email stored yet!<br>";
			if(empty($tocolleague))
				$error_msg=$error_msg."Cannot have empty message to colleague!<br>";
			elseif(strlen($tocolleague) > 2000)
				$error_msg=$error_msg."Message too long please moderate yourself!<br>";
			if(!empty($time_finalized) || !empty($isarchivized))
				$error_msg=$error_msg."Finalized or archivized campaign, should not be seeing this ...<br>";
			if(empty($error_msg))
			{
				//CREATE NEW REVIEW AND SEND DIRECT LOGIN LINK
				//GENERATE DIRECT LOGIN CODE
				$direct_login_token=openssl_random_pseudo_bytes(32);

				//UPDATE REVIEW TIMESTAMP AND REVIEW DIRECT LOGIN CODE
				$direct_login_link="https://www.myphdidea.org/invitation.php?directlogin=".bin2hex($direct_login_token);

				//SEND EMAILS
				$row3=$conn->query("SELECT prof_familyname FROM prof WHERE prof_id='".$_SESSION['prof']."'")->fetch_assoc();
				$text1="Your colleague Prof. ".$row3['prof_familyname']." is passing on responsibility as reviewer for a student research project idea, which should have previously been agreed with you. Your colleague writes\n\n'"
					.$tocolleague."'\n\nIn order to learn about this research pitch, please follow the link below:\n\n";
				$text2="\n\nFrom the reception of this mail, you are guaranteed a minimum of 2 weeks to make a decision on whether you want to review this idea.
					If you signal a positive intent on the site the link will take you to, you are then guaranteed an additional 4 weeks, for a total of 6 weeks
					to submit your review, extensible at the discretion of the student.";

				if(send_profnotif($conn, $review_together_with, 0, "Research project idea", $text1, $direct_login_link, $text2))
				{
					$direct_login_token=$conn->real_escape_string($direct_login_token);
					$sql="INSERT INTO review (review_upload, review_prof, review_time_requested, review_agreed, review_directlogin, review_msg_toprof)
						VALUES ('$review_upload','$review_together_with',NOW(),NULL,'$direct_login_token','$tocolleague')";
					if(!$conn->query($sql))
						$error_msg=$error_msg."Database insert error please retry!<br>";
					else
					{
						$sql="UPDATE review SET review_time_tgth_passedon=NOW() WHERE review_id='".$review_id."'";
						$conn->query($sql);
					
						$sql="UPDATE prof SET prof_hasactivity='1' WHERE prof='".$review_together_with."'";
						$conn->query($sql);
					
						header("Location: index.php?confirm=passon_exec");
					}
				}
				else $error_msg=$error_msg."Mail could not be sent please retry!";
			}
		}
	}
}
?>
<form method="post" action="">
      <div id="centerpage">
        <h1>Transfer review</h1>
        <h2>of "<?php echo '<a href="index.php?cmpgn='.$cmpgn_id.'">'.$cmpgn_title.'</a> (v.'.$version.')'; ?>"</h2>
        <h2>to "<?php echo $tgth_string ?>"</h2>
        (<?php  if(empty($time_firstsend) && !empty($time_finalized))
						$launched="approval not obtained";
					  elseif(empty($time_firstsend))
        				$launched="not yet approved";
					  else $launched="approved ".$time_firstsend;
        		echo "Created ".$time_launched.", ".$launched
        			  	.", "; if($isarchivized) echo "archivized";
        					 else if(empty($time_finalized)) echo "still running";
        					 else echo "finalized ".$time_finalized;?>)<br>
        <?php if(!empty($error_msg)) echo '<br><div style="color: red; text-align: center">'.$error_msg.'</div>';?>
		<br>You now have permission to transfer the task of writing your review to Prof. <?php echo $prof_familyname.". ";
			if(!empty($passon_disabled))
				echo "However, your colleague has not yet registered an e-mail address with us, so we do not know where we should send the notification email. Hence, please get your colleague to log into our website with ORCID and register an institutional e-mail address under <i>Settings</i>. We will then enable the panels below.";
			else echo "Upon transfer, you will no longer be able to write the review, but your colleague will receive a link to our page, and the grade will be shared between the two of you.";
			?> 
        <p>In this context, you are the person who guarantees that Prof. <?php echo $prof_familyname ?> is competent to comment on the idea,
        	and moreover consents to write this review. Please write a short personal message to your colleague so that our mail does not get overlooked:
        </p>
        <p class="indentation"><div style="text-align: center"><textarea style="width: 400px; height: 200px;"

class="indentation" name="Tocolleague" <?php if(!empty($passon_disabled)) echo $passon_disabled; ?>><?php if(!empty($tocolleague)) echo $tocolleague; ?></textarea></div>
		<input type="checkbox" name="confirm_passon" <?php if(!empty($passon_disabled)) echo $passon_disabled; ?> <?php if(!empty($confirm_passon)) echo 'checked' ?>> I hereby confirm that Prof. <?php echo $prof_familyname?> has agreed to write the review for me.
        <p style="text-align: right;"> <button name="submit" <?php if(!empty($passon_disabled)) echo $passon_disabled; ?>>Submit</button></p>
      </div>
</form>