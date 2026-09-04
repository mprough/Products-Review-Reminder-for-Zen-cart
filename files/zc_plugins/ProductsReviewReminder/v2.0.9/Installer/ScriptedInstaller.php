<?php

declare(strict_types=1);

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    public string $pluginKey = 'ProductsReviewReminder';
    public string $version = '2.0.9';
    protected string $groupTitle = 'Products Review Reminder';

    protected function groupId(): int
    {
        $titles = "'Products Review Reminder', 'Product\\'s Review Reminder'";
        $result = $this->dbConn->Execute(
            'SELECT configuration_group_id FROM ' . TABLE_CONFIGURATION_GROUP . " WHERE configuration_group_title IN ($titles) ORDER BY configuration_group_id LIMIT 1"
        );
        if (!$result->EOF) {
            $id = (int)$result->fields['configuration_group_id'];
            $this->executeInstallerSql('UPDATE ' . TABLE_CONFIGURATION_GROUP . " SET configuration_group_title = 'Products Review Reminder', configuration_group_description = 'Configure manual product review requests.' WHERE configuration_group_id = $id");
            return $id;
        }
        $this->executeInstallerSql("INSERT INTO " . TABLE_CONFIGURATION_GROUP . " (configuration_group_title, configuration_group_description, sort_order, visible) VALUES ('Products Review Reminder', 'Configure manual product review requests.', 0, 1)");
        $id = (int)$this->dbConn->Insert_ID();
        $this->executeInstallerSql('UPDATE ' . TABLE_CONFIGURATION_GROUP . " SET sort_order = $id WHERE configuration_group_id = $id");
        return $id;
    }

    protected function executeInstall(): bool
    {
        $groupId = $this->groupId();
        $this->executeInstallerSql(
            'CREATE TABLE IF NOT EXISTS ' . DB_PREFIX . "addon_review_reminder_log (
                orders_id int(11) NOT NULL,
                date_time datetime NOT NULL,
                sent_by varchar(32) NOT NULL DEFAULT 'admin',
                PRIMARY KEY (orders_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $this->executeInstallerSql(
            'CREATE TABLE IF NOT EXISTS ' . DB_PREFIX . "addon_review_reminder_optout (
                customers_id int(11) NOT NULL,
                orders_id int(11) NOT NULL DEFAULT 0,
                date_time datetime NOT NULL,
                PRIMARY KEY (customers_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $rows = [
            ['Installed version', 'PLUGIN_PRODUCTS_REVIEW_REMINDER_VERSION', '2.0.9', 'Installed plugin version.', 0, "zen_cfg_select_option(array('2.0.9'),"],
            ['Trigger order status', 'REVIEWS_REMINDER_TRIGGER_ORDER_STATUS', '3', 'The order status that starts the waiting period.', 10, 'zen_cfg_pull_down_order_statuses('],
            ['Waiting period', 'REVIEWS_REMINDER_COOLOFF_DAYS', '14', 'Days after the order reaches the trigger status before it becomes eligible.', 20, null],
            ['How far back to look', 'REVIEWS_REMINDER_WINDOW_DAYS', '30', 'After the waiting period, keep an order eligible for this many additional days. Example: a 14-day waiting period and 30 days here includes orders that reached the selected status 14 to 44 days ago.', 30, null],
            ['Maximum products per email', 'REVIEWS_REMINDER_MAX_PRODUCTS', '0', 'Maximum products included in one email. Use 0 for no limit.', 40, null],
			['Maximum reminders per batch', 'REVIEWS_REMINDER_BATCH_LIMIT', '10', 'Maximum eligible orders shown and sent in one admin batch. Refresh the Tools page to load the next batch.', 45, null],
			['Available email shortcodes', 'REVIEWS_REMINDER_EMAIL_SHORTCODES', '{customer_name}, {store_name}, {order_number}', 'Available in Email subject, Email greeting, Email introduction, and Email closing. Copy the shortcode exactly, including the braces.', 48, "zen_cfg_select_option(array('{customer_name}, {store_name}, {order_number}'),"],
            ['Email subject', 'REVIEWS_REMINDER_EMAIL_SUBJECT', '{customer_name}, how did your recent order from {store_name} work out?', 'Available shortcodes: {customer_name}, {store_name}, {order_number}.', 50, 'zen_cfg_textarea('],
            ['Email greeting', 'REVIEWS_REMINDER_EMAIL_GREETING', 'Hello {customer_name},', 'Opening text of the review request. Available shortcodes: {customer_name}, {store_name}, {order_number}.', 60, 'zen_cfg_textarea('],
            ['Email introduction', 'REVIEWS_REMINDER_EMAIL_INTRO', 'Thank you for shopping with {store_name}. We would appreciate your honest feedback about your purchase.', 'Text shown before the products. Available shortcodes: {customer_name}, {store_name}, {order_number}.', 70, 'zen_cfg_textarea('],
            ['Product question', 'REVIEWS_REMINDER_EMAIL_QUESTION', 'How did this item meet your expectations?', 'Question shown with each product.', 80, 'zen_cfg_textarea('],
            ['Review link text', 'REVIEWS_REMINDER_EMAIL_CTA', 'Rate and review this item', 'Linked call to action shown with each product.', 90, 'zen_cfg_textarea('],
            ['Email closing', 'REVIEWS_REMINDER_EMAIL_CLOSING', 'Thank you for taking the time to share your experience.', 'Text shown after the products. Available shortcodes: {customer_name}, {store_name}, {order_number}.', 100, 'zen_cfg_textarea('],
        ];
        foreach ($rows as [$title, $key, $value, $description, $sort, $setFunction]) {
            $title = zen_db_input($title);
            $key = zen_db_input($key);
            $value = zen_db_input($value);
            $description = zen_db_input($description);
            $set = $setFunction === null ? 'NULL' : "'" . zen_db_input($setFunction) . "'";
            $this->executeInstallerSql(
                'INSERT IGNORE INTO ' . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, set_function) VALUES ('$title', '$key', '$value', '$description', $groupId, $sort, NOW(), $set)"
            );
        }
        $this->executeInstallerSql(
            'UPDATE ' . TABLE_CONFIGURATION . "
                SET configuration_title = 'How far back to look',
                    configuration_description = 'After the waiting period, keep an order eligible for this many additional days. Example: a 14-day waiting period and 30 days here includes orders that reached the selected status 14 to 44 days ago.'
              WHERE configuration_key = 'REVIEWS_REMINDER_WINDOW_DAYS'"
        );
        $this->executeInstallerSql(
            'UPDATE ' . TABLE_CONFIGURATION . "
                SET set_function = 'zen_cfg_textarea('
              WHERE configuration_key IN (
                'REVIEWS_REMINDER_EMAIL_SUBJECT',
                'REVIEWS_REMINDER_EMAIL_GREETING',
                'REVIEWS_REMINDER_EMAIL_INTRO',
                'REVIEWS_REMINDER_EMAIL_QUESTION',
                'REVIEWS_REMINDER_EMAIL_CTA',
                'REVIEWS_REMINDER_EMAIL_CLOSING'
              )"
        );

        $legacyCooloff = $this->dbConn->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'REVIEWS_REMINDER_COOLOFF_PERIOD' LIMIT 1");
        if (!$legacyCooloff->EOF) {
            $days = match ((string)$legacyCooloff->fields['configuration_value']) {
                'Now' => 0,
                'One Week' => 7,
                'Two Weeks' => 14,
                'Three Weeks' => 21,
                'Four Weeks' => 28,
                'One Month', 'One Months' => 30,
                'Two Months' => 60,
                'Three Months' => 90,
                default => 14,
            };
            $this->executeInstallerSql('UPDATE ' . TABLE_CONFIGURATION . " SET configuration_value = '$days' WHERE configuration_key = 'REVIEWS_REMINDER_COOLOFF_DAYS'");
        }
        $legacyWindow = $this->dbConn->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'REVIEWS_REMINDER_TIME_WINDOW' LIMIT 1");
        if (!$legacyWindow->EOF) {
            $days = match ((string)$legacyWindow->fields['configuration_value']) {
                'One Week' => 7,
                'Two Weeks' => 14,
                'Three Weeks' => 21,
                'One Month' => 30,
                'Two Months' => 60,
                'Three Months' => 90,
                'Six Months' => 180,
                'One Year' => 365,
                default => 30,
            };
            $this->executeInstallerSql('UPDATE ' . TABLE_CONFIGURATION . " SET configuration_value = '$days' WHERE configuration_key = 'REVIEWS_REMINDER_WINDOW_DAYS'");
        }
        $legacyMaximum = $this->dbConn->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'REVIEWS_REMINDER_MAX_PRODUCTS' LIMIT 1");
        if (!$legacyMaximum->EOF && !is_numeric($legacyMaximum->fields['configuration_value'])) {
            $maximum = match ((string)$legacyMaximum->fields['configuration_value']) {
                'One Product' => 1,
                'Two Products' => 2,
                'Four Products' => 4,
                'Eight Products' => 8,
                'Sixteen Products' => 16,
                'Thirty Two Products' => 32,
                default => 0,
            };
            $this->executeInstallerSql('UPDATE ' . TABLE_CONFIGURATION . " SET configuration_value = '$maximum' WHERE configuration_key = 'REVIEWS_REMINDER_MAX_PRODUCTS'");
        }
        $this->executeInstallerSql("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key IN ('REVIEWS_REMINDER_COOLOFF_PERIOD', 'REVIEWS_REMINDER_TIME_WINDOW', 'REVIEWS_REMINDER_AUTOMATIC')");
        $this->executeInstallerSql('UPDATE ' . TABLE_CONFIGURATION . " SET configuration_value = '2.0.9', set_function = 'zen_cfg_select_option(array(\\'2.0.9\\'),' WHERE configuration_key = 'PLUGIN_PRODUCTS_REVIEW_REMINDER_VERSION'");
        $this->executeInstallerSql("DELETE FROM " . TABLE_ADMIN_PAGES . " WHERE page_key IN ('configProductReviewReminder', 'toolsProductReviewReminder')");

        if (!zen_page_key_exists('configProductsReviewReminder')) {
            zen_register_admin_page('configProductsReviewReminder', 'BOX_CONFIGURATION_REVIEW_REMINDER', 'FILENAME_CONFIGURATION', "gID=$groupId", 'configuration', 'Y', $groupId);
        }
        if (!zen_page_key_exists('toolsProductsReviewReminder')) {
            zen_register_admin_page('toolsProductsReviewReminder', 'BOX_TOOLS_REVIEW_REMINDER', 'FILENAME_ADDON_REVIEW_REMINDER', '', 'tools', 'Y', 17);
        }
        return true;
    }

    protected function executeUpgrade(...$args): bool
    {
        return $this->executeInstall();
    }

    protected function executeUninstall(): bool
    {
        $this->executeInstallerSql("DELETE FROM " . TABLE_ADMIN_PAGES . " WHERE page_key IN ('configProductsReviewReminder', 'toolsProductsReviewReminder', 'configProductReviewReminder', 'toolsProductReviewReminder')");
        $group = $this->dbConn->Execute("SELECT configuration_group_id FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'PLUGIN_PRODUCTS_REVIEW_REMINDER_VERSION' LIMIT 1");
        if (!$group->EOF) {
            $groupId = (int)$group->fields['configuration_group_id'];
            $this->executeInstallerSql('DELETE FROM ' . TABLE_CONFIGURATION . " WHERE configuration_group_id = $groupId");
            $this->executeInstallerSql('DELETE FROM ' . TABLE_CONFIGURATION_GROUP . " WHERE configuration_group_id = $groupId");
        }
        return true;
    }
}
