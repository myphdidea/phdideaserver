<?phpinclude("includes/cron_slow.php");//FOR DELETING EMPTY ACCOUNTS//FOR SENDING REMINDER TO PROF/*$result=$conn->query("SELECT review_id, review_prof, review_directlogin, review_msg_toprof FROM review WHERE	review_time_requested + INTERVAL 1 WEEK < NOW()	AND review_sentreminder='0' AND review_agreed IS NULL	AND review_time_aborted IS NULL AND review_time_submit IS NULL AND review_time_tgth_passedon IS NULL");while($row=$result->fetch_assoc()){	$direct_login_link="https://www.myphdidea.org/invitation.php?directlogin=".bin2hex($row['direct_login_token']);	//SEND EMAILS	$text1="It seems that you have not noticed our last email! We have got a student who wants to send you a research project idea and writes\n\n'"		.$row['review_msg_toprof']."'\n\nIn order to learn about this research pitch, please follow the link below:\n\n";	$text2="\n\nIf you do not agree, the student will be allowed to cancel the review request and approach someone else.";		$conn->query("UPDATE review SET review_sentreminder='1' WHERE review_id='".$row['review_id']."'");	send_profnotif($conn, $row['review_prof'], 1, "Research project idea: reminder", $text1, $direct_login_link, $text2);}$result=$conn->query("SELECT review_id, review_prof, review_directlogin FROM review WHERE	review_time_requested + INTERVAL 5 WEEK < NOW()	AND review_sentreminder='1' AND review_agreed='1'	AND review_time_aborted IS NULL AND review_time_submit IS NULL AND review_time_tgth_passedon IS NULL");while($row=$result->fetch_assoc()){	$direct_login_link="https://www.myphdidea.org/invitation.php?directlogin=".bin2hex($row['direct_login_token']);	//SEND EMAILS	$text1="We are approaching the 6 week deadline before which we recommend that you submit your review. Click below to be taken to the review screen:\n\n";	$text2="\n\nYou can still submit a review afterwards but only if the student agrees to waiting rather than contacting someone else. Use 'dialogue' to ask the student for an extension.";		$conn->query("UPDATE review SET review_sentreminder='0' WHERE review_id='".$row['review_id']."'");	send_profnotif($conn, $row['review_prof'], 1, "Research project idea: reminder", $text1, $direct_login_link, $text2);}$result=$conn->query("SELECT review_id, review_prof, review_directlogin FROM review WHERE	review_time_requested + INTERVAL 3 WEEK < NOW() AND review_time_requested + INTERVAL 5 WEEK > NOW()	AND review_sentreminder='0' AND review_agreed='1'	AND review_time_aborted IS NULL AND review_time_submit IS NULL AND review_time_tgth_passedon IS NULL");while($row=$result->fetch_assoc()){	$direct_login_link="https://www.myphdidea.org/invitation.php?directlogin=".bin2hex($row['direct_login_token']);	//SEND EMAILS	$text1="Just to remind you that it is half-time now for the review you agreed to contribute, don't forget to arrange a Skype discussion with the student. You can then submit your review below:\n\n";	$text2="\n\nFrom the reception of this e-mail, you are still guaranteed a minimum of 3 weeks but you shouldn't wait much longer.";		$conn->query("UPDATE review SET review_sentreminder='0' WHERE review_id='".$row['review_id']."'");	send_profnotif($conn, $row['review_prof'], 1, "Research project idea: reminder", $text1, $direct_login_link, $text2);}		//FOR TERMINATING CAMPAIGNS$result=$conn->query("SELECT c.cmpgn_id, c.cmpgn_user, max(m.moderators_id) AS max_mod,	c.cmpgn_moderators_group FROM cmpgn c	JOIN moderators m ON (m.moderators_group=c.cmpgn_moderators_group)	WHERE c.cmpgn_type_isarchivized='0' AND c.cmpgn_time_finalized IS NULL AND	((c.cmpgn_time_launched + INTERVAL 4 MONTH < NOW() AND c.cmpgn_time_firstsend IS NULL)	OR (c.cmpgn_time_firstsend + INTERVAL 8 MONTH < NOW() AND c.cmpgn_time_firstsend IS NOT NULL))	GROUP BY c.cmpgn_id");if($result->num_rows > 0)	while($row=$result->fetch_assoc())	{		$result2=$conn->query("SELECT r.review_id, r.review_prof FROM upload u JOIN review r ON (u.upload_id=r.review_upload) WHERE u.upload_cmpgn='".$row['cmpgn_id']."' AND r.review_time_requested IS NOT NULL AND (r.review_time_submit IS NULL OR r.review_grade IS NULL) AND r.review_time_tgth_passedon IS NULL AND r.review_time_aborted IS NULL");		if($result2->num_rows==0 || $conn->query("SELECT 1 FROM cmpgn WHERE cmpgn_id=".$row['cmpgn_id']." AND cmpgn_time_firstsend + INTERVAL 11 MONTH < NOW()")->num_rows > 0)		{			if($result2->num_rows != 0)			{				$row2=$result2->fetch_assoc();				send_profnotif($conn,$row2['review_prof'],4,'Review close','Your review has been automatically terminated as we finalized this campaign.','','');				$conn->query("UPDATE review SET review_time_aborted=NOW(), review_aborted_byuser='0' WHERE review_id='".$row2['review_id']."' AND review_grade IS NULL");			}						//PAY OUT IDEA POINTS			$row3=$conn->query("SELECT moderators_time_joined1, moderators_time_joined2, moderators_time_joined3 FROM moderators WHERE moderators_id='".$row['max_mod']."'")->fetch_assoc();			$idea_pts_suppl1=2*floor((strtotime("now")-strtotime($row3['moderators_time_joined1']))/(6*7*24*60*60));			$idea_pts_suppl2=2*floor((strtotime("now")-strtotime($row3['moderators_time_joined2']))/(6*7*24*60*60));			$idea_pts_suppl3=2*floor((strtotime("now")-strtotime($row3['moderators_time_joined3']))/(6*7*24*60*60));									$conn->query("UPDATE student SET student_cmpgn_own_latest=NULL WHERE student_user_id='".$row['cmpgn_user']."'");			$conn->query("UPDATE moderators m JOIN student s1 ON (s1.student_user_id=m.moderators_first_user)				JOIN student s2 ON (s2.student_user_id=m.moderators_second_user)				JOIN student s3 ON (s3.student_user_id=m.moderators_third_user)				SET s1.student_cmpgn_shadowed_latest=NULL, s1.student_pts_cmpgn=s1.student_pts_cmpgn+{$idea_pts_suppl1},				s2.student_cmpgn_shadowed_latest=NULL, s2.student_pts_cmpgn=s2.student_pts_cmpgn+{$idea_pts_suppl2},				s3.student_cmpgn_shadowed_latest=NULL, s3.student_pts_cmpgn=s3.student_pts_cmpgn+{$idea_pts_suppl3}				WHERE m.moderators_id='".$row['max_mod']."'");			$conn->query("UPDATE cmpgn SET cmpgn_time_finalized=NOW() WHERE cmpgn_id='".$row['cmpgn_id']."'");			//EMPTY WATCHLIST			$conn->query("DELETE w FROM watchlist w JOIN moderators m ON (w.watchlist_moderators=m.moderators_id)				WHERE m.moderators_group='".$row['cmpgn_moderators_group']."'");		}	}*/?><?php$recent_upload="";$sql="SELECT MAX(u.upload_timestamp) AS upload_timestamp_latest, c.cmpgn_id, c.cmpgn_title, COUNT(*) AS upload_version FROM upload u	JOIN cmpgn c ON (u.upload_cmpgn=c.cmpgn_id) GROUP BY c.cmpgn_id ORDER BY upload_timestamp_latest DESC LIMIT 4";$result=$conn->query($sql);while($row=$result->fetch_assoc()){//if(strlen($row['cmpgn_title'])>25) $ellipsis="..."; else $ellipsis="";$recent_upload=$recent_upload.'<tr>              <td style="width: 90px;">'.$row['upload_timestamp_latest'].'<br>              </td>              <td style="width: 210px;"><a href="index.php?cmpgn='.$row['cmpgn_id'].'">'.$row['cmpgn_title'].'</a><br>              </td>              <td style="max-width: 75.3px;">'.$row['upload_version'].'<br>              </td>       </tr>';}?>      <div id="centerpage"> <img title="Student revolts: Past and present" alt="Photos of various student protests"          src="images/titleimage.png"><br><br>
        Welcome to <i>myphdidea.org</i>! We are an online publishing platform
        founded with the purpose of connecting students at Master level with
        professors. For students, we hope to encourage a pro-active attitude
        towards graduate studies, where creativity and developing ideas of your own count
        more than career-minded pursuit of formal qualifications. For
        professors, we hope to give due credit to anyone dedicated to opening up
        science to new participants, on terms that are not basically one-sided.<br>
        <br>
        If you have got an idea for a PhD project, please become a member and
        upload it! You can then request reviews from professors; both your idea
        and the expert reviews will be given a permanent digital home on our
        site. Our crowd-sourced student editor system assures a minimum quality
        level for all uploads, and respectful treatment of professors' time.
        Though aimed particularly at students in engineering and
        natural science, anyone with 2 years of study and an institutional email
        account can register.<br>
        <br>
        If you are a first time visitor, the best point to start is the <a title="An introduction to the site"
          href="index.php?page=faq">FAQ</a> section. <br>
        <br>
        Recent site news:
        <div style="height: 200px;" id="toplist" class="list">        	<?php include("sitenews.php"); ?>        </div><?php   if(!empty($recent_upload)) echo '<table style="width: 100%">          <tbody>            <tr>              <th style="width:90px;">Uploaded<br>              </th>              <th style="width: 210px;">Idea<br>              </th>              <th style="width:75.3px;">Version<a href="rss.php?type=upload"><img src="images/rss_black_small.png" style="float: right; margin-right: 5px; margin-top: 2px"></a><br>              </th>            </tr>'.$recent_upload.'</tbody></table>';		else echo "";?>
        <div style="text-align: center;"> <br>
          Number of verified student accounts: <?php echo $conn->query("SELECT 1 FROM student WHERE student_email_auth IS NOT NULL AND student_verdict_summary='1'")->num_rows; ?> </div>
      </div>
