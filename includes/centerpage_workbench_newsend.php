<?php
//echo '<div id="centerpage">';
include("includes/cmpgn_header.php");

if(isset($_GET['upload']))
	$upload_id=$conn->real_escape_string(test($_GET['upload']));
else
{
	$sql="SELECT upload_id FROM upload WHERE upload_cmpgn='$cmpgn_id' ORDER BY upload_id DESC";
	$row=$conn->query($sql)->fetch_assoc();
	$upload_id=$row['upload_id'];
}

if(isset($_SESSION['user']) && $_SESSION['user']==$owner)
{
	$error_msg=$other_orcid=$confirm_family=$confirm_given=$Apologia=$pass_on_orcid="";
	if($isarchivized)
		$error_msg="No send for archivized campaigns!<br>";
	if(isset($_POST['Apologia'])) $Apologia=$conn->real_escape_string(test($_POST['Apologia']));
	
	if(isset($_POST['search']) || isset($_POST['ask_permission']))
	{
		$prof_gname=$conn->real_escape_string(test($_POST['gname']));
		$prof_fname=$conn->real_escape_string(test($_POST['fname']));
		$prof_instit=$conn->real_escape_string(test($_POST['instit']));
		if(isset($_POST['orcid_enter'])) $orcid_enter=$conn->real_escape_string(test($_POST['orcid_enter']));
		if(isset($_POST['otherorcid'])) $other_orcid=$conn->real_escape_string(test($_POST['otherorcid']));
		if(isset($_POST['confirm_family'])) $confirm_family=$conn->real_escape_string(test($_POST['confirm_family']));
		if(isset($_POST['confirm_given'])) $confirm_given=$conn->real_escape_string(test($_POST['confirm_given']));
		if(isset($_POST['instit_selec'])) $instit_selec=$conn->real_escape_string(test($_POST['instit_selec']));
		
		if(!empty($prof_instit))
		{
			$sql="SELECT i.institution_id, i.institution_name, c.country_name, i.institution_emailsuffix
				FROM institution i JOIN country c ON (i.institution_country=c.country_id) WHERE i.institution_name LIKE '%".$prof_instit."%' LIMIT 20";
			$result=$conn->query($sql);
			if($result->num_rows > 0)
			{
				$instit_selector="";
				while($row=$result->fetch_assoc())
				{
					if(!empty($instit_selec) && $instit_selec==$row['institution_id'])
						$instselected="selected";
					else $instselected="";
					$instit_selector=$instit_selector.'<option style="min-width: 400px; max-width: 400px" value="'.$row['institution_id'].'" '.$instselected.'>'.$row['institution_name'].', '.$row['country_name'].' @'.$row['institution_emailsuffix'].'</option>';
				}
            	$instit_selector='<select name="instit_selec" style="min-width: 400px; max-width: 400px">'.$instit_selector.'</select>';			
			}
			else $instit_selector='<i>Could not find institution, please try again</i>';
		}
		else $error_msg=$error_msg."Can't have empty institution!<br>";
		
		include("includes/send_selectors.php");

		if(strtotime($time_firstsend."+ 8 months") < strtotime("now"))
			$error_msg=$error_msg."Already 8 months since first send too late to ask for new permission.<br>";
		
		if(empty($error_msg) && isset($_POST['ask_permission']))
		{
			$regex = '#^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$#';

			if(empty($_POST['orcid_enter']))
				$error_msg=$error_msg."Need to choose researcher id!<br>";
			elseif($orcid_enter=='hasorcid' && (empty($_POST['otherorcid']) || !preg_match($regex,$other_orcid)))
				$error_msg=$error_msg."Checked other ORCID but provided none, or wrong format!<br>";
			elseif($orcid_enter=='hasorcid' && !isset($_POST['confirm_otherorcid']) && !isset($_POST['confirm_deduct_idea']))
			{
				$sql="SELECT prof_id, prof_givenname, prof_familyname FROM prof WHERE prof_orcid='$other_orcid'";
				$result=$conn->query($sql);
				if($result->num_rows > 0 && $row=$result->fetch_assoc())
					$error_msg=$error_msg.'Send to '.$row['prof_givenname'].' '.$row['prof_familyname'].' OK?<input type="checkbox" name="confirm_otherorcid" value="'.$row['prof_id'].'"><br>';
				//COUNTERCHECK WITH ORCID API HERE
				else $error_msg=$error_msg."Could not find this ORCID in database!<br>";
			}
			elseif($orcid_enter=='noorcid' && (empty($confirm_family) || empty($confirm_given)))
				$error_msg=$error_msg."Checked no ORCID but not confirmed name as identifier!<br>";
			elseif($orcid_enter=='noorcid' && (stripos($confirm_family,$prof_fname)===FALSE || (!empty($prof_gname) && stripos($confirm_given,$prof_gname)===FALSE)))
				$error_msg=$error_msg."Checked no ORCID but mismatch with confirmed name?<br>";
			elseif($orcid_enter=='noorcid' && empty($_POST['pub_confirm']))
				$error_msg=$error_msg.'Please select at least one publication so the moderators can find the researcher!<br>';
			elseif($orcid_enter=='noorcid' && empty($_POST['confirm_newrecord']) && !isset($_POST['confirm_deduct_idea']))
				$error_msg=$error_msg.'Request insertion of '.$confirm_given.' '.$confirm_family.' into database please confirm: <input type="checkbox" name="confirm_newrecord"><br>';
			if(empty($_POST['instit_selec']))
				$error_msg=$error_msg."Need to choose institution!<br>";

/*			if(empty($_POST['Apologia']))
				$error_msg=$error_msg."Please write justification why it has to be this prof!<br>";
			if(strlen($_POST['Apologia']) > 2000)
				$error_msg=$error_msg."Message too long please moderate yourself!<br>";*/

			$sql="SELECT 1 FROM upload WHERE upload_id='".$upload_id."' AND upload_cmpgn='".$cmpgn_id."'";
			if($conn->query($sql)->num_rows==0)
				$error_msg=$error_msg."Seems to be some problem between upload and campaign association ...<br>";

			//check whether at least one previous upload has been OKd
			$sql="SELECT 1 FROM upload WHERE upload_cmpgn='$cmpgn_id' AND upload_verdict_summary='1'";
			if($conn->query($sql)->num_rows==0)
				$error_msg=$error_msg."No upload for this campaign seems to have been OKd yet!";
			
			//check that not sent previously to same prof
//			$sql="SELECT 1 FROM send s JOIN upload u ON (s.send_upload=u.upload_id) WHERE send_prof='".$row['prof_id']."' AND u.upload_cmpgn='$cmpgn_id'"
			
			//check that last send permission more than 1 week ago
			$sql="SELECT s.send_timestamp FROM send s JOIN upload u ON (s.send_upload=u.upload_id) WHERE s.send_timestamp IS NOT NULL AND s.send_verdict IS NOT NULL AND u.upload_cmpgn='$cmpgn_id' ORDER BY send_timestamp DESC";
			$result=$conn->query($sql);
			$row=$result->fetch_assoc();
//			if($result->num_rows > 0 && strtotime($row['send_timestamp']."+ 1 week") > strtotime("now"))
//				$error_msg=$error_msg."Last send less than 1 week ago please wait a little!<br>";
			
			//check that enough points
			$sql="SELECT s.student_pts_cmpgn-s.student_pts_cmpgn_consmd+s.student_pts_feat+u.user_pts_misc-u.user_pts_fail AS pts_sum,
				s.student_pts_cmpgn-s.student_pts_cmpgn_consmd AS idea_pts, u.user_pts_misc, s.student_pts_feat
				FROM user u JOIN student s ON (s.student_user_id=u.user_id)
				WHERE user_id='".$_SESSION['user']."'";
			$result=$conn->query($sql);
			$row=$result->fetch_assoc();
			if($idea_pts_suppl+$row['pts_sum'] <= 0)
				$error_msg=$error_msg."Total points zero or negative please earn more points!<br>";
			elseif($row['user_pts_misc'] <= 0 && $idea_pts_suppl+$row['idea_pts'] <= 0 && $row['student_pts_feat'] <= 0)
				$error_msg=$error_msg."Not enought points to send please earn some!<br>";
			elseif(empty($error_msg))
			{
				//CHECK pub_confirm AGAINST pubreg
				$sql="SELECT r1.resbox_impl_researcher AS res1, r2.resbox_impl_researcher AS res2, r3.resbox_impl_researcher AS res3 FROM pubreg p
					JOIN resbox_impl r1 ON (p.pubreg_resbox1=r1.resbox_impl_id)
					JOIN resbox_impl r2 ON (p.pubreg_resbox2=r2.resbox_impl_id)
					JOIN resbox_impl r3 ON (p.pubreg_resbox3=r3.resbox_impl_id)
					WHERE pubreg_cmpgn='$cmpgn_id' ORDER BY r1.resbox_impl_rel_nb DESC, r2.resbox_impl_rel_nb DESC, r3.resbox_impl_rel_nb DESC";
				
				if(!empty($_POST['pub_confirm']))
				{
					$rel_nbs=array();
					foreach($_POST['pub_confirm'] as $pub_item)
						$rel_nbs[$pub_item]=virtuoso_nb($pub_item);
					arsort($rel_nbs);
					$row3=$conn->query($sql)->fetch_assoc();
					foreach($row3 as $pubreg_item)
					{
						$count=0;
						foreach($rel_nbs as $rel_key => $rel_nb)
						{
							$distance=virtuoso_dist($rel_key,$pubreg_item);
							if(!empty($distance) && $distance <= 10)
							{
								$verified_dist=TRUE;
								break;
							}
							elseif($count==4) break;
							$count++;
						}
					}
				}	
				
				if(!empty($_POST['pub_confirm']) && !empty($verified_dist))
				{
					if(!isset($_POST['confirm_deduct_idea']) && ($row['student_pts_feat'] > 0 && $row['user_pts_misc'] <= 0))
						$error_msg=$error_msg.'Not enough Misc points really deduct <b>1x</b> Feat points?<input type="checkbox" name="confirm_deduct_idea"><br>';
					elseif(!isset($_POST['confirm_deduct_idea']) && ($idea_pts_suppl+$row['idea_pts'] > 0 && $row['user_pts_misc'] <= 0))
						$error_msg=$error_msg.'Not enough Misc points really deduct <b>1x</b> Idea points?<input type="checkbox" name="confirm_deduct_idea"><br>';
				}
				elseif(!isset($_POST['confirm_deduct_idea']) && $idea_pts_suppl+$row['idea_pts'] > 0)
					$error_msg=$error_msg.'Could not verify publications really deduct <b>1x</b> Idea points?<input type="checkbox" name="confirm_deduct_idea"><br>';
				elseif($idea_pts_suppl+$row['idea_pts'] <= 0)
					$error_msg=$error_msg.'Could not verify publications please earn some more Idea points!<br>';
			}
			
			if(!empty($orcid_enter) && ctype_digit($orcid_enter) && empty($_POST['confirm_emptyorcid']) && $conn->query("SELECT 1 FROM prof WHERE (prof_description IS NULL OR LENGTH(prof_description) < 1) AND prof_hasactivity IS NULL AND prof_country IS NULL AND prof_resbox IS NULL AND prof_id='".$conn->real_escape_string(test($orcid_enter))."'")->num_rows > 0)
				$error_msg=$error_msg.'ORCID profile seemingly empty really OK? <input type="checkbox" name="confirm_emptyorcid"><br>';

			if(empty($error_msg))
			{
				if($orcid_enter=="hasorcid")
				{
					$sql="SELECT prof_id FROM prof WHERE prof_orcid='$other_orcid'";
					$row=$conn->query($sql)->fetch_assoc();
					$check_prof="'".$row['prof_id']."'";
				}
				elseif($orcid_enter!="noorcid")
					$check_prof="'".$orcid_enter."'";
				if($conn->query("SELECT 1 FROM review r JOIN send s ON (r.review_send=s.send_id)
					JOIN upload u ON (s.send_upload=u.upload_id) WHERE r.review_prof=$check_prof AND (u.upload_cmpgn='$cmpgn_id' OR r.review_time_requested + INTERVAL 2 MONTH > NOW())")->num_rows > 0
					|| $conn->query("SELECT 1 FROM review r1 JOIN review r2 ON (r1.review_prof=r2.review_together_with) JOIN send s ON (r2.review_send=s.send_id)
					JOIN upload u ON (s.send_upload=u.upload_id) WHERE r1.review_prof=$check_prof AND (u.upload_cmpgn='$cmpgn_id' OR r1.review_time_requested + INTERVAL 2 MONTH > NOW())")->num_rows > 0)
					$error_msg=$error_msg."You or someone else already e-mailed Prof recently, please wait a month!<br>";
			}

			//PROCEED TO INSERT
			if(empty($error_msg))
			{
				if(($row['student_pts_feat'] > 0 && $row['user_pts_misc'] <= 0) && !empty($_POST['pub_confirm']) && !empty($verified_dist))
					$conn->query("UPDATE student SET student_pts_feat=student_pts_feat-1 WHERE student_user_id='".$_SESSION['user']."'");
				elseif(($idea_pts_suppl+$row['idea_pts'] > 0 && $row['user_pts_misc'] <= 0) || empty($_POST['pub_confirm']) || empty($verified_dist))
					$conn->query("UPDATE student SET student_pts_cmpgn_consmd=student_pts_cmpgn_consmd+1 WHERE student_user_id='".$_SESSION['user']."'");
				else $conn->query("UPDATE user SET user_pts_misc=user_pts_misc-1 WHERE user_id='".$_SESSION['user']."'");
				
				$insert_family=$prof_fname;
				$insert_given=$prof_gname;
				
				if($orcid_enter=="hasorcid")
					$insert_prof="'".$conn->real_escape_string(test($_POST['confirm_otherorcid']))."'";
				elseif($orcid_enter!="noorcid")
					$insert_prof="'".$orcid_enter."'";
				else
				{
					$insert_prof="NULL";
					$insert_family=$confirm_family;
					$insert_given=$confirm_given;
				}
				
				if(!empty($_POST['pub_confirm']))
				{
					$sql="INSERT INTO resbox VALUES ()";
					$conn->query($sql);
				
					$sql="SELECT LAST_INSERT_ID();";
					$resbox_id=$conn->query($sql)->fetch_assoc();
					$resbox_id="'".$resbox_id['LAST_INSERT_ID()']."'";
					
					foreach($_POST['pub_confirm'] as $item)
					{
						$item=$conn->real_escape_string(test($item));
						$sql="INSERT INTO resbox_impl (resbox_impl_id,resbox_impl_researcher,resbox_impl_rel_nb) VALUES ($resbox_id,'$item','".$rel_nbs[$item]."')";
						$conn->query($sql);
					}
				}
				else $resbox_id="NULL";
				
				//CREATE TASK, ASSEMBLE VERDICT & NOTIFY MODERATORS
				$verdict_id=create_verdict($conn,$cmpgn_id,'SEND');
				
				$sql="INSERT INTO send (send_upload, send_prof, send_prof_givenname, send_prof_familyname,
						send_prof_institution, send_timestamp, send_resbox, send_verdict) VALUES ('$upload_id', $insert_prof,
						'$insert_given','$insert_family','$instit_selec',NOW(),$resbox_id, '$verdict_id')";
				$conn->query($sql);

				$sql="SELECT LAST_INSERT_ID();";
				$send_id=$conn->query($sql)->fetch_assoc();
				$send_id=$send_id['LAST_INSERT_ID()'];
				
				//GENERATE THUMBNAILS
				$row=$result->fetch_assoc();
				$target='user_data/uploads/'.$cmpgn_id.'_'.$upload_id.'_1.pdf';
//				putenv('PATH=C:/Program Files/ImageMagick-7.0.5-Q16/');
				putenv('PATH=/usr/bin');
				exec("convert ".$target."[1] -resize 180x180! user_data/tmp/send".$send_id."_1.png");
				exec("convert ".$target."[2] -resize 180x180! user_data/tmp/send".$send_id."_2.png");
				exec("convert ".$target."[3] -resize 180x180! user_data/tmp/send".$send_id."_3.png");
				
				header("Location: index.php?confirm=send_permission");
			}
		}
	}

	$sql="SET @version=0;";
	$result=$conn->query($sql);
	$sql="SELECT @version:=@version+1 AS version, upload_id FROM upload WHERE upload_cmpgn='".$cmpgn_id."' ORDER BY version DESC";
	$result=$conn->query($sql);
	while($row=$result->fetch_assoc())
		if($row['upload_id']==$upload_id)
		{
			$version=$row['version'];
			break;
		}

	$sql="SELECT 1 FROM send s JOIN upload u ON (s.send_upload=u.upload_id)
		JOIN cmpgn c ON (u.upload_cmpgn=c.cmpgn_id) WHERE c.cmpgn_user='".$_SESSION['user']."'
		AND s.send_verdict_summary='1' AND cmpgn_time_firstsend IS NOT NULL";
	if($conn->query($sql)->num_rows > 0 && $conn->query("SELECT 1 FROM student WHERE student_user_id='".$_SESSION['user']."'
		AND student_cmpgn_shadowed_latest IS NULL AND (student_taskexcl_cmpgn='0' OR student_taskexcl_cmpgn IS NULL)")->num_rows > 0)
		$cantake_excl_prop=TRUE;
	if(isset($_POST['send_exec']) && isset($_POST['excl_prop']) && !empty($cantake_excl_prop))
	{
		$conn->query("UPDATE student SET student_taskexcl_cmpgn='1' WHERE student_user_id='".$_SESSION['user']."'");
		$cantake_excl_prop=FALSE;
	}

	//check that no other send in progress
	$sql="SELECT review_id, review_time_requested, review_agreed, review_prof, review_together_with
			FROM review r JOIN upload u ON (r.review_upload=u.upload_id)
			WHERE review_time_requested IS NOT NULL AND review_time_submit IS NULL
			AND review_time_tgth_passedon IS NULL AND review_time_aborted IS NULL AND
			u.upload_cmpgn='$cmpgn_id'";
	$result=$conn->query($sql);
	if($result->num_rows > 0)
	{
		$row=$result->fetch_assoc();
		$review_id=$row['review_id'];
		$reviewprof=$row['review_prof'];
		
		if(isset($_POST['send_exec']))
		{
			$abort_select=$conn->real_escape_string(test($_POST['abort_select']));
			if($abort_select=="none")
				$error_msg=$error_msg."Cannot have 'do nothing' if you want to change review prof!<br>";
			elseif($abort_select=="pass_on")
			{
				$pass_on_orcid=$conn->real_escape_string(test($_POST['pass_on_orcid']));
				$regex = '#^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$#';
				if(!preg_match($regex,$pass_on_orcid) || empty($pass_on_orcid))
					$error_msg=$error_msg.'No ORCID provided, or wrong format!<br>';
				elseif($conn->query("SELECT 1 FROM review r1 JOIN review r2 ON (r1.review_together_with=r2.review_prof) WHERE r1.review_upload=r2.review_upload AND r2.review_id='$review_id'")->num_rows > 0)
					$error_msg=$error_msg.'No chaining deferred responsibility please!<br>';
				elseif($conn->query("SELECT 1 FROM review r JOIN upload u ON (r.review_upload=u.upload_id)
					JOIN prof p ON (p.prof_id=r.review_prof)
					WHERE u.upload_cmpgn='$cmpgn_id' AND p.prof_orcid='$pass_on_orcid'")->num_rows > 0)
					$error_msg=$error_msg."Already got a review from this professor!<br>";
				if(empty($error_msg) && !isset($_POST['confirm_pass_on']))
				{
					$sql="SELECT prof_id, prof_familyname, prof_givenname FROM prof WHERE prof_orcid='$pass_on_orcid'";
					$result=$conn->query($sql);
					if($result->num_rows > 0)
					{
						$row3=$result->fetch_assoc();
						$error_msg=$error_msg.'Really pass on to '.$row3['prof_givenname'].' '.$row3['prof_familyname'].' ? <input type="checkbox" name="confirm_pass_on" value="'.$row3['prof_id'].'"><br>';
					}
					else $error_msg=$error_msg."Could not find ORCID!<br>";
				}
				elseif(empty($error_msg))
				{
					$pass_on_prof=$conn->real_escape_string($_POST['confirm_pass_on']);
					$sql="UPDATE review SET review_together_with='$pass_on_prof' WHERE review_id='$review_id'";
					$conn->query($sql);
					header("Location: index.php");
				}
			}
			elseif($abort_select=="abort")
			{
				//GIVE MINUS POINT IN CASE PROFESSOR NOT CONSULTED LINK IN FIRST 2 WEEKS
				$conn->query("UPDATE user u
					JOIN cmpgn c ON (c.cmpgn_user=u.user_id)
					JOIN upload ul ON (c.cmpgn_id=ul.upload_cmpgn)
					JOIN review r ON (r.review_upload=ul.upload_id)
					SET u.user_pts_fail=u.user_pts_fail+1
					WHERE r.review_id='$review_id' AND r.review_sentreminder='1' AND r.review_agreed IS NULL");

				$sql="UPDATE review SET review_time_aborted=NOW(), review_aborted_byuser='1' WHERE review_id='$review_id'";
				$conn->query($sql);
				
				send_profnotif($conn, $reviewprof, 3, "User abortion", "Apparently the student has decided to rather approach someone else so your review has now been closed.", '', '');
				header("Location: index.php");
			}
		}
		
		$sql="SELECT prof_familyname FROM prof WHERE prof_id='".$row['review_prof']."'";
		$row2=$conn->query($sql)->fetch_assoc();

		if(isset($_POST['pass_on_orcid'])) $pass_on_orcid=$conn->real_escape_string(test($_POST['pass_on_orcid']));

		if(isset($abort_select) && $abort_select=="pass_on") $pass_on_checked="checked"; else $pass_on_checked="";
		if(empty($row['review_together_with']) && $conn->query("SELECT 1 FROM review r1 JOIN review r2 ON (r1.review_together_with=r2.review_prof) WHERE r1.review_upload=r2.review_upload AND r2.review_id='$review_id'")->num_rows == 0)
		{
			$can_abort=TRUE;
			$abort_selector='<input name="abort_select" value="pass_on" type="radio" '.$pass_on_checked.'>Allow Prof. '.$row2['prof_familyname'].' to <br>pass on review responsibilities to: <input type="text" name="pass_on_orcid" placeholder="Enter ORCID" value="'.$pass_on_orcid.'"><br>';
		}
		else $abort_selector='';

		if(($row['review_agreed']=='1' && strtotime($row['review_time_requested']."+6 weeks") < strtotime("now"))
			|| (empty($row['review_agreed']) && strtotime($row['review_time_requested']."+2 weeks") < strtotime("now")))
		{
			$can_abort=TRUE;
			$abort_selector='<input name="abort_select" value="none" type="radio" checked>Do nothing<br>
				<input name="abort_select" value="abort" type="radio">Abort<br>
				'.$abort_selector.'
				<span style="font-size: small">Note: Pass-on function can only be used once and with consent of both professors.</span>';
		}
		else
		{
			if(!empty($abort_selector))
				$abort_selector='<input name="abort_select" value="none" type="radio" checked>Do nothing<br>
				'.$abort_selector.'
				<span style="font-size: small">Note: Pass-on function can only be used once and with consent of both professors.</span>';
		}
		if($row['review_agreed']=='1')
			$time_limit=strtotime($row['review_time_requested']."+6 weeks");
		else $time_limit=strtotime($row['review_time_requested']."+2 weeks");
		$abort_selector='<i>Before you can send the material out to anyone else,
				you first have to wait for the current professor to submit a review. You can
				also abort if the professor exceeds the guaranteed time limit ('.date("Y-m-d H:i:s",$time_limit).').</i><br>'.$abort_selector;
	}
	else
	{
		if(isset($_POST['send_exec']))
		{
			$send_select=$conn->real_escape_string(test($_POST['send_select']));
			if($send_select=="none")
				$error_msg=$error_msg."Please select professor to send to!<br>";
			if(empty($Apologia))
				$error_msg=$error_msg."Do not forget personal message to prof!<br>";
			if(strtotime($time_firstsend."+ 8 months") < strtotime("now"))
				$error_msg=$error_msg."Already 8 months since first send too late to contact new professor.<br>";
			$sql="SELECT upload_id FROM review r JOIN upload u ON (r.review_upload=u.upload_id)
				WHERE review_id='$send_select' AND u.upload_cmpgn='$cmpgn_id'";
			$result=$conn->query($sql);
			if(empty($error_msg) && $result->num_rows > 0)
			{
				//GENERATE DIRECT LOGIN CODE
				$direct_login_token=openssl_random_pseudo_bytes(32);

				//UPDATE REVIEW TIMESTAMP AND REVIEW DIRECT LOGIN CODE
				$direct_login_link="https://www.myphdidea.org/invitation.php?directlogin=".bin2hex($direct_login_token);

				//SEND EMAILS
				$text1="You have been contacted by a student who wants to pitch a research project idea to you! The student writes\n\n'"
							.$Apologia."'\n\nIn order to learn about this research pitch, please follow the link below:\n\n";
				$text2="\n\nFrom the reception of this mail, you are guaranteed a minimum of 2 weeks to make a decision on whether you want to review this idea. If you signal a positive intent on the site the link will take you to, you are then guaranteed an additional 4 weeks, for a total of 6 weeks to submit your review, extensible at the discretion of the student.";

				$sql="SELECT review_prof, review_send FROM review WHERE review_id='$send_select'";
				$row=$conn->query($sql)->fetch_assoc();
				if(!empty(send_profnotif($conn, $row['review_prof'], 0, "Research project idea", $text1, $direct_login_link, $text2)))
				{
					$direct_login_token=$conn->real_escape_string($direct_login_token);
					$sql="UPDATE review SET review_time_requested=NOW(), review_directlogin='$direct_login_token',
						review_msg_toprof='$Apologia' WHERE review_id='$send_select'";echo $sql;
					$conn->query($sql);

					$sql="UPDATE prof SET prof_hasactivity='1' WHERE prof_id='".$row['review_prof']."'";
					$conn->query($sql);
				
					//SET FIRSTSEND (LOCKS TITLE EDIT AND ENTERS INTO NEWSFEED COMPETITION)
					$result=$conn->query("SELECT cmpgn_title FROM cmpgn WHERE cmpgn_time_firstsend IS NULL AND cmpgn_displayinfeed='1' AND cmpgn_id='$cmpgn_id'");
					$conn->query("UPDATE cmpgn SET cmpgn_time_firstsend=NOW() WHERE cmpgn_time_firstsend IS NULL AND cmpgn_id='$cmpgn_id'");
					if($result->num_rows > 0)
					{
//						putenv('PATH=C:/Program Files/ImageMagick-7.0.5-Q16/');
						exec("convert user_data/tmp/send".$row['review_send']."_*.png +append user_data/tmp/twitter_".$row['review_send'].".png");
													
						$row2=$result->fetch_assoc();
						if(strlen("Launched: ".$row2['cmpgn_title']) < 140)
							post_tweet("Launched: ".$row2['cmpgn_title'],array('user_data/tmp/twitter_'.$row['review_send'].'.png'));
						else post_tweet(substr("Launched: ".$row2['cmpgn_title'],0,135)." ...",array('user_data/tmp/twitter_'.$row['review_send'].'.png'));

						unlink("user_data/tmp/twitter_".$row['review_send'].".png");						
					}

					header("Location: index.php?confirm=send_exec");
				}
				else $error_msg=$error_msg."Could not send mail!<br>";
			}
			elseif(empty($error_msg)) $error_msg=$error_msg."Does not seem to have send permission for this review!";
		}
		
		//ADD REVIEWS FOR THOSE WITH DIALOGUE
		$result_dialogue=$conn->query("SELECT DISTINCT d.dialogue_prof, u2.upload_id FROM dialogue d
			JOIN autoedit a ON (a.autoedit_prof=d.dialogue_prof)
			JOIN upload u2 ON (u2.upload_cmpgn=d.dialogue_cmpgn)
			WHERE d.dialogue_cmpgn='$cmpgn_id' AND u2.upload_verdict_summary='1'
			AND NOT EXISTS (SELECT 1 FROM send s JOIN upload u ON (s.send_upload=u.upload_id) WHERE u.upload_cmpgn='$cmpgn_id' AND s.send_prof=d.dialogue_prof)
			GROUP BY d.dialogue_prof");
		while($row=$result_dialogue->fetch_assoc())
		{
			$conn->query("INSERT INTO send (send_prof, send_upload, send_verdict_summary,send_timestamp) SELECT '".$row['dialogue_prof']."', max(upload_id), '1', NOW() FROM upload WHERE upload_cmpgn='$cmpgn_id'");

			$sql="SELECT LAST_INSERT_ID();";
			$send_id=$conn->query($sql)->fetch_assoc();
			$send_id=$send_id['LAST_INSERT_ID()'];

			//GENERATE THUMBNAILS
			$target='user_data/uploads/'.$cmpgn_id.'_'.$row['upload_id'].'_1.pdf';
			putenv('PATH=/usr/bin');
			exec("convert ".$target."[1] -resize 180x180! user_data/tmp/send".$send_id."_1.png");
			exec("convert ".$target."[2] -resize 180x180! user_data/tmp/send".$send_id."_2.png");
			exec("convert ".$target."[3] -resize 180x180! user_data/tmp/send".$send_id."_3.png");

			$conn->query("INSERT INTO review (review_prof, review_upload, review_send) SELECT '".$row['dialogue_prof']."', s.send_upload, s.send_id FROM send s JOIN upload u ON (s.send_upload=u.upload_id) WHERE s.send_prof='".$row['dialogue_prof']."' AND u.upload_cmpgn='$cmpgn_id'");
		}
		//DELETE THOSE WHERE PASSED ON
		$conn->query("DELETE r FROM review r JOIN send s ON (r.review_send=s.send_id)
			JOIN upload u ON (s.send_upload=u.upload_id)
			JOIN review r1 ON (r1.review_prof=s.send_prof)
			JOIN review r2 ON (r2.review_together_with=s.send_prof)
			WHERE u.upload_cmpgn='$cmpgn_id'");
		$conn->query("DELETE s FROM send s JOIN upload u ON (s.send_upload=u.upload_id)
			JOIN review r1 ON (r1.review_prof=s.send_prof)
			JOIN review r2 ON (r2.review_together_with=s.send_prof)
			WHERE u.upload_cmpgn='$cmpgn_id'");
		
		$send_select="";
		$sql="SELECT r.review_prof, r.review_id FROM send s
			JOIN review r ON (s.send_id=r.review_send)
			JOIN upload u ON (s.send_upload=u.upload_id)
			WHERE r.review_time_requested IS NULL AND s.send_verdict_summary='1' AND u.upload_cmpgn='$cmpgn_id'";
//		$sql="SELECT r.review_prof FROM send s JOIN review r ON (s.send_id=r.review_send) WHERE r.review_time_requested IS NULL AND s.send_verdict_summary='1' AND s.send_upload='$upload_id'";
		$result_send=$conn->query($sql);
		while($row=$result_send->fetch_assoc())
		{
			$sql="SELECT prof_givenname, prof_familyname, prof_email, prof_email_alt FROM prof WHERE prof_id='".$row['review_prof']."'";
			$row2=$conn->query($sql)->fetch_assoc();
			$prof_email=$row2['prof_email'];
			if(!empty($row2['prof_email_alt'])) $prof_email=$prof_email.", ".$row2['prof_email_alt'];
			if(empty($prof_email) && empty($prof_email_alt))
			{
				$row3=$conn->query("SELECT autoedit_email FROM autoedit WHERE autoedit_email_auth IS NOT NULL AND autoedit_prof='".$row['review_prof']."'")->fetch_assoc();
				$prof_email=$row3['autoedit_email'];
			}
			if(!empty($prof_email)) $send_select=$send_select.'<input value="'.$row['review_id'].'" name="send_select" type="radio">Send to <a href="index.php?prof='.$row['review_prof'].'">'.$row2['prof_givenname'].' '.$row2['prof_familyname'].'</a> ('.$prof_email.')<br>';
		}
		if(!empty($send_select)) $send_select='<input value="none" name="send_select" type="radio" checked>None of the below</a><br>'.$send_select;

//    	$sql="SELECT d.dialogue_prof FROM dialogue d JOIN review r ON (d.dialogue_prof=r.review_prof) WHERE r.review_id IS NULL AND d.dialogue_cmpgn='$cmpgn_id'";
/*		$result_dialogue=$conn->query("SELECT d.dialogue_prof FROM dialogue d WHERE d.dialogue_cmpgn='$cmpgn_id'");
		while($row=$result_dialogue->fetch_assoc())
		{
			if($conn->query("SELECT 1 FROM review WHERE review_prof='".$row['dialogue_prof']."'")->num_rows==0)
				$conn->query("INSERT INTO review (review_prof, review_upload) VALUES SELECT ".$row['dialogue_prof'].", max(upload_id) FROM upload WHERE upload_cmpgn='$cmpgn_id'");
//			$sql="SELECT prof_givenname, prof_familyname FROM prof WHERE prof_id='".$row['dialogue_prof']."'";
//			$row2=$conn->query($sql)->fetch_assoc();
//			$send_select=$send_select.'<input value="'.$row['dialogue_prof'].'" name="send_select" type="radio">Send to <a href="index.php?prof='.$row['dialogue_prof'].'">'.$row2['prof_givenname'].' '.$row2['prof_familyname'].'</a><br>';
		}*/
		if(!empty($send_select)) $send_select=$send_select.'<p>You should also add a short personal message to the prof:</p>
        	<div style="text-align: center"><textarea style="width: 400px; height: 200px;"
			class="indentation" name="Apologia">'.$Apologia.'</textarea></div>';
	}
}	

?>
<div id="centerpage">
<?php include("includes/cmpgn_header_display.php")?>
<form method="post" action="">
<?php if(!empty($error_msg)) echo '<br><div style="color: red; text-align: center">'.$error_msg.'</div>';?>
        <h3>Send upload v. <?php echo $version?> to reviewer</h3>
        <p>During the maximum 8 months duration of your campaign, you can submit
          your pitch to professors or other researchers, both registered
          and new to our database. Researchers are guaranteed 2 weeks to
          accept the task, and 6 weeks to write a review,
          after which extension is at your own discretion.
          You will not be able to make
          another submission to a reviewer during this period, so choose wisely
          (in case your professor feels he/she was not the right choice, you
          will be able to transfer reviewer responsibility once). All reviews will
          be published on your campaign page.</p>
        <p>To make sure you do not spam professors needlessly, the send request will
          pend approval by the 2 moderators. Try to give the researcher
          a phone call first, in order to verify both thematic overlap, and whether the person is
          motivated at all.</p>
        <p>The cost for a send request is <b>1x</b> Misc if you enter publications (or <b>1x</b> Idea else).
			After execution, you have got 2 weeks to get the professor to consult the link in the invitation we send (you get a notice).
			Else, there is a <b>1x</b> Fail penalty for aborting.</p>
        <?php
			if(!empty($send_select) || !empty($abort_selector))
				echo "You now have permission to send to the following professors:";
			else echo '<p class="indentation"><i>Request permission below, then return here to send to professors.</i></p>';
        ?>
        <p class="indentation">
        	<?php if(!empty($abort_selector)) echo $abort_selector;
				elseif(!empty($send_select)) echo $send_select;?>
			<?php
				if(!empty($cantake_excl_prop) && (!empty($send_select) || (!empty($abort_selector)) && !empty($can_abort)))
					echo '<div><input type="checkbox" name="excl_prop" checked> Turn on task proposals with 24 hours timed exclusivity in Settings.</div>';
			?>
            <p style="text-align: right;"> <button name="send_exec" <?php if(empty($send_select) && empty($can_abort)) echo "disabled"; ?>>
            		Execute</button></p>
        </p>
        <h3>Request upload v. <?php echo $version?> send permission</h3>
        <p>Please begin by entering surname, given name and institution of the professor:</p>
        <div class="indentation"> 
          <label>Family name:</label><input type="text" name="fname" value="<?php if(!empty($prof_fname)) echo $prof_fname; ?>">
          <label>Given name (or blank):</label><input type="text" name="gname" value="<?php if(!empty($prof_gname)) echo $prof_gname; ?>">
          <p><label>Institution:</label><input type="text" name="instit" value="<?php if(!empty($prof_instit)) echo $prof_instit; ?>">
          </p>
          <p style="text-align: right;"> <button name="search">Search</button>
          </p>
        </div>
        We use <a href="index.php?page=faq#orcid">ORCID</a> to identify researchers, which ensures that only one profile per researcher gets created,
        and that all reviews written by that researcher are grouped together. Please check whether any of the
        below matches the researcher, googling the ORCID to investigate further if necessary (you will be penalized if you get it
        wrong):
        <p class="indentation">
          <?php
          		if(isset($_POST['ask_permission']) || isset($_POST['search'])/*!empty($orcid_selector)*/)
				{
					if(isset($orcid_enter) && $orcid_enter=="hasorcid")
						$checkothorcid="checked";
					else $checkothorcid="";
          			if(!isset($orcid_selector)) $orcid_selector="";
          			echo '<div class="indentation">'.$orcid_selector;
					echo '<input value="hasorcid" name="orcid_enter" type="radio" '.$checkothorcid.'>Manually enter
						ORCID: <input type="text" name="otherorcid" value="'.$other_orcid.'"><br>
          				<input value="noorcid" name="orcid_enter" type="radio">I could not
          				find an ORCID.<br>
          				<div class="ifnoorcid">Confirm family name: <input type="text" style="width: 140px" name="confirm_family" value="'.$confirm_family.'"> 
          					Given name: <input type="text" style="width: 140px" name="confirm_given" value="'.$confirm_given.'"></div>';
					echo '<span style="font-size: smaller">Note I: In case ownership of an ORCID is ambiguous, please create a new profile. Note II: In case you find <i>both</i> a profile already in use
						and an ORCID, please choose the profile.</span>';
				}
				else echo '<i>Please complete search fields above.</i>';
          ?>
        </p>
        Institution (please select):
        <p class="indentation">
        <?php
        	if(!empty($instit_selector)) echo $instit_selector;
			else echo '<i>Please enter institution searchname above.</i>';
		?></p>
        <p>Publications (please tick all co-authored by this researcher):</p>
        <table style="width: 100%" border="0">
          <tbody>
            <tr>
              <td style="width: 92.1px;">Author<br>
              </td>
              <td style="width: 284.3px;">Title<br>
              </td>
              <td style="width: 35.3px;">Date<br>
              </td>
              <td style="width: 15.3px;"><br>
              </td>
            </tr>
            <?php
            	if(!empty($pub_selector))
            		echo $pub_selector;
				else echo '<tr>
              			<td><br></td>
              			<td><i>Click "search" to find publications.</i><br></td>
              			<td><br></td>
              			<td><br></td></tr>';
            ?>
          </tbody>
        </table>
<!--        <p>Please explain why you think this researcher would be a good choice, e.g. you
          have cited his/her papers in your draft. Your justification will be
          visible to the moderators:</p>
        <div style="text-align: center"><textarea style="width: 400px; height: 200px;"
class="indentation" name="Apologia"><?php if(!empty($Apologia)) echo $Apologia; ?></textarea></div>-->
        <p style="text-align: right;"> <button name="ask_permission">Request permission</button></p></form>
      </div>
      </div>
