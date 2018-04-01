<?php
$disable_pubreg="";
if(isset($_SESSION['user']) && isset($_SESSION['isstudent']))
{
	$sql="SELECT student_image, student_selfdescription, student_socialmedia_link, student_taskexcl_cmpgn,
		student_taskexcl_feat FROM student WHERE student_user_id='".$_SESSION['user']."'";
	$row=$conn->query($sql)->fetch_assoc();
	
	$sql="SELECT 1 FROM send s JOIN upload u ON (s.send_upload=u.upload_id)
		JOIN cmpgn c ON (u.upload_cmpgn=c.cmpgn_id) WHERE c.cmpgn_user='".$_SESSION['user']."'
		AND s.send_verdict_summary='1' AND cmpgn_time_firstsend IS NOT NULL";
	if($conn->query($sql)->num_rows > 0 && $conn->query("SELECT 1 FROM student WHERE student_user_id='".$_SESSION['user']."'
		AND student_cmpgn_shadowed_latest IS NULL")->num_rows > 0)
		$cantake_excl_prop=TRUE;
	if($conn->query("SELECT 1 FROM feature f JOIN student s ON (f.feature_student=s.student_id) WHERE f.feature_time_approved IS NOT NULL AND s.student_user_id='".$_SESSION['user']."'")->num_rows > 0)
		$cantake_excl_feat=TRUE;
	if($conn->query("SELECT 1 FROM student WHERE student_cmpgn_own_latest IS NOT NULL AND student_user_id='".$_SESSION['user']."'")->num_rows > 0)
		$disable_pubreg='disabled';
	
	if(isset($_POST['submit']) || isset($_POST['save']) || isset($_POST['search1']) || isset($_POST['search2']) || isset($_POST['search3']))
	{
		$error_msg=$self_description=$socialmedia_link="";
		if(isset($_POST['self_descr'])) $self_description=$conn->real_escape_string(test($_POST['self_descr']));
		if(isset($_POST['socialmedia_link'])) $socialmedia_link=$conn->real_escape_string(test($_POST['socialmedia_link']));

		if(isset($_POST['display_image'])) $display_image=TRUE; else $display_image=FALSE;
		if(isset($_POST['taskexcl_cmpgn'])) $taskexcl_cmpgn=TRUE; else $taskexcl_cmpgn=FALSE;
		if(isset($_POST['taskexcl_feat'])) $taskexcl_feat=TRUE; else $taskexcl_feat=FALSE;
		$taskexcl_feat=FALSE; //OUTCOMMENT IN ORDER TO ACTIVATE
		
        if(isset($_POST['gname1'])) $gname1=$conn->real_escape_string(test($_POST['gname1'])); else $gname1="";
        if(isset($_POST['fname1'])) $fname1=$conn->real_escape_string(test($_POST['fname1'])); else $fname1="";
        if(isset($_POST['gname2'])) $gname2=$conn->real_escape_string(test($_POST['gname2'])); else $gname2="";
        if(isset($_POST['fname2'])) $fname2=$conn->real_escape_string(test($_POST['fname2'])); else $fname2="";
        if(isset($_POST['gname3'])) $gname3=$conn->real_escape_string(test($_POST['gname3'])); else $gname3="";
        if(isset($_POST['fname3'])) $fname3=$conn->real_escape_string(test($_POST['fname3'])); else $fname3="";
		
		if(isset($_POST['save']) && (empty($fname1) || empty($fname2) || empty($fname3)))
			$error_msg=$error_msg."Please fill out all 3 researcher names!<br>";
		elseif(isset($_POST['save']) && (empty($_POST['pub_confirm1']) || empty($_POST['pub_confirm2']) || empty($_POST['pub_confirm3'])))
			$error_msg=$error_msg."Please tick at least one publication per researcher!<br>";
		if(isset($_POST['save']) && !empty($disable_pubreg))
			$error_msg=$error_msg."Cannot edit research interests while campaign is running!<br>";

		if(empty($error_msg) && isset($_POST['save']))
		{
			$result=$conn->query("SELECT pubreg_id, pubreg_resbox1, pubreg_resbox2, pubreg_resbox3 FROM pubreg p
				JOIN student s ON (p.pubreg_student=s.student_id) WHERE s.student_user_id='".$_SESSION['user']."'
				AND p.pubreg_cmpgn IS NULL ORDER BY pubreg_id DESC");
			if($result->num_rows == 0)
			{
				//CREATE NEW PUBREG
				$conn->query("INSERT INTO resbox VALUES ()");
				$resbox1=$conn->query("SELECT LAST_INSERT_ID();")->fetch_assoc();
				$resbox1=$resbox1['LAST_INSERT_ID()'];				

				$conn->query("INSERT INTO resbox VALUES ()");
				$resbox2=$conn->query("SELECT LAST_INSERT_ID();")->fetch_assoc();
				$resbox2=$resbox2['LAST_INSERT_ID()'];				

				$conn->query("INSERT INTO resbox VALUES ()");
				$resbox3=$conn->query("SELECT LAST_INSERT_ID();")->fetch_assoc();
				$resbox3=$resbox3['LAST_INSERT_ID()'];				

				$row=$conn->query("SELECT student_id FROM student WHERE student_user_id='".$_SESSION['user']."'")->fetch_assoc();

				$sql="INSERT INTO pubreg (pubreg_resbox1,pubreg_resbox2,pubreg_resbox3,pubreg_cmpgn,pubreg_student)
					VALUES ('$resbox1','$resbox2','$resbox3',NULL,'".$row['student_id']."')";
				$conn->query($sql);
				$pubreg_id=$conn->query("SELECT LAST_INSERT_ID();")->fetch_assoc();
				$pubreg_id=$pubreg_id['LAST_INSERT_ID()'];
			}
			else
			{
				$row=$result->fetch_assoc();
				$pubreg_id=$row['pubreg_id'];
				$resbox1=$row['pubreg_resbox1']; $resbox2=$row['pubreg_resbox2']; $resbox3=$row['pubreg_resbox3'];
				$conn->query("DELETE FROM resbox_impl WHERE resbox_impl_id='$resbox1'");
				$conn->query("DELETE FROM resbox_impl WHERE resbox_impl_id='$resbox2'");
				$conn->query("DELETE FROM resbox_impl WHERE resbox_impl_id='$resbox3'");
			}
			
			$conn->query("UPDATE pubreg SET pubreg_fname1='$fname1',pubreg_gname1='$gname1',pubreg_fname2='$fname2',
				pubreg_gname2='$gname2',pubreg_fname3='$fname3',pubreg_gname3='$gname3' WHERE pubreg_id='$pubreg_id'");
			
			foreach($_POST['pub_confirm1'] as $item)
			{
				$item=$conn->real_escape_string(test($item));
				$coll_nb=virtuoso_nb($item);
				if(empty($coll_nb_max1) || $coll_nb > $coll_nb_max1 || ($coll_nb == $coll_nb_max1 && $item > $item_max1))
				{
					$coll_nb_max1=$coll_nb;
					$item_max1=$item;
				}
				$sql="INSERT INTO resbox_impl (resbox_impl_id,resbox_impl_researcher,resbox_impl_rel_nb) VALUES ('$resbox1','$item','".$coll_nb."')";
				$conn->query($sql);
			}

			foreach($_POST['pub_confirm2'] as $item)
			{
				$item=$conn->real_escape_string(test($item));
				$coll_nb=virtuoso_nb($item);
				if(empty($coll_nb_max2) || $coll_nb > $coll_nb_max2 || ($coll_nb == $coll_nb_max2 && $item > $item_max2))
				{
					$coll_nb_max2=$coll_nb;
					$item_max2=$item;
				}
				$sql="INSERT INTO resbox_impl (resbox_impl_id,resbox_impl_researcher,resbox_impl_rel_nb) VALUES ('$resbox2','$item','".$coll_nb."')";
				$conn->query($sql);
			}

			foreach($_POST['pub_confirm3'] as $item)
			{
				$item=$conn->real_escape_string(test($item));
				$coll_nb=virtuoso_nb($item);
				if(empty($coll_nb_max3) || $coll_nb > $coll_nb_max3 || ($coll_nb == $coll_nb_max3 && $item > $item_max3))
				{
					$coll_nb_max3=$coll_nb;
					$item_max3=$item;
				}
				$sql="INSERT INTO resbox_impl (resbox_impl_id,resbox_impl_researcher,resbox_impl_rel_nb) VALUES ('$resbox3','$item','".$coll_nb."')";
				$conn->query($sql);
			}

			if(empty($coll_nb_max1) || $coll_nb_max1 < 100) $error_msg=$error_msg."Few friends for Prof 1 choose more publications/other prof?<br>";
			if(empty($coll_nb_max2) || $coll_nb_max2 < 100) $error_msg=$error_msg."Few friends for Prof 2 choose more publications/other prof?<br>";			
			if(empty($coll_nb_max3) || $coll_nb_max3 < 100) $error_msg=$error_msg."Few friends for Prof 3 choose more publications/other prof?<br>";
			
			$error_msg=check_collocs($error_msg,$item_max1,$item_max2,$item_max3);
			
			echo "Pubreg updated!";
		}
		
		if(isset($_POST['submit']) && !empty($socialmedia_link) && !preg_match("/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i",$socialmedia_link))
			$error_msg=$error_msg.'Filled out external link field but not a valid URL.<br>';
		if(isset($_POST['submit']) && strlen($self_description) > 100)
			$error_msg=$error_msg.'Self-description too long!.<br>';
		
		if(!empty($_FILES['icon']['name']) && isset($_POST['submit']) && empty($error_msg))
		{
			$target_dir = "user_data/profile_pictures/";
			$target_file = $target_dir . $_SESSION['user'].".png";//basename($_FILES["icon"]["name"]);
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
				$image_p = imagecreatetruecolor(40, 40);
				if($imageFileType=="jpg" || $imageFileType=="jpeg")
					$image = imagecreatefromjpeg($_FILES["icon"]["tmp_name"]);
				else if($imageFileType=="png")
					$image = imagecreatefrompng($_FILES["icon"]["tmp_name"]);
				else if($imageFileType=="gif")
					$image = imagecreatefromgif($_FILES["icon"]["tmp_name"]);
				imagecopyresampled($image_p, $image, 0, 0, 0, 0, 40, 40, imagesx($image), imagesy($image));

				// Output
				imagePNG($image_p, $target_file,1);
				
				$sql="UPDATE student SET student_image='1' WHERE student_user_id='".$_SESSION['user']."'";
				$display_image=1;
				$conn->query($sql);
			}
		}

		if(isset($_POST['submit']) && empty($error_msg))
		{
			$sql="UPDATE student SET student_image='$display_image', student_selfdescription='$self_description',
				student_taskexcl_cmpgn='$taskexcl_cmpgn', student_taskexcl_feat='$taskexcl_feat', student_socialmedia_link='$socialmedia_link'
				WHERE student_user_id='".$_SESSION['user']."'";
			$conn->query($sql);
			
			header("Location: index.php");
		}
	}
	else
	{
		$_POST['self_descr']=$row['student_selfdescription'];
		$_POST['socialmedia_link']=$row['student_socialmedia_link'];
		$_POST['display_image']=$row['student_image'];
		if(!empty($cantake_excl_prop)) $_POST['taskexcl_cmpgn']=$row['student_taskexcl_cmpgn'];
		if(!empty($cantake_excl_feat)) $_POST['taskexcl_feat']=$row['student_taskexcl_feat'];
		
		$load_pubreg=TRUE;
	}
	
	if((!empty($disable_pubreg) || !empty($load_pubreg)) && $conn->query("SELECT 1 FROM pubreg p JOIN student s ON (s.student_id=p.pubreg_student)
		WHERE student_user_id='".$_SESSION['user']."' AND (p.pubreg_cmpgn IS NULL OR s.student_cmpgn_own_latest IS NOT NULL)")->num_rows > 0)
	{
		$row=$conn->query("SELECT p.pubreg_fname1, p.pubreg_fname2, p.pubreg_fname3,
			p.pubreg_gname1, p.pubreg_gname2, p.pubreg_gname3 FROM pubreg p
			JOIN student s ON (p.pubreg_student=s.student_id) WHERE s.student_user_id='".$_SESSION['user']."'
			ORDER BY p.pubreg_id DESC")->fetch_assoc();
		$fname1=$row['pubreg_fname1']; $fname2=$row['pubreg_fname2']; $fname3=$row['pubreg_fname3'];
		$gname1=$row['pubreg_gname1']; $gname2=$row['pubreg_gname2']; $gname3=$row['pubreg_gname3'];
		
		$result=$conn->query("SELECT r.resbox_impl_researcher, p.pubreg_id FROM resbox_impl r
			JOIN pubreg p ON (p.pubreg_resbox1=r.resbox_impl_id)
			JOIN student s ON (p.pubreg_student=s.student_id) WHERE s.student_user_id='".$_SESSION['user']."'
			ORDER BY pubreg_id DESC");
		$_POST['pub_confirm1']=array();
		do
		{
			if(!empty($row['pubreg_id'])) $pubreg_prev1=$row['pubreg_id'];
			$row=$result->fetch_assoc();
			$_POST['pub_confirm1'][]=$row['resbox_impl_researcher'];
		} while(empty($pubreg_prev1) || $pubreg_prev1==$row['pubreg_id']);
		
		$result=$conn->query("SELECT r.resbox_impl_researcher, p.pubreg_id FROM resbox_impl r
			JOIN pubreg p ON (p.pubreg_resbox2=r.resbox_impl_id)
			JOIN student s ON (p.pubreg_student=s.student_id) WHERE s.student_user_id='".$_SESSION['user']."'
			ORDER BY pubreg_id DESC");
		$_POST['pub_confirm2']=array();
		do
		{
			if(!empty($row['pubreg_id'])) $pubreg_prev2=$row['pubreg_id'];
			$row=$result->fetch_assoc();
			$_POST['pub_confirm2'][]=$row['resbox_impl_researcher'];
		} while(empty($pubreg_prev2) || $pubreg_prev2==$row['pubreg_id']);
		
		$result=$conn->query("SELECT r.resbox_impl_researcher, p.pubreg_id FROM resbox_impl r
			JOIN pubreg p ON (p.pubreg_resbox3=r.resbox_impl_id)
			JOIN student s ON (p.pubreg_student=s.student_id) WHERE s.student_user_id='".$_SESSION['user']."'
			ORDER BY pubreg_id DESC");
		$_POST['pub_confirm3']=array();
		do
		{
			if(!empty($row['pubreg_id'])) $pubreg_prev3=$row['pubreg_id'];
			$row=$result->fetch_assoc();
			$_POST['pub_confirm3'][]=$row['resbox_impl_researcher'];
		} while(empty($pubreg_prev3) || $pubreg_prev3==$row['pubreg_id']);
	}
}
?>
      <div id="centerpage">
        <h2>Profile</h2>
<?php if(!empty($error_msg)) echo '<div style="color: red; text-align: center">'.$error_msg.'</div><br>';?>
        <form method="post" action="" enctype="multipart/form-data">
        <div class="indentation"><label>Icon (max 1 MB):</label><input type="checkbox" name="display_image" <?php if(!empty($_POST['display_image'])) echo "checked"; ?>> <input name="icon" id="icon" type="file"> <label>Self-description:</label><input            style="width: 150px" name="self_descr" type="text" value=" <?php if(!empty($_POST['self_descr'])) echo test($_POST['self_descr']); ?>"> <!--<label>Pseudonym:</label><input style="width: 150px"
            type="text">--></div>
        <i>myphdidea.org</i> is a publishing platform more than a social
        network, and thus has got limited options for creating a profile. You
        can however link to an external profile:<br>
        <div class="indentation"> <label>External profile link:</label><input style="width: 200px"            type="text" name="socialmedia_link" value="<?php if(!empty($_POST['socialmedia_link'])) echo test($_POST['socialmedia_link']); ?>"></div>
        <h3>Priority proposals</h3>
        By default, task proposals are usually visible to several members at once, and distributed
        on a <i>first come, first served</i> basis. However, it is possible to request timed exclusivity
        in order to increase your chances of landing a preferred job. Note that if
        you fail to reply within the time limit, you revert to non-exclusive offers.<br>
        <div class="indentation">
        <input type="checkbox" name="taskexcl_cmpgn" <?php if(!empty($_POST['taskexcl_cmpgn'])) echo "checked"; ?> <?php if(empty($cantake_excl_prop)) echo "disabled"; ?> > Activate exclusive campaign proposals (24 hours)<br>
<!--        <input type="checkbox" name="taskexcl_feat" <?php if(!empty($_POST['taskexcl_feat'])) echo "checked"; ?> <?php if(empty($cantake_excl_feat)) echo "disabled"; ?>> Activate exclusive feature proposals (24 hours)<br>-->
        <p style="text-align: right;" class="indentation"><button type="submit" name="submit">Submit</button>
		</div>
        <h3>Research interests</h3>
        <p>Here, you can choose 3 researchers whose work best illustrates your own
        research interests (please tick all publications that
        apply). This information is used by the matching engine when
        assigning moderators to campaigns, and in order to check for consistency
        when you try to contact professors about a project.</p>
        <p>Note that this section must be present for you to launch a campaign, and editing will be locked
        	during its runtime. Try to put researchers you intend to contact here.</p>
        <div class="indentation"> <label style="width: 80px;"><b>Prof 1:</b></label><input style="width: 120px"            name="fname1" type="text" placeholder="Surname" value="<?php if(!empty($fname1)) echo $fname1;?>" <?php echo $disable_pubreg; ?> ><input style="width: 120px"
            name="gname1" type="text" placeholder="First Name" value="<?php if(!empty($gname1)) echo $gname1; ?>" <?php echo $disable_pubreg; ?> ><button name="search1" <?php echo $disable_pubreg; ?> >Search</button></div>
        <table style="width: 100%" border="0">
          <tbody>
            <tr>
              <th style="width: 92.1px;">Author<br>
              </th>
              <th style="width: 284.3px;">Title<br>
              </th>
              <th style="width: 35.3px;">Date<br>
              </th>
              <th style="width: 15.3px;"><br>
              </th>
            </tr>
            <?php
            	if(isset($_POST['pub_confirm1'])) $pub_confirm1=$_POST['pub_confirm1']; else $pub_confirm1="";
            	if(!empty($fname1) && (isset($_POST['search1']) || isset($_POST['pub_confirm1']) || isset($_POST['save']))) $pub_selector1=gen_pubsel($conn,$fname1,$gname1,'1',$pub_confirm1,!empty($disable_pubreg));
            	if(!empty($pub_selector1))
            		echo $pub_selector1;
				else echo '<tr>
              			<td><br></td>
              			<td><i>Click "search" to find publications.</i><br></td>
              			<td><br></td>
              			<td><br></td></tr>';
            ?>
          </tbody>
        </table>
            
        <div class="indentation"> <label style="width: 80px;"><b>Prof 2:</b></label><input style="width: 120px"
            name="fname2" type="text" placeholder="Surname" value="<?php if(!empty($fname2)) echo $fname2; ?>" <?php echo $disable_pubreg; ?> ><input style="width: 120px"
            name="gname2" type="text" placeholder="First Name" value="<?php if(!empty($gname2)) echo $gname2; ?>" <?php echo $disable_pubreg; ?> ><button name="search2" <?php echo $disable_pubreg; ?> >Search</button></div>
        <table style="width: 100%" border="0">
          <tbody>
            <tr>
              <th style="width: 92.1px;">Author<br>
              </th>
              <th style="width: 284.3px;">Title<br>
              </th>
              <th style="width: 35.3px;">Date<br>
              </th>
              <th style="width: 15.3px;"><br>
              </th>
            </tr>
            <?php
            	if(isset($_POST['pub_confirm2'])) $pub_confirm2=$_POST['pub_confirm2']; else $pub_confirm2="";
            	if(!empty($fname2) && (isset($_POST['search2']) || isset($_POST['pub_confirm2']) || isset($_POST['save']))) $pub_selector2=gen_pubsel($conn,$fname2,$gname2,'2',$pub_confirm2,!empty($disable_pubreg));
            	if(!empty($pub_selector2))
            		echo $pub_selector2;
				else echo '<tr>
              			<td><br></td>
              			<td><i>Click "search" to find publications.</i><br></td>
              			<td><br></td>
              			<td><br></td></tr>';
            ?>
          </tbody>
        </table>

        <div class="indentation"> <label style="width: 80px;"><b>Prof 3:</b></label><input style="width: 120px"
            name="fname3" type="text" placeholder="Surname" value="<?php if(!empty($fname3)) echo $fname3; ?>" <?php echo $disable_pubreg; ?> ><input style="width: 120px"
            name="gname3" type="text" placeholder="First Name" value="<?php if(!empty($gname3)) echo $gname3; ?>" <?php echo $disable_pubreg; ?> ><button name="search3" <?php echo $disable_pubreg; ?>>Search</button></div>
        <table style="width: 100%" border="0">
          <tbody>
            <tr>
              <th style="width: 92.1px;">Author<br>
              </th>
              <th style="width: 284.3px;">Title<br>
              </th>
              <th style="width: 35.3px;">Date<br>
              </th>
              <th style="width: 15.3px;"><br>
              </th>
            </tr>
            <?php
            	if(isset($_POST['pub_confirm3'])) $pub_confirm3=$_POST['pub_confirm3']; else $pub_confirm3="";
            	if(!empty($fname3) && (isset($_POST['search3']) || isset($_POST['pub_confirm3']) || isset($_POST['save'])) ) $pub_selector3=gen_pubsel($conn,$fname3,$gname3,'3',$pub_confirm3,!empty($disable_pubreg));
            	if(!empty($pub_selector3))
            		echo $pub_selector3;
				else echo '<tr>
              			<td><br></td>
              			<td><i>Click "search" to find publications.</i><br></td>
              			<td><br></td>
              			<td><br></td></tr>';
            ?>
          </tbody>
        </table>
        <p style="text-align: right;" class="indentation"><button type="submit" name="save" <?php if(!empty($disable_pubreg)) echo $disable_pubreg;?>>Save</button>
        </p></form>
</div>