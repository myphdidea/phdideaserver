<?php
$error_msg=$opinion="";
if(empty($_SESSION['isstudent']) || empty($_SESSION['user']))
	echo "Need to be student to comment!<br>";
elseif(isset($_SESSION['user']))
{
	$cmpgn_id=$conn->real_escape_string(test($_GET['cmpgn']));
	
	$sql="SET @version=0;";
	$result=$conn->query($sql);

	$sql="SELECT @version:=@version+1 AS version, u.upload_id, c.cmpgn_title, c.cmpgn_user, c.cmpgn_time_launched,
				 c.cmpgn_time_firstsend, c.cmpgn_time_finalized, c.cmpgn_type_isarchivized, c.cmpgn_moderators_group
				 FROM cmpgn c JOIN upload u ON (c.cmpgn_id=u.upload_cmpgn) WHERE c.cmpgn_id='".$cmpgn_id."' ORDER BY version DESC";
	$row=$conn->query($sql)->fetch_assoc();

	$version=$row['version'];
	$upload_id=$row['upload_id'];
	$cmpgn_title=$row['cmpgn_title'];
	$cmpgn_user=$row['cmpgn_user'];
	$time_launched=$row['cmpgn_time_launched'];
	$time_firstsend=$row['cmpgn_time_firstsend'];
	$time_finalized=$row['cmpgn_time_finalized'];
	$isarchivized=$row['cmpgn_type_isarchivized'];
	$moderators_group=$row['cmpgn_moderators_group'];

	if($cmpgn_user==$_SESSION['user'])
		$error_msg=$error_msg."Can't comment on own campaign!<br>";
	if($isarchivized==TRUE)
		$error_msg=$error_msg."Can't comment on archivized campaign!<br>";
	if(!empty($time_finalized))
		$error_msg=$error_msg."Can't comment on finalized campaign!<br>";

	if(empty($error_msg) && isset($_POST['submit']))
	{
		$opinion=$conn->real_escape_string(test($_POST['Opinion']));
		if(!empty($_POST['comment_revealname'])) $comment_revealname=$conn->real_escape_string(test($_POST['comment_revealname']));
		
		if(empty($_POST['Opinion']) || empty($_POST['comment_revealname']))
			$error_msg=$error_msg."Can't have empty fields!<br>";
		if(strlen($opinion) > 4000)
			$error_msg=$error_msg."Comment too long please moderate yourself!";
		if($conn->query("SELECT 1 FROM watchlist w
			JOIN moderators m ON (w.watchlist_moderators=m.moderators_id)
			WHERE m.moderators_group='$moderators_group' AND w.watchlist_enrolled='0' AND w.watchlist_user='".$_SESSION['user']."'")->num_rows > 0)
			if(!isset($_POST['confirm_watchlist']))
				$error_msg=$error_msg.'Need to forego job proposal before you can comment, really OK? <input type="checkbox" name="confirm_watchlist"><br>';
			else $conn->query("UPDATE watchlist w
				JOIN moderators m ON (w.watchlist_moderators=m.moderators_id)
				SET w.watchlist_enrolled='1'
				WHERE m.moderators_group='$moderators_group' AND w.watchlist_enrolled='0' AND w.watchlist_user='".$_SESSION['user']."'");

		$sql="SELECT 1 FROM moderators m, student s WHERE m.moderators_group='".$moderators_group."' AND s.student_user_id='".$_SESSION['user']."' AND ((m.moderators_first_user=s.student_user_id) OR (m.moderators_second_user=s.student_user_id) OR (m.moderators_first_user=s.student_user_id))";
		$ismoderator=$conn->query($sql)->num_rows;
		if(!empty($ismoderator))
			$error_msg=$error_msg."Can't comment on shadowed campaign!<br>";
		
		$sql="SELECT c.comment_accepted, c.comment_revealrealname FROM comment c JOIN student s ON (c.comment_student=s.student_id) WHERE s.student_user_id='".$_SESSION['user']."' ORDER BY c.comment_accepted DESC";
		$result=$conn->query($sql);
		if($result->num_rows > 0 && empty($error_msg))
		{
			$row=$result->fetch_assoc();
			if(!isset($row['comment_accepted']) && $result->num_rows > 0)
				$error_msg=$error_msg."Please await decision before resubmitting comment!<br>";
			if($result->num_rows > 1 || $row['comment_accepted']==TRUE)
				$error_msg=$error_msg."No more than 1 comment per person!<br>";
			if(($row['comment_revealrealname']==0 && $comment_revealname=="realname")
				|| ($row['comment_revealrealname']==1 && $comment_revealname=="pseudonym"))
				$error_msg=$error_msg."Choose same privacy status as previously please!<br>";
		}

		if(empty($error_msg))
		{
			$sql="SELECT student_id FROM student WHERE student_user_id='".$_SESSION['user']."'";
			$row=$conn->query($sql)->fetch_assoc();
			$student_id=$row['student_id'];
			
			if($comment_revealname=="realname")
				$comment_revealrealname=1;
			else $comment_revealrealname=0;
			$sql="INSERT INTO comment (comment_upload, comment_student, comment_text, comment_time_proposed, comment_revealrealname)
							  VALUES ('$upload_id','$student_id','$opinion',NOW(),'$comment_revealrealname')";
			if(!$conn->query($sql)) echo 'Comment insert failed!';
			send_notification($conn,$cmpgn_user,3,'New comment','A new comment by another student is available on your campaign page!','','');
			
			header("Location: index.php?confirm=newcomment");
		}
	}
	
//	$conn->close();
}
?>
<form method="post" action="">
      <div id="centerpage">
        <h1>New comment</h1>
        <h2>on "<?php echo $cmpgn_title.' (v.'.$version.')'; ?>"</h2>
        (<?php  if(empty($time_firstsend) && !empty($time_finalized))
						$launched="approval not obtained";
					  elseif(empty($time_firstsend))
        				$launched="not yet approved";
					  else $launched="approved ".$time_firstsend;
        		echo "Created ".$time_launched.", ".$launched
        			  	.", "; if($isarchivized) echo "archivized";
        					 else if(empty($time_finalized)) echo "still running";
        					 else echo "finalized ".$time_finalized;?>)<br>
        <?php if(!empty($error_msg)) echo '<br><div style="color: red; text-align: center">'.$error_msg.'</div>';?>
        <p>Students interact with campaigns mainly through assignment as moderators, but they can also comment on other students' ideas. However, only 1
           comment per student is allowed, and revisions are not possible! Student opinions are graded
           on a pass/fail basis by the campaign owner, and failed comments may be resubmitted once with corrections.
           If successful, they become visible to other students who have sucessfully submitted or will submit opinions,
           as well as professors who have submitted reviews. All successful submissions become public upon campaign termination.</p>
           Note: By commenting, you waive any present job proposals for this campaign.
        <p class="indentation"><div style="text-align: center"><textarea style="width: 400px; height: 200px;"

class="indentation" name="Opinion"><?php if(!empty($opinion)) echo $opinion; ?></textarea></div>
		<p>You can choose submission under either your real name or your pseudonym:</p>

        <p class="indentation"> <input value="realname" name="comment_revealname" type="radio"
        	<?php if(!empty($comment_revealname) && $comment_revealname=="realname") echo 'checked';?>>Real name<br>
          <input value="pseudonym" name="comment_revealname" type="radio"
            <?php if(!empty($comment_revealname) && $comment_revealname=="pseudonym") echo 'checked';?>>Pseudonym<br>
        </p>

        <p style="text-align: right;"> <button name="submit">Submit</button></p>
      </div>
</form>