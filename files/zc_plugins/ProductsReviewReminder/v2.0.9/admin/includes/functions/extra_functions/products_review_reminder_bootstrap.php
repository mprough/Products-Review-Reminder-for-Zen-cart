<?php

declare(strict_types=1);

if (!defined('IS_ADMIN_FLAG') || IS_ADMIN_FLAG !== true) {
    die('Illegal Access');
}

if (!defined('BOX_CONFIGURATION_REVIEW_REMINDER')) {
    define('BOX_CONFIGURATION_REVIEW_REMINDER', 'Products Review Reminder');
}
if (!defined('BOX_TOOLS_REVIEW_REMINDER')) {
    define('BOX_TOOLS_REVIEW_REMINDER', 'Products Review Reminder');
}
if (!defined('FILENAME_ADDON_REVIEW_REMINDER')) {
    define('FILENAME_ADDON_REVIEW_REMINDER', 'addon_review_reminder');
}

$prrInstalled = defined('PLUGIN_PRODUCTS_REVIEW_REMINDER_VERSION');
$prrGroupId = 0;
if (isset($db)) {
    $prrVersion = $db->Execute("SELECT configuration_group_id FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'PLUGIN_PRODUCTS_REVIEW_REMINDER_VERSION' LIMIT 1");
    if (!$prrVersion->EOF) {
        $prrInstalled = true;
        $prrGroupId = (int)$prrVersion->fields['configuration_group_id'];
    }
}
if (function_exists('zen_register_admin_page') && $prrInstalled) {
    if ($prrGroupId > 0 && !zen_page_key_exists('configProductsReviewReminder')) {
        zen_register_admin_page('configProductsReviewReminder', 'BOX_CONFIGURATION_REVIEW_REMINDER', 'FILENAME_CONFIGURATION', 'gID=' . $prrGroupId, 'configuration', 'Y', $prrGroupId);
    }
    if (!zen_page_key_exists('toolsProductsReviewReminder')) {
        zen_register_admin_page('toolsProductsReviewReminder', 'BOX_TOOLS_REVIEW_REMINDER', 'FILENAME_ADDON_REVIEW_REMINDER', '', 'tools', 'Y', 17);
    }
}
unset($prrInstalled, $prrGroupId, $prrVersion);
