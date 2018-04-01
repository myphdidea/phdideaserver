<?php

if(isset($_POST['duration_monitored']))
	$dur_monit=$conn->real_escape_string(test($_POST['duration_monitored']));
else $dur_monit="dur_halfyear";

switch($dur_monit)
{
	case "dur_week":
		$bestcondit2=" AND f.feature_time_approved >= DATE(NOW()) - INTERVAL 1 WEEK";
		break;
	case "dur_month":
		$bestcondit2=" AND f.feature_time_approved >= DATE(NOW()) - INTERVAL 1 MONTH";
		break;
	case "dur_halfyear":
		$bestcondit2=" AND f.feature_time_approved >= DATE(NOW()) - INTERVAL 6 MONTH";
		break;
	case "dur_2years":
		$bestcondit2=" AND f.feature_time_approved >= DATE(NOW()) - INTERVAL 24 MONTH";
		break;
	default:
		$bestcondit2="";
}

$sql="SELECT ft.featuretext_feature, ft.featuretext_title, f.feature_time_approved, rb.ratebox_popvote, rb.ratebox_popvote_nb
	FROM featuretext ft
	JOIN feature f ON (ft.featuretext_feature=f.feature_id)
	JOIN ratebox rb ON (f.feature_ratebox=rb.ratebox_id)
	WHERE ft.featuretext_verdict_summary='1' AND (f.feature_visibility_blocked='0' OR f.feature_visibility_blocked IS NULL)";
$recent_rvw="";
$result_recent=$conn->query($sql." ORDER BY f.feature_time_approved DESC LIMIT 4");
while($row=$result_recent->fetch_assoc())
{
//if(strlen($row['cmpgn_title'])>25) $ellipsis="..."; else $ellipsis="";
$recent_rvw=$recent_rvw.'<tr>
              <td style="width: 90px;">'.$row['feature_time_approved'].'<br>
              </td>
              <td style="width: 210px;"><a href="index.php?feat='.$row['featuretext_feature'].'">'.$row['featuretext_title'].'</a><br>
              </td>
              <td style="max-width: 75.3px;"><img alt="Rating" src="images/'.$row['ratebox_popvote'].'-star.png">
				<br>('.$row['ratebox_popvote_nb'].' votes)<br>
              </td>
       </tr>';
}
$best_rvw="";
$result_best=$conn->query($sql.$bestcondit2." ORDER BY rb.ratebox_popvote DESC LIMIT 10");
while($row=$result_best->fetch_assoc())
{
//if(strlen($row['cmpgn_title'])>25) $ellipsis="..."; else $ellipsis="";
$best_rvw=$best_rvw.'<tr>
              <td style="width: 90px;">'.$row['feature_time_approved'].'<br>
              </td>
              <td style="width: 210px;"><a href="index.php?feat='.$row['featuretext_feature'].'">'.$row['featuretext_title'].'</a><br>
              </td>
              <td style="max-width: 75.3px;"><img alt="Rating" src="images/'.$row['ratebox_popvote'].'-star.png">
				<br>('.$row['ratebox_popvote_nb'].' votes)<br>
              </td>
       </tr>';
}

$sql="SELECT COUNT(*) AS feature_approved_nb FROM feature WHERE feature_time_approved IS NOT NULL";
$row=$conn->query($sql)->fetch_assoc();
$feature_approved_nb=$row['feature_approved_nb'];
$sql="SELECT COUNT(*) AS feature_rejected_nb FROM feature WHERE NOT EXISTS (SELECT 1 FROM featuretext WHERE featuretext_verdict_summary IS NULL AND featuretext_feature=feature_id)
	AND feature_time_approved IS NULL AND feature_time_created + INTERVAL 2 MONTH < NOW()";
$row=$conn->query($sql)->fetch_assoc();
$feature_rejected_nb=$row['feature_rejected_nb'];
$sql="SELECT COUNT(*) AS feature_created_nb FROM feature";
$row=$conn->query($sql)->fetch_assoc();
$feature_created_nb=$row['feature_created_nb'];
?>
<form method="post" action="">
      <div id="centerpage"> In addition to publishing PhD ideas by students and reviews by professors,
      	we also offer a platform for articles on higher education and research, aimed
      	at a generalist audience. Like all material on the site, these have been vetted by 3 volunteers.<br>
        <br>
        Most recent features (approved only):<a href="rss.php?type=feat"><img src="images/rss_black_small.png" style="float: right"></a>
                
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
        Top features by community score:
        <div id="dur_monitor">
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
          Statistics: <?php echo $feature_approved_nb; ?> approved, <?php echo $feature_rejected_nb ?> rejected, <?php echo $feature_created_nb-$feature_rejected_nb-$feature_approved_nb; ?> running, <?php echo $feature_created_nb; ?> total </div>
      </div>