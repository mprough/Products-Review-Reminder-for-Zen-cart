<?php
/**
 * @package addon_review_reminder
 * @copyright Copyright 2003-2017 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: addon_my_reviews.php 2017-07-12 08:00 v.1.0 $
 * @author Will Vasconcelos willvasconcelos@outlook.com $
 */
?>
<style type="text/css">
	#optOutMainContent{
		margin:20px;
	}
	#btnOptOut{
		border:solid 1px Silver;
		border-radius:5px;
		padding:5px 15px;
		background-color:#F8F8F8;
	}
	#btnOptOut:hover{
		background-color:#F25603;
		color:white;
	}
	#btnOptOut:active{
		background-color:#333;
	}
</style>
<div class="centerColumn formPage" id="accountDefault">
	<h1 id="myReviewsDefaultHeading"><?php echo HEADING_TITLE; ?></h1>
	<div id="optOutMainContent" class="content">
<?php
	$sql = "SELECT `customers_id`
			FROM `" . TABLE_ADDON_REVIEW_REMINDER_OPTOUT . "`
			WHERE `customers_id` = '" . (int)$customerId . "'";
	$rec = $db->Execute($sql);
	if( $rec->EOF ){ #START: CUSTOMER NOT OPTED OUT YET
		echo zen_draw_form('frmOptOut', $_SERVER['REQUEST_URI']);
?>
		<h2><?php echo OPT_OUT_SUBTITLE; ?></h2>
		<p><?php echo OPT_OUT_MESSAGE; ?></p>
		<input type="submit" name="btnOptOut" id="btnOptOut" value="<?php echo OPT_OUT_BUTTON; ?>" />
		<input type="hidden" name="optOutToken" value="<?php echo hash('sha256', 'review-reminder|' . (int)$customerId . '|' . session_id()); ?>" />
		<p><?php echo OPT_OUT_DISCLAIMER; ?></p>
		</form>
<?php
	}else{ #CUSTOMER HAS ALREADY OPTED OUT
?>
		<p><?php echo OPTED_OUT; ?></p>
		<p><?php echo OPT_OUT_DISCLAIMER; ?></p>
<?php
	}
?>
	</div>
</div>
