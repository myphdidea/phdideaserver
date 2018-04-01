<?php
$feat_id=$conn->real_escape_string(test($_GET['feat']));

$sql="SELECT s.student_user_id, ft.featuretext_title, ft.featuretext_text, ft.featuretext_timestamp, ft.featuretext_verdict_summary,
	f.feature_revealrealname, f.feature_time_created, f.feature_amendments, f.feature_ratebox, f.feature_time_approved, f.feature_visibility_blocked
	FROM featuretext ft JOIN feature f ON (ft.featuretext_feature=f.feature_id)
	JOIN student s ON (f.feature_student=s.student_id)
	WHERE f.feature_id='$feat_id' ORDER BY ft.featuretext_timestamp DESC";
$result=$conn->query($sql);
if($result->num_rows > 0)
{
	$row=$result->fetch_assoc();
	$owner=$row['student_user_id'];
	$title=$row['featuretext_title'];
	$featuretext=$row['featuretext_text'];
	$title=$row['featuretext_title'];
	$time_created=$row['feature_time_created'];
	$time_approved=$row['feature_time_approved'];
	$time_recent=$row['featuretext_timestamp'];
	$revealrealname=$row['feature_revealrealname'];
	$verdict=$row['featuretext_verdict_summary'];
	$amendments=$row['feature_amendments'];
	$ratebox=$row['feature_ratebox'];
	if(!empty($row['feature_visibility_blocked']) && (empty($_SESSION['user']) || $_SESSION['user']!='1'))
		header("Location: index.php?confirm=blocked");
	
	if(isset($_SESSION['user']) && $owner==$_SESSION['user'])
		$visitor_isowner=TRUE;
	else $visitor_isowner=FALSE;
	
	if(isset($_POST['block']) && isset($_SESSION['user']) && $_SESSION['user']=='1')
	{
		if($_POST['visibility']=='block') $conn->query("UPDATE feature SET feature_visibility_blocked='1' WHERE feature_id='$feat_id'");
		elseif($_POST['visibility']=='unblock') $conn->query("UPDATE feature SET feature_visibility_blocked='0' WHERE feature_id='$feat_id'");
	}

	if($visitor_isowner || (!is_null($verdict) && ($verdict=='1' || strtotime($time_created."+2 months") < strtotime("now"))))
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
				$printname='<a href="index.php?page=redirect&link='.$row2['student_socialmedia_link'].'">'.$printname.'</a>';
//			$printname=$printname.", ".$row['student_selfdescription'];
			if(!empty($row2['student_selfdescription'])) $printname=$printname.", ".$row2['student_selfdescription'];
		}
	}

	if($visitor_isowner)
	{
		if($verdict=='1' || strtotime($time_created."+ 2 months") < strtotime("now"))
		{
			$manip_msg="Evaluation is now over but you can still change settings.";
			$buttons='<a href="index.php?workbench=featconfig&feat='.$feat_id.'"><img class="upload_buttons" title="Settings" alt="Settings-button" src="images/settings-gear.png"></a>';
		}
		else
		{
			$manip_msg="This is your feature! Click below to manage settings or resubmit a corrected version.";
			$buttons='<a href="index.php?workbench=featupdate&feat='.$feat_id.'"><img class="upload_buttons" title="Revise" alt="Settings-button" src="images/write-feature.png"></a> <a href="index.php?workbench=featconfig&feat='.$feat_id.'"><img class="upload_buttons" title="Settings" alt="Settings-button" src="images/settings-gear.png"></a>';
		}
	}
}
else echo "Not a valid feature index!";

if(isset($_POST['submit_rating'])
	&& (isset($_SESSION['user']) && isset($_SESSION['isstudent']) && $_SESSION['user']!=$owner
		|| isset($_SESSION['prof']) && empty($visitor_isreviewprof)))
{
	$submit_rating=$conn->real_escape_string(test($_POST['submit_rating']));
	$ratevote=$conn->real_escape_string(test($_POST['rate'.$submit_rating]));
	if($ratevote!="none")
	{
		if(isset($_SESSION['user']))
		{
			$sql="SELECT student_id FROM student WHERE student_user_id='".$_SESSION['user']."'";
			$row=$conn->query($sql)->fetch_assoc();
						
			$sql="INSERT INTO rating (rating_ratebox, rating_student, rating_value, rating_timestamp) VALUES ('".$submit_rating."','".$row['student_id']."','".$ratevote."',NOW())";
			$conn->query($sql);
		}
		elseif(isset($_SESSION['prof']))
		{
			$sql="SELECT 1 FROM autoedit WHERE autoedit_email_auth IS NOT NULL AND autoedit_prof='".$_SESSION['prof']."'";
			if($conn->query($sql)->num_rows > 0)
			{
				$sql="INSERT INTO rating_byprof (rating_ratebox, rating_prof, rating_value, rating_timestamp) VALUES ('".$submit_rating."','".$_SESSION['prof']."','".$ratevote."',NOW())";
				$conn->query($sql);
			}
		}
		//UPDATE REVIEW RATING
		$sql="SELECT AVG(rating_value) AS rating_avg, COUNT(rating_value) AS rating_nb FROM rating WHERE rating_ratebox='$submit_rating'";
		$row1=$conn->query($sql)->fetch_assoc();
		$sql="SELECT AVG(rating_value) AS rating_avg, COUNT(rating_value) AS rating_nb FROM rating_byprof WHERE rating_ratebox='$submit_rating'";
		$row2=$conn->query($sql)->fetch_assoc();
		$rate_avg=($row1['rating_avg']*$row1['rating_nb']+$row2['rating_avg']*$row2['rating_nb'])/($row1['rating_nb']+$row2['rating_nb']);
//					$sql="UPDATE review SET review_popvote='".ceil($rate_avg)."', review_popvote_nb='".($row1['rating_nb']+$row2['rating_nb'])."' WHERE review_ratebox='$submit_rating'";
		$sql="UPDATE ratebox SET ratebox_popvote='".round($rate_avg)."', ratebox_popvote_nb='".($row1['rating_nb']+$row2['rating_nb'])."' WHERE ratebox_id='$submit_rating'";
		$conn->query($sql);
	}
}

	
?>
<div style="float: right; margin-top: 5px; margin-right: 12px">
						<?php if(isset($_SESSION['user']) && $_SESSION['user']=='1') echo '<form method="post" action=""><select name="visibility"><option value="" >--</option>
																<option value="block">Block</option>
																<option value="unblock">Unblock</option></select><button name="block">OK</button></form>'; ?>
						<a href="https://twitter.com/share?url=https://www.myphdidea.org/index.php?feat=<?php echo $feat_id; ?>" target="_blank"><img src="images/twitter_square.png" style="width: 20px"></a>
						<a href="https://www.facebook.com/sharer/sharer.php?u=https://www.myphdidea.org/index.php?feat=<?php echo $feat_id; ?>" target="_blank"><img src="images/fb_square.png" style="width: 20px"></a></div>
<div id="centerpage">
<?php echo '<h1>'.$title.'</h1>'; ?>
<?php
	if($verdict=='0' && strtotime($time_created."+ 2 months") < strtotime("now"))
		$approved="updated ".$time_recent.", rejected";
	elseif($verdict=='1')
//	{
//		$row4=$conn->query("SELECT v.verdict_time1, v.verdict_time2, v.verdict_time3
//			FROM verdict v JOIN featuretext f ON (f.featuretext_verdict=v.verdict_id) WHERE f.featuretext_feature='$feat_id'")->fetch_assoc();
		$approved="approved ".$time_approved;//date("Y-m-d H:i:s",max(strtotime($row4['verdict_time1']),strtotime($row4['verdict_time2']),strtotime($row4['verdict_time3'])));
//	}
	elseif(empty($verdict))
		$approved="updated ".$time_recent.", not yet approved";
	$date_queue="(Created ".$time_created.", ".$approved.")";
	
	if(!$visitor_isowner && (is_null($verdict) || ($verdict=='0' && strtotime($time_created."+ 2 months") > strtotime("now"))))
        echo 'Author: <i>We do not publicly disclose author names before evaluation is finished</i> '.$date_queue;
    else echo 'Author: '.$printname.'<br>'.$date_queue; 
	
	if(!empty($manip_msg))
	{
        $manip_footer='<div class="upload_footer">'.$buttons.'<br> </div>';
		echo '<br><br><div class="list" style="border-color: red; height: 80px" id="toplist"><div style="height: '.(80-35).'px; display: block">'.$manip_msg.'</div>'.$manip_footer.'</div>';
	}
?>
<?php echo html_entity_decode(stripcslashes($featuretext)); ?>
<?php if(!empty($amendments)) echo '<i>Correction:</i> <div class="indentation">'.$amendments.'</div>'; ?>
<?php if($verdict=='1')
	{
		if(!empty($ratebox))
		{
			$rvw_row2=$conn->query("SELECT ratebox_popvote, ratebox_popvote_nb FROM ratebox WHERE ratebox_id='$ratebox'")->fetch_assoc();
			$popvote=$rvw_row2['ratebox_popvote'];
			$popvote_nb=$rvw_row2['ratebox_popvote_nb'];
		}

		if((isset($_SESSION['user']) && isset($_SESSION['isstudent'])) || isset($_SESSION['prof']))
		{
			$star1=$star2=$star3=$star4=$star5="";
			if(isset($_SESSION['user']))
			{
				$sql="SELECT 1 FROM moderators m JOIN feature f ON (m.moderators_group=f.feature_moderators_group) WHERE
					(m.moderators_first_user='".$_SESSION['user']."' OR m.moderators_second_user='".$_SESSION['user']."' OR m.moderators_third_user='".$_SESSION['user']."') AND f.feature_id='$feat_id'";
				$visitor_wasmoderator=$conn->query($sql)->num_rows;
			}
			if($visitor_isowner || !empty($visitor_wasmoderator))
				$disable_vote="disabled";
			else
			{							
				if(isset($_SESSION['user']))
					$sql="SELECT r.rating_value FROM rating r JOIN student s ON (r.rating_student=s.student_id)
						JOIN feature f ON (f.feature_ratebox=r.rating_ratebox)
						WHERE s.student_user_id='".$_SESSION['user']."' AND f.feature_id='$feat_id'";
				else $sql="SELECT r.rating_value FROM rating_byprof r
					JOIN feature f ON (f.feature_ratebox=r.rating_ratebox)
					WHERE r.rating_prof='".$_SESSION['prof']."' AND f.feature_id='$feat_id'";
				$result2=$conn->query($sql);
				if($result2->num_rows > 0)
				{
					$disable_vote="disabled";
					$row=$result2->fetch_assoc();
					if($row['rating_value']==1) $star1="selected";
					if($row['rating_value']==2) $star2="selected";
					if($row['rating_value']==3) $star3="selected";
					if($row['rating_value']==4) $star4="selected";
					if($row['rating_value']==5) $star5="selected";
				}
				elseif(isset($_SESSION['prof']) && $conn->query("SELECT 1 FROM autoedit WHERE autoedit_email_auth IS NOT NULL AND autoedit_prof='".$_SESSION['prof']."'")->num_rows == 0)
													$disable_vote="disabled";
				else $disable_vote="";
			}
			$vote_panel='<form method="post" action="" style="float: right">Your score:
				<select name="rate'.$ratebox.'" '.$disable_vote.'>
				<option value="none">--</option>
				<option value="5" '.$star5.'>5 stars</option>
				<option value="4" '.$star4.'>4 stars</option>
				<option value="3" '.$star3.'>3 stars</option>
				<option value="2" '.$star2.'>2 stars</option>
				<option value="1" '.$star1.'>1 star</option>
        		</select>
				<button style="padding-right: 0px; padding-left: 0px;"
				name="submit_rating" value="'.$ratebox.'" '.$disable_vote.'>Rate</button><br></form>';
		}
		else $vote_panel="";
		
		if(isset($popvote)) $vote_panel='Popular score ('
			.$popvote_nb.' votes): <img style="vertical-align: bottom" title="Popular vote" alt="'.$popvote.'-star rating" src="images/'.$popvote.'-star.png">'
			.$vote_panel;
		echo $vote_panel;
	}
?>
</div>
