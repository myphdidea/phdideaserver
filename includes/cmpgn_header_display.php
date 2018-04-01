        <h2><?php echo $title;?></h2>
<!--        <?php if(!empty($error_msg)) echo '<div style="color: red; text-align: center">'.$error_msg.'</div><br>';?>-->
        <?php if(empty($time_firstsend) && !empty($time_finalized))
						$launched="approval not obtained";
					  elseif(empty($time_firstsend))
        				$launched="not yet approved";
					  else $launched="approved ".$time_firstsend;
					  $date_queue=" (Created ".$time_launched.", ".$launched.", ";
        			  if($isarchivized) $date_queue=$date_queue."archivized)";
        					 else if(empty($time_finalized)) $date_queue=$date_queue."still running)";
        					 else $date_queue=$date_queue."finalized <br>".$time_finalized.")";
        			  if($revealtoprof && $visitor_isowner)
					  	$date_queue='[Author: '.$printname_prof.' <i>(as seen by review prof)</i>]<br>'.$date_queue;
					  
					  if(!$isarchivized && empty($time_finalized) && (empty($visitor_isreviewprof) || !$revealtoprof))
        				echo 'Author: <i>We do not publicly disclose author names before the campaign is finished</i> '.$date_queue;
					  elseif($isarchivized && $conn->query("SELECT 1 FROM upload WHERE upload_verdict_summary IS NOT NULL AND upload_cmpgn='$cmpgn_id'")->num_rows==0)
					  	echo 'Author: <i>We do not publicly disclose author names while approval is pending.</i> '.$date_queue;
        			  else echo 'Author: '.$printname.'<br>'.$date_queue; 
       	?>
