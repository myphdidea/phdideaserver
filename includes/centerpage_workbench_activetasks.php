<?php
if(isset($_SESSION['user']))
{
	//CHECK WHETHER REDEEMED FROM FAILURE
	if((isset($_SESSION['isstudent']) && $conn->query("SELECT 1 FROM user u
		JOIN student s ON (s.student_user_id=u.user_id) WHERE u.user_id='".$_SESSION['user']."' AND 
		s.student_pts_cmpgn_consmd+u.user_pts_fail-s.student_pts_cmpgn-s.student_pts_feat-u.user_pts_misc
		- {$idea_pts_suppl} <= 0")->num_rows) || (!isset($_SESSION['isstudent']) && $conn->query("SELECT 1
		FROM user WHERE user_id='".$_SESSION['user']."' AND user_pts_fail-user_pts_misc <= 0")->num_rows))
		$conn->query("UPDATE user SET user_rank=0 WHERE user_id='".$_SESSION['user']."'");
	//CHECK WHETHER USER PTS TOO LOW
	elseif((isset($_SESSION['isstudent']) && $conn->query("SELECT 1 FROM user u
		JOIN student s ON (s.student_user_id=u.user_id) WHERE u.user_id='".$_SESSION['user']."' AND 
		s.student_pts_cmpgn_consmd+u.user_pts_fail-s.student_pts_cmpgn-s.student_pts_feat-u.user_pts_misc
		- {$idea_pts_suppl} >= FLOOR((u.user_rank+1)*5.5)")->num_rows) || (!isset($_SESSION['isstudent']) && $conn->query("SELECT 1
		FROM user WHERE user_id='".$_SESSION['user']."' AND user_pts_fail-user_pts_misc >= FLOOR((user_rank+1)*5.5)")->num_rows))
	{
		$conn->query("UPDATE user SET user_rank=user_rank+1, user_lastblock=NOW() WHERE user_id='".$_SESSION['user']."'");
		header("Location: logout.php");
	}
		
	//ADD TO EVERYONE AFTER DELAY PASSED
	$sql="SELECT v.verdict_id, v.verdict_moderators, v.verdict_type, v.verdict_task FROM verdict v
		JOIN moderators m ON (v.verdict_moderators=m.moderators_id)
		JOIN task t ON (t.task_id=v.verdict_task)
		WHERE ((t.task_time_created + INTERVAL 2 WEEK < NOW() AND v.verdict_type != 'USER')
		OR (t.task_time_created + INTERVAL 1 WEEK < NOW() AND v.verdict_type = 'USER'))
		AND (m.moderators_first_user IS NULL OR m.moderators_second_user IS NULL OR m.moderators_third_user IS NULL)
		AND t.task_time_completed IS NULL
		AND NOT EXISTS (SELECT COUNT(*) AS usercnt, watchlist_user FROM watchlist
		WHERE watchlist_moderators=v.verdict_moderators AND watchlist_enrolled='0'
		GROUP BY watchlist_moderators HAVING usercnt > 100)";
	$result=$conn->query($sql);
	while($row=$result->fetch_assoc())
		if($row['verdict_type']!='USER' || $conn->query("SELECT 1 FROM student WHERE student_initauth_verdict='".$row['verdict_id']."' AND student_verdict_summary IS NOT NULL")->num_rows==0)
			addto_watchlists($conn, $row['verdict_moderators'], $row['verdict_type'], $row['verdict_task'], 0, 0);

	//CLOSE FEATURES BY ATTRIBUTING REMAINING POINTS
	$result=$conn->query("SELECT MAX(m.moderators_id) AS max_mod, s.student_user_id
		FROM moderators m JOIN feature f ON (m.moderators_group=f.feature_moderators_group)
		JOIN student s ON (f.feature_student=s.student_id)
		WHERE f.feature_time_created + INTERVAL 2 MONTH < NOW() AND NOT EXISTS (SELECT 1 FROM featuretext WHERE featuretext_verdict_summary IS NULL AND featuretext_feature=f.feature_id)
		GROUP BY f.feature_id ORDER BY m.moderators_id DESC");
	while($row=$result->fetch_assoc())
	{
		$row2=$conn->query("moderators_first_user, moderators_second_user, moderators_third_user,
			moderators_time_joined1, moderators_time_joined2, moderators_time_joined3 FROM moderators WHERE moderators_id='".$row['max_mod']."'");
		if(strtotime($row2['moderators_time_joined1']."+ 1 month") < strtotime("now")) $conn->query("UPDATE student SET student_pts_feat=student_pts_feat+1 WHERE student_user_id='".$row2['moderators_first_user']."'");
		if(strtotime($row2['moderators_time_joined2']."+ 1 month") < strtotime("now")) $conn->query("UPDATE student SET student_pts_feat=student_pts_feat+1 WHERE student_user_id='".$row2['moderators_second_user']."'");
		if(strtotime($row2['moderators_time_joined3']."+ 1 month") < strtotime("now")) $conn->query("UPDATE student SET student_pts_feat=student_pts_feat+1 WHERE student_user_id='".$row2['moderators_third_user']."'");
		array_map('unlink', glob("user_data/tmp_feat/".$row['student_user_id']."_*.*"));
	}
	$conn->query("UPDATE student s JOIN feature f ON (f.feature_id=s.student_feat_shadowed_latest)
		SET s.student_pts_feat=s.student_pts_feat+1, s.student_feat_shadowed_latest=NULL
		WHERE NOT EXISTS (SELECT 1 FROM featuretext WHERE featuretext_verdict_summary IS NULL AND featuretext_feature=f.feature_id)
		AND f.feature_time_created + INTERVAL 2 MONTH < NOW() AND s.student_feat_shadowed_latest IS NOT NULL");
	$conn->query("UPDATE student s JOIN feature f ON (f.feature_id=s.student_feat_own_latest) SET s.student_feat_own_latest=NULL
		WHERE s.student_feat_own_latest IS NOT NULL AND f.feature_time_created + INTERVAL 2 MONTH < NOW()");
		
			
	//FINALLY, ACTIVE TASKS!		
	$sql="SELECT m.moderators_id, m.moderators_group, g.moderators_group_type,
		m.moderators_first_user, m.moderators_second_user, m.moderators_third_user
		FROM (SELECT * FROM moderators ORDER BY moderators_id DESC) AS m
		JOIN moderators_group g ON (m.moderators_group=g.moderators_group)
		WHERE (m.moderators_first_user IS NULL OR m.moderators_second_user IS NULL OR m.moderators_third_user IS NULL)
		AND EXISTS (SELECT 1 FROM watchlist w WHERE w.watchlist_moderators=m.moderators_id AND w.watchlist_user='".$_SESSION['user']."'
		AND w.watchlist_enrolled='0') AND NOT EXISTS (SELECT 1 FROM taskentrusted t JOIN verdict v ON (t.taskentrusted_task=v.verdict_task)
		WHERE v.verdict_moderators=m.moderators_id AND t.taskentrusted_completed IS NULL
		AND (t.taskentrusted_urgency='3' OR t.taskentrusted_urgency='4'))
		GROUP BY m.moderators_group";
	$result=$conn->query($sql);
	$vacancies="";
	if($result->num_rows > 0)
		while($row=$result->fetch_assoc())
		{
			$sql="SELECT 1 FROM moderators WHERE moderators_group='".$row['moderators_group']."' AND
	  				(moderators_first_user='".$_SESSION['user']."' OR moderators_second_user='".$_SESSION['user']."' OR moderators_third_user='".$_SESSION['user']."')";
			if($conn->query($sql)->num_rows==0 && ($row['moderators_group_type']=='CMPGN' || $row['moderators_group_type']=='ARCHV')
				&& $conn->query("SELECT 1 FROM cmpgn WHERE NOW() > cmpgn_time_launched + INTERVAL MOD(".($row['moderators_group']+$_SESSION['user']).",10) MINUTE AND cmpgn_moderators_group='".$row['moderators_group']."'")->num_rows > 0)
			{
				$sql="SELECT cmpgn_id,cmpgn_title,cmpgn_type_isarchivized,cmpgn_time_firstsend,cmpgn_user FROM cmpgn WHERE cmpgn_moderators_group='".$row['moderators_group']."'";
				$row2=$conn->query($sql)->fetch_assoc();
/*				if($row2['cmpgn_user']!=$_SESSION['user'] && (!isset($_SESSION['isstudent']) || 
					$conn->query("SELECT 1 FROM student s1 JOIN student s2 ON (s1.student_institution=s2.student_institution) WHERE (s1.student_user_id='".$row2['cmpgn_user']."'
						OR s1.student_user_id='".$row['moderators_first_user']."'
						OR s1.student_user_id='".$row['moderators_second_user']."'
						OR s1.student_user_id='".$row['moderators_third_user']."') AND s2.student_user_id='".$_SESSION['user']."'")->num_rows==0))
				{*/
            		if($row2['cmpgn_type_isarchivized'])
						$cmpgn_type_label="(archivized)";
					elseif(empty($row2['cmpgn_time_firstsend']))
						$cmpgn_type_label="(new!)";
					else $cmpgn_type_label="";
            		$vacancies=$vacancies.'<tr>
              				<td style="width: 92.1px;">Campaign '.$cmpgn_type_label.'<br>
              				</td>
              				<td style="width: 284.3px;"><a href="index.php?cmpgn='.$row2['cmpgn_id'].'">'.$row2['cmpgn_title'].'</a><br>
              				</td>
              				<td style="max-width: 30.3px;"><a href="index.php?workbench=enrol&moderators='.$row['moderators_id'].'"><img title="Enrol as moderator" src="images/enrol-small.png"></a><br>
              				</td>
            			</tr>';
//            	}
			}
			elseif($row['moderators_group_type']=='USER')
			{
            	$vacancies=$vacancies.'<tr>
              			<td style="width: 92.1px;">Student authentication<br>
              			</td>
              			<td style="width: 284.3px;">n/a<br>
              			</td>
              			<td style="max-width: 30.3px;"><a href="index.php?workbench=enrol&moderators='.$row['moderators_id'].'"><img title="Enrol as moderator" src="images/enrol-small.png"></a><br>
              			</td>
            		</tr>';
			}
			elseif($row['moderators_group_type']=='FEAT')
			{
				$sql="SELECT f.feature_id, ft.featuretext_title, ft.featuretext_timestamp FROM featuretext ft JOIN feature f ON (ft.featuretext_feature=f.feature_id)
					WHERE f.feature_moderators_group='".$row['moderators_group']."' ORDER BY featuretext_timestamp DESC";
            	$row2=$conn->query($sql)->fetch_assoc();
            	$vacancies=$vacancies.'<tr>
              			<td style="width: 92.1px;">Feature<br>
              			</td>
              			<td style="width: 284.3px;"><a href="index.php?feat='.$row2['feature_id'].'">'.$row2['featuretext_title'].'</a><br>
              			</td>
              			<td style="max-width: 30.3px;"><a href="index.php?workbench=enrol&moderators='.$row['moderators_id'].'"><img title="Enrol as moderator" src="images/enrol-small.png"></a><br>
              			</td>
            		</tr>';
				
			}
		}

	//ASSEMBLE TASKLIST
    $taskentrusted="";
	$sql="SELECT v.verdict_id, v.verdict_type, v.verdict_moderators, t.taskentrusted_timestamp, t.taskentrusted_urgency FROM taskentrusted t
      	JOIN verdict v ON (v.verdict_task=t.taskentrusted_task) JOIN student s ON (t.taskentrusted_to=s.student_id)
      	WHERE t.taskentrusted_completed IS NULL AND s.student_user_id='".$_SESSION['user']."' ORDER BY t.taskentrusted_timestamp ASC LIMIT 4";
    $result=$conn->query($sql);
    while($row=$result->fetch_assoc())
	{
		switch($row['verdict_type'])
		{
			case 'UPLOAD':
				$link="upload"; $type="Upload";
				break;
			case 'USER':
				$link="student"; $type="Student";
				break;
			case 'SEND':
				$link="send"; $type="Send";
				break;
			case 'RVW':
				$link="review"; $type="Review";
				break;
			case 'FTR':
				$link="feat"; $type="Feature";
				break;
		}
		switch($row['taskentrusted_urgency'])
		{
			case '1':
				$addtime="7 days"; break;
			case '2':
				$addtime="3 days"; break;
			case '3':
				$addtime="24 hours"; break;
			case '4':
				$addtime="12 hours"; break;
			case '5':
				$addtime="3 hours"; break;
		}
		$remaining=strtotime($row['taskentrusted_timestamp']."+".$addtime)-strtotime("now");
		$remaining_out=floor($remaining/(60*60*24))."d ".floor(($remaining % 86400)/(60*60))."h ".floor(($remaining % 3600)/60)."m";
		if($remaining > 2*24*60*60)
			$remaining_out='<span style="color: #1ae61a">'.$remaining_out.'</span>';
		else if($remaining > 6*60*60)
			$remaining_out='<span style="color: orange">'.$remaining_out.'</span>';
		else $remaining_out='<span style="color: red">'.$remaining_out.'</span>';
		
		//CHECK FOR PRIORITY PROPOSALS
		if($row['taskentrusted_urgency']==3 || $row['taskentrusted_urgency']==4)
		{
			$sql="SELECT MAX(m.moderators_id) as max_mod FROM moderators m JOIN moderators n
				ON (m.moderators_group=n.moderators_group) WHERE n.moderators_id='".$row['verdict_moderators']."'";
			$row2=$conn->query($sql)->fetch_assoc();
			$link_ext='<a href="index.php?workbench=enrol&moderators='.$row2['max_mod'].'"><img title="Enrol" src="images/enrol-small.png"></a>';
		}
		else $link_ext='<a href="index.php?workbench='.$link.'verdict&verdict='.$row['verdict_id'].'"><img title="Render verdict" src="images/gavel.png"></a>';
		
		$taskentrusted=$taskentrusted.'<tr>
              		<td style="width: 92.1px;">'.$type.'<br>
              		</td>
              		<td style="width: 90px;">'.$row['taskentrusted_timestamp'].'<br>
              		</td>
              		<td style="width: 90px;">'.date("Y-m-d H:i:s",strtotime($row['taskentrusted_timestamp']." + ".$addtime)).'<br>
              		</td>
              		<td style="width: 90px;">'.$remaining_out.'<br>
              		</td>
              		<td style="max-width: 30.3px;">'.$link_ext.'<br>
              		</td>
            	</tr>';
	}

	//ASSEMBLE NOTIFICATIONS
/*	$notifications="";
	$sql="SELECT notification_id, notification_object, notification_text, notification_time FROM notification WHERE notification_user='".$_SESSION['user']."'
		ORDER BY notification_id DESC LIMIT 3";
	$result=$conn->query($sql);
	if($result->num_rows > 0)
		while($row=$result->fetch_assoc())
		{
			$notifications=$notifications.'<tr>
              		<td style="width: 72.1px;">'.$row['notification_object'].'<br>
              		</td>
              		<td style="width: 125px;"><a href="index.php?notification='.$row['notification_id'].'">'.substr($row['notification_text'],0,30).'...</a><br>
              		</td>
              		<td style="width: 105px;">'.$row['notification_time'].'<br>
              		</td>
            	</tr>';
		}*/
	$limit=3;
}
?>
      <div id="centerpage">
      <?php include("includes/notifications_generate.php"); ?>
        <a id="seeall_link" href="index.php?workbench=notifications">See all &gt;&gt;</a>
<?php   if(!empty($taskentrusted)) echo '<h3>Tasks</h3><div class="list" style="height: 210px" id="toplist"><table style="width: 100%">
          <tbody>
            <tr>
              <th style="width: 92.1px;">Type<br>
              </th>
              <th style="width:90px;">Requested<br>
              </th>
              <th style="width:90px;">Due<br>
              </th>
              <th style="width:90px;">Remaining<br>
              </th>
              <th style="width: 35.3px;">Judge<br>
              </th>
            </tr>'.$taskentrusted.'</tbody></table></div>';
		elseif(!empty($cmpgn_shdw) || !empty($feat_shdw))
			echo '<h3>Tasks</h3><div class="list" style="height: 200px" id="toplist"></div>';
		else echo "";
?>
        <h3>Proposals</h3>
                <table style="width: 100%">
          <tbody>
<?php
            if(!empty($vacancies)) echo '<tr>
              <th style="width: 92.1px;">Type<br>
              </th>
              <th style="width: 284.3px;">Name<br>
              </th>
              <th style="width: 35.3px;">Enrol<br>
              </th>
            </tr>'.$vacancies;//'<div class="indentation">'.$vacancies.'</div>';
			else echo '<i>No vacancies at the moment.</i>';
?>
          </tbody>
        </table>
      </div>