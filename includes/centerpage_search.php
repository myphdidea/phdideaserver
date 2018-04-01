<div id="centerpage">
<?php
if(isset($_GET['category']))
{
	$searchstring=explode(" ",$conn->real_escape_string(test($_GET['searchstring'])));
	switch(test($_GET['category']))
	{
		case 'res':
			$familystring=$searchstring[0];
			if(!empty($searchstring[1]))
			{
				$givenstring=$searchstring[0];
				$familystring=$searchstring[1];
			}
			else
			{
				$familystring=$searchstring[0];
				$givenstring="";
			}
			$sql="SELECT prof_id, prof_familyname, prof_givenname, prof_hasactivity, prof_institution, prof_country, prof_image FROM prof WHERE
				prof_familyname LIKE '$familystring%' ORDER BY IF(prof_givenname LIKE '$givenstring%',0,1), prof_hasactivity DESC, IF(LENGTH(prof_email > 0),0,1), prof_familyname ASC,prof_givenname ASC LIMIT 20";
//			$sql="SELECT prof_id, prof_familyname, prof_givenname, prof_hasactivity, prof_institution, prof_country, prof_image FROM prof WHERE
//				prof_familyname LIKE '$familystring%' ORDER BY prof_hasactivity DESC, IF(prof_givenname LIKE '$givenstring%',0,1),prof_familyname ASC,prof_givenname ASC LIMIT 15";
			$result=$conn->query($sql);
			if($result->num_rows==0 && !empty($searchstring[1]))
			{
				$givenstring=$searchstring[1]; $familystring=$searchstring[0];
				$sql="SELECT prof_id, prof_familyname, prof_givenname, prof_hasactivity, prof_institution, prof_country, prof_image FROM prof WHERE
					prof_familyname LIKE '$familystring%' ORDER BY IF(prof_givenname LIKE '$givenstring%',0,1), prof_hasactivity DESC, IF(LENGTH(prof_email > 0),0,1), prof_familyname ASC,prof_givenname ASC LIMIT 20";
//				$sql="SELECT prof_id, prof_familyname, prof_givenname, prof_hasactivity, prof_institution, prof_country, prof_image FROM prof WHERE
//					prof_familyname LIKE '$familystring%' ORDER BY prof_hasactivity DESC, IF(prof_givenname LIKE '$givenstring%',0,1),prof_familyname ASC,prof_givenname ASC LIMIT 15";
				$result=$conn->query($sql);
			}
			
			if($result->num_rows > 0)
			{
				$result_table="";
				while($row=$result->fetch_assoc())
				{
					if(!empty($row['prof_institution']))
					{
						$row2=$conn->query("SELECT institution_name FROM institution WHERE institution_id='".$row['prof_institution']."'")->fetch_assoc();
						if(strlen($row2['institution_name']) > 50)
							$affiliation=substr($row2['institution_name'],0,50)." ...";
						else $affiliation=$row2['institution_name'];
					}
					elseif(!empty($row['prof_country']))
					{
						$row2=$conn->query("SELECT country_name FROM country WHERE country_id='".$row['prof_country']."'")->fetch_assoc();
						$affiliation=$row2['country_name'];
					}
					else $affiliation="";

					if(!empty($row['prof_hasactivity']) && $conn->query("SELECT 1 FROM review WHERE review_prof='".$row['prof_id']."'")->num_rows > 0)
					{
						$rvw_img_latest=rvw_img_latest($conn,$row['prof_id']);
						$rvw_img_latest='<img style="float: left; margin-left: 35px; vertical-align: baseline" title="Last obtained score"
							src="images/'.$rvw_img_latest.'-smiley-small.png">';
					}
					else $rvw_img_latest="";
					
					if(!empty($row['prof_hasactivity']))
					{
						if(!empty($row['prof_image']))
							$image_path="user_data/researcher_pictures/".$row['prof_id']."_small.png";
						else $image_path="images/default_scholar.png";

						$image_path='<img alt="" src="'.$image_path.'">';
						$image_path='<div class="icon" style="margin-left: 10px; margin-top: 5px">'.$image_path.'</div>';
					}
					else $image_path="";
					
					$result_table=$result_table.'<tr>
             			<td>'.$image_path.'<br>
             			</td>
            			<td style="width: 140.3px;"><a href="index.php?prof='.$row['prof_id'].'">'.$row['prof_givenname'].' '.$row['prof_familyname'].'</a><br>
             			</td>
             			<td style="width: 120.3px;">'.$affiliation.'<br>
             			</td>
             			<td>'.$rvw_img_latest.'<br>
             			</td>
           			</tr>';
				}					
				$result_table='<table style="width: 100%" border="0">
          			<tbody>
            			<tr>
             	 			<th style="width: 40.1px;"><br>
              				</th>
              				<th style="width: 140.3px;">Name<br>
              				</th>
              				<th style="width: 120.3px;">Affiliation<br>
              				</th>
              				<th style="width: 50.3px; text-align: center">Latest<br>
              				</th>
            			</tr>'.$result_table.'</tbody>
        			</table>';
				echo $result_table;

			}	
			else echo "<i>Could not find matching familyname.</i>";
			break;
		case 'idea':
			$sql="SELECT c.cmpgn_title, c.cmpgn_id, c.cmpgn_time_launched, c.cmpgn_time_firstsend, c.cmpgn_time_finalized, c.cmpgn_type_isarchivized
				FROM cmpgn c JOIN upload ul ON (ul.upload_cmpgn=c.cmpgn_id)
				JOIN user u ON (c.cmpgn_user=u.user_id)
				JOIN student s ON (c.cmpgn_user=s.student_user_id)
				WHERE (c.cmpgn_type_isarchivized='0' OR ul.upload_verdict_summary='1') AND c.cmpgn_visibility_blocked='0'";
			foreach($searchstring as $searchfragment)
				$sql=$sql." AND (c.cmpgn_title LIKE '%{$searchfragment}%' OR (c.cmpgn_issearchable='1' AND ul.upload_keywords LIKE '%{$searchfragment}%')
					OR (s.student_familyname LIKE '{$searchfragment}%' AND c.cmpgn_revealrealname=1 AND c.cmpgn_time_finalized IS NOT NULL)
					OR (s.student_givenname LIKE '{$searchfragment}%' AND c.cmpgn_revealrealname=1 AND c.cmpgn_time_finalized IS NOT NULL)
					OR (u.user_pseudonym LIKE '{$searchfragment}%' AND c.cmpgn_revealrealname=2 AND c.cmpgn_time_finalized IS NOT NULL))";
			$sql=$sql." GROUP BY c.cmpgn_id ORDER BY c.cmpgn_type_isarchivized, c.cmpgn_time_launched DESC LIMIT 20";
			$result=$conn->query($sql);
			if($result->num_rows > 0)
			{
				$cmpgns="";
				while($row=$result->fetch_assoc())
				{
            		if($row['cmpgn_type_isarchivized'])
						$cmpgn_type_label="(archivized)";
					elseif(empty($row['cmpgn_time_firstsend']) && empty($row['cmpgn_time_finalized']))
						$cmpgn_type_label="New!";
					elseif(!empty($row['cmpgn_time_firstsend']) && !empty($row['cmpgn_time_finalized']))
						$cmpgn_type_label="Finished";
					elseif(empty($row['cmpgn_time_firstsend']) && !empty($row['cmpgn_time_finalized']))
						$cmpgn_type_label="Rejected";
					else $cmpgn_type_label="Running";
            		$cmpgns=$cmpgns.'<tr>
              				<td style="width: 82.1px;">'.$row['cmpgn_time_launched'].'<br>
              				</td>
              				<td style="width: 360.3px;"><a href="index.php?cmpgn='.$row['cmpgn_id'].'">'.$row['cmpgn_title'].'</a><br>
              				</td>
              				<td style="max-width: 80.3px;">'.$cmpgn_type_label.'<br>
              				</td>
            			</tr>';
				}
				$cmpgns='<table style="width: 100%" border="0">
          			<tbody>
            			<tr>
              			<th style="width: 82.1px;">Created<br>
              			</th>
              			<th style="width:360px;">Title<br>
              			</th>
              			<th style="width:80px;">Status<br>
              			</th>
            		</tr>'.$cmpgns.'</tbody></table>';
				echo $cmpgns;
			}
			else echo "<i>Could not find any ideas for these search parameters.</i>";
			break;
		case 'features':
			$sql="SELECT ft.featuretext_title, ft.featuretext_feature, f.feature_time_created, f.feature_time_approved
				FROM featuretext ft JOIN feature f ON (ft.featuretext_feature=f.feature_id)
				JOIN student s ON (f.feature_student=s.student_id)
				JOIN user u ON (s.student_user_id=u.user_id)
				WHERE ft.featuretext_verdict_summary='1'";
			foreach($searchstring as $searchfragment)
				$sql=$sql." AND (ft.featuretext_title LIKE '%{$searchfragment}%' OR ft.featuretext_keywords LIKE '%{$searchfragment}%'
					OR (s.student_familyname LIKE '{$searchfragment}%' AND f.feature_revealrealname=1)
					OR (s.student_givenname LIKE '{$searchfragment}%' AND f.feature_revealrealname=1)
					OR (u.user_pseudonym LIKE '{$searchfragment}%' AND f.feature_revealrealname=2))";
			$sql=$sql." ORDER BY f.feature_time_created DESC LIMIT 20";
			$result=$conn->query($sql);
			if($result->num_rows > 0)
			{
				$result_table="";
				while($row=$result->fetch_assoc())
				{
					$result_table=$result_table.'<tr>
             			<td>'.$row['feature_time_created'].'<br>
             			</td>
            			<td><a href="index.php?feat='.$row['featuretext_feature'].'">'.$row['featuretext_title'].'</a><br>
             			</td>
             			<td>'.$row['feature_time_approved'].'<br>
             			</td>
           			</tr>';
				}
				$result_table='<table style="width: 100%" border="0">
          			<tbody>
            			<tr>
             	 			<th style="width: 85.1px;">Created<br>
              				</th>
              				<th style="width: 250.3px;">Name<br>
              				</th>
              				<th style="width: 85.3px;">Approved<br>
              				</th>
            			</tr>'.$result_table.'</tbody>
        			</table>';
				echo $result_table;

			}
			else echo "<i>Could not find any features for these search parameters.</i>";
			break;
	}
}
?>
</div>