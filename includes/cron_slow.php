<?php
//FOR DELETING EMPTY ACCOUNTS
$conn->query("DELETE FROM student WHERE (student_email_auth IS NULL OR student_verdict_summary='0') AND NOW() > student_time_created+INTERVAL 1 MONTH ");
$conn->query("DELETE FROM user WHERE user_email_auth_time IS NULL AND NOW() > user_time_created + INTERVAL 1 MONTH 
	AND NOT EXISTS (SELECT 1 FROM student WHERE student_user_id=user_id)");

//FOR SENDING REMINDER TO PROF
$result=$conn->query("SELECT review_id, review_prof, review_directlogin, review_msg_toprof FROM review WHERE
	review_time_requested + INTERVAL 1 WEEK < NOW()
	AND (review_sentreminder='0' OR review_sentreminder IS NULL ) AND review_agreed IS NULL
	AND review_time_aborted IS NULL AND review_time_submit IS NULL AND review_time_tgth_passedon IS NULL");
while($row=$result->fetch_assoc())
{
	$direct_login_link="https://www.myphdidea.org/invitation.php?directlogin=".bin2hex($row['review_directlogin']);

	//SEND EMAILS
	$text1="It seems that you have not noticed our last email! We have got a student who wants to send you a research project idea and writes\n\n'"
		.$row['review_msg_toprof']."'\n\nIn order to learn about this research pitch, please follow the link below:\n\n";
	$text2="\n\nIf you do not agree, the student will be allowed to cancel the review request and approach someone else.";
	
	if(send_profnotif($conn, $row['review_prof'], 1, "Research project idea: reminder", $text1, $direct_login_link, $text2))
		$conn->query("UPDATE review SET review_sentreminder='1' WHERE review_id='".$row['review_id']."'");	
}
$result=$conn->query("SELECT review_id, review_prof, review_directlogin FROM review WHERE
	review_time_requested + INTERVAL 5 WEEK < NOW()
	AND review_sentreminder='1' AND review_agreed='1'
	AND review_time_aborted IS NULL AND review_time_submit IS NULL AND review_time_tgth_passedon IS NULL");
while($row=$result->fetch_assoc())
{
	$direct_login_link="https://www.myphdidea.org/invitation.php?directlogin=".bin2hex($row['review_directlogin']);

	//SEND EMAILS
	$text1="We are approaching the 6 week deadline before which we recommend that you submit your review. Click below to be taken to the review screen:\n\n";
	$text2="\n\nYou can still submit a review afterwards but only if the student agrees to wait rather than contact someone else. Use 'dialogue' to ask the student for an extension.";
	
	if(send_profnotif($conn, $row['review_prof'], 1, "Research project idea: reminder", $text1, $direct_login_link, $text2))
		$conn->query("UPDATE review SET review_sentreminder='0' WHERE review_id='".$row['review_id']."'");
}
$result=$conn->query("SELECT review_id, review_prof, review_directlogin FROM review WHERE
	review_time_requested + INTERVAL 3 WEEK < NOW() AND review_time_requested + INTERVAL 5 WEEK > NOW()
	AND (review_sentreminder='0' OR review_sentreminder IS NULL) AND review_agreed='1'
	AND review_time_aborted IS NULL AND review_time_submit IS NULL AND review_time_tgth_passedon IS NULL");
while($row=$result->fetch_assoc())
{
	$direct_login_link="https://www.myphdidea.org/invitation.php?directlogin=".bin2hex($row['review_directlogin']);

	//SEND EMAILS
	$text1="Just to remind you that it is half-time now for the review you agreed to contribute, don't forget to arrange a Skype discussion with the student. You can then submit your review below:\n\n";
	$text2="\n\nFrom the reception of this e-mail, you are still guaranteed a minimum of 3 weeks but it shouldn't be too early to fix a date for that presentation.";
	
	if(send_profnotif($conn, $row['review_prof'], 1, "Research project idea: reminder", $text1, $direct_login_link, $text2))
		$conn->query("UPDATE review SET review_sentreminder='1' WHERE review_id='".$row['review_id']."'");
}		

//FOR TERMINATING CAMPAIGNS
$result=$conn->query("SELECT c.cmpgn_id, c.cmpgn_user, max(m.moderators_id) AS max_mod,
	c.cmpgn_moderators_group FROM cmpgn c
	JOIN moderators m ON (m.moderators_group=c.cmpgn_moderators_group)
	WHERE c.cmpgn_type_isarchivized='0' AND c.cmpgn_time_finalized IS NULL AND
	((c.cmpgn_time_launched + INTERVAL 4 MONTH < NOW() AND c.cmpgn_time_firstsend IS NULL)
	OR (c.cmpgn_time_firstsend + INTERVAL 8 MONTH < NOW() AND c.cmpgn_time_firstsend IS NOT NULL))
	GROUP BY c.cmpgn_id");
if($result->num_rows > 0)
	while($row=$result->fetch_assoc())
	{
		$outstanding_verdicts=$conn->query("SELECT 1 FROM verdict v JOIN upload u ON (v.verdict_id=u.upload_verdict)
			WHERE u.upload_cmpgn='".$row['cmpgn_id']."' AND (v.verdict_time1 IS NULL OR v.verdict_time2 IS NULL OR v.verdict_time3 IS NULL)")->num_rows > 0;
		$outstand_verdicts=$outstanding_verdicts || ($conn->query("SELECT 1 FROM verdict v JOIN send s ON (v.verdict_id=s.send_verdict)
			JOIN upload u ON (s.send_upload=u.upload_id)
			WHERE u.upload_cmpgn='".$row['cmpgn_id']."' AND (v.verdict_time1 IS NULL OR v.verdict_time2 IS NULL OR v.verdict_time3 IS NULL)")->num_rows > 0);
		$outstand_verdicts=$outstanding_verdicts || ($conn->query("SELECT 1 FROM verdict v JOIN review r ON (v.verdict_id=r.review_gradedby_verdict)
			JOIN upload u ON (r.review_upload=u.upload_id)
			WHERE u.upload_cmpgn='".$row['cmpgn_id']."' AND (v.verdict_time1 IS NULL OR v.verdict_time2 IS NULL OR v.verdict_time3 IS NULL)")->num_rows > 0);
		$result2=$conn->query("SELECT r.review_id, r.review_prof FROM upload u JOIN review r ON (u.upload_id=r.review_upload) WHERE u.upload_cmpgn='".$row['cmpgn_id']."' AND r.review_time_requested IS NOT NULL AND (r.review_time_submit IS NULL OR r.review_grade IS NULL) AND r.review_time_tgth_passedon IS NULL AND r.review_time_aborted IS NULL");
		if(empty($outstanding_verdicts) && ($result2->num_rows==0 || $conn->query("SELECT 1 FROM cmpgn WHERE cmpgn_id=".$row['cmpgn_id']." AND cmpgn_time_firstsend + INTERVAL 11 MONTH < NOW()")->num_rows > 0))
		{
			if($result2->num_rows != 0)
			{
				$row2=$result2->fetch_assoc();
				send_profnotif($conn,$row2['review_prof'],4,'Review close','Your review has been automatically terminated as we finalized this campaign.','','');
				$conn->query("UPDATE review SET review_time_aborted=NOW(), review_aborted_byuser='0' WHERE review_id='".$row2['review_id']."' AND review_grade IS NULL");
			}
						
			//PAY OUT IDEA POINTS
			$row3=$conn->query("SELECT moderators_time_joined1, moderators_time_joined2, moderators_time_joined3 FROM moderators WHERE moderators_id='".$row['max_mod']."'")->fetch_assoc();
			$idea_pts_suppl1=2*floor((strtotime("now")-strtotime($row3['moderators_time_joined1']))/(6*7*24*60*60));
			$idea_pts_suppl2=2*floor((strtotime("now")-strtotime($row3['moderators_time_joined2']))/(6*7*24*60*60));
			$idea_pts_suppl3=2*floor((strtotime("now")-strtotime($row3['moderators_time_joined3']))/(6*7*24*60*60));
						
			$conn->query("UPDATE student SET student_cmpgn_own_latest=NULL WHERE student_user_id='".$row['cmpgn_user']."'");
			$conn->query("UPDATE moderators m JOIN student s1 ON (s1.student_user_id=m.moderators_first_user)
				SET s1.student_cmpgn_shadowed_latest=NULL, s1.student_pts_cmpgn=s1.student_pts_cmpgn+{$idea_pts_suppl1}
				WHERE m.moderators_id='".$row['max_mod']."'");
			$conn->query("UPDATE moderators m JOIN student s2 ON (s2.student_user_id=m.moderators_second_user)
				SET s2.student_cmpgn_shadowed_latest=NULL, s2.student_pts_cmpgn=s2.student_pts_cmpgn+{$idea_pts_suppl2}
				WHERE m.moderators_id='".$row['max_mod']."'");
			$conn->query("UPDATE moderators m JOIN student s3 ON (s3.student_user_id=m.moderators_third_user)
				SET s3.student_cmpgn_shadowed_latest=NULL, s3.student_pts_cmpgn=s3.student_pts_cmpgn+{$idea_pts_suppl3}
				WHERE m.moderators_id='".$row['max_mod']."'");
			$conn->query("UPDATE cmpgn SET cmpgn_time_finalized=NOW() WHERE cmpgn_id='".$row['cmpgn_id']."'");
			//EMPTY WATCHLIST
			$conn->query("DELETE w FROM watchlist w JOIN moderators m ON (w.watchlist_moderators=m.moderators_id)
				WHERE m.moderators_group='".$row['cmpgn_moderators_group']."'");
			$result=$conn->query("SELECT s.send_id FROM send s JOIN upload u ON (s.send_upload=u.upload_id) WHERE u.upload_cmpgn='$cmpgn_id'");
			while($row=$result->fetch_assoc())
			{
				if(file_exists('user_data/tmp/send'.$row['send_id'].'_1.png')) unlink('user_data/tmp/send'.$row['send_id'].'_1.png');
				if(file_exists('user_data/tmp/send'.$row['send_id'].'_2.png')) unlink('user_data/tmp/send'.$row['send_id'].'_2.png');
				if(file_exists('user_data/tmp/send'.$row['send_id'].'_3.png')) unlink('user_data/tmp/send'.$row['send_id'].'_3.png');
			}
			rvw_to_newsfeeds($conn,$row['cmpgn_id']);
		}
	}

?>