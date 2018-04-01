<?php
$feat_id=$conn->real_escape_string(test($_GET['feat']));

if(isset($_SESSION['user']) && isset($_SESSION['isstudent']) &&
	$conn->query("SELECT 1 FROM feature f JOIN student s ON (f.feature_student=s.student_id) WHERE f.feature_id='$feat_id' AND s.student_user_id='".$_SESSION['user']."'")->num_rows > 0)
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

	if($conn->query("SELECT 1 FROM featuretext WHERE featuretext_verdict_summary='1' AND featuretext_feature='$feat_id'")->num_rows > 0)
		$error_msg=$error_msg."Your feature has been published now and cannot be revised any longer.<br>";
	elseif($conn->query("SELECT 1 FROM feature WHERE feature_time_created + INTERVAL 2 MONTH < NOW() AND feature_id='$feat_id'")->num_rows > 0)
		$error_msg=$error_msg."Sorry too late to add revision.<br>";
	elseif($conn->query("SELECT 1 FROM featuretext WHERE featuretext_verdict_summary IS NULL AND featuretext_feature='$feat_id'")->num_rows > 0)
		$error_msg=$error_msg."Please wait for the pending verdict before making revisions.<br>";

	if($_SERVER['REQUEST_METHOD']=='POST')
	{
		if(isset($_POST['title'])) $title=test($_POST['title']);
		if(isset($_POST['keywords'])) $keywords=test($_POST['keywords']);
		if(isset($_POST['featuretext'])) $featuretext=test($_POST['featuretext']);
		
		if(isset($_POST['submit']) && empty($title))
			$error_msg=$error_msg."Cannot have empty title!<br>";
		elseif(isset($_POST['submit']) && (empty($featuretext) || strlen($featuretext) < 2 ))
			$error_msg=$error_msg."Feature needs to be 2000 characters minimum!<br>";
		elseif(isset($_POST['submit']) && empty($error_msg))
		{
			//CHECK LAST FEATURE AND WHETHER ENOUGH POINTS
			if($conn->query("SELECT 1 FROM featuretext WHERE featuretext_timestamp + INTERVAL 2 WEEK > NOW() AND featuretext_feature='$feat_id'")->num_rows > 0)
				$error_msg=$error_msg."Please wait 2 weeks before submitting revisions!<br>";
			elseif(empty($error_msg))
			{
				if(isset($_POST['title'])) $title=$conn->real_escape_string(test($_POST['title']));
				if(isset($_POST['keywords'])) $keywords=$conn->real_escape_string(test($_POST['keywords'])); else $keywords="";
				if(isset($_POST['featuretext'])) $featuretext=$conn->real_escape_string(test($_POST['featuretext']));
				
				//PROCEED TO INSERT
				//CREATE TASK, ASSEMBLE VERDICT (NO NOTIFYING MODERATORS YET)
				
				$verdict_id=create_verdict($conn,$feat_id,'FTR');

				//MOVE FILES AND UPDATE featuretext
				$list=glob("user_data/tmp_feat/".$_SESSION['user']."_*.*");
				foreach($list as $item)
					copy($item,str_replace("tmp_feat/".$_SESSION['user']."_","feature_pictures/".$feat_id."_",$item));
				$featuretext=str_replace("tmp_feat/".$_SESSION['user']."_","feature_pictures/".$feat_id."_",$featuretext);
				
				$sql="INSERT INTO featuretext (featuretext_feature,featuretext_title,featuretext_text,featuretext_keywords,featuretext_timestamp,featuretext_verdict)
					VALUES ('$feat_id','$title','$featuretext','$keywords',NOW(),'$verdict_id')";
				$conn->query($sql);
				
				header("Location: index.php?feat=".$feat_id);
			}
		}
	}
	else
	{
		$sql="SELECT featuretext_id, featuretext_text, featuretext_title, featuretext_keywords FROM featuretext
			WHERE featuretext_feature='$feat_id' ORDER BY featuretext_id DESC";
		$row=$conn->query($sql)->fetch_assoc();
		$featuretext=str_replace("feature_pictures/".$feat_id."_","tmp_feat/".$_SESSION['user']."_",$row['featuretext_text']);
		$title=$row['featuretext_title'];
		$keywords=$row['featuretext_keywords'];
	}
}
else $error_msg="Not your feature!<br>";
?>
<div id="centerpage">
<form method="post" action="" enctype="multipart/form-data">
	<h2>Update feature article</h2>
	<?php if(!empty($error_msg)) echo '<div style="color: red; text-align: center">'.$error_msg.'</div>';?>
	<p>In case your first draft gets rejected, you are allowed a 2 month period during which you may submit
	corrected versions, one every 2 weeks (if the verdict is pending or results in acceptance, reediting will be locked).</p>
	Please set title and search keywords:
	<div class="indentation">
        <label style="width: 100px">Title:</label><input name="title" style="width: 350px" value="<?php if(!empty($title)) echo $title; ?>" type="text"><br>
        <label style="width: 100px">Keywords:</label><input name="keywords" style="width: 350px" value="<?php if(!empty($keywords)) echo $keywords; ?>" type="text"><br>
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