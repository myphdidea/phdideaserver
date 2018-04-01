<?php

//fetch data from student
$sql = "SELECT s.student_pts_cmpgn-s.student_pts_cmpgn_consmd AS cmpgn_pts_total,
			   s.student_pts_feat,s.student_cmpgn_own_latest, s.student_cmpgn_shadowed_latest,
			   s.student_givenname,s.student_familyname,s.student_selfdescription,
			   s.student_image,s.student_socialmedia_link, s.student_pts_cmpgn,
			   s.student_feat_own_latest, s.student_feat_shadowed_latest,
			   u.user_pts_misc, u.user_pts_fail, u.user_pseudonym, u.user_rank
			   FROM user u JOIN student s ON ( u.user_id=s.student_user_id )
			   WHERE (u.user_id LIKE '".$conn->real_escape_string($_SESSION['user'])."')";
$result = $conn->query($sql);
$row=$result->fetch_assoc();
$cmpgn_pts=$row['cmpgn_pts_total'];
$feat_pts=$row['student_pts_feat'];
$misc_pts=$row['user_pts_misc'];
$fail_pts=$row['user_pts_fail'];
$cmpgn_own=$row['student_cmpgn_own_latest'];
$cmpgn_shdw=$row['student_cmpgn_shadowed_latest'];
$feat_own=$row['student_feat_own_latest'];
$feat_shdw=$row['student_feat_shadowed_latest'];
$givenname=$row['student_givenname'];
$familyname=$row['student_familyname'];
$selfdescription=$row['student_selfdescription'];
$hasimage=$row['student_image'];
$pseudonym=$row['user_pseudonym'];
$socialmedialink=$row['student_socialmedia_link'];
$user_rank=$row['user_rank'];
$pos_rank_pts=$row['student_pts_cmpgn'];

if(!empty($cmpgn_shdw))
{
	$row=$conn->query("SELECT m.moderators_first_user, m.moderators_second_user, m.moderators_third_user,
		m.moderators_time_joined1, m.moderators_time_joined2, m.moderators_time_joined3 FROM cmpgn c
		JOIN moderators m ON (m.moderators_group=c.cmpgn_moderators_group)
		WHERE c.cmpgn_id='".$cmpgn_shdw."' ORDER BY moderators_id DESC")->fetch_assoc();
	switch($_SESSION['user'])
	{
		case $row['moderators_first_user']:
			$time_joined=$row['moderators_time_joined1'];
			break;
		case $row['moderators_second_user']:
			$time_joined=$row['moderators_time_joined2'];
			break;
		case $row['moderators_third_user']:
			$time_joined=$row['moderators_time_joined3'];
			break;
	}
	$idea_pts_suppl=2*floor((strtotime("now")-strtotime($time_joined))/(6*7*24*60*60));
	$cmpgn_pts+=$idea_pts_suppl;
}
else $idea_pts_suppl=0;
?>
      <div class="leftmargin">
        <div id="workdesk_controls">

          <div id="profile_icon" style="float: left; width: 40px; height: 40px; padding-right: 10px">
          <?php
          	if($hasimage) $image_path="user_data/profile_pictures/".$_SESSION['user'].".png";
			else $image_path="images/default.png";
			$image_path='<img alt="" src="'.$image_path.'">';
			if(!empty($socialmedialink))
          		echo '<a href="index.php?page=redirect&link='.$socialmedialink.'">'.$image_path.'</a>';
			else echo $image_path;
          ?></div>
          <div id="name_displayed" style="float: right; width: 150px">
          	<?php /*if(!empty($socialmedialink))
          			echo '<a href="index.php?redirect='.$socialmedialink.'">'.$givenname.' '.$familyname.'</a>';
				else*/ echo $givenname.' '.$familyname;?>,<br>
            <?php if(!empty($selfdescription)) echo $selfdescription.",<br>";?>
            <i>alias</i> "<?php echo $pseudonym?>"</div>
          <div style="text-align: center; clear: both"> <span id="rankfield">Rank:
          		<?php if($user_rank==1) echo "Disgraced";
          			elseif($user_rank==2) echo "Serial Offender";
          			elseif($user_rank==0 && $pos_rank_pts >= 12) echo "Senpai";
          			elseif($user_rank==0 && $conn->query("SELECT 1 FROM student WHERE NOW() < student_time_created + INTERVAL 6 MONTH AND student_backuponly='1' AND student_user_id='".$_SESSION['user']."'")->num_rows > 0) echo "Apprentice";
					elseif($_SESSION['user']==1) echo "Superuser";
          			else echo "Student";
          		?>
            </span></div>
          <ul id="userscore" class="dashboard" title="Points scored by completing (or failing) tasks">
            <li style="color: #1ae61a"><?php echo $cmpgn_pts; ?></li>
            <li style="color: #1ae61a"><?php echo $feat_pts; ?></li>
            <li style="color: #1ae61a"><?php echo $misc_pts; ?></li>
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

	$remaining_prev=8640000;
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
					$remaining_color='#1ae61a';
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
          <br>
          <div class="workdesk_tab widgets"> <a href="index.php?workbench=activetasks">Active Tasks</a></div>
          <div class="workdesk_tab widgets"> <a href="index.php?workbench=history">History</a></div>
          <div class="workdesk_tab widgets"> <?php if(!empty($cmpgn_own))
          												echo '<a href="index.php?cmpgn='.$cmpgn_own.'">My Campaign</a>';
          										   else echo '<a href="index.php?workbench=newcmpgn">New Campaign</a>';?></div>
          <div class="workdesk_tab widgets"><?php if(!empty($feat_own))
          												echo '<a href="index.php?feat='.$feat_own.'">My Feature</a>';
          										   else echo '<a href="index.php?workbench=newfeat">New Feature</a>';?></div>
          <div class="workdesk_tab widgets <?php if(empty($cmpgn_shdw)) echo 'workdesk_tab_inactive'?>">
            <?php if(!empty($cmpgn_shdw)) { if($conn->query("SELECT 1 FROM cmpgn WHERE cmpgn_visibility_blocked='1' AND cmpgn_id='$cmpgn_shdw'")->num_rows > 0)
												echo '<a href="index.php?workbench=resign&cmpgn='.$cmpgn_shdw.'">';
											else echo '<a href="index.php?cmpgn='.$cmpgn_shdw.'">'; } ?>Shadowed Campaign<?php if(!empty($cmpgn_shdw)) echo '</a>';?></div>
          <div class="workdesk_tab widgets <?php if(empty($feat_shdw)) echo 'workdesk_tab_inactive'?>">
          	<?php if(!empty($feat_shdw)) echo '<a href="index.php?feat='.$feat_shdw.'">'; ?>Shadowed Feature<?php if(!empty($feat_shdw)) echo '</a>';?></div>
          <div class="workdesk_tab widgets"> <a href="index.php?workbench=profile">Profile</a></div>
          <div class="workdesk_tab widgets"> <a href="index.php?workbench=settings">Settings</a></div>
          <div class="workdesk_tab widgets"> <a href="logout.php">Logout</a></div>
          <br>
        </div>
        <?php
        	$row=$conn->query("SELECT poll_id, poll_question, poll_ratebox FROM poll ORDER BY poll_id DESC LIMIT 1")->fetch_assoc();
			$poll_id=$row['poll_id'];
			$poll_question=$row['poll_question'];
			
			$rate_result="";
			if($conn->query("SELECT 1 FROM rating r JOIN student s ON (r.rating_student=s.student_id) WHERE r.rating_ratebox='".$row['poll_ratebox']."' AND s.student_user_id='".$_SESSION['user']."'")->num_rows > 0)
			{
				$tot_nb=$conn->query("SELECT 1 FROM rating WHERE rating_ratebox='".$row['poll_ratebox']."'")->num_rows;
				$result2=$conn->query("SELECT rating_value, COUNT(*) AS rate_nb FROM rating WHERE rating_ratebox='".$row['poll_ratebox']."' GROUP BY rating_value ORDER BY rating_value");
				while($row2=$result2->fetch_assoc())
					$rate_result=$rate_result.$row2['rating_value'].": ".round((100*$row2['rate_nb'])/$tot_nb,2)."% ";
				$rate_result='<br>'.$rate_result;
			}
			elseif(isset($_POST['poll_submit']) && !empty($_POST['poll']))
			{
				$result3=$conn->query("SELECT pollanswer_id, pollanswer_text FROM pollanswer WHERE pollanswer_poll='$poll_id'");
				if($result3->num_rows > 0)
				{
					$i=0;
					while($row3=$result3->fetch_assoc())
					{
						if($row3['pollanswer_id']==$conn->real_escape_string(test($_POST['poll'])))
							break;
						$i++;
					}
					$conn->query("INSERT INTO rating (rating_ratebox, rating_student, rating_value, rating_timestamp) SELECT '".$row['poll_ratebox']."',student_id,'$i',NOW() FROM student WHERE student_user_id='".$_SESSION['user']."'");
				}
				header("Location: index.php");
			}
        ?>
        <div class="widgets" style="border-color: red">
          <form method="post" action="">
          <div style="text-align: center; padding-top: 15px; padding-bottom: 15px">
            <b>Poll</b></div>
          <?php echo $poll_question; if(!empty($rate_result)) echo '<br>'.$rate_result; ?> <br>
		  <div style="margin-top: 10px; margin-bottom: 20px; padding-left: 20px; padding-right: 20px">
          <?php 
          	if(!empty($rate_result))
				$disable_rate="disabled";
			else $disable_rate="";
          	$result3=$conn->query("SELECT pollanswer_id, pollanswer_text FROM pollanswer WHERE pollanswer_poll='$poll_id'");
			if($result3->num_rows==0)
			{
				echo '<span style="margin-left: 0px"><i>None at the moment.</i></span><br><br><br>';
				$disable_rate="disabled";
			}
			else while($row3=$result3->fetch_assoc())
          		echo '<input value="'.$row3['pollanswer_id'].'" name="poll" type="radio" '.$disable_rate.' >'.$row3['pollanswer_text'].'<br>';
          ?>
          </div>
          <button style="display: block; margin: 0px auto; margin-top: 5px; margin-bottom: 5px; float: center;"
            name="poll_submit" <?php echo $disable_rate; ?> >Submit</button>
          </form>
          </div>
      </div>