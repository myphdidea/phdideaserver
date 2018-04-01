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
		$bestcondit2=" AND c.cmpgn_time_firstsend >= DATE(NOW()) - INTERVAL 1 WEEK";
		break;
	case "dur_month":
		$bestcondit2=" AND c.cmpgn_time_firstsend >= DATE(NOW()) - INTERVAL 1 MONTH";
		break;
	case "dur_halfyear":
		$bestcondit2=" AND c.cmpgn_time_firstsend >= DATE(NOW()) - INTERVAL 6 MONTH";
		break;
	case "dur_2years":
		$bestcondit2=" AND c.cmpgn_time_firstsend >= DATE(NOW()) - INTERVAL 24 MONTH";
		break;
	default:
		$bestcondit2="";
}

$sql="SELECT u.user_subject1, u.user_subject2,
	c.cmpgn_id, c.cmpgn_title, c.cmpgn_time_firstsend, rb.ratebox_popvote, rb.ratebox_popvote_nb
	FROM cmpgn c
	JOIN user u ON (u.user_id=c.cmpgn_user)
	JOIN ratebox rb ON (c.cmpgn_ratebox=rb.ratebox_id)
	WHERE c.cmpgn_time_firstsend IS NOT NULL AND c.cmpgn_displayinfeed='1' AND c.cmpgn_visibility_blocked='0'";
$recent_rvw="";
$result_recent=$conn->query($sql.$recentcondit." ORDER BY c.cmpgn_time_firstsend DESC LIMIT 4");
while($row=$result_recent->fetch_assoc())
{
//if(strlen($row['cmpgn_title'])>25) $ellipsis="..."; else $ellipsis="";
$recent_rvw=$recent_rvw.'<tr>
              <td style="width: 90px;">'.$row['cmpgn_time_firstsend'].'<br>
              </td>
              <td style="width: 210px;"><a href="index.php?cmpgn='.$row['cmpgn_id'].'">'.$row['cmpgn_title'].'</a><br>
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
//if(strlen($row['cmpgn_title'])>25) $ellipsis="..."; else $ellipsis="";
$best_rvw=$best_rvw.'<tr>
              <td style="width: 90px;">'.$row['cmpgn_time_firstsend'].'<br>
              </td>
              <td style="width: 210px;"><a href="index.php?cmpgn='.$row['cmpgn_id'].'">'.$row['cmpgn_title'].'</a><br>
              </td>
              <td style="max-width: 75.3px;"><img alt="Rating" src="images/'.$row['ratebox_popvote'].'-star.png">
				<br>('.$row['ratebox_popvote_nb'].' votes)<br>
              </td>
              </td>
       </tr>';
}

$sql="SELECT COUNT(*) AS cmpgn_approved_nb FROM cmpgn WHERE cmpgn_time_firstsend IS NOT NULL AND cmpgn_type_isarchivized='0'";
$row=$conn->query($sql)->fetch_assoc();
$cmpgn_approved_nb=$row['cmpgn_approved_nb'];
$sql="SELECT COUNT(*) AS cmpgn_created_nb FROM cmpgn WHERE cmpgn_type_isarchivized='0'";
$row=$conn->query($sql)->fetch_assoc();
$cmpgn_created_nb=$row['cmpgn_created_nb'];
$sql="SELECT COUNT(*) AS cmpgn_finished_nb FROM cmpgn WHERE cmpgn_time_finalized IS NOT NULL AND cmpgn_time_firstsend IS NOT NULL AND cmpgn_type_isarchivized='0'";
$row=$conn->query($sql)->fetch_assoc();
$cmpgn_finished_nb=$row['cmpgn_finished_nb'];
$sql="SELECT COUNT(*) AS cmpgn_running_nb FROM cmpgn WHERE cmpgn_time_finalized IS NULL AND cmpgn_time_firstsend IS NOT NULL AND cmpgn_type_isarchivized='0'";
$row=$conn->query($sql)->fetch_assoc();
$cmpgn_running_nb=$row['cmpgn_running_nb'];
?>
<form method="post" action="">
      <div id="centerpage"> PhD ideas first go through a "student peer review"
      	approval process, and then are published here with the consent of the authors (see upload newsfeed
      	for a complete overview of all new material). Reviews are published a few months later under Profs.<br>
        <br>
        Most recent ideas (approved only):
        <div id="dur_monitor">
          <select name="recentsubject">
          	<?php $subject=$recentsubject; include("includes/subject_selector.php") ?>
          </select>
          <button name="update_recent">OK</button>
        </div>    
        <a href="rss.php?type=idea&recentsubject=<?php if(!empty($recentsubject)) echo $recentsubject; else echo '0'; ?>"><img src="images/rss_black_small.png" style="float: right; margin-right: 5px; margin-top: 3px"></a>
        
        <div style="height: 210px;" id="toplist" class="list">
<?php   if(!empty($recent_rvw)) echo '<table style="width: 100%">
          <tbody>
            <tr>
              <th style="width:90px;">Published<br>
              </th>
              <th style="width: 210px;">Title<br>
              </th>
              <th style="width:75.3px;">Popular vote<br>
              </th>
            </tr>'.$recent_rvw.'</tbody></table>';
		else echo '<div style="margin: 2px"><i>No publications yet, yours can be the first!</i></div>';
?>
        </div>
        Top ideas by community score:
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
              <th style="width:90px;">Published<br>
              </th>
              <th style="width: 210px;">Title<br>
              </th>
              <th style="width:75.3px;">Popular vote<br>
              </th>
            </tr>'.$best_rvw.'</tbody></table>';
		else echo "";
?>
        </div>
        <div style="text-align: center;"> <br>
          Statistics: <?php echo $cmpgn_approved_nb; ?> approved, <?php echo $cmpgn_created_nb ?> created, <?php echo $cmpgn_running_nb; ?> running, <?php echo $cmpgn_finished_nb; ?> finished </div>
      </div>
</form>