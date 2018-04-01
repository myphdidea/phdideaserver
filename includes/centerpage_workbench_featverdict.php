<?php
$verdict_id=$conn->real_escape_string(test($_GET['verdict']));

$error_msg="";
$sql="SELECT s.student_user_id, ft.featuretext_title, ft.featuretext_text, f.feature_id
	FROM featuretext ft JOIN feature f ON (ft.featuretext_feature=f.feature_id)
	JOIN student s ON (f.feature_student=s.student_id)
	WHERE ft.featuretext_verdict='$verdict_id' ORDER BY ft.featuretext_timestamp DESC";
$row2=$conn->query($sql)->fetch_assoc();
$owner=$row2['student_user_id'];
$feat_id=$row2['feature_id'];
$feat_title=$row2['featuretext_title'];
$featuretext=$row2['featuretext_text'];
$verdict_on='<a href="index.php?feat='.$row2['feature_id'].'">'.$row2['featuretext_title'].'</a>';

if(isset($_SESSION['user']))
{
	$sql="SELECT v.verdict_task, v.verdict_moderators, m.moderators_first_user, m.moderators_second_user, m.moderators_third_user
		FROM moderators m JOIN verdict v ON (m.moderators_id=v.verdict_moderators)
		WHERE (m.moderators_first_user='".$_SESSION['user']."' OR m.moderators_second_user='".$_SESSION['user']."' OR m.moderators_third_user='".$_SESSION['user']."')
		AND v.verdict_type='FTR' AND v.verdict_id='$verdict_id'";
	$result=$conn->query($sql);
	if($result->num_rows > 0)
	{
		$row=$result->fetch_assoc();
		$verdict_task=$row['verdict_task'];
		$verdict_moderators=$row['verdict_moderators'];
		$moderators_first_user=$row['moderators_first_user'];
		$moderators_second_user=$row['moderators_second_user'];
		$moderators_third_user=$row['moderators_third_user'];

		$sql="SELECT t.taskentrusted_timestamp, taskentrusted_completed, s.student_id FROM taskentrusted t JOIN student s
			  ON (t.taskentrusted_to=s.student_id) WHERE s.student_user_id='".$_SESSION['user']."' AND t.taskentrusted_task='$verdict_task'";
		$row=$conn->query($sql)->fetch_assoc();
		$student_id=$row['student_id'];
		$time_due=date("Y-m-d H:i:s",strtotime($row['taskentrusted_timestamp']." + 7 days"));
		if(strtotime('now') > strtotime($row['taskentrusted_timestamp']." + 7 days"))
			$error_msg=$error_msg."Ouch verdict time is now past!<br>";
		elseif($row['taskentrusted_completed']=='')
		{
			if(isset($_POST['submit']) && empty($error_msg))
			{
				if($conn->real_escape_string(test($_POST['appr_or_decl']))=="none")
					$error_msg=$error_msg."Please either accept or decline!<br>";
				else
					include("includes/render_verdict.php");
			}
		}
		elseif($row['taskentrusted_completed']=='1') $error_msg=$error_msg."Already completed this task!<br>";
		else $error_msg=$error_msg."Too late to render judgment now!<br>";
	}
	else $error_msg=$error_msg."Not a moderator for this verdict!<br>";
}
?>
<div id="centerpage">
<h1>New verdict</h1>
<h2>on <?php echo $verdict_on; ?></h2>
<?php if(isset($error_msg)) echo '<div style="color: red; text-align: center">'.$error_msg.'</div><br>';?>
Please read through the following feature article:
<?php echo html_entity_decode(stripcslashes($featuretext));?>
Now check for compatibility of the feature text with our editorial guidelines:
	<ul>
		<li>The feature is concerned with higher education or research to at least 50 %</li>
		<li>Its material does not derive to more than 50 % from any single project idea proposed by the author or someone else
		(chronicles going beyond pitching the idea and dealing with its practical realization are OK though)</li>
		<li>The article is intelligible to a non-specialist audience</li>
		<li>Where a question is mooted or a position taken, arguments are furnished</li>
		<li>The material is presented with a certain level of care, and in (correct) English</li>
		<li>No obviously misplaced or offensive content</li>
	</ul>
       	<form method="post" action="">
        <p>Approve or decline  publication?
          <select name="appr_or_decl" style="width: 200px">
            <option value="none">--</option>
            <option value="appr">Approve</option>
            <option value="decl">Decline (Reason: Not concerned with research or higher education)</option>
            <option value="decl">Decline (Reason: Just a running commentary on a project idea)</option>
            <option value="decl">Decline (Reason: Not intelligible without very specific background)</option>
            <option value="decl">Decline (Reason: Opinions but no arguments)</option>
            <option value="decl">Decline (Reason: Substandard presentation)</option>
            <option value="decl">Decline (Reason: Misplaced or offensive content)</option>
          </select>
          <button name="submit">OK</button> 
          </p></form>

</div>
