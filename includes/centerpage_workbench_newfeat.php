<?php
if(isset($_SESSION['user']) && isset($_SESSION['isstudent']))
{
	$error_msg=$pic_selector="";
	$list=glob("user_data/tmp_feat/".$_SESSION['user']."_*.*");
	if(isset($_POST['rmvpic']) && strpos(test($_POST['rmvpic']),"user_data/tmp_feat/".$_SESSION['user']."_")!=='FALSE')
		unlink(test($_POST['rmvpic']));
	elseif(isset($_POST['upload_pic']) && !empty($_FILES["newpic"]["tmp_name"]) && sizeof($list) < 10)
	{
		
		$target_dir = "user_data/tmp_feat/";
		$target_file = $target_dir . $_SESSION['user']."_".$_FILES["newpic"]["name"];//basename($_FILES["icon"]["name"]);
		$uploadOk = 1;
		$imageFileType = strtolower(pathinfo($_FILES["newpic"]["name"],PATHINFO_EXTENSION));
		// Check if image file is a actual image or fake image
    	$check = getimagesize($_FILES["newpic"]["tmp_name"]);
    	if($check !== false)
    	{
//        	echo "File is an image - " . $check["mime"] . ".";
        	$uploadOk = 1;
    	}
    	else
    	{
        	$error_msg."File is not an image.<br>";
     		$uploadOk = 0;
    	}
		// Check file size
		if ($_FILES["newpic"]["size"] > 500000) 
		{
    		$error_msg=$error_msg."Sorry 500 kB max please.<br>";
    		$uploadOk = 0;
		}
		// Allow certain file formats
		if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
			&& $imageFileType != "gif" )
		{
    		$error_msg=$error_msg."Sorry, only JPG, PNG and GIF files are allowed.<br>";
    		$uploadOk = 0;
		}
		// Check if $uploadOk is set to 0 by an error
		if ($uploadOk == 0)
		{
    		$error_msg=$error_msg."Sorry, your file was not uploaded.<br>";
		// if everything is ok, try to upload file
		} 
		else 
			rename($_FILES["newpic"]["tmp_name"],$target_file);
	}
	elseif(isset($_POST['upload_pic']) && !empty($_FILES["newpic"]["tmp_name"]))
		$error_msg=$error_msg."No more than 10 pictures allowed!<br>";
	elseif(isset($_POST['upload_pic']))
		$error_msg=$error_msg."Please select picture to upload!<br>";

	$list=glob("user_data/tmp_feat/".$_SESSION['user']."_*.*");	
	foreach($list as $item)
		$pic_selector=$pic_selector.'<p style="width: 400px"><a href="'.$item.'" target="blank_">'.substr($item,strlen("user_data/tmp_feat/".$_SESSION['user']."_")).'</a><button name="rmvpic" value="'.$item.'" style="float: right">Remove</button></p>';

	if($_SERVER['REQUEST_METHOD']=='POST')
	{
		if(isset($_POST['title'])) $title=test($_POST['title']);
		if(isset($_POST['keywords'])) $keywords=test($_POST['keywords']);
		if(isset($_POST['pubid'])) $pubid=test($_POST['pubid']);
		if(isset($_POST['featuretext'])) $featuretext=test($_POST['featuretext']);
		
		if(isset($_POST['submit']) && empty($title))
			$error_msg=$error_msg."Cannot have empty title!<br>";
		elseif(isset($_POST['submit']) && empty($pubid))
			$error_msg=$error_msg."Cannot have empty privacy settings!<br>";
		elseif(isset($_POST['submit']) && (empty($featuretext) || strlen($featuretext) < 2 ))
			$error_msg=$error_msg."Feature needs to be 2000 characters minimum!<br>";
		elseif(isset($_POST['submit']) && empty($error_msg))
		{
			//CHECK LAST FEATURE AND WHETHER ENOUGH POINTS
			if($conn->query("SELECT 1 FROM feature f JOIN student s ON (f.feature_student=s.student_id) WHERE s.student_user_id='".$_SESSION['user']."'")->num_rows > 0
				&& $conn->query("SELECT 1 FROM student s JOIN user u ON (s.student_user_id=u.user_id)
					WHERE (s.student_pts_feat+s.student_pts_cmpgn-s.student_pts_cmpgn_consmd+u.user_pts_misc-u.user_pts_fail <= 0 OR s.student_pts_feat=0) AND s.student_user_id='".$_SESSION['user']."'")->num_rows > 0 )
				$error_msg=$error_msg."Not enough points please earn some!<br>";
			elseif($conn->query("SELECT 1 FROM feature f JOIN student s ON (f.feature_student=s.student_id) WHERE f.feature_time_created + INTERVAL 3 MONTH > NOW() AND s.student_user_id='".$_SESSION['user']."'")->num_rows > 0)
				$error_msg=$error_msg."Less than 3 months since last feature please wait a little!<br>";
			elseif($conn->query("SELECT 1 FROM student WHERE student_feat_own_latest IS NOT NULL AND student_user_id='".$_SESSION['user']."'")->num_rows > 0)
				$error_msg=$error_msg."Please close running feature before launching new one!<br>";
			elseif(empty($error_msg))
			{
				if(isset($_POST['title'])) $title=$conn->real_escape_string(test($_POST['title']));
				if(isset($_POST['keywords'])) $keywords=$conn->real_escape_string(test($_POST['keywords'])); else $keywords="";
				if(isset($_POST['pubid'])) $pubid=$conn->real_escape_string(test($_POST['pubid']));
				if(isset($_POST['featuretext'])) $featuretext=$conn->real_escape_string(test($_POST['featuretext']));
				
				//PROCEED TO INSERT
				if($pubid=="pub_pseudonym") $revealrealname=2;
				elseif($pubid=="pub_realname") $revealrealname=1;
				elseif($pubid=="pub_anonym") $revealrealname=0;
				
				$sql="INSERT INTO moderators_group (moderators_group_type,moderators_group_hashcode) VALUES ('FEAT','".rand(0,9999)."')";
				$conn->query($sql);

				$sql="SELECT LAST_INSERT_ID();";
				$moderators_group=$conn->query($sql)->fetch_assoc();
				$moderators_group=$moderators_group['LAST_INSERT_ID()'];

				$sql="INSERT INTO moderators (moderators_group) VALUES ('$moderators_group')";
				$result=$conn->query($sql);

				$sql="SELECT LAST_INSERT_ID();";
				$moderators_id=$conn->query($sql)->fetch_assoc();
				$moderators_id=$moderators_id['LAST_INSERT_ID()'];
				
				$sql="INSERT INTO ratebox VALUES ()";
				$conn->query($sql);
				$sql="SELECT LAST_INSERT_ID();";
				$newratebox=$conn->query($sql)->fetch_assoc();
				$newratebox=$newratebox['LAST_INSERT_ID()'];
				
				$sql="INSERT INTO feature (feature_student,feature_revealrealname,feature_moderators_group,feature_ratebox,feature_time_created)
					SELECT student_id, '$revealrealname', '$moderators_group', '$newratebox', NOW() FROM student WHERE student_user_id='".$_SESSION['user']."'";
				$conn->query($sql);
				$sql="SELECT LAST_INSERT_ID();";
				$feature_id=$conn->query($sql)->fetch_assoc();
				$feature_id=$feature_id['LAST_INSERT_ID()'];
				
				//CREATE TASK, ASSEMBLE VERDICT (NO NOTIFYING MODERATORS YET)
				$sql="INSERT INTO task (task_time_created) VALUES (NOW())";
				$conn->query($sql);
				
				$sql="SELECT LAST_INSERT_ID();";
				$task_id=$conn->query($sql)->fetch_assoc();
				$task_id=$task_id['LAST_INSERT_ID()'];
				
				$sql="INSERT INTO verdict (verdict_moderators , verdict_task, verdict_type) VALUES ('$moderators_id', '$task_id', 'FTR')";
				$conn->query($sql);

				$sql="SELECT LAST_INSERT_ID();";
				$verdict_id=$conn->query($sql)->fetch_assoc();
				$verdict_id=$verdict_id['LAST_INSERT_ID()'];

				//MOVE FILES AND UPDATE featuretext
				$list=glob("user_data/tmp_feat/".$_SESSION['user']."_*.*");
				foreach($list as $item)
					copy($item,str_replace("tmp_feat/".$_SESSION['user']."_","feature_pictures/".$feature_id."_",$item));
//					rename($item,str_replace("tmp_feat/".$_SESSION['user']."_","feature_pictures/".$feature_id."_",$item));
				$featuretext=str_replace("tmp_feat/".$_SESSION['user']."_","feature_pictures/".$feature_id."_",$featuretext);
				
				$sql="INSERT INTO featuretext (featuretext_feature,featuretext_title,featuretext_text,featuretext_keywords,featuretext_timestamp,featuretext_verdict)
					VALUES ('$feature_id','$title','$featuretext','$keywords',NOW(),'$verdict_id')";
				$conn->query($sql);

				//WATCHLIST!
				addto_watchlists($conn,$moderators_id,'FTR',$task_id, 1, 1);
				
				$sql = "UPDATE student SET student_feat_own_latest='$feature_id' WHERE student_user_id LIKE '".$_SESSION['user']."'";
				$result = $conn->query($sql);

				$sql = "UPDATE student SET student_pts_feat=student_pts_feat-1 WHERE student_user_id LIKE '".$_SESSION['user']."' AND student_pts_feat > 0";
				$result = $conn->query($sql);

				
				header("Location: index.php?feat=".$feature_id);
			}
		}
	}
}
?>
<div id="centerpage">
<form method="post" action="" enctype="multipart/form-data">
	<h2>New feature article</h2>
	<?php if(!empty($error_msg)) echo '<div style="color: red; text-align: center">'.$error_msg.'</div>';?>
	<p><i>myphdidea.org</i> is mainly about publishing scientific project proposals and sending them to professors.
	However, we also feel that it is important to offer a less formal space for personal expression, which is
	why our website has a category for posting of WordPress-style articles. As with all material on the
	website, a "student peer review" process has to be passed for your article to gain approval and therewith,
	publication in the newsfeed. Please consult our editorial guidelines:</p>
	<ul>
		<li>The feature is concerned with higher education or research to at least 50 %</li>
		<li>Its material does not derive to more than 50 % from any single project idea proposed by the author or someone else
		(chronicles going beyond pitching the idea and dealing with its practical realization are OK though)</li>
		<li>The article is intelligible to a non-specialist audience</li>
		<li>Where a question is mooted or a position taken, arguments are furnished</li>
		<li>The material is presented with a certain level of care, and in (correct) English</li>
		<li>No obviously misplaced or offensive content</li>
		<li>Between 2000 and 65000 characters</li>
	</ul>
	<p>The limit is 1 feature per 3 months and each costs <b>1x</b> feature points (first one free!).</p>
	Please first set title, search keywords and privacy options:
	<div class="indentation">
        <label style="width: 100px">Title:</label><input name="title" style="width: 350px" value="<?php if(!empty($title)) echo $title; ?>" type="text"><br>
        <label style="width: 100px">Keywords:</label><input name="keywords" style="width: 350px" value="<?php if(!empty($keywords)) echo $keywords; ?>" type="text"><br>
        <p> 
          <input value="pub_realname" name="pubid" type="radio" <?php if(!empty($pubid) && $pubid=="pub_realname") echo 'checked'; ?>>Publish under
          real name<br>
          <input value="pub_pseudonym" name="pubid" type="radio" <?php if(!empty($pubid) && $pubid=="pub_pseudonym") echo 'checked'; ?>>Publish
          under pseudonym<br>
          <input value="pub_anonym" name="pubid" type="radio" <?php if(!empty($pubid) && $pubid=="pub_anonym") echo 'checked'; ?>>Publish anonymously<br>
        </p>
   	</div>
	Next, please upload all images you intend to use in your article:
	<div class="indentation">
		<?php if(!empty($pic_selector)) echo $pic_selector;?>
        <p style="text-align: center;"><input name="newpic" id="newpic" type="file" style="float: left"><button name="upload_pic">Upload</button></p>
	</div>
	Finally, please write or copy-paste your article:<br><br>
	        <div style="text-align: center"><textarea style="width: 555px; height: 600px;"
class="indentation" name="featuretext"><?php if(!empty($featuretext)) echo $featuretext; ?></textarea></div>

	<p style="text-align: right;" class="indentation"><button name="submit">Submit</button></p>
</form>
</div>

<script src="libs/tinymce/tinymce.min.js"></script>
<script>tinymce.init({ selector:'textarea',
					   plugins: 'link image lists charmap code hr fullscreen preview table toc',
					   toolbar: 'undo redo styleselect bold italic alignleft aligncenter alignright bullist numlist outdent indent link image'
					   });</script>
					   