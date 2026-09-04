<?php
/**
 * @package addon_review_reminder
 * @copyright Copyright 2003-2017 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: addon_my_reviews.php 2017-07-12 08:00 v.1.0 $
 * @author Will Vasconcelos willvasconcelos@outlook.com $
 */

const HEADING_TITLE = 'Product Review Reminder';
	/* Listing table */
const TBL_ORDER_ID = 'Order#';
const TBL_ORDER_DATE = 'Order Date';
const TBL_ORDER_STATUS_DATE = 'Effective';
const TBL_CUSTOMER_NAME = 'Customer Name';
const TBL_COMPANY_NAME = 'Company';
const TBL_PRODUCT_COUNT = 'Items';
const TBL_INFO = 'Info';

const TBL_PRODUCT_NAME = 'Item Name';
const TBL_PRODUCT_HAS_REVIEWS = 'Reviews';

const FEEDBACK_NO_RESULTS = 'There are no orders pending product review requests at this time.';
	#CONFIGURATION PANEL
const TBL_INFO_HEADER = 'Order\'s Packing List';
const REVIEW_REMINDER_CFG_AUTO_STATUS = 'Automatic Request: ';
const REVIEW_REMINDER_CFG_COOLOFF = 'Cool-off Period: ';
const REVIEW_REMINDER_CFG_MAX_PRODUCTS = 'Maximum Number of Products: ';
const REVIEW_REMINDER_CFG_LIST_ORDER = 'Product List Ordered By: ';
	
	#EMAIL CONTENT
const EMAIL_EXPECTATION_QUESTION = 'How did this item meet your expectations?';
const EMAIL_ACTION_CALL = 'Start by rating it';
const EMAIL_REVIEW_REMINDER_SUBJECT = '%s, did your recent order at %s meet your expectations?';

const EMAIL_REVIEW_TEXT_HEAD = "Thank you for shopping at %s\n\n";

const EMAIL_REVIEW_TEXT_FOOTER = "\n" . 'Review all your past purchases at: ' . HTTP_CATALOG_SERVER . '/index.php?main_page=addon_my_reviews
	
	We hope you found this message to be useful.
	If you\'d rather not receive future e-mails of this sort from ' . HTTP_CATALOG_SERVER . ' at %s, please opt-out at ' . HTTP_CATALOG_SERVER . '/index.php?main_page=addon_reviews_reminder_optout
	--
	%s
	%s';