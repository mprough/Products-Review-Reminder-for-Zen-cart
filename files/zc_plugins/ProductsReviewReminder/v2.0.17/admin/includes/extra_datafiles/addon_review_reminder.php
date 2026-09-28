<?php
/**
 * @package addon_review_reminder
 * @copyright Copyright 2003-2017 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: addon_my_reviews.php 2024/27/08 PRO-Webs.net v.1.2 $
 * @author: Will Davies Vasconcelos <willvasconcelos@outlook.com> $
 */

if (!defined('TABLE_ADDON_REVIEW_REMINDER_LOG')) {
    define('TABLE_ADDON_REVIEW_REMINDER_LOG', DB_PREFIX . 'addon_review_reminder_log');
}
if (!defined('TABLE_ADDON_REVIEW_REMINDER_OPTOUT')) {
    define('TABLE_ADDON_REVIEW_REMINDER_OPTOUT', DB_PREFIX . 'addon_review_reminder_optout');
}
