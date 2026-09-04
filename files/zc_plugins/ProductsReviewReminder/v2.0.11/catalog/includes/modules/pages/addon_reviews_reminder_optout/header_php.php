<?php

declare(strict_types=1);

require DIR_WS_MODULES . zen_get_module_directory('require_languages.php');

$customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$token = isset($_GET['token']) ? (string)$_GET['token'] : '';
$authenticatedCustomerId = isset($_SESSION['customer_id']) ? (int)$_SESSION['customer_id'] : 0;
$reviewReminderTest = isset($_GET['test']) && $_GET['test'] === '1';

if (!$reviewReminderTest && $customerId > 0 && preg_match('/^[a-f0-9]{64}$/', $token)) {
    $customer = $db->Execute(
        'SELECT customers_email_address FROM ' . TABLE_CUSTOMERS . ' WHERE customers_id = ' . $customerId . ' LIMIT 1'
    );
    if (!$customer->EOF) {
        $optoutSecret = (string)STORE_OWNER_EMAIL_ADDRESS . '|' . (defined('DB_SERVER_PASSWORD') ? (string)DB_SERVER_PASSWORD : '');
        $expected = hash_hmac('sha256', (string)$customerId, $optoutSecret);
        if (hash_equals($expected, $token)) {
            $db->Execute(
                'INSERT INTO ' . TABLE_ADDON_REVIEW_REMINDER_OPTOUT . ' (customers_id, orders_id, date_time) VALUES (' . $customerId . ', 0, NOW())
                 ON DUPLICATE KEY UPDATE date_time = NOW()'
            );
            $authenticatedCustomerId = $customerId;
            $reviewReminderOptedOut = true;
        }
    }
}

if (!$reviewReminderTest && empty($reviewReminderOptedOut)) {
    if ($authenticatedCustomerId < 1) {
        $_SESSION['navigation']->set_snapshot();
        zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL'));
    }
    $customerId = $authenticatedCustomerId;
    if (
        isset($_POST['optOutToken'])
        && hash_equals(hash('sha256', 'review-reminder|' . $customerId . '|' . session_id()), (string)$_POST['optOutToken'])
    ) {
        $db->Execute(
            'INSERT INTO ' . TABLE_ADDON_REVIEW_REMINDER_OPTOUT . ' (customers_id, orders_id, date_time) VALUES (' . $customerId . ', 0, NOW())
             ON DUPLICATE KEY UPDATE date_time = NOW()'
        );
        $reviewReminderOptedOut = true;
    }
}

$breadcrumb->add(NAVBAR_TITLE);
