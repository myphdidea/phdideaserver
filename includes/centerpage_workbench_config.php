<?php
include("includes/cmpgn_header.php");
$error_msg="";
if($visitor_isowner)
{
	$showprofabstr=$showproftitle=$showprofthmbnails=$displayinfeed=$issearchable="";
	$row=$conn->query("SELECT cmpgn_showprofabstr, cmpgn_showproftitle, cmpgn_showprofthmbnails, cmpgn_displayinfeed,
		cmpgn_issearchable, cmpgn_blanknames, cmpgn_type_isarchivized FROM cmpgn WHERE cmpgn_id='$cmpgn_id'")->fetch_assoc();
	$showprofabstr=$row['cmpgn_showprofabstr'];
	$showproftitle=$row['cmpgn_showproftitle'];
	$showprofthmbnails=$row['cmpgn_showprofthmbnails'];
	$displayinfeed=$row['cmpgn_displayinfeed'];
	$issearchable=$row['cmpgn_issearchable'];
	$c_blanknames=$blanknames=$row['cmpgn_blanknames'];
	$isarchivized=$row['cmpgn_type_isarchivized'];
	
	$locktitle=$conn->query("SELECT 1 FROM send s JOIN upload u ON (s.send_upload=u.upload_id) WHERE u.upload_cmpgn='$cmpgn_id'")->num_rows;
	if($isarchivized) $locktitle=TRUE;

	if(isset($_POST['submit']) || isset($_POST['terminate']))
	{
		if(isset($_POST['title'])) $title=test($_POST['title']);
		if(isset($_POST['cmpgn_extlink'])) $cmpgn_extlink=test($_POST['cmpgn_extlink']);
		if(isset($_POST['cmpgn_intlink'])) $cmpgn_intlink=test($_POST['cmpgn_intlink']);
		if(isset($_POST['pubid'])) $pubid=test($_POST['pubid']); else $pubid="";

		$title=$conn->real_escape_string($title);
		if(!empty($_POST['blanknames'])) $c_blanknames=true; elseif(!empty($blanknames) && (empty($time_finalized) /*|| $isarchivized*/)) $c_blanknames=false;
		if(!empty($_POST['issearchable'])) $issearchable=true; else $issearchable=false;
		if(!empty($_POST['displayinfeed'])) $displayinfeed=true; elseif(empty($time_firstsend) && empty($time_finalized)) $displayinfeed=false;
		if(!empty($_POST['showprofabstr'])) $showprofabstr=true; elseif(empty($time_finalized)) $showprofabstr=false;
		if(!empty($_POST['showproftitle'])) $showproftitle=true; elseif(empty($time_finalized)) $showproftitle=false;
		if(!empty($_POST['showprofthmbnails'])) $showprofthmbnails=true; elseif(empty($time_finalized)) $showprofthmbnails=false;
		if(!empty($_POST['revealtoprof'])) $revealtoprof=true; else $revealtoprof=false;
		if($pubid=="pub_pseudonym") $revealrealname=2;
		elseif($pubid=="pub_realname") $revealrealname=1;
		elseif($pubid=="pub_anonym") $revealrealname=0;

		if(isset($_POST['submit']))
		{			
			if(empty($title))
				$error_msg=$error_msg."Cannot have empty title or abstract!<br>";
			elseif (strlen($title) > 200)
				$error_msg=$error_msg."Title too long please control yourself!<br>";
			if (!empty($cmpgn_extlink) && !preg_match("/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i",$cmpgn_extlink))
				$error_msg=$error_msg.'Filled out external link field but not a valid URL.<br>';
			if(!empty($cmpgn_intlink) && (!ctype_digit($cmpgn_intlink) || $conn->query("SELECT 1 FROM cmpgn WHERE cmpgn_id='".$conn->real_escape_string($cmpgn_intlink)."'")->num_rows==0))
				$error_msg=$error_msg.'Filled out internal link field but not a valid campaign ID.<br>';
			elseif(!empty($cmpgn_intlink) && $conn->query("SELECT 1 FROM cmpgn c JOIN upload u ON (u.upload_cmpgn=c.cmpgn_id) WHERE u.upload_verdict_summary='1' AND c.cmpgn_id='".$conn->real_escape_string($cmpgn_intlink)."'")->num_rows==0)
				$error_msg=$error_msg.'Internal link field campaign not approved yet.<br>';
			elseif(!empty($cmpgn_intlink) && empty($time_finalized) && $conn->query("SELECT 1 FROM cmpgn WHERE cmpgn_revealrealname='1' AND cmpgn_user='".$_SESSION['user']."' AND cmpgn_id='".$conn->real_escape_string($cmpgn_intlink)."'")->num_rows > 0)
				$error_msg=$error_msg.'Please change authorship revelation on linked-to campaign to forestall blackmailing!<br>';
			if(empty($pubid))
				$error_msg=$error_msg.'Please pick option for authorship revelation at campaign end.<br>';
			if($pubid=='pub_pseudonym' && !empty($_POST['revealtoprof']) && empty($_POST['confirm_pseudonym']))
				$error_msg=$error_msg.'OK to potentially clue in prof on pseudonym? <input type="checkbox" name="confirm_pseudonym"><br>';
			if(!empty($blanknames) & empty($c_blanknames) && empty($_POST['confirm_blanknames']))
				$error_msg=$error_msg.'Really de-anonymize pdfs? Moderators might blackmail you! <input type="checkbox" name="confirm_blanknames">';
/*				if(!empty($cmpgn_externallink))
					$cmpgn_externallink="'".$cmpgn_externallink."'";
				else $cmpgn_externallink="NULL";*/

			if(empty($error_msg))
			{
				if(!empty($cmpgn_extlink))
					$cmpgn_extlink="'".$conn->real_escape_string($cmpgn_extlink)."'";
				else $cmpgn_extlink="NULL";
				if(!empty($cmpgn_intlink))
					$cmpgn_intlink="'".$conn->real_escape_string($cmpgn_intlink)."'";
				else $cmpgn_intlink="NULL";
				
				if(empty($locktitle)) $conn->query("UPDATE cmpgn SET cmpgn_title='$title' WHERE cmpgn_id='$cmpgn_id'");

				if(!empty($blanknames) && (empty($time_finalized)/* || $isarchivized*/)) $conn->query("UPDATE cmpgn SET cmpgn_blanknames='$c_blanknames' WHERE cmpgn_id='$cmpgn_id'");
				if(empty($time_finalized)) $conn->query("UPDATE cmpgn SET cmpgn_showproftitle='$showproftitle' WHERE cmpgn_id='$cmpgn_id'"); 
				if(empty($time_finalized)) $conn->query("UPDATE cmpgn SET cmpgn_showprofabstr='$showprofabstr' WHERE cmpgn_id='$cmpgn_id'");
				if(empty($time_finalized)) $conn->query("UPDATE cmpgn SET cmpgn_showprofthmbnails='$showprofthmbnails' WHERE cmpgn_id='$cmpgn_id'");
				if(empty($time_firstsend) && empty($time_finalized)) $conn->query("UPDATE cmpgn SET cmpgn_displayinfeed='$displayinfeed' WHERE cmpgn_id='$cmpgn_id'");

				$conn->query("UPDATE cmpgn SET cmpgn_revealrealname='$revealrealname', cmpgn_revealtoprof='$revealtoprof',
					cmpgn_issearchable='$issearchable', cmpgn_internallink=$cmpgn_intlink, cmpgn_externallink=$cmpgn_extlink WHERE cmpgn_id='$cmpgn_id'");

				header("Location: index.php?cmpgn={$cmpgn_id}");
			}
		}
		elseif(isset($_POST['terminate']) && isset($_POST['terminate_confirm']))
		{
			if($conn->query("SELECT 1 FROM verdict v JOIN upload u ON (v.verdict_id=u.upload_verdict)
				WHERE u.upload_cmpgn='$cmpgn_id' AND (v.verdict_time1 IS NULL OR v.verdict_time2 IS NULL OR v.verdict_time3 IS NULL)")->num_rows > 0)
				$error_msg=$error_msg."Please wait for all current verdicts to finish!<br>";
			elseif($conn->query("SELECT r.review_id, r.review_prof FROM upload u JOIN review r ON (u.upload_id=r.review_upload) WHERE u.upload_cmpgn='$cmpgn_id' AND r.review_time_requested IS NOT NULL AND (r.review_time_submit IS NULL OR r.review_grade IS NULL) AND r.review_time_tgth_passedon IS NULL AND r.review_time_aborted IS NULL")->num_rows > 0)
				$error_msg=$error_msg."Seems you have got an outstanding review please wait!<br>";
			elseif(empty($_POST['confirm_terminate']))
				$error_msg=$error_msg.'Sure you wish to terminate (cannot be undone)? <input type="checkbox" name="confirm_terminate"><br>';
			elseif($conn->query("SELECT 1 FROM cmpgn WHERE cmpgn_time_finalized IS NOT NULL AND cmpgn_id='$cmpgn_id'")->num_rows > 0)
				$error_msg=$error_msg."Seems your campaign is already finalized!<br>";
			else
			{
				
				$row=$conn->query("SELECT max(m.moderators_id) AS max_mod, c.cmpgn_moderators_group FROM cmpgn c
					JOIN moderators m ON (m.moderators_group=c.cmpgn_moderators_group)
					WHERE c.cmpgn_type_isarchivized='0' AND c.cmpgn_time_finalized IS NULL AND c.cmpgn_id='$cmpgn_id'")->fetch_assoc();
				if(sizeof($row) > 0)
				{	
					//PAY OUT IDEA POINTS
					$row3=$conn->query("SELECT moderators_time_joined1, moderators_time_joined2, moderators_time_joined3 FROM moderators WHERE moderators_id='".$row['max_mod']."'")->fetch_assoc();
					$idea_pts_suppl1=2*floor((strtotime("now")-strtotime($row3['moderators_time_joined1']))/(6*7*24*60*60));
					$idea_pts_suppl2=2*floor((strtotime("now")-strtotime($row3['moderators_time_joined2']))/(6*7*24*60*60));
					$idea_pts_suppl3=2*floor((strtotime("now")-strtotime($row3['moderators_time_joined3']))/(6*7*24*60*60));
						
					$conn->query("UPDATE student SET student_cmpgn_own_latest=NULL WHERE student_user_id='".$_SESSION['user']."'");
					$conn->query("UPDATE moderators m JOIN student s1 ON (s1.student_user_id=m.moderators_first_user)
						SET s1.student_cmpgn_shadowed_latest=NULL, s1.student_pts_cmpgn=s1.student_pts_cmpgn+{$idea_pts_suppl1}
						WHERE m.moderators_id='".$row['max_mod']."'");
					$conn->query("UPDATE moderators m JOIN student s2 ON (s2.student_user_id=m.moderators_second_user)
						SET s2.student_cmpgn_shadowed_latest=NULL, s2.student_pts_cmpgn=s2.student_pts_cmpgn+{$idea_pts_suppl2}
						WHERE m.moderators_id='".$row['max_mod']."'");
					$conn->query("UPDATE moderators m JOIN student s3 ON (s3.student_user_id=m.moderators_third_user)
						SET s3.student_cmpgn_shadowed_latest=NULL, s3.student_pts_cmpgn=s3.student_pts_cmpgn+{$idea_pts_suppl3}
						WHERE m.moderators_id='".$row['max_mod']."'");

					$conn->query("UPDATE cmpgn SET cmpgn_time_finalized=NOW() WHERE cmpgn_id='$cmpgn_id'");
					//EMPTY WATCHLIST
					$conn->query("DELETE w FROM watchlist w JOIN moderators m ON (w.watchlist_moderators=m.moderators_id)
						WHERE m.moderators_group='".$row['cmpgn_moderators_group']."'");
					$result=$conn->query("SELECT s.send_id FROM send s JOIN upload u ON (s.send_upload=u.upload_id) WHERE u.upload_cmpgn='$cmpgn_id'");
					while($row=$result->fetch_assoc())
					{
						if(file_exists('user_data/tmp/send'.$row['send_id'].'_1.png')) unlink('user_data/tmp/send'.$row['send_id'].'_1.png');
						if(file_exists('user_data/tmp/send'.$row['send_id'].'_2.png')) unlink('user_data/tmp/send'.$row['send_id'].'_2.png');
						if(file_exists('user_data/tmp/send'.$row['send_id'].'_3.png')) unlink('user_data/tmp/send'.$row['send_id'].'_3.png');
					}
					rvw_to_newsfeeds($conn,$cmpgn_id); //POST ON SOCIAL MEDIA
				}
			}
		}
		else $error_msg=$error_msg."Please confirm that you really wish to terminate!<br>";
	}
	else
	{
		$_POST['issearchable']=$issearchable;
/*		$_POST['blanknames']=$blanknames;
		$_POST['displayinfeed']=$displayinfeed;
		$_POST['showprofabstr']=$showprofabstr;
		$_POST['showproftitle']=$showproftitle;
		$_POST['showprofthmbnails']=$showprofthmbnails;*/
		$_POST['revealtoprof']=$revealtoprof;
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
		$cmpgn_extlink=$extlink;
		$cmpgn_intlink=$intlink;
	}
}
else $error_msg=$error_msg."Only accessible to the campaign owner!<br>";
?>
<form method="post" action="">
<div id="centerpage">
	<h1>Settings</h1>
	<h2>of <?php echo '<a href="index.php?cmpgn='.$cmpgn_id.'">'.$title.'</a>';?></h2>
	<?php if(!empty($error_msg)) echo '<br><div style="color: red; text-align: center">'.$error_msg.'</div><br>';?>
	<h3>Title</h3>
	The title can only be changed up to the first send request.
	<div class="indentation">
	Title: <input name="title" style="width: 350px" value="<?php if(!empty($title)) echo $title; ?>" class="indentation" <?php if($locktitle) echo "disabled";?>></div>

	<h3>Privacy</h3>
    All of the below are recommended but can be turned off individually:<br><br>
    <input name="blanknames" type="checkbox" <?php if(!empty($c_blanknames)) echo 'checked';?> <?php if(empty($blanknames) || (!empty($time_finalized) && !$isarchivized)) echo "disabled"; ?> > Anonymize pdfs i.e.
    delete text from area on first page containing name.<br>

    <input name="showproftitle" type="checkbox" <?php if(!empty($showproftitle)) echo 'checked'; ?> <?php if(!empty($time_finalized) || $isarchivized) echo "disabled"; ?> > Show professors the title of your project idea.<br>
    <input name="showprofabstr" type="checkbox" <?php if(!empty($showprofabstr)) echo 'checked'; ?> <?php if(!empty($time_finalized) || $isarchivized) echo "disabled"; ?> > Show professors your abstract.<br>

    <input name="showprofthmbnails" type="checkbox" <?php if(!empty($showprofthmbnails)) echo 'checked'; ?> <?php if(!empty($time_finalized) || $isarchivized) echo "disabled"; ?> > Show professors thumbnails (pages 2,3,4 of upload 1).<br>
    <input name="revealtoprof" type="checkbox" <?php if(!empty($_POST['revealtoprof'])) echo 'checked'; ?> <?php if($isarchivized) echo "disabled"; ?> > Show professors your real name.<br>
    <input name="displayinfeed" type="checkbox" <?php if(!empty($displayinfeed)) echo 'checked'; ?> <?php if(!empty($time_firstsend) || !empty($time_finalized) || $isarchivized) echo "disabled"; ?> > Enter into "Ideas" titlepage
    newsfeed (executed upon first 'send' to prof).<br>
    <input name="issearchable" type="checkbox" <?php if(!empty($_POST['issearchable'])) echo 'checked'; ?>> Tick if you want
    keywords in uploads to be used to facilitate search indexing.<br>
    <p class="indentation" style="font-size: small">
    	Anonymized PDFs (meant to thwart 'blackmailing') cannot be reenabled if disabled once.
    </p>
    During their runtime, all campaigns are anonymous, but upon termination
    they can be published either under your pseudonym, or under your real name.
    If you choose your real name, the unredacted pdfs will be displayed automatically (you can change this option anytime after your campaign is finished).
    <p class="indentation">
      <input value="pub_realname" name="pubid" type="radio" <?php if(!empty($pubid) && $pubid=="pub_realname") echo 'checked'; ?> <?php if(empty($_SESSION['isstudent'])) echo "disabled"; ?> >Finalize under
      real name<br>
      <input value="pub_pseudonym" name="pubid" type="radio" <?php if(!empty($pubid) && $pubid=="pub_pseudonym") echo 'checked'; ?>>Finalize
      under pseudonym<br>
      <input value="pub_anonym" name="pubid" type="radio" <?php if(!empty($pubid) && $pubid=="pub_anonym") echo 'checked'; ?>>Leave anonymous<br>
    </p>
    Finally, you can link to an external site, as well as to another
    campaign: <br>
    <div class="indentation">
    <label>External link:</label><input type="text" name="cmpgn_extlink" value="<?php if(!empty($cmpgn_extlink)) echo $cmpgn_extlink; ?>"><br>
    <label>Internal (campaign ID):</label><input type="text" name="cmpgn_intlink" value="<?php if(!empty($cmpgn_intlink)) echo $cmpgn_intlink; ?>" placeholder="See 'cmpgn' field in URL"></div>
    <p style="text-align: right;" class="indentation"><button name="submit">Submit</button></p>
	<h3>Terminate campaign</h3>
	<p>Your campaign is automatically terminated 8 months after the first send is executed, provided no reviews are still
	outstanding (campaigns that fail to win approval are automatically terminated after 4 months). You can however terminate your campaign prematurely if you so wish, which you should only do if you have run out of professors to contact.
	Upon termination, all reviews and comments will be published.</p>
	<p>You can only terminate manually after all outstanding verdicts have been completed (also, please verify your choice of favourite review to be published in the newsfeed).</p>
    <input type="checkbox" name="terminate_confirm" <?php if(!empty($time_finalized) || $isarchivized) echo "disabled"; ?>> I've had enough and really wish to terminate my campaign (cannot be undone!).
    <p style="text-align: right;" class="indentation"><button name="terminate" <?php if(!empty($time_finalized) || $isarchivized) echo "disabled"; ?>>Terminate</button></p>
</div>
</form>
