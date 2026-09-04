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

const INSPECTION_ORDER_LABEL = 'Inspect an email using order #';
const INSPECTION_HELP = 'Use any real order. Eligibility dates are ignored, and no reminder-log entry is created.';
const BUTTON_PREVIEW_EMAIL = 'Preview email';
const BUTTON_SEND_TEST_EMAIL = 'Send test to store owner';
const TEST_FORMAT_LABEL = 'Test email format';
const TEST_FORMAT_HTML = 'HTML';
const TEST_FORMAT_TEXT = 'Plain text';
const PREVIEW_HEADING = 'Email preview';
const SUCCESS_PREVIEW_READY = 'Preview generated from order #%d. Nothing was sent or recorded.';
const SUCCESS_TEST_EMAIL_SENT = 'Test email sent to %s using order #%d. The customer was not contacted and the order was not recorded.';
const ERROR_INSPECTION_ORDER_REQUIRED = 'Enter a valid order number to preview or test the email.';
const ERROR_INSPECTION_ORDER_NOT_FOUND = 'Order #%d was not found.';
const ERROR_INSPECTION_NO_PRODUCTS = 'Order #%d has no products available for this review reminder.';
const ERROR_TEST_EMAIL_NOT_SENT = 'Zen Cart did not report a successful test email send. Check the store email settings and email logs.';
