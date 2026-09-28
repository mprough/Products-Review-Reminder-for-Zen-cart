<?php
/** Stable scheduled entry point in the shop root. */
$isCli = PHP_SAPI === 'cli';
if (!$isCli && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    exit;
}
$shopRoot = __DIR__;
chdir($shopRoot);
if ($isCli) {
    $storeHost = parse_url(getenv('PRR_STORE_URL') ?: 'https://localhost', PHP_URL_HOST) ?: 'localhost';
    $_SERVER['HTTP_HOST'] = $storeHost;
    $_SERVER['SERVER_NAME'] = $storeHost;
    $_SERVER['SERVER_PORT'] = '443';
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['REQUEST_URI'] = '/index.php';
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';
}
require $shopRoot . '/includes/application_top.php';
if (!$isCli) {
    $provided = $_GET['token'] ?? '';
    $key = $db->Execute('SELECT configuration_value FROM ' . TABLE_CONFIGURATION . " WHERE configuration_key = 'REVIEWS_REMINDER_CRON_TOKEN' LIMIT 1");
    if (!is_string($provided) || $key->EOF || !preg_match('/^[a-f0-9]{48}$/D', (string)$key->fields['configuration_value']) || !hash_equals((string)$key->fields['configuration_value'], $provided)) {
        http_response_code(404);
        exit;
    }
    define('PRR_AUTHORIZED_WORKER', true);
    header('Cache-Control: no-store');
    http_response_code(204);
}
$installedVersion = defined('PLUGIN_PRODUCTS_REVIEW_REMINDER_VERSION') ? (string)PLUGIN_PRODUCTS_REVIEW_REMINDER_VERSION : '';
if (!preg_match('/^\d+\.\d+\.\d+$/D', $installedVersion)) {
    if (!$isCli) http_response_code(500);
    error_log('Products Review Reminder is not installed.');
    exit(1);
}
$worker = $shopRoot . '/zc_plugins/ProductsReviewReminder/v' . $installedVersion . '/catalog/reminder_worker.php';
if (!is_file($worker)) {
    if (!$isCli) http_response_code(500);
    error_log('Products Review Reminder worker is missing for the installed version.');
    exit(1);
}
require $worker;
