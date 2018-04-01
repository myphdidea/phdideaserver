<?php
$error_msg=$opinion="";
if(empty($_SESSION['user']))
	echo "Need to be user to join as moderator!<br>";
elseif(isset($_SESSION['user']))
{
	$moderators_id=$conn->real_escape_string(test($_GET['moderators']));
	
	$sql="SELECT g.moderators_group_type, g.moderators_group, m.moderators_first_user, m.moderators_second_user, m.moderators_third_user
		FROM moderators m JOIN moderators_group g
		ON (m.moderators_group=g.moderators_group) WHERE moderators_id='$moderators_id'";
	$row=$conn->query($sql)->fetch_assoc();
	if($row['moderators_group_type']=='USER' && $conn->query("SELECT 1 FROM student WHERE student_email_auth IS NOT NULL AND student_verdict_summary='1'")->num_rows < 100)
	 	$error_msg="Less than 100 registered students, please wait (or sign up friends!)";
	if(!empty($row) && (empty($row['moderators_first_user']) || empty($row['moderators_second_user']) || empty($row['moderators_third_user'])))
	{
		//check that never before been on this particular moderators group
		$sql="SELECT 1 FROM moderators WHERE moderators_group='".$row['moderators_group']."' AND
			(moderators_first_user='".$_SESSION['user']."' OR moderators_second_user='".$_SESSION['user']."' OR moderators_third_user='".$_SESSION['user']."')";
		$found_prev_moderator=$conn->query($sql)->num_rows;
		
		if($row['moderators_group_type']=='USER' && $conn->query("SELECT 1 FROM moderators_group g JOIN moderators m ON (g.moderators_group=m.moderators_group)
			WHERE g.moderators_group_type='USER' AND ((m.moderators_first_user='".$_SESSION['user']."' AND m.moderators_time_joined1 + INTERVAL 1 DAY > NOW())
			OR (m.moderators_second_user='".$_SESSION['user']."' AND m.moderators_time_joined2 + INTERVAL 1 DAY > NOW())
			OR (m.moderators_third_user='".$_SESSION['user']."' AND m.moderators_time_joined3 + INTERVAL 1 DAY > NOW()))")->num_rows > 0)
			$found_prev_user='TRUE';
		elseif($row['moderators_group_type']=='FEAT' && $conn->query("SELECT 1 FROM moderators_group g JOIN moderators m ON (g.moderators_group=m.moderators_group)
			WHERE g.moderators_group_type='FEAT' AND ((m.moderators_first_user='".$_SESSION['user']."' AND m.moderators_time_joined1 + INTERVAL 1 MONTH > NOW())
			OR (m.moderators_second_user='".$_SESSION['user']."' AND m.moderators_time_joined2 + INTERVAL 1 MONTH > NOW())
			OR (m.moderators_third_user='".$_SESSION['user']."' AND m.moderators_time_joined3 + INTERVAL 1 MONTH > NOW()))")->num_rows > 0)
			$found_prev_feat='TRUE';
			
		//check whether priority offer
		$sql="SELECT t.taskentrusted_task, t.taskentrusted_completed, s.student_id, v.verdict_type
			FROM taskentrusted t
			JOIN verdict v ON (v.verdict_task=t.taskentrusted_task)
			JOIN student s ON (t.taskentrusted_to=s.student_id)
			JOIN moderators m ON (v.verdict_moderators=m.moderators_id)
			WHERE (t.taskentrusted_completed IS NULL OR t.taskentrusted_completed='0')
			AND m.moderators_id='$moderators_id'
			AND s.student_user_id='".$_SESSION['user']."'";
		$result=$conn->query($sql);
		if($result->num_rows > 0)
		{
			$row3=$result->fetch_assoc();
			$cancel_task=$row3['taskentrusted_task'];
			$cancel_student=$row3['student_id'];
			if(is_null($row3['taskentrusted_completed']))
				$cancel_priority='TRUE';

			if(isset($_POST['cancel']))
			{
				$conn->query("UPDATE taskentrusted SET taskentrusted_completed='0' WHERE taskentrusted_task='$cancel_task' AND taskentrusted_to='$cancel_student'");
				if(!$conn->query("DELETE FROM watchlist WHERE watchlist_user='".$_SESSION['user']."' AND watchlist_moderators='$moderators_id'")) echo "Problem with delete!";
				$enable_priority=!empty($conn->query("SELECT 1 FROM task WHERE task_time_created + INTERVAL 3 DAY > NOW() AND task_id='".$cancel_task."'")->num_rows);
				addto_watchlists($conn,$moderators_id,$row3['verdict_type'],$cancel_task, 2*(int)$enable_priority, '1'/*, $moderators_first_user, $moderators_second_user, $moderators_third_user*/);

				header("Location: index.php?workbench=activetasks");
			}
		}

		if($row['moderators_group_type']=='CMPGN' && $conn->query("SELECT 1 FROM student WHERE student_cmpgn_shadowed_latest IS NOT NULL AND student_user_id='".$_SESSION['user']."'")->num_rows > 0)
			$error_msg=$error_msg."Please resign from your shadowed campaign first before enrolling!<br>";
		elseif($row['moderators_group_type']=='FEAT' && $conn->query("SELECT 1 FROM student WHERE student_feat_shadowed_latest IS NOT NULL AND student_user_id='".$_SESSION['user']."'")->num_rows > 0)
			$error_msg=$error_msg."Please finish shadowing your current feature first before enrolling!<br>";
		//check that on watchlist
		$sql="SELECT 1 FROM watchlist WHERE watchlist_moderators='$moderators_id' AND watchlist_user='".$_SESSION['user']."' AND watchlist_enrolled='0'";
		if($conn->query($sql)->num_rows==0)
			$error_msg=$error_msg."Seems this moderators group is not on your watchlist!<br>";
		elseif(empty($cancel_student)&& $conn->query("SELECT 1 FROM taskentrusted t JOIN verdict v ON (v.verdict_task=t.taskentrusted_task)
			WHERE v.verdict_moderators='$moderators_id' AND t.taskentrusted_completed IS NULL AND t.taskentrusted_urgency='3' OR t.taskentrusted_urgency='4'")->num_rows > 0)
			$error_msg=$error_msg."Seems this task is presently being touted as a priority offer to someone else ...<br>";
		elseif($row['moderators_group_type']=='CMPGN' || $row['moderators_group_type']=='ARCHV')
		{
			$sql="SELECT cmpgn_id, cmpgn_user, cmpgn_title, cmpgn_time_launched, cmpgn_time_firstsend,
				  cmpgn_time_finalized, cmpgn_type_isarchivized, cmpgn_visibility_blocked
				  FROM cmpgn WHERE cmpgn_moderators_group='".$row['moderators_group']."'";
			$row2=$conn->query($sql)->fetch_assoc();

			$groupfor="";
			$time_finalized=$row2['cmpgn_time_finalized'];
			$time_launched=$row2['cmpgn_time_launched'];
			$time_firstsend=$row2['cmpgn_time_firstsend'];
			$isarchivized=$row2['cmpgn_type_isarchivized'];
			$cmpgn_id=$row2['cmpgn_id'];
			if($row2['cmpgn_visibility_blocked']=='1')
					$error_msg=$error_msg."Cannot enrol on blocked campaign!<br>";
			if(!$row2['cmpgn_type_isarchivized'] && !isset($_SESSION['isstudent']))
				$error_msg=$error_msg.'Need to be student user to moderate student campaign!<br>';
			elseif(!empty($row2['cmpgn_time_finalized']))
				$error_msg=$error_msg."Cannot enrol on finalized campaign!<br>";
			elseif($row2['cmpgn_user']==$_SESSION['user'])
				$error_msg=$error_msg."Can't be moderator for own campaign!";
			else
			{
				$groupfor='<a href="index.php?cmpgn='.$row2['cmpgn_id'].'">'.$row2['cmpgn_title'].'</a>';
				if($row2['cmpgn_type_isarchivized'])
					$groupfor=$groupfor." <i>(archivized)</i>";
				
				if(!$isarchivized) $advertisment_text="A new job is available as moderator on the campaign above! If you agree to join this campaign,
					you will be rewarded with a base salary of <b>2x</b> Idea points every 6 weeks, plus additional Misc
					points for sundry tasks. You will pledge to occupy your post for at least 4 weeks, else there will
					be a penalty of <b>3x</b> fail points. Tasks parcelled out to you will be given with 3 days or 7 days
					deadline, depending on complexity (you will accrue <b>2x</b> fail points if you do not complete
					a task before the deadline, and get suspended from your role).";
				else $advertisment_text="A quick judgment is needed on the archivized campaign above! We require you to examine the campaign to ascertain
					whether the material is suitable for our website, for which we will reward you with <b>1x</b> Misc points (this is a one-off task).";
				if(!$isarchivized) $termsandconditions='<input type="checkbox" name="confirm_submit">I agree to join this campaign for at least 4 weeks,
					completing my moderator duties promptly and according to editorial guidelines.';
				else $termsandconditions='<input type="checkbox" name="confirm_submit">I agree to examine the material and proposed links,
					completing my moderator duties promptly and according to editorial guidelines.';
			}
		}
		elseif($row['moderators_group_type']=='FEAT')
		{
			$sql="SELECT s.student_user_id, ft.featuretext_title, f.feature_id
				FROM featuretext ft JOIN feature f ON (ft.featuretext_feature=f.feature_id)
				JOIN student s ON (f.feature_student=s.student_id)
				JOIN verdict v ON (ft.featuretext_verdict=v.verdict_id)
				WHERE v.verdict_moderators='$moderators_id' ORDER BY ft.featuretext_timestamp DESC";
			$row2=$conn->query($sql)->fetch_assoc();
			$feat_id=$row2['feature_id'];
			$groupfor='<a href="index.php?feat='.$row2['feature_id'].'">'.$row2['featuretext_title'].'</a>';

			$advertisment_text="A new job is available as moderator of the feature above!
				If you decide to render judgment on this feature, you will be rewarded with a salary of <b>1x</b> Feature points.
				If you find that the feature is lacking, the author will
				be allowed to submit revised versions, at most one every 2 weeks and up to a maximum of
				2 months after submission of the initial draft. Should you indeed have to occupy your post
				for more than 1 month, you will be rewarded with an additional <b>1x</b> Feature points (for a total of <b>2x</b>).
				Verdicts are required with 7 days deadline (you will accrue <b>2x</b> fail points if you do not complete
				a verdict before the deadline, and get suspended from your role).";
			$termsandconditions='<input type="checkbox" name="confirm_submit">I agree to judge this feature
				and (if necessary) revised versions of it promptly and according to editorial guidelines.';

		}
		elseif($row['moderators_group_type']=='USER')
		{
			$groupfor="new student membership decision";
			$advertisment_text="A new application for student membership has been received! We have verified the
				email address, but we now require someone to judge whether the applicant
				has indeed completed 2 years of study, while still being a student (i.e. no PhD yet).
				Should you accept this job, you will be shown name, institution and subject of study
				of the student, as well as a link to the university server for verifying study level.
				If your judgment on the case concurs with that of the 2 other student assigned to the same
				task, you will gain <b>1x</b> Misc points (otherwise, you risk adding <b>1x</b> Fail points).";
			$termsandconditions='<input type="checkbox" name="confirm_submit">I agree to verify this
				student\'s identity immediately (< 3 h) after being shown the next page.';

		}
				
		//PROCEED TO INSERTION
		if(/*isset($_POST['submit']) && */$found_prev_moderator)
			$error_msg=$error_msg."Seems you've already been a moderator for this task previously!<br>";
		elseif(/*isset($_POST['submit']) && */!empty($found_prev_feat))
			$error_msg=$error_msg."Please wait 1 month before arbitrating on your next article!<br>";
		elseif(/*isset($_POST['submit']) && */!empty($found_prev_user))
			$error_msg=$error_msg."Please wait 24 hours before verifying your next user!<br>";
		elseif(isset($_POST['submit']) && isset($_POST['confirm_submit']) && empty($error_msg))
		{
			if(!empty($cancel_student))
				$conn->query("DELETE FROM taskentrusted WHERE taskentrusted_to='$cancel_student' AND taskentrusted_task='$cancel_task'");

			//ONLY FOR CMPGN VERDICT TYPE ...
			if(!empty($row2['cmpgn_id']) && !$isarchivized)
			{
				$conn->query("UPDATE student SET student_cmpgn_shadowed_latest='".$row2['cmpgn_id']."' WHERE student_user_id='".$_SESSION['user']."'");
			}
			elseif(!empty($feat_id)) $conn->query("UPDATE student SET student_feat_shadowed_latest='$feat_id' WHERE student_user_id='".$_SESSION['user']."'");
			
			/*if(!empty($row2['cmpgn_id']) && $isarchivized)
				$conn->query("UPDATE watchlist SET watchlist_enrolled='1' WHERE watchlist_user='".$_SESSION['user']."' AND watchlist_moderators='$moderators_id'");
			else */$conn->query("UPDATE watchlist w JOIN student s1 ON (w.watchlist_user=s1.student_user_id)
				JOIN student s2 ON (s1.student_institution=s2.student_institution) SET w.watchlist_enrolled='1' WHERE s2.student_user_id='".$_SESSION['user']."' AND watchlist_moderators='$moderators_id'");
			unset($result2);
			//PROBLEM HERE: CAN HAVE MISMATCH MODERATOR POSITION <-> VERDICT POSITION
			if(empty($row['moderators_first_user']))
			{
				$sql_a="UPDATE moderators SET moderators_first_user='".$_SESSION['user']."',moderators_time_joined1=NOW() WHERE moderators_id=";
//				$sql_b="SELECT verdict_id, verdict_task, verdict_type FROM verdict WHERE verdict_time1 IS NULL AND verdict_moderators='$moderators_id'";
				$sql_b="SELECT v.verdict_id, v.verdict_task, v.verdict_type, v.verdict_moderators
					FROM verdict v JOIN moderators m1 ON (v.verdict_moderators=m1.moderators_id)
					JOIN moderators m2 ON (m1.moderators_group=m2.moderators_group)
					WHERE v.verdict_time1 IS NULL AND m2.moderators_id='$moderators_id'";
				$result2=$conn->query($sql_b);
			}
			if(empty($row['moderators_second_user']) && $result2->num_rows==0)
			{
				$sql_a="UPDATE moderators SET moderators_second_user='".$_SESSION['user']."',moderators_time_joined2=NOW() WHERE moderators_id=";
//				$sql_b="SELECT verdict_id, verdict_task, verdict_type FROM verdict WHERE verdict_time2 IS NULL AND verdict_moderators='$moderators_id'";
				$sql_b="SELECT v.verdict_id, v.verdict_task, v.verdict_type, v.verdict_moderators
					FROM verdict v JOIN moderators m1 ON (v.verdict_moderators=m1.moderators_id)
					JOIN moderators m2 ON (m1.moderators_group=m2.moderators_group)
					WHERE v.verdict_time2 IS NULL AND m2.moderators_id='$moderators_id'";
				$result2=$conn->query($sql_b);
			}
			if(empty($row['moderators_third_user']) && $result2->num_rows==0)
			{
				$sql_a="UPDATE moderators SET moderators_third_user='".$_SESSION['user']."',moderators_time_joined3=NOW() WHERE moderators_id=";
//				$sql_b="SELECT verdict_id, verdict_task, verdict_type FROM verdict WHERE verdict_time3 IS NULL AND verdict_moderators='$moderators_id'";
				$sql_b="SELECT v.verdict_id, v.verdict_task, v.verdict_type, v.verdict_moderators
					FROM verdict v JOIN moderators m1 ON (v.verdict_moderators=m1.moderators_id)
					JOIN moderators m2 ON (m1.moderators_group=m2.moderators_group)
					WHERE v.verdict_time3 IS NULL AND m2.moderators_id='$moderators_id'";
				$result2=$conn->query($sql_b);
			}

			$conn->query($sql_a."'$moderators_id'");
			$result=$result2;//$result=$conn->query($sql_b);
			
			//if watchlists have run down to zero ...
			if(!empty($cancel_student))
			{
				if($conn->query("SELECT 1 FROM moderators WHERE moderators_id='$moderators_id' AND (moderators_first_user IS NULL OR moderators_second_user IS NULL OR moderators_third_user IS NULL)")->num_rows > 0
					&& $conn->query("SELECT 1 FROM watchlist WHERE watchlist_moderators='$moderators_id' AND watchlist_enrolled='0'")->num_rows == 0)
					addto_watchlists($conn,$moderators_id,$row3['verdict_type'],$cancel_task, 0, '1');

				//CANCEL ALL OTHER EXCLUSIVE OFFERS THAT MIGHT HOLD
				$result3=$conn->query("SELECT v.verdict_moderators, v.verdict_type, v.verdict_task FROM verdict v
					JOIN taskentrusted t ON (v.verdict_task=t.taskentrusted_task)
					JOIN moderators m ON (m.moderators_id=v.verdict_moderators)
					WHERE t.taskentrusted_to='$cancel_student' AND t.taskentrusted_completed IS NULL
					AND m.moderators_first_user!='".$_SESSION['user']."' AND m.moderators_second_user!='".$_SESSION['user']."' AND m.moderators_third_user!='".$_SESSION['user']."'");
				if($result3->num_rows > 0)
					while($row5=$result3->fetch_assoc())
					{
						$conn->query("UPDATE taskentrusted SET taskentrusted_completed='0' WHERE taskentrusted_to='$cancel_student' AND taskentrusted_task='".$row5['verdict_task']."'");
						addto_watchlists($conn,$row5['verdict_moderators'],$row5['verdict_type'],$row5['verdict_task'], 0, '1');
					}
			}
				
			if(isset($_POST['cancel']))
			{
				$conn->query("UPDATE taskentrusted SET taskentrusted_completed='0' WHERE taskentrusted_task='$cancel_task' AND taskentrusted_to='$cancel_student'");
				if(!$conn->query("DELETE FROM watchlist WHERE watchlist_user='".$_SESSION['user']."' AND watchlist_moderators='$moderators_id'")) echo "Problem with delete!";
				$enable_priority=!empty($conn->query("SELECT 1 FROM task WHERE task_time_created + INTERVAL 3 DAY > NOW() AND task_id='".$cancel_task."'")->num_rows);
				addto_watchlists($conn,$moderators_id,$row3['verdict_type'],$cancel_task, 2*(int)$enable_priority, '1'/*, $moderators_first_user, $moderators_second_user, $moderators_third_user*/);

				header("Location: index.php?workbench=activetasks");
			}
			
							
			//CASE WHERE SIGNS UP TO VERDICT WHERE SOMEONE JUST DISENROLLED?
			if($result->num_rows > 0/* && empty($isarchivized)*/)
			{
				$sql="SELECT s.student_id, u.user_turnoff_notifications, u.user_email, s.student_givenname,
					s.student_institution_email, s.student_sendto_instmail
	    			FROM student s JOIN user u
					ON (s.student_user_id=u.user_id) WHERE u.user_id='".$_SESSION['user']."'";
				$row3=$conn->query($sql)->fetch_assoc();
				while($row4=$result->fetch_assoc())
				{
					if($row4['verdict_type']=='SEND')
					{
						$task_urgency='2';
						$witharticle="a send"; $subject="Verdict on send"; $inxdays="3 days";
					}
					elseif($row4['verdict_type']=='USER')
					{
						$task_urgency='5';
						$witharticle="a membership"; $subject="Verdict member"; $inxdays="3 hours";
					}
					else
					{
						$task_urgency='1';
						$inxdays="7 days";
						if($row4['verdict_type']=='RVW')
						{
							$witharticle="a review"; $subject="Verdict review";
						}
						if($row4['verdict_type']=='UPLOAD')
						{
							$witharticle="an upload"; $subject="Verdict upload";
							$verdict_address='https://www.myphdidea.org/index.php?workbench=uploadverdict&cmpgn='.$cmpgn_id.'&verdict='.$row4['verdict_id'];
						}
						if($row4['verdict_type']=='FTR')
						{
							$witharticle="a feature"; $subject="Verdict feature";
							$verdict_address='https://www.myphdidea.org/index.php?workbench=featverdict&feat='.$feat_id.'&verdict='.$row4['verdict_id'];
						}
					}
					//WHAT IF SAME TASK PREVIOUSLY PROPOSED AS EXCLUSIVE OFFER?
					$conn->query("INSERT INTO taskentrusted (taskentrusted_to,taskentrusted_task,taskentrusted_timestamp,taskentrusted_urgency)
								  VALUES ('".$row3['student_id']."','".$row4['verdict_task']."',NOW(),'$task_urgency')");
					if($row4['verdict_moderators']!=$moderators_id)
						$conn->query($sql_a."'".$row4['verdict_moderators']."'");
					//SEND EMAIL IF SETTINGS ALLOW
					if($row4['verdict_type']!='USER') send_notification($conn, $_SESSION['user'], 3, $subject, "You have been asked to render a verdict on ".$witharticle." request as below:\n\n", $verdict_address, "\n\n Please complete this task within the next ".$inxdays);

					if(!empty($row2['cmpgn_id']) && empty($isarchivized))
						$conn->query("UPDATE student SET student_cmpgn_shadowed_latest='".$row2['cmpgn_id']."' WHERE student_user_id='".$_SESSION['user']."'");
					
					//DELETE FROM WATCHLIST
//					$conn->query("DELETE FROM watchlist WHERE watchlist_user='".$_SESSION['user']."' AND watchlist_moderators='$moderators_id'");
					if($verdict_type!='INTERACT')
						$conn->query("UPDATE watchlist w JOIN student s1 ON (w.watchlist_user=s1.student_user_id) 
							JOIN student s2 ON (s1.student_institution=s2.student_institution)SET w.watchlist_enrolled='1' WHERE s2.student_user_id='".$_SESSION['user']."' AND watchlist_moderators='$moderators_id'");
					else $conn->query("UPDATE watchlist SET watchlist_enrolled='1' WHERE watchlist_user='".$_SESSION['user']."' AND watchlist_moderators='$moderators_id'");
										
					if($row4['verdict_type']=='USER')
						header("Location: index.php?workbench=studentverdict&verdict=".$row4['verdict_id']);
					else header("Location: index.php?workbench=activetasks");
				}
			}
			elseif(!$isarchivized)
				if($row['moderators_group_type']=='USER')
					header("Location: index.php?workbench=studentverdict&verdict=".$row4['verdict_id']);
				else header("Location: index.php?workbench=activetasks");
			elseif($isarchivized) ;//DO SOMETHING FOR ARCHIVIZED
		}
		else if(isset($_POST['submit'])) $error_msg="Please tick box to confirm your engagement.<br>";

	}
	else $error_msg="No moderators group or no vacancy any longer on this moderators group!";
	
//	$conn->close();
}
?>
<form method="post" action="">
      <div id="centerpage">
        <h1>Join moderator group</h1>
        <h2>for <?php echo $groupfor; ?></h2>
        <?php if(empty($time_firstsend) && !empty($time_finalized))
						$launched="approval not obtained";
					  elseif(empty($time_firstsend))
        				$launched="not yet approved";
					  else $launched="approved ".$time_firstsend;
			  if($row['moderators_group_type']=='CMPGN' || $row['moderators_group_type']=='ARCHV')
			  {
        		echo "(Created ".$time_launched.", ".$launched.", ";
        		if($isarchivized) echo "archivized)<br>";
        			else if(empty($time_finalized)) echo "still running)<br>";
        			else echo "finalized ".$time_finalized.")<br>";
        	  }
        ?>
        <?php if(!empty($error_msg)) echo '<div style="color: red; text-align: center">'.$error_msg.'</div>';?>
        <p><?php echo $advertisment_text; ?></p>
        <p>Completing work as moderator is asked of you in order to benefit from the same services when
        submitting requests of your own, a reciprocity we quantify in terms of Idea, Feature, Misc and Fail points. Approval through
        our student peer review system is meant to guarantee
        an acceptable level of quality for all submissions, which is what we owe professors if we expect them
        to engage with our site.</p>
        <form method="post" action="">
        <p class="indentation"> <?php echo $termsandconditions; ?> </p>
        <?php if(isset($cancel_priority)) echo "<p><i>This is an exclusive priority offer that has not been made to anyone else yet. Please </i><b>cancel</b><i> if you are not interested so we can give your place to someone else.</i></p>"; ?>
        <p style="text-align: right;"> <?php if(isset($cancel_priority)) echo '<button name="cancel">Cancel</button>'; ?> <button name="submit">Submit</button></p></form>
      </div>
</form>