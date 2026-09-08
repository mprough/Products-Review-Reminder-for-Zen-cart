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
	#tblMyReviews{
		margin:0 10px 20px 20px;
		min-width:90%;
	}
	#tblMyReviews td{
		padding:10px;
		border-bottom:solid 1px #EEE;
		border-top:solid 1px #EEE;
		text-align:left;
		vertical-align:top;
	}
	.product_image{
		width:<?php echo SMALL_IMAGE_WIDTH; ?>px;
	}
	.product_info a{
		font-size:16px;
		display:inline-block;
		margin-bottom:0;
	}
	.product_info p{
		font-size:14px;
		margin-top:0;
	}
	.product_info img{
		cursor:pointer;
	}
	.product_info textarea{
		width:100%;
	}
	.feedback{
		font-size:16px;
		margin:0 0 0 20px;
	}
	.feedback-posted{
		color:green;
	}
	.feedback-pending{
		color:#900;
	}
	.btnClass{
		border:solid 1px Silver;
		border-radius:5px;
		padding:5px 15px;
		float:right;
		background-color:#F8F8F8;
	}
	.btnClass:active{
		background-color:#EEE;
	}
	.clear-rating:hover{
		cursor:pointer;
		color:#F25603;
	}
	.lblRating{
		font-size:20px;
		margin:0 0 0 20px;
		vertical-align:bottom;
	}
	.txt-review{
		font-size:1.1em;
		line-height:1.4em;
		margin-top:20px;
	}
	#ajax-feedback{
		font-size:20px;
		margin:20px;
		color:red;
	}
	#email-optin{
		float:right;
		margin-top:5px;
	}
	#email-optin input[type='checkbox']{
		width:20px;
		height:20px;
		vertical-align:bottom;
	}
	#email-optin label{
		font-size:1.1em;
	}
	.reviewed-item{
		display:none;
	}
</style>
<?php
	#ADD JAVASCRIPT
	require( DIR_WS_MODULES . 'pages/' . $current_page_base . '/jscript_main.php' );
?>
<div class="centerColumn formPage" id="accountDefault">
	<?php if ($messageStack->size('addon_my_reviews') > 0) echo $messageStack->output('addon_my_reviews'); ?>
<?php
	if( isset($_GET['pid']) and (int)$_GET['pid']!='' ){
?>
	<input type="button" name="btnSwitchItemDisplay" id="btnSwitchItemDisplay" class="btnClass" value="<?php echo BTN_SHOW_REVIEWED; ?>" onclick="window.location='/index.php?main_page=addon_my_reviews';" style="margin:0 20px 0 0;" />
<?php
	}else{
?>
	<input type="button" name="btnSwitchItemDisplay" id="btnSwitchItemDisplay" class="btnClass" value="<?php echo BTN_SHOW_REVIEWED; ?>" onclick="switch_hide_review(this);" style="margin:0 20px 0 0;" />
<?php
	}
?>
	<h1 id="myReviewsDefaultHeading"><?php echo HEADING_TITLE; ?></h1>
<?php
	if( isset($_SESSION['customer_id']) and (int)$_SESSION['customer_id'] > 0 ){ #JUST MAKING SURE...
		$customer_id = zen_db_prepare_input( (int)$_SESSION['customer_id'] );
		#LOAD THE UPDATED CUSTOMER NAME
		$customer_name = '';
		$abreviated_name = '';
		$sql = "SELECT `customers_firstname`, `customers_lastname`
				FROM `" . TABLE_CUSTOMERS . "`
				WHERE `customers_id` = '" . $customer_id . "'";
		$rec = $db->Execute($sql);
		if( !$rec->EOF ){
			$customer_name = trim( $rec->fields['customers_firstname'] . ' ' . $rec->fields['customers_lastname'] );
			$abreviated_name = $rec->fields['customers_firstname'];
			if( trim($rec->fields['customers_lastname']) != "" )
				$abreviated_name .= ' ' . substr($rec->fields['customers_lastname'],0,1) . '.';
		}
		#GET PRODUCT ID (IF ANY)
		$pid = false;
		if( isset($_GET['pid']) and (int)$_GET['pid'] > 0 ){
			$pid = zen_db_prepare_input( (int)$_GET['pid'] );
		}
		#LOAD PRODUCTS ORDERED BY THE CUSTOMER
		$sql = "SELECT op.`products_id`, op.`products_name`, r.`reviews_rating`, p.`products_image`, m.`manufacturers_name`, r.`customers_name`, r.`status`, rd.`reviews_text`
				FROM `" . TABLE_ORDERS_PRODUCTS . "` AS op
					JOIN `" . TABLE_ORDERS . "` AS o
						ON op.`orders_id` = o.`orders_id`
					LEFT JOIN `" . TABLE_REVIEWS . "` AS r
						ON (r.`products_id` = op.`products_id` and r.`customers_id` = '" . $customer_id . "')
					LEFT JOIN `" . TABLE_REVIEWS_DESCRIPTION . "` AS rd
						ON r.`reviews_id` = rd.`reviews_id`
					LEFT JOIN `" . TABLE_PRODUCTS . "` AS p
						ON op.`products_id` = p.`products_id`
					LEFT JOIN `" . TABLE_MANUFACTURERS . "` AS m
						ON p.`manufacturers_id` = m.`manufacturers_id`
				WHERE o.`customers_id` = '" . $customer_id . "'";

		if( $pid ){
			$sql .= "
					AND p.`products_id` = '" . $pid . "'";
		}
		$sql .="
				GROUP BY op.`products_id`
				ORDER BY op.`products_name` ASC
				LIMIT 500";
		$rec = $db->Execute($sql);
		if(!$rec->EOF){ #START: IF ANY PRODUCT IS ELIGIBLE FOR REVIEW
?>
		<input type="hidden" name="customer_name" id="customer_name" value="<?php echo $abreviated_name; ?>" />
		<table id="tblMyReviews">
			<tbody>
<?php
			while(!$rec->EOF){
				#RATING
				$rating = false;
				$review = '';
				if( $rec->fields['reviews_rating'] != null ){
					$rating = (int)$rec->fields['reviews_rating'];
					$review = $rec->fields['reviews_text'];
				}

				#PRODUCT ID
				$pid = (int)$rec->fields['products_id'];

				#BUILD RATING PAGE LINK
				$rating_page_link = '<a href="' . zen_href_link(FILENAME_PRODUCT_REVIEWS_WRITE, 'products_id='. $pid ) . '">' . zen_image(DIR_WS_TEMPLATE . 'images/icon_edit.gif', '') . '</a>' . "\n";

				#BUILD RATING STARS
				if( $rating ){
					$rating_stars = '<div id="' . $pid . '_stars">' . "\n";
					for($i=0; $i < $rating; $i++){
						$rating_stars .= '<img src="' . DIR_WS_IMAGES . 'review_reminder/rating_star_on.png" />' . "\n";
					}
					for( $i; $i < 5; $i++ ){
						$rating_stars .= '<img src="' . DIR_WS_IMAGES . 'review_reminder/rating_star.png" />' . "\n";
					}

					$rating_stars .= '<span class="feedback">';
					if( $rec->fields['status'] == '1' ){
						$rating_stars .= '<span class="feedback-posted">&#10004; posted publicly as</span> '; $rating_stars .= $rec->fields['customers_name'] . ' | ';
					}else{
						$rating_stars .= '<span class="feedback-pending">Pending review.</span> | ';
					}

					$rating_stars .= '<span onclick="change_rating(' . $pid . ');" class="clear-rating">Change</span></span>' . "\n";

					$rating_stars .= '</div>';
				}else{ #CUSTOMER DID NOT RATE THIS PRODUCT YET
					$rating_stars = '<div id="' . $pid . '-stars">
						<img src="' . DIR_WS_IMAGES . 'review_reminder/rating_star.png" id="' . $pid . '-' . '1" onmouseover="show_rating(' . $pid . ', 1);" onmouseout="hide_rating(\'' . $pid . '\');" onclick="update_rating(\'' . $pid . '\', 1);" />

						<img src="' . DIR_WS_IMAGES . 'review_reminder/rating_star.png" id="' . $pid . '-' . '2" onmouseover="show_rating(' . $pid . ', 2);" onmouseout="hide_rating(\'' . $pid . '\');" onclick="update_rating(\'' . $pid . '\', 2);" />

						<img src="' . DIR_WS_IMAGES . 'review_reminder/rating_star.png" id="' . $pid . '-' . '3" onmouseover="show_rating(' . $pid . ', 3);" onmouseout="hide_rating(\'' . $pid . '\');" onclick="update_rating(\'' . $pid . '\', 3);" />

						<img src="' . DIR_WS_IMAGES . 'review_reminder/rating_star.png" id="' . $pid . '-' . '4" onmouseover="show_rating(' . $pid . ', 4);" onmouseout="hide_rating(\'' . $pid . '\');" onclick="update_rating(\'' . $pid . '\', 4);" />

						<img src="' . DIR_WS_IMAGES . 'review_reminder/rating_star.png" id="' . $pid . '-' . '5" onmouseover="show_rating(' . $pid . ', 5);" onmouseout="hide_rating(\'' . $pid . '\');" onclick="update_rating(\'' . $pid . '\', 5);" />
						<span id="' . $pid . '-lblRating" class="lblRating"></span>
					</div>' . "\n";

				}
?>
				<tr<?php if($rating) echo ' class="reviewed-item"'; ?>>
					<td class="product_image">
						<?php echo zen_get_products_image($pid); ?>
						<input type="hidden" name="<?php echo $pid; ?>-rating" id="<?php echo $pid; ?>-rating" value="<?php echo $rating; ?>" />
					</td><td class="product_info">
						<?php echo '<a href="' . zen_href_link(FILENAME_PRODUCT_INFO, 'products_id='. $pid ) . '">' . $rec->fields['products_name'] . '</a>'; ?>
						<p><?php echo $rec->fields['manufacturers_name']; ?></p>
						<div id="<?php echo $pid; ?>-stars"><?php echo $rating_stars; ?></div>

						<textarea name="<?php echo $pid; ?>-review"  id="<?php echo $pid; ?>-review" style="display:none;" placeholder="Write your review here" onkeyup="review_status(this, <?php echo $pid; ?>);"><?php echo $review; ?></textarea>

						<?php if( $review != '' ) echo '<div class="txt-review" id="txt-review-' . $pid . '">' . nl2br(htmlspecialchars((string)$review, ENT_QUOTES, CHARSET)) . '</div>'; ?>

						<input type="button" name="btnSave-<?php echo $pid; ?>"  id="btnSave-<?php echo $pid; ?>" class="btnClass" onclick="update_rating('<?php echo $pid; ?>');" style="display:none;" value="Submit" />
					</td>
				</tr>
<?php
				$rec->MoveNext();
			}
?>
		</table>
		<div id="ajax-feedback"></div>
<?php
		}else{ #NO PRODUCT IS ELIGIBLE FOR REVIEW
?>
	<p style="margin:20px;"><?php echo PRODUCT_NOT_ELIGIBLE_FOR_REVIEW; ?></p>
<?php
		}
	} #END IF CUSTOMER LOGGED IN
?>
	<div id="bottomButtons">
		<div class="back leftRightMargin">
			<a href="<?php echo zen_back_link(true); ?>" class="genericButtonSprite"><span>Back</span></a>
<?php
	$sql = "SELECT `customers_id`
			FROM `" . TABLE_ADDON_REVIEW_REMINDER_OPTOUT . "`
			WHERE `customers_id` = '" . zen_db_prepare_input( (int)$_SESSION['customer_id'] ) . "'";
	$rec = $db->Execute($sql);
	if( !$rec->EOF ){ #START: CUSTOMER OPTED OUT
?>
			<span id="email-optin">
				<input type="checkbox" name="cbxOptIn" id="cbxOptIn" onclick="reminder_signup();" /> <label for="cbxOptIn"><?php echo OPT_IN_OPTION; ?></label>
			</span>
<?php
	}
?>
		</div>
	</div>
</div>
