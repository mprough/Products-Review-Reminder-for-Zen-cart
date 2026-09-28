<?php
/** Stable CLI entry point. Keep this file in the shop root across plugin upgrades. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$shopRoot = __DIR__;
chdir($shopRoot);
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
require $shopRoot . '/includes/application_top.php';
$installedVersion = defined('PLUGIN_PRODUCTS_REVIEW_REMINDER_VERSION') ? (string)PLUGIN_PRODUCTS_REVIEW_REMINDER_VERSION : '';
if (!preg_match('/^\d+\.\d+\.\d+$/D', $installedVersion)) {
    fwrite(STDERR, "Products Review Reminder is not installed.\n");
    exit(1);
}
$worker = $shopRoot . '/zc_plugins/ProductsReviewReminder/v' . $installedVersion . '/catalog/reminder_worker.php';
if (!is_file($worker)) {
    fwrite(STDERR, "Products Review Reminder worker is missing for the installed version.\n");
    exit(1);
}
require $worker;
