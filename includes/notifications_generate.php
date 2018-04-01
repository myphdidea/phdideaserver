<?php
if(isset($_SESSION['user']) || isset($_SESSION['prof']))
{
	if(!isset($limit))
		$limit=32;
	//ASSEMBLE NOTIFICATIONS
	$notifications="";
	if(isset($_SESSION['user']))
	{
		$sql="SELECT notification_id, notification_object, notification_text, notification_time, notification_consulted, notification_urgency FROM notification WHERE notification_user='".$_SESSION['user']."'
			ORDER BY notification_id DESC LIMIT $limit";
		$prefix="notification";
	}
	else
	{
		$sql="SELECT profnotif_id, profnotif_object, profnotif_text, profnotif_time, profnotif_consulted, profnotif_urgency FROM profnotif WHERE profnotif_prof='".$_SESSION['prof']."'
			ORDER BY profnotif_id DESC LIMIT $limit";
		$prefix="profnotif";
	}
	$result=$conn->query($sql);
	if($result->num_rows > 0)
		while($row=$result->fetch_assoc())
		{
			if($row[$prefix.'_consulted']=='1')
				$consulted='style="color: purple"';
			else $consulted='';
			if(strlen($row[$prefix.'_object']) > 17) $object=substr($row[$prefix.'_object'],0,17)."..."; else $object=$row[$prefix.'_object'];
			$notifications=$notifications.'<tr>
              		<td style="width: 80.1px;">'.$object.'<br>
              		</td>
              		<td style="width: 120px;"><a '.$consulted.' href="index.php?workbench=displaynotify&'.$prefix.'='.$row[$prefix.'_id'].'">'.substr($row[$prefix.'_text'],0,23).'...</a><br>
              		</td>
              		<td style="width: 90px;">'.$row[$prefix.'_time'].'<br>
              		</td>
              		<td style="width: 15px;">'.$row[$prefix.'_urgency'].'<br>
              		</td>
            	</tr>';
		}
}
?>
        <h3>Notifications</h3>
        <div style="height: 100px;" class="list">
        <?php
        	if(!empty($notifications))
        		echo '<table style="width: 100%">
        			<tbody class="table_override">
        				<tr style="background-color: white">
              				<th style="width: 80.1px;">Object<br>
              				</th>
              				<th style="width: 120px;">Text<br>
              				</th>
              				<th style="width: 90px;">Time<br>
              				</th>
              				<th style="width: 15px;">Rnk<br>
              				</th>
            			</tr>'.$notifications.'</tbody></table>';
			else echo '<i>Check back for notifications later.</i>';
        ?>
        </div>
