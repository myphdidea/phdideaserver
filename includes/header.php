<?php
include("includes/header_functions.php");
?>
<div id="header">
<a href="index.php">
<img id="logo" class="leftmargin" title="Our logo" alt="" src="images/logo_muted.png">
</a>
	  <form method="get" action="/index.php">
	  <a href="rss.php"><img src="images/rss_yellow_muted.png" style="float: right; margin-right: 30px; margin-top: 8px"></a>
<!--	  <a href="rss.php"><img src="images/fb_icon_yellow.png" style="float: right; margin-right: 10px; margin-top: 8px"></a>-->
	  <a href="https://twitter.com/myphdidea"><img src="images/twitter_icon_yellow_muted.png" style="float: right; margin-right: 6px; margin-top: 8px"></a>
      <ul id="search_bar">
        <li>Search:</li>
        <li><input id="mainsearchbox" name="searchstring" type="search" <?php if(isset($_GET['searchstring'])) echo 'value="'.test($_GET['searchstring']).'"'; ?> ></li>
        <li>Category:</li>
        <li>
          <select style="width: 100px;" name="category">
            <option value="res" <?php if(isset($_GET['category']) && $_GET['category']=='res') echo "selected";?> >Researchers</option>
            <option value="idea" <?php if(isset($_GET['category']) && $_GET['category']=='idea') echo "selected";?> >Campaigns</option>
            <option value="features" <?php if(isset($_GET['category']) && $_GET['category']=='features') echo "selected";?> >Features</option>
          </select>
        </li>
        <li><button name="page" value="search">Go!</button></li>
      </ul>
      </form>
      <br>
      <ul id="horz_bar">
      	<li class="dummy" style="height: 48px; margin-left: -30px;"><a style="width: 190px;" title="Our logo" href="index.php"></a></li>
        <li <?php if(!isset($_GET['page']) || $_GET['page']=="home") echo 'class="active"'?>><a title="Latest site-info" href="?page=home">Home</a></li>
        <li <?php if(isset($_GET['page']) && $_GET['page']=="ideas") echo 'class="active"'?>><a title="Recent Campaigns" href="?page=ideas">Ideas</a></li>
        <li <?php if(isset($_GET['page']) && $_GET['page']=="features") echo 'class="active"'?>><a title="Recent Feature Articles" href="?page=features">Features</a></li>
        <li <?php if(isset($_GET['page']) && $_GET['page']=="researchers") echo 'class="active"'?>><a title="Recent researcher profile updates" href="?page=researchers">Profs</a></li>
        <li <?php if(isset($_GET['page']) && $_GET['page']=="faq") echo 'class="active"'?>><a title="A how-to guide" href="?page=faq">FAQ</a></li>
        <li <?php if(isset($_GET['page']) && $_GET['page']=="about") echo 'class="active"'?>><a title="For first-time visitors" href="?page=about">About</a></li>
        <li <?php if(isset($_GET['page']) && $_GET['page']=="donate") echo 'class="active"'?>><a title="Support us" href="?page=donate">Donate</a></li>
      </ul>
</div>
</div>