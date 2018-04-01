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
if(!$row['cmpgn_type_isarchivized'])
	header("Location: index.php?workbench=uploadverdict&verdict=".$verdict_id);

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
			
			//GET INTERACTIONS
			$sql="SELECT p.prof_id, p.prof_givenname, p.prof_familyname, i.interaction_grade
				FROM interaction i JOIN prof p ON (p.prof_id=i.interaction_with)
				JOIN upload u ON (u.upload_cmpgn=i.interaction_cmpgn)
				WHERE i.interaction_cmpgn='$cmpgn_id' LIMIT 5";
			$result=$conn->query($sql);
			$interacts="";
			if($result->num_rows==0)
				$interacts="<i>No interactions or not approved.</i>";
			while($row=$result->fetch_assoc())
				$interacts=$interacts.'<div class="review" style="width: 90%">With <a href="index.php?prof='.$row['prof_id'].'">'.$row['prof_givenname'].' '.$row['prof_familyname'].'</a></div>';
			
			$inspect_nb=isset($_SESSION['upload_'.$upload_id.'_1'])+isset($_SESSION['upload_'.$upload_id.'_2'])+isset($_SESSION['upload_'.$upload_id.'_3']);
			if(isset($_POST['submit']) && ($inspect_nb>=$tot_nb))
			{
				if($conn->real_escape_string(test($_POST['appr_or_decl']))=="none")
					$error_msg=$error_msg."Please either accept or decline!<br>";
				else
				{
					include("includes/render_verdict.php");
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
        <h3>Archivized idea to verify (verdict due <?php echo $time_due; ?>)</h3>
        <p>Archivized campaigns consist in a pdf document set and interactions with
           researchers. We require you to judge both the upload itself (basic suitability)
           and whether the researchers chosen do not seem grossly mismatched given
           the nature of the material. With this perspective, please first go through the attached
           pdf files and form your opinion. Documents to check: <?php echo $tot_nb; ?></p>
		<?php echo $upload_body; ?>
		Next, please go over the proposed interactions, investigating researchers via google:
		<?php if(!empty($interacts)) echo '<br><div style="margin-top: 10px; margin-bottom: 20px">'.$interacts.'</div><br>'; ?>
		You should approve this archivized idea if:
		<ul>
			<li>There's a scientific project proposal here</li>
			<li>Researcher interactions are unambiguous (no void ORCIDs)<br> and credible inside the scope of the project</li>
			<li>No obviously misplaced or offensive material</li>
		</ul>
       	<form method="post" action="">
        <p>Approve or decline this idea?
          <select name="appr_or_decl" style="width: 200px">
            <option value="none">--</option>
            <option value="appr">Approve</option>
            <option value="decl">Decline (Reason: Not a scientific project proposal)</option>
            <option value="decl">Decline (Reason: Researchers ambiguous or obviously from the wrong domain)</option>
            <option value="decl">Decline (Reason: Material would be embarrassing for site)</option>
          </select>
          <button name="submit">OK</button> 
          </p></form>
      </div>
