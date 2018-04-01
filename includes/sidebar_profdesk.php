      <div class="leftmargin">
        <div id="workdesk_controls">
<!--
          <div id="profile_icon" style="float: left; width: 40px; height: 40px; padding-right: 10px">
          <?php
          	if($hasimage) $image_path="user_data/profile_pictures/".$_SESSION['user'].".png";
			else $image_path="images/default.png";
			$image_path='<img alt="" src="'.$image_path.'">';
			if(!empty($socialmedialink))
          		echo '<a href="index.php?redirect='.$socialmedialink.'">'.$image_path.'</a>';
			else echo $image_path;
          ?></div>
          <div id="name_displayed" style="float: right; width: 150px">
          	<?php /*if(!empty($socialmedialink))
          			echo '<a href="index.php?redirect='.$socialmedialink.'">'.$givenname.' '.$familyname.'</a>';
				else*/ echo $givenname.' '.$familyname;?>,<br>
            <?php if(!empty($selfdescription)) echo $selfdescription.",<br>";?>
            <i>alias</i> "<?php echo $pseudonym?>"</div>
          <div style="text-align: center; clear: both"> <span id="rankfield">Rank: Student
            </span></div>
          <ul id="userscore" class="dashboard" title="Points scored by completing (or failing) tasks">
            <li style="color: lime"><?php echo $cmpgn_pts; ?></li>
            <li style="color: lime"><?php echo $feat_pts; ?></li>
            <li style="color: lime"><?php echo $misc_pts; ?></li>
            <li style="color: red"><?php echo $fail_pts; ?></li>
          </ul>
          <ul id="caption_userscore" class="dashboard">
            <li>Ideas</li>
            <li>Features</li>
            <li>Misc</li>
            <li>Fail</li>
          </ul>
<?php
   	$taskentrusted="";
	$sql="SELECT t.taskentrusted_timestamp, t.taskentrusted_urgency FROM taskentrusted t
		JOIN student s ON (t.taskentrusted_to=s.student_id)
		WHERE t.taskentrusted_completed IS NULL AND s.student_user_id='".$_SESSION['user']."' ORDER BY t.taskentrusted_timestamp DESC";
	$result=$conn->query($sql);

	$remaining_prev=864000;
	if($result->num_rows > 0)
	{
		while($row=$result->fetch_assoc())
		{
			switch($row['taskentrusted_urgency'])
			{
				case '1':
					$addtime="7 days"; break;
				case '2':
					$addtime="3 days"; break;
				case '3':
					$addtime="24 hours"; break;
				case '4':
					$addtime="12 hours"; break;
				case '5':
					$addtime="3 hours"; break;
			}
			$remaining=strtotime($row['taskentrusted_timestamp']."+".$addtime)-strtotime("now");
			if($remaining < $remaining_prev)
			{
				$remaining_out="<li>".floor($remaining/(60*60*24))."d</li><li>".floor(($remaining % 86400)/(60*60))."h</li><li>".floor(($remaining % 3600)/60)."m</li>";
				if($remaining > 2*24*60*60)
					$remaining_color='lime';
				else if($remaining > 6*60*60)
					$remaining_color='orange';
				else $remaining_color='red';
				$remaining_prev=$remaining;
			}
		}
		echo 'Next deadline:<br>
          <ul class="dashboard" style="color: '.$remaining_color.'; padding-bottom: 5px">
            <li><br>
            </li>'
			.$remaining_out.
          '</ul>';
	}
	else echo '<br><br><br>';
?>
          <br>-->
          <div class="workdesk_tab widgets"><a href="index.php?profdesk=activities">Activities</a></div>
          <div class="workdesk_tab widgets"><a href="index.php?prof=<?php echo $_SESSION['prof']; ?>">My Profile</a></div>
          <div class="workdesk_tab widgets"> <a href="index.php?profdesk=settings">Settings</a></div>
          <div class="workdesk_tab widgets"> <a href="logout.php">Logout</a></div>
          <br>
        </div>
      </div>