<?php

if(isset($_POST['update_recent']) && isset($_POST['recentsubject']) && $_POST['recentsubject']!=0)
{
	$recentsubject=$conn->real_escape_string(test($_POST['recentsubject']));
	$recentcondit=" AND (user_subject1='$recentsubject' OR user_subject2='$recentsubject')";
}
else $recentcondit="";

if(isset($_POST['update_best']) && isset($_POST['bestsubject']) && $_POST['bestsubject']!=0)
{
	$bestsubject=$conn->real_escape_string(test($_POST['bestsubject']));
	$bestcondit1=" AND (user_subject1='$bestsubject' OR user_subject2='$bestsubject')";
}
else $bestcondit1="";

if(isset($_POST['duration_monitored']))
	$dur_monit=$conn->real_escape_string(test($_POST['duration_monitored']));
else $dur_monit="dur_halfyear";

switch($dur_monit)
{
	case "dur_week":
		$bestcondit2=" AND c.cmpgn_time_finalized >= DATE(NOW()) - INTERVAL 1 WEEK";
		break;
	case "dur_month":
		$bestcondit2=" AND c.cmpgn_time_finalized >= DATE(NOW()) - INTERVAL 1 MONTH";
		break;
	case "dur_halfyear":
		$bestcondit2=" AND c.cmpgn_time_finalized >= DATE(NOW()) - INTERVAL 6 MONTH";
		break;
	case "dur_2years":
		$bestcondit2=" AND c.cmpgn_time_finalized >= DATE(NOW()) - INTERVAL 24 MONTH";
		break;
	default:
		$bestcondit2="";
}

$sql="SELECT r.review_prof, p.prof_givenname, p.prof_familyname, u.user_subject1, u.user_subject2,
	c.cmpgn_title, r.review_time_submit, c.cmpgn_time_finalized, rb.ratebox_popvote, rb.ratebox_popvote_nb
	FROM review r
	JOIN cmpgn c ON (r.review_id=c.cmpgn_rvw_favourite)
	JOIN prof p ON (r.review_prof=p.prof_id)
	JOIN user u ON (u.user_id=c.cmpgn_user)
	JOIN ratebox rb ON (r.review_ratebox=rb.ratebox_id)
	WHERE c.cmpgn_time_finalized IS NOT NULL";
$recent_rvw="";
$result_recent=$conn->query($sql.$recentcondit." ORDER BY c.cmpgn_time_finalized DESC LIMIT 4");
while($row=$result_recent->fetch_assoc())
{
if(strlen($row['cmpgn_title'])>25) $ellipsis="..."; else $ellipsis="";
$recent_rvw=$recent_rvw.'<tr>
              <td style="width: 60px;">'.$row['cmpgn_time_finalized'].'<br>
              </td>
              <td style="width: 92.1px;"><a href="index.php?prof='.$row['review_prof'].'">'.$row['prof_givenname'].' '.$row['prof_familyname'].'</a><br>
              </td>
              <td style="width: 150px;">'.substr($row['cmpgn_title'],0,28).$ellipsis.'<br>
              </td>
              <td style="max-width: 75.3px;"><img alt="Rating" src="images/'.$row['ratebox_popvote'].'-star.png">
				<br>('.$row['ratebox_popvote_nb'].' votes)<br>
              </td>
       </tr>';
}
$best_rvw="";
$result_best=$conn->query($sql.$bestcondit1.$bestcondit2." ORDER BY rb.ratebox_popvote DESC LIMIT 10");
while($row=$result_best->fetch_assoc())
{
if(strlen($row['cmpgn_title'])>25) $ellipsis="..."; else $ellipsis="";
$best_rvw=$best_rvw.'<tr>
              <td style="width: 60px;">'.$row['cmpgn_time_finalized'].'<br>
              </td>
              <td style="width: 92.1px;"><a href="index.php?prof='.$row['review_prof'].'">'.$row['prof_givenname'].' '.$row['prof_familyname'].'</a><br>
              </td>
              <td style="width: 150px;">'.substr($row['cmpgn_title'],0,28).$ellipsis.'<br>
              </td>
              <td style="max-width: 75.3px;"><img alt="Rating" src="images/'.$row['ratebox_popvote'].'-star.png">
				<br>('.$row['ratebox_popvote_nb'].' votes)<br>
              </td>
       </tr>';
}

$sql="SELECT COUNT(*) AS rvw_finished_nb FROM review WHERE review_time_requested IS NOT NULL AND review_time_submit IS NOT NULL";
$row=$conn->query($sql)->fetch_assoc();
$rvw_finished_nb=$row['rvw_finished_nb'];
$sql="SELECT COUNT(*) AS rvw_notfinished_nb FROM review WHERE review_time_requested IS NOT NULL AND (review_time_aborted IS NOT NULL OR review_time_tgth_passedon IS NOT NULL)";
$row=$conn->query($sql)->fetch_assoc();
$rvw_requested_nb=$rvw_finished_nb+$row['rvw_notfinished_nb'];
$sql="SELECT COUNT(*) AS prof_reg_nb FROM autoedit WHERE autoedit_email_auth IS NOT NULL";
$row=$conn->query($sql)->fetch_assoc();
$prof_reg_nb=$row['prof_reg_nb'];
?>
<form method="post" action="">
      <div id="centerpage"> Please use the above search bar to access specific
        researcher profiles. Profiles consist mainly in a list of reviews written
        by the professor, and recent (good) reviews are published here upon
        campaign end (reviews are invisible during campaign runtime). <br>
        <br>
        Most recent reviews (approved only):
        
        <div id="dur_monitor">
          <select name="recentsubject">
          	<?php $subject=$recentsubject; include("includes/subject_selector.php") ?>
          </select>
          <button name="update_recent">OK</button>
        </div>    
        <a href="rss.php?type=prof&recentsubject=<?php if(!empty($recentsubject)) echo $recentsubject; else echo '0'; ?>"><img src="images/rss_black_small.png" style="float: right; margin-right: 5px; margin-top: 3px"></a>
        	
        <div style="height: 210px;" id="toplist" class="list">
<?php   if(!empty($recent_rvw)) echo '<table style="width: 100%">
          <tbody>
            <tr>
              <th style="width:60px;">Published<br>
              </th>
              <th style="width: 92.1px;">Reviewer<br>
              </th>
              <th style="width:150px;">Subject<br>
              </th>
              <th style="width:75.3px;">Popular vote<br>
              </th>
            </tr>'.$recent_rvw.'</tbody></table>';
		else echo '<div style="margin: 2px"><i>No publications yet, yours can be the first!</i></div>';
?>
        </div>
        Top reviews by community score:
        <div id="dur_monitor">
          <select name="bestsubject" style="max-width: 150px">
          	<?php $subject=$bestsubject; include("includes/subject_selector.php") ?>
          </select>
          <select name="duration_monitored">
            <option value="dur_week" <?php if($dur_monit=="dur_week") echo "selected"; ?>>Last week</option>
            <option value="dur_month" <?php if($dur_monit=="dur_month") echo "selected"; ?>>Last month</option>
            <option value="dur_halfyear" <?php if($dur_monit=="dur_halfyear") echo "selected"; ?>>Last 6 months</option>
            <option value="dur_2years" <?php if($dur_monit=="dur_2years") echo "selected"; ?>>Last 2 years</option>
          </select>
          <button name="update_best">OK</button>
        </div><br>
        <div id="bottomlist" class="list">
<?php   if(!empty($best_rvw)) echo '<table style="width: 100%">
          <tbody>
            <tr>
              <th style="width:60px;">Published<br>
              </th>
              <th style="width: 92.1px;">Reviewer<br>
              </th>
              <th style="width:150px;">Subject<br>
              </th>
              <th style="width:75.3px;">Popular vote<br>
              </th>
            </tr>'.$best_rvw.'</tbody></table>';
		else echo "";
?>
        </div>
        <div style="text-align: center;"> <br>
          Statistics: <?php echo $rvw_finished_nb; ?> reviews, <?php echo $rvw_requested_nb ?> requested, <?php echo $prof_reg_nb; ?> professors registered </div>
      </div>
</form>