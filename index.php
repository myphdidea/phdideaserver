<?php ob_start()?>
<!DOCTYPE html>
<html>
  <head>
	<meta name="description" content="Open academic journal for students and their research project ideas, with a crowd-sourced editor system for requesting reviews from professors.">
	<meta name="keywords" content="open publishing platform, crowd-sourced academic journal, post-publication peer review, rate my professor, undergraduate journal, crowdfund science">
	<meta name="author" content="myphdidea">
	<meta name="viewport" content="device-width=900px, initial-scale=0.4" />
	<meta content="text/html; charset=utf-8" http-equiv="content-type">
    <link rel="alternate" href="rss.php" title="myphdidea.org RSS feed" type="application/rss+xml" />
	<link rel="icon" type="image/x-icon" href="favicon.ico?" />
	<link rel="shortcut icon" type="image/x-icon" href="favicon.ico?" />
    <title>myphdidea.org</title>
    <link href="phdideastyle.css?"
      rel="stylesheet" type="text/css">
  </head>
  <body style="clear: none;">
    <?php include("includes/header.php"); ?>
    <div id="maincontainer">
		<?php
			session_start();
			
			if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 3600)) {
    		// last request was more than 30 minutes ago
    			session_unset();     // unset $_SESSION variable for the run-time 
    			session_destroy();   // destroy session data in storage
			}
			$_SESSION['LAST_ACTIVITY'] = time(); // update last activity time stamp
			
			if(isset($_SESSION['prof']))
				include("includes/sidebar_profdesk.php");
			elseif(isset($_SESSION['user']))
				include("includes/sidebar_workbench.php");
			else include("includes/sidebar_login.php");
		?>
		<?php
		if(isset($_GET['page']))
			include "includes/centerpage_".test($_GET['page']).".php";
		else if(isset($_GET['workbench']))
			include "includes/centerpage_workbench_".test($_GET['workbench']).".php";		
		else if(isset($_GET['profdesk']))
			include "includes/centerpage_profdesk_".test($_GET['profdesk']).".php";		
		else if(isset($_GET['cmpgn']))
			include "includes/centerpage_campaign.php";
		else if(isset($_GET['feat']))
			include "includes/centerpage_feature.php";
		else if(isset($_GET['prof']))
			include "includes/centerpage_professor.php";
		elseif(isset($_GET['register']))
			include "includes/centerpage_register_".test($_GET['register']).".php";
		elseif(isset($_GET['confirm']))
			include 'includes/confirm_'.test($_GET['confirm']).'.php';
		elseif(isset($_SESSION['user']))
			if($_SESSION['user']==1)
				include("includes/centerpage_workbench_superuser.php");
			else include("includes/centerpage_workbench_activetasks.php");
		elseif(isset($_SESSION['prof']))
			include("includes/centerpage_profdesk_activities.php");
		else include("includes/centerpage_home.php");
		?>
    </div>
	<?php include("includes/footer.php"); ?>
  </body>
</html>
<?php ob_end_flush();?>