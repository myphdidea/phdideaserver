<?php
if(isset($_SESSION['user']) && $_SESSION['user']=='1')
{	
	if(isset($_POST['submit']) && isset($_POST['students']))
	{
		foreach($_POST['students'] as $item)
			if(substr($item,0,4)!="none")
			{
				$result=$conn->query("SELECT m1.moderators_id FROM moderators m1 JOIN moderators m2 ON (m1.moderators_group=m2.moderators_group)
					JOIN verdict v ON (m2.moderators_id=v.verdict_moderators) JOIN student s ON (v.verdict_id=s.student_initauth_verdict)
					WHERE s.student_id='".substr($item,4)."'");
				while($row=$result->fetch_assoc())
					$conn->query("DELETE FROM watchlist WHERE watchlist_moderators='".$row['moderators_id']."'
						AND NOT EXISTS (SELECT 1 FROM moderators WHERE moderators_id='".$row['moderators_id']."'
						AND (moderators_first_user=watchlist_user OR moderators_second_user=watchlist_user OR moderators_third_user=watchlist_user))");
				
				$row=$conn->query("SELECT student_user_id, student_givenname, student_familyname FROM student WHERE student_id='".substr($item,4)."'")->fetch_assoc();
				if(substr($item,0,4)=="accp")
					$text="The student verdict for ".$row['student_givenname']." ".$row['student_familyname']." has resulted in acceptance! Everyone rejoice now that we can welcome a new member to our site. ";
				else $text="The student verdict on ".$row['student_givenname']." ".$row['student_familyname']." has resulted in refusal! Likely, this student would be better served by other publication platforms at this career stage.";

				send_notification($conn, $row['student_user_id'], 2, "Verdict completed", $text,'','');
				
				if(substr($item,0,4)=="accp") $verdict_rendered=1;
				elseif(substr($item,0,4)=="decl") $verdict_rendered=0;
				
				$conn->query("UPDATE student SET student_verdict_summary='$verdict_rendered' WHERE student_id='".substr($item,4)."'");
				$conn->query("UPDATE task t JOIN verdict v ON (t.task_id=v.verdict_task)
					JOIN student s ON (s.student_initauth_verdict=v.verdict_id) SET t.task_time_completed=NOW() WHERE student_id='".substr($item,4)."'");
				if(file_exists('user_data/transcripts/'.substr($item,4).'.pdf')) unlink('user_data/transcripts/'.substr($item,4).'.pdf');
			}
		update_watchlist($conn,'USER');
	}
	
	$result=$conn->query("SELECT s.student_id, s.student_givenname, s.student_familyname, s.student_annuary_link, s.student_annuary_instructions,
		s.student_institution_email, u.user_subject1, u.user_subject2, s.student_verdict_summary, s.student_time_created FROM student s JOIN user u ON (s.student_user_id=u.user_id)
		WHERE s.student_email_auth IS NOT NULL ORDER BY IF(s.student_verdict_summary IS NULL, 1,0) DESC, s.student_time_created DESC LIMIT 30");
	if($result->num_rows > 0)
	{
		$recent_students="";
		while($row=$result->fetch_assoc())
		{
			$warning="";
			if(is_null($row['student_verdict_summary']))
			{
				$controls='<select name="students[]" style="width: 60px">
					<option value="none" >--</option>
					<option value="accp'.$row['student_id'].'" >Accept</option>
					<option value="decl'.$row['student_id'].'" >Decline</option>
          		</select><br>';
				if(strtotime($row['student_time_created']." + 1 week") < strtotime("now"))
					$warning='color: red';
			}
			elseif($row['student_verdict_summary']=='1') $controls="OK";
			elseif($row['student_verdict_summary']=='0') $controls="X";
			
			if(file_exists('user_data/transcripts/'.$row['student_id'].'.pdf')) $transcript=', <a target="_blank" href="download_privatepdf.php?student='.$row['student_id'].'">file.pdf</a>'; else $transcript="";
			
			$recent_students=$recent_students.'<tr>
            	<td style="word-break: break-all; '.$warning.'" >'.str_replace('@','@<br>',$row['student_institution_email']).'<br>
             	</td>
             	<td><a href="'.html_entity_decode(html_entity_decode(stripcslashes($row['student_annuary_link']))).'">'.$row['student_givenname'].' '.$row['student_familyname'].'</a><br>
             	</td>
             	<td style="font-size:small">'.$row['student_annuary_instructions'].$transcript.'<br>
             	</td>
             	<td>'.$row['user_subject1'].', '.$row['user_subject2'].'<br>
             	</td>
             	<td style="text-align: center">'.$controls.'<br>
             	</td>
           	</tr>';
		}
		$recent_students='<table style="width: 100%" border="0">
          	<tbody>
            	<tr>
             	 	<th style="width: 90.1px;"><br>
              		</th>
              		<th style="width: 90.3px;">Name<br>
              		</th>
              		<th style="width: 140.3px;">Instructions<br>
              		</th>
              		<th style="width: 30.3px;">Subj<br>
              		</th>
              		<th style="width: 60.3px; text-align: center">Verdict<br>
              		</th>
            	</tr>'.$recent_students.'</tbody>
        	</table><p style="text-align: right"><button name="submit">Submit</button></p>';
	}
}
else $recent_students="Page only available to superuser!";
?>
<div id="centerpage">
	<form method="post" action="">
	<?php if(!empty($recent_students)) echo $recent_students; ?>
	</form>
</div>
