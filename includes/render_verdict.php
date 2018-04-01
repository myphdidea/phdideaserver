<?php
					if(!isset($verdict))
					{
						if(test($_POST['appr_or_decl'])=="appr")
							$verdict="'1'";
						else $verdict="'0'";
					}
					
					$sql="UPDATE taskentrusted SET taskentrusted_completed='1' WHERE taskentrusted_to='$student_id' AND taskentrusted_task='$verdict_task'";
					$conn->query($sql);
					
					switch($_SESSION['user'])
					{
						case $moderators_first_user:
							$sql="UPDATE verdict SET verdict_1st=$verdict, verdict_time1=NOW() WHERE verdict_id='$verdict_id'";
							break;
						case $moderators_second_user:
							$sql="UPDATE verdict SET verdict_2nd=$verdict, verdict_time2=NOW() WHERE verdict_id='$verdict_id'";
							break;
						case $moderators_third_user:
							$sql="UPDATE verdict SET verdict_3rd=$verdict, verdict_time3=NOW() WHERE verdict_id='$verdict_id'";
							break;
					}
					
					$conn->query($sql);

					$sql="SELECT verdict_type FROM verdict WHERE verdict_time1 IS NOT NULL AND verdict_time2 IS NOT NULL AND verdict_time3 IS NOT NULL AND verdict_id='$verdict_id'";
					$result=$conn->query($sql);
					
					//SET BACKUP ONLY
					if(isset($verdict_type) && $verdict_type=='USER')
					{
						if($conn->query("SELECT 1 FROM student WHERE student_backuponly IS NULL AND student_initauth_verdict='$verdict_id'")->num_rows)
							$conn->query("UPDATE student SET student_backuponly='$third_year' WHERE student_initauth_verdict='$verdict_id'");
						elseif($result->num_rows == 0)//i.e. IN CASE THIS IS NOT FINAL VOTE
							$conn->query("UPDATE student SET student_backuponly=NULL WHERE student_backuponly!='$third_year' AND student_initauth_verdict='$verdict_id'");
						elseif($conn->query("SELECT 1 FROM student WHERE student_backuponly!='$third_year' AND student_initauth_verdict='$verdict_id'")->num_rows)
							$third_year=!$third_year;
					}
					
					
					if($result->num_rows > 0)
					{
						$row=$result->fetch_assoc();
						$verdict_type=$row['verdict_type'];
						
						//set task_completed, update verdict_summary, give minus points, send notifications
						$sql="UPDATE task SET task_time_completed=NOW() WHERE task_id='$verdict_task'";
						$conn->query($sql);
						
						$sql="SELECT verdict_1st, verdict_2nd, verdict_3rd, IFNULL(verdict_1st,0)+IFNULL(verdict_2nd,0)+IFNULL(verdict_3rd,0) AS verdict_sum FROM verdict WHERE verdict_id='$verdict_id'";
						$row=$conn->query($sql)->fetch_assoc();
						$verdict_sum=$row['verdict_sum'];
						$verdict_1st=!empty($row['verdict_1st']);
						$verdict_2nd=!empty($row['verdict_2nd']);
						$verdict_3rd=!empty($row['verdict_3rd']);
						

						//-------
						if($verdict_type=='SEND' && $verdict_sum >= 2)
						{
							//UPDATE PROF RECORD (NOTABLY, INSTITUTION)
							$sql="SELECT i.institution_id, i.institution_emailsuffix, i.institution_country, s.send_prof,
								s.send_prof_givenname, s.send_prof_familyname, s.send_resbox FROM institution i
								JOIN send s ON (i.institution_id=s.send_prof_institution) WHERE s.send_verdict='$verdict_id'";
							$row=$conn->query($sql)->fetch_assoc();
							$instit_id=$row['institution_id'];
							$instit_country=$row['institution_country'];//RATHER USE COUNTRY INFORMATION FROM ORCID PROFILE?
							$email_suffix=$row['institution_emailsuffix'];
							$insert_prof_id=$row['send_prof'];
							$insert_gname=$row['send_prof_givenname'];
							$insert_fname=$row['send_prof_familyname'];
							if(!empty($row['send_resbox']))
								$insert_resbox="'".$row['send_resbox']."'";
							else $insert_resbox="NULL";
							
							//CHECK PROF
							$sql="SELECT crowdedit_email, COUNT(*) AS vote_nb FROM crowdedit WHERE crowdedit_task='$verdict_task' GROUP BY crowdedit_email ORDER BY vote_nb DESC";
							$result=$conn->query($sql);
							if($result->num_rows > 0)
							{
								$prof_email_main=$prof_email_alt="";
								while($row=$result->fetch_assoc())
									if($row['vote_nb'] >= 2)
									{
										if(empty($prof_email_main))
										{
											$prof_email_main=$row['crowdedit_email'];
											//REWARD STUDENTS HERE
											$sql="UPDATE user u JOIN student s ON (u.user_id=s.student_user_id)
												JOIN crowdedit c ON (c.crowdedit_student=s.student_id)
												SET u.user_pts_misc=u.user_pts_misc+1 WHERE c.crowdedit_email='$prof_email_main'";
											$conn->query($sql);
										}
										elseif(empty($prof_email_alt))
											$prof_email_alt=$row['crowdedit_email'];
										elseif(strpos($prof_email_alt,$email_suffix)!==false)
											$prof_email_alt=$row['crowdedit_email'];
									}

								if(!empty($prof_email_main))
								{
									//NOW, ANALYZE prof_id
									$sql="SELECT crowdedit_prof, COUNT(*) AS vote_nb FROM crowdedit WHERE crowdedit_prof IS NOT NULL AND crowdedit_task='$verdict_task' GROUP BY crowdedit_prof ORDER BY vote_nb DESC";
									$row=$conn->query($sql)->fetch_assoc();
									if(!empty($row['vote_nb']) && $row['vote_nb']>=2)
									{
										//2 MODERATORS AGREE -> CHOOSE THIS PROF PROFILE
										$chosen_prof=$row['crowdedit_prof'];
										
										//PENALIZE SEND STUDENT IF HE GOT IT WRONG
										if($insert_prof_id!=$row['crowdedit_prof'])
										{
											$sql="UPDATE user u SET u.user_pts_fail=u.user_pts_fail+1
												JOIN cmpgn c ON (c.cmpgn_user=u.user_id)
												JOIN upload ul ON (c.cmpgn_id=ul.upload_cmpgn)
												JOIN send s ON (s.send_upload=ul.upload_id) WHERE s.send_id='$send_id'";
											$conn->query($sql);
										}
									}
									else
									{
										$sql="SELECT s.send_prof FROM crowdedit c JOIN send s ON (s.send_prof=c.crowdedit_prof) WHERE s.send_id='$send_id' AND crowdedit_task='$verdict_task'";

										$row=$conn->query($sql)->fetch_assoc();
										if(!empty($row['send_prof']))
										{
											//STUDENT AND 1X MODERATOR AGREE -> CHOOSE THIS PROFILE
											$chosen_prof=$row['send_prof'];
										}
										elseif(empty($insert_prof_id))
										{
											//COULD NOT FIND PROFILE -> IF SO REQUESTED, CREATE NEW ONE WITHOUT ORCID
//											if(empty($insert_resbox))
//												$insert_resbox="NULL";
//											else $insert_resbox="'".$insert_resbox."'";
											$sql="INSERT INTO prof (prof_givenname, prof_familyname, prof_institution, prof_country,
												prof_resbox, prof_email, prof_email_alt) VALUES ('$insert_gname','$insert_fname',
												'$instit_id','$instit_country',$insert_resbox,'$prof_email_main','$prof_email_alt')";
											$conn->query($sql); echo $sql;
											
											$sql="SELECT LAST_INSERT_ID();";
											$chosen_prof=$conn->query($sql)->fetch_assoc();
											$chosen_prof=$chosen_prof['LAST_INSERT_ID()'];
											
											$conn->query("UPDATE send SET send_prof='$chosen_prof' WHERE send_id='$send_id'");
										}
										else
										{
											$verdict_sum=1;
											//PENALIZE STUDENT
											$sql="UPDATE user u SET u.user_pts_fail=u.user_pts_fail+1
												JOIN cmpgn c ON (c.cmpgn_user=u.user_id)
												JOIN upload ul ON (c.cmpgn_id=ul.upload_cmpgn)
												JOIN send s ON (s.send_upload=ul.upload_id) WHERE s.send_id='$send_id'";
											$conn->query($sql);
										}
									}

									//CHECK THAT NOT SAME REVIEW PREVIOUSLY
									if($verdict_sum >= 2 && !empty($chosen_prof))
									{
										$sql="SELECT 1 FROM review r
											JOIN upload u2 ON (r.review_upload=u2.upload_id)
											JOIN upload u1 ON (u2.upload_cmpgn=u1.upload_cmpgn)
											JOIN send s ON (s.send_upload=u1.upload_id)
											WHERE s.send_id='$send_id' AND r.review_prof='$chosen_prof'";
										if($conn->query($sql)->num_rows > 0)
											$verdict_sum=1;
									}

									if($verdict_sum >= 2 && !empty($chosen_prof))
									{
										$sql="SELECT autoedit_email, autoedit_email_auth FROM autoedit WHERE autoedit_prof='".$chosen_prof."'";
										$result=$conn->query($sql);
										if($result->num_rows == 0)
											$sql="UPDATE prof SET prof_email='$prof_email_main',
												prof_email_alt='$prof_email_alt', prof_institution='$instit_id',
												prof_resbox=$insert_resbox, prof_description='' WHERE prof_id='".$chosen_prof."'";
										else
										{
											//ADD autoedit email
/*											$row=$result->fetch_assoc();
											if(!empty($row['autoedit_email_auth']) && $row['autoedit_email']!=$prof_email_main && $row['autoedit_email']!=$prof_email_alt)
												$autoedit_email=$row['autoedit_email'];*/
											$sql="UPDATE prof SET prof_email='$prof_email_main',
												prof_email_alt='$prof_email_alt' WHERE prof_id='".$chosen_prof."'";
										}
										$conn->query($sql);

										//FINALLY, CREATE REVIEW
										$sql="INSERT INTO review (review_upload, review_send, review_prof) VALUES
											('$upload_id','$send_id','$chosen_prof')";
										$conn->query($sql);
									}
								}
								else $verdict_sum=1;
							}
							else $verdict_sum=1;//i.e. PUNISH FOR NOT FINDING EMAIL
							
						}
						//-------
						
						if($verdict_sum >= 2)
							$verdict_summary=1;
						else $verdict_summary=0;
												
						switch($verdict_type)
						{
							case 'FTR':
								if($verdict_summary=='1')
								{
									//GIVE REWARDS
									$row=$conn->query("SELECT m.moderators_id, m.moderators_first_user, m.moderators_second_user, m.moderators_third_user,
										m.moderators_time_joined1, m.moderators_time_joined2, m.moderators_time_joined3, s.student_user_id
										FROM moderators m JOIN feature f ON (m.moderators_group=f.feature_moderators_group)
										JOIN student s ON (s.student_id=f.feature_student)
										WHERE f.feature_id='$feat_id' ORDER BY m.moderators_id DESC")->fetch_assoc();
									if(strtotime($row['moderators_time_joined1']."+ 1 month") < strtotime("now")) $conn->query("UPDATE student SET student_pts_feat=student_pts_feat+1 WHERE student_user_id='".$row['moderators_first_user']."'");
									if(strtotime($row['moderators_time_joined2']."+ 1 month") < strtotime("now")) $conn->query("UPDATE student SET student_pts_feat=student_pts_feat+1 WHERE student_user_id='".$row['moderators_second_user']."'");
									if(strtotime($row['moderators_time_joined3']."+ 1 month") < strtotime("now")) $conn->query("UPDATE student SET student_pts_feat=student_pts_feat+1 WHERE student_user_id='".$row['moderators_third_user']."'");
									$conn->query("UPDATE student SET student_pts_feat=student_pts_feat+1, student_feat_shadowed_latest=NULL
										WHERE student_feat_shadowed_latest='$feat_id'");
									$conn->query("UPDATE student s JOIN feature f ON (f.feature_student=s.student_id) SET s.student_feat_own_latest=NULL WHERE f.feature_id='$feat_id'");
									$conn->query("UPDATE feature SET feature_time_approved=NOW() WHERE feature_id='$feat_id'");
									array_map('unlink', glob("user_data/tmp_feat/".$row['student_user_id']."_*.*"));
								}
								$sql="UPDATE featuretext SET featuretext_verdict_summary='$verdict_summary' WHERE featuretext_verdict_summary IS NULL AND featuretext_feature='$feat_id'";
								break;
							case 'UPLOAD':
								if(!empty($interacts))
								{
									if($verdict_sum == 3 || $verdict_sum == 0)
										$conn->query("UPDATE user SET user_pts_misc=user_pts_misc+1 WHERE user_id='$moderators_first_user' OR user_id='$moderators_second_user' OR user_id='$moderators_third_user'");
									if($verdict_sum >=2)
										$conn->query("UPDATE cmpgn SET cmpgn_time_firstsend=NOW(), cmpgn_time_finalized=NOW() WHERE cmpgn_id='$cmpgn_id'");
									else $conn->query("UPDATE cmpgn SET cmpgn_time_finalized=NOW() WHERE cmpgn_id='$cmpgn_id'");
									$conn->query("DELETE FROM watchlist WHERE watchlist_moderators='$verdict_moderators'");
									rvw_to_newsfeeds($conn,$cmpgn_id);
								}
								$sql="UPDATE upload SET upload_verdict_summary='$verdict_summary' WHERE upload_id='$upload_id'";
								break;
							case 'SEND':
								$sql="UPDATE send SET send_verdict_summary='$verdict_summary' WHERE send_id='$send_id'";
								break;
							case 'RVW':
								if($verdict_summary==0 && is_null($row['verdict_1st'])+is_null($row['verdict_2nd'])+is_null($row['verdict_3rd']) >=2)
									$review_grade=0;
								else $review_grade=$verdict_summary+1;

								//choose new favourite
								if(empty($rvw_favourite) && $review_grade=='2' && empty($r_hideifgood))
								{
									$conn->query("UPDATE cmpgn SET cmpgn_rvw_favourite='$r_id' WHERE cmpgn_id='$cmpgn_id'");
								}
								
								$sql="UPDATE review SET review_grade='$review_grade' WHERE review_id='$r_id'";
								//should send additional notification to professor!
								send_profnotif($conn,$r_prof,3,'Review grade','Your review has now been graded ...','','');
								break;
							case 'USER':
								if($verdict_sum == 3 || $verdict_sum == 0) $conn->query("UPDATE user SET user_pts_misc=user_pts_misc+1 WHERE user_id='$moderators_first_user' OR user_id='$moderators_second_user' OR user_id='$moderators_third_user'");
								//CLEAR EXISTING WATCHLISTS (UPDATE NEW ONES IN ACTIVE TASKS)
								$result=$conn->query("SELECT m1.moderators_id FROM moderators m1 JOIN moderators m2 ON (m1.moderators_group=m2.moderators_group)
									WHERE m2.moderators_id='$verdict_moderators'");
								while($row=$result->fetch_assoc())
									$conn->query("DELETE watchlist FROM watchlist WHERE watchlist_moderators='".$row['moderators_id']."'");
								//with the verdict completed, should fill up watchlists again
								update_watchlist($conn,'USER');

								$row_student=$conn->query("SELECT student_id FROM student WHERE student_initauth_verdict='$verdict_id'")->fetch_assoc();
								if(file_exists('user_data/transcripts/'.$row_student['student_id'].'.pdf')) unlink('user_data/transcripts/'.$row_student['student_id'].'.pdf');

								$sql="UPDATE student SET student_verdict_summary='$verdict_summary' WHERE student_verdict_summary IS NULL AND student_initauth_verdict='$verdict_id'";
								break;
						}
						$conn->query($sql);
						
						if($verdict_sum == 2)
						{
							if($verdict_1st=='0')
								$sql="UPDATE user SET user_pts_fail=user_pts_fail+1 WHERE user_id='$moderators_first_user'";
							elseif($verdict_2nd=='0')
								$sql="UPDATE user SET user_pts_fail=user_pts_fail+1 WHERE user_id='$moderators_second_user'";
							elseif($verdict_3rd=='0')
								$sql="UPDATE user SET user_pts_fail=user_pts_fail+1 WHERE user_id='$moderators_third_user'";
							$conn->query($sql);
						}
						elseif($verdict_sum == 1)
						{
							/*if($verdict_type=='SEND')
								$fail_pts=2;
							else */$fail_pts=1;
							if($verdict_1st=='1')
								$sql="UPDATE user SET user_pts_fail=user_pts_fail+".$fail_pts." WHERE user_id='$moderators_first_user'";
							elseif($verdict_2nd=='1')
								$sql="UPDATE user SET user_pts_fail=user_pts_fail+".$fail_pts." WHERE user_id='$moderators_second_user'";
							elseif($verdict_3rd=='1')
								$sql="UPDATE user SET user_pts_fail=user_pts_fail+".$fail_pts." WHERE user_id='$moderators_third_user'";
							$conn->query($sql);
						}

						$student_array=array($moderators_first_user,$moderators_second_user,$moderators_third_user,$owner);
						
						switch($verdict_type)
						{
							case 'FTR':
								if($verdict_sum >= 2)
									$text="The feature verdict on ".$feat_title." has resulted in acceptance! Hence, this feature has been published in the newsfeed.";
								else $text="The feature verdict on ".$feat_title." has resulted in refusal! Revisions will have to be made before this feature can be published.";
								break;
							case 'UPLOAD':
								if(!empty($interacts))
								{
									if($verdict_sum >= 2)
										$text="The verdict on ".$title." has resulted in acceptance! Hence, interaction links to the professors in question have been established.";
									else $text="The upload verdict on ".$title." has resulted in refusal! The campaign will stay in the database but links to professors will not be established.";
								}
								else
								{
									if($verdict_sum >= 2)
										$text="The upload verdict on ".$title." has resulted in acceptance! Hence, this campaign can now be sent out to professors.";
									else $text="The upload verdict on ".$title." has resulted in refusal! Revisions will have to be made before the material from this campaign can be forwarded to professors.";
								}
								break;
							case 'SEND':
								if($verdict_sum >= 2)
									$text="The send verdict for ".$prof_fullname." has resulted in acceptance! Hence, this particular professor can now be emailed from the send panel of the campaign.";
								else $text="The send verdict on ".$prof_fullname." has resulted in refusal! It is possible to revise the material and try another send, but most likely, mismatch between this professor and the subject is just too great.";
								break;
							case 'RVW':
								switch($review_grade)
								{
									case '2':
										$text="good! By way of reward, the professor now gets early access to all other reviewers and his review is a finalist for inclusion in the site newsfeed at campaign end (winner chosen by idea's author).";
										break;
									case '1':
										$text="mediocre!";
										break;
									case '0':
										$text="void!";
										break;
								}
								$text="The review verdict for Prof. ".$r_prof_familyname." has now been submitted! The consensus is that this review is: ".$text;
								break;
							case 'USER':
								if($third_year) $text="However, since the student is only in the 3rd year, a 6 month moratorium on launching new campaigns has been decreed."; else $text="";
								if($verdict_sum >= 2)
									$text="The student verdict for ".$student_givenname." ".$student_familyname." has resulted in acceptance! Everyone rejoice now that we can welcome a new member to our site. ".$text;
								else $text="The student verdict on ".$student_givenname." ".$student_familyname." has resulted in refusal! Likely, this student would be better served by other publication platforms at this career stage.";
								break;
						}
						
						foreach($student_array as $student_item)
						//HOW TO HANDLE APOSTATE STUDENTS?
						if(!empty($student_item))
						{
							//IF USER SETTINGS ALLOW, SEND EMAIL
							if($verdict_type=='USER' && !empty($owner) && $student_item==$owner)
								send_notification($conn, $student_item, 2, 'Verdict completed', $text,'','');
							elseif(!empty($owner) && $student_item==$owner)
								send_notification($conn, $student_item, 3, 'Verdict completed', $text,'','');
							else send_notification($conn, $student_item, 4, 'Verdict completed', $text,'','');
						}
						

					}
					header("Location: index.php?workbench=activetasks");
?>