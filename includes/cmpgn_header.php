<?php
$cmpgn_id=$conn->real_escape_string(test($_GET['cmpgn']));
$isarchivized=$revealtoprof=FALSE;

if(isset($_SESSION['prof']))
{
	$sql="SELECT 1 FROM review r JOIN upload u ON (r.review_upload=u.upload_id) WHERE u.upload_cmpgn='$cmpgn_id' AND review_prof='".$_SESSION['prof']."'";
	if($conn->query($sql)->num_rows > 0)
		$visitor_isreviewprof=TRUE;
}

$sql="SELECT cmpgn_user, cmpgn_title, cmpgn_time_launched, cmpgn_time_firstsend, cmpgn_time_finalized, cmpgn_type_isarchivized, cmpgn_ratebox, cmpgn_visibility_blocked,
	  cmpgn_externallink, cmpgn_internallink, cmpgn_revealrealname, cmpgn_revealtoprof, cmpgn_rvw_favourite FROM cmpgn WHERE cmpgn_id=".$cmpgn_id;
$result = $conn->query($sql);
if($result->num_rows > 0)
{
	$row=$result->fetch_assoc();
	$owner=$title=$time_launched=$time_firstsend=$cmpgn_type=$extlink=$intlink=$revealrealname="";
	$owner=$row['cmpgn_user'];
	$title=$row['cmpgn_title'];
	$time_launched=$row['cmpgn_time_launched'];
	$time_firstsend=$row['cmpgn_time_firstsend'];
	$time_finalized=$row['cmpgn_time_finalized'];
	$isarchivized=$row['cmpgn_type_isarchivized'];
	$extlink=$row['cmpgn_externallink'];
	$intlink=$row['cmpgn_internallink'];
	$revealrealname=$row['cmpgn_revealrealname'];
	$revealtoprof=$row['cmpgn_revealtoprof'];
	$rvw_favourite=$row['cmpgn_rvw_favourite'];
	$ratebox=$row['cmpgn_ratebox'];
	if(!empty($row['cmpgn_visibility_blocked']) && (empty($_SESSION['user']) || $_SESSION['user']!='1'))
		header("Location: index.php?confirm=blocked");

	if(isset($_POST['block']) && isset($_SESSION['user']) && $_SESSION['user']=='1')
	{
		if($_POST['visibility']=='block') $conn->query("UPDATE cmpgn SET cmpgn_visibility_blocked='1' WHERE cmpgn_id='$cmpgn_id'");
		elseif($_POST['visibility']=='unblock') $conn->query("UPDATE cmpgn SET cmpgn_visibility_blocked='0' WHERE cmpgn_id='$cmpgn_id'");
	}
	
	if(isset($_SESSION['user']) && $owner==$_SESSION['user'])
		$visitor_isowner=TRUE;
	else $visitor_isowner=FALSE;

	if(($revealtoprof && (isset($visitor_isreviewprof) && $visitor_isreviewprof)) || $visitor_isowner)
	{
			$sql="SELECT student_familyname, student_givenname, student_selfdescription, student_socialmedia_link FROM student WHERE student_user_id=".$owner;
			$row2=$conn->query($sql)->fetch_assoc();
			$printname_prof=$row2['student_givenname']." ".$row2['student_familyname'];//.", ".$row['student_selfdescription'];
			if(!empty($row2['student_socialmedia_link']) && !empty($time_finalized))
				$printname_prof='<a href="index.php?page=redirect&link='.$row2['student_socialmedia_link'].'">'.$printname_prof.'</a>';
			if(!empty($row2['student_selfdescription'])) $printname_prof=$printname_prof.", ".$row2['student_selfdescription'];
			if(!$visitor_isowner) $printname=$printname_prof;
	}
	if(($isarchivized && $conn->query("SELECT 1 FROM upload WHERE upload_verdict_summary IS NOT NULL AND upload_cmpgn='$cmpgn_id'"))
		|| (!empty($time_finalized) && empty($printname)))
	{
		if($revealrealname==0)
			$printname="<i>Anonymous</i>";
		elseif($revealrealname==2)
		{
			$sql="SELECT user_pseudonym FROM user WHERE user_id='".$owner."'";
			$row2=$conn->query($sql)->fetch_assoc();
			$printname=$row2['user_pseudonym'];
		}
		elseif($revealrealname==1)
		{
			$sql="SELECT student_familyname, student_givenname, student_selfdescription, student_socialmedia_link FROM student WHERE student_user_id=".$owner;
			$row2=$conn->query($sql)->fetch_assoc();
			$printname=$row2['student_givenname']." ".$row2['student_familyname'];//.", ".$row['student_selfdescription'];
			if(!empty($row2['student_socialmedia_link']))
				$printname='<a href="'.$row2['student_socialmedia_link'].'">'.$printname.'</a>';
//			$printname=$printname.", ".$row['student_selfdescription'];
			if(!empty($row2['student_selfdescription'])) $printname=$printname.", ".$row2['student_selfdescription'];
		}
	}
}
else echo "Not a valid campaign index!";
?>