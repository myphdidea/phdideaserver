<?php
$error_msg="";
if(isset($_SESSION['prof']))
{
	$sql="SELECT prof_orcid, prof_description, prof_familyname, prof_institution, prof_image FROM prof WHERE prof_id='".$_SESSION['prof']."'";
	$row=$conn->query($sql)->fetch_assoc();
	$real_orcid=$orcid=$row['prof_orcid'];
	$description=$row['prof_description'];
	$prof_fname=$row['prof_familyname'];
	$has_image=$row['prof_image'];
	if(!empty($row['prof_institution']))
	{
		$row=$conn->query("SELECT institution_name FROM institution WHERE institution_id='".$row['prof_institution']."'")->fetch_assoc();
		$instit_search=$row['institution_name'];
	}
	$result=$conn->query("SELECT autoedit_notification_frequency, autoedit_email_new, autoedit_email_auth, autoedit_email_token FROM autoedit WHERE autoedit_prof='".$_SESSION['prof']."'");
	if($result->num_rows > 0)
	{
		$row0=$result->fetch_assoc();
		$email=$row0['autoedit_email_new'];
		$turnoff_notif=$row0['autoedit_notification_frequency'];
	}
	//ADDED FOR guestprof
	$can_auth=$conn->query("SELECT 1 FROM autoedit a JOIN institution i ON a.autoedit_email LIKE CONCAT('%',i.institution_emailsuffix) WHERE i.institution_isuniversity='1' AND a.autoedit_prof='".$_SESSION['prof']."'")->num_rows > 0
		&& $conn->query("SELECT 1 FROM guestprof WHERE NOW() < guestprof_authenticated + INTERVAL 2 MONTH AND guestprof_prof='".$_SESSION['prof']."'")->num_rows == 0
		&& $conn->query("SELECT 1 FROM guestprof g JOIN autoedit a ON a.autoedit_email LIKE g.guestprof_authmail
			WHERE NOW() < g.guestprof_authenticated + INTERVAL 2 MONTH AND a.autoedit_prof='".$_SESSION['prof']."'")->num_rows == 0;
	$result=$conn->query("SELECT guestprof_orcid, guestprof_familyname, guestprof_givenname, guestprof_authenticated
		FROM guestprof WHERE guestprof_prof='".$_SESSION['prof']."' ORDER BY guestprof_id DESC");
	if($result->num_rows > 0)
	{
		$row1=$result->fetch_assoc();
		if(empty($can_auth) || empty($row1['guestprof_authenticated']))
		{
			if(!isset($_POST['new_orcid'])) $new_orcid=$row1['guestprof_orcid'];
			if(!isset($_POST['new_fname'])) $new_fname=$row1['guestprof_familyname'];
			if(!isset($_POST['new_gname'])) $new_gname=$row1['guestprof_givenname'];
		}
	}

	
	if(isset($_POST['search']) || isset($_POST['save']))
	{
		if(empty($real_orcid)) $orcid=test($_POST['orcid']);
		$description=test($_POST['self_descr']);
		if(!empty($_POST['display_image'])) $has_image=TRUE; else $has_image=FALSE;
		$email=test($_POST['auto_email']);
		$instit_search=test($_POST['instit']);
		if(!empty($_POST['freq_mails'])) $turnoff_notif=test($_POST['freq_emails']);
		
		if((isset($_POST['search']) || isset($_POST['instit_selec'])) && !empty($instit_search))
		{
			$sql="SELECT i.institution_id, i.institution_name, c.country_name, i.institution_emailsuffix
				FROM institution i JOIN country c ON (i.institution_country=c.country_id) WHERE i.institution_name LIKE '%".$instit_search."%' LIMIT 20";
			$result=$conn->query($sql);
			if($result->num_rows > 0)
			{
				$instit_selector="";
				while($row=$result->fetch_assoc())
				{
					if(!empty($instit_selec) && $instit_selec==$row['institution_id'])
						$instselected="selected";
					else $instselected="";
					$instit_selector=$instit_selector.'<option style="min-width: 280px; max-width: 280px" value="'.$row['institution_id'].'" '.$instselected.'>'.$row['institution_name'].', '.$row['country_name'].' @'.$row['institution_emailsuffix'].'</option>';
				}
            	$instit_selector='<select style="float: right; min-width: 280px; max-width: 280px" name="instit_selec">'.$instit_selector.'</select>';			
			}
			else $instit_selector='<i>Could not find institution, please try again</i>';
		}
		elseif(isset($_POST['search']) && empty($instit_search)) $error_msg=$error_msg."Can't have empty institution!<br>";

		if(filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($email)) list($part1,$part2)=explode('@',$email);
		
		if(strlen($description) > 250)
			$error_msg=$error_msg."Max 250 characters please!<br>";
		elseif(!empty($email) && (empty($row0['autoedit_email_new']) || $email!=$row0['autoedit_email_new']) && !filter_var($email, FILTER_VALIDATE_EMAIL))
			$error_msg=$error_msg."Not a valid e-mail format!<br>";
		elseif(!empty($part2) && $conn->query("SELECT 1 FROM institution WHERE institution_emailsuffix LIKE '".$conn->real_escape_string($part2)."' OR '".$conn->real_escape_string($part2)."' LIKE CONCAT('%.',institution_emailsuffix)")->num_rows == 0)
			$error_msg=$error_msg."Not a recognized institutional suffix!<br>";
		elseif(!empty($email) && (empty($row0['autoedit_email_new']) || $email!=$row0['autoedit_email_new']) && ($conn->query("SELECT 1 FROM user WHERE user_email LIKE '$email' AND user_email_auth_time IS NOT NULL")->num_rows > 0
			|| $conn->query("SELECT 1 FROM student WHERE student_institution_email LIKE '$email' AND student_email_auth IS NOT NULL")->num_rows > 0 
			|| $conn->query("SELECT 1 FROM autoedit WHERE autoedit_email LIKE '$email' AND autoedit_email_auth IS NOT NULL AND autoedit_prof!='".$_SESSION['prof']."'")->num_rows > 0))
			$error_msg=$error_msg."E-mail seems to be already registered!<br>";

		if(empty($real_orcid) && !empty($orcid) && $conn->query("SELECT 1 FROM prof WHERE prof_orcid='".$conn->real_escape_string($orcid)."'")->num_rows > 0)
			$error_msg=$error_msg."ORCID seems to be already taken!<br>";
		elseif(empty($real_orcid) && !empty($orcid) && (empty($_SESSION['orcid']) || $_SESSION['orcid']!=$orcid))
			$error_msg=$error_msg."Not logged in with (this?) ORCID, please use link!<br>";

		if(!empty($_POST['new_orcid']) || !empty($_POST['new_fname']) || !empty($_POST['new_gname']))
		{
			if(!empty($_POST['new_orcid'])) $new_orcid=$conn->real_escape_string(test($_POST['new_orcid']));
			if(!empty($_POST['new_fname'])) $new_fname=$conn->real_escape_string(test($_POST['new_fname']));
			if(!empty($_POST['new_gname'])) $new_gname=$conn->real_escape_string(test($_POST['new_gname']));
			$regex = '#^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$#';
			if(empty($_POST['new_orcid']) || empty($_POST['new_fname']) || empty($_POST['new_gname']))
				$error_msg=$error_msg."Please fill in all 3 fields if you want to authenticate new user!<br>";
			elseif(!empty($new_orcid) && !preg_match($regex,$new_orcid))
				$error_msg=$error_msg."New user ORCID not right format!<br>";
		}
			
		if(!empty($_FILES['icon']['name']) && isset($_POST['save']) && empty($error_msg))
		{
			$target_dir = "user_data/researcher_pictures/";
			$target_file1 = $target_dir . $_SESSION['prof'].".png";//basename($_FILES["icon"]["name"]);
			$target_file2 = $target_dir . $_SESSION['prof']."_small.png";//basename($_FILES["icon"]["name"]);
			$uploadOk = 1;
			$imageFileType = strtolower(pathinfo($_FILES["icon"]["name"],PATHINFO_EXTENSION));
			// Check if image file is a actual image or fake image
    		$check = getimagesize($_FILES["icon"]["tmp_name"]);
    		if($check !== false)
    		{
        		echo "File is an image - " . $check["mime"] . ".";
        		$uploadOk = 1;
    		}
    		else
    		{
        		$error_msg."File is not an image.<br>";
     			$uploadOk = 0;
    		}
			// Check file size
			if ($_FILES["icon"]["size"] > 1000000) 
			{
    			$error_msg=$error_msg."Sorry, your file is too large.<br>";
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
			{
				if($imageFileType=="jpg" || $imageFileType=="jpeg")
					$image = imagecreatefromjpeg($_FILES["icon"]["tmp_name"]);
				else if($imageFileType=="png")
					$image = imagecreatefrompng($_FILES["icon"]["tmp_name"]);
				else if($imageFileType=="gif")
					$image = imagecreatefromgif($_FILES["icon"]["tmp_name"]);

				$image_p = imagecreatetruecolor(40, 40);
				imagecopyresampled($image_p, $image, 0, 0, 0, 0, 40, 40, imagesx($image), imagesy($image));
				// Output
				imagePNG($image_p, $target_file2,1);
				
				$image_p = imagecreatetruecolor(100, 100);
				imagecopyresampled($image_p, $image, 0, 0, 0, 0, 100, 100, imagesx($image), imagesy($image));
				// Output
				imagePNG($image_p, $target_file1,1);
				
				$has_image=TRUE;
			}
		}
		
		if(empty($error_msg) && isset($_POST['save']))
		{
			$description=$conn->real_escape_string(test($_POST['self_descr']));
			$email=$conn->real_escape_string(test($_POST['auto_email']));
			if(!empty($_POST['freq_emails'])) $turnoff_notif=$conn->real_escape_string(test($_POST['freq_emails']));
			
			//PROCEED TO INSERTS
			$conn->query("UPDATE prof SET prof_image='$has_image', prof_description='$description' WHERE prof_id='".$_SESSION['prof']."'");
			if(!empty($_POST['instit_selec']))
				$conn->query("UPDATE prof SET prof_institution='".$conn->real_escape_string(test($_POST['instit_selec']))."' WHERE prof_id='".$_SESSION['prof']."'");
			if(!empty($row0['autoedit_email_new']) && !is_null($row0['autoedit_notification_frequency']) && !empty($row0['autoedit_email_auth']))
				$conn->query("UPDATE autoedit SET autoedit_notification_frequency='$turnoff_notif' WHERE autoedit_prof='".$_SESSION['prof']."'");
			
			if(empty($real_orcid) && !empty($orcid) && $_SESSION['orcid']==$orcid)
				$conn->query("UPDATE prof SET prof_orcid='".$conn->real_escape_string($orcid)."' WHERE prof_id='".$_SESSION['prof']."'");
			
			if(!empty($_POST['new_orcid']) && $can_auth)
			{
				if($conn->query("SELECT 1 FROM guestprof WHERE guestprof_authenticated IS NULL AND guestprof_prof='".$_SESSION['prof']."'")->num_rows==0)
					$conn->query("INSERT INTO guestprof (guestprof_prof,guestprof_orcid,guestprof_familyname,guestprof_givenname)
						VALUES ('".$_SESSION['prof']."','$new_orcid','$new_fname','$new_gname')");
				else $conn->query("UPDATE guestprof SET guestprof_orcid='$new_orcid', guestprof_familyname='$new_fname',
					guestprof_givenname='$new_gname' WHERE guestprof_authenticated IS NULL AND guestprof_prof='".$_SESSION['prof']."'");
			}
			elseif(empty($_POST['new_orcid']))
				$conn->query("DELETE FROM guestprof WHERE guestprof_authenticated IS NULL AND guestprof_prof='".$_SESSION['prof']."'");
			
			if(!empty($email) && (empty($row0['autoedit_email_new']) || $email!=$row0['autoedit_email_new']))
			{
				//CHECK WHETHER INSTITUTIONAL EMAIL!

				if(empty($row0['autoedit_email_new']))
					$conn->query("INSERT INTO autoedit (autoedit_prof, autoedit_notification_frequency) VALUES ('".$_SESSION['prof']."', '2')");

				$private_email_token=md5( rand(0,10000000) );
				
				if(send_mail($email,'Confirm your credentials','https://www.myphdidea.org/index.php?confirm=verify_prof&verify='.$private_email_token,
					"Dear Prof. ".$prof_fname.",\n\n".
					"You have registered this institutional e-mail address to myphdidea.org, in order to confirm it,
					 please click on or copy-paste the link below:\n\n","\n\nOnce your e-mail address is verified, you will be allowed to participate in the popular vote.\n\n
					 The myphdidea team"))
				{
					$private_email_token=$conn->real_escape_string($private_email_token);
					$conn->query("UPDATE autoedit SET autoedit_email_new='$email', autoedit_email_token='$private_email_token' WHERE autoedit_prof='".$_SESSION['prof']."'");
					header("Location: index.php?confirm=register_student");
				}
			}
//			else header("Location: index.php");
		}
	}
}
?>
      <div id="centerpage">
        <h2>Settings</h2>
<?php if(!empty($error_msg)) echo '<div style="color: red; text-align: center">'.$error_msg.'</div><br>';?>
        <form method="post" action="" enctype="multipart/form-data">
        <div class="indentation"><label>Icon (max 1 MB):</label><input type="checkbox" name="display_image" <?php if(!empty($has_image)) echo "checked"; ?>> <input name="icon" id="icon" type="file"> <label>Description:</label><textarea
            style="width: 200px; height: 100px; vertical-align: top" name="self_descr"><?php if(!empty($description)) echo $description; ?></textarea>
          <p><label>Institution:</label><input type="text" name="instit" style="width: 200px" value="<?php if(!empty($instit_search)) echo $instit_search; ?>">
          <button name="search" style="float: right">Search</button></p>
        Institution (please select): 
        <?php
        	if(!empty($instit_selector)) echo $instit_selector;
			else echo '<i>Please enter institution searchname above.</i>';
		?>
        </div>

        <h3>E-mail</h3>
        <div class="indentation"> <label>Institutional mail:</label><input style="width: 200px"
            type="text" name="auto_email" value="<?php if(!empty($email)) echo $email; ?>">
             <?php if(!empty($row0['autoedit_email_new']) && $row0['autoedit_email_new']==$email && !empty($row0['autoedit_email_auth']) && empty($row0['autoedit_email_token'])) echo "(OK!)"; ?><br><br>
        <label>E-mail frequency <?php if(!empty($row0['autoedit_notification_frequency'])) echo "(".$row0['autoedit_notification_frequency'].")" ?>:</label><input name="freq_emails"
            min="0" max="5" value="<?php if(isset($turnoff_notif)) echo $turnoff_notif; ?>" <?php if(empty($row0['autoedit_email_auth'])) echo "disabled"; ?> type="range"> </div>
        Note that registering your e-mail address is required for participating in the popular vote, proposing a review to students via dialogue, and substituting for a colleague.

        <h3>ORCID</h3>
        ORCID helps us avoid creation of duplicate profiles. You can register an ORCID to a profile that
        has not yet got one, but cannot unlink an already present ORCID from it.<br>
        <div class="indentation">
		<label>ORCID (login <a href="https://orcid.org/oauth/authorize?client_id=INSERT_ORCIDAPP_LOGIN_HERE&response_type=code&scope=/authenticate&redirect_uri=https://www.myphdidea.org/login_orcid.php">here</a>):</label><input name="orcid"
            value="<?php if(!empty($orcid)) echo $orcid; elseif(!empty($_SESSION['orcid'])) echo $_SESSION['orcid']; ?>" <?php if(!empty($real_orcid)) echo "disabled"; ?> style="width: 200px" type="text"> </div>
        <h3>Authenticate new researcher</h3>
        Finally, new entries can be added to the researcher database if 3 existing members authenticate the new user.
        Condition for being allowed to do so is an e-mail address from a <a href="http://localhost/myphdidea/index.php?page=institutionlist">recognized university</a>.
        You are limited to one authentication per 2 months.
        <div class="indentation">
		<label>New user ORCID:</label><input name="new_orcid"
            value="<?php if(!empty($new_orcid)) echo $new_orcid; ?>" <?php if(empty($can_auth)) echo "disabled"; ?> style="width: 200px" type="text">
		<label>New user family name:</label><input name="new_fname"
            value="<?php if(!empty($new_fname)) echo $new_fname; ?>" <?php if(empty($can_auth)) echo "disabled"; ?> style="width: 200px" type="text">
		<label>New user given name:</label><input name="new_gname"
            value="<?php if(!empty($new_gname)) echo $new_gname; ?>" <?php if(empty($can_auth)) echo "disabled"; ?> style="width: 200px" type="text"> </div>
        <p style="text-align: right;" class="indentation"><button type="submit" name="save">Save</button>
        </p></form>
</div>
