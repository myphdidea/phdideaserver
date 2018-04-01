<?php
include("includes/header_functions.php");
$directlogin=hex2bin(test($_GET['directlogin']));
$directlogin=$conn->real_escape_string($directlogin);
/*$directlogin=$conn->real_escape_string(test($_GET['directlogin']));
$directlogin=hex2bin($directlogin);*/
$sql1="SELECT c.cmpgn_id, c.cmpgn_showproftitle, c.cmpgn_showprofabstr, c.cmpgn_showprofthmbnails, c.cmpgn_revealtoprof,
	u.upload_id, u.upload_abstract_text, c.cmpgn_title, c.cmpgn_user, r.review_prof, r.review_agreed,
	r.review_time_requested, r.review_time_submit, r.review_time_tgth_passedon, r.review_time_aborted,
	r.review_id, r.review_send, p.prof_orcid, p.prof_familyname
	FROM cmpgn c JOIN upload u ON (u.upload_cmpgn=c.cmpgn_id)
	JOIN review r ON (r.review_upload=u.upload_id) JOIN prof p ON (p.prof_id=r.review_prof)
	WHERE r.review_directlogin='$directlogin'";
$row=$conn->query($sql1)->fetch_assoc();
if(!empty($row['review_send']))
	$review_send=$row['review_send'];
else
{
	$sql="SELECT review_send FROM review WHERE review_upload='".$row['upload_id']."' AND review_together_with='".$row['review_prof']."'";
	$row2=$conn->query($sql)->fetch_assoc();
	$review_send=$row2['review_send'];
}

if(isset($_POST['topdfs']) && isset($_POST['agreed']))
{
	$sql="UPDATE review SET review_agreed='1', review_sentreminder='0' WHERE review_id='".$row['review_id']."'";
	$conn->query($sql);
	send_notification($conn, $row['cmpgn_user'], 3, 'Review agreed!', 'Prof. '.$row['prof_familyname'].' has now accepted your review request!', '', '');
	header("Location: index.php?cmpgn=".$row['cmpgn_id']);
}

//DIRECT LOGIN?
$rvw_isfinished=(!empty($row['review_time_submit']) || !empty($row['review_time_tgth_passedon']) || !empty($row['review_time_aborted']));
if($rvw_isfinished && !empty($row['prof_orcid']))
	//REVIEW FINISHED AND HAS ORCID -> NO NEED FOR DIRECT LOGIN
	header("Location: index.php");
else
{
	session_start();
	unset($_SESSION["user"]);
	unset($_SESSION["isstudent"]);
	$_SESSION['prof']=$row['review_prof'];
}

//REDIRECT TO CAMPAIGN PAGE
if($row['review_agreed']=='1' || $rvw_isfinished)
{
	$conn->close();
	header("Location: index.php?cmpgn=".$row['cmpgn_id']);
}
else {
	if(is_null($row['review_agreed']))
	{
		$sql="UPDATE review SET review_agreed='0' WHERE review_id='".$row['review_id']."'";
		$conn->query($sql);
		$conn->query("UPDATE review SET review_sentreminder='0' WHERE NOW() < review_time_requested + INTERVAL 2 WEEK AND review_id='".$row['review_id']."'");
		send_notification($conn, $row['cmpgn_user'], 3, 'Offer consulted!', 'Prof. '.$row['prof_familyname'].' just consulted your review request (but has not accepted yet)!', '', '');
	}

	if($row['cmpgn_revealtoprof']=='1')
	{
		$sql="SELECT student_givenname, student_familyname, student_selfdescription	FROM student WHERE student_user_id='".$row['cmpgn_user']."'";
		$row2=$conn->query($sql)->fetch_assoc();
		$student_revealname=$row2['student_givenname']." ".$row2['student_familyname'];
		if(!empty($row2['student_selfdescription'])) $row2['student_selfdescription']=", ".$row2['student_selfdescription'];
		$student_revealname_short=$row2['student_givenname'];
	}
	else $student_revealname="";

	$conn->close();
}
?>
<!DOCTYPE html>
<html>
  <head>
    <meta content="text/html; charset=utf-8" http-equiv="content-type">
    <title>home</title>
    <link href="phdideastyle.css?"

      rel="stylesheet" type="text/css">
  </head>
  <body style="clear: none;">
  <div id="header" style="height: 80px; width: 740px"><img class="leftmargin" style="width: 130px" title="Our logo" alt="logo_small" src="images/logo_small_muted.png"></div>
  <div id="maincontainer" style="width: 740px">
  	<div class="leftmargin" style="min-width: 130px; max-width: 130px"></div>
  	<div id="centerpage" style="padding-left: 170px">
  		<h1>Review request</h1>
  		<?php
  			if($row['cmpgn_showproftitle']=='1')
  				echo '<h2>on "'.$row['cmpgn_title'].'"</h2>';
  		?>
		<?php if(isset($_POST['topdfs']) && !isset($_POST['agreed'])) echo '<div style="color: red; text-align: center">Please agree to submitting a review!</div><br>';?>
  		Welcome to <i>myphdidea.org</i>, Prof. <?php echo $row['prof_familyname'];?>! We are a website that publishes student PhD ideas and manages interactions with professors. <?php if(!empty($student_revealname)) echo $student_revealname; else echo "A student";?> has chosen
  		our service to share his idea for a research project <?php if($row['cmpgn_showproftitle']=='1') echo '"'.$row['cmpgn_title'].'" '; ?>with you. <?php if($row['cmpgn_showprofabstr']=='1' || $row['cmpgn_showprofthmbnails']=='1') echo "Please have a look at the following excerpt:"; else echo '<br>'; ?><br>
  		<?php if($row['cmpgn_showprofabstr']=='1') echo '<br><div class="upload" style="width: 65%; font-size: small; margin-left: 80px">'.$row['upload_abstract_text'].'</div><br>'; ?>
  		<?php if($row['cmpgn_showprofthmbnails']=='1' && file_exists("user_data/tmp/send".$review_send."_1.png"))
  			 	echo '<img src="user_data/tmp/send'.$review_send.'_1.png" id="page2_thmb" alt="Thumbnail1">
  				    <img src="user_data/tmp/send'.$review_send.'_2.png" id="page3_thmb" alt="Thumbnail2">
  				    <img src="user_data/tmp/send'.$review_send.'_3.png" id="page4_thmb" alt="Thumbnail3"><br><br>';?>
  		The details of the idea have been documented in pdf files hosted on our site, already whetted in a prior "student peer review" process. In case you are curious about this idea and willing to help, we would like you to submit a short review (~ 1500 characters) on the topic, to be published alongside the original material.
  		Besides a chance to showcase your critical acumen and dedication to open science, subject to community approval of your contribution, you can also improve your profile reputation score and get early access to other reviews of the proposal published by your colleagues<?php if(!empty($row['review_send'])) echo ' (in case you have
  		second thoughts after accepting, with the consent of the student, you can still engage one of your peers to substitute for you)'?>.<br><br>
  		
  		You are guaranteed at least 2 weeks i.e. until <?php echo date("d-m-Y (H:i)",strtotime($row['review_time_requested']."+2 weeks")); ?> to make up your mind whether you want to contribute a review. In the affirmative case, you are guaranteed at least another 4 weeks (for a total of 6 weeks) i.e. until 
  		<?php echo date("d-m-Y",strtotime($row['review_time_requested']."+6 weeks")); ?> to finish your review, which should be enough time to arrange e.g. a Skype interview (beyond the guaranteed time, extension is at the discretion of the student). <?php if(!empty($student_revealname_short)) echo $student_revealname_short; else echo "The student"; ?> will not be allowed to contact any other
  		researchers during this period.<br>
  		<form method="post" action="">
  		<div class="indentation"><input type="checkbox" name="agreed">Yes, I agree to contribute a short review (~ 1500 characters) of this research project idea for the site.
          <p style="text-align: right;"> <button name="topdfs">Take me to the pdfs!</button>
          </p>
  		</div>
  		</form>
  	</div>
  </div>
  <div id="footer" style="width: 740px">
      <div style="text-align: center; font-size: small"><a href="https://creativecommons.org/licenses/by-sa/4.0/">CC BY SA 4.0</a> </div>
  </div>
  </body>
</html>