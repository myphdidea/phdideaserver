<?php
//FIRE THOSE WHO HAVE NOT COMPLETED TASKS
$sql="SELECT s.student_user_id, t.taskentrusted_to, t.taskentrusted_task FROM taskentrusted t JOIN student s ON (t.taskentrusted_to=s.student_id) WHERE t.taskentrusted_completed IS NULL AND NOW() >= t.taskentrusted_timestamp + INTERVAL ";
$result_array=array();
$result_array[]=$conn->query($sql."7 DAY AND t.taskentrusted_urgency='1'");
$result_array[]=$conn->query($sql."3 DAY AND t.taskentrusted_urgency='2'");
$result_array[]=$conn->query($sql."1 DAY AND t.taskentrusted_urgency='3'");
$result_array[]=$conn->query($sql."12 HOUR AND t.taskentrusted_urgency='4'");
$result_array[]=$conn->query($sql."3 HOUR AND t.taskentrusted_urgency='5'");

foreach($result_array as $result)
	while($row=$result->fetch_assoc())
	{
		$taskentrusted_task=$row['taskentrusted_task'];
		$taskentrusted_student=$row['taskentrusted_to'];
		$taskentrusted_user=$row['student_user_id'];
		
		$sql="UPDATE taskentrusted SET taskentrusted_completed='FALSE' WHERE taskentrusted_to='".$row['taskentrusted_to']."' AND taskentrusted_task='$taskentrusted_task'";
		$conn->query($sql);
		
		//SEARCH verdict type
		$sql="SELECT v.verdict_id, v.verdict_type, v.verdict_moderators,
			m.moderators_first_user, m.moderators_second_user, m.moderators_third_user FROM verdict v
			JOIN moderators m ON (m.moderators_id=v.verdict_moderators) WHERE v.verdict_task='$taskentrusted_task'";
		$result2=$conn->query($sql);
		
		if($result2->num_rows > 0)
		{
			$row=$result2->fetch_assoc();
			$verdict_moderators=$row['verdict_moderators'];
			$moderators_first_user=$row['moderators_first_user'];
			$moderators_second_user=$row['moderators_second_user'];
			$moderators_third_user=$row['moderators_third_user'];
			$verdict_id=$row['verdict_id'];
			$verdict_type=$row['verdict_type'];
			
			//get most recent ID in this moderators group
			$row=$conn->query("SELECT m1.moderators_id FROM moderators m1 JOIN moderators m2 ON (m1.moderators_group=m2.moderators_group)
				WHERE m2.moderators_id='$verdict_moderators' ORDER BY m1.moderators_id DESC")->fetch_assoc();
			$recent_id=$row['moderators_id'];
			
			//give minus points and create new moderators with vacancy
			//not executed in case of EXCLUSIVE proposal
			switch($taskentrusted_user)
			{
				case $moderators_first_user:
					$sql="SELECT moderators_time_joined1 FROM moderators WHERE moderators_id='$verdict_moderators'";
					$row=$conn->query($sql)->fetch_assoc();
					$moderators_time_joined=$row['moderators_time_joined1'];
					//SOMEONE FIRED PREVIOUSLY OR FIRED BEFORE ALL POSTS FILLED -> MUST STILL BE ACTIVE IN WATCHLISTS
					if(empty($moderators_second_user) || empty($moderators_third_user))
						$update_watchlists=TRUE;
					$sql_1="INSERT INTO moderators (moderators_group, moderators_second_user, moderators_third_user, moderators_time_joined2, moderators_time_joined3)
						SELECT moderators_group, moderators_second_user, moderators_third_user, moderators_time_joined2, moderators_time_joined3 FROM moderators WHERE moderators_id='$verdict_moderators'";
					//SECOND ONE ONLY USED IF verdict_id UNEQUALS MOST RECENT ID - MAYBE JUST INSERT ALWAYS?
					$sql_2="INSERT INTO moderators (moderators_group, moderators_second_user, moderators_third_user, moderators_time_joined2, moderators_time_joined3)
						SELECT moderators_group, moderators_second_user, moderators_third_user, moderators_time_joined2, moderators_time_joined3 FROM moderators WHERE moderators_id='$recent_id'";
					//WRONG NEED TO GET MOST RECENT ID
					break;
				case $moderators_second_user:
					$sql="SELECT moderators_time_joined2 FROM moderators WHERE moderators_id='$verdict_moderators'";
					$row=$conn->query($sql)->fetch_assoc();
					$moderators_time_joined=$row['moderators_time_joined2'];
					if(empty($moderators_first_user) || empty($moderators_third_user))
						$update_watchlists=TRUE;
					$sql_1="INSERT INTO moderators (moderators_group, moderators_first_user, moderators_third_user, moderators_time_joined1, moderators_time_joined3)
						SELECT moderators_group, moderators_first_user, moderators_third_user, moderators_time_joined1, moderators_time_joined3 FROM moderators WHERE moderators_id='$verdict_moderators'";
					//SECOND ONE ONLY USED IF verdict_id UNEQUALS MOST RECENT ID - MAYBE JUST INSERT ALWAYS?
					$sql_2="INSERT INTO moderators (moderators_group, moderators_first_user, moderators_third_user, moderators_time_joined1, moderators_time_joined3)
						SELECT moderators_group, moderators_first_user, moderators_third_user, moderators_time_joined1, moderators_time_joined3 FROM moderators WHERE moderators_id='$recent_id'";
					break;
				case $moderators_third_user:
					$sql="SELECT moderators_time_joined3 FROM moderators WHERE moderators_id='$verdict_moderators'";
					$row=$conn->query($sql)->fetch_assoc();
					$moderators_time_joined=$row['moderators_time_joined3'];
					if(empty($moderators_first_user) || empty($moderators_second_user))
						$update_watchlists=TRUE;
					$sql_1="INSERT INTO moderators (moderators_group, moderators_first_user, moderators_second_user, moderators_time_joined1, moderators_time_joined2)
						SELECT moderators_group, moderators_first_user, moderators_second_user, moderators_time_joined1, moderators_time_joined2 FROM moderators WHERE moderators_id='$verdict_moderators'";
					//SECOND ONE ONLY USED IF verdict_id UNEQUALS MOST RECENT ID - MAYBE JUST INSERT ALWAYS?
					$sql_2="INSERT INTO moderators (moderators_group, moderators_first_user, moderators_second_user, moderators_time_joined1, moderators_time_joined2)
						SELECT moderators_group, moderators_first_user, moderators_second_user, moderators_time_joined1, moderators_time_joined2 FROM moderators WHERE moderators_id='$recent_id'";
					break;
			}

			//EXCLUSIVE PROPOSAL FAILED -> PREPARE FOR DISTRIBUTING INTO WATCHLISTS
			$usepriority=0;
			if(empty($moderators_time_joined))
			{
				$new_moderators_id=$verdict_moderators;
				$conn->query("UPDATE student SET student_taskexcl_cmpgn='FALSE', student_taskexcl_feat='FALSE' WHERE student_id='$taskentrusted_student'");
				$conn->query("DELETE FROM watchlist WHERE watchlist_user='$taskentrusted_user' AND watchlist_moderators='$verdict_moderators'");
				if($conn->query("SELECT 1 FROM task WHERE task_id='$taskentrusted_task' AND task_time_created + INTERVAL 3 DAY > NOW()")->num_rows > 0)
					$usepriority=1;
			}
			else
			{
				$conn->query($sql_1);
				$sql="SELECT LAST_INSERT_ID();";
				$new_moderators_id=$conn->query($sql)->fetch_assoc();
				$new_moderators_id=$new_moderators_id['LAST_INSERT_ID()'];
				$conn->query("UPDATE verdict SET verdict_moderators='$new_moderators_id' WHERE verdict_moderators='$verdict_moderators' AND (verdict_time1 IS NULL OR verdict_time2 IS NULL OR verdict_time3 IS NULL)");
				
				if($recent_id!=$verdict_moderators)
				{
					$conn->query($sql_2);
					$sql="SELECT LAST_INSERT_ID();";
					$new_moderators_id=$conn->query($sql)->fetch_assoc();
					$new_moderators_id=$new_moderators_id['LAST_INSERT_ID()'];
					$conn->query("UPDATE verdict SET verdict_moderators='$new_moderators_id' WHERE verdict_moderators='$recent_id' AND (verdict_time1 IS NULL OR verdict_time2 IS NULL OR verdict_time3 IS NULL)");
					if(!empty($update_watchlists))
						$conn->query("UPDATE watchlist SET watchlist_moderators='$new_moderators_id' WHERE watchlist_moderators='$recent_id'");
				}
				if(!empty($update_watchlists))
					$conn->query("UPDATE watchlist SET watchlist_moderators='$new_moderators_id' WHERE watchlist_moderators='$verdict_moderators'");

				//RE-ADD ALL THOSE FROM PREVIOUS INSTITUTION
				$conn->query("UPDATE watchlist w JOIN student s1 ON (w.watchlist_user=s1.student_user_id)
					JOIN student s2 ON (s1.student_institution=s2.student_institution)
					JOIN moderators m1 ON (w.watchlist_moderators=m1.moderators_id)
					JOIN moderators m2 ON (m2.moderators_group=m1.moderators_group) SET w.watchlist_enrolled='0'
					WHERE NOT EXISTS (SELECT 1 FROM moderators m WHERE m.moderators_group=m1.moderators_group AND
					(m.moderators_first_user=s1.student_user_id OR m.moderators_second_user=s1.student_user_id OR m.moderators_third_user=s1.student_user_id))
					AND s2.student_id='$taskentrusted_student' AND m2.moderators_id='$verdict_moderators'");
				
				$sql="UPDATE user SET user_pts_fail=user_pts_fail+2 WHERE user_id='".$taskentrusted_user."'";
				$fired_from="You exceeded the deadline and have been fired from your job ";
				switch($verdict_type)
				{
					case 'RVW':
					case 'SEND':
					case 'UPLOAD':
						//PAY OUT REMAINING IDEA POINTS
						$idea_pts_suppl=2*floor((strtotime("now")-strtotime($moderators_time_joined))/(6*7*24*60*60));
						$conn->query("UPDATE student SET student_cmpgn_shadowed_latest=NULL,
							student_pts_cmpgn=student_pts_cmpgn+{$idea_pts_suppl} WHERE student_id='$taskentrusted_student'");
						$fired_from=$fired_from."on the shadowed campaign.";
						if(strtotime($moderators_time_joined." + 4 weeks") > strtotime("now") && $conn->query("SELECT 1 FROM cmpgn c
							JOIN upload u ON (c.cmpgn_id=u.upload_cmpgn) WHERE cmpgn_type_isarchivized='1' AND upload_verdict='$verdict_id'")->num_rows==0)
						{
							$fired_from=$fired_from." Because you haven't occupied the post for long enough, extra minus points have been awarded!";
							$sql="UPDATE user SET user_pts_fail=user_pts_fail+5 WHERE user_id='".$taskentrusted_user."'";
						}
						break;
					case 'FTR':
						if(strtotime("now") > strtotime($moderators_time_joined." + 1 month"))
							$conn->query("UPDATE student SET student_feat_shadowed_latest=NULL,
								student_pts_feat=student_pts_feat+1 WHERE student_id='$taskentrusted_student'");
						else $conn->query("UPDATE student SET student_feat_shadowed_latest=NULL WHERE student_id='$taskentrusted_student'");
						$fired_from=$fired_from."on the shadowed feature.";
						break;
					case 'USER':
						$fired_from=$fired_from."of vetting a new student.";
						break;
				}
				$conn->query($sql);
				
				send_notification($conn, $taskentrusted_user, 4, "You are fired!", $fired_from, '', '');
			}
			
			//ADD THESE MODERATORS TO JOB WATCHLIST OF SELECTED USERS - HOW TO SELECT USERS?
			addto_watchlists($conn,$new_moderators_id,$verdict_type,$taskentrusted_task, 2*(int)$usepriority, 1/*, $moderators_first_user, $moderators_second_user, $moderators_third_user*/);
		}

	}

?>