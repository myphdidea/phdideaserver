<?php
$feat_id=$conn->real_escape_string(test($_GET['feat']));

if(!empty($feat_id) && isset($_SESSION['user']) && $conn->query("SELECT 1 FROM feature f JOIN student s ON (f.feature_student=s.student_id)
	WHERE f.feature_id='$feat_id' AND s.student_user_id='".$_SESSION['user']."'")->num_rows > 0)
{
	$sql="SELECT ft.featuretext_title, f.feature_amendments, f.feature_revealrealname
		FROM feature f JOIN featuretext ft ON (f.feature_id=ft.featuretext_feature) WHERE f.feature_id='$feat_id' ORDER BY ft.featuretext_id DESC";
	$row=$conn->query($sql)->fetch_assoc();
	$title=$row['featuretext_title'];
	$amendments=$row['feature_amendments'];
	$revealrealname=$row['feature_revealrealname'];
	if(isset($_POST['submit']))
	{
		if(isset($_POST['amendments'])) $amendments=$conn->real_escape_string(test($_POST['amendments']));
		if(isset($_POST['pubid'])) $pubid=$conn->real_escape_string(test($_POST['pubid'])); else $pubid="";
		
		if($pubid=="pub_pseudonym") $revealrealname=2;
		elseif($pubid=="pub_realname") $revealrealname=1;
		elseif($pubid=="pub_anonym") $revealrealname=0;
		
		$conn->query("UPDATE feature SET feature_amendments='$amendments', feature_revealrealname='$revealrealname' WHERE feature_id='$feat_id'");
		header("Location: index.php?feat=".$feat_id);
	}
	else
	{
		switch($revealrealname)
		{
			case 2:	
				$pubid="pub_pseudonym";
				break;
			case 1:
				$pubid="pub_realname";
				break;
			case 0:
				$pubid="pub_anonym";
				break;
		}
	}
}
else echo "Not the owner of this feature!";
?>

<div id="centerpage">
<form method="post" action="">
<h1>Settings</h1>
<h2>for <?php echo '<a href="index.php?feat='.$feat_id.'">'.$title.'</a>'; ?></h2>
	Privacy:<br>
    <p class="indentation">
      <input value="pub_realname" name="pubid" type="radio" <?php if(!empty($pubid) && $pubid=="pub_realname") echo 'checked'; ?>>Finalize under
      real name<br>
      <input value="pub_pseudonym" name="pubid" type="radio" <?php if(!empty($pubid) && $pubid=="pub_pseudonym") echo 'checked'; ?>>Finalize
      under pseudonym<br>
      <input value="pub_anonym" name="pubid" type="radio" <?php if(!empty($pubid) && $pubid=="pub_anonym") echo 'checked'; ?>>Leave anonymous<br>
    </p>
    Amendments:<br>
    <div class="indentation">
    <textarea name="amendments" style="height: 100px; width: 400px"><?php if(isset($amendments)) echo $amendments; ?></textarea>
    </div>
    <p style="text-align: right;" class="indentation"><button name="submit">Submit</button></p>
</div>
</form>