<?php
if(((isset($_SESSION['user']) && isset($_GET['notification'])) || (isset($_SESSION['prof']) && isset($_GET['profnotif']))) )
{
	if(isset($_SESSION['user']))
		$prefix='notification';
	else $prefix='profnotif';
	$notification_id=test($_GET[$prefix]);
	if(isset($_SESSION['user']))
		$sql="SELECT notification_object, notification_text, notification_time, notification_consulted FROM notification
			WHERE notification_user='".$_SESSION['user']."' AND notification_id='$notification_id'";
	elseif(isset($_SESSION['prof']))
		$sql="SELECT profnotif_object, profnotif_text, profnotif_time, profnotif_consulted FROM profnotif
			WHERE profnotif_prof='".$_SESSION['prof']."' AND profnotif_id='$notification_id'";
	$row=$conn->query($sql)->fetch_assoc();
	if(!empty($row))
	{
		if(isset($_SESSION['isstudent']))
		{
			$sql="SELECT student_givenname FROM student WHERE student_user_id='".$_SESSION['user']."'";
			$row2=$conn->query($sql)->fetch_assoc();
			$address="Dear ".$row2['student_givenname'].",<br><br>";
		}
		elseif(isset($_SESSION['prof']))
		{
			$sql="SELECT prof_familyname FROM prof WHERE prof_id='".$_SESSION['prof']."'";
			$row2=$conn->query($sql)->fetch_assoc();
			$address="Dear Prof. ".$row2['prof_familyname'].",<br><br>";
		}
		else $adress='';
		if($row[$prefix.'_consulted']!='1')
		{
			if(isset($_SESSION['user']))
				$sql="UPDATE notification SET notification_consulted='1' WHERE notification_id='$notification_id'";
			elseif(isset($_SESSION['prof']))
				$sql="UPDATE profnotif SET profnotif_consulted='1' WHERE profnotif_id='$notification_id'";
			$conn->query($sql);
		}
		echo '<div id="centerpage"><h2>Notification</h2>';
		echo "<b>Time: </b> ".$row[$prefix.'_time']."<br>";
		echo "<b>Object: </b> ".$row[$prefix.'_object']."<br>";
		echo '<b>Text: </b> <div class="indentation" style="word-wrap: break-word">'.$address.$row[$prefix.'_text']."<br><br>The myphdidea team</div><br>";
		echo '</div>';
	}
	else echo "Could not find notification!";
}
else echo "Please login and select a notification!";
?>