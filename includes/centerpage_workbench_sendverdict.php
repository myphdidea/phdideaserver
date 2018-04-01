<?php
$verdict_id=$conn->real_escape_string(test($_GET['verdict']));

$owner=$title=$time_launched=$time_firstsend=$time_finalized=$error_msg="";
$sql="SELECT c.cmpgn_id, c.cmpgn_title, c.cmpgn_user, c.cmpgn_time_launched, c.cmpgn_time_firstsend, c.cmpgn_time_finalized FROM cmpgn c
	JOIN moderators m ON (c.cmpgn_moderators_group=m.moderators_group)
	JOIN verdict v ON (m.moderators_id=v.verdict_moderators) WHERE verdict_id='".$verdict_id."'";
$row=$conn->query($sql)->fetch_assoc();
$title=$row['cmpgn_title'];
$cmpgn_id=$row['cmpgn_id'];
$owner=$row['cmpgn_user'];
$time_launched=$row['cmpgn_time_launched'];
$time_firstsend=$row['cmpgn_time_firstsend'];
$time_finalized=$row['cmpgn_time_finalized'];

$other_orcid=$confirm_family=$confirm_given="";
if(isset($_POST['orcid_enter'])) $orcid_enter=$conn->real_escape_string(test($_POST['orcid_enter']));
if(isset($_POST['otherorcid'])) $other_orcid=$conn->real_escape_string(test($_POST['otherorcid']));
if(isset($_POST['confirm_family'])) $confirm_family=$conn->real_escape_string(test($_POST['confirm_family']));
if(isset($_POST['confirm_given'])) $confirm_given=$conn->real_escape_string(test($_POST['confirm_given']));

if(isset($_SESSION['user']))
{
	$sql="SELECT v.verdict_task, v.verdict_moderators, m.moderators_first_user, m.moderators_second_user, m.moderators_third_user
		FROM moderators m JOIN verdict v ON (m.moderators_id=v.verdict_moderators)
		WHERE (m.moderators_first_user='".$_SESSION['user']."' OR m.moderators_second_user='".$_SESSION['user']."' OR m.moderators_third_user='".$_SESSION['user']."')
		AND v.verdict_type='SEND' AND v.verdict_id='$verdict_id'";
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
		$time_due=date("Y-m-d H:i:s",strtotime($row['taskentrusted_timestamp']." + 3 days"));
		if(strtotime('now') > strtotime($row['taskentrusted_timestamp']." + 3 days"))
		{
			$error_msg=$error_msg."Ouch verdict time is now past!<br>";
			$sql="UPDATE taskentrusted SET taskentrusted_completed='FALSE' WHERE taskentrusted_to='$student_id' AND taskentrusted_task='$verdict_task'";
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
			$conn->query($sql);
		}
		elseif($row['taskentrusted_completed']=='')
		{
			//find send upload id
			$sql="SELECT s.send_upload, s.send_prof, s.send_prof_givenname, s.send_prof_familyname,
				s.send_resbox, s.send_msg_text, s.send_id, i.institution_name
				FROM send s	JOIN institution i ON (s.send_prof_institution=i.institution_id)
				WHERE s.send_verdict='$verdict_id'";
			$row=$conn->query($sql)->fetch_assoc();
			$send_id=$row['send_id'];
			$search_prof=$row['send_prof'];
			$upload_id=$row['send_upload'];
			$institution_name=$row['institution_name'];
			$justification=$row['send_msg_text'];
			$resbox=$row['send_resbox'];
			if(!empty($row['send_prof']))
			{
				$sql="SELECT prof_familyname, prof_givenname, prof_orcid FROM prof WHERE prof_id='".$row['send_prof']."'";
				$row2=$conn->query($sql)->fetch_assoc();
				$prof_fullname=$row2['prof_familyname'].", ".$row2['prof_givenname'];
				$prof_orcid=$row2['prof_orcid'];
				$found_prof=FALSE;
			}
			else
			{
				$found_prof=TRUE;//NO NEED TO FIND SINCE REQUESTING INSERTION OF NEW RECORD
				$prof_fullname=$row['send_prof_familyname'].", ".$row['send_prof_givenname'];
			}
			$prof_gname_default=$row['send_prof_givenname'];
			$prof_fname_default=$row['send_prof_familyname'];

			$sql="SET @version=0;";
			$result=$conn->query($sql);
			$sql="SELECT @version:=@version+1 AS version, upload_id, upload_timestamp, upload_abstract_text, upload_file1, upload_file2, upload_file3
				FROM upload WHERE upload_cmpgn='$cmpgn_id' ORDER BY version DESC";
			$result=$conn->query($sql);
			if($result->num_rows==0) $error_msg=$error_msg."Could not find upload!<br>";
			
			while($row=$result->fetch_assoc())
			{
				if($row['upload_id']!=$upload_id)
					continue;
				$version=$row['version'];
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
			
			if(isset($_POST['judge']) || isset($_POST['search']))
			{
				$prof_gname=$conn->real_escape_string(test($_POST['gname']));
				$prof_fname=$conn->real_escape_string(test($_POST['fname']));
				$main_email=$conn->real_escape_string(test($_POST['main_email']));
				$alt_email=$conn->real_escape_string(test($_POST['alt_email']));

				$selec_row_limit=3;
				include("includes/send_selectors.php");
				if(!$found_prof)//i.e. has to be user-added ORCID
				{
					if(!empty($orcid_enter) && $orcid_enter==$search_prof)
						$orcidselected="checked";
					else $orcidselected="";
					if(isset($prof_fullname))
						$prof_printname=$prof_fullname;
					else $prof_printname=$prof_fname.', '.$prof_gname;
					$orcid_printname="";
					if(!empty($prof_orcid))
						$orcid_printname=' (<a target="_blank" href="https://orcid.org/'.$prof_orcid.'">'.$prof_orcid.'</a>)';
					if($row['prof_hasactivity'])
						$prof_printname='<a href=index.php?prof="'.$search_prof.'">'.$prof_printname.'</a>';
					$orcid_selector=$orcid_selector.'<input value="'.$search_prof.'" name="orcid_enter" '.$orcidselected.' type="radio">'.
          				$prof_printname.$orcid_printname.'<br>';//ADD TO ORCID SELECTOR
				}
				
				if(empty($error_msg) && isset($_POST['judge']))
				{
					$regex = '#^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$#';
			
					if(empty($_POST['orcid_enter']))
						$error_msg=$error_msg."Need to choose researcher id!<br>";
					elseif($orcid_enter=='hasorcid' && (empty($_POST['otherorcid']) || !preg_match($regex,$other_orcid)))
						$error_msg=$error_msg."Checked other ORCID but provided none, or wrong format!<br>";
					elseif($orcid_enter=='hasorcid' && !isset($_POST['confirm_otherorcid']))
					{
						$sql="SELECT prof_id, prof_givenname, prof_familyname FROM prof WHERE prof_orcid='$other_orcid'";
						$result=$conn->query($sql);
						if($result->num_rows > 0 && $row=$result->fetch_assoc())
							$error_msg=$error_msg.'Send to '.$row['prof_givenname'].' '.$row['prof_familyname'].' OK?<input type="checkbox" name="confirm_otherorcid" value="'.$row['prof_id'].'"><br>';
						//COUNTERCHECK WITH ORCID API HERE
						else $error_msg=$error_msg."Could not find this ORCID in database!<br>";
					}

					if(!empty($orcid_enter) && ctype_digit($orcid_enter) && empty($_POST['confirm_emptyorcid']) && $conn->query("SELECT 1 FROM prof WHERE (prof_description IS NULL OR LENGTH(prof_description) < 1) AND prof_hasactivity IS NULL AND prof_country IS NULL AND prof_id='".$conn->real_escape_string(test($orcid_enter))."'")->num_rows > 0)
						$error_msg=$error_msg.'ORCID profile seemingly empty really OK? <input type="checkbox" name="confirm_emptyorcid"><br>';
					
					if(empty($main_email))
						$error_msg=$error_msg."Need to enter at least main e-mail!<br>";
					elseif(!filter_var($main_email, FILTER_VALIDATE_EMAIL))
						$error_msg=$error_msg.'Main e-mail not a valid format!<br>';
					if(!empty($alt_email) && !filter_var($alt_email, FILTER_VALIDATE_EMAIL))
						$error_msg=$error_msg.'Alt e-mail not a valid format!<br>';
					if(!empty($alt_email) && $main_email==$alt_email)
						$error_msg=$error_msg.'Cannot have identical main and alt email!<br>';

					if(test($_POST['appr_or_decl'])=="none")
						$error_msg=$error_msg."Please either accept or decline!<br>";

					$sql="SELECT 1 FROM upload WHERE upload_id='".$upload_id."' AND upload_cmpgn='".$cmpgn_id."'";
					if($conn->query($sql)->num_rows==0)
						$error_msg=$error_msg."Seems to be some problem between upload and campaign association ...<br>";
					
/*					if($inspect_nb < $tot_nb)
						$error_msg=$error_msg."Please check all documents currently only checked ".(isset($_SESSION['upload_'.$upload_id.'_1'])+isset($_SESSION['upload_'.$upload_id.'_2'])+isset($_SESSION['upload_'.$upload_id.'_3']))."/".$tot_nb."!<br>";*/
					
					//PROCEED TO INSERTION
					if(empty($error_msg))
					{
						if($orcid_enter=="hasorcid")
							$insert_prof="'".$conn->real_escape_string(test($_POST['confirm_otherorcid']))."'";
						elseif($orcid_enter!="noorcid")
							$insert_prof="'".$orcid_enter."'";
						else
							$insert_prof="NULL";

						$sql="INSERT INTO crowdedit (crowdedit_student, crowdedit_task, crowdedit_email, crowdedit_prof, crowdedit_timestamp)
							SELECT student_id, '$verdict_task', '$main_email', $insert_prof, NOW() FROM student WHERE student_user_id='".$_SESSION['user']."'";
						$conn->query($sql);
						
						if(!empty($alt_email))
						{
							$sql="INSERT INTO crowdedit (crowdedit_student, crowdedit_task, crowdedit_email, crowdedit_prof, crowdedit_timestamp)
								SELECT student_id, '$verdict_task', '$alt_email', $insert_prof, NOW() FROM student WHERE student_user_id='".$_SESSION['user']."'";
							$conn->query($sql);
						}
												
						//IF VERDICT COMPLETE, SEND EMAIL
						include("includes/render_verdict.php");
					}
				}
			}
						
						
			
			//GENERATE PUBLICATION LIST
			$publication_list_prev="";
			$sql="SELECT p.publication_title, p.publication_date FROM publication p
				JOIN record r ON (p.publication_id=r.record_publication)
				JOIN resbox_impl rb ON (r.record_researcher_id=rb.resbox_impl_researcher)
				WHERE rb.resbox_impl_id='$resbox'";
			$result=$conn->query($sql);
			while($row=$result->fetch_assoc())
				$publication_list_prev=$publication_list_prev."<li>".$row['publication_title']." (".$row['publication_date'].")</li>";
			$publication_list_prev="<ul>".$publication_list_prev."</ul>";

/*			if(isset($_POST['submit']) && ($inspect_nb>=$tot_nb))
			{
				if($_POST['appr_or_decl']=="none")
					$error_msg=$error_msg."Please either accept or decline!<br>";
				else
				{
					if(test($_POST['appr_or_decl'])=="appr")
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
						
					}
//					header("Location: index.php?workbench=confirm");
				}
			}
			elseif(isset($_POST['submit']))
				$error_msg=$error_msg."Please check all documents currently only checked ".(isset($_SESSION['upload_'.$upload_id.'_1'])+isset($_SESSION['upload_'.$upload_id.'_2'])+isset($_SESSION['upload_'.$upload_id.'_3']))."/".$tot_nb."!<br>";*/
		}
		elseif($row['taskentrusted_completed']=='1') $error_msg=$error_msg."Already completed this task!<br>";
		else $error_msg=$error_msg."Too late to render judgment now!<br>";
	}
	else $error_msg=$error_msg."Not a moderator for this verdict!<br>";
}
else
{
	$_SESSION['after_login']="https://www.myphdidea.org/index.php?workbench=sendverdict&verdict=".$verdict_id;
	header("Location: index.php?confirm=after_login");
}
?>
      <div id="centerpage">
	  <h1>New verdict</h1>
	  <h2>on <?php echo '<a href="index.php?cmpgn='.$cmpgn_id.'">'.$title.'</a>'; ?></h2>
<form method="post" action="">
<?php if(isset($error_msg)) echo '<div style="color: red; text-align: center">'.$error_msg.'</div>';?>
        <h3>Send request (verdict on v. <?php echo $version?>, due <?php echo $time_due; ?>)</h3>

		You and your fellow moderators have been asked to evaluate a send request for "<?php echo $title ?>" to<br>
		<div class="indentation" style="border-color: lightblue; border-style: solid; border-width: 2px; padding: 5px">
		<b>Name: </b><?php echo $prof_fullname ?><br>
		<b>Institution: </b><?php echo $institution_name ?><br>
		<b>Publications: </b><br><?php echo $publication_list_prev ?>
		</div>
		<p>Please use <a href="http://www.google.com">google</a> to find this researcher's institutional e-mail address
			and enter it:</p>
<div class="indentation"> <label>Main e-mail (required):</label><input name="main_email" value="<?php if(isset($main_email)) echo $main_email; ?>"
            type="text"> <label>Alternative e-mail (optional):</label><input name="alt_email" value="<?php if(isset($alt_email)) echo $alt_email; ?>" type="text"></div>
        <p>We strive to ensure that no more than one profile per researcher is created on our website. To this
        	end we employ <a href="index.php?page=faq#orcid">ORCID</a> identifiers. Please use the search engine below to find ORCIDs and existing profiles in our database:</p>
        <div class="indentation"> 
          <label>Family name:</label><input type="text" name="fname" value="<?php if(!empty($prof_fname)) echo $prof_fname; else echo $prof_fname_default; ?>">
          <label>Given name (or blank):</label><input type="text" name="gname" value="<?php if(!empty($prof_gname)) echo $prof_gname; else echo $prof_gname_default; ?>">
          <p style="text-align: right;"> <button name="search">Search</button>
          </p>
        </div>
        Please pick an option, using google if necessary to investigate the ORCID further:
        <p class="indentation">
          <?php
          		if(isset($_POST['ask_permission']) || isset($_POST['search']) || !empty($orcid_selector))
				{
					if(isset($orcid_enter) && $orcid_enter=="hasorcid")
						$checkothorcid="checked";
					else $checkothorcid="";
          			if(!isset($orcid_selector)) $orcid_selector="";
          			echo '<div class="indentation">'.$orcid_selector;
					echo '<input value="hasorcid" name="orcid_enter" type="radio" '.$checkothorcid.'>Manually enter
						ORCID: <input type="text" name="otherorcid" value="'.$other_orcid.'"><br>
          				<input value="noorcid" name="orcid_enter" type="radio">I could not
          				find an ORCID.<br>';
					echo '<span style="font-size: smaller">Note: If you find <i>both</i> a profile already in use
						and an ORCID, please choose the profile.</span></div>';
				}
				else echo '<i>Please click "search" above.</i>';
          ?>
        </p>
        <p>This campaign was approved previously, so we are reasonably confident that the material
        	attains a certain standard. To be on the safe side however, please have another look at the files below.</p>
		<?php echo $upload_body; ?>
		<?php echo '<img src="user_data/tmp/send'.$send_id.'_1.png" id="page2_thmb" alt="Thumbnail1">
  		<img src="user_data/tmp/send'.$send_id.'_2.png" id="page3_thmb" alt="Thumbnail2">
  		<img src="user_data/tmp/send'.$send_id.'_3.png" id="page4_thmb" alt="Thumbnail3"><br><br>';?>
		You should approve this send request if the following conditions are satisfied:
		<ul>
			<li>Good overlap between professor's domain and project (e.g. professor covers <br>one aspect of project or combination of aspects better than anyone else)</li>
			<li>Professor's details (name, institution, publications) correct</li>
			<li>Professor's email address publicly accessible e.g. on institutional website</li>
			<li>Professor's institution at least in part publicly financed</li>
			<li>Uploaded material not obviously a step back from previous versions</li>
		</ul>
		Please remember that approving send requests to professors who cannot reasonably be
		expected to be knowledgeable about the material damages our site's credibility.
        <p>Approve or decline this send request?
          <select name="appr_or_decl" style="width: 200px">
            <option value="none">--</option>
            <option value="appr">Approve</option>
            <option value="decl">Decline (Reason: Scientific mismatch between researcher and proposal)</option>
            <option value="decl">Decline (Reason: Mismatch among name, institution and/or publications)</option>
            <option value="decl">Decline (Reason: Could not find e-mail)</option>
            <option value="decl">Decline (Reason: Researcher employed by private sector)</option>
            <option value="decl">Decline (Reason: Change-for-the-worse of upload)</option>
          </select>
          <button name="judge">Judge</button> 
          </p></form>
      </div>
