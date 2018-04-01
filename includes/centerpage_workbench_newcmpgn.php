<?php
//CHECK WHETHER PUBREG/RESBOX FILLED OUT?
$title=$abstract=$keywords=$cmpgn_extlink=$cmpgn_intlink=$blanknames="";
$xmin1=$xmax1=$ymin1=$ymax1=$xmin2=$xmax2=$ymin2=$ymax2=$xmin3=$xmax3=$ymin3=$ymax3="";
$upload1_name=$upload2_name=$upload3_name="";
	if($_SESSION['user']!=1 && $conn->query("SELECT 1 FROM student s
	JOIN user u1 ON (s.student_user_id=u1.user_id)
	WHERE EXISTS (SELECT 1 FROM user u2 WHERE u2.user_id='".$_SESSION['user']."' AND (u2.user_subject1=u1.user_subject1 OR u2.user_subject1=u1.user_subject2 OR u2.user_subject2=u1.user_subject1 OR u2.user_subject2=u1.user_subject2))
	AND s.student_email_auth IS NOT NULL AND s.student_verdict_summary='1'")->num_rows < 50)
	 	$error_msg='Less than 50 registered students for your subject, please wait, sign up friends or consider <a href="index.php?workbench=newarchvcmpgn">archivized campaign</a> instead.<br>';
if($_SERVER['REQUEST_METHOD']=='POST' && !empty($_SESSION['user']))
{
	if(isset($_POST['title'])) $title=test($_POST['title']);
	if(isset($_POST['abstract'])) $abstract=test($_POST['abstract']);	
	if(isset($_POST['keywords'])) $keywords=test($_POST['keywords']);
	if(isset($_POST['cmpgn_extlink'])) $cmpgn_extlink=test($_POST['cmpgn_extlink']);
	if(isset($_POST['cmpgn_intlink'])) $cmpgn_intlink=test($_POST['cmpgn_intlink']);
	if(isset($_POST['xmin1']) && is_numeric($_POST['xmin1'])) $xmin1=test($_POST['xmin1']);
	if(isset($_POST['xmax1']) && is_numeric($_POST['xmax1'])) $xmax1=test($_POST['xmax1']);
	if(isset($_POST['ymin1']) && is_numeric($_POST['ymin1'])) $ymin1=test($_POST['ymin1']);
	if(isset($_POST['ymax1']) && is_numeric($_POST['ymax1'])) $ymax1=test($_POST['ymax1']);
	if(isset($_POST['xmin2']) && is_numeric($_POST['xmin2'])) $xmin2=test($_POST['xmin2']);
	if(isset($_POST['xmax2']) && is_numeric($_POST['xmax2'])) $xmax2=test($_POST['xmax2']);
	if(isset($_POST['ymin2']) && is_numeric($_POST['ymin2'])) $ymin2=test($_POST['ymin2']);
	if(isset($_POST['ymax2']) && is_numeric($_POST['ymax2'])) $ymax2=test($_POST['ymax2']);
	if(isset($_POST['xmin3']) && is_numeric($_POST['xmin3'])) $xmin3=test($_POST['xmin3']);
	if(isset($_POST['xmax3']) && is_numeric($_POST['xmax3'])) $xmax3=test($_POST['xmax3']);
	if(isset($_POST['ymin3']) && is_numeric($_POST['ymin3'])) $ymin3=test($_POST['ymin3']);
	if(isset($_POST['ymax3']) && is_numeric($_POST['ymax3'])) $ymax3=test($_POST['ymax3']);
	if(!empty($_POST['blanknames'])) $blanknames=$_POST['blanknames'];
	if(isset($_POST['pubid'])) $pubid=test($_POST['pubid']); else $pubid="";
/*	if(!empty($_FILES["upload1"]["name"]))
		$upload1_name=test($_FILES["upload1"]["name"]);
	else*/if(!empty($_POST["upload1_name"]) && !isset($_POST['rmv1']))
		$upload1_name=test($_POST["upload1_name"]);
/*	if(!empty($_FILES["upload2"]["name"]))
		$upload2_name=test($_FILES["upload2"]["name"]);
	else*/if(!empty($_POST["upload2_name"]) && !isset($_POST['rmv2']))
		$upload2_name=test($_POST["upload2_name"]);
/*	if(!empty($_FILES["upload3"]["name"]))
		$upload3_name=test($_FILES["upload3"]["name"]);
	else*/if(!empty($_POST["upload3_name"]) && !isset($_POST['rmv3']))
		$upload3_name=test($_POST["upload3_name"]);
	$error_msg="";

	if(empty($_FILES["upload1"]["tmp_name"]) && empty($upload1_name))
		$error_msg=$error_msg."At least first upload required<br>";
	elseif(function_exists('finfo_open'))
	{
		$finfo = finfo_open(FILEINFO_MIME);
    	if(!empty($_FILES["upload1"]["tmp_name"]))
		{
			if ($_FILES["upload1"]["size"] > 5000000)
    			$error_msg=$error_msg."File 1 too large!<br>";
			elseif(strtolower(pathinfo(test($_FILES["upload1"]["name"]),PATHINFO_EXTENSION))!="pdf")
				$error_msg=$error_msg."File 1 not a pdf file?<br>";
			elseif(strpos(finfo_file($finfo, $_FILES["upload1"]["tmp_name"]),'application/pdf')===false)
				$error_msg=$error_msg."File 1 not a valid pdf file!<br>";
			else
			{
				$upload1_name=test($_FILES["upload1"]["name"]);
				pdf_beginupload($_FILES["upload1"]["tmp_name"], 'user_data/tmp/'.$_SESSION['user'].'_1.pdf');
			}
		}
		if(!empty($_FILES["upload2"]["tmp_name"]))
		{
			if ($_FILES["upload2"]["size"] > 5000000)
    			$error_msg=$error_msg."File 2 too large!<br>";
			elseif(strtolower(pathinfo(test($_FILES["upload2"]["name"]),PATHINFO_EXTENSION))!="pdf")
				$error_msg=$error_msg."File 2 not a pdf file?<br>";
			elseif(strpos(finfo_file($finfo, $_FILES["upload2"]["tmp_name"]),'application/pdf')===false)
				$error_msg=$error_msg."File 2 not a valid pdf file!<br>";
			else
			{
				$upload2_name=test($_FILES["upload2"]["name"]);
				pdf_beginupload($_FILES["upload2"]["tmp_name"], 'user_data/tmp/'.$_SESSION['user'].'_2.pdf');
			}
		}
		if(!empty($_FILES["upload3"]["tmp_name"]))
		{
			if ($_FILES["upload3"]["size"] > 5000000)
    			$error_msg=$error_msg."File 3 too large!<br>";
			elseif(strtolower(pathinfo(test($_FILES["upload3"]["name"]),PATHINFO_EXTENSION))!="pdf")
				$error_msg=$error_msg."File 3 not a pdf file?<br>";
			elseif(strpos(finfo_file($finfo, $_FILES["upload3"]["tmp_name"]),'application/pdf')===false)
				$error_msg=$error_msg."File 3 not a valid pdf file!<br>";
			else
			{
				$upload3_name=test($_FILES["upload3"]["name"]);
				pdf_beginupload($_FILES["upload3"]["tmp_name"], 'user_data/tmp/'.$_SESSION['user'].'_3.pdf');
			}
		}
    	finfo_close($finfo);
	}
	
	if(!empty($upload1_name) && (!empty($_POST['blanknames']) /*|| isset($_POST['preview_sansnoms'])*/))
	{
		if((empty($xmin1) || empty($xmax1) || empty($ymin1) || empty($ymax1)))
			$error_msg=$error_msg."Please enter box coordinates for 1!<br>";
		elseif( $xmin1 >= $xmax1 || $ymin1 >= $ymax1 || $xmin1 < 0 || $ymin1 < 0 || $xmax1 > 100 || $ymax1 > 100)
			$error_msg=$error_msg."No valid box coordinates for 1!<br>";
	}
	if(!empty($upload2_name) && (!empty($_POST['blanknames']) /*|| isset($_POST['preview_sansnoms'])*/))
	{
		if(empty($xmin2) || empty($xmax2) || empty($ymin2) || empty($ymax2))
			$error_msg=$error_msg."Please enter box coordinates for 2!<br>";
		elseif( $xmin2 >= $xmax2 || $ymin2 >= $ymax2 || $xmin2 < 0 || $ymin2 < 0 || $xmax2 > 100 || $ymax2 > 100)
			$error_msg=$error_msg."No valid box coordinates for 2!<br>";
	}
	if(!empty($upload3_name) && (!empty($_POST['blanknames']) /*|| isset($_POST['preview_sansnoms'])*/))
	{
		if(empty($xmin3) || empty($xmax3) || empty($ymin3) || empty($ymax3))
			$error_msg=$error_msg."Please enter box coordinates for 3!<br>";
		elseif( $xmin3 >= $xmax3 || $ymin3 >= $ymax3 || $xmin3 < 0 || $ymin3 < 0 || $xmax3 > 100 || $ymax3 > 100)
			$error_msg=$error_msg."No valid box coordinates for 3!<br>";
	}
	
	if(isset($_POST['submit']))
	{
		if(empty($title) || empty($abstract))
			$error_msg=$error_msg."Cannot have empty title or abstract!<br>";
		elseif (strlen($title) > 200)
			$error_msg=$error_msg."Title too long please control yourself!<br>";
		if(strlen($abstract) > 2000)
			$error_msg=$error_msg."Abstract max length 2000 characters!<br>";
		if(!empty($_POST['issearchable']) && empty($keywords))
			$error_msg=$error_msg."Ticked searchable by keywords but no keywords entered!<br>";
		if (!empty($cmpgn_extlink) && !preg_match("/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i",$cmpgn_extlink))
			$error_msg=$error_msg.'Filled out external link field but not a valid URL.<br>';
		if(!empty($cmpgn_intlink) && (!ctype_digit($cmpgn_intlink) || $conn->query("SELECT 1 FROM cmpgn WHERE cmpgn_id='".$conn->real_escape_string($cmpgn_intlink)."'")->num_rows==0))
			$error_msg=$error_msg.'Filled out internal link field but not a valid campaign ID.<br>';
		elseif(!empty($cmpgn_intlink) && $conn->query("SELECT 1 FROM cmpgn c JOIN upload u ON (u.upload_cmpgn=c.cmpgn_id) WHERE u.upload_verdict_summary='1' AND c.cmpgn_id='".$conn->real_escape_string($cmpgn_intlink)."'")->num_rows==0)
				$error_msg=$error_msg.'Internal link field campaign not approved yet.<br>';
		elseif(!empty($cmpgn_intlink) && empty($time_finalized) && $conn->query("SELECT 1 FROM cmpgn WHERE cmpgn_revealrealname='1' AND cmpgn_user='".$_SESSION['user']."' AND cmpgn_id='".$conn->real_escape_string($cmpgn_intlink)."'")->num_rows > 0)
			$error_msg=$error_msg.'Please change authorship revelation on linked-to campaign to forestall blackmailing!<br>';
		if(empty($pubid))
			$error_msg=$error_msg.'Please pick option for authorship revelation at campaign end.<br>';
		if($pubid=='pub_pseudonym' && !empty($_POST['revealtoprof']) && empty($_POST['confirm_pseudonym']))
			$error_msg=$error_msg.'OK to potentially clue in prof on pseudonym? <input type="checkbox" name="confirm_pseudonym"><br>';

		if(!empty($_POST['blanknames']))
		{
			if(empty($upload1_name))
				$error_msg=$error_msg."Please generate preview for anonymized pdfs once before submitting!<br>";
			elseif(!file_exists('user_data/tmp/'.$_SESSION['user'].'_1.pdf') || !file_exists('user_data/tmp/'.$_SESSION['user'].'_1.pdf.rdc')
				|| filemtime('user_data/tmp/'.$_SESSION['user'].'_1.pdf') > filemtime('user_data/tmp/'.$_SESSION['user'].'_1.pdf.rdc'))
					$error_msg=$error_msg."Please examine upload 1 link once before submitting anonymized pdfs!<br>";
			if(!empty($upload2_name) &&
				(!file_exists('user_data/tmp/'.$_SESSION['user'].'_2.pdf') || !file_exists('user_data/tmp/'.$_SESSION['user'].'_2.pdf.rdc')
				|| empty($upload2_name) || filemtime('user_data/tmp/'.$_SESSION['user'].'_2.pdf') > filemtime('user_data/tmp/'.$_SESSION['user'].'_2.pdf.rdc')))
					$error_msg=$error_msg."Please examine upload 2 link once before submitting anonymized pdfs!<br>";
			if(!empty($upload3_name) &&
				(!file_exists('user_data/tmp/'.$_SESSION['user'].'_3.pdf') || !file_exists('user_data/tmp/'.$_SESSION['user'].'_3.pdf.rdc')
				|| filemtime('user_data/tmp/'.$_SESSION['user'].'_3.pdf') > filemtime('user_data/tmp/'.$_SESSION['user'].'_3.pdf.rdc')))
					$error_msg=$error_msg."Please examine upload 3 link once before submitting anonymized pdfs!<br>";
		}
		
		//Create campaign and first upload in user database
		if(empty($error_msg))
		{			
			//First, check whether running campaign or 4 months since last campaign creation
			$sql = "SELECT 1 FROM cmpgn WHERE (cmpgn_user LIKE 
					'".$conn->real_escape_string($_SESSION['user'])."') AND (cmpgn_type_isarchivized = '0') AND (cmpgn_time_finalized = NULL)";
			$result = $conn->query($sql);
			if($result->num_rows > 0) $error_msg=$error_msg."Please finish running campaign first before launching new one!<br>";
			
			$sql = "SELECT cmpgn_time_launched FROM cmpgn WHERE (cmpgn_user LIKE 
					'".$conn->real_escape_string($_SESSION['user'])."') AND (cmpgn_type_isarchivized = '0')
					ORDER BY cmpgn_time_launched DESC";
			$result = $conn->query($sql);

			if(empty($_POST['showprofabstr']) && empty($_POST['showproftitle']) && empty($_POST['showprofthmbnails']))
				$error_msg=$error_msg."Come on show the profs <i>something</i>!<br>";
			
			if ($result->num_rows > 0)
			{
    			$row = $result->fetch_assoc();
				if(strtotime($row['cmpgn_time_launched']) > strtotime("-4 months"))
					$error_msg=$error_msg."Seems your last campaign launch was less than 4 months ago please wait a little!<br>";

				//Then, check whether enough points to launch new campaign
				//Notice that not performed if this is first campaign launch
				$sql = "SELECT student.student_pts_cmpgn-student.student_pts_cmpgn_consmd AS cmpgn_pts_tot,
			   			student.student_pts_cmpgn-student.student_pts_cmpgn_consmd+student.student_pts_feat
			   			+user.user_pts_misc-user.user_pts_fail AS pts_total
						FROM user JOIN student ON user.user_id=student.student_user_id
						WHERE (student_user_id LIKE '".$conn->real_escape_string($_SESSION['user'])."')";
				$result = $conn->query($sql);
				$row = $result->fetch_assoc();
				
				if($row['cmpgn_pts_tot'] < 3 || $row['pts_total'] < 0)
					$error_msg=$error_msg."Not enough points to launch new campaign!<br>";
				
				$deduct_points=TRUE;
			}
			else
			{
				if($conn->query("SELECT 1 FROM student s JOIN user u ON (u.user_id=s.student_user_id) WHERE u.user_id='".$_SESSION['user']."'
					AND student_backuponly='1' AND NOW() < student_email_auth + INTERVAL 6 MONTH")->num_rows > 0)
					$error_msg=$error_msg."There is a 6 months moratorium for 3rd year students before they can launch a campaign.<br>";
			}


			$sql = "SELECT p.pubreg_resbox1, p.pubreg_resbox2, p.pubreg_resbox3 FROM pubreg p
				JOIN student s ON (p.pubreg_student=s.student_id) WHERE s.student_user_id='".$_SESSION['user']."' AND p.pubreg_cmpgn IS NULL ORDER BY p.pubreg_id DESC";
			$result = $conn->query($sql);
			if($result->num_rows==0)
				$error_msg=$error_msg."Not yet edited 'favourite researcher' section in profile, required by matching engine!";
			else 
			{
				$row=$result->fetch_assoc();
				$row2=$conn->query("SELECT resbox_impl_researcher FROM resbox_impl WHERE resbox_impl_id='".$row['pubreg_resbox1']."' ORDER BY resbox_impl_rel_nb DESC")->fetch_assoc();
				$row3=$conn->query("SELECT resbox_impl_researcher FROM resbox_impl WHERE resbox_impl_id='".$row['pubreg_resbox2']."' ORDER BY resbox_impl_rel_nb DESC")->fetch_assoc();
				$row4=$conn->query("SELECT resbox_impl_researcher FROM resbox_impl WHERE resbox_impl_id='".$row['pubreg_resbox3']."' ORDER BY resbox_impl_rel_nb DESC")->fetch_assoc();
				
				$error_msg=check_collocs($error_msg,$row2['resbox_impl_researcher'],$row3['resbox_impl_researcher'],$row4['resbox_impl_researcher']);
			}
			
			//Perform insert operation
			if(empty($error_msg))
			{
				$title=$conn->real_escape_string($title);
				if(!empty($cmpgn_extlink))
					$cmpgn_extlink="'".$conn->real_escape_string($cmpgn_extlink)."'";
				else $cmpgn_extlink="NULL";
				if(!empty($cmpgn_intlink))
					$cmpgn_intlink="'".$conn->real_escape_string($cmpgn_intlink)."'";
				else $cmpgn_intlink="NULL";
				if(!empty($_POST['blanknames'])) $blanknames=true; else $blanknames=false;
				if(!empty($_POST['issearchable'])) $issearchable=true; else $issearchable=false;
				if(!empty($_POST['displayinfeed'])) $displayinfeed=true; else $displayinfeed=false;
				if(!empty($_POST['showprofabstr'])) $showprofabstr=true; else $showprofabstr=false;
				if(!empty($_POST['showproftitle'])) $showproftitle=true; else $showproftitle=false;
				if(!empty($_POST['showprofthmbnails'])) $showprofthmbnails=true; else $showprofthmbnails=false;
				if(!empty($_POST['revealtoprof'])) $revealtoprof=true; else $revealtoprof=false;
				if($pubid=="pub_pseudonym") $revealrealname=2;
				elseif($pubid=="pub_realname") $revealrealname=1;
				elseif($pubid=="pub_anonym") $revealrealname=0;
				
//				if(!empty($cmpgn_externallink))
//				{
/*					$sql= "INSERT INTO link (link_to) VALUES('$cmpgn_externallink');";
					$conn->query($sql);
					$sql="SELECT LAST_INSERT_ID();";
					$link_id=$conn->query($sql)->fetch_assoc();
					$link_id=$link_id['LAST_INSERT_ID()'];
					$link_id="'".$link_id."'";*/
//					$cmpgn_externallink="'".$cmpgn_externallink."'";
//				}
//				else $cmpgn_externallink/*$link_id*/="NULL";

				$sql="INSERT INTO moderators_group (moderators_group_type,moderators_group_hashcode) VALUES ('CMPGN','".rand(0,9999)."')";
				$conn->query($sql);

				$sql="SELECT LAST_INSERT_ID();";
				$moderators_group=$conn->query($sql)->fetch_assoc();
				$moderators_group=$moderators_group['LAST_INSERT_ID()'];

				$sql="INSERT INTO moderators (moderators_group) VALUES ('$moderators_group')";
				$result=$conn->query($sql);

				$sql="SELECT LAST_INSERT_ID();";
				$moderators_id=$conn->query($sql)->fetch_assoc();
				$moderators_id=$moderators_id['LAST_INSERT_ID()'];
				
				$sql="INSERT INTO ratebox VALUES ()";
				$conn->query($sql);
				$sql="SELECT LAST_INSERT_ID();";
				$newratebox=$conn->query($sql)->fetch_assoc();
				$newratebox=$newratebox['LAST_INSERT_ID()'];
				
				$sql = "INSERT INTO cmpgn (cmpgn_title, cmpgn_user, cmpgn_type_isarchivized, cmpgn_externallink, cmpgn_internallink,
						cmpgn_blanknames, cmpgn_displayinfeed, cmpgn_revealtoprof, cmpgn_showprofabstr, cmpgn_showproftitle, cmpgn_showprofthmbnails, cmpgn_issearchable, cmpgn_revealrealname, cmpgn_moderators_group, cmpgn_ratebox)
						VALUES ('$title','".$_SESSION['user']."','0',$cmpgn_extlink,$cmpgn_intlink,'$blanknames',
								'$displayinfeed','$revealtoprof','$showprofabstr','$showproftitle','$showprofthmbnails','$issearchable','$revealrealname','$moderators_group','$newratebox')";
				$result = $conn->query($sql);
				if(!$result) echo "Insert into cmpgn failed!";
				
				$sql="SELECT LAST_INSERT_ID();";
				$cmpgn_id=$conn->query($sql)->fetch_assoc();
				$cmpgn_id=$cmpgn_id['LAST_INSERT_ID()'];

				if(!empty($upload1_name) && !empty($_POST['blanknames']))
				{
					$sql = "INSERT INTO coord (coord_xmin, coord_xmax, coord_ymin, coord_ymax)
							VALUES ('$xmin1', '$xmax1', '$ymin1', '$ymax1')";
					$result = $conn->query($sql);

					$sql="SELECT LAST_INSERT_ID();";
					$upload1_coord_id=$conn->query($sql)->fetch_assoc();
					$upload1_coord_id=$upload1_coord_id['LAST_INSERT_ID()'];
					$upload1_coord_id="'".$upload1_coord_id."'";
				}
				else $upload1_coord_id="NULL";

				if(!empty($upload2_name) && !empty($_POST['blanknames']))
				{
					$sql = "INSERT INTO coord (coord_xmin, coord_xmax, coord_ymin, coord_ymax)
							VALUES ('$xmin2', '$xmax2', '$ymin2', '$ymax2')";
					$result = $conn->query($sql);

					$sql="SELECT LAST_INSERT_ID();";
					$upload2_coord_id=$conn->query($sql)->fetch_assoc();
					$upload2_coord_id=$upload2_coord_id['LAST_INSERT_ID()'];
					$upload2_coord_id="'".$upload2_coord_id."'";
				}
				else $upload2_coord_id="NULL";

				if(!empty($upload3_name) && !empty($_POST['blanknames']))
				{
					$sql = "INSERT INTO coord (coord_xmin, coord_xmax, coord_ymin, coord_ymax)
							VALUES ('$xmin3', '$xmax3', '$ymin3', '$ymax3')";
					$result = $conn->query($sql);

					$sql="SELECT LAST_INSERT_ID();";
					$upload3_coord_id=$conn->query($sql)->fetch_assoc();
					$upload3_coord_id=$upload3_coord_id['LAST_INSERT_ID()'];
					$upload3_coord_id="'".$upload3_coord_id."'";
				}
				else $upload3_coord_id="NULL";


				//CREATE TASK, ASSEMBLE VERDICT (NO NOTIFYING MODERATORS YET)
				$sql="INSERT INTO task (task_time_created) VALUES (NOW())";
				$conn->query($sql);
				
				$sql="SELECT LAST_INSERT_ID();";
				$task_id=$conn->query($sql)->fetch_assoc();
				$task_id=$task_id['LAST_INSERT_ID()'];
				
				$sql="INSERT INTO verdict (verdict_moderators , verdict_task, verdict_type) VALUES ('$moderators_id', '$task_id', 'UPLOAD')";
				$conn->query($sql);

				$sql="SELECT LAST_INSERT_ID();";
				$verdict_id=$conn->query($sql)->fetch_assoc();
				$verdict_id=$verdict_id['LAST_INSERT_ID()'];

				//WATCHLIST!
				$row=$conn->query("SELECT student_id FROM student WHERE student_user_id='".$_SESSION['user']."'")->fetch_assoc();
				if(!empty($deduct_points)) $conn->query("UPDATE student SET student_pts_cmpgn_consmd=student_pts_cmpgn_consmd+3 WHERE student_id='".$row['student_id']."'");
				$conn->query("UPDATE pubreg SET pubreg_cmpgn='$cmpgn_id' WHERE pubreg_cmpgn IS NULL AND pubreg_student='".$row['student_id']."'");
				addto_watchlists($conn,$moderators_id,'UPLOAD',$task_id, 1, 1/*,'','',''*/);

				//SAME FOR 2,3
				$abstract=$conn->real_escape_string($abstract);
				$keywords=$conn->real_escape_string($keywords);
				$upload1_name=$conn->real_escape_string($upload1_name);
				if(!empty($upload2_name))
					$upload2_name="'".$conn->real_escape_string($upload2_name)."'";
				else $upload2_name="NULL";
				if(!empty($upload3_name))
					$upload3_name="'".$conn->real_escape_string($upload3_name)."'";
				else $upload3_name="NULL";
				$sql = "INSERT INTO upload (upload_cmpgn, upload_abstract_text, upload_keywords, 
						upload_file1, upload_file2, upload_file3, upload_verdict,
						upload_file1_coord, upload_file2_coord, upload_file3_coord)
						VALUES ('$cmpgn_id', '$abstract','$keywords',
						'$upload1_name',$upload2_name,$upload3_name,'$verdict_id',
						$upload1_coord_id,$upload2_coord_id,$upload3_coord_id)";
				$result = $conn->query($sql);
				if(!$result) echo "Insert into upload failed!";
				
				$sql="SELECT LAST_INSERT_ID();";
				$upload_id=$conn->query($sql)->fetch_assoc();
				$upload_id=$upload_id['LAST_INSERT_ID()'];
				
				//move files, update cmgpn_time_launched and upload_timestamp
				if(isset($_POST['submit']))
				{
					rename('user_data/tmp/'.$_SESSION['user'].'_1.pdf','user_data/uploads/'.$cmpgn_id.'_'.$upload_id.'_1.pdf');
					rename('user_data/tmp/'.$_SESSION['user'].'_1.pdf.rdc','user_data/uploads_redacted/'.$cmpgn_id.'_'.$upload_id.'_1.pdf.rdc');
					if($upload2_name!="NULL")
					{
						rename('user_data/tmp/'.$_SESSION['user'].'_2.pdf','user_data/uploads/'.$cmpgn_id.'_'.$upload_id.'_2.pdf');
						rename('user_data/tmp/'.$_SESSION['user'].'_2.pdf.rdc','user_data/uploads_redacted/'.$cmpgn_id.'_'.$upload_id.'_2.pdf.rdc');
					}
					if($upload3_name!="NULL")
					{
						rename('user_data/tmp/'.$_SESSION['user'].'_3.pdf','user_data/uploads/'.$cmpgn_id.'_'.$upload_id.'_3.pdf');
						rename('user_data/tmp/'.$_SESSION['user'].'_3.pdf.rdc','user_data/uploads_redacted/'.$cmpgn_id.'_'.$upload_id.'_3.pdf.rdc');
					}

					unlink('user_data/tmp/'.$_SESSION['user'].'_1.pdf.misc');
					unlink('user_data/tmp/'.$_SESSION['user'].'_2.pdf.misc');
					unlink('user_data/tmp/'.$_SESSION['user'].'_3.pdf.misc');
					unlink('user_data/tmp/'.$_SESSION['user'].'_1.png');
					unlink('user_data/tmp/'.$_SESSION['user'].'_2.png');
					unlink('user_data/tmp/'.$_SESSION['user'].'_3.png');
					
					$sql = "UPDATE cmpgn SET cmpgn_time_launched=NOW() WHERE cmpgn_id='$cmpgn_id'";
					$result = $conn->query($sql);
					if(!$result) echo "Update of campaign failed!";

					$sql = "UPDATE upload SET upload_timestamp=NOW() WHERE upload_id='$upload_id'";
					$result = $conn->query($sql);
					if(!$result) echo "Update of upload failed!";

					$sql = "UPDATE student SET student_cmpgn_own_latest='".$cmpgn_id."' WHERE student_user_id LIKE '".$_SESSION['user']."'";
					$result = $conn->query($sql);
					if(!$result) echo "Update of student failed!";
					
					//CREATE MODERATORS
					tsa_callmultiple($conn,$cmpgn_id,$upload_id);

					header("Location: index.php?cmpgn=".$cmpgn_id);
				}
			}
//			$conn->close();
		}
	}
}
else
{
	$_POST['issearchable']=true;
	$_POST['blanknames']=true;
	$_POST['displayinfeed']=true;
//	$_POST['excl_prop']=true;
	$_POST['showprofabstr']=true;
	$_POST['showproftitle']=true;
	$_POST['showprofthmbnails']=true;
	$_POST['revealtoprof']=true;	
}

function pdf_beginupload($source, $target)
{
	move_uploaded_file($source, $target);

	putenv('PATH=/usr/bin');
 	exec("gs921 -o ".$target.".misc"." -dNoOutputFonts -sDEVICE=pdfwrite ".$target);

	putenv('PATH=/usr/bin');
	exec("convert ".$target."[0] -resize 180x180! ".substr($target,0,strlen($target)-4).".png");
}

/*function pdf_preview($target, $x_min, $x_max, $y_min, $y_max)
{
	require_once('includes/tcpdf/config/tcpdf_config.php');
	require_once('includes/tcpdf/tcpdf.php');
	require_once('includes/tcpdf/tcpdi.php');
	
	$pdf = new TCPDI(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
	$pdf->setPrintHeader(false);

	$pdfdata = file_get_contents($source); 
	$pagecount = $pdf->setSourceData($pdfdata);

	for ($i = 1; $i <= $pagecount; $i++)
	{ 
    	$tplidx = $pdf->importPage($i);
    	$pdf->AddPage();

		if($i==1)
			$pdf->Rect(100, 10, 40, 20, 'F', null,array(255, 255, 255));

    	$pdf->useTemplate($tplidx, 0, 0, 0, 0, true);

		if($i==1)
			$pdf->Rect(100, 10, 40, 20, 'F', null,array(255, 255, 0));
		
	}

	$pdf->Output($_SERVER['DOCUMENT_ROOT'].'myphdidea/'.$target,'F');
}

function pdf_replace($source,$target,$search)
{
	require_once('includes/tcpdf/config/tcpdf_config.php');
	require_once('includes/tcpdf/tcpdf.php');
	require_once('includes/tcpdf/tcpdi.php');
	
	$pdf = new TCPDI(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
	$pdf->setPrintHeader(false);

	$pdfdata = file_get_contents($source); 
	$pagecount = $pdf->setSourceData($pdfdata);
	
	for ($i = 1; $i <= $pagecount; $i++)
	{ 
    	$tplidx = $pdf->importPage($i);
    	$pdf->AddPage();

		if($i==1)
			$pdf->Rect(100, 10, 40, 20, 'F', null,array(255, 255, 255));

    	$pdf->useTemplate($tplidx, 0, 0, 0, 0, true);

		if($i==1)
			$pdf->Rect(100, 10, 40, 20, 'F', null,array(255, 255, 0));
		
	}

	$pdf->Output($_SERVER['DOCUMENT_ROOT'].'myphdidea/'.$target,'F');
}

function remove_characters($filename,$x_min,$x_max,$y_min,$y_max)
{
	$string=file_get_contents($filename);
	preg_match_all('/(?<=\n)[0-9 .]*(?= m\n)/', $string, $matches);

	$matches=reset($matches);
	foreach ($matches as $key => $match)
	{
		list($part1,$part2)=explode(" ",$match);
    	if($x_min < $part1 && $part1 < $x_max && $y_min < $part2 && $part2 < $y_max)
    	{
			$begin_pos=strpos($string,$match." m\n");
			$end_pos=strpos($string,"\nf\n",$begin_pos);
			if(!empty($begin_pos) && !empty($end_pos))
			{
				$text_to_delete=substr($string,$begin_pos,$end_pos+3-$begin_pos);
				$string=str_replace($text_to_delete,'',$string);
			}
    	}
	}
	file_put_contents($filename,$string);
}*/
?>
<form method="post" action="" enctype="multipart/form-data">
      <div id="centerpage">
        <h2>Create new campaign</h2>
        <?php if(isset($error_msg)) echo '<div style="color: red; text-align: center">'.$error_msg.'</div>';?>
        <p><i>Note: Please make sure the "favourite researchers" section in Profile is up-to-date.</i></p>
        <p>Great things have small beginnings! Here, you can start your campaign,
        which requires you to upload at least 1 document set, to be evaluated
        subsequently by student reviewers and then, professors. First, enter a preliminary title below.</p>
        <div class="indentation">
        Title: <input name="title" style="width: 350px" value="<?php if(!empty($title)) echo $title; ?>" class="indentation"></div>
        Note: This is for creating a <i>standard</i> campaign, which can be sent to professors.
        Alternatively, <a href="index.php?workbench=newarchvcmpgn">click here</a> to create an <i>archivized</i> campaign, which cannot be sent out nor updated. 
        This type is intended for ideas you have ceased
        to actively work on.<br><br>
        Also, note that the minimum wait time between campaign creations is <b>4 months</b>. Each
        new campaign consumes <b>3x</b> 'Ideas' points (first campaign free!).
        <h3>Initial upload</h3>
        This is the heart of your campaign, a document set &lt; 5 MB typically
          comprising a PowerPoint, plus other pdf files. You can
          upload updated files as your ideas progress:<div class="indentation">
        <input name="upload1" id="upload1" type="file">
        <?php /*if(isset($_POST['preview_sansnoms']))
			{*/
				if(file_exists('user_data/tmp/'.$_SESSION['user'].'_1.pdf') && !empty($upload1_name))
        			echo '<button name="rmv1">Remove</button><br><a style="margin-left: 10px" target="_blank" href="prvw_pdf.php?id=1&blanknames='.$blanknames.'&xmin='.$xmin1.'&xmax='.$xmax1.'&ymin='.$ymin1.'&ymax='.$ymax1.'">'.$upload1_name.'</a>';
//			}
        ?>
        <input name="upload1_name" type="hidden" value="<?php if(!empty($upload1_name)) echo $upload1_name; ?>"><br>
        <input name="upload2" id="upload2" type="file">
        <?php /*if(isset($_POST['preview_sansnoms']))
			{*/
				if(file_exists('user_data/tmp/'.$_SESSION['user'].'_2.pdf') && !empty($upload2_name))
        			echo '<button name="rmv2">Remove</button><br><a style="margin-left: 10px" target="_blank" href="prvw_pdf.php?id=2&blanknames='.$blanknames.'&xmin='.$xmin2.'&xmax='.$xmax2.'&ymin='.$ymin2.'&ymax='.$ymax2.'">'.$upload2_name.'</a>';
//			}
        ?>
        <input name="upload2_name" type="hidden" value="<?php if(!empty($upload2_name)) echo $upload2_name; ?>"><br>
        <input name="upload3" id="upload3" type="file">
        <?php /*if(isset($_POST['preview_sansnoms']))
			{*/
				if(file_exists('user_data/tmp/'.$_SESSION['user'].'_3.pdf') && !empty($upload3_name))
        			echo '<button name="rmv3">Remove</button><br><a style="margin-left: 10px" class="indentation" target="_blank" href="prvw_pdf.php?id=3&blanknames='.$blanknames.'&xmin='.$xmin3.'&xmax='.$xmax3.'&ymin='.$ymin3.'&ymax='.$ymax3.'">'.$upload3_name.'</a>';
//			}
        ?>
        <input name="upload3_name" type="hidden" value="<?php if(!empty($upload3_name)) echo $upload3_name; ?>"><br></div>
        <p style="text-align: center;"><button name="preview_sansnoms">Display thumbnails</button></p>
        By default, we recommend <a href="index.php?page=faq#whyanonymous">anonymized</a> pdfs, which
        allows you to draw a box manually on the front page for
        effacing your name (note that you can restore the original at any time after submission).
        Please click 'display' above and draw box:
        <?php if(!empty($upload1_name)) echo '<img src="user_data/tmp/'.$_SESSION['user'].'_1.png" id="upload1_thmb" alt="Thumbnail1">';?>
        <?php if(!empty($upload2_name)) echo '<img src="user_data/tmp/'.$_SESSION['user'].'_2.png" id="upload2_thmb" alt="Thumbnail2">';?>
		<?php if(!empty($upload3_name)) echo '<img src="user_data/tmp/'.$_SESSION['user'].'_3.png" id="upload3_thmb" alt="Thumbnail3">';?>
<!--    <div class="indentation">
        <?php if(isset($_POST['preview_sansnoms']))
			{
				if(file_exists('user_data/'.$_SESSION['user'].'_1.pdf'))
        			echo '<a href="'.$upload1_name.'">'.$upload1_name.'</a><br>';
			}
        ?>
     	</div>-->
     	<div class="indentation">
     		File 1: x<sub>min</sub> <input type="number" name="xmin1" value="<?php echo $xmin1; ?>">
     				 x<sub>max</sub> <input type="number" name="xmax1" value="<?php echo $xmax1; ?>">
     				 y<sub>min</sub> <input type="number" name="ymin1" value="<?php echo $ymin1; ?>">
     				 y<sub>max</sub> <input type="number" name="ymax1" value="<?php echo $ymax1; ?>"> %<br>     								
     		File 2: x<sub>min</sub> <input type="number" name="xmin2" value="<?php echo $xmin2; ?>">
     				 x<sub>max</sub> <input type="number" name="xmax2" value="<?php echo $xmax2; ?>">
     				 y<sub>min</sub> <input type="number" name="ymin2" value="<?php echo $ymin2; ?>">
     				 y<sub>max</sub> <input type="number" name="ymax2" value="<?php echo $ymax2; ?>"> %<br>
     		File 3: x<sub>min</sub> <input type="number" name="xmin3" value="<?php echo $xmin3; ?>">
     				 x<sub>max</sub> <input type="number" name="xmax3" value="<?php echo $xmax3; ?>">
     				 y<sub>min</sub> <input type="number" name="ymin3" value="<?php echo $ymin3; ?>">
     				 y<sub>max</sub> <input type="number" name="ymax3" value="<?php echo $ymax3; ?>"> %<br>
     	</div>
     	<p class="indentation" style="font-size: small">
     		Note: At a later stage, we intend to replace the graphical method
     		with a text parser. Please ensure spelling of your name in the
     		documents is correct to facilitate transition.
     	</p>
     	     	<p style="text-align: center;"><button name="preview_sansnoms">Update preview links</button></p>
        The first thing visitors to your campaign see is usually your abstract:
        <div style="text-align: center"><textarea style="width: 400px; height: 200px;"
class="indentation" name="abstract"><?php if(!empty($abstract)) echo $abstract; ?></textarea></div>
        Keywords can be entered to facilitate searching for your campaign: <br>
        <div style="text-align: center"><input style="width: 400px" class="indentation"
            name="keywords" value="<?php if(!empty($keywords)) echo $keywords; ?>" type="text"></div>
        <h3>Settings</h3>
        All of the below are recommended but can be turned off individually:<br><br>
        <input name="blanknames" type="checkbox" <?php if(!empty($_POST['blanknames'])) echo 'checked'; ?>> Anonymize pdfs i.e.
        delete text from area on first page containing name.<br>

        <input name="showproftitle" type="checkbox" <?php if(!empty($_POST['showproftitle'])) echo 'checked'; ?>> Show professors the title of your project idea.<br>
        <input name="showprofabstr" type="checkbox" <?php if(!empty($_POST['showprofabstr'])) echo 'checked'; ?>> Show professors your abstract.<br>

        <input name="showprofthmbnails" type="checkbox" <?php if(!empty($_POST['showprofthmbnails'])) echo 'checked'; ?>> Show professors thumbnails (pages 2,3,4 of upload 1).<br>
        <input name="revealtoprof" type="checkbox" <?php if(!empty($_POST['revealtoprof'])) echo 'checked'; ?>> Show professors your real name.<br>
        <input name="displayinfeed" type="checkbox" <?php if(!empty($_POST['displayinfeed'])) echo 'checked'; ?>> Enter into "Ideas" titlepage
        newsfeed (executed upon first 'send' to prof).<br>
        <input name="issearchable" type="checkbox" <?php if(!empty($_POST['issearchable'])) echo 'checked'; ?>> Tick if you want the above
        keywords to be used to facilitate search indexing.<br>
<!--        <input name="excl_prop" type="checkbox" <?php if(!empty($_POST['excl_prop'])) echo 'checked'; ?>> Turn on priority reception
        of new task proposals in user profile.<br>-->
        <p class="indentation" style="font-size: small">
     		Anonymized PDFs (meant to thwart 'blackmailing') cannot be reenabled if disabled now.
     	</p>
        During their runtime, all campaigns are anonymous, but upon termination
        they can be published either under your pseudonym, or under your real name
        (if in doubt, anonymous status can still be changed to the other 2 later
        on).
        <p class="indentation"> 
          <input value="pub_realname" name="pubid" type="radio" <?php if(!empty($pubid) && $pubid=="pub_realname") echo 'checked'; ?>>Finalize under
          real name<br>
          <input value="pub_pseudonym" name="pubid" type="radio" <?php if(!empty($pubid) && $pubid=="pub_pseudonym") echo 'checked'; ?>>Finalize
          under pseudonym<br>
          <input value="pub_anonym" name="pubid" type="radio" <?php if(!empty($pubid) && $pubid=="pub_anonym") echo 'checked'; ?>>Leave anonymous<br>
        </p>
        Finally, you can link to an external site, as well as to another
        campaign: <br>
        <div class="indentation">
        <label>External link:</label><input type="text" name="cmpgn_extlink" value="<?php if(!empty($cmpgn_extlink)) echo $cmpgn_extlink; ?>"><br>
        <label>Internal (campaign ID):</label><input type="text" name="cmpgn_intlink" value="<?php if(!empty($cmpgn_intlink)) echo $cmpgn_intlink; ?>" placeholder="See 'cmpgn' field in URL"></div>
        <p style="text-align: right;" class="indentation"><button name="submit">Submit</button></p>
      </div>
</form>
<script src="libs/jcrop/js/jquery.min.js"></script>
<script src="libs/jcrop/js/jquery.Jcrop.min.js"></script>
<link rel="stylesheet" href="libs/jcrop/css/jquery.Jcrop.css" type="text/css" />

		<script type="text/javascript">

		jQuery(function($){

  		$('#upload1_thmb').Jcrop({
    		onChange:   showCoords,
    		onSelect:   showCoords,
    		bgColor: '',
    		addClass: 'jcrop-left'
  		});
  		
  		$('#upload2_thmb').Jcrop({
    		onChange:   showCoords2,
    		onSelect:   showCoords2,
    		bgColor: '',
    		addClass: 'jcrop-left'
  		});
  		
  		$('#upload3_thmb').Jcrop({
    		onChange:   showCoords3,
    		onSelect:   showCoords3,
    		bgColor: '',
    		addClass: 'jcrop-left'
  		});
		});
		
/*		jQuery(function($){

  		$('#upload2_thmb').Jcrop({
    		onChange:   showCoords2,
    		onSelect:   showCoords2
  		});
  		
		});

		jQuery(function($){

  		$('#upload3_thmb').Jcrop({
    		onChange:   showCoords3,
    		onSelect:   showCoords3
  		});
  		
		});*/


		function showCoords(c)
		{
  			document.getElementsByName("xmin1")[0].value=Math.floor(c.x/1.8);
  			document.getElementsByName("xmax1")[0].value=Math.floor(c.x2/1.8);
  			document.getElementsByName("ymin1")[0].value=100-Math.floor(c.y2/1.8);
  			document.getElementsByName("ymax1")[0].value=100-Math.floor(c.y/1.8);
		};
		
		function showCoords2(c)
		{
  			document.getElementsByName("xmin2")[0].value=Math.floor(c.x/1.8);
  			document.getElementsByName("xmax2")[0].value=Math.floor(c.x2/1.8);
  			document.getElementsByName("ymin2")[0].value=100-Math.floor(c.y2/1.8);
  			document.getElementsByName("ymax2")[0].value=100-Math.floor(c.y/1.8);
		};

		function showCoords3(c)
		{
  			document.getElementsByName("xmin3")[0].value=Math.floor(c.x/1.8);
  			document.getElementsByName("xmax3")[0].value=Math.floor(c.x2/1.8);
  			document.getElementsByName("ymin3")[0].value=100-Math.floor(c.y2/1.8);
  			document.getElementsByName("ymax3")[0].value=100-Math.floor(c.y/1.8);
		};

		</script>

