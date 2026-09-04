<?php
/**
 * @package addon_review_reminder
 * @copyright Copyright 2003-2017 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: addon_my_reviews.php 2017-07-12 08:00 v.1.0 $
 * @author Will Vasconcelos willvasconcelos@outlook.com $
 */

const EMAIL_REMINDER_SUBJECT = ', how would you rate your recent order at ' . STORE_NAME . '?';
const EMAIL_REMINDER_ITEM_TITLE = 'How did this item meet your expectations?';
const EMAIL_REMINDER_ITEMS_TITLE = 'How did those items meet your expectations?';
const EMAIL_REMINDER_START_RATING = 'Start by rating it';
const EMAIL_REMINDER_REVIEW_PURCHASES_TITLE = 'Review your purchases';
const EMAIL_REMINDER_REVIEW_PURCHASES_DESCRIPTION = 'Check out <a href="' . HTTPS_SERVER . '/index.php?main_page=addon_my_reviews">' . HTTPS_SERVER . '/index.php?main_page=addon_my_reviews</a> to find past purchases to review.';
const EMAIL_REMINDER_DISCLAIMER = 'We hope you found this message to be useful. If you would rather not receive future e-mails of this sort from <a href="' . HTTPS_SERVER . '/">' . HTTPS_SERVER . '</a>, please opt-out <a href="' . HTTPS_SERVER . '/index.php?main_page=addon_reviews_reminder_optout&e=%s">here</a>.';
// standard email disclaimers
const EMAIL_DISCLAIMER = 'This email address was given to us by you or by one of our customers. If you feel that you have received this email in error, please send an email to %s ';
const EMAIL_SPAM_DISCLAIMER = 'This email is sent in accordance with the US CAN-SPAM Law in effect 01/01/2004. Removal requests can be sent to this address and will be honored and respected.';
const EMAIL_FOOTER_COPYRIGHT = 'Copyright (c) ' . date('Y') . ' <a href="' . HTTPS_SERVER . '" target="_blank">' . HTTPS_SERVER . '</a>.'."\n" . STORE_NAME_ADDRESS . "\n" . STORE_TELEPHONE_CUSTSERVICE . "\n" . 'Powered by <a href="' . HTTPS_SERVER . '" target="_blank">' . STORE_NAME . '</a>';
// email advisory for all emails customer generate
const EMAIL_ADVISORY = '-----' . "\n" . '<strong>IMPORTANT:</strong> For your protection and to prevent malicious use, all emails sent via this web site are logged and the contents recorded and available to the store owner. If you feel that you have received this email in error, please send an email to ' . STORE_OWNER_EMAIL_ADDRESS . "\n\n";