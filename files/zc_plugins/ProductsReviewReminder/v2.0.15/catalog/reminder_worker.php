<?php
/** Run once per minute from cron using PHP CLI. Never invoke through HTTP. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$shopRoot = dirname(__DIR__, 4);
chdir($shopRoot);
$_SERVER['HTTP_HOST'] = parse_url(getenv('PRR_STORE_URL') ?: 'https://localhost', PHP_URL_HOST);
$_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];
$_SERVER['SERVER_PORT'] = '443';
$_SERVER['HTTPS'] = 'on';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
require $shopRoot . '/includes/application_top.php';
require_once __DIR__ . '/includes/functions/products_review_reminder_mail.php';
require_once __DIR__ . '/includes/functions/products_review_reminder_queue.php';
if (!function_exists('products_review_reminder_decode_text')) {
    function products_review_reminder_decode_text(string $text): string
    {
        for ($i = 0; $i < 5; $i++) {
            $value = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, CHARSET);
            if ($value === $text) break;
            $text = $value;
        }
        return $text;
    }
}
$lock = $db->Execute("SELECT GET_LOCK('products_review_reminder_worker', 0) AS acquired");
if ((int)$lock->fields['acquired'] !== 1) exit;
try {
    $job = prr_queue_job();
    if (!$job || $job['state'] !== 'running' || strtotime((string)$job['next_run_at']) > time()) exit;
    $runId = (int)$job['run_id'];
    $stuck = $db->Execute('SELECT orders_id FROM ' . DB_PREFIX . "addon_review_reminder_queue WHERE run_id = $runId AND state = 'processing' LIMIT 1");
    if (!$stuck->EOF) {
        $db->Execute('UPDATE ' . DB_PREFIX . "addon_review_reminder_job SET state = 'paused', next_run_at = NULL, last_error = 'Interrupted send requires review' WHERE job_id = 1");
        exit;
    }
    $batch = $db->Execute('SELECT orders_id FROM ' . DB_PREFIX . "addon_review_reminder_queue WHERE run_id = $runId AND state = 'pending' ORDER BY orders_id DESC LIMIT " . max(1, min(100, (int)$job['batch_limit'])));
    $ids = [];
    while (!$batch->EOF) { $ids[] = (int)$batch->fields['orders_id']; $batch->MoveNext(); }
    if (!$ids) {
        $db->Execute('UPDATE ' . DB_PREFIX . "addon_review_reminder_job SET state = 'complete', next_run_at = NULL WHERE job_id = 1");
        exit;
    }
    $config = $db->Execute('SELECT configuration_key, configuration_value FROM ' . TABLE_CONFIGURATION . " WHERE configuration_key IN ('STORE_NAME', 'STORE_NAME_ADDRESS', 'EMAIL_FROM')");
    $settings = [];
    while (!$config->EOF) { $settings[$config->fields['configuration_key']] = $config->fields['configuration_value']; $config->MoveNext(); }
    $store_name = $settings['STORE_NAME'] ?? STORE_NAME;
    $store_address = $settings['STORE_NAME_ADDRESS'] ?? '';
    $store_email = $settings['EMAIL_FROM'] ?? '';
    $images_folder = HTTP_CATALOG_SERVER . '/' . str_replace(DIR_FS_CATALOG, '', DIR_FS_CATALOG_IMAGES);
    $template_images_folder = HTTP_CATALOG_SERVER . '/' . DIR_WS_TEMPLATES . $template_dir . '/images/';
    foreach ($ids as $oid) {
        $latest = prr_queue_job();
        if (!$latest || $latest['state'] !== 'running' || (int)$latest['run_id'] !== $runId) break;
        $record = $db->Execute('SELECT customers_id, customers_name, customers_company, customers_email_address FROM ' . TABLE_ORDERS . " WHERE orders_id = $oid AND orders_id NOT IN (SELECT orders_id FROM " . DB_PREFIX . 'addon_review_reminder_log) AND customers_id NOT IN (SELECT customers_id FROM ' . DB_PREFIX . 'addon_review_reminder_optout) LIMIT 1');
        if ($record->EOF || !get_products_pending_review($oid)) {
            $db->Execute('UPDATE ' . DB_PREFIX . "addon_review_reminder_queue SET state = 'skipped' WHERE run_id = $runId AND orders_id = $oid");
            continue;
        }
        $customer = $record->fields;
        $name = trim((string)$customer['customers_name']) ?: (string)$customer['customers_company'];
        $email = (string)$customer['customers_email_address'];
        $message = get_request_for_review_content($oid, (int)$job['product_limit'], $email, $name, (int)$customer['customers_id']);
        if (!$message) {
            $db->Execute('UPDATE ' . DB_PREFIX . "addon_review_reminder_queue SET state = 'skipped' WHERE run_id = $runId AND orders_id = $oid");
            continue;
        }
        $db->Execute('UPDATE ' . DB_PREFIX . "addon_review_reminder_queue SET state = 'processing' WHERE run_id = $runId AND orders_id = $oid");
        $tokens = ['{customer_name}' => $name, '{store_name}' => $store_name, '{order_number}' => (string)$oid];
        $subject = trim((string)preg_replace('/[\r\n]+/', ' ', strtr(products_review_reminder_decode_text(defined('REVIEWS_REMINDER_EMAIL_SUBJECT') ? REVIEWS_REMINDER_EMAIL_SUBJECT : EMAIL_REVIEW_REMINDER_SUBJECT), $tokens)));
        $block = ['EMAIL_MESSAGE_HTML' => $message['html'], 'EMAIL_TO_ADDRESS' => $email];
        $result = zen_mail($name, $email, $subject, $message['text'], STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, $block, 'addon_review_reminder');
        if (!review_reminder_mail_succeeded($result)) {
            $db->Execute('UPDATE ' . DB_PREFIX . "addon_review_reminder_queue SET state = 'failed' WHERE run_id = $runId AND orders_id = $oid");
            $db->Execute('UPDATE ' . DB_PREFIX . "addon_review_reminder_job SET state = 'paused', next_run_at = NULL, last_error = 'Email send failed for order $oid; check Zen Cart email logs' WHERE job_id = 1");
            break;
        }
        $db->Execute('INSERT IGNORE INTO ' . DB_PREFIX . "addon_review_reminder_log (orders_id, date_time, sent_by) VALUES ($oid, NOW(), 'queue')");
        $db->Execute('UPDATE ' . DB_PREFIX . "addon_review_reminder_queue SET state = 'sent' WHERE run_id = $runId AND orders_id = $oid");
    }
    $latest = prr_queue_job();
    if ($latest && $latest['state'] === 'running') {
        $pending = $db->Execute('SELECT orders_id FROM ' . DB_PREFIX . "addon_review_reminder_queue WHERE run_id = $runId AND state = 'pending' LIMIT 1");
        $db->Execute('UPDATE ' . DB_PREFIX . 'addon_review_reminder_job SET state = ' . ($pending->EOF ? "'complete', next_run_at = NULL" : "'running', next_run_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE)") . ' WHERE job_id = 1');
    }
} finally {
    $db->Execute("SELECT RELEASE_LOCK('products_review_reminder_worker')");
}
