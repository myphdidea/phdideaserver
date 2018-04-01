<?php
		if(!isset($selec_row_limit))
			$selec_row_limit=1;
		if(!empty($prof_fname))
		{
			$conn->query("SET NAMES utf8");
			$sql="SELECT rs.researcher_id, rs.researcher_familyname, rs.researcher_givenname,
				p.publication_title, p.publication_date FROM publication p, record rc,
				researcher rs WHERE (rs.researcher_id=rc.record_researcher_id) AND (p.publication_id=rc.record_publication)
				AND rs.researcher_familyname LIKE '".$prof_fname."%' ORDER BY IF(rs.researcher_givenname LIKE '".$prof_gname."%',0,1), IF(rs.researcher_givenname LIKE '".substr($prof_gname,0,1)."%',0,1), p.publication_date DESC, rs.researcher_familyname LIMIT 20";
			if($selec_row_limit!=1) {} //REMOVE TO EN/DISABLE
			else
			{
				$result=$conn->query($sql);
				if($result->num_rows > 0)
				{
					$pub_selector="";
					while($row=$result->fetch_assoc())
					{
						if(isset($_POST['pub_confirm']) && in_array($row['researcher_id'],$_POST['pub_confirm']))
							$pubischecked="checked";
						else $pubischecked="";
//						if(isset($_POST['pub_confirm'])) echo var_dump($_POST['pub_confirm'])." ".$row['researcher_id']."<br>";
						$pub_selector=$pub_selector.'<tr>
              				<td>'.$row['researcher_givenname'].' '.$row['researcher_familyname'].'<br>
              				</td>
              				<td>'.$row['publication_title'].'<br>
              				</td>
              				<td>'.$row['publication_date'].'<br>
              				</td>
              				<td><input name="pub_confirm[]" type="checkbox" value="'.$row['researcher_id'].'" '.$pubischecked.'><br>
              				</td>
            			</tr>';
            		}
				}
				else $error_msg="Could not find publications!<br>".$error_msg;
			}
	
			//WHILE LOOP FOR WHITTLING DOWN prof_fname?
			$i=0;
			do {
				$sql="SELECT prof_id, prof_orcid, prof_givenname, prof_familyname, prof_description, prof_hasactivity FROM prof
				  WHERE prof_familyname LIKE '".substr($prof_fname,0,strlen($prof_fname)-$i)."%' ORDER BY prof_hasactivity DESC, IF(prof_familyname LIKE '".$prof_fname."%',0,1), IF(prof_givenname LIKE '".$prof_gname."%',0,1), IF(prof_givenname LIKE '".strstr($prof_gname,' ',TRUE)."%',0,1) LIMIT 10";
				$result=$conn->query($sql);
				$i++;
			} while($result->num_rows < $selec_row_limit && $i < strlen($prof_fname)-1);
			
//			$prof_fname=substr($prof_fname,0,strlen($prof_fname)-$i+1);
			
			if($result->num_rows > 0)
			{
				$orcid_selector="";
				while($row=$result->fetch_assoc())
				{
					if(isset($search_prof) && $search_prof==$row['prof_id'])
						$found_prof=TRUE;
					if(!empty($orcid_enter) && $orcid_enter==$row['prof_id'])
						$orcidselected="checked";
					else $orcidselected="";
					$prof_printname=$row['prof_familyname'].', '.$row['prof_givenname'];
					$orcid_printname="";
					if(!empty($row['prof_orcid']))
						$orcid_printname=' (<a target="_blank" href="https://orcid.org/'.$row['prof_orcid'].'">'.$row['prof_orcid'].'</a>)';
					if($row['prof_hasactivity'])
						$prof_printname='<a href=index.php?prof='.$row['prof_id'].'>'.$prof_printname.'</a>';
					$orcid_selector=$orcid_selector.'<input value="'.$row['prof_id'].'" name="orcid_enter" '.$orcidselected.' type="radio">'.
          				$prof_printname.$orcid_printname.'<br>';
          		}
			}
//			else if(empty($_POST['confirm_noorcid'])) $error_msg=$error_msg.'Couldn\'t find researcher record please confirm: <input name="confirm_noorcid" type="checkbox">';
			else if(!isset($orcid_enter) || ($orcid_enter!='hasorcid' && $orcid_enter!='noorcid')) $error_msg=$error_msg.'Couldn\'t find researcher record, please try again or choose "no ORCID"!';
		}
//		else $error_msg="Need at least professor family name!<br>".$error_msg;
?>
