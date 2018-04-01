<?php
$verdict_id=$conn->real_escape_string(test($_GET['verdict']));

$owner=$title=$time_launched=$time_firstsend=$time_finalized=$error_msg="";
$sql="SELECT c.cmpgn_id, c.cmpgn_title, c.cmpgn_user, c.cmpgn_time_launched,
	c.cmpgn_time_firstsend, c.cmpgn_time_finalized, c.cmpgn_rvw_favourite FROM cmpgn c
	JOIN moderators m ON (c.cmpgn_moderators_group=m.moderators_group)
	JOIN verdict v ON (m.moderators_id=v.verdict_moderators) WHERE verdict_id='".$verdict_id."'";
$row=$conn->query($sql)->fetch_assoc();
$title=$row['cmpgn_title'];
$cmpgn_id=$row['cmpgn_id'];
$owner=$row['cmpgn_user'];
$time_launched=$row['cmpgn_time_launched'];
$time_firstsend=$row['cmpgn_time_firstsend'];
$time_finalized=$row['cmpgn_time_finalized'];
$rvw_favourite=$row['cmpgn_rvw_favourite'];

if(isset($_SESSION['user']))
{
	$sql="SELECT v.verdict_task, v.verdict_moderators, m.moderators_first_user, m.moderators_second_user, m.moderators_third_user
		FROM moderators m JOIN verdict v ON (m.moderators_id=v.verdict_moderators)
		WHERE (m.moderators_first_user='".$_SESSION['user']."' OR m.moderators_second_user='".$_SESSION['user']."' OR m.moderators_third_user='".$_SESSION['user']."')
		AND v.verdict_type='RVW' AND v.verdict_id='$verdict_id'";
	$result=$conn->query($sql);
	if($result->num_rows > 0)
	{
		//find send upload id
		$sql="SELECT p.prof_familyname, p.prof_givenname, p.prof_id, r.review_hideifgood,
			  r.review_id, r.review_prof, r.review_text, r.review_time_requested, r.review_time_submit
			  FROM review r JOIN prof p ON (r.review_prof=p.prof_id) WHERE r.review_gradedby_verdict='".$verdict_id."'";
		$rvw_row=$conn->query($sql)->fetch_assoc();

		$r_id=$rvw_row['review_id'];
		$r_prof=$rvw_row['prof_id'];
		$r_prof_familyname=$rvw_row['prof_familyname'];
		$r_text=$rvw_row['review_text'];
		$r_time_requested=$rvw_row['review_time_requested'];
		$r_time_submit=$rvw_row['review_time_submit'];
		$r_hideifgood=$rvw_row['review_hideifgood'];

		$sql="SELECT prof_id, prof_givenname, prof_familyname FROM prof WHERE prof_id='".$r_prof."'";
		$prof_lbl=prof_label($rvw_row/*,$conn*/);
		$submitted_text=",<br> submitted ".$r_time_submit;

		$review_panel='<div class="review" style="width: 90%">
           			<div class="review_inset"> '.$r_text.'</div>
           			<div class="upload_footer">'
						.$prof_lbl.
           				'Requested on '.$r_time_requested.$submitted_text.' </div>
        		</div>';
		
		$row=$result->fetch_assoc();
		$verdict_task=$row['verdict_task'];
		$verdict_moderators=$row['verdict_moderators'];
		$moderators_first_user=$row['moderators_first_user'];
		$moderators_second_user=$row['moderators_second_user'];
		$moderators_third_user=$row['moderators_third_user'];

		$sql="SELECT t.taskentrusted_timestamp, taskentrusted_completed, s.student_id FROM taskentrusted t JOIN student s
			  ON (t.taskentrusted_to=s.student_id) WHERE s.student_user_id='".$_SESSION['user']."' AND t.taskentrusted_task='$verdict_task'";
		$row=$conn->query($sql)->fetch_assoc();
		$student_id=$row['student_id'];
		$time_due=date("Y-m-d H:i:s",strtotime($row['taskentrusted_timestamp']." + 7 days"));
		if(strtotime('now') > strtotime($row['taskentrusted_timestamp']." + 7 days"))
		{
			$error_msg=$error_msg."Ouch verdict time is now past!<br>";
			$sql="UPDATE taskentrusted SET taskentrusted_completed='FALSE' WHERE taskentrusted_to='$student_id' AND taskentrusted_task='$verdict_task'";
			$conn->query($sql);
			
			//give minus points and create new moderators with vacancy
			switch($_SESSION['user'])
			{
				case $moderators_first_user:
					$sql="SELECT moderators_time_joined1 FROM moderators WHERE moderators_id='$verdict_moderators'";
					$row=$conn->query($sql)->fetch_assoc();
					$moderators_time_joined=$row['moderators_time_joined1'];
					$sql="INSERT INTO moderators (moderators_group, moderators_second_user, moderators_third_user, moderators_time_joined2, moderators_time_joined3)
						SELECT moderators_group, moderators_second_user, moderators_third_user, moderators_time_joined2, moderators_time_joined3 WHERE moderators_id='$verdict_moderators'";
					break;
				case $moderators_second_user:
					$sql="SELECT moderators_time_joined2 FROM moderators WHERE moderators_id='$verdict_moderators'";
					$row=$conn->query($sql)->fetch_assoc();
					$moderators_time_joined=$row['moderators_time_joined2'];
					$sql="INSERT INTO moderators (moderators_group, moderators_first_user, moderators_third_user, moderators_time_joined1, moderators_time_joined3)
						SELECT moderators_group, moderators_first_user, moderators_third_user, moderators_time_joined1, moderators_time_joined3 WHERE moderators_id='$verdict_moderators'";
					break;
				case $moderators_third_user:
					$sql="SELECT moderators_time_joined3 FROM moderators WHERE moderators_id='$verdict_moderators'";
					$row=$conn->query($sql)->fetch_assoc();
					$moderators_time_joined=$row['moderators_time_joined3'];
					$sql="INSERT INTO moderators (moderators_group, moderators_first_user, moderators_third_user, moderators_time_joined1, moderators_time_joined3)
						SELECT moderators_group, moderators_first_user, moderators_third_user, moderators_time_joined1, moderators_time_joined3 WHERE moderators_id='$verdict_moderators'";
					break;
			}

			$conn->query($sql);
			$sql="SELECT LAST_INSERT_ID();";
			$new_moderators_id=$conn->query($sql)->fetch_assoc();
			$new_moderators_id=$new_moderators_id['LAST_INSERT_ID()'];
			
			$sql="UPDATE verdict SET verdict_moderators='$new_moderators_id' WHERE verdict_id='$verdict_id'";
			$conn->query($sql);
			
			if(strtotime($moderators_time_joined." + 1 month") > strtotime("now"))
				$sql="UPDATE user SET user_pts_fail=user_pts_fail+5 WHERE user_id='".$_SESSION['user']."'";
			else $sql="UPDATE user SET user_pts_fail=user_pts_fail+2 WHERE user_id='".$_SESSION['user']."'";
			$conn->query($sql);
		}
		elseif($row['taskentrusted_completed']=='')
		{

			if(isset($_POST['judge']))
			{
				$appr_or_decl=$conn->real_escape_string(test($_POST['appr_or_decl']));
				if($appr_or_decl=="none")
					$error_msg=$error_msg."Please select a verdict !<br>";

				if(empty($error_msg))
				{
					//PROCEED TO INSERTION
					switch($appr_or_decl)
					{
						case "red":
							$verdict="NULL";
							break;
						case "orange":
							$verdict="'0'";
							break;
						case "green":
							$verdict="'1'";
							break;
					}

					//IF VERDICT COMPLETE, SEND EMAIL
					include("includes/render_verdict.php");
				}
			}
						
		}
		elseif($row['taskentrusted_completed']=='1') $error_msg=$error_msg."Already completed this task!<br>";
		else $error_msg=$error_msg."Too late to render judgment now!<br>";
	}
	else $error_msg=$error_msg."Not a moderator for this verdict!<br>";
}
else
{
	$_SESSION['after_login']="https://www.myphdidea.org/index.php?workbench=reviewverdict&verdict=".$verdict_id;
	header("Location: index.php?confirm=after_login");
}
?>
      <div id="centerpage">
	  <h1>New verdict</h1>
	  <h2>on <?php echo '<a href="index.php?cmpgn='.$cmpgn_id.'">'.$title.'</a>'; ?></h2>
<form method="post" action="">
<?php if(isset($error_msg)) echo '<div style="color: red; text-align: center">'.$error_msg.'</div>';?>
        <h3>Review verdict (due <?php echo $time_due; ?>)</h3>

		Prof. <?php echo $r_prof_familyname; ?> has submitted his review of "<?php echo $title; ?>". In order to reward professors who engage seriously with student ideas, you and your fellow moderator can now grade the review. The grade will show up in Prof. <?php echo $r_prof_familyname; ?>'s profile, along with the review itself.
		<p>
		<?php echo $review_panel; ?>
		</p>
        As usual, an unanimous verdict among moderators is called for, else penalty points will be given. In order to ensure objective assessment, please take a moment to peruse our editorial guidelines:<br>
        <p><?php include("includes/editorial_guidelines.php"); ?></p> 
        <p>Your grade?
          <select name="appr_or_decl">
            <option value="none">--</option>
            <option value="red">Red</option>
            <option value="orange">Orange</option>
            <option value="green">Green</option>
          </select>
          <button name="judge">Render verdict</button> 
          </p></form>
      </div>
