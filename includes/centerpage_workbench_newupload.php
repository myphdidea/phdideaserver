<?php

$error_msg="";
$xmin1=$xmax1=$ymin1=$ymax1=$xmin2=$xmax2=$ymin2=$ymax2=$xmin3=$xmax3=$ymin3=$ymax3="";
$cmpgn_id=$conn->real_escape_string(test($_GET['cmpgn']));

$sql="SELECT cmpgn_user, cmpgn_title, cmpgn_time_launched, cmpgn_time_firstsend, cmpgn_time_finalized, cmpgn_type_isarchivized,
	  cmpgn_externallink, cmpgn_internallink, cmpgn_revealrealname, cmpgn_blanknames, cmpgn_issearchable FROM cmpgn WHERE cmpgn_id='".$cmpgn_id."'";
$result = $conn->query($sql);
$row=$result->fetch_assoc();
$owner=$title=$time_launched=$time_firstsend=$cmpgn_type=$extlink=$intlink=$revealrealname="";
$owner=$row['cmpgn_user'];
$title=$row['cmpgn_title'];
$time_launched=$row['cmpgn_time_launched'];
$time_firstsend=$row['cmpgn_time_firstsend'];
$time_finalized=$row['cmpgn_time_finalized'];
$isarchivized=$row['cmpgn_type_isarchivized'];
$extlink=$row['cmpgn_externallink'];
$intlink=$row['cmpgn_internallink'];
$revealrealname=$row['cmpgn_revealrealname'];
$blanknames=$row['cmpgn_blanknames'];
$issearchable=$row['cmpgn_issearchable'];

if($owner!=$_SESSION['user'])
	header("Location: index.php");
else if($isarchivized)
	$error_msg=$error_msg."No new uploads for archivized campaign!<br>";
else if(!empty($time_finalized) || ((!empty($time_firstsend) && strtotime("today") > strtotime($time_firstsend."+8 months"))
	|| (empty($time_firstsend) && strtotime("today") > strtotime($time_launched."+4 months")) ) )
	$error_msg=$error_msg."More than 8 months since first send can't upload new files anymore!<br>";

if(!$isarchivized && !empty($time_finalized))
{
	if($revealrealname==0)
		$printname="Anonymous";
	elseif($revealrealname==1)
	{
		$sql="SELECT user_pseudonym FROM user WHERE user_id=".$_SESSION['user'];
		$row=$conn->query($sql)->fetch_assoc();
		$printname=$row['user_pseudonym'];
	}
	elseif($revealrealname==2)
	{	$sql="SELECT student_familyname, student_givenname, student_selfdescription FROM student WHERE student_user_id=".$_SESSION['user'];
		$row=$conn->query($sql)->fetch_assoc();
		$printname=$row['student_givenname']." ".$row['student_familyname'].", ".$row['student_selfdescription'];
	}
}

$sql="SELECT upload_timestamp, upload_abstract_text, upload_keywords FROM upload WHERE upload_cmpgn='$cmpgn_id' ORDER BY upload_timestamp DESC";
$result=$conn->query($sql);
if($result->num_rows > 0)
{
	$row=$result->fetch_assoc();
	if(strtotime("now") < strtotime($row['upload_timestamp']."+2 weeks"))
		$error_msg=$error_msg."Less than 2 weeks since last upload, please wait a little!<br>";
	if($_SERVER['REQUEST_METHOD']!='POST' /*!isset($_GET['submit'])*/)
	{
		$abstract=$row['upload_abstract_text'];
		$keywords=$row['upload_keywords'];
	}
}

if($_SERVER['REQUEST_METHOD']=='POST' && !empty($_SESSION['user']))
{
	if(isset($_POST['title'])) $title=test($_POST['title']);
	if(isset($_POST['abstract'])) $abstract=test($_POST['abstract']);	
	if(isset($_POST['keywords'])) $keywords=test($_POST['keywords']);
	if(isset($_POST['xmin1']) && is_numeric($_POST['xmin1'])) $xmin1=test($_POST['xmin1']);
	if(isset($_POST['xmax1']) && is_numeric($_POST['xmax1'])) $xmax1=test($_POST['xmax1']);
	if(isset($_POST['ymin1']) && is_numeric($_POST['ymin1'])) $ymin1=test($_POST['ymin1']);
	if(isset($_POST['ymax1']) && is_numeric($_POST['ymax1'])) $ymax1=test($_POST['ymax1']);
	if(isset($_POST['xmin2']) && is_numeric($_POST['xmin2'])) $xmin2=test($_POST['xmin2']);
	if(isset($_POST['xmax2']) && is_numeric($_POST['xmax2'])) $xmax2=test($_POST['xmax2']);
	if(isset($_POST['ymin2']) && is_numeric($_POST['ymin2'])) $ymin2=test($_POST['ymin2']);
	if(isset($_POST['ymax2']) && is_numeric($_POST['ymax2'])) $ymax2=test($_POST['ymax2']);
	if(isset($_POST['xmin3']) && is_numeric($_POST['xmin3'])) $xmin3=test($_POST['xmin3']);
	if(isset($_POST['xmax3']) && is_numeric($_POST['xmax3'])) $xmax3=test($_POST['xmax3']);
	if(isset($_POST['ymin3']) && is_numeric($_POST['ymin3'])) $ymin3=test($_POST['ymin3']);
	if(isset($_POST['ymax3']) && is_numeric($_POST['ymax3'])) $ymax3=test($_POST['ymax3']);
	
	if(!empty($_POST["upload1_name"]) && !isset($_POST['rmv1']))
		$upload1_name=test($_POST["upload1_name"]);
	if(!empty($_POST["upload2_name"]) && !isset($_POST['rmv2']))
		$upload2_name=test($_POST["upload2_name"]);
	if(!empty($_POST["upload3_name"]) && !isset($_POST['rmv3']))
		$upload3_name=test($_POST["upload3_name"]);



	if(empty($_FILES["upload1"]["tmp_name"]) && empty($upload1_name))
		$error_msg=$error_msg."At least first upload required<br>";
	elseif(function_exists('finfo_open'))
	{
		$finfo = finfo_open(FILEINFO_MIME);
    	if(!empty($_FILES["upload1"]["tmp_name"]))
		{
			if ($_FILES["upload1"]["size"] > 5000000)
    			$error_msg=$error_msg."File 1 too large!<br>";
			elseif(strtolower(pathinfo(test($_FILES["upload1"]["name"]),PATHINFO_EXTENSION))!="pdf")
				$error_msg=$error_msg."File 1 not a pdf file?<br>";
			elseif(strpos(finfo_file($finfo, $_FILES["upload1"]["tmp_name"]),'application/pdf')===false)
				$error_msg=$error_msg."File 1 not a valid pdf file!<br>";
			else
			{
				$upload1_name=test($_FILES["upload1"]["name"]);
				pdf_beginupload($_FILES["upload1"]["tmp_name"], 'user_data/tmp/'.$_SESSION['user'].'_1.pdf');
			}
		}
		if(!empty($_FILES["upload2"]["tmp_name"]))
		{
			if ($_FILES["upload2"]["size"] > 5000000)
    			$error_msg=$error_msg."File 2 too large!<br>";
			elseif(strtolower(pathinfo(test($_FILES["upload2"]["name"]),PATHINFO_EXTENSION))!="pdf")
				$error_msg=$error_msg."File 2 not a pdf file?<br>";
			elseif(strpos(finfo_file($finfo, $_FILES["upload2"]["tmp_name"]),'application/pdf')===false)
				$error_msg=$error_msg."File 2 not a valid pdf file!<br>";
			else
			{
				$upload2_name=test($_FILES["upload2"]["name"]);
				pdf_beginupload($_FILES["upload2"]["tmp_name"], 'user_data/tmp/'.$_SESSION['user'].'_2.pdf');
			}
		}
		if(!empty($_FILES["upload3"]["tmp_name"]))
		{
			if ($_FILES["upload3"]["size"] > 5000000)
    			$error_msg=$error_msg."File 3 too large!<br>";
			elseif(strtolower(pathinfo(test($_FILES["upload3"]["name"]),PATHINFO_EXTENSION))!="pdf")
				$error_msg=$error_msg."File 3 not a pdf file?<br>";
			elseif(strpos(finfo_file($finfo, $_FILES["upload3"]["tmp_name"]),'application/pdf')===false)
				$error_msg=$error_msg."File 3 not a valid pdf file!<br>";
			else
			{
				$upload3_name=test($_FILES["upload3"]["name"]);
				pdf_beginupload($_FILES["upload3"]["tmp_name"], 'user_data/tmp/'.$_SESSION['user'].'_3.pdf');
			}
		}
    	finfo_close($finfo);
	}
	
	if(!empty($upload1_name) && (!empty($blanknames) /*|| isset($_POST['preview_sansnoms'])*/))
	{
		if((empty($xmin1) || empty($xmax1) || empty($ymin1) || empty($ymax1)))
			$error_msg=$error_msg."Please enter box coordinates for 1!<br>";
		elseif( $xmin1 >= $xmax1 || $ymin1 >= $ymax1 || $xmin1 < 0 || $ymin1 < 0 || $xmax1 > 100 || $ymax1 > 100)
			$error_msg=$error_msg."No valid box coordinates for 1!<br>";
	}
	if(!empty($upload2_name) && (!empty($blanknames) /*|| isset($_POST['preview_sansnoms'])*/))
	{
		if(empty($xmin2) || empty($xmax2) || empty($ymin2) || empty($ymax2))
			$error_msg=$error_msg."Please enter box coordinates for 2!<br>";
		elseif( $xmin2 >= $xmax2 || $ymin2 >= $ymax2 || $xmin2 < 0 || $ymin2 < 0 || $xmax2 > 100 || $ymax2 > 100)
			$error_msg=$error_msg."No valid box coordinates for 2!<br>";
	}
	if(!empty($upload3_name) && (!empty($blanknames) /*|| isset($_POST['preview_sansnoms'])*/))
	{
		if(empty($xmin3) || empty($xmax3) || empty($ymin3) || empty($ymax3))
			$error_msg=$error_msg."Please enter box coordinates for 3!<br>";
		elseif( $xmin3 >= $xmax3 || $ymin3 >= $ymax3 || $xmin3 < 0 || $ymin3 < 0 || $xmax3 > 100 || $ymax3 > 100)
			$error_msg=$error_msg."No valid box coordinates for 3!<br>";
	}
	
	if(isset($_POST['submit']))
	{
		if(empty($title) || empty($abstract))
			$error_msg=$error_msg."Cannot have empty title or abstract!<br>";
				if(strlen($abstract) > 2000)
			$error_msg=$error_msg."Abstract max length 2000 characters!<br>";
		if(!empty($issearchable) && empty($keywords))
			$error_msg=$error_msg."Ticked searchable by keywords but no keywords entered!<br>";
		if(!empty($blanknames))
		{
			if(empty($upload1_name))
				$error_msg=$error_msg."Please generate preview for anonymized pdfs once before submitting!<br>";
			elseif(!file_exists('user_data/tmp/'.$_SESSION['user'].'_1.pdf') || !file_exists('user_data/tmp/'.$_SESSION['user'].'_1.pdf.rdc')
				|| filemtime('user_data/tmp/'.$_SESSION['user'].'_1.pdf') > filemtime('user_data/tmp/'.$_SESSION['user'].'_1.pdf.rdc'))
					$error_msg=$error_msg."Please examine upload 1 link once before submitting anonymized pdfs!<br>";
			if(!empty($upload2_name) &&
				(!file_exists('user_data/tmp/'.$_SESSION['user'].'_2.pdf') || !file_exists('user_data/tmp/'.$_SESSION['user'].'_2.pdf.rdc')
				|| empty($upload2_name) || filemtime('user_data/tmp/'.$_SESSION['user'].'_2.pdf') > filemtime('user_data/tmp/'.$_SESSION['user'].'_2.pdf.rdc')))
					$error_msg=$error_msg."Please examine upload 2 link once before submitting anonymized pdfs!<br>";
			if(!empty($upload3_name) &&
				(!file_exists('user_data/tmp/'.$_SESSION['user'].'_3.pdf') || !file_exists('user_data/tmp/'.$_SESSION['user'].'_3.pdf.rdc')
				|| filemtime('user_data/tmp/'.$_SESSION['user'].'_3.pdf') > filemtime('user_data/tmp/'.$_SESSION['user'].'_3.pdf.rdc')))
					$error_msg=$error_msg."Please examine upload 3 link once before submitting anonymized pdfs!<br>";
		}
		
		//Create campaign and first upload in user database
		if(empty($error_msg))
		{
			
			//Perform insert operation
			if(empty($error_msg))
			{
				if(!empty($upload1_name) && !empty($blanknames))
				{
					$sql = "INSERT INTO coord (coord_xmin, coord_xmax, coord_ymin, coord_ymax)
							VALUES ('$xmin1', '$xmax1', '$ymin1', '$ymax1')";
					$result = $conn->query($sql);

					$sql="SELECT LAST_INSERT_ID();";
					$upload1_coord_id=$conn->query($sql)->fetch_assoc();
					$upload1_coord_id=$upload1_coord_id['LAST_INSERT_ID()'];
					$upload1_coord_id="'".$upload1_coord_id."'";
				}
				else $upload1_coord_id="NULL";

				if(!empty($upload2_name) && !empty($blanknames))
				{
					$sql = "INSERT INTO coord (coord_xmin, coord_xmax, coord_ymin, coord_ymax)
							VALUES ('$xmin2', '$xmax2', '$ymin2', '$ymax2')";
					$result = $conn->query($sql);

					$sql="SELECT LAST_INSERT_ID();";
					$upload2_coord_id=$conn->query($sql)->fetch_assoc();
					$upload2_coord_id=$upload2_coord_id['LAST_INSERT_ID()'];
					$upload2_coord_id="'".$upload2_coord_id."'";
				}
				else $upload2_coord_id="NULL";

				if(!empty($upload3_name) && !empty($blanknames))
				{
					$sql = "INSERT INTO coord (coord_xmin, coord_xmax, coord_ymin, coord_ymax)
							VALUES ('$xmin3', '$xmax3', '$ymin3', '$ymax3')";
					$result = $conn->query($sql);

					$sql="SELECT LAST_INSERT_ID();";
					$upload3_coord_id=$conn->query($sql)->fetch_assoc();
					$upload3_coord_id=$upload3_coord_id['LAST_INSERT_ID()'];
					$upload3_coord_id="'".$upload3_coord_id."'";
				}
				else $upload3_coord_id="NULL";
				
				//check whether at least one previous upload has been OKd
				$sql="SELECT 1 FROM upload WHERE upload_cmpgn='$cmpgn_id' AND upload_verdict_summary='1'";
				if($conn->query($sql)->num_rows==0)
				{
					//NOT OKd, DEMAND OK FOR THIS ONE!
					//CREATE TASK, ASSEMBLE VERDICT & NOTIFY MODERATORS
					$verdict_id="'".create_verdict($conn,$cmpgn_id,'UPLOAD')."'";
				}
				else $verdict_id="NULL";
				
				//SAME FOR 2,3
				$abstract=$conn->real_escape_string($abstract);
				$keywords=$conn->real_escape_string($keywords);
				$upload1_name=$conn->real_escape_string($upload1_name);
				if(!empty($upload2_name))
					$upload2_name="'".$conn->real_escape_string($upload2_name)."'";
				else $upload2_name="NULL";
				if(!empty($upload3_name))
					$upload3_name="'".$conn->real_escape_string($upload3_name)."'";
				else $upload3_name="NULL";
				$sql = "INSERT INTO upload (upload_cmpgn, upload_abstract_text, upload_keywords, 
						upload_file1, upload_file2, upload_file3,upload_verdict,
						upload_file1_coord, upload_file2_coord, upload_file3_coord)
						VALUES ('$cmpgn_id', '$abstract','$keywords',
						'$upload1_name',$upload2_name,$upload3_name,$verdict_id,
						$upload1_coord_id,$upload2_coord_id,$upload3_coord_id)";
				$result = $conn->query($sql);
				if(!$result) echo "Insert into upload failed!";
				
				$sql="SELECT LAST_INSERT_ID();";
				$upload_id=$conn->query($sql)->fetch_assoc();
				$upload_id=$upload_id['LAST_INSERT_ID()'];
				
				//move files, update cmgpn_time_launched and upload_timestamp
				if(isset($_POST['submit']))
				{
					rename('user_data/tmp/'.$_SESSION['user'].'_1.pdf','user_data/uploads/'.$cmpgn_id.'_'.$upload_id.'_1.pdf');
					rename('user_data/tmp/'.$_SESSION['user'].'_1.pdf.rdc','user_data/uploads_redacted/'.$cmpgn_id.'_'.$upload_id.'_1.pdf.rdc');
					if($upload2_name!="NULL")
					{
						rename('user_data/tmp/'.$_SESSION['user'].'_2.pdf','user_data/uploads/'.$cmpgn_id.'_'.$upload_id.'_2.pdf');
						rename('user_data/tmp/'.$_SESSION['user'].'_2.pdf.rdc','user_data/uploads_redacted/'.$cmpgn_id.'_'.$upload_id.'_2.pdf.rdc');
					}
					if($upload3_name!="NULL")
					{
						rename('user_data/tmp/'.$_SESSION['user'].'_3.pdf','user_data/uploads/'.$cmpgn_id.'_'.$upload_id.'_3.pdf');
						rename('user_data/tmp/'.$_SESSION['user'].'_3.pdf.rdc','user_data/uploads_redacted/'.$cmpgn_id.'_'.$upload_id.'_3.pdf.rdc');
					}
					
					unlink('user_data/tmp/'.$_SESSION['user'].'_1.pdf.misc');
					unlink('user_data/tmp/'.$_SESSION['user'].'_2.pdf.misc');
					unlink('user_data/tmp/'.$_SESSION['user'].'_3.pdf.misc');
					unlink('user_data/tmp/'.$_SESSION['user'].'_1.png');
					unlink('user_data/tmp/'.$_SESSION['user'].'_2.png');
					unlink('user_data/tmp/'.$_SESSION['user'].'_3.png');

/*					$sql = "UPDATE cmpgn SET cmpgn_time_launched=NOW() WHERE cmpgn_id='$cmpgn_id'";
					$result = $conn->query($sql);
					if(!$result) echo "Update of campaign failed!";*/

					$sql = "UPDATE upload SET upload_timestamp=NOW() WHERE upload_id='$upload_id'";
					$result = $conn->query($sql);
					if(!$result) echo "Update of upload failed!";

					tsa_callmultiple($conn,$cmpgn_id,$upload_id);

/*					$sql = "UPDATE student SET student_cmpgn_own_latest='".$cmpgn_id."' WHERE student_user_id LIKE '".$_SESSION['user']."'";
					$result = $conn->query($sql);
					if(!$result) echo "Update of student failed!";*/

//					$conn->close();
					header("Location: index.php?cmpng=".$cmpgn_id);
				}
			}
//			$conn->close();
		}
	}
}

function pdf_beginupload($source, $target)
{
	move_uploaded_file($source, $target);

//	putenv('PATH=C:/Program Files/gs/gs9.21/bin/');
	putenv('PATH=/usr/bin');
 	exec("gs921 -o ".$target.".misc"." -dNoOutputFonts -sDEVICE=pdfwrite ".$target);
	
//	putenv('PATH=C:/Program Files/ImageMagick-7.0.5-Q16/');
	putenv('PATH=/usr/bin');
	exec("convert ".$target."[0] -resize 180x180! ".substr($target,0,strlen($target)-4).".png");
}


?>
<form method="post" action="" enctype="multipart/form-data">
      <div id="centerpage">
        <h2><?php echo $title;?></h2>
        Author: <?php if(empty($time_firstsend) && !empty($time_finalized))
						$launched="approval not obtained";
					  elseif(empty($time_firstsend))
        				$launched="not yet approved";
					  else $launched="approved ".$time_firstsend;
        			  if(!$isarchivized && empty($time_finalized))
        				echo '<i>We do not disclose author names before the campaign is finished</i>';
        			  else echo $printname; ?> (<?php echo "Created ".$time_launched.", ".$launched
        			  	.", "; if($isarchivized) echo "archivized";
        					 else if(empty($time_finalized)) echo "still running";
        					 else echo "finalized ".$time_finalized;?>)<br>
        <h3>Request upload (last upload <?php echo $row['upload_timestamp']; ?>)</h3>
        <?php if(isset($error_msg)) echo '<div style="color: red; text-align: center">'.$error_msg.'</div>';?>
        <p>As your ideas progress, you can replace older versions of your
          documentation (PowerPoint presentations etc.) with newer ones. Please
          specify your files:</p>
<div class="indentation">
        <input name="upload1" id="upload1" type="file">
        <?php
				if(file_exists('user_data/tmp/'.$_SESSION['user'].'_1.pdf') && !empty($upload1_name))
        			echo '<button name="rmv1">Remove</button><br><a style="margin-left: 10px" target="_blank" href="prvw_pdf.php?id=1&blanknames='.$blanknames.'&xmin='.$xmin1.'&xmax='.$xmax1.'&ymin='.$ymin1.'&ymax='.$ymax1.'">'.$upload1_name.'</a>';
//			}
        ?>
        <input name="upload1_name" type="hidden" value="<?php if(!empty($upload1_name)) echo $upload1_name; ?>"><br>
        <input name="upload2" id="upload2" type="file">
        <?php /*if(isset($_POST['preview_sansnoms']))
			{*/
				if(file_exists('user_data/tmp/'.$_SESSION['user'].'_2.pdf') && !empty($upload2_name))
        			echo '<button name="rmv2">Remove</button><br><a style="margin-left: 10px" target="_blank" href="prvw_pdf.php?id=2&blanknames='.$blanknames.'&xmin='.$xmin2.'&xmax='.$xmax2.'&ymin='.$ymin2.'&ymax='.$ymax2.'">'.$upload2_name.'</a>';
//			}
        ?>
        <input name="upload2_name" type="hidden" value="<?php if(!empty($upload2_name)) echo $upload2_name; ?>"><br>
        <input name="upload3" id="upload3" type="file">
        <?php /*if(isset($_POST['preview_sansnoms']))
			{*/
				if(file_exists('user_data/tmp/'.$_SESSION['user'].'_3.pdf') && !empty($upload3_name))
        			echo '<button name="rmv3">Remove</button><br><a style="margin-left: 10px" class="indentation" target="_blank" href="prvw_pdf.php?id=3&blanknames='.$blanknames.'&xmin='.$xmin3.'&xmax='.$xmax3.'&ymin='.$ymin3.'&ymax='.$ymax3.'">'.$upload3_name.'</a>';
//			}
        ?>
        <input name="upload3_name" type="hidden" value="<?php if(!empty($upload3_name)) echo $upload3_name; ?>"><br></div>
        <p style="text-align: center;"><button name="preview_sansnoms">Display thumbnails</button></p>
        <?php if($blanknames) echo 'You have enabled anonymized pdfs in the <a href="index.php?workbench=config&cmpgn='.$cmpgn_id.'">campaign settings</a>. Please click
        							\'display\':';
							  else "Anonymized pdfs are now disabled, so feel free to ignore the below."; ?>
        <?php if(!empty($upload1_name)) echo '<img src="user_data/tmp/'.$_SESSION['user'].'_1.png" id="upload1_thmb" alt="Thumbnail1">';?>
        <?php if(!empty($upload2_name)) echo '<img src="user_data/tmp/'.$_SESSION['user'].'_2.png" id="upload2_thmb" alt="Thumbnail2">';?>
		<?php if(!empty($upload3_name)) echo '<img src="user_data/tmp/'.$_SESSION['user'].'_3.png" id="upload3_thmb" alt="Thumbnail3">';?>
<!--    <div class="indentation">
        <?php if(isset($_POST['preview_sansnoms']))
			{
				if(file_exists('user_data/'.$_SESSION['user'].'_1.pdf'))
        			echo '<a href="'.$upload1_name.'">'.$upload1_name.'</a><br>';
			}
        ?>
     	</div>-->
     	<div class="indentation">
     		File 1: x<sub>min</sub> <input type="number" name="xmin1" value="<?php echo $xmin1; ?>">
     				 x<sub>max</sub> <input type="number" name="xmax1" value="<?php echo $xmax1; ?>">
     				 y<sub>min</sub> <input type="number" name="ymin1" value="<?php echo $ymin1; ?>">
     				 y<sub>max</sub> <input type="number" name="ymax1" value="<?php echo $ymax1; ?>"> %<br>     								
     		File 2: x<sub>min</sub> <input type="number" name="xmin2" value="<?php echo $xmin2; ?>">
     				 x<sub>max</sub> <input type="number" name="xmax2" value="<?php echo $xmax2; ?>">
     				 y<sub>min</sub> <input type="number" name="ymin2" value="<?php echo $ymin2; ?>">
     				 y<sub>max</sub> <input type="number" name="ymax2" value="<?php echo $ymax2; ?>"> %<br>
     		File 3: x<sub>min</sub> <input type="number" name="xmin3" value="<?php echo $xmin3; ?>">
     				 x<sub>max</sub> <input type="number" name="xmax3" value="<?php echo $xmax3; ?>">
     				 y<sub>min</sub> <input type="number" name="ymin3" value="<?php echo $ymin3; ?>">
     				 y<sub>max</sub> <input type="number" name="ymax3" value="<?php echo $ymax3; ?>"> %<br>
     	</div>
     	<p class="indentation" style="font-size: small">
     		Note: At a later stage, we intend to replace the graphical method
     		with a text parser. Please ensure spelling of your name in the
     		documents is correct to facilitate transition.
     	</p>
     	     	<p style="text-align: center;"><button name="preview_sansnoms">Update preview links</button></p>
        Please check your abstract:
        <div style="text-align: center"><textarea style="width: 400px; height: 200px;"

class="indentation" name="abstract"><?php if(!empty($abstract)) echo $abstract; ?></textarea></div>
        You can update your keywords (used by the top search bar): <br>
        <div style="text-align: center"><input style="width: 400px" class="indentation"

            name="keywords" value="<?php if(!empty($keywords)) echo $keywords; ?>" type="text"></div>

        <p style="text-align: right;" class="indentation"><button name="submit">Submit</button></p>
      </div>
</form>
<script src="libs/jcrop/js/jquery.min.js"></script>
<script src="libs/jcrop/js/jquery.Jcrop.min.js"></script>
<link rel="stylesheet" href="libs/jcrop/css/jquery.Jcrop.css" type="text/css" />

		<script type="text/javascript">

		jQuery(function($){

  		$('#upload1_thmb').Jcrop({
    		onChange:   showCoords,
    		onSelect:   showCoords,
    		bgColor: '',
    		addClass: 'jcrop-left'
  		});
  		
  		$('#upload2_thmb').Jcrop({
    		onChange:   showCoords2,
    		onSelect:   showCoords2,
    		bgColor: '',
    		addClass: 'jcrop-left'
  		});
  		
  		$('#upload3_thmb').Jcrop({
    		onChange:   showCoords3,
    		onSelect:   showCoords3,
    		bgColor: '',
    		addClass: 'jcrop-left'
  		});
		});

		function showCoords(c)
		{
  			document.getElementsByName("xmin1")[0].value=Math.floor(c.x/1.8);
  			document.getElementsByName("xmax1")[0].value=Math.floor(c.x2/1.8);
  			document.getElementsByName("ymin1")[0].value=100-Math.floor(c.y2/1.8);
  			document.getElementsByName("ymax1")[0].value=100-Math.floor(c.y/1.8);
		};
		
		function showCoords2(c)
		{
  			document.getElementsByName("xmin2")[0].value=Math.floor(c.x/1.8);
  			document.getElementsByName("xmax2")[0].value=Math.floor(c.x2/1.8);
  			document.getElementsByName("ymin2")[0].value=100-Math.floor(c.y2/1.8);
  			document.getElementsByName("ymax2")[0].value=100-Math.floor(c.y/1.8);
		};

		function showCoords3(c)
		{
  			document.getElementsByName("xmin3")[0].value=Math.floor(c.x/1.8);
  			document.getElementsByName("xmax3")[0].value=Math.floor(c.x2/1.8);
  			document.getElementsByName("ymin3")[0].value=100-Math.floor(c.y2/1.8);
  			document.getElementsByName("ymax3")[0].value=100-Math.floor(c.y/1.8);
		};

		</script>
