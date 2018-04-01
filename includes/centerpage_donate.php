<?php
if(isset($_POST['change_currency']))
	$curr_button=test($_POST['currency']);
else $curr_button='BHA4PNABM98SA';
?>
<div id="centerpage">
	Thank you for visiting the donate page, you are a good person! We accept donations either
	via PayPal or Bitcoin. Money donated will go towards paying for server costs, site
	maintenance and continuing development, and we eventually hope to be able
	to pay salaries for regular employees.<br><br>
	<div class="indentation">
	
<!--	<a href="https://blockchain.info/address/1MToe1xeDVJMCgVMNWirYr2E2AP6tJeWbX" style="float: right; margin-right: 20px"><img src="https://blockchain.info/Resources/buttons/donate_64.png"></a>-->
	
	<div style="font-size:16px;margin:0 auto;width:300px; float: right" class="blockchain-btn"
     data-address="1MToe1xeDVJMCgVMNWirYr2E2AP6tJeWbX"
     data-shared="false">
    <div class="blockchain stage-begin">
        <img src="https://blockchain.info/Resources/buttons/donate_64.png"/>
    </div>
    <div class="blockchain stage-loading" style="text-align:center">
        <img src="https://blockchain.info/Resources/loading-large.gif"/>
    </div>
    <div class="blockchain stage-ready">
         <p align="center">Please Donate To Bitcoin Address: <b>[[address]]</b></p>
         <p align="center" class="qr-code"></p>
    </div>
    <div class="blockchain stage-paid">
         Donation of <b>[[value]] BTC</b> Received. Thank You.
    </div>
    <div class="blockchain stage-error">
        <font color="red">[[error]]</font>
    </div>
</div>
	
	<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
	<input type="hidden" name="cmd" value="_s-xclick">
	<input type="hidden" name="hosted_button_id" value="<?php echo $curr_button; ?>">
	<input type="image" src="https://www.paypalobjects.com/en_US/GB/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
	<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
	</form>
	<form method="post" action="">
	<select name="currency" style="margin-left: 20px">
		<option value="BHA4PNABM98SA" <?php if($curr_button=='BHA4PNABM98SA') echo 'selected'; ?> >EUR</option>
		<option value="TSUT7K3TY7HTC" <?php if($curr_button=='TSUT7K3TY7HTC') echo 'selected'; ?> >USD</option>
		<option value="C3PDX2GJJ3FE6" <?php if($curr_button=='C3PDX2GJJ3FE6') echo 'selected'; ?> >GBP</option>
		<option value="D2UBRBQ23XAAS" <?php if($curr_button=='D2UBRBQ23XAAS') echo 'selected'; ?> >CHF</option>
		<option value="MDBAGWA5NL8R8" <?php if($curr_button=='MDBAGWA5NL8R8') echo 'selected'; ?> >JPY</option>
	</select>
	<button name="change_currency">Change</button>
	</form>
	</div><br>
As the organization develops, expect this page
	to contain a breakdown of how much was donated and how it was spent, for full transparency.
</div>

<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.8.0/jquery.min.js"></script>
<script type="text/javascript" src="https://blockchain.info/Resources/js/pay-now-button.js"></script>

