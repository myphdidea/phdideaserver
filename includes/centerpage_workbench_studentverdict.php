<?php
$verdict_id=$conn->real_escape_string(test($_GET['verdict']));

if(isset($_SESSION['user']))
{
	$sql="SELECT v.verdict_task, v.verdict_moderators, m.moderators_first_user, m.moderators_second_user, m.moderators_third_user
		FROM moderators m JOIN verdict v ON (m.moderators_id=v.verdict_moderators)
		WHERE (m.moderators_first_user='".$_SESSION['user']."' OR m.moderators_second_user='".$_SESSION['user']."' OR m.moderators_third_user='".$_SESSION['user']."')
		AND v.verdict_type='USER' AND v.verdict_id='$verdict_id'";
	$result=$conn->query($sql);
	if($result->num_rows > 0)
	{
		$row=$result->fetch_assoc();
		$verdict_task=$row['verdict_task'];
		$verdict_moderators=$row['verdict_moderators'];
		$moderators_first_user=$row['moderators_first_user'];
		$moderators_second_user=$row['moderators_second_user'];
		$moderators_third_user=$row['moderators_third_user'];

		$sql="SELECT s.student_id, s.student_givenname, s.student_familyname, s.student_institution_email, i.institution_name,
		    i.institution_annuary_yearofstudy, i.institution_annuary_subject, s.student_annuary_link, s.student_annuary_instructions,
			u.user_subject1, u.user_subject2, u.user_id
			FROM student s JOIN user u ON (s.student_user_id=u.user_id)
			JOIN institution i ON (i.institution_id=s.student_institution) WHERE s.student_initauth_verdict='$verdict_id'";
		$row=$conn->query($sql)->fetch_assoc();
		$student_givenname=$row['student_givenname'];
		$student_familyname=$row['student_familyname'];
		$student_email=$row['student_institution_email'];
		$student_institution=$row['institution_name'];
		$annuary_link=$row['student_annuary_link'];
		$transcript_file=$row['student_id'];
//		$annuary_link_explicit=$row['link_to'];
		$annuary_instructions=$row['student_annuary_instructions'];
		$institution_yearofstudy=$row['institution_annuary_yearofstudy'];
		$institution_subject=$row['institution_annuary_subject'];
		$user_subject1=$row['user_subject1'];
		$user_subject2=$row['user_subject2'];
		$owner=$row['user_id'];
		if(isset($_POST['third_year'])) $third_year=!empty(test($_POST['third_year'])); else $third_year="0";

		$sql="SELECT t.taskentrusted_timestamp, taskentrusted_completed, s.student_id FROM taskentrusted t JOIN student s
			  ON (t.taskentrusted_to=s.student_id) WHERE s.student_user_id='".$_SESSION['user']."' AND t.taskentrusted_task='$verdict_task'";
		$row=$conn->query($sql)->fetch_assoc();
		$student_id=$row['student_id'];
		$error_msg="";
		$time_due=date("Y-m-d H:i:s",strtotime($row['taskentrusted_timestamp']." + 7 days"));
		if(strtotime('now') > strtotime($row['taskentrusted_timestamp']." + 7 days"))
		{
			$error_msg=$error_msg."Ouch verdict time is now past!<br>";
		}
		elseif($row['taskentrusted_completed']=='' && isset($_POST['submit']))
		{
			if($conn->real_escape_string(test($_POST['appr_or_decl']))=="none")
					$error_msg=$error_msg."Please either accept or decline!<br>";
			if(!isset($_SESSION['link'])/* || $_SESSION['link']!=$annuary_link*/)
				$error_msg=$error_msg."Please visit annuary link once!<br>";
			
			$verdict_type='USER';
			if(empty($error_msg))
				include("includes/render_verdict.php");
		}
	}
}
else
{
	$_SESSION['after_login']="https://www.myphdidea.org/index.php?workbench=studentverdict&verdict=".$verdict_id;
	header("Location: index.php?confirm=after_login");
}
?>

      <div id="centerpage">
	  <h1>New verdict</h1>
	  <h2>on <?php echo '"'.$student_givenname.' '.$student_familyname.'" ('.$student_email.')'; ?></h2>
<?php if(isset($error_msg)) echo '<div style="color: red; text-align: center">'.$error_msg.'</div>';?>
        <h3>Student membership request (verdict due <?php echo $time_due; ?>)</h3>
        <p>Membership has been requested by <?php echo $student_givenname.' '.$student_familyname?>, who belongs to <?php echo $student_institution; ?>.
           The main task here is to make sure that the e-mail address matches the name given by the student,
           easy in some cases (<i>peter.parker@psu.edu</i>) but less so in others (<i>pp281@psu.edu</i>). Also, we require all student members to have completed
           at least 2 years of study, but they must not have obtained a PhD yet (current PhD students are OK, but not e.g. post-docs). </p>
           <p>In order to verify these circumstances, we ask students to furnish a link to the annuary of the institution, or any other page on the institution's web server linking
           their name and e-mail (to establish year of study, students can also share transcripts of their grades). Our records show
           that for this university, you should be able to find</p>
           <ul>
           	<?php if(!empty($institution_yearofstudy)) echo '<li>The student\'s year of study</li>'; ?>
           	<?php if(!empty($institution_subject)) echo '<li>The student\'s subject of study</li>'; ?>
           	<?php if(empty($institution_subject) && empty($institution_yearofstudy)) echo '<li><i>No indication in the institutional database.</i></li>'; ?>
           </ul>
           <?php echo $student_givenname ?> has indicated his/her subject of study as follows:
                   <div class="indentation">
       	<label>Subject 1:</label>
          <select name="subject1" disabled>
          	<?php $subject=$user_subject1; include("includes/subject_selector.php") ?>
          </select><br>
		<label>Subject 2 (optional):</label>
		  <select name="subject2" disabled>
          	<?php $subject=$user_subject2; include("includes/subject_selector.php") ?>
          </select>
        </div>
        Please go to the following link to check these propositions:
        <div class="indentation">
           	<?php if(!empty($annuary_link)) echo '<a target="_blank" href="index.php?page=redirect&link='.urlencode(html_entity_decode(html_entity_decode(stripcslashes($annuary_link)))).'">'.html_entity_decode(stripcslashes($annuary_link)).'</a>'; else echo '<i>No link supplied.</i>'; ?>
        </div>
		<?php if(!empty($annuary_instructions)) echo $student_givenname.' explains about the link:<div class="indentation"><i>'.$annuary_instructions.'</i></div>'; ?>
        <?php if(file_exists('user_data/transcripts/'.$transcript_file.'.pdf')) echo "<p>Also, ".$student_givenname.' has chosen to add supporting documents: <img src="images/pdf_icon_18x18.png" style="vertical-align: text-bottom; background-color: lightgrey" alt=""> <a target="_blank" href="download_privatepdf.php?student='.$transcript_file.'">transcript.pdf</a></p>';?>
       	<form method="post" action="">
       	<input name="third_year" type="checkbox" <?php if(!empty($third_year)) echo "checked"; ?>>Please check in case <?php echo $student_givenname; ?> is in his/her 3rd year.
        <p>Approve or decline membership?
          <select name="appr_or_decl" style="width: 200px">
            <option value="none">--</option>
            <option value="appr">Approve</option>
            <option value="decl">Decline (Reason: E-mail or name not on annuary page)</option>
            <option value="decl">Decline (Reason: &lt; 2 years of study)</option>
            <option value="decl">Decline (Reason: Already got PhD)</option>
            <option value="decl">Decline (Reason: Subject of study manifestly misstated)</option>
            <option value="decl">Decline (Reason: Link empty or probably fake material)</option>
            <?php if(!empty($annuary_instructions)) echo '<option value="decl">Decline (Reason: Inappropriate comments in '.$student_givenname.'\'s message)</option>'; ?>
          </select>
          <button name="submit">OK</button> 
          </p></form>
      </div>
