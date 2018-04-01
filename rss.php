<?php
include("includes/header_functions.php");

if(isset($_GET['recentsubject']) && $_GET['recentsubject']!=0)
{
	$recentsubject=$conn->real_escape_string(test($_GET['recentsubject']));
	$recentcondit=" AND (u.user_subject1='$recentsubject' OR u.user_subject2='$recentsubject')";
}
else $recentcondit="";

header("Content-Type: text/xml; charset=utf-8");

$sql_idea="SELECT c1.cmpgn_title AS name, 'New campaign' AS description,
	CONCAT('https://www.myphdidea.org/index.php?cmpgn=',c1.cmpgn_id) AS link,
	c1.cmpgn_time_firstsend AS time
	FROM cmpgn c1 JOIN user u ON (u.user_id=c1.cmpgn_user)
	WHERE c1.cmpgn_time_firstsend IS NOT NULL AND c1.cmpgn_displayinfeed='1'".$recentcondit;
$sql_prof="SELECT CONCAT(p.prof_givenname,' ',p.prof_familyname) AS name, CONCAT('On ',
	c2.cmpgn_title) AS description, CONCAT('https://www.myphdidea.org/index.php?prof=',p.prof_id) AS link,
	c2.cmpgn_time_finalized AS time
	FROM review r
	JOIN cmpgn c2 ON (r.review_id=c2.cmpgn_rvw_favourite)
	JOIN prof p ON (r.review_prof=p.prof_id)
	JOIN user u ON (u.user_id=c2.cmpgn_user)
	WHERE c2.cmpgn_time_finalized IS NOT NULL".$recentcondit;
$sql_feat="SELECT CONCAT('https://www.myphdidea.org/index.php?feat=',ft.featuretext_feature) AS link,
	ft.featuretext_title AS name,ft.featuretext_keywords AS description,f.feature_time_approved AS time
	FROM featuretext ft
	JOIN feature f ON (ft.featuretext_feature=f.feature_id)
	WHERE ft.featuretext_verdict_summary='1'";
$sql_upload="SELECT MAX(u.upload_timestamp) AS time, CONCAT('https://www.myphdidea.org/index.php?cmpgn=',c.cmpgn_id) AS link,
	CONCAT(c.cmpgn_title,' (Version ',COUNT(*),')') AS name, u.upload_keywords AS description FROM upload u
	JOIN cmpgn c ON (u.upload_cmpgn=c.cmpgn_id) GROUP BY c.cmpgn_id";
/*$sql="SELECT c1.cmpgn_title AS name, 'New campaign' AS description,
	CONCAT('https://www.myphdidea.org/index.php?cmpgn=',c1.cmpgn_id) AS link,
	c1.cmpgn_time_firstsend AS time
	FROM cmpgn c1 JOIN user u ON (u.user_id=c1.cmpgn_user)
	WHERE c1.cmpgn_time_firstsend IS NOT NULL AND c1.cmpgn_displayinfeed='1'".$recentcondit
	." UNION SELECT CONCAT(p.prof_givenname,' ',p.prof_familyname) AS name, CONCAT('On ',
	c2.cmpgn_title) AS description, CONCAT('https://www.myphdidea.org/index.php?prof=',p.prof_id) AS link,
	c2.cmpgn_time_finalized AS time
	FROM review r
	JOIN cmpgn c2 ON (r.review_id=c2.cmpgn_rvw_favourite)
	JOIN prof p ON (r.review_prof=p.prof_id)
	JOIN user u ON (u.user_id=c2.cmpgn_user)
	WHERE c2.cmpgn_time_finalized IS NOT NULL".$recentcondit." ORDER BY time DESC LIMIT 10";*/

$ch_descr="Ideas and Reviews";
if(empty($_GET['type'])) $sql=$sql_idea." UNION ".$sql_prof." ORDER BY time DESC LIMIT 15";
else switch($_GET['type'])
{
	case 'idea': $sql=$sql_idea." ORDER BY time DESC LIMIT 10"; $ch_descr="Ideas"; break;
	case 'prof': $sql=$sql_prof." ORDER BY time DESC LIMIT 10"; $ch_descr="Reviews"; break;
	case 'feat': $sql=$sql_feat." ORDER BY time DESC LIMIT 10"; $ch_descr="Feature articles"; break;
	case 'upload': $sql=$sql_upload." ORDER BY time DESC LIMIT 10"; $ch_descr="Uploads (non-vetted)"; break;
	default: $sql_idea." UNION ".$sql_prof." ORDER BY time DESC LIMIT 15"; break;
}
	
$result_recent=$conn->query($sql);

$rssfeed = '<?xml version="1.0" encoding="utf-8"?>';
$rssfeed .= '<rss version="2.0">';
$rssfeed .= '<channel>';
$rssfeed .= '<title>myphidea.org RSS feed</title>';
$rssfeed .= '<link>https://www.myphidea.org</link>';
$rssfeed .= '<description>'.$ch_descr.' from myphdidea.org</description>';
$rssfeed .= '<language>en-us</language>';
$rssfeed .= '<copyright>CC BY SA 4.0 International</copyright>';

 
while($row = $result_recent->fetch_assoc()) {
		if(empty($row['description'])) $row['description']=$row['name'];
        $rssfeed .= '<item>';
        $rssfeed .= '<title>' .$row['name']. '</title>';
        $rssfeed .= '<description>'.$row['description'].'</description>';
        $rssfeed .= '<link>' . $row['link'] . '</link>';
        $rssfeed .= '<pubDate>' . date("D, d M Y H:i:s O", strtotime($row['time'])) . '</pubDate>';
        $rssfeed .= '</item>';
    }
 
$rssfeed .= '</channel>';
$rssfeed .= '</rss>';
 
echo $rssfeed;

$conn->close();
?>