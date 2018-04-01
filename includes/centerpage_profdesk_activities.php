<?php

if(isset($_SESSION['prof']))
{
	$dialogue_list="";
	$sql="SELECT c.cmpgn_id, c.cmpgn_title, MAX(d.dialogue_time_sent) AS last_activity FROM cmpgn c JOIN dialogue d ON (d.dialogue_cmpgn=c.cmpgn_id) WHERE d.dialogue_prof='".$_SESSION['prof']."' GROUP BY c.cmpgn_id ORDER BY last_activity DESC";
	$result=$conn->query($sql);
	while($row=$result->fetch_assoc())
		$dialogue_list=$dialogue_list.'<tr>
              		<td style="width: 300px;"><a href="index.php?cmpgn='.$row['cmpgn_id'].'">'.$row['cmpgn_title'].'</a><br>
              		</td>
              		<td style="width: 90px;">'.$row['last_activity'].'<br>
              		</td>
            	</tr>';


	//ASSEMBLE TASKLIST
    $taskentrusted="";
	$sql="SELECT review_time_requested, review_agreed, review_directlogin FROM review WHERE review_time_requested IS NOT NULL AND review_time_submit IS NULL
		AND review_time_tgth_passedon IS NULL AND review_time_aborted IS NULL AND review_prof='".$_SESSION['prof']."'";
    $result=$conn->query($sql);
    while($row=$result->fetch_assoc())
	{
		if(!empty($row['review_agreed']))
		{
			$addtime="6 weeks";
			$type="Review agreed";
		}
		else
		{
			$addtime="2 weeks";
			$type="Review request";
		}
		
		$remaining=strtotime($row['review_time_requested']."+".$addtime)-strtotime("now");
		$remaining_out=floor($remaining/(60*60*24))."d ".floor(($remaining % 86400)/(60*60))."h ".floor(($remaining % 3600)/60)."m";
		if($remaining > 7*24*60*60)
			$remaining_out='<span style="color: #1ae61a">'.$remaining_out.'</span>';
		else if($remaining > 0*24*60*60)
			$remaining_out='<span style="color: orange">'.$remaining_out.'</span>';
		else $remaining_out='<span style="color: red">'.$remaining_out.'</span>';
		
		$taskentrusted=$taskentrusted.'<tr>
              		<td style="width: 92.1px;">'.$type.'<br>
              		</td>
              		<td style="width: 90px;">'.$row['review_time_requested'].'<br>
              		</td>
              		<td style="width: 90px;">'.date("Y-m-d H:i:s",strtotime($row['review_time_requested']." + ".$addtime)).'<br>
              		</td>
              		<td style="width: 90px;">'.$remaining_out.'<br>
              		</td>
              		<td style="max-width: 30.3px;"><a href="invitation.php?directlogin='.bin2hex($row['review_directlogin']).'"><img title="Write review" src="images/write-review-small.png"></a><br>
              		</td>
            	</tr>';
	}

	//ASSEMBLE NOTIFICATIONS
/*	$notifications="";
	$sql="SELECT notification_id, notification_object, notification_text, notification_time FROM notification WHERE notification_user='".$_SESSION['user']."'
		ORDER BY notification_id DESC LIMIT 3";
	$result=$conn->query($sql);
	if($result->num_rows > 0)
		while($row=$result->fetch_assoc())
		{
			$notifications=$notifications.'<tr>
              		<td style="width: 72.1px;">'.$row['notification_object'].'<br>
              		</td>
              		<td style="width: 125px;"><a href="index.php?notification='.$row['notification_id'].'">'.substr($row['notification_text'],0,30).'...</a><br>
              		</td>
              		<td style="width: 105px;">'.$row['notification_time'].'<br>
              		</td>
            	</tr>';
		}*/
		
	$limit=3;
}
?>
      <div id="centerpage">
      <?php include("includes/notifications_generate.php"); ?>
        <a id="seeall_link" href="index.php?workbench=notifications">See all &gt;&gt;</a>
<?php   if(!empty($taskentrusted)) echo '<h3>Tasks</h3><div class="list" style="height: 210px" id="toplist"><table style="width: 100%">
          <tbody>
            <tr>
              <th style="width: 92.1px;">Type<br>
              </th>
              <th style="width:90px;">Requested<br>
              </th>
              <th style="width:90px;">Due<br>
              </th>
              <th style="width:90px;">Remaining<br>
              </th>
              <th style="width: 35.3px;">Link<br>
              </th>
            </tr>'.$taskentrusted.'</tbody></table></div>';
		else echo "";
?>


	  <h3>Contacted students</h3>
	  <?php if(!empty($dialogue_list)) echo '<table style="width: 100%"><tbody>
            <tr>
              <th style="width: 300px;">Author of<br>
              </th>
              <th style="width:90px;">Last active<br>
              </th>
            </tr>'.$dialogue_list.'</tbody></table></div>';
		else echo '<i>Use dialogue option to contact author of any idea on site.</i>';
      ?>

        </table>
      </div>