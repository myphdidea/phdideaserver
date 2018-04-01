<?php

if(isset($_SESSION['user']) || isset($_SESSION['prof']))
{
/*	$cmpgn_id=$conn->real_escape_string(test($_GET['cmpgn']));
	$submit_text="";

	$sql="SELECT cmpgn_user, cmpgn_title, cmpgn_time_launched, cmpgn_time_firstsend, cmpgn_time_finalized, cmpgn_type_isarchivized,
	  cmpgn_revealrealname, cmpgn_revealtoprof FROM cmpgn WHERE cmpgn_id='".$cmpgn_id."'";
	$result = $conn->query($sql);
	$row=$result->fetch_assoc();
	$owner=$title=$time_launched=$time_firstsend=$time_finalized=$cmpgn_type=$revealrealname="";
	$owner=$row['cmpgn_user'];
	$title=$row['cmpgn_title'];
	$time_launched=$row['cmpgn_time_launched'];
	$time_firstsend=$row['cmpgn_time_firstsend'];
	$time_finalized=$row['cmpgn_time_finalized'];
	$isarchivized=$row['cmpgn_type_isarchivized'];
	$revealrealname=$row['cmpgn_revealrealname'];*/
	
	$submit_text="";
	echo '<div id="centerpage">';
	include("includes/cmpgn_header.php");
	include("includes/cmpgn_header_display.php");
	echo '<br><br>';

	if(isset($_SESSION['user']) && !isset($_SESSION['prof']) && $owner!=$_SESSION['user'])
		header("Location: index.php");

	list($image_path,$printname)=cmpgn_label($row, $conn, $cmpgn_id, false);
/*	if($printname!="")
		$own_label=$image_path.$printname.'<br>';
	else $own_label=$image_path."<i>To be disclosed later</i>".'<br>';*/

	//PROCESS POST DATA BEFORE OUTPUTTING
	if(isset($_POST['submit_toprof']) || isset($_POST['submit_tocmpgn']))
	{
		if(isset($_POST['submit_toprof']))
			$toprof=$conn->real_escape_string(test($_POST['submit_toprof']));
		else $toprof=$_SESSION['prof'];
		$submit_text=$conn->real_escape_string(test($_POST['reply'.$toprof]));
		$sql="SELECT dialogue_speaker, dialogue_time_sent FROM dialogue WHERE dialogue_cmpgn='".$cmpgn_id."' AND dialogue_prof='".$toprof."' ORDER BY dialogue_time_sent DESC";
		$result=$conn->query($sql);
		if(($result->num_rows > 0 || isset($_SESSION['prof'])) && strlen($submit_text) < 250 && strlen($submit_text) > 0)
		{
			$test=$result->fetch_assoc(); $test1=$test['dialogue_speaker'];
			$test=$result->fetch_assoc(); $test2=$test['dialogue_speaker'];
			$test=$result->fetch_assoc(); $test3=$test['dialogue_speaker'];
			
			if(isset($_SESSION['prof'])) $speaker=1; else $speaker=0;
			
			if($test1!=$test2 || $test2!=$test3 || $test1!=$test3 || $speaker!=$test1 
				|| strtotime($test['dialogue_time_sent'].'+3 days')<strtotime('today'))
			{
				$notif_succes=TRUE;
				if($speaker!=$test1 || strtotime($test['dialogue_time_sent'].'+3 days')<strtotime('today'))
				{
					$link='https://www.myphdidea.org/index.php?workbench=dialogue&cmpgn='.$cmpgn_id;
					$text2="\n\n Click on the link to be taken to the dialogue page (requires login)."/*\n\n
							 The myphdidea team"*/;
					if($speaker==1)
					{
/*						$sql="SELECT s.student_givenname, s.student_institution_email, s.student_sendto_instmail,
								u.user_email FROM student s JOIN user u ON (s.student_user_id=u.user_id) WHERE user_id='".$owner."'";
						$test=$conn->query($sql)->fetch_assoc();*/
						if($result->num_rows == 0)
							$notif_success=send_notification($conn,$owner,1,'Contacted by prof',"You have a new dialogue with a researcher!\n\n",$link,$text2);//$contacted="new dialogue with";
						else $notif_success=send_notification($conn,$owner,3,'Contacted by prof',"You have been contacted by a researcher.\n\n",$link,$text2);//$contacted="been contacted by";
/*						$text1="Dear ".$test['student_givenname'].",\n\n".
							"You have ".$contacted." a researcher!\n\n";
						if($test['student_sendto_instmail'])
							send_mail($test['student_institution_email'], 'Contacted by prof', $link, $text1, $text2);
						else send_mail($test['user_email'], 'Contacted by prof', $link, $text1, $text2);*/
					}
					else
					{
						$notif_success=send_profnotif($conn, $toprof, 2, 'Reply from student', "You have a reply from the student you contacted!", '', '');
/*						$sql="SELECT a.autoedit_email, p.prof_familyname FROM autoedit a
							JOIN prof p ON (a.autoedit_prof=p.prof_id) WHERE prof_id='".$toprof."'";
						$test=$conn->query($sql)->fetch_assoc();
						$text1="Dear Prof.".$test['prof_familyname'].",\n\n".
							"You have a reply from the student you contacted!\n\n";

						send_mail($test['autoedit_email'], 'Reply from student', $link, $text1, $text2);*/
					}
				}
				$sql="INSERT INTO dialogue (dialogue_cmpgn, dialogue_prof, dialogue_speaker, dialogue_text, dialogue_time_sent)
							  VALUES ('$cmpgn_id', '$toprof', '$speaker', '$submit_text', NOW())";
				if($notif_success)
				{
					$conn->query($sql);
					$submit_text="";
				}
				else $error_msg=$error_msg."Could not send mail!<br>";
			}
			else $error_msg="No more than 3 messages in a row please wait 72h!";
		}
		else $error_msg="Max 250 characters!";
	}
	
	//ASSEMBLE HEADER
//	echo cmpgn_head($row,$printname);
    if(!empty($error_msg)) echo '<div style="color: red; text-align: center">'.$error_msg.'</div>';
	
	$sql="SELECT DISTINCT p.prof_id, p.prof_givenname, p.prof_familyname, p.prof_image FROM dialogue d JOIN prof p ON (d.dialogue_prof=p.prof_id) WHERE d.dialogue_cmpgn='".$cmpgn_id."'";
	$result=$conn->query($sql);

	while($prof_row=$result->fetch_assoc())
	{
		if(isset($_SESSION['prof']) && $prof_row['prof_id']!=$_SESSION['prof'])
			continue;
		//ASSEMBLE PROF ID TAG
		$prof_lbl=prof_label($prof_row/*,$conn*/);
		
		//CHOOSE WHETHER TO REVEAL NAME TO PROF
		$sql="SELECT 1 FROM review r JOIN upload u ON (r.review_upload=u.upload_id) WHERE u.upload_cmpgn='$cmpgn_id' AND review_prof='".$prof_row['prof_id']."'";
		if($conn->query($sql)->num_rows > 0 /*&& $printname!=""*/)
		{
			list($image_path_new,$printname_new)=cmpgn_label($row, $conn, $cmpgn_id, true);
			$own_label=$image_path_new.$printname_new.'<br>';
		}
		elseif($printname!="")
			$own_label=$image_path.$printname.'<br>';
		else $own_label=$image_path."<i>To be disclosed later</i>".'<br>';

		if(isset($_SESSION['user']))
		{
			echo '<div id="outer'.$prof_row['prof_id'].'"><input id="textexpand'.$prof_row['prof_id'].'" class="checkbox_arrow" type="checkbox"><label
            	for="textexpand'.$prof_row['prof_id'].'"></label>';
			echo '<h3>Dialogue with '.$prof_row['prof_givenname'].' '.$prof_row['prof_familyname'].'</h3><div class="hideable">';
		}
		
		//retrieve dialogue with that professor
		$sql="SELECT dialogue_speaker, dialogue_text, dialogue_time_sent FROM dialogue WHERE dialogue_cmpgn='".$cmpgn_id."' AND dialogue_prof='".$prof_row['prof_id']."' ORDER BY dialogue_time_sent ASC";	
		$result_prof=$conn->query($sql);
		while($dialogue_row=$result_prof->fetch_assoc())
		{
			if($dialogue_row['dialogue_speaker']==0)
			{
				$dialogue_assembled='<div class="comment" style="min-height: 100px;">';
				$label=$own_label;
				$finalbr="";
			}
			else
			{
				$dialogue_assembled='<div class="review" style="min-height: 100px; margin-left: 100px; float: none">';
				$label=$prof_lbl;
				$finalbr="<br>";
			}

          	$dialogue_assembled=$dialogue_assembled.'<div class="review_inset" style="min-height: 100px">'
          						.$dialogue_row['dialogue_text'].'</div><div class="upload_footer">'
            					.$label.'Submitted on '.$dialogue_row['dialogue_time_sent'].' </div></div><br>'.$finalbr;
			echo $dialogue_assembled;
		}
		if(isset($_SESSION['user']))
			echo '<form action="" method="post"><textarea name="reply'.$prof_row['prof_id'].'" style="width: 400px; height: 100px; margin-left: 15px">'.$submit_text.'</textarea>
			<br><div style="width: 400px; text-align: right"><button name="submit_toprof" value="'.$prof_row['prof_id'].'">Reply</button></div></form></div></div>';
	}
	if(isset($_SESSION['prof']))
			echo '<form action="" method="post"><textarea name="reply'.$_SESSION['prof'].'" style="width: 400px; height: 100px; margin-left: 110px"></textarea>
			<br><div style="width: 400px; margin-left: 100px; text-align: right"><button name="submit_tocmpgn" value="'.$prof_row['prof_id'].'">Contact</button></div></form>';
	echo '</div>';

}
else 
{
	echo 'Please use login screen on left!';
	if(isset($_GET['cmpgn']))
		$_SESSION['after_login'] = "index.php?workbench=dialogue&cmpgn=".test($_GET['cmpgn']);
}
//$conn->close();

function cmpgn_head($row, $printname)
{
	$owner=$title=$time_launched=$time_firstsend=$time_finalized=$cmpgn_type=$revealrealname="";
	$owner=$row['cmpgn_user'];
	$title=$row['cmpgn_title'];
	$time_launched=$row['cmpgn_time_launched'];
	$time_firstsend=$row['cmpgn_time_firstsend'];
	$time_finalized=$row['cmpgn_time_finalized'];
	$isarchivized=$row['cmpgn_type_isarchivized'];
	$revealrealname=$row['cmpgn_revealrealname'];

	if(empty($time_firstsend) && !empty($time_finalized))
						$launched="approval not obtained";
					  elseif(empty($time_firstsend))
        				$launched="not yet approved";
					  else $launched="approved ".$time_firstsend;
	$cmpgn_header='<div id="centerpage"><h2>'.$title.'</h2>';
/*	$cmpgn_header=$cmpgn_header.'Author: ';
    if(!$isarchivized && empty($time_finalized) && !$override_runningcheck)
    	$cmpgn_header=$cmpgn_header.'<i>We do not disclose author names before the campaign is finished</i> ';
    else $cmpgn_header=$cmpgn_header.$printname." ";*/
    $cmpgn_header=$cmpgn_header."(Created ".$time_launched.", ".$launched.", ";
    if($isarchivized) $cmpgn_header=$cmpgn_header."archivized";
    else if(empty($time_finalized)) $cmpgn_header=$cmpgn_header."still running";
    else $cmpgn_header=$cmpgn_header."finalized ".$time_finalized;
	return $cmpgn_header.')<br><br>';
}

/*function prof_label($prof_row, $conn)
{
	$printname=$prof_row['prof_givenname']." ".$prof_row['prof_familyname'];
	
	$sql="SELECT 1 FROM autoedit WHERE autoedit_prof='".$prof_row['prof_id']."' AND autoedit_image='TRUE'";
	if($conn->query($sql)->num_rows > 0)
		$image_path="user_data/researcher_pictures/".$row['prof_id'].".png";
	else $image_path="images/default.png";

	$image_path='<img alt="" src="'.$image_path.'">';
	$image_path='<a href="index.php?prof='.$prof_row['prof_id'].'">'.$image_path.'</a>';
	$image_path='<div class="icon">'.$image_path.'</div>';
	
	return $image_path.$printname.'<br>';
}*/

function cmpgn_label($row, $conn, $cmpgn_id, $isreviewprof)
{
	$owner=$title=/*$time_launched=$time_firstsend=*/$time_finalized=$cmpgn_type=$revealrealname="";
	$owner=$row['cmpgn_user'];
	$title=$row['cmpgn_title'];
//	$time_launched=$row['cmpgn_time_launched'];
//	$time_firstsend=$row['cmpgn_time_firstsend'];
	$time_finalized=$row['cmpgn_time_finalized'];
	$isarchivized=$row['cmpgn_type_isarchivized'];
	$revealrealname=$row['cmpgn_revealrealname'];
	$revealtoprof=$row['cmpgn_revealtoprof'];

/*	$visitor_isreviewprof=FALSE;
	if($revealtoprof)
	{
		$sql="SELECT 1 FROM review r JOIN upload u ON (r.review_upload=u.upload_id) WHERE u.upload_cmpgn='$cmpgn_id' AND review_prof='";
		if($conn->query($sql)->num_rows > 0)
			$visitor_isreviewprof=TRUE;
	}*/
	
	if($revealtoprof && $isreviewprof)
	{
			$sql="SELECT student_familyname, student_givenname, student_selfdescription, student_image FROM student WHERE student_user_id=".$owner;
			$row=$conn->query($sql)->fetch_assoc();
			if(!empty($row['student_selfdescription']))
				$self_descript=', '.$row['student_selfdescription'];
			else $self_descript="";
			$printname=$row['student_givenname']." ".$row['student_familyname'].$self_descript;
			if($row['student_image'])
				$image_path="user_data/profile_pictures/".$owner.".png";
			else $image_path="images/default.png";
	}
	elseif(!$isarchivized && !empty($time_finalized))
	{
		if($revealrealname==0 && !empty($time_finalized))
		{
			$printname="<i>Anonymous</i>";
			$image_path="images/pseudonym.png";
		}
		elseif($revealrealname==1 && !empty($time_finalized))
		{
			$sql="SELECT user_pseudonym FROM user WHERE user_id=".$owner;
			$row=$conn->query($sql)->fetch_assoc();
			$printname=$row['user_pseudonym'];
			$image_path="images/pseudonym.png";
		}
		elseif($revealrealname==2 && !empty($time_finalized))
		{
			$sql="SELECT student_familyname, student_givenname, student_selfdescription, student_image FROM student WHERE student_user_id=".$owner;
			$row=$conn->query($sql)->fetch_assoc();
			if(!empty($row['student_selfdescription']))
				$self_descript=', '.$row['student_selfdescription'];
			else $self_descript="";
			$printname=$row['student_givenname']." ".$row['student_familyname'].$self_descript;
			if($row['student_image'])
				$image_path="user_data/profile_pictures/".$owner.".png";
			else $image_path="images/default.png";
		}
	}
	elseif($isarchivized)
	{
		$sql="SELECT user_pseudonym FROM user WHERE user_id=".$owner;
		$row=$conn->query($sql)->fetch_assoc();
		$printname=$row['user_pseudonym'];
		$image_path="images/pseudonym.png";
	}
	else
	{
		$printname="";
//		$printname='<i>Author of</i> '.substr($cmpgn_title,1,15)." ...";
		$image_path="images/pseudonym.png";
	}
	$image_path='<img alt="" src="'.$image_path.'">';
	$image_path='<a href="index.php?cmpgn='.$cmpgn_id.'">'.$image_path.'</a>';
	$image_path='<div class="icon">'.$image_path.'</div>';
	
	return array($image_path,$printname);
//	return $image_path.$printname.'<br>';
}

?>