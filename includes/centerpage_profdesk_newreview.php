<?php
$error_msg="";
if(empty($_SESSION['prof']))
	echo "Review screen only available to researchers please login!<br>";
else
{
	$cmpgn_id=$conn->real_escape_string(test($_GET['cmpgn']));
	
	$sql="SELECT r.review_id, DATE_ADD(r.review_time_requested,INTERVAL 6 WEEK) AS review_time_due, r.review_time_submit,
		  r.review_time_tgth_passedon, r.review_time_aborted, r.review_together_with, r.review_send,
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
		$review_send=$row['review_send'];
		if(!empty($row['review_time_submit']))
			$error_msg="Thank you, your review has now been submitted and will be published soon.<br>";
		elseif(!empty($row['review_time_aborted']))
			$error_msg="Sorry but you have taken too long and can no longer submit a review.<br>";
		elseif(!empty($row['review_time_tght_passedon']))
		{
			$sql="SELECT prof_givenname, prof_familyname FROM prof WHERE prof_id='".$row['review_together_with']."'";
			$result=$conn->query($sql);
			$row2=$result->fetch_assoc();
			$error_msg='Your reviewer responsibility has now been passed on to <a href="index.php?prof="'.$row['review_together_with'].'">'.$row2['prof_givenname'].' '.$row2['prof_familyname'].'</a><br>';
		}

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
		
		if(empty($error_msg) && isset($_POST['submit']))
		{
			$review=$conn->real_escape_string(test($_POST['Review']));
			if(isset($_POST['optout'])) $hideifgood='1'; else $hideifgood='0';
			
			$student_row=$conn->query("SELECT s.student_givenname, s.student_familyname FROM student s
				JOIN cmpgn c ON s.student_user_id=c.cmpgn_user WHERE cmpgn_id='$cmpgn_id'")->fetch_assoc();
			if(strpos($review,$student_row['student_givenname'])!==false || strpos($review,$student_row['student_familyname'])!==false)
				$error_msg=$error_msg."Please avoid mentioning personal names in review!<br>";
			
			if(empty($_POST['Review']))
				$error_msg=$error_msg."Can't have empty review!<br>";
			elseif(strlen($review) < 500 && empty($_POST['short_rvw_confirm']))
				$error_msg=$error_msg.'More than 500 characters recommended for review please confirm: <input type="checkbox" name="short_rvw_confirm"><br>';
			elseif(strlen($review) > 4000)
				$error_msg=$error_msg.'Review too long please moderate yourself!';
			if(!empty($time_finalized) || !empty($isarchivized))
				$error_msg=$error_msg."Finalized or archivized campaign, should not be seeing this ...<br>";
			if(isset($_POST['optout']) && !isset($_POST['optout_confirm']))
				$error_msg=$error_msg.'Sure you want to hide a <i>good</i> review from the world? <input type="checkbox" name="optout_confirm"><br>';
			if(empty($error_msg))
			{
				$sql="INSERT INTO ratebox (ratebox_popvote, ratebox_popvote_nb) VALUES (0, 0)";
				$conn->query($sql);
				$sql="SELECT LAST_INSERT_ID();";
				$newratebox=$conn->query($sql)->fetch_assoc();
				$newratebox=$newratebox['LAST_INSERT_ID()'];

				//REQUEST REVIEW VERDICT!
				$verdict_id=create_verdict($conn, $cmpgn_id, 'RVW');
				
				$sql="UPDATE review SET review_time_submit=NOW(), review_text='$review',
					review_ratebox='$newratebox', review_hideifgood='$hideifgood', review_gradedby_verdict='$verdict_id'
					WHERE review_id='$review_id'";echo $sql;
				$conn->query($sql);	
				
				header("Location: index.php?confirm=submit_review");
			}
		}
	}
}
?>
<form method="post" action="">
      <div id="centerpage">
        <h1>New review</h1>
        <h2>of "<?php echo '<a href="index.php?cmpgn='.$cmpgn_id.'">'.$cmpgn_title.'</a> (v.'.$version.')'; ?>"</h2>
        (<?php  if(empty($time_firstsend) && !empty($time_finalized))
						$launched="approval not obtained";
					  elseif(empty($time_firstsend))
        				$launched="not yet approved";
					  else $launched="approved ".$time_firstsend;
        		echo "Created ".$time_launched.", ".$launched
        			  	.", "; if($isarchivized) echo "archivized";
        					 else if(empty($time_finalized)) echo "still running";
        					 else echo "finalized ".$time_finalized;?>)<br>
        <?php if(!empty($error_msg)) echo '<br><div style="color: red; text-align: center">'.$error_msg.'</div><br>';?>
        <?php if(isset($review_time_due)) echo '<br>Your review is due '.$review_time_due.', in '.floor((strtotime($review_time_due)-time())/(24*60*60)).' days.';?>
        <?php
        	if(isset($review_together_with)) echo 'Also, you now have permission to pass on your reviewer responsibility.';
			elseif(!empty($review_send)) echo 'If you wish to pass on your reviewer responsibility to a colleague, please contact the student via "dialogue".';
        ?>
        <p>This is where you get to submit your review! Please study the material on the previous page, and use the 'dialogue' window to arrange a presentation date with the student. Once you have formed your opinion, please submit your review. <i>myphidea.org</i>
        	guarantees every researcher who has agreed to it 6 weeks from the reception of the first notification e-mail, during which time no other reviews will be solicited (if you need more time, you should request an extension from the student).
        </p>
        <p>The review will be graded by 3 student moderators, and
           a good grade is rewarded by a better reputation score on your profile, early access to
           other reviews on this idea by your colleagues, and (after the campaign ends) publication in the site
           newsfeed. All reviews submitted will we published on the same page as the original idea.</p>

        <p class="indentation"><div style="text-align: center"><textarea style="width: 400px; height: 300px;"

class="indentation" name="Review"><?php if(!empty($review)) echo $review; ?></textarea></div>
        <p>Please take a moment to read through the editorial guidelines used for grading:<br>
		<?php include("includes/editorial_guidelines.php"); ?>
        </p>
		<input type="checkbox" name="optout" <?php if(isset($hideifgood) && $hideifgood=='1') echo 'checked' ?>> Tick if you do <i>not</i> want a "good" review (community judgment) to be published.
        <p style="text-align: right;"> <button name="submit">Submit</button></p>
      </div>
</form>