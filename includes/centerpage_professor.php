<?php
$prof_id=$conn->real_escape_string($_GET['prof']);

$sql="SELECT prof_givenname, prof_familyname, prof_orcid, prof_image, prof_description, prof_resbox,
	  prof_institution, prof_country, prof_email, prof_email_alt FROM prof WHERE prof_id='$prof_id'";
$row=$conn->query($sql)->fetch_assoc();
$prof_givenname=$row['prof_givenname'];
$prof_familyname=$row['prof_familyname'];
$prof_orcid=$row['prof_orcid'];
$prof_image=$row['prof_image'];
$prof_description=$row['prof_description'];
$prof_resbox=$row['prof_resbox'];
if(!empty($row['prof_email_alt'])) $prof_email_tot=$row['prof_email'].", ".$row['prof_email_alt'];
elseif(!empty($row['prof_email'])) $prof_email_tot=$row['prof_email'];

$row2=$conn->query("SELECT institution_name FROM institution WHERE institution_id='".$row['prof_institution']."'")->fetch_assoc();
$instit_name=$row2['institution_name'];

$row2=$conn->query("SELECT country_name FROM country WHERE country_id='".$row['prof_country']."'")->fetch_assoc();
$country_name=$row2['country_name'];

//GENERATE PUBLICATION LIST
$publication_list_prev="";
if(!empty($prof_description)) $limit=1; else $limit=3;
$sql="SELECT p.publication_title, p.publication_date FROM publication p
	JOIN record r ON (p.publication_id=r.record_publication)
	JOIN resbox_impl rb ON (r.record_researcher_id=rb.resbox_impl_researcher)
	WHERE rb.resbox_impl_id='$prof_resbox' LIMIT $limit";
$result=$conn->query($sql);
while($row=$result->fetch_assoc())
	$publication_list_prev=$publication_list_prev."<li>".$row['publication_title']." (".$row['publication_date'].")</li>";
if(!empty($publication_list_prev)) $publication_list_prev='<ul style="margin: 0px; padding: 0px; padding-left: 20px">'.$publication_list_prev."</ul>";

//get review scores
$poor=$mediocre=$good=0;
$sql="SELECT review_grade, COUNT(*) AS score_nb FROM review WHERE review_grade_invalidated='0'
	AND (review_time_submit IS NOT NULL OR (review_time_aborted IS NOT NULL AND review_agreed='0')) AND review_prof='$prof_id'
	AND NOT EXISTS (SELECT 1 FROM verdict WHERE verdict_id=review_gradedby_verdict AND
	(verdict_time1 IS NULL OR verdict_time2 IS NULL OR verdict_time3 IS NULL)) GROUP BY review_grade ORDER BY review_grade ASC";
$result=$conn->query($sql);
while($row=$result->fetch_assoc())
	switch($row['review_grade'])
	{
		case '0':
		case 0:
			$poor+=$row['score_nb'];
			break;
		case 1:
			$mediocre+=$row['score_nb'];
			break;
		case 2:
			$good+=$row['score_nb'];
			break;
	}
$sql="SELECT COUNT(*) AS score_nb FROM review WHERE review_time_aborted IS NOT NULL AND review_agreed='1' AND review_prof='$prof_id'";
$row=$conn->query($sql)->fetch_assoc();
$shame=$row['score_nb']+0;

$sql="SELECT review_id, review_grade, review_time_aborted, review_agreed FROM review WHERE review_prof='$prof_id'
	AND (review_time_submit IS NOT NULL OR review_time_aborted IS NOT NULL OR review_time_tgth_passedon IS NOT NULL) ORDER BY review_id DESC";
$row=$conn->query($sql)->fetch_assoc();
if(!is_null($row['review_grade']))
	$review_grade=$row['review_grade'];
elseif(!empty($row['review_time_aborted']) && $row['review_agreed']=='0')
	$review_grade=0;
elseif(!empty($row['review_time_aborted']) && $row['review_agreed']=='1')
	$review_grade=-1;

if(!isset($review_grade))
	$rvw_img_latest="no";
else switch($review_grade)
	{
		case 2: $rvw_img_latest="green"; break;
		case 1: $rvw_img_latest="orange"; break;
		case 0: $rvw_img_latest="red"; break;
		case -1: $rvw_img_latest="skull"; break;
	}

if(isset($_SESSION['prof']) && $_SESSION['prof']==$prof_id)
	$visitor_isreviewer=TRUE;
else $visitor_isreviewer=FALSE;

?>
<div id="centerpage">

		<div class="prof_profile">
		<div style="float: left">
<?php
	if(!empty($prof_image))
		$image_path="user_data/researcher_pictures/".$prof_id.".png";
	else $image_path="images/default_scholar_large.png";

	$image_path='<img alt="" src="'.$image_path.'">';
	if(!empty($prof_orcid)) //<b>ORCID: </b>'.str_replace("-",'-<wbr>',$prof_orcid).'
		$image_path='<a title="ORCID: '.$prof_orcid.'" href="http://orcid.org/'.$prof_orcid.'">'.$image_path.'</a>';
	$image_path='<div class="icon_large" style="margin-right: 3px">'.$image_path.'</div>';
	
	echo $image_path;
?>
		</div>
		<b>Name: </b><?php echo $prof_givenname." ".$prof_familyname;
			if((isset($_SESSION['prof']) || (isset($_SESSION['user']) && isset($_SESSION['isstudent']))) && !empty($prof_email_tot)) echo " <i>(".$prof_email_tot.")</i>";
			?><br>
		<?php if(!empty($instit_name)) echo "<b>Institution: </b>".$instit_name;
			elseif(!empty($country_name)) echo '<b>Country: </b>'.$country_name;
		?>
		<br>
		<div style="padding: 5px"><img style="float: left; margin-left: 35px; vertical-align: baseline" title="Last obtained score"
				src="images/<?php echo $rvw_img_latest; ?>-smiley-small.png">
          <ul id="profscore" class="dashboard" title="Aggregate review scores from crowd-sourced moderators">
            <?php if(!empty($good)) echo '<li style="color: #1ae61a">'.$good.'</li>'; else echo '<li style="color: dimgrey">0</li>'; ?>
            <?php if(!empty($mediocre)) echo '<li style="color: orange">'.$mediocre.'</li>'; else echo '<li style="color: dimgrey">0</li>'; ?></li>
            <?php if(!empty($poor)) echo '<li style="color: red">'.$poor.'</li>'; else echo '<li style="color: dimgrey">0</li>'; ?>
            <?php if(!empty($shame)) echo '<li style="color: black; background-color: red; height: 20px">'.$shame.'</li>'; else echo '<li style="color: dimgrey; background-color: lightgrey; height: 20px">0</li>';?>
<!--            <li style="color: lime"><?php echo $good; ?></li>
            <li style="color: orange"><?php echo $mediocre; ?></li>
            <li style="color: red"><?php echo $poor; ?></li>
            <li style="color: black; background-color: red; height: 20px"><?php echo $shame; ?></li>-->
          </ul><br><br>
          <ul id="caption_userscore" class="dashboard" style="display: inline-block; margin-left: 30px; margin-bottom: 0px; margin-top: 0px">
            <li>Latest</li>
            <li>Good</li>
            <li>Mediocre</li>
            <li>Poor</li>
            <li>Shame</li>
          </ul>
		</div>
		<?php 
			if(!empty($prof_description)) echo '<b>Description: </b><div style="display: inline-block; width: 400px; vertical-align: top">'.$prof_description.'</div><br>'; 
			if(!empty($prof_description) && !empty($publication_list_prev)) echo '<br>';
			if(!empty($publication_list_prev)) echo '<b>Papers: </b><div style="display: inline-block; width: 450px; vertical-align: top">'.$publication_list_prev.'</div><br>';
		?>
		</div>

		<h3>Reviews</h3>
<?php
			if(isset($_POST['submit_rating'])
				&& (isset($_SESSION['user']) && isset($_SESSION['isstudent']) //&& $_SESSION['user']!=$owner
					|| (isset($_SESSION['prof']) && !$visitor_isreviewer)))
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
//					$sql="UPDATE review SET review_popvote='".round($rate_avg)."', review_popvote_nb='".($row1['rating_nb']+$row2['rating_nb'])."' WHERE review_ratebox='$submit_rating'";
					$sql="UPDATE ratebox SET ratebox_popvote='".round($rate_avg)."', ratebox_popvote_nb='".($row1['rating_nb']+$row2['rating_nb'])."' WHERE ratebox_id='$submit_rating'";
					$conn->query($sql);
				}
			}
?>
<?php
$sql="SELECT r.review_id, "/*r.review_prof,*/." r.review_text, r.review_agreed, r.review_time_requested,
	  r.review_time_submit, r.review_time_tgth_passedon, r.review_time_aborted, r.review_together_with,
	  r.review_grade, r.review_ratebox, r.review_grade_invalidated, r.review_aborted_byuser,
	  r.review_hideifgood, c.cmpgn_id, c.cmpgn_title, c.cmpgn_time_finalized, c.cmpgn_user, c.cmpgn_rvw_favourite FROM review r
	  JOIN upload u ON (r.review_upload=u.upload_id) JOIN cmpgn c ON (c.cmpgn_id=u.upload_cmpgn)
	  WHERE r.review_prof='".$prof_id."' AND (r.review_time_submit IS NOT NULL OR r.review_time_tgth_passedon IS NOT NULL
	  OR r.review_time_aborted IS NOT NULL) ORDER BY r.review_time_requested DESC";
$result=$conn->query($sql);
if($result->num_rows == 0) echo '<i>No reviews contributed by this researcher yet.</i>';
while($rvw_row=$result->fetch_assoc())
{
	$r_id=$rvw_row['review_id'];
//	$r_prof=$rvw_row['review_prof'];
	$r_text=$rvw_row['review_text'];
	$r_seen=$rvw_row['review_agreed'];
	$r_time_requested=$rvw_row['review_time_requested'];
	$r_time_submit=$rvw_row['review_time_submit'];
	$r_time_tgth_passedon=$rvw_row['review_time_tgth_passedon'];
	$r_time_aborted=$rvw_row['review_time_aborted'];
	$r_aborted_byuser=$rvw_row['review_aborted_byuser'];
	$r_together_with=$rvw_row['review_together_with'];
	$r_grade=$rvw_row['review_grade'];
	$r_grade_invalidated=$rvw_row['review_grade_invalidated'];
	$r_ratebox=$rvw_row['review_ratebox'];
	if(empty($r_text)) $shrink='style="min-height: 150px; max-height: 150px"'; else $shrink="";
	if(!empty($r_ratebox))
	{
		$rvw_row2=$conn->query("SELECT ratebox_popvote, ratebox_popvote_nb FROM ratebox WHERE ratebox_id='$r_ratebox'")->fetch_assoc();
		$r_popvote=$rvw_row2['ratebox_popvote'];
		$r_popvote_nb=$rvw_row2['ratebox_popvote_nb'];
	}
	else
	{
		unset($r_popvote);
		unset($r_popvote_nb);
	}
	$r_hideifgood=$rvw_row['review_hideifgood'];
	$cmpgn_id=$rvw_row['cmpgn_id'];
	$c_title=$rvw_row['cmpgn_title'];
	$owner=$rvw_row['cmpgn_user'];
	$rvw_favourite=$rvw_row['cmpgn_rvw_favourite'];
	$time_finalized=$rvw_row['cmpgn_time_finalized'];
	
	if(empty($r_time_submit) && empty($r_time_aborted) && empty($r_time_tgth_passedon))
		continue;

	if($r_hideifgood && strlen($r_grade)==0 && !$visitor_isreviewer/*moderator && !$visitor_isowner*/)
		continue;
	
	if(!$visitor_isreviewer && empty($time_finalized))
		continue;
	
/*	$sql="SELECT prof_id, prof_image, prof_givenname, prof_familyname FROM prof WHERE prof_id='".$r_prof."'";
		$prof_lbl=prof_label($conn->query($sql)->fetch_assoc());*/
	if(strlen($c_title) > 45) $c_title=substr($c_title,0,45)."...";
	$prof_lbl='On <a href="index.php?cmpgn='.$cmpgn_id.'">'.$c_title.'</a>';
					
	$vote_panel="";
	if(!empty($r_time_tgth_passedon))
	//print name of prof to whom passed on, set r_grade to zero if passed on prof review bad ...
	{
		$sql="SELECT prof_givenname, prof_familyname FROM prof WHERE prof_id='".$r_together_with."'";
		$row=$conn->query($sql)->fetch_assoc();
		$r_text='<i>The responsibility for this review was passed on to</i> <a href="index.php?prof='.$r_together_with.'">'.$row['prof_givenname'].' '.$row['prof_familyname'].'</a> <i>as of</i> '.$r_time_tgth_passedon.'.';
		$sql="SELECT r.review_time_submit, r.review_grade, r.review_agreed, r.review_time_aborted FROM review r JOIN upload u ON (r.review_upload=u.upload_id)
			  WHERE r.review_prof='".$r_together_with."' AND u.upload_cmpgn='".$cmpgn_id."'";
		$row=$conn->query($sql)->fetch_assoc();
		if(!empty($row['review_grade']) || $row['review_grade']=='0' || !empty($row['review_time_aborted']))
		{
			if(!empty($row['review_time_aborted']) && !empty($row['review_agreed']))
				$r_grade=-1;
			elseif(!empty($row['review_time_aborted']))
				$r_grade=0;
			else $r_grade=$row['review_grade'];
			$r_text=$r_text." <i>The grade reflects the score obtained by the replacement reviewer.</i>";
		}
	}
	else if(!empty($r_time_aborted))
	{
		$r_grade=0;
		if(!empty($r_seen))
		{
			$r_text="<i>Review agreed to but not completed even after multiple reminders, resulting in more than 6 weeks delay.</i>";
			$r_grade=-1;
		}
		else $r_text="<i>Professor declined to inspect material/submit review.</i>";
	}
	elseif(!empty($r_hideifgood) && $r_grade==2 && !$visitor_isreviewer)
		$r_text="<i>Achieved good score but did not want to publish out of privacy concerns.</i>";
	elseif(!empty($r_grade) || $r_grade=='0')
	//ENABLE POPULAR VOTE
	{
		if((isset($_SESSION['user']) && isset($_SESSION['isstudent'])) || isset($_SESSION['prof']))
		{
			$star1=$star2=$star3=$star4=$star5="";
			if(isset($_SESSION['user']))
			{
				$sql="SELECT 1 FROM moderators m JOIN cmpgn c ON (m.moderators_group=c.cmpgn_moderators_group) WHERE
					(moderators_first_user='".$_SESSION['user']."' OR moderators_second_user='".$_SESSION['user']."' OR moderators_third_user='".$_SESSION['user']."') AND c.cmpgn_id='$cmpgn_id'";
				$visitor_wasmoderator=$conn->query($sql)->num_rows;
			}
			if((isset($_SESSION['user']) && $owner==$_SESSION['user']) || !empty($visitor_wasmoderator) || $visitor_isreviewer)
				$disable_vote="disabled";
			else
			{
				if(isset($_SESSION['user']))
					$sql="SELECT r.rating_value FROM rating r JOIN student s ON (r.rating_student=s.student_id)
						JOIN review rv ON (rv.review_ratebox=r.rating_ratebox)
						WHERE s.student_user_id='".$_SESSION['user']."' AND rv.review_id='$r_id'";
				else $sql="SELECT r.rating_value FROM rating_byprof r 
					JOIN review rv ON (rv.review_ratebox=r.rating_ratebox)
					WHERE r.rating_prof='".$_SESSION['prof']."' AND rv.review_id='$r_id'";
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
			$vote_panel='<form method="post" action="">Your score:<br>
				<select name="rate'.$r_ratebox.'" '.$disable_vote.'>
				<option value="none">--</option>
				<option value="5" '.$star5.'>5 stars</option>
				<option value="4" '.$star4.'>4 stars</option>
				<option value="3" '.$star3.'>3 stars</option>
				<option value="2" '.$star2.'>2 stars</option>
				<option value="1" '.$star1.'>1 star</option>
         			</select>
				<button style="padding-right: 0px; padding-left: 0px;"
				name="submit_rating" value="'.$r_ratebox.'" '.$disable_vote.'>Rate</button><br></form>';
		}
		else $vote_panel="";

	}
	if(!empty($r_time_submit))
		$submitted_text=", submitted ".$r_time_submit;
	elseif(!empty($r_time_aborted) && $r_aborted_byuser=='0')
		$submitted_text=", auto-aborted ".$r_time_aborted;
	elseif(!empty($r_time_aborted))
		$submitted_text=", user-aborted ".$r_time_aborted;
	else $submitted_text="";

	if($r_grade=='0')
		$moderator_view='<img title="No answer or very short answer" class="review_smiley" alt="Low grade"
   					src="images/red-smiley.png">';
	elseif($r_grade==1)
		$moderator_view='<img title="Rather superficial answer" class="review_smiley" alt="Mid grade"
   					src="images/orange-smiley.png">';
	elseif($r_grade==2)
		$moderator_view='<img title="An answer to be proud of" class="review_smiley" alt="High grade"
   					src="images/green-smiley.png">';
	elseif($r_grade==-1)
		$moderator_view='<img title="The worst of the worst" class="review_smiley" alt="Beyond bad"
   					src="images/smiley-skull.png">';
	else $moderator_view='<img title="Rating pending" class="review_smiley" alt="No grade"
    				src="images/no-smiley.png">';

    if(isset($r_popvote)) $vote_panel='Popular score ('
		.$r_popvote_nb.' votes):<img title="Popular vote" alt="'.$r_popvote.'-star rating" src="images/'.$r_popvote.'-star.png"><br>'
		.$vote_panel;

	if($r_id==$rvw_favourite)
		$fav_issel="selected";
	else $fav_issel="";
	/*if($visitor_isowner && $r_grade==2 && empty($time_finalized))
    		$choose_fav='<div style="float: right">
       					<form method="post" action="">
           					<select name="fav_select'.$r_id.'">
         					<option value="none">--</option>
       					<option value="fav" '.$fav_issel.'>Favourite</option>
       				</select>
       				<br>
      				<button name="update_fav" value="'.$r_id.'">Update</button></form>
    			</div>';
	else*/if($r_id==$rvw_favourite && !empty($time_finalized)) $choose_fav='<div style="float: right"><img src="images/trophy.png"></div>';
	else $choose_fav='';
												
        echo '<a id="r'.$r_id.'"></a><div class="review_superframe">
        	<div class="review">
        		<div class="review_inset" '.$shrink.'> '.$r_text.'</div>
            	<div class="upload_footer">'
					.$choose_fav
            		.$prof_lbl.
            		', requested '.$r_time_requested.$submitted_text.' </div>
          	</div>
          			Moderator view of this review:<br>
				'.$moderator_view.$vote_panel.'<br>
				<a href="https://www.facebook.com/sharer/sharer.php?u=https://www.myphdidea.org/index.php?prof='.$prof_id.'#r'.$r_id.'" target="_blank"><img src="images/fb_share.png" style="width: 8.25%; opacity:0.6;filter:alpha(opacity=60)"></a>
				<a href="https://twitter.com/share?url=https://www.myphdidea.org/index.php?prof='.$prof_id.'#r'.$r_id.'" target="_blank"><img src="images/tweet.png" style="width: 9%; opacity:0.6;filter:alpha(opacity=60)"></a>
        </div>';
	}
?>
<?php
$sql="SELECT c.cmpgn_id, c.cmpgn_title FROM cmpgn c JOIN interaction i ON (c.cmpgn_id=i.interaction_cmpgn)
	JOIN upload u ON (u.upload_cmpgn=c.cmpgn_id)
	JOIN user us ON (c.cmpgn_user=us.user_id)
	WHERE us.user_pts_misc >=0 AND u.upload_verdict_summary='1' AND c.cmpgn_visibility_blocked='0' AND i.interaction_with='$prof_id' LIMIT 20";
$result=$conn->query($sql);
if($result->num_rows > 3)
	echo '<br><input id="textexpand" class="checkbox_arrow" type="checkbox"><label for="textexpand"></label>';
echo '<h3>Interactions</h3>';
if($result->num_rows==0)
	echo "<i>No interactions yet.</i>";
if($result->num_rows > 3) echo '<div class="hideable">';
while($row=$result->fetch_assoc())
{
	if(strlen($row['cmpgn_title']) > 80)
		$row['cmpgn_title']=substr($row['cmpgn_title'],0,80)."...";
	echo '<div class="review" style="width: 90%">With <a href="index.php?cmpgn='.$row['cmpgn_id'].'">'.$row['cmpgn_title'].'</a></div>';
}
if($result->num_rows > 3) echo '</div>';
?>
</div>