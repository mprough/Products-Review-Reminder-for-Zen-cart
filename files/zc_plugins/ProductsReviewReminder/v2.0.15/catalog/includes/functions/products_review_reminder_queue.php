<?php
/** Persistent reminder runs shared by the admin page and the CLI worker. */
if (!defined('DB_PREFIX')) {
    exit;
}

function prr_queue_job()
{
    global $db;
    $result = $db->Execute('SELECT * FROM ' . DB_PREFIX . 'addon_review_reminder_job WHERE job_id = 1 LIMIT 1');
    return $result->EOF ? [] : $result->fields;
}

function prr_queue_start(int $status, int $cooloffDays, int $windowDays, int $batchLimit, int $productLimit): int
{
    global $db;
    $lock = $db->Execute("SELECT GET_LOCK('products_review_reminder_worker', 0) AS acquired");
    if ((int)$lock->fields['acquired'] !== 1) {
        return -1;
    }
    try {
        return prr_queue_start_locked($status, $cooloffDays, $windowDays, $batchLimit, $productLimit);
    } finally {
        $db->Execute("SELECT RELEASE_LOCK('products_review_reminder_worker')");
    }
}

function prr_queue_start_locked(int $status, int $cooloffDays, int $windowDays, int $batchLimit, int $productLimit): int
{
    global $db;
    $current = prr_queue_job();
    if ($current && in_array($current['state'], ['running', 'paused'], true)) {
        return -1;
    }
    if ($current) {
        $unsafe = $db->Execute('SELECT orders_id FROM ' . DB_PREFIX . 'addon_review_reminder_queue WHERE run_id = ' . (int)$current['run_id'] . " AND state IN ('processing', 'failed') LIMIT 1");
        if (!$unsafe->EOF) {
            return -2;
        }
    }
    // Snapshot eligible orders at Start. Later orders require a new run.
    $start = date('Y-m-d', strtotime('-' . ($cooloffDays + $windowDays) . ' days'));
    $end = date('Y-m-d', strtotime('-' . $cooloffDays . ' days')) . ' 23:59:59';
    $sql = 'SELECT DISTINCT o.orders_id FROM ' . TABLE_ORDERS . ' o JOIN ' . TABLE_ORDERS_STATUS_HISTORY . ' h ON h.orders_id = o.orders_id AND h.orders_status_id = ' . $status
        . ' WHERE o.orders_status = ' . $status
        . " AND h.date_added > '" . $start . "' AND h.date_added < '" . $end . "'"
        . ' AND o.orders_id NOT IN (SELECT orders_id FROM ' . DB_PREFIX . 'addon_review_reminder_log)'
        . ' AND o.customers_id NOT IN (SELECT customers_id FROM ' . DB_PREFIX . 'addon_review_reminder_optout)'
        . ' AND EXISTS (SELECT 1 FROM ' . TABLE_ORDERS_PRODUCTS . ' op LEFT JOIN ' . TABLE_REVIEWS . ' r ON r.customers_id = o.customers_id AND r.products_id = op.products_id WHERE op.orders_id = o.orders_id AND r.reviews_id IS NULL)'
        . ' ORDER BY o.orders_id DESC';
    $orders = $db->Execute($sql);
    $ids = [];
    while (!$orders->EOF) {
        $id = (int)$orders->fields['orders_id'];
        $ids[] = $id;
        $orders->MoveNext();
    }
    if (!$ids) {
        return 0;
    }
    $runId = $current ? (int)$current['run_id'] + 1 : 1;
    $db->Execute('DELETE FROM ' . DB_PREFIX . 'addon_review_reminder_queue');
    foreach ($ids as $id) {
        $db->Execute('INSERT INTO ' . DB_PREFIX . "addon_review_reminder_queue (run_id, orders_id, state) VALUES ($runId, $id, 'pending')");
    }
    $batchLimit = min(10, max(1, $batchLimit));
    $db->Execute('REPLACE INTO ' . DB_PREFIX . "addon_review_reminder_job (job_id, run_id, state, batch_limit, product_limit, interval_minutes, next_run_at, started_at) VALUES (1, $runId, 'running', $batchLimit, $productLimit, 5, NOW(), NOW())");
    return count($ids);
}

function prr_queue_stop(): void
{
    global $db;
    $db->Execute('UPDATE ' . DB_PREFIX . "addon_review_reminder_job SET state = 'stopped', next_run_at = NULL WHERE job_id = 1 AND state = 'running'");
}
