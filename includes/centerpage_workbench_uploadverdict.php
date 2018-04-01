<?php
$verdict_id=$conn->real_escape_string(test($_GET['verdict']));

$owner=$title=$time_launched=$time_firstsend=$time_finalized=$error_msg="";
$sql="SELECT c.cmpgn_id, c.cmpgn_title, c.cmpgn_user, c.cmpgn_time_launched, c.cmpgn_time_firstsend, c.cmpgn_time_finalized,
	c.cmpgn_type_isarchivized FROM cmpgn c
	JOIN moderators m ON (c.cmpgn_moderators_group=m.moderators_group)
	JOIN verdict v ON (m.moderators_id=v.verdict_moderators) WHERE verdict_id='".$verdict_id."'";
$row=$conn->query($sql)->fetch_assoc();
$title=$row['cmpgn_title'];
$cmpgn_id=$row['cmpgn_id'];
$owner=$row['cmpgn_user'];
$time_launched=$row['cmpgn_time_launched'];
$time_firstsend=$row['cmpgn_time_firstsend'];
$time_finalized=$row['cmpgn_time_finalized'];
if($row['cmpgn_type_isarchivized'])
	header("Location: index.php?workbench=archiveverdict&verdict=".$verdict_id);

if(isset($_SESSION['user']))
{
	$sql="SELECT v.verdict_task, v.verdict_moderators, m.moderators_first_user, m.moderators_second_user, m.moderators_third_user
		FROM moderators m JOIN verdict v ON (m.moderators_id=v.verdict_moderators)
		WHERE (m.moderators_first_user='".$_SESSION['user']."' OR m.moderators_second_user='".$_SESSION['user']."' OR m.moderators_third_user='".$_SESSION['user']."')
		AND v.verdict_type='UPLOAD' AND v.verdict_id='$verdict_id'";
	$result=$conn->query($sql);
	if($result->num_rows > 0)
	{
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
/*			$sql="UPDATE taskentrusted SET taskentrusted_completed='FALSE' WHERE taskentrusted_to='$student_id' AND taskentrusted_task='$verdict_task'";
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
			$conn->query($sql);*/
		}
		elseif($row['taskentrusted_completed']=='')
		{
			$sql="SET @version=0;";
			$result=$conn->query($sql);
			$sql="SELECT @version:=@version+1 AS version, upload_id, upload_verdict, upload_timestamp, upload_abstract_text, upload_file1, upload_file2, upload_file3
				FROM upload WHERE upload_cmpgn='$cmpgn_id' ORDER BY version DESC";
			$result=$conn->query($sql);
			if($result->num_rows==0) $error_msg=$error_msg."Does not seem to be an upload verdict!<br>";
			
			while($row=$result->fetch_assoc())
			{
				if($row['upload_verdict']!=$verdict_id)
					continue;
				$upload_id=$row['upload_id'];
				$tot_nb=!empty($row['upload_file1'])+!empty($row['upload_file2'])+!empty($row['upload_file3']);
				$upload_sendbutton="";
				$upload_footer='<div class="upload_footer"> Version '.$row['version'].', uploaded '.$row['upload_timestamp'].','.$upload_sendbutton.'<br>
            		License CC BY SA 4.0, International </div>';
				$icon1=""; if(!empty($row['upload_file1'])) $icon1='<img src="images/pdf_icon_18x18.png" style="vertical-align: text-bottom; background-color: lightgrey" alt=""> ';
				$icon2=""; if(!empty($row['upload_file2'])) $icon2='<img src="images/pdf_icon_18x18.png" style="vertical-align: text-bottom; background-color: lightgrey" alt=""> ';
				$icon3=""; if(!empty($row['upload_file3'])) $icon3='<img src="images/pdf_icon_18x18.png" style="vertical-align: text-bottom; background-color: lightgrey" alt=""> ';
				$file_column='<div id="file_column"><div class="file_list">
					<div style="margin-bottom: 10px">'.$icon1.'<a target="_blank" href="download_pdf.php?upload='.$row['upload_id'].'&nb=1">'.$row['upload_file1'].'</a></div>
                	<div style="margin-bottom: 10px">'.$icon2.'<a target="_blank" href="download_pdf.php?upload='.$row['upload_id'].'&nb=2">'.$row['upload_file2'].'</a></div>
                	'.$icon3.'<a target="_blank" href="download_pdf.php?upload='.$row['upload_id'].'&nb=3">'.$row['upload_file3'].'</a>
              		</div>
            	</div>';
				$upload_body='<div class="upload"><div class="top">
            		<div class="abstract">'.$row['upload_abstract_text'].'</div>'.$file_column.'</div>
            		<a style="text-align: right; font-size: smaller; float: right" href="index.php?page=timestamps&upload='.$row['upload_id'].'">View
            		digital certificates</a><br><br>'.$upload_footer.'</div><br>';
			}
			
			$inspect_nb=isset($_SESSION['upload_'.$upload_id.'_1'])+isset($_SESSION['upload_'.$upload_id.'_2'])+isset($_SESSION['upload_'.$upload_id.'_3']);
			if(isset($_POST['submit']) && ($inspect_nb>=$tot_nb))
			{
				if($conn->real_escape_string(test($_POST['appr_or_decl']))=="none")
					$error_msg=$error_msg."Please either accept or decline!<br>";
				else
				{
					include("includes/render_verdict.php");
/*					if(test($_POST['appr_or_decl'])=="appr")
						$verdict=1;
					else $verdict=0;
					
					$sql="UPDATE taskentrusted SET taskentrusted_completed='1' WHERE taskentrusted_to='$student_id' AND taskentrusted_task='$verdict_task'";
					$conn->query($sql);
					
					switch($_SESSION['user'])
					{
						case $moderators_first_user:
							$sql="UPDATE verdict SET verdict_1st='$verdict', verdict_time1=NOW() WHERE verdict_id='$verdict_id'";
							break;
						case $moderators_second_user:
							$sql="UPDATE verdict SET verdict_2nd='$verdict', verdict_time2=NOW() WHERE verdict_id='$verdict_id'";
							break;
						case $moderators_third_user:
							$sql="UPDATE verdict SET verdict_3rd='$verdict', verdict_time3=NOW() WHERE verdict_id='$verdict_id'";
							break;
					}
					
					$conn->query($sql);

					$sql="SELECT 1 FROM verdict WHERE verdict_1st IS NOT NULL AND verdict_2nd IS NOT NULL AND verdict_3rd IS NOT NULL AND verdict_id='$verdict_id'";
					if($conn->query($sql)->num_rows > 0)
					{
						//set task_completed, update verdict_summary, give minus points, send notifications
						$sql="UPDATE task SET task_time_completed=NOW() WHERE task_id='$verdict_task'";
						$conn->query($sql);
						
						$sql="SELECT verdict_1st, verdict_2nd, verdict_3rd, verdict_1st+verdict_2nd+verdict_3rd AS verdict_sum FROM verdict WHERE verdict_id='$verdict_id'";
						$row=$conn->query($sql)->fetch_assoc();
						$verdict_sum=$row['verdict_sum'];
						if($row['verdict_sum'] >=2)
							$sql="UPDATE upload SET upload_verdict_summary='1' WHERE upload_id='$upload_id'";
						else $sql="UPDATE upload SET upload_verdict_summary='0' WHERE upload_id='$upload_id'";
						$conn->query($sql);
						
						if($row['verdict_sum'] == 2)
						{
							if($row['verdict_1st']=='0')
								$sql="UPDATE user SET user_pts_fail=user_pts_fail+1 WHERE user_id='$moderators_first_user'";
							elseif($row['verdict_2nd']=='0')
								$sql="UPDATE user SET user_pts_fail=user_pts_fail+1 WHERE user_id='$moderators_second_user'";
							elseif($row['verdict_3rd']=='0')
								$sql="UPDATE user SET user_pts_fail=user_pts_fail+1 WHERE user_id='$moderators_third_user'";
							$conn->query($sql);
						}
						elseif($row['verdict_sum'] == 1)
						{
							if($row['verdict_1st']=='1')
								$sql="UPDATE user SET user_pts_fail=user_pts_fail+1 WHERE user_id='$moderators_first_user'";
							elseif($row['verdict_2nd']=='1')
								$sql="UPDATE user SET user_pts_fail=user_pts_fail+1 WHERE user_id='$moderators_second_user'";
							elseif($row['verdict_3rd']=='1')
								$sql="UPDATE user SET user_pts_fail=user_pts_fail+1 WHERE user_id='$moderators_third_user'";
							$conn->query($sql);
						}

						$student_array=array($moderators_first_user,$moderators_second_user,$moderators_third_user,$owner);
						
//						if($verdict_sum >= 2)
//						{
//							$sql="UPDATE cmpgn SET cmpgn_time_firstsend=NOW() WHERE cmpgn_id='$cmpgn_id'";
//							$conn->query($sql);
//						}

						if($verdict_sum >= 2)
							$text="The upload verdict on ".$title." has resulted in acceptance! Hence, this campaign can now be sent out to professors.";
						else $text="The upload verdict on ".$title." has resulted in refusal! Revisions will have to be made before the material from this campaign can be forwarded to professors.";
						
						foreach($student_array as $student_item)
						//HOW TO HANDLE APOSTATE STUDENTS?
						if(!empty($student_item))
						{
							//IF USER SETTINGS ALLOW, SEND EMAIL
							send_notification($conn, $student_item, 2, 'Verdict completed', $text,
								'','');

						}
						
					}*/
//					header("Location: index.php?workbench=confirm");
				}
			}
			elseif(isset($_POST['submit']))
				$error_msg=$error_msg."Please check all documents currently only checked ".(isset($_SESSION['upload_'.$upload_id.'_1'])+isset($_SESSION['upload_'.$upload_id.'_2'])+isset($_SESSION['upload_'.$upload_id.'_3']))."/".$tot_nb."!<br>";
		}
		elseif($row['taskentrusted_completed']=='1') $error_msg=$error_msg."Already completed this task!<br>";
		else $error_msg=$error_msg."Too late to render judgment now!<br>";
	}
	else $error_msg=$error_msg."Not a moderator for this verdict!<br>";
}
?>
      <div id="centerpage">
	  <h1>New verdict</h1>
	  <h2>on <?php echo '<a href="index.php?cmpgn='.$cmpgn_id.'">'.$title.'</a>'; ?></h2>
<?php if(isset($error_msg)) echo '<div style="color: red; text-align: center">'.$error_msg.'</div>';?>
        <h3>Upload request (verdict due <?php echo $time_due; ?>)</h3>
        <p>Before we forward project ideas out to professors, we wish to ensure that the material
           attains a certain standard. With this perspective, please go through the attached
           pdf files and form your opinion. Documents to check: <?php echo $tot_nb; ?></p>
		<?php echo $upload_body; ?>
		You should approve this upload if:
		<ul>
			<li>It's innovative and the innovation is (also) scientific, i.e.<br> not just a new application for old technology, nor just a business idea</li>
			<li>Some bibliographizing (2-3 references) so that the novelty can be expressed in existing terminology (as e.g. combination of subject A with subject B)</li>
			<li>Analysis has turned up 1-2 technical difficulties i.e. the project is not trivial</li>
			<li>The difficulties are likely solvable with current technology</li>
			<li>There's a well defined problem not just "it will change the world!"</li>
			<li>Not obviously substandard presentation</li>
		</ul>
		Please try to estimate the time that was invested into writing this proposal, which should have been 2-3 months part time (evening and weekends) for approval.
       	<form method="post" action="">
        <p>Approve or decline this upload?
          <select name="appr_or_decl" style="width: 200px">
            <option value="none">--</option>
            <option value="appr">Approve</option>
            <option value="decl">Decline (Reason: Not scientifically novel)</option>
            <option value="decl">Decline (Reason: Novelty not clearly stated as combination of existing results)</option>
            <option value="decl">Decline (Reason: Expected technical difficulties not mentioned)</option>
            <option value="decl">Decline (Reason: Not feasible in &lt; 5 years)</option>
            <option value="decl">Decline (Reason: Overly broad or problem addressed not well defined)</option>
            <option value="decl">Decline (Reason: No PowerPoint or very short/sloppy one)</option>
            <option value="decl">Decline (Reason: Contains obviously misplaced material)</option>
            <option value="decl">Decline (Reason: Not in English)</option>
          </select>
          <button name="submit">OK</button> 
          </p></form>
      </div>
