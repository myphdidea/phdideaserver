<?php
$upload_id=$conn->real_escape_string(test($_GET['upload']));
$result=$conn->query("SELECT c.cmpgn_id, c.cmpgn_title, COUNT(*) AS version FROM cmpgn c JOIN upload u1 ON (c.cmpgn_id=u1.upload_cmpgn)
	JOIN upload u2 ON (u1.upload_cmpgn=u2.upload_cmpgn) WHERE u2.upload_id='$upload_id' AND u1.upload_id <= u2.upload_id");
if($result->num_rows > 0)
{
	$row=$result->fetch_assoc();
	$title="<h2>Version ".$row['version'].' of <a href="index.php?cmpgn='.$row['cmpgn_id'].'">'.$row['cmpgn_title'].'</a></h2>';
}
?>
<div id="centerpage">
	<?php echo $title?>
	We try to timestamp all uploads so that ideas' authors can prove inventorship. Below you
	find a list of TSAs (trusted timestamping authorities) used for this upload, along with their certificates. Using the certificates,
	the (hash of the) original documents and the all-important tsr-files, with a cryptographic software like <a href="https://www.openssl.org/">OpenSSL</a>,
	it is possible to check that the below timestamps are correct (further details e.g. <a href="https://en.wikipedia.org/wiki/Trusted_timestamping">here</a>).<br><br>
	We recommend that the idea's author download a copy of all the documents below for personal safekeeping. Note also that
	we link to the latest certificates only, which may be different from those used for actual timestamping if files are older.
	<h3>Timestamps</h3>
	<?php
	$result=$conn->query("SELECT timestamp_authority FROM timestamp WHERE timestamp_upload='$upload_id' GROUP BY timestamp_authority ORDER BY IF(timestamp_authority='safestamper',0,1)");
	if($result->num_rows > 0)
	{
		while($authority=$result->fetch_assoc())
		{
			$authority=$authority['timestamp_authority'];
			switch($authority)
			{
				case 'freetsa':
					echo 'Service: <a href="https://www.freetsa.org">www.freetsa.org</a><br>Certificate: <a href="https://www.freetsa.org/files/cacert.pem">https://www.freetsa.org/files/cacert.pem</a><br>';
					break;
				case 'dfn':
					echo 'Service: <a href="https://www.pki.dfn.de/zeitstempeldienst/">zeitstempel.dfn.de</a><br>Certificate: <a href="https://pki.pca.dfn.de/global-services-ca/pub/cacert/chain.txt">https://pki.pca.dfn.de/global-services-ca/pub/cacert/chain.txt</a><br>';
					break;
				case 'safestamper':
					echo 'Service: <a href="https://tsa.safecreative.org">tsa.safecreative.org</a><br>
						Certificate: <a href="https://tsa.safecreative.org/certificate">https://tsa.safecreative.org/certificate</a><br>';
					break;
			}
			$authoritys_ts="";
			$result2=$conn->query("SELECT t.timestamp_id, t.timestamp_file_nb, t.timestamp_time, t.timestamp_hash,
				u.upload_file1, u.upload_file2, u.upload_file3 FROM timestamp t
				JOIN upload u ON (u.upload_id=t.timestamp_upload) WHERE t.timestamp_upload='$upload_id' AND t.timestamp_authority='$authority' ORDER BY t.timestamp_file_nb ASC");
			while($row=$result2->fetch_assoc())
				$authoritys_ts=$authoritys_ts.'<br>TSR: <a href="user_data/timestamps/'.$upload_id.'_'.$row['timestamp_file_nb'].'_'.$row['timestamp_id'].'.tsr">'.$row['upload_file'.$row['timestamp_file_nb']].".tsr</a><br>Time: ".date("Y-m-d H:i:s",$row['timestamp_time'])." (".$row['timestamp_time'].')<br>SHA-512 Hash: '.$row['timestamp_hash']."<br>";
			echo '<div class="indentation" style="word-break: break-all">'.$authoritys_ts.'</div><br>';
		}
	}
	else echo '<i>No timestamps found for this upload.</i>' 
	?>
</div>
