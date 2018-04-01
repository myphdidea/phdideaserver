<?php
include("includes/header_functions.php");

$code=$_GET['code'];

$fields=gen_orcidquery($code);//CONTAINS PASSWORD TO ORCID

$auth_array=login_callback('https://pub.orcid.org/oauth/token',$fields);
session_start();

if(!empty($auth_array['orcid']))
{
	$result=$conn->query("SELECT prof_id FROM prof WHERE prof_orcid='".$conn->real_escape_string(test($auth_array['orcid']))."'");
	if($result->num_rows > 0 && empty($_SESSION['prof']))
	{
		$row=$result->fetch_assoc();
		$_SESSION['prof']=$row['prof_id'];
	}
	elseif($result->num_rows==0 && empty($_SESSION['prof']))
	{
		$result2=$conn->query("SELECT COUNT(*) as tot_guestprof, guestprof_familyname, guestprof_givenname
			FROM guestprof WHERE guestprof_authenticated IS NULL
			AND guestprof_orcid='".$conn->real_escape_string(test($auth_array['orcid']))."'
			GROUP BY guestprof_familyname, guestprof_givenname HAVING tot_guestprof > 2");
		if($result2->num_rows > 0)
		{
			$row2=$result2->fetch_assoc();
			$result3=$conn->query("SELECT g.guestprof_id, a.autoedit_email FROM guestprof g
				JOIN autoedit a ON (a.autoedit_prof=g.guestprof_prof)
				JOIN institution i ON (a.autoedit_email LIKE CONCAT('%',i.institution_emailsuffix))
				WHERE g.guestprof_authenticated IS NULL AND i.institution_isuniversity='1'
				AND g.guestprof_orcid='".$conn->real_escape_string(test($auth_array['orcid']))."'
				AND g.guestprof_familyname='".$row2['guestprof_familyname']."' AND g.guestprof_givenname='".$row2['guestprof_givenname']."'
				LIMIT 3");
			if($result3->num_rows > 0)
			{
				while($row3=$result3->fetch_assoc())
					$conn->query("UPDATE guestprof SET guestprof_authenticated=NOW(), guestprof_authmail='".$row3['autoedit_email']."' WHERE guestprof_id='".$row3['guestprof_id']."'");
				$conn->query("INSERT INTO prof (prof_orcid, prof_familyname, prof_givenname) VALUES ('".$conn->real_escape_string($auth_array['orcid'])."','".$row2['guestprof_familyname']."','".$row2['guestprof_givenname']."')");

				$prof_id=$conn->query("SELECT LAST_INSERT_ID();")->fetch_assoc();
				$prof_id=$prof_id['LAST_INSERT_ID()'];
				$_SESSION['prof']=$prof_id;
			}
			else $_SESSION['orcid']=$auth_array['orcid'];
		}
		else $_SESSION['orcid']=$auth_array['orcid'];
	}
	else $_SESSION['orcid']=$auth_array['orcid'];
}
else echo "Failed to return ORCID!";

header("Location: index.php");

?>