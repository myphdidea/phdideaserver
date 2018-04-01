<?php
$cmpgn_id=$conn->real_escape_string($_GET['cmpgn']);

$error_msg="";
$sql="SELECT c.cmpgn_title, m.moderators_id, m.moderators_group, m.moderators_first_user, m.moderators_time_joined1,
	m.moderators_second_user, m.moderators_time_joined2, m.moderators_third_user, m.moderators_time_joined3
	FROM cmpgn c JOIN moderators m ON (c.cmpgn_moderators_group=m.moderators_group)
	WHERE c.cmpgn_id='$cmpgn_id' AND (c.cmpgn_type_isarchivized IS NULL OR c.cmpgn_type_isarchivized!='1') ORDER BY m.moderators_id DESC";
$result=$conn->query($sql);
if($result->num_rows > 0)
{
	$row=$result->fetch_assoc();
	$title=$row['cmpgn_title'];
	$moderators_id=$row['moderators_id'];
	$moderators_first_user=$row['moderators_first_user']; $moderators_time_joined1=$row['moderators_time_joined1'];
	$moderators_second_user=$row['moderators_second_user']; $moderators_time_joined2=$row['moderators_time_joined2'];
	$moderators_third_user=$row['moderators_third_user']; $moderators_time_joined3=$row['moderators_time_joined3'];
	$moderators_group=$row['moderators_group'];
	
	//get most recent ID in this moderators group
	$row=$conn->query("SELECT m1.moderators_id FROM moderators m1 JOIN moderators m2 ON (m1.moderators_group=m2.moderators_group)
		WHERE m2.moderators_id='$moderators_id' ORDER BY m1.moderators_id DESC")->fetch_assoc();
	$recent_id=$row['moderators_id'];
	
	switch($_SESSION['user'])
	{
		case $moderators_first_user:
			$moderators_time_joined=$moderators_time_joined1;
			if(empty($moderators_second_user) || empty($moderators_third_user))
				$update_watchlists='TRUE';
			//SHOULDN'T USE MOST RECENT ID HERE? 
			$sql="INSERT INTO moderators (moderators_group, moderators_second_user, moderators_third_user, moderators_time_joined2, moderators_time_joined3)
				SELECT moderators_group, moderators_second_user, moderators_third_user, moderators_time_joined2, moderators_time_joined3 FROM moderators WHERE moderators_id='$recent_id'";
			break;
		case $moderators_second_user:
			$moderators_time_joined=$moderators_time_joined2;
			if(empty($moderators_first_user) || empty($moderators_third_user))
				$update_watchlists='TRUE';
			$sql="INSERT INTO moderators (moderators_group, moderators_first_user, moderators_third_user, moderators_time_joined1, moderators_time_joined3)
				SELECT moderators_group, moderators_first_user, moderators_third_user, moderators_time_joined1, moderators_time_joined3 FROM moderators WHERE moderators_id='$recent_id'";
			break;
		case $moderators_third_user:
			$moderators_time_joined=$moderators_time_joined3;
			if(empty($moderators_first_user) || empty($moderators_second_user))
				$update_watchlists='TRUE';
			$sql="INSERT INTO moderators (moderators_group, moderators_first_user, moderators_second_user, moderators_time_joined1, moderators_time_joined2)
				SELECT moderators_group, moderators_first_user, moderators_second_user, moderators_time_joined1, moderators_time_joined2 FROM moderators WHERE moderators_id='$recent_id'";
			break;
	}
	
	if(empty($moderators_time_joined))
		$error_msg=$error_msg."Not a moderator for this campaign, or not any longer!<br>";
	else
	{
		$punishment_due=strtotime($moderators_time_joined." + 4 weeks");
		if($punishment_due > strtotime("now"))
		{
			$get_punishment='TRUE';
			$punishment_due_days=ceil(($punishment_due-strtotime("now"))/(24*60*60));
			$punishment_due_date=date("Y-m-d H:i:s",$punishment_due);
		}
		$weeks_offset=6;
		do {
			$salary_due=strtotime($moderators_time_joined." + {$weeks_offset} weeks ");
			$weeks_offset+=6;
		}while($salary_due < strtotime("now"));
		$salary_due_days=ceil(($salary_due-strtotime("now"))/(24*60*60));
		$salary_due_date=date("Y-m-d H:i:s",$salary_due);
		if(isset($_POST['submit']) && empty($_POST['agree']))
			$error_msg=$error_msg."Please confirm you really want to withdraw!<br>";
		elseif(isset($_POST['submit']) && $conn->query("SELECT 1 FROM verdict v
						JOIN taskentrusted t ON (v.verdict_task=t.taskentrusted_task)
						JOIN student s ON (s.student_id=t.taskentrusted_to)
						WHERE v.verdict_moderators='$moderators_id' AND s.student_user_id='".$_SESSION['user']."' AND t.taskentrusted_completed IS NULL")->num_rows > 0)
			$error_msg=$error_msg."Not finished all tasks yet!<br>";
		elseif(isset($_POST['submit']))
		{
			$conn->query($sql);
			$sql="SELECT LAST_INSERT_ID();";
			$new_moderators_id=$conn->query($sql)->fetch_assoc();
			$new_moderators_id=$new_moderators_id['LAST_INSERT_ID()'];

			if(!empty($update_watchlists))
				$conn->query("UPDATE watchlist SET watchlist_moderators='$new_moderators_id' WHERE watchlist_moderators='$moderators_id'");
//VERDICT HAS TO BE COMPLETED WITH PREVIOUS MODERATORS
//			$conn->query("UPDATE verdict SET verdict_moderators='$new_moderators_id' WHERE verdict_moderators='$moderators_id' AND (verdict_time1 IS NULL OR verdict_time2 IS NULL OR verdict_time3 IS NULL)");
			//RE-ADD ALL THOSE FROM PREVIOUS INSTITUTION
			$conn->query("UPDATE watchlist w JOIN student s1 ON (w.watchlist_user=s1.student_user_id)
				JOIN student s2 ON (s1.student_institution=s2.student_institution)
				JOIN moderators m1 ON (w.watchlist_moderators=m1.moderators_id)
				JOIN moderators m2 ON (m2.moderators_group=m1.moderators_group) SET w.watchlist_enrolled='0'
				WHERE NOT EXISTS (SELECT 1 FROM moderators m WHERE m.moderators_group=m1.moderators_group AND
				(m.moderators_first_user=s1.student_user_id OR m.moderators_second_user=s1.student_user_id OR m.moderators_third_user=s1.student_user_id))
				AND s2.student_user_id='".$_SESSION['user']."' AND m2.moderators_id='$moderators_id'");
			
			$idea_pts_suppl=2*floor((strtotime("now")-strtotime($moderators_time_joined))/(6*7*24*60*60));
			$conn->query("UPDATE student SET student_cmpgn_shadowed_latest=NULL,
				student_pts_cmpgn=student_pts_cmpgn+{$idea_pts_suppl} WHERE student_user_id='".$_SESSION['user']."'");

			if($get_punishment)
				$conn->query("UPDATE user SET user_pts_fail=user_pts_fail+3 WHERE user_id='".$_SESSION['user']."'");
			
			$row=$conn->query("SELECT v.verdict_id, v.verdict_type, v.verdict_task FROM verdict v
				JOIN moderators m1 ON (v.verdict_moderators=m1.moderators_id)
				JOIN moderators m2 ON (m2.moderators_group=m1.moderators_group)
				WHERE m2.moderators_id='$moderators_id' ORDER BY v.verdict_id DESC")->fetch_assoc();
			addto_watchlists($conn,$new_moderators_id,$row['verdict_type'],$row['verdict_task'], 2, 1);
			
			header("Location: index.php?workbench=activetasks");
		}
	}
}
else $error_msg=$error_msg."Not a valid campaign id!<br>";
?>
<div id="centerpage">
<h1>Resignation</h1>
<h2>from <a href="index.php?cmpgn=<?php echo $cmpgn_id; ?>"><?php echo $title; ?></a></h2>
<?php if(isset($error_msg)) echo '<div style="color: red; text-align: center">'.$error_msg.'</div><br>';?>
We are sad to see you go! Your next <b>2x</b> Idea points salary is due <?php echo $salary_due_date.", in ".$salary_due_days." days."; ?> You may want to
consider waiting until then to withdraw from your role as moderator. <?php if(isset($get_punishment)) echo "Also, since you have been on this job for less than 4 weeks, 
you might want to consider waiting ".$punishment_due_days." days until ".$punishment_due_date." in order to escape the <b>3x</b> Fail points penalty for early withdrawal." ?><br><br>
Note that you first have to finish all running tasks before you can use this option.<br><br>
<form method="post" action="">
<input type="checkbox" name="agree" <?php if(isset($_POST['agree'])) echo "checked"; ?>> I still wish to put down my job as moderator on this campaign.
<p style="text-align: right;"><button name="submit">Withdraw</button></p></form>
</div>