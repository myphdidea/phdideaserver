<?php
$error_msg="";
include("includes/cmpgn_header.php");

if(!empty($intlink))
{
	$sql="SELECT cmpgn_title FROM cmpgn WHERE cmpgn_id='".$intlink."'";
	$row=$conn->query($sql)->fetch_assoc();
	$intlink_html=$row['cmpgn_title'];
}

$visitor_isowner=FALSE; $visitor_ismoderator=FALSE;// $visitor_isreviewprof=FALSE;
if(isset($_SESSION['user']) && isset($_SESSION['isstudent']))
{
	if($_SESSION['user']==$owner)
	{
		$visitor_isowner=TRUE;
		//SUMMARIZE LAST DIALOGUE WITH PROF
		$sql="SELECT p.prof_id, p.prof_givenname, p.prof_familyname, d.dialogue_time_sent FROM prof p JOIN dialogue d ON (p.prof_id=d.dialogue_prof) WHERE dialogue_speaker=TRUE AND dialogue_cmpgn LIKE '".$cmpgn_id."' ORDER BY d.dialogue_time_sent DESC";
		$result=$conn->query($sql);
		if($result->num_rows > 0)
		{
			$row=$result->fetch_assoc();
			$last_dialogue='<a href="index.php?prof='.$row['prof_id'].'">'.$row['prof_givenname'].' '.$row['prof_familyname'].'</a>, '.$row['dialogue_time_sent'];
		}
		//SUMMARIZE LAST COMMENT TIME
		$sql="SELECT c.comment_time_proposed FROM comment c JOIN upload u ON (c.comment_upload=u.upload_id) WHERE c.comment_accepted IS NULL AND u.upload_cmpgn='".$cmpgn_id."' ORDER BY c.comment_time_proposed DESC";
		$result=$conn->query($sql);
		if($result->num_rows > 0)
		{
			$row=$result->fetch_assoc();
			$last_comment=$row['comment_time_proposed'];
		}
		if(isset($_POST['submit_comment']))
		{
			if(test($_POST['vis_selector'.test($_POST['submit_comment'])])=="accept")
				$accept_comment=TRUE;
			elseif(test($_POST['vis_selector'.test($_POST['submit_comment'])])=="reject")
				$accept_comment=FALSE;
			else $error_msg=$error_msg."Please choose between accept/reject!<br>";
			
			$msg_to_comm=$conn->real_escape_string(test($_POST['msg'.test($_POST['submit_comment'])]));
			if(empty($msg_to_comm))
				$error_msg=$error_msg."Please don't forget thank-you message!<br>";
			elseif(strlen($msg_to_comm) > 2000)
				$error_msg=$error_msg."Max 2000 characters, moderate yourself!";
			if(isset($accept_comment) && empty($error_msg))
			{
				$sql="UPDATE comment SET comment_accepted='".$accept_comment."',comment_msg='".$msg_to_comm."', comment_time_posted=NOW() WHERE comment_id='".$conn->real_escape_string(test($_POST['submit_comment']))."'";
				$conn->query($sql);
				$row=$conn->query("SELECT s.student_user_id FROM student s JOIN comment c ON (s.student_id=c.comment_student) WHERE c.comment_id='".$conn->real_escape_string(test($_POST['submit_comment']))."'")->fetch_assoc();
				send_notification($conn,$row['student_user_id'],3,'Comment publication','A decision on your comment has now been rendered, where the owner writes: '.$msg_to_comm,'','');
			}
		}
	}
	else if(!$isarchivized && empty($time_finalized))
	{
		$sql="SELECT student_cmpgn_shadowed_latest FROM student WHERE student_user_id=".$_SESSION['user'];
		$result=$conn->query($sql);
		$row=$result->fetch_assoc();
		if($row['student_cmpgn_shadowed_latest']==$cmpgn_id)
			$visitor_ismoderator=TRUE;
	}
}
else if(isset($_SESSION['prof']) && !$isarchivized && empty($time_finalized))
{
	$sql="SELECT u.upload_id, r.review_id, DATE_ADD(r.review_time_requested,INTERVAL 6 WEEK) AS review_time_due, r.review_time_submit,
		r.review_time_aborted, r.review_time_tgth_passedon, r.review_agreed, r.review_together_with, r.review_aborted_byuser
			FROM upload u JOIN review r ON (u.upload_id=r.review_upload) WHERE r.review_agreed='1' AND r.review_prof=".$_SESSION['prof']." AND u.upload_cmpgn=".$cmpgn_id;
	$result=$conn->query($sql);
	$visitor_isreviewprof=FALSE;
	if($result->num_rows > 0)
	{
		$visitor_isreviewprof=TRUE;
		$row=$result->fetch_assoc();
		if(!empty($row['review_time_submit']))
			$prof_msg="Thank you, your review has now been submitted and will be published soon.";
		elseif(!empty($row['review_time_aborted']))
			$prof_msg="Sorry but you have taken too long and can no longer submit a review.";
		elseif(!empty($row['review_time_tgth_passedon']))
		{
			$sql="SELECT prof_givenname, prof_familyname FROM prof WHERE prof_id='".$row['review_together_with']."'";
			$result=$conn->query($sql);
			$row2=$result->fetch_assoc();
			$prof_msg='Your reviewer responsibility has now been passed on to <a href="index.php?prof='.$row['review_together_with'].'">'.$row2['prof_givenname'].' '.$row2['prof_familyname'].'</a>';
		}
		else
		{
			$rvwprof_canwrite=TRUE;
			$prof_msg='Your review is due '.$row['review_time_due'].', in '.floor((strtotime($row['review_time_due'])-time())/(24*60*60)).' days.
					   Please study the material below, using the "dialogue" option to contact the student and arrange a presentation. Once you have formed your opinion, please write and submit your review.';
			if($row['review_together_with']!=0)
			{
				$sql="SELECT prof_givenname, prof_familyname FROM prof WHERE prof_id='".$row['review_together_with']."'";
				$result=$conn->query($sql);
				$row2=$result->fetch_assoc();

				$rvwprof_canpasson=TRUE;
				$prof_msg=$prof_msg.'<br>You have also now been granted permission to pass on your reviewer duties to <a href="index.php?prof='.$row['review_together_with'].'">'.$row2['prof_givenname'].' '.$row2['prof_familyname'].'</a>, please use the button.';
			}
		}
	}
	else $prof_msg='Interested in signing on this idea&#39;s author as a PhD student? Click button to contact.';
}
else if(isset($_SESSION['prof'])) $prof_msg='Interested in signing on this idea&#39;s author as a PhD student? Click button to contact.';

if($revealtoprof && (isset($visitor_isreviewprof) && $visitor_isreviewprof) || $visitor_isowner)
{
		$sql="SELECT student_familyname, student_givenname, student_selfdescription, student_socialmedia_link FROM student WHERE student_user_id=".$owner;
		$row=$conn->query($sql)->fetch_assoc();
		$printname_prof=$row['student_givenname']." ".$row['student_familyname'];//.", ".$row['student_selfdescription'];
		if(!empty($row['student_socialmedia_link']))
			$printname_prof='<a href="index.php?page=redirect&link='.$row['student_socialmedia_link'].'">'.$printname_prof.'</a>';
		if(!empty($row['student_selfdescription'])) $printname_prof=$printname_prof.", ".$row['student_selfdescription'];
		if(!$visitor_isowner) $printname=$printname_prof;
}
if(!$isarchivized && !empty($time_finalized) && empty($printname))
{
	if($revealrealname==0)
		$printname="<i>Anonymous</i>";
	elseif($revealrealname==2)
	{
		$sql="SELECT user_pseudonym FROM user WHERE user_id=".$owner;
		$row=$conn->query($sql)->fetch_assoc();
		$printname=$row['user_pseudonym'];
	}
	elseif($revealrealname==1)
	{
		$sql="SELECT student_familyname, student_givenname, student_selfdescription, student_socialmedia_link FROM student WHERE student_user_id=".$owner;
		$row=$conn->query($sql)->fetch_assoc();
		$printname=$row['student_givenname']." ".$row['student_familyname'];//.", ".$row['student_selfdescription'];
		if(!empty($row['student_socialmedia_link']))
			$printname='<a href="index.php?page=redirect&link='.$row['student_socialmedia_link'].'">'.$printname.'</a>';
//		$printname=$printname.", ".$row['student_selfdescription'];
		if(!empty($row['student_selfdescription'])) $printname=$printname.", ".$row['student_selfdescription'];
	}
}

//$conn->close();
?>
<div style="float: right; margin-top: 5px; margin-right: 12px">
						<?php if(isset($_SESSION['user']) && $_SESSION['user']=='1') echo '<form method="post" action=""><select name="visibility"><option value="" >--</option>
																<option value="block">Block</option>
																<option value="unblock">Unblock</option></select><button name="block">OK</button></form>'; ?>
						<a href="https://twitter.com/share?url=https://www.myphdidea.org/index.php?cmpgn=<?php echo $cmpgn_id; ?>" target="_blank"><img src="images/twitter_square.png" style="width: 20px"></a>
						<a href="https://www.facebook.com/sharer/sharer.php?u=https://www.myphdidea.org/index.php?cmpgn=<?php echo $cmpgn_id; ?>" target="_blank"><img src="images/fb_square.png" style="width: 20px"></a></div>
      <div id="centerpage">
      	<?php if(!empty($title)) include("includes/cmpgn_header_display.php"); /*$conn->close();*/ ?>
        <?php
        	if(!empty($intlink))
        	{
/*        		$sql="SELECT cmpgn_title FROM cmpgn WHERE cmpgn_id='".$intlink."'";
				$row=$conn->query($sql)->fetch_assoc();
				$intlink_html=$row['cmpgn_title'];*/
				$intlink_html='<a href="index.php?cmpgn='.$intlink.'">'.$intlink_html.'</a>';
        	}
        	if(!empty($extlink)) $extlink_html='<a href="index.php?page=redirect&link='.$extlink.'">external site</a>.';
			
			if(!empty($intlink) && !empty($extlink))
				echo "<br><br>Please also visit ".$intlink_html." and this campaign&#39;s ".$extlink_html;
			elseif(!empty($intlink))
				echo "<br><br>Please also visit ".$intlink_html;
			elseif(!empty($extlink))
				echo "<br><br>Please also visit this campaign&#39;s ".$extlink_html;
        ?>
		<?php
			$buttons="";
			$manip_hght=100;
			$manip_color='red';
			if(isset($_SESSION['prof']))
			{
				if(isset($prof_msg)) $manip_msg=$prof_msg;
				if(empty($visitor_isreviewprof) || !$visitor_isreviewprof)
				{
					$manip_color='#1ae61a';
					//ONLY DIALOGUE BUTTON
					$buttons='<a href="index.php?workbench=dialogue&cmpgn='.$cmpgn_id.'"><img
              		class="upload_buttons" title="Contact author" alt="Dialogue-button" src="images/dialogue.png"></a>';
				}
				else
				{
					$manip_hght=120;
					//PASS ON, DIALOGUE AND REVIEW BUTTONS
					if(!empty($rvwprof_canwrite)) $buttons='<a href="index.php?profdesk=newreview&cmpgn='.$cmpgn_id.'"><img class="upload_buttons" title="Write review" alt="Review-button" src="images/write-review.png"></a>';
					else $buttons="";
					$buttons=$buttons.'<a href="index.php?workbench=dialogue&cmpgn='.$cmpgn_id.'"><img class="upload_buttons" title="Contact author" alt="Dialogue-button" src="images/dialogue.png"></a>';
					if(!empty($rvwprof_canpasson) && $rvwprof_canpasson)
						$buttons=$buttons.'<a href="index.php?profdesk=passonrvw&cmpgn='.$cmpgn_id.'"><img class="upload_buttons" title="Pass on" alt="Pass-on-button" src="images/passon.png"></a>';
				}
			}
			else if($visitor_isowner)
			{

				$manip_msg="";

				if(!empty($last_dialogue))	$manip_msg="<br>&bull; Last contacted by ".$last_dialogue;
				if(!empty($last_comment)) $manip_msg=$manip_msg."<br>&bull; Unanswered comment ".$last_comment;

				if(empty($time_finalized) &&!$isarchivized)
				{
					//CHECK WHETHER SEND AUTHORIZED
					$sql="SELECT 1 FROM upload WHERE upload_verdict_summary='1' AND upload_cmpgn='$cmpgn_id'";
					if($conn->query($sql)->num_rows > 0)
					{
						$manip_msg=" Click 'send' to contact professors yourself.".$manip_msg;
						$buttons='<a href="index.php?workbench=newsend&cmpgn='.$cmpgn_id.'"><img class="upload_buttons" title="Send to prof" alt="Send-button" src="images/send.png"></a>'.$buttons;
					}
					else $manip_msg=" The 'send' button will appear once your idea has been approved by other students.";

					//NEW UPLOAD, SETTINGS, DIALOGUE
					$manip_msg="This is the campaign menu! Click below to manage settings, upload new material or respond to contact requests by professors.".$manip_msg;
					$buttons=$buttons.'<a href="index.php?workbench=dialogue&cmpgn='.$cmpgn_id.'"><img class="upload_buttons" title="Dialogue" alt="Dialogue-button" src="images/dialogue.png"></a>
							  <a href="index.php?workbench=newupload&cmpgn='.$cmpgn_id.'"><img class="upload_buttons" title="New Upload" alt="Upload-button" src="images/upload.png"></a>
							  <a href="index.php?workbench=config&cmpgn='.$cmpgn_id.'"><img class="upload_buttons" title="Settings" alt="Settings-button" src="images/settings-gear.png"></a>';
				}
				else
				{
					//SETTINGS AND DIALOGUE
					$manip_msg="Your campaign is now over, but you can still view settings and respond to contact requests.".$manip_msg;
					$buttons='<a href="index.php?workbench=dialogue&cmpgn='.$cmpgn_id.'"><img class="upload_buttons" title="Dialogue" alt="Dialogue-button" src="images/dialogue.png"></a>
							  <a href="index.php?workbench=config&cmpgn='.$cmpgn_id.'"><img class="upload_buttons" title="Settings" alt="Settings-button" src="images/settings-gear.png"></a>';
				}
			}
			else if($visitor_ismoderator && empty($time_finalized))
			{
				//TASKS
				$manip_msg='Please consult the "Active Tasks" tab for all matters requiring your attention on this campaign, and information about deadlines. In case you feel this job has become too much of a burden, a manual option for disenrollment is available below.';
				$buttons='<a href="index.php?workbench=resign&cmpgn='.$cmpgn_id.'"><img class="upload_buttons" title="Resignation" alt="Resign-button" src="images/resign.png"></a>';
			}
			else if(isset($_SESSION['user']) && isset($_SESSION['isstudent']) && !$visitor_ismoderator && empty($time_finalized) && !$isarchivized)
			{
				$manip_msg="Though student interaction with campaigns is mainly through assignment to one as moderator, every student may write and send 1 review to the author.
					In return, you get to see other peoples' opinions below (all will be published at campaign end).";
				$buttons='<a href="index.php?workbench=newcomment&cmpgn='.$cmpgn_id.'"><img class="upload_buttons" title="Write opinion" alt="Opinion-button" src="images/write-review.png"></a>';
			}
			if(!empty($manip_msg))
			{
          		$manip_footer='<div class="upload_footer">'.$buttons.'<br> </div>';
				echo '<br><br><div class="list" style="border-color: '.$manip_color.'; height: '.$manip_hght.'px" id="toplist"><div style="height: '.($manip_hght-35).'px; display: block">'.$manip_msg.'</div>'.$manip_footer.'</div>';
			}
		?>
<?php
if(isset($_POST['submit_rating'])
	&& (isset($_SESSION['user']) && isset($_SESSION['isstudent']) && $_SESSION['user']!=$owner
		|| isset($_SESSION['prof']) && empty($visitor_isreviewprof)))
{
	$submit_rating=$conn->real_escape_string(test($_POST['submit_rating']));
	$ratevote=$conn->real_escape_string(test($_POST['rate'.$submit_rating]));
	if($ratevote!="none")
	{
		if(isset($_SESSION['user']))
		{
			$sql="SELECT student_id FROM student WHERE student_user_id='".$_SESSION['user']."'";
			$row=$conn->query($sql)->fetch_assoc();
						
			$sql="INSERT INTO rating (rating_ratebox, rating_student, rating_value, rating_timestamp) VALUES ('".$submit_rating."','".$row['student_id']."','".$ratevote."',NOW())";
			$conn->query($sql);
		}
		elseif(isset($_SESSION['prof']))
		{
			$sql="SELECT 1 FROM autoedit WHERE autoedit_email_auth IS NOT NULL AND autoedit_prof='".$_SESSION['prof']."'";
			if($conn->query($sql)->num_rows > 0)
			{
				$sql="INSERT INTO rating_byprof (rating_ratebox, rating_prof, rating_value, rating_timestamp) VALUES ('".$submit_rating."','".$_SESSION['prof']."','".$ratevote."',NOW())";
				$conn->query($sql);
			}
		}
		//UPDATE REVIEW RATING
		$sql="SELECT AVG(rating_value) AS rating_avg, COUNT(rating_value) AS rating_nb FROM rating WHERE rating_ratebox='$submit_rating'";
		$row1=$conn->query($sql)->fetch_assoc();
		$sql="SELECT AVG(rating_value) AS rating_avg, COUNT(rating_value) AS rating_nb FROM rating_byprof WHERE rating_ratebox='$submit_rating'";
		$row2=$conn->query($sql)->fetch_assoc();
		$rate_avg=($row1['rating_avg']*$row1['rating_nb']+$row2['rating_avg']*$row2['rating_nb'])/($row1['rating_nb']+$row2['rating_nb']);
//					$sql="UPDATE review SET review_popvote='".ceil($rate_avg)."', review_popvote_nb='".($row1['rating_nb']+$row2['rating_nb'])."' WHERE review_ratebox='$submit_rating'";
		$sql="UPDATE ratebox SET ratebox_popvote='".round($rate_avg)."', ratebox_popvote_nb='".($row1['rating_nb']+$row2['rating_nb'])."' WHERE ratebox_id='$submit_rating'";
		$conn->query($sql);
	}
}
?>

<?php
if(!empty($time_firstsend))
//ENABLE POPULAR VOTE
{
	if((isset($_SESSION['user']) && isset($_SESSION['isstudent'])) || isset($_SESSION['prof']))
	{
		$star1=$star2=$star3=$star4=$star5="";
		if(isset($_SESSION['user']))
		{
			$sql="SELECT 1 FROM moderators m JOIN cmpgn c ON (m.moderators_group=c.cmpgn_moderators_group) WHERE
				(m.moderators_first_user='".$_SESSION['user']."' OR m.moderators_second_user='".$_SESSION['user']."' OR m.moderators_third_user='".$_SESSION['user']."') AND c.cmpgn_id='$cmpgn_id'";
			$visitor_wasmoderator=$conn->query($sql)->num_rows;
		}
		if($visitor_isowner || !empty($visitor_wasmoderator) || !empty($visitor_isreviewprof))
			$disable_vote="disabled";
		else
		{
			if(isset($_SESSION['user']))
				$sql="SELECT r.rating_value FROM rating r JOIN student s ON (r.rating_student=s.student_id)
					JOIN cmpgn c ON (c.cmpgn_ratebox=r.rating_ratebox)
					WHERE s.student_user_id='".$_SESSION['user']."' AND c.cmpgn_id='$cmpgn_id'";
			else $sql="SELECT r.rating_value FROM rating_byprof r 
				JOIN cmpgn c ON (c.cmpgn_ratebox=r.rating_ratebox)
				WHERE r.rating_prof='".$_SESSION['prof']."' AND c.cmpgn_id='$cmpgn_id'";
/*			if(isset($_SESSION['user']))
				$sql="SELECT r.rating_value FROM rating r JOIN student s ON (r.rating_student=s.student_id) WHERE s.student_user_id='".$_SESSION['user']."' AND r.rating_ratebox='$ratebox'";
			else $sql="SELECT rating_value FROM rating_byprof WHERE rating_prof='".$_SESSION['prof']."' AND rating_ratebox='$ratebox'";*/
			$result2=$conn->query($sql);
			if($result2->num_rows > 0)
			{
				//$disable_vote="disabled";
				$already_rated=TRUE;
				$row=$result2->fetch_assoc();
				$yourrating=$row['rating_value'];
/*				$row=$result->fetch_assoc();
				if($row['rating_value']==1) $star1="selected";
				if($row['rating_value']==2) $star2="selected";
				if($row['rating_value']==3) $star3="selected";
				if($row['rating_value']==4) $star4="selected";
				if($row['rating_value']==5) $star5="selected";*/
			}
			elseif(isset($_SESSION['prof']) && $conn->query("SELECT 1 FROM autoedit WHERE autoedit_email_auth IS NOT NULL AND autoedit_prof='".$_SESSION['prof']."'")->num_rows == 0)
				$disable_vote="disabled";
			else $disable_vote="";
		}
		if(empty($already_rated)) $vote_panel='<form method="post" action="">Your score:<br>
			<select name="rate'.$ratebox.'" '.$disable_vote.'>
			<option value="none">--</option>
			<option value="5" '.$star5.'>5 stars</option>
			<option value="4" '.$star4.'>4 stars</option>
			<option value="3" '.$star3.'>3 stars</option>
			<option value="2" '.$star2.'>2 stars</option>
			<option value="1" '.$star1.'>1 star</option>
    				</select>
			<button style="padding-right: 0px; padding-left: 0px;"
			name="submit_rating" value="'.$ratebox.'" '.$disable_vote.'>Rate</button><br></form>';
	}
	else $vote_panel="";
}
?>		
        <?php
        //RETRIEVE AND PRINT ALL UPLOADS IN THIS BLOCK
		$sql="SET @version=0;";
		$result=$conn->query($sql);
		$sql="SELECT @version:=@version+1 AS version, upload_id, upload_timestamp, upload_abstract_text, upload_file1, upload_file2, upload_file3 FROM upload WHERE upload_cmpgn='".$cmpgn_id."' ORDER BY version DESC";
		$result=$conn->query($sql);
		
		if($result->num_rows > 1)
			echo '<br><input id="textexpand" class="checkbox_arrow" type="checkbox"><label for="textexpand"></label>';
		echo '<h3>Uploads</h3>';
		
		if(!empty($time_firstsend))
		{
			if(empty($vote_panel) && ((isset($_SESSION['user']) && isset($_SESSION['isstudent'])) || isset($_SESSION['prof'])))
			{
				$sql="SELECT ratebox_popvote, ratebox_popvote_nb FROM ratebox WHERE ratebox_id='$ratebox'";
				$row2=$conn->query($sql)->fetch_assoc();
				$vote_panel='<div class="upload_buttons" style="background-color: gold; padding: 2px; color: dimgrey"><img title="Your score: '.$yourrating.'" alt="Rating" src="images/'.$row2['ratebox_popvote'].'-star.png">
					<br>('.$row2['ratebox_popvote_nb'].' votes)</div>';
			}
			else $vote_panel='<div class="upload_buttons" style="background-color: gold; padding: 2px">'.$vote_panel.'</div>';
		}
		else $vote_panel="";
		if(!empty($disable_vote)) $vote_panel="";
		
		while($row=$result->fetch_assoc())
		{
/*			if($visitor_isowner && !$isarchivized && empty($time_finalized))
				$upload_sendbutton='<img class="upload_buttons" style="border: 1px solid red; background-color: white" title="Your rating: '.$yourrating.'" alt="Send-button" src="images/send.png">';
			else $upload_sendbutton="";*/
			$upload_footer='<div class="upload_footer"> Version '.$row['version'].', uploaded '.$row['upload_timestamp'].','.$vote_panel.'<br>
            	License CC BY SA 4.0, International </div>';
            $vote_panel="";//ONLY DISPLAYED FOR TOPMOST UPLOAD
			$icon1=""; if(!empty($row['upload_file1'])) $icon1='<img src="images/pdf_icon_18x18.png" style="vertical-align: text-bottom; background-color: lightgrey" alt=""> ';
			$icon2=""; if(!empty($row['upload_file2'])) $icon2='<img src="images/pdf_icon_18x18.png" style="vertical-align: text-bottom; background-color: lightgrey" alt=""> ';
			$icon3=""; if(!empty($row['upload_file3'])) $icon3='<img src="images/pdf_icon_18x18.png" style="vertical-align: text-bottom; background-color: lightgrey" alt=""> ';
			$file_column='<div id="file_column"><div class="file_list">
				<div style="margin-bottom: 10px">'.$icon1.'<a href="download_pdf.php?upload='.$row['upload_id'].'&nb=1">'.$row['upload_file1'].'</a></div>
                <div style="margin-bottom: 10px">'.$icon2.'<a href="download_pdf.php?upload='.$row['upload_id'].'&nb=2">'.$row['upload_file2'].'</a></div>
                '.$icon3.'<a href="download_pdf.php?upload='.$row['upload_id'].'&nb=3">'.$row['upload_file3'].'</a>
              </div>
            </div>';
			$upload_body='<div class="upload"><div class="top">
            	<div class="abstract">'.$row['upload_abstract_text'].'</div>'.$file_column.'</div>
            	<a style="text-align: right; font-size: smaller; float: right" href="index.php?page=timestamps&upload='.$row['upload_id'].'">View
            	digital certificates</a><br><br>'.$upload_footer.'</div><br>';
            if($result->num_rows==$row['version'])
            	echo $upload_body;
			else echo '<div class="hideable">'.$upload_body.'</div>';
        }
		
        
        ?>
<?php
if($isarchivized)
{
	echo "<h3>Interactions</h3>";
	$sql="SELECT p.prof_id, p.prof_givenname, p.prof_familyname, i.interaction_grade
		FROM interaction i JOIN prof p ON (p.prof_id=i.interaction_with)
		JOIN upload u ON (u.upload_cmpgn=i.interaction_cmpgn)
		JOIN cmpgn c ON (i.interaction_cmpgn=c.cmpgn_id)
		JOIN user us ON (us.user_id=c.cmpgn_user)
		WHERE us.user_pts_misc >= 0 AND u.upload_verdict_summary='1' AND i.interaction_cmpgn='$cmpgn_id' ORDER BY i.interaction_grade DESC LIMIT 5";
	$result=$conn->query($sql);
	if($result->num_rows==0)
		echo "<i>No interactions or not approved.</i>";
	while($row=$result->fetch_assoc())
	{
		$smile_color=($row['interaction_grade'] ? "green" : "red");
		echo '<div class="review" style="width: 90%; line-height: 40px">With <a href="index.php?prof='.$row['prof_id'].'">'.$row['prof_givenname'].' '.$row['prof_familyname'].'</a><img src="images/'.$smile_color.'-smiley-small.png" style="float: right"></div>';
	}	
}	
?>
        
        <?php
 		if(!$isarchivized) echo '<h3>Reviews (by professors)</h3>';
/*			if(isset($_POST['submit_rating']) && isset($_SESSION['user']) && isset($_SESSION['isstudent']) && $_SESSION['user']!=$owner)
			{
				$sql="SELECT student_id FROM student WHERE student_user_id='".$_SESSION['user']."'";
				$row=$conn->query($sql)->fetch_assoc();
				
				$submit_rating=$conn->real_escape_string(test($_POST['submit_rating']));
				if($submit_rating!="none")
				{
					$ratevote=$conn->real_escape_string(test($_POST['rate'.$submit_rating]));
					$sql="INSERT INTO rating (rating_ratebox, rating_student, rating_value, rating_timestamp) VALUES ('".$submit_rating."','".$row['student_id']."','".$ratevote."',NOW())";
					$conn->query($sql);
				
					//UPDATE REVIEW RATING
					$sql="SELECT AVG(rating_value) AS rating_avg, COUNT(rating_value) AS rating_nb FROM rating WHERE rating_ratebox='$submit_rating'";
					echo $row['rating_avg']." ".$row['rating_nb'];
					$sql="UPDATE review SET review_popvote='".ceil($row['rating_avg'])."', review_popvote_nb='".$row['rating_nb']."' WHERE review_ratebox='$submit_rating'";
					$conn->query($sql);
				}
			}*/
			
			if(isset($_POST['update_fav']) && isset($_SESSION['user']) && $_SESSION['user']==$owner)
			{
				$update_fav=$conn->real_escape_string(test($_POST['update_fav'])); //contains review id
				$fav_select=$conn->real_escape_string(test($_POST['fav_select'.$update_fav])); //contains review id
				if($fav_select!="none")
				{
					$sql="UPDATE cmpgn SET cmpgn_rvw_favourite='$update_fav' WHERE cmpgn_id='".$cmpgn_id."'";
					$conn->query($sql);
				}
			}

			$print_comments=FALSE;
        	if(!empty($time_finalized))
				$print_comments=TRUE;
			elseif(isset($_SESSION['prof']))
			{
				$sql="SELECT r.review_grade FROM review r JOIN upload u ON (r.review_upload=u.upload_id) WHERE u.upload_cmpgn='".$cmpgn_id."' AND r.review_prof='".$_SESSION['prof']."'";
				$result=$conn->query($sql);
				$row=$result->fetch_assoc();
				if($result->num_rows > 0 && $row['review_grade']==2)
					$print_comments=TRUE;
				elseif(!$isarchivized) echo '<i>To be published at campaign end</i>.';
			}
			elseif(!$visitor_isowner && !$isarchivized)
        		echo '<i>To be published at campaign end</i>.';
			
			if($print_comments || $visitor_isowner)
			{
				$sql="SELECT r.review_id, r.review_prof, r.review_text, r.review_agreed, r.review_time_requested,
					  r.review_time_submit, r.review_time_tgth_passedon, r.review_time_aborted, r.review_together_with,
					  r.review_aborted_byuser, r.review_grade, r.review_ratebox, r.review_grade_invalidated, r.review_hideifgood FROM review r
					  JOIN upload u ON (r.review_upload=u.upload_id) WHERE u.upload_cmpgn='".$cmpgn_id."' ORDER BY r.review_time_requested DESC";
				$result=$conn->query($sql);
				while($rvw_row=$result->fetch_assoc())
				{
					$r_id=$rvw_row['review_id'];
					$r_prof=$rvw_row['review_prof'];
					$r_text=$rvw_row['review_text'];
					$r_seen=$rvw_row['review_agreed'];
					$r_time_requested=$rvw_row['review_time_requested'];
					$r_time_submit=$rvw_row['review_time_submit'];
					$r_time_tgth_passedon=$rvw_row['review_time_tgth_passedon'];
					$r_time_aborted=$rvw_row['review_time_aborted'];
					$r_aborted_byuser=$rvw_row['review_aborted_byuser'];
					$r_together_with=$rvw_row['review_together_with'];
					$r_grade=$rvw_row['review_grade'];
					$r_ratebox=$rvw_row['review_ratebox'];
					$r_grade_invalidated=$rvw_row['review_grade_invalidated'];
					$r_hideifgood=$rvw_row['review_hideifgood'];
					if(empty($r_text) || (!empty($r_hideifgood) && $r_grade==2 && !$visitor_isowner))
						$shrink='style="min-height: 150px; max-height: 150px"'; else $shrink="";
					if(!empty($r_ratebox))
					{
						$rvw_row2=$conn->query("SELECT ratebox_popvote, ratebox_popvote_nb FROM ratebox WHERE ratebox_id='$r_ratebox'")->fetch_assoc();
						$r_popvote=$rvw_row2['ratebox_popvote'];
						$r_popvote_nb=$rvw_row2['ratebox_popvote_nb'];
					}
					else
					{
						unset($r_popvote);
						unset($r_popvote_nb);
					}
					if(empty($r_time_submit) && empty($r_time_aborted) && empty($r_time_tgth_passedon))
						continue;

					if($r_hideifgood && strlen($r_grade)==0 && !$visitor_ismoderator && !$visitor_isowner)
						continue;

					$sql="SELECT prof_id, prof_image, prof_givenname, prof_familyname FROM prof WHERE prof_id='".$r_prof."'";
					$prof_lbl=prof_label($conn->query($sql)->fetch_assoc()/*,$conn*/);
					
					$vote_panel="";
					if(!empty($r_time_tgth_passedon))
					//print name of prof to whom passed on, set r_grade to zero if passed on prof review bad ...
					{
						$sql="SELECT prof_givenname, prof_familyname FROM prof WHERE prof_id='".$r_together_with."'";
						$row=$conn->query($sql)->fetch_assoc();
						$r_text='<i>The responsibility for this review was passed on to</i> <a href="index.php?prof='.$r_together_with.'">'.$row['prof_givenname'].' '.$row['prof_familyname'].'</a> <i>as of</i> '.$r_time_tgth_passedon.'.';
						$sql="SELECT r.review_time_submit, r.review_grade, r.review_agreed, r.review_time_aborted FROM review r JOIN upload u ON (r.review_upload=u.upload_id)
							  WHERE r.review_prof='".$r_together_with."' AND u.upload_cmpgn='".$cmpgn_id."'";
						$row=$conn->query($sql)->fetch_assoc();
						if(!empty($row['review_grade']) || $row['review_grade']=='0' || !empty($row['review_time_aborted']))
						{
							if(!empty($row['review_time_aborted']) && !empty($row['review_agreed']))
								$r_grade=-1;
							elseif(!empty($row['review_time_aborted']))
								$r_grade=0;
							else $r_grade=$row['review_grade'];
							$r_text=$r_text." <i>The grade reflects the score obtained by the replacement reviewer.</i>";
						}
					}
					else if(!empty($r_time_aborted))
					{
						$r_grade=0;
						if(!empty($r_seen))
						{
							$r_text="<i>Review agreed to but not completed even after multiple reminders, resulting in more than 6 weeks delay.</i>";
							$r_grade=-1;
						}
						else $r_text="<i>Professor declined to inspect material/submit review.</i>";
					}
					elseif(!empty($r_hideifgood) && $r_grade==2 && !$visitor_isowner)
						$r_text="<i>Achieved good score but did not want to publish out of privacy concerns.</i>";
					elseif(!empty($r_grade) || $r_grade=='0')
					//ENABLE POPULAR VOTE
					{
						if((isset($_SESSION['user']) && isset($_SESSION['isstudent'])) || isset($_SESSION['prof']))
						{
							$star1=$star2=$star3=$star4=$star5="";
							if(isset($_SESSION['user']))
							{
								$sql="SELECT 1 FROM moderators m JOIN cmpgn c ON (m.moderators_group=c.cmpgn_moderators_group) WHERE
									(m.moderators_first_user='".$_SESSION['user']."' OR m.moderators_second_user='".$_SESSION['user']."' OR m.moderators_third_user='".$_SESSION['user']."') AND c.cmpgn_id='$cmpgn_id'";
								$visitor_wasmoderator=$conn->query($sql)->num_rows;
							}
							if($visitor_isowner || !empty($visitor_wasmoderator) || !empty($visitor_isreviewprof))
								$disable_vote="disabled";
							else
							{							
								if(isset($_SESSION['user']))
									$sql="SELECT r.rating_value FROM rating r JOIN student s ON (r.rating_student=s.student_id)
										JOIN review rv ON (rv.review_ratebox=r.rating_ratebox)
										WHERE s.student_user_id='".$_SESSION['user']."' AND rv.review_id='$r_id'";
								else $sql="SELECT r.rating_value FROM rating_byprof r
									JOIN review rv ON (rv.review_ratebox=r.rating_ratebox)
									WHERE r.rating_prof='".$_SESSION['prof']."' AND rv.review_id='$r_id'";
								$result2=$conn->query($sql);
								if($result2->num_rows > 0)
								{
									$disable_vote="disabled";
									$row=$result2->fetch_assoc();
									if($row['rating_value']==1) $star1="selected";
									if($row['rating_value']==2) $star2="selected";
									if($row['rating_value']==3) $star3="selected";
									if($row['rating_value']==4) $star4="selected";
									if($row['rating_value']==5) $star5="selected";
								}
								elseif(isset($_SESSION['prof']) && $conn->query("SELECT 1 FROM autoedit WHERE autoedit_email_auth IS NOT NULL AND autoedit_prof='".$_SESSION['prof']."'")->num_rows == 0)
																	$disable_vote="disabled";
								else $disable_vote="";
							}
							$vote_panel='<form method="post" action="">Your score:<br>
								<select name="rate'.$r_ratebox.'" '.$disable_vote.'>
								<option value="none">--</option>
								<option value="5" '.$star5.'>5 stars</option>
								<option value="4" '.$star4.'>4 stars</option>
								<option value="3" '.$star3.'>3 stars</option>
								<option value="2" '.$star2.'>2 stars</option>
								<option value="1" '.$star1.'>1 star</option>
          						</select>
								<button style="padding-right: 0px; padding-left: 0px;"
								name="submit_rating" value="'.$r_ratebox.'" '.$disable_vote.'>Rate</button><br></form>';
						}
						else $vote_panel="";
					}
					if(!empty($r_time_submit))
						$submitted_text=",<br> submitted ".$r_time_submit;
					elseif(!empty($r_time_aborted) && $r_aborted_byuser=='0')
						$submitted_text=",<br> auto-aborted ".$r_time_aborted;
					elseif(!empty($r_time_aborted))
						$submitted_text=",<br> user-aborted ".$r_time_aborted;
					else $submitted_text="";

					if($r_grade=='0')
						$moderator_view='<img title="No answer or very short answer" class="review_smiley" alt="Low grade"
            					src="images/red-smiley.png">';
					elseif($r_grade==1)
						$moderator_view='<img title="Rather superficial answer" class="review_smiley" alt="Mid grade"
            					src="images/orange-smiley.png">';
					elseif($r_grade==2)
						$moderator_view='<img title="An answer to be proud of" class="review_smiley" alt="High grade"
            					src="images/green-smiley.png">';
					elseif($r_grade==-1)
						$moderator_view='<img title="The worst of the worst" class="review_smiley" alt="Beyond bad"
            					src="images/smiley-skull.png">';
					else $moderator_view='<img title="Rating pending" class="review_smiley" alt="No grade"
            					src="images/no-smiley.png">';

            		if(isset($r_popvote) && empty($r_hideifgood) ) $vote_panel='Popular score ('
						.$r_popvote_nb.' votes):<img title="Popular vote" alt="'.$r_popvote.'-star rating" src="images/'.$r_popvote.'-star.png"><br>'
						.$vote_panel;

					if(!empty($r_hideifgood) && $r_grade==2) $fav_disable="disabled"; else $fav_disable="";
					if($r_id==$rvw_favourite)
						$fav_issel="selected";
					else $fav_issel="";
					if($visitor_isowner && empty($r_time_tgth_passedon) && $r_grade==2 && empty($time_finalized))
              				$choose_fav='<div style="float: right">
              						<form method="post" action="">
                					<select name="fav_select'.$r_id.'" '.$fav_disable.'>
                 	 					<option value="none">--</option>
                  						<option value="fav" '.$fav_issel.'>Favourite</option>
                					</select>
                					<br>
                					<button name="update_fav" value="'.$r_id.'" '.$fav_disable.'>Update</button></form>
              					</div>';
					elseif($r_id==$rvw_favourite && !empty($time_finalized)) $choose_fav='<div style="float: right"><img src="images/trophy.png"></div>';
					else $choose_fav='';
												
        			echo '<a id="r'.$r_id.'"></a><div class="review_superframe">
          				<div class="review">
            				<div class="review_inset" '.$shrink.'> '.$r_text.'</div>
            				<div class="upload_footer">'
								.$choose_fav
              					.$prof_lbl.
              					'Requested on '.$r_time_requested.$submitted_text.' </div>
          				</div>
          						Moderator view of this review:<br>
								'.$moderator_view.$vote_panel.'<br>
						<a href="https://www.facebook.com/sharer/sharer.php?u=https://www.myphdidea.org/index.php?cmpgn='.$cmpgn_id.'#r'.$r_id.'" target="_blank"><img src="images/fb_share.png" style="width: 8.25%; opacity:0.6;filter:alpha(opacity=60)"></a>
						<a href="https://twitter.com/share?url=https://www.myphdidea.org/index.php?cmpgn='.$cmpgn_id.'#r'.$r_id.'" target="_blank"><img src="images/tweet.png" style="width: 9%; opacity:0.6;filter:alpha(opacity=60)"></a>
        			</div>';

				}
			}
        	        	
/*function prof_label($prof_row, $conn)
{
	$printname=$prof_row['prof_givenname']." ".$prof_row['prof_familyname'];
	
	$sql="SELECT 1 FROM autoedit WHERE autoedit_prof='".$prof_row['prof_id']."' AND autoedit_image='TRUE'";
	if($conn->query($sql)->num_rows > 0)
		$image_path="user_data/researcher_pictures/".$row['prof_id'].".png";
	else $image_path="images/default.png";

	$image_path='<img alt="" src="'.$image_path.'">';
	$image_path='<a href="index.php?prof='.$prof_row['prof_id'].'">'.$image_path.'</a>';
	$image_path='<div class="icon">'.$image_path.'</div>';
	
	return $image_path.$printname.'<br>';
}*/
        ?>
        
        <?php
        	if(!$isarchivized) echo '<h3>Opinions (by students)</h3>';
			// Create connection
        	if(!empty($time_finalized))
				$print_comments=TRUE;
			elseif(isset($_SESSION['user']) && isset($_SESSION['isstudent']))
			{
				$sql="SELECT c.comment_id, c.comment_accepted, c.comment_msg FROM comment c
					JOIN student s ON (c.comment_student=s.student_id)
					JOIN upload u ON (c.comment_upload=u.upload_id)
					WHERE s.student_user_id='".$_SESSION['user']."' AND u.upload_cmpgn='$cmpgn_id' ORDER BY c.comment_accepted DESC";
				$result=$conn->query($sql);
				if($result->num_rows>0)
				{
					$row=$result->fetch_assoc();
					if($row['comment_accepted']==0)
						$accepted_inwords="(rejected)";
					if($row['comment_accepted'])
					{
						$print_comments=TRUE;
						$accepted_inwords="(accepted)";
					}
					else $print_comments=FALSE;
					if(!empty($row['comment_msg'])) echo "<i>Author reply to your ".$accepted_inwords
						." comment: </i>".$row['comment_msg']."<br><br>";
				}
				else $print_comments=FALSE;
			}
			elseif(isset($_SESSION['prof']))
			{
				$sql="SELECT r.review_grade FROM review r JOIN upload u ON (r.review_upload=u.upload_id) WHERE u.upload_cmpgn='".$cmpgn_id."' AND r.review_prof='".$_SESSION['prof']."'";
				$result=$conn->query($sql);
				$row=$result->fetch_assoc();
				if($result->num_rows > 0 && $row['review_grade']==2)
					$print_comments=TRUE;
				else $print_comments=FALSE; 
			}
			else $print_comments=FALSE;
			
			if($print_comments || $visitor_isowner)
			{
				$sql="SELECT c.comment_id, c.comment_upload, c.comment_student, c.comment_text, c.comment_time_posted,
							 c.comment_time_proposed, c.comment_revealrealname, c.comment_accepted, c.comment_msg FROM comment c
							 JOIN upload u ON (c.comment_upload=u.upload_id) WHERE u.upload_cmpgn='".$cmpgn_id."' ORDER BY c.comment_time_proposed DESC";
				$result=$conn->query($sql);
				while($row=$result->fetch_assoc())
				{
					$comment_id=$row['comment_id'];
					$comment_upload=$row['comment_upload'];
					$comment_student=$row['comment_student'];
					$comment_time_proposed=$row['comment_time_proposed'];
					$comment_accepted=$row['comment_accepted'];
					$comment_msg=$row['comment_msg'];
					$comment_text=$row['comment_text'];
					if($visitor_isowner)
					{
						if(isset($comment_accepted))
							$disabled='disabled="disabled"';
						else $disabled="";
						if($comment_accepted=='0')
						{
							$reject_field='selected="selected"';
							$accept_field='';
						}
						elseif($comment_accepted=='1')
						{
							$accept_field='selected="selected"';
							$reject_field='';
						}
						else
						{
							$accept_field='';
							$reject_field='';
						}
						$vis_selector='<div style="float: right">
              				<select name="vis_selector'.$comment_id.'" '.$disabled.'>
                			<option value="nothing">--</option>
                			<option value="accept" '.$accept_field.'>Accept</option>
                			<option value="reject" '.$reject_field.'>Reject</option>
              				</select><br>
              				<button name="submit_comment" value="'.$comment_id.'" '.$disabled.'>Publish?</button> </div>';
              		}
					else $vis_selector='';/*'<div style="float: right">
						<a href="https://www.facebook.com/sharer/sharer.php?u=https://www.myphdidea.org/index.php?cmpgn='.$cmpgn_id.'#c'.$comment_id.'" target="_blank"><img src="images/fb_share.png" style="width: 60%"></a><br>
						<a href="https://twitter.com/share?url=https://www.myphdidea.org/index.php?cmpgn='.$cmpgn_id.'#c'.$comment_id.'" target="_blank"><img src="images/tweet.png" style="width: 60%"></a></div>';*/
					if($row['comment_revealrealname']==TRUE)
					{
						$sql="SELECT student_user_id, student_familyname, student_givenname,
							  student_selfdescription, student_image, student_socialmedia_link FROM student
							  WHERE student_id='".$comment_student."'";
						$row=$conn->query($sql)->fetch_assoc();
						if($row['student_image']==TRUE)
							$image_path="user_data/profile_pictures/".$row['student_user_id'].".png";
						else $image_path="images/default.png";
						$image_path='<img alt="" src="'.$image_path.'">';
						if(!empty($socialmedialink))
          					$image_path='<a href="index.php?redirect='.$row['student_socialmedia_link'].'">'.$image_path.'</a>';
						if(!empty($row['student_selfdescription']))
							$self_descript=', '.$row['student_selfdescription'];
						else $self_descript="";
						$commentator_label='<div class="icon">'.$image_path.'</div>'.$row['student_givenname'].' '
							.$row['student_familyname'].$self_descript.'<br>';
						$comm_user=$row['student_user_id'];
					}
					else
					{
						$image_path="images/pseudonym.png";
						$image_path='<img alt="" src="'.$image_path.'">';
						$sql="SELECT u.user_pseudonym, u.user_id FROM user u JOIN student s
							  ON (s.student_user_id=u.user_id) WHERE s.student_id='".$comment_student."'";
						$row=$conn->query($sql)->fetch_assoc();
						$commentator_label='<div class="icon">'.$image_path.'</div>'.$row['user_pseudonym'].'<br>';
						$comm_user=$row['user_id'];
					}
					$comment_assembled='<a id="c'.$comment_id.'"></a>
						<div class="comment">
          						<div class="review_inset">'.$comment_text.'</div>
          						<div class="upload_footer">
            					'.$vis_selector.$commentator_label.'Submitted on '.$comment_time_proposed.' </div></div>
							<br>';
/*					if($comm_user==$_SESSION['user'] && !empty($comment_msg))
						$author_reply="<i>Author reply to your comment: </i>".$comment_msg;
					else $author_reply="";*/
					if(isset($_POST['msg'.$comment_id]))
						$prevtext=test($_POST['msg'.$comment_id]);
					else $prevtext="";
					if($comment_accepted==TRUE || ($comment_accepted=='0' && $visitor_isowner))
						echo $comment_assembled;
					elseif($visitor_isowner)
						echo '<form action="" method="post">'.$comment_assembled.'<textarea name="msg'.$comment_id.'" style="width: 400px; height: 50px; margin-left: 15px" placeholder="Please write a short thank-you note here.">'.$prevtext.'</textarea></form><br>';
				}
			}
			elseif(!$isarchivized) echo '<i>To be published at campaign end</i>.';
//			$conn->close();
        ?>
        
      </div>