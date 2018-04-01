<?php
if(isset($_SESSION['user']))
{
	$sql="SELECT c.cmpgn_id, c.cmpgn_title, c.cmpgn_time_launched, c.cmpgn_time_firstsend,
		r.ratebox_popvote, r.ratebox_popvote_nb, c.cmpgn_time_finalized, c.cmpgn_type_isarchivized
		FROM cmpgn c JOIN ratebox r ON (c.cmpgn_ratebox=r.ratebox_id) WHERE cmpgn_user='".$_SESSION['user']."'
		ORDER BY c.cmpgn_time_launched DESC";
	$result=$conn->query($sql);
	$cmpgns="";
	if($result->num_rows > 0)
		while($row=$result->fetch_assoc())
		{
            		if($row['cmpgn_type_isarchivized'])
						$cmpgn_type_label="(archivized)";
					elseif(empty($row['cmpgn_time_firstsend']) && empty($row['cmpgn_time_finalized']))
						$cmpgn_type_label="New!";
					elseif(!empty($row['cmpgn_time_firstsend']) && !empty($row['cmpgn_time_finalized']))
						$cmpgn_type_label="Finished";
					elseif(empty($row['cmpgn_time_firstsend']) && !empty($row['cmpgn_time_finalized']))
						$cmpgn_type_label="Rejected";
					else $cmpgn_type_label="Running";
            		$cmpgns=$cmpgns.'<tr>
              				<td style="width: 82.1px;">'.$row['cmpgn_time_launched'].'<br>
              				</td>
              				<td style="width: 240.3px;"><a href="index.php?cmpgn='.$row['cmpgn_id'].'">'.$row['cmpgn_title'].'</a><br>
              				</td>
              				<td style="max-width: 85.3px;">'.$cmpgn_type_label.'<br>
              				</td>
              				<td style="max-width: 95.3px;"><img alt="Rating" src="images/'.$row['ratebox_popvote'].'-star.png">
								<br>('.$row['ratebox_popvote_nb'].' votes)<br>
              				</td>
            			</tr>';
		}

	$sql="SELECT cm.comment_time_proposed, cm.comment_revealrealname, cm.comment_accepted, c.cmpgn_title, c.cmpgn_id
		FROM comment cm JOIN upload u ON cm.comment_upload=u.upload_id
		JOIN cmpgn c ON (c.cmpgn_id=u.upload_cmpgn)
		JOIN student s ON (cm.comment_student=s.student_id)
		WHERE s.student_user_id='".$_SESSION['user']."' ORDER BY cm.comment_time_proposed DESC";
	$result=$conn->query($sql);
	$comments="";
	if($result->num_rows > 0)
		while($row=$result->fetch_assoc())
		{
            $comments=$comments.'<tr>
            		<td style="width: 82.1px;">'.$row['comment_time_proposed'].'<br>
            		</td>
              		<td style="width: 360.3px;"><a href="index.php?cmpgn='.$row['cmpgn_id'].'">'.$row['cmpgn_title'].'</a><br>
              		</td>
              		<td style="max-width: 30.3px;">'.$row['comment_revealrealname'].'<br>
              		</td>
              		<td style="max-width: 30.3px;">'.$row['comment_accepted'].'<br>
              		</td>
            	</tr>';
		}

	

	$sql="SELECT f.feature_id, ft.featuretext_title, f.feature_time_created, f.feature_time_approved, r.ratebox_popvote, r.ratebox_popvote_nb
		FROM feature f JOIN student s ON (f.feature_student=s.student_id)
		JOIN featuretext ft ON (ft.featuretext_feature=f.feature_id)
		JOIN ratebox r ON (f.feature_ratebox=r.ratebox_id) WHERE s.student_user_id='".$_SESSION['user']."'
		GROUP BY f.feature_id ORDER BY f.feature_time_created DESC";
	$result=$conn->query($sql);
	$feats="";
	if($result->num_rows > 0)
		while($row=$result->fetch_assoc())
		{
			if(empty($row['feature_time_approved']) && strtotime($row['feature_time_created']."+ 2 months") < strtotime("now"))
				$approved="rejected";
			else $approved=$row['feature_time_approved'];
            $feats=$feats.'<tr>
            		<td style="width: 82.1px;">'.$row['feature_time_created'].'<br>
            		</td>
              		<td style="width: 240.3px;"><a href="index.php?feat='.$row['feature_id'].'">'.$row['featuretext_title'].'</a><br>
              		</td>
              		<td style="max-width: 85.3px;">'.$approved.'<br>
              		</td>
              		<td style="max-width: 95.3px;"><img alt="Rating" src="images/'.$row['ratebox_popvote'].'-star.png">
						<br>('.$row['ratebox_popvote_nb'].' votes)<br>
              		</td>
            	</tr>';
		}

	//ASSEMBLE TASKLIST
    $verdicts=""; $nb_success=0;
	$sql="SELECT v.verdict_id, v.verdict_type, v.verdict_time1, v.verdict_time2, v.verdict_time3,
		m.moderators_first_user, m.moderators_second_user, m.moderators_third_user, m.moderators_group,
		v.verdict_1st, v.verdict_2nd, v.verdict_3rd, t.task_id, t.task_time_created FROM verdict v 
		JOIN moderators m ON (v.verdict_moderators=m.moderators_id)
		JOIN task t ON (t.task_id=v.verdict_task)
      	WHERE v.verdict_time1 IS NOT NULL AND v.verdict_time2 IS NOT NULL AND v.verdict_time3 IS NOT NULL
      	AND (m.moderators_first_user='".$_SESSION['user']."' OR m.moderators_second_user='".$_SESSION['user']."' OR m.moderators_third_user='".$_SESSION['user']."') ORDER BY t.task_time_created DESC";
    $result=$conn->query($sql);
    while($row=$result->fetch_assoc())
	{
		switch($_SESSION['user'])
		{
			case $row['moderators_first_user']:
				$grade_own=is_null($row['verdict_1st']) ? -1 : (!empty($row['verdict_1st']) ? 1 : 0);
				$verdict_time=$row['verdict_time1'];
				break;
			case $row['moderators_second_user']:
				$grade_own=is_null($row['verdict_2nd']) ? -1 : (!empty($row['verdict_2nd']) ? 1 : 0);
				$verdict_time=$row['verdict_time2'];
				break;
			case $row['moderators_third_user']:
				$grade_own=is_null($row['verdict_3rd']) ? -1 : (!empty($row['verdict_3rd']) ? 1 : 0);;
				$verdict_time=$row['verdict_time3'];
				break;
		}
		$description="";
		switch($row['verdict_type'])
		{
			case 'USER':
				$row2=$conn->query("SELECT student_givenname, student_familyname, student_verdict_summary
					FROM student WHERE student_initauth_verdict='".$row['verdict_id']."'")->fetch_assoc();
				//$description=$row2['student_givenname']." ".$row2['student_familyname'];
				$description="n/a";
				$grade_collective=$row2['student_verdict_summary'];
				$type="Student";
				break;
			case 'UPLOAD':
				$row2=$conn->query("SELECT upload_verdict_summary FROM upload WHERE upload_verdict='".$row['verdict_id']."'")->fetch_assoc();
				$grade_collective=$row2['upload_verdict_summary'];
				$type="Upload";
				break;
			case 'SEND':
				$row2=$conn->query("SELECT send_verdict_summary FROM send WHERE send_verdict='".$row['verdict_id']."'")->fetch_assoc();
				$grade_collective=$row2['send_verdict_summary'];
//				$row2=$conn->query("SELECT p.prof_id, p.prof_givenname, p.prof_familyname FROM prof p JOIN crowdedit c ON (c.crowdedit_prof=p.prof_id)
//					WHERE crowdedit_task='".$row['task_id']."'")->fetch_assoc();
				$row2=$conn->query("SELECT p.prof_id, p.prof_givenname, p.prof_familyname FROM prof p JOIN send s ON (s.send_prof=p.prof_id)
					WHERE send_verdict='".$row['verdict_id']."'")->fetch_assoc();
				if(!empty($row2['prof_id']))
					$description='<a href="index.php?prof='.$row2['prof_id'].'">'.$row2['prof_givenname'].' '.$row2['prof_familyname'].'</a> from ';
				else $description='?? from ';
				$type="Send";
				break;
			case 'RVW':
				$row2=$conn->query("SELECT p.prof_id, p.prof_givenname, p.prof_familyname, r.review_grade
					FROM prof p JOIN review r ON (p.prof_id=r.review_prof)
					WHERE r.review_gradedby_verdict='".$row['verdict_id']."'")->fetch_assoc();
				$grade_collective=$row2['review_grade']-1;
				$description='<a href="index.php?prof='.$row2['prof_id'].'">'.$row2['prof_givenname'].' '.$row2['prof_familyname'].'</a> on ';
				$type="Review";
				break;
			case 'FTR':
				$row2=$conn->query("SELECT featuretext_feature, featuretext_title, featuretext_verdict_summary
					FROM featuretext WHERE featuretext_verdict='".$row['verdict_id']."'")->fetch_assoc();
				$grade_collective=$row2['featuretext_verdict_summary'];
				$description='<a href="index.php?feat='.$row2['featuretext_feature'].'">'.$row2['featuretext_title'].'</a>';
				$type="Feature";
				break;

		}
		switch($row['verdict_type'])
		{
			case 'UPLOAD':
			case 'SEND':
			case 'RVW':
				$row2=$conn->query("SELECT cmpgn_id, cmpgn_title
					FROM cmpgn WHERE cmpgn_moderators_group='".$row['moderators_group']."'")->fetch_assoc();
				if(!empty($description) && strlen($description)+strlen($row2['cmpgn_title']) > 120)
					$description=$description.'<a href="index.php?cmpgn='.$row2['cmpgn_id'].'">'.substr($row2['cmpgn_title'],0,120-strlen($description))."...</a>";
				else $description=$description.'<a href="index.php?cmpgn='.$row2['cmpgn_id'].'">'.$row2['cmpgn_title']."</a>";
				break;
		}
						
		$verdicts=$verdicts.'<tr>
              		<td style="width: 82.1px;">'.$verdict_time.'<br>
              		</td>
              		<td style="width: 72.1px;">'.$type.'<br>
              		</td>
              		<td style="width: 270px;">'.$description.'<br>
              		</td>
              		<td style="width: 15px;">'.$grade_own.'<br>
              		</td>
              		<td style="max-width: 15.3px;">'.$grade_collective.'<br>
              		</td>
            	</tr>';
		if($grade_collective==$grade_own) $nb_success++;
	}
	
	if($result->num_rows > 0) $nb_success=($nb_success/$result->num_rows)*100;
	
	//ASSEMBLE NOTIFICATIONS
	$limit=3;
}
?>
      <div id="centerpage">
      	<h3>Campaigns</h3>
<?php   if(!empty($cmpgns)) echo '<table style="width: 100%">
          <tbody>
            <tr>
              <th style="width: 82.1px;">Created<br>
              </th>
              <th style="width:240px;">Title<br>
              </th>
              <th style="width:85px;">Status<br>
              </th>
              <th style="width:95px;">Popular<br>
              </th>
            </tr>'.$cmpgns.'</tbody></table>';
		else echo "<i>No campaigns yet.</i>";
?>
      	<h3>Features</h3>
<?php   if(!empty($feats)) echo '<table style="width: 100%">
          <tbody>
            <tr>
              <th style="width: 82.1px;">Created<br>
              </th>
              <th style="width:240px;">Title<br>
              </th>
              <th style="width:85px;">Approved<br>
              </th>
              <th style="width:95px;">Popular<br>
              </th>
            </tr>'.$feats.'</tbody></table>';
		else echo "<i>No features yet.</i>";
?>

      	<h3>Comments</h3>
<?php   if(!empty($comments)) echo '<table style="width: 100%">
          <tbody>
            <tr>
              <th style="width: 82.1px;">Created<br>
              </th>
              <th style="width:360px;">On<br>
              </th>
              <th style="width:30px;">Name?<br>
              </th>
              <th style="width:30px;">OK?<br>
              </th>
            </tr>'.$comments.'</tbody></table>';
		else echo "<i>No comments yet.</i>";
?>

        <h3>Verdicts <?php if(!empty($nb_success)) echo "(success rate ".$nb_success." %)" ?></h3>
                <table style="width: 100%">
          <tbody>
<?php
            if(!empty($verdicts)) echo '<tr>
              <th style="width: 82.1px;">Rendered<br>
              </th>
              <th style="width: 72.1px;">Type<br>
              </th>
              <th style="width: 274.3px;">Description<br>
              </th>
              <th style="width: 15.3px;">I<br>
              </th>
              <th style="width: 15.3px;">O<br>
              </th>
            </tr>'.$verdicts;//'<div class="indentation">'.$vacancies.'</div>';
			else echo '<i>No verdicts yet.</i>';
?>
          </tbody>
        </table>
      </div>