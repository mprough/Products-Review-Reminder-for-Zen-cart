<?php
/**
 * @package addon_review_reminder
 * @copyright Copyright 2003-2017 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * 2024/27/08 PRO-Webs.net v.1.2 $
 * @author Will Vasconcelos willvasconcelos@outlook.com $
 */

 require 'includes/application_top.php';
 require_once dirname(__DIR__) . '/catalog/includes/functions/products_review_reminder_mail.php';
 require_once dirname(__DIR__) . '/catalog/includes/functions/products_review_reminder_queue.php';

?>
<?php
	#LOAD AND SANITIZE GET PARAMETERS
	$order_id = -1;
	if( isset($_GET['oid']) and (int)$_GET['oid'] > 0 ){
		$order_id = zen_db_prepare_input( (int)$_GET['oid'] );
	}
	$action = '';
	if( isset($_POST['action']) and $_POST['action'] != '' ){
		$action = zen_db_prepare_input( $_POST['action'] );
	}

	#GET PLUGIN'S CONFIGURATION GROUP KEY
	$gid = 0;
	$sql = "SELECT `configuration_group_id`
			FROM `" . TABLE_CONFIGURATION . "`
			WHERE `configuration_key` LIKE 'REVIEWS_REMINDER%'
			LIMIT 1";
	$rec = $db->Execute($sql);
	if(!$rec->EOF){
		$gid = $rec->fields['configuration_group_id'];

		#LOAD CONFIGURATION VALUES
		// Reserved for compatibility with early development copies.
		$legacy_mode = false;
		$trigger_order_status = defined('REVIEWS_REMINDER_TRIGGER_ORDER_STATUS') ? (int)REVIEWS_REMINDER_TRIGGER_ORDER_STATUS : 3;
		$cooloff_days = defined('REVIEWS_REMINDER_COOLOFF_DAYS') ? max(0, (int)REVIEWS_REMINDER_COOLOFF_DAYS) : 14;
		$window_days = defined('REVIEWS_REMINDER_WINDOW_DAYS') ? max(1, (int)REVIEWS_REMINDER_WINDOW_DAYS) : 30;
		$cooloff_period = strtotime('-' . $cooloff_days . ' days');
		$start_date = strtotime('-' . $window_days . ' days', $cooloff_period);
		$limit_products = defined('REVIEWS_REMINDER_MAX_PRODUCTS') ? max(0, (int)REVIEWS_REMINDER_MAX_PRODUCTS) : 0;
		$batch_limit = defined('REVIEWS_REMINDER_BATCH_LIMIT') ? max(1, (int)REVIEWS_REMINDER_BATCH_LIMIT) : 10;

		$sql = "SELECT configuration_title, configuration_key, configuration_value
					FROM `" . TABLE_CONFIGURATION . "`
					WHERE `configuration_group_id` = '" . zen_db_prepare_input( $gid ) . "'
					ORDER BY `sort_order` ASC";
		$rec = $db->Execute($sql);
		while(!$rec->EOF){
			switch( $rec->fields['configuration_key'] ){
				case 'REVIEWS_REMINDER_TRIGGER_ORDER_STATUS':
					$trigger_order_status = (int)$rec->fields['configuration_value'];
					break;
				case 'REVIEWS_REMINDER_COOLOFF_DAYS':
					$cooloff_days = max(0, (int)$rec->fields['configuration_value']);
					$cooloff_period = strtotime('-' . $cooloff_days . ' days');
					break;
				case 'REVIEWS_REMINDER_WINDOW_DAYS':
					$window_days = max(1, (int)$rec->fields['configuration_value']);
					$start_date = strtotime('-' . $window_days . ' days', $cooloff_period);
					break;
				case 'REVIEWS_REMINDER_MAX_PRODUCTS':
					$limit_products = max(0, (int)$rec->fields['configuration_value']);
					break;
				case 'REVIEWS_REMINDER_BATCH_LIMIT':
					$batch_limit = max(1, (int)$rec->fields['configuration_value']);
					break;
				default:
			}
			$rec->MoveNext();
		}
	}

	#LOAD STORE INFORMATION
	$store_name = '';
	$store_address = '';
	$store_email = '';
	$sql = "SELECT `configuration_value`, `configuration_key`
			FROM `" . TABLE_CONFIGURATION . "`
			WHERE `configuration_key` IN ('EMAIL_FROM', 'STORE_NAME', 'STORE_NAME_ADDRESS')";
	$rec = $db->Execute($sql);
	while(!$rec->EOF){
		switch($rec->fields['configuration_key']){
			case 'STORE_NAME':
				$store_name = $rec->fields['configuration_value'];
				break;
			case 'STORE_NAME_ADDRESS':
				$store_address = $rec->fields['configuration_value'];
				break;
			case 'EMAIL_FROM':
				$store_email = $rec->fields['configuration_value'];
				break;
			default:
		}
		$rec->MoveNext();
	}
?>
<?php
	$images_folder = HTTP_CATALOG_SERVER . '/' . str_replace(DIR_FS_CATALOG, '', DIR_FS_CATALOG_IMAGES);
	$template_images_folder = HTTP_CATALOG_SERVER . '/' . DIR_WS_TEMPLATES . $template_dir . '/images/';
	$inspection_feedback = '';
	$inspection_error = '';
	if ($action === 'start_queue') {
		$queued = prr_queue_start((int)$trigger_order_status, (int)$cooloff_days, (int)$window_days, (int)$batch_limit, (int)$limit_products);
		$inspection_feedback = $queued > 0 ? "Queued $queued eligible orders. The worker will send the first batch and continue every 5 minutes." : ($queued === 0 ? 'No eligible orders were found.' : ($queued === -3 ? 'The worker heartbeat is missing or stale. Set up the cron task and wait for it to check in before starting.' : ($queued === -2 ? 'A previous send has an uncertain result. Review the run before starting another.' : 'A reminder run is already active.')));
	} elseif ($action === 'stop_queue') {
		prr_queue_stop();
		$inspection_feedback = 'The reminder run was stopped. A batch already in progress may finish.';
	}
	$queue_job = prr_queue_job();
	$worker_healthy = $queue_job && $queue_job['heartbeat_age'] !== null && (int)$queue_job['heartbeat_age'] >= 0 && (int)$queue_job['heartbeat_age'] <= 660;
	$worker_path = realpath(rtrim(DIR_FS_CATALOG, '/') . '/products_review_reminder_worker.php');
	$catalog_server = defined('HTTPS_CATALOG_SERVER') && HTTPS_CATALOG_SERVER !== '' ? HTTPS_CATALOG_SERVER : HTTP_CATALOG_SERVER;
	$store_url = rtrim($catalog_server, '/') . '/' . ltrim(DIR_WS_CATALOG, '/');
	$cron_command = $worker_path === false ? '' : 'PRR_STORE_URL=' . escapeshellarg($store_url) . ' php ' . escapeshellarg($worker_path);
	$queue_counts = ['pending' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0, 'processing' => 0];
	if ($queue_job) {
		$counts = $db->Execute('SELECT state, COUNT(*) AS total FROM ' . DB_PREFIX . 'addon_review_reminder_queue WHERE run_id = ' . (int)$queue_job['run_id'] . ' GROUP BY state');
		while (!$counts->EOF) {
			$queue_counts[$counts->fields['state']] = (int)$counts->fields['total'];
			$counts->MoveNext();
		}
	}
	$preview_message = false;
	$inspection_order_id = isset($_POST['inspection_order_id']) ? (int)$_POST['inspection_order_id'] : 0;
	$test_format = isset($_POST['test_format']) && $_POST['test_format'] === 'text' ? 'text' : 'html';
	$inspection_customer = false;
	$inspection_customer_opted_out = false;

	#PREVIEW OR TEST USING ANY REAL ORDER. THESE ACTIONS NEVER WRITE TO THE REMINDER LOG.
	if (in_array($action, ['preview', 'test', 'live_optout_test', 'check_optout', 'restore_optin'], true) && $inspection_order_id > 0) {
		$sql = "SELECT `customers_id`, `customers_name`, `customers_company`, `customers_email_address`
				FROM `" . TABLE_ORDERS . "`
				WHERE `orders_id` = '" . $inspection_order_id . "'
				LIMIT 1";
		$inspection_order = $db->Execute($sql);
		if ($inspection_order->EOF) {
			$inspection_error = sprintf(ERROR_INSPECTION_ORDER_NOT_FOUND, $inspection_order_id);
		} else {
			$inspection_customer_name = trim((string)$inspection_order->fields['customers_name']);
			if ($inspection_customer_name === '') {
				$inspection_customer_name = (string)$inspection_order->fields['customers_company'];
			}
			$inspection_customer = [
				'id' => (int)$inspection_order->fields['customers_id'],
				'name' => $inspection_customer_name,
				'email' => (string)$inspection_order->fields['customers_email_address'],
			];
			if ($action === 'restore_optin') {
				$db->Execute(
					'DELETE FROM ' . TABLE_ADDON_REVIEW_REMINDER_OPTOUT .
					' WHERE customers_id = ' . (int)$inspection_customer['id']
				);
				$inspection_feedback = sprintf(SUCCESS_CUSTOMER_RESTORED, $inspection_customer['name'], $inspection_customer['id']);
			}

			$optout_check = $db->Execute(
				'SELECT customers_id FROM ' . TABLE_ADDON_REVIEW_REMINDER_OPTOUT .
				' WHERE customers_id = ' . (int)$inspection_customer['id'] . ' LIMIT 1'
			);
			$inspection_customer_opted_out = !$optout_check->EOF;

			if ($action === 'restore_optin' || $action === 'check_optout') {
				$preview_message = false;
			} else {
			$inspection_name = $inspection_customer_name;
			$inspection_tokens = ['{customer_name}' => $inspection_name, '{store_name}' => $store_name, '{order_number}' => (string)$inspection_order_id];
			$inspection_subject = trim((string)preg_replace('/[\r\n]+/', ' ', strtr(products_review_reminder_decode_text(defined('REVIEWS_REMINDER_EMAIL_SUBJECT') ? REVIEWS_REMINDER_EMAIL_SUBJECT : EMAIL_REVIEW_REMINDER_SUBJECT), $inspection_tokens)));
			$preview_message = get_request_for_review_content(
				$inspection_order_id,
				$limit_products,
				(string)$inspection_order->fields['customers_email_address'],
				$inspection_name,
				(int)$inspection_order->fields['customers_id'],
				true,
				$action === 'live_optout_test'
			);
			if (!$preview_message) {
				$inspection_error = sprintf(ERROR_INSPECTION_NO_PRODUCTS, $inspection_order_id);
			} elseif ($action === 'test' || $action === 'live_optout_test') {
				$html_block = [
					'EMAIL_MESSAGE_HTML' => $preview_message['html'],
					'EMAIL_TO_ADDRESS' => STORE_OWNER_EMAIL_ADDRESS,
				];
				$GLOBALS['products_review_reminder_test_format'] = $test_format;
				$mail_result = zen_mail(
					STORE_OWNER,
					STORE_OWNER_EMAIL_ADDRESS,
					'[TEST] ' . $inspection_subject,
					$preview_message['text'],
					STORE_OWNER,
					STORE_OWNER_EMAIL_ADDRESS,
					$html_block,
					'addon_review_reminder'
				);
				unset($GLOBALS['products_review_reminder_test_format']);
				if (review_reminder_mail_succeeded($mail_result)) {
					$inspection_feedback = $action === 'live_optout_test'
						? sprintf(SUCCESS_LIVE_OPTOUT_TEST_SENT, STORE_OWNER_EMAIL_ADDRESS, $inspection_order_id, $inspection_customer['name'], $inspection_customer['id'])
						: sprintf(SUCCESS_TEST_EMAIL_SENT, STORE_OWNER_EMAIL_ADDRESS, $inspection_order_id);
				} else {
					$inspection_error = ERROR_TEST_EMAIL_NOT_SENT;
				}
			} else {
				$inspection_feedback = sprintf(SUCCESS_PREVIEW_READY, $inspection_order_id);
			}
			}
		}
	} elseif (in_array($action, ['preview', 'test', 'live_optout_test', 'check_optout', 'restore_optin'], true) && $inspection_order_id < 1) {
		$inspection_error = ERROR_INSPECTION_ORDER_REQUIRED;
	}

	#PROCESS SEND REVIEW REQUEST IF ANY
	$active_queue = prr_queue_job();
	if ($action === 'send' && $active_queue && in_array($active_queue['state'], ['running', 'paused'], true)) {
		$inspection_error = 'Resolve or stop the scheduled run before sending a manual batch. A paused send may already have reached the customer.';
	} elseif($action === 'send' && isset($_POST['orders_id']) and is_array($_POST['orders_id']) and count($_POST['orders_id']) > 0 ){
		$orders_to_send = array_slice($_POST['orders_id'], 0, $batch_limit);
		$sent_count = 0;
		$failed_count = 0;
		$skipped_count = 0;
		foreach( $orders_to_send as $oid ){
			$oid = zen_db_prepare_input( $oid );
			#LOAD ORDER INFORMATION
			$oid = (int)$oid;
			$sql = "SELECT `customers_id`, `customers_name`, `customers_company`, `customers_email_address`
					FROM `" . TABLE_ORDERS . "`
					WHERE `orders_id`='" . $oid . "'
					  AND `orders_id` NOT IN (SELECT `orders_id` FROM `" . TABLE_ADDON_REVIEW_REMINDER_LOG . "`)
					  AND `customers_id` NOT IN (SELECT `customers_id` FROM `" . TABLE_ADDON_REVIEW_REMINDER_OPTOUT . "`)";
			$rec = $db->Execute($sql);
			if(!$rec->EOF){
				$mailto = $rec->fields['customers_email_address'];
				if( trim($rec->fields['customers_name']) != '' )
					$customer_name = $rec->fields['customers_name'];
				else
					$customer_name = $rec->fields['customers_company'];
				$tokens = ['{customer_name}' => $customer_name, '{store_name}' => $store_name, '{order_number}' => (string)$oid];
				$subject = trim((string)preg_replace('/[\r\n]+/', ' ', strtr(products_review_reminder_decode_text(defined('REVIEWS_REMINDER_EMAIL_SUBJECT') ? REVIEWS_REMINDER_EMAIL_SUBJECT : EMAIL_REVIEW_REMINDER_SUBJECT), $tokens)));
			} else {
				$skipped_count++;
				continue;
			}

			#SEND EMAIL
			$message = get_request_for_review_content($oid, $limit_products, $mailto, $customer_name, (int)$rec->fields['customers_id']);
			if( $message ){
				$block['EMAIL_MESSAGE_HTML'] = $message['html'];
				$block['EMAIL_TO_ADDRESS'] = $mailto;
				$mail_result = zen_mail($customer_name, $mailto, $subject, $message['text'], STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, $block, 'addon_review_reminder' );
				if (review_reminder_mail_succeeded($mail_result)) {
				$sql = "INSERT IGNORE INTO `" . TABLE_ADDON_REVIEW_REMINDER_LOG . "` (`orders_id`, `date_time`, `sent_by`)
						VALUES ('" . $oid . "', '" . date("Y-m-d H:i:s") . "','" . zen_db_prepare_input((int)$_SESSION['admin_id']) . "')";
				$db->Execute($sql);
				$sent_count++;
				} else {
				$failed_count++;
				}
			} else {
				$skipped_count++;
			}
		}
		$inspection_feedback = "$sent_count reminders accepted by the mail service. $skipped_count skipped. The next eligible orders are now shown.";
		if ($failed_count > 0) {
			$inspection_error = "$failed_count reminders failed to send. Check the Zen Cart email log.";
		}
	}
	#PROCESS REMOVE ORDER FROM THE LIST IF ANY
	if( $action == 'remove' and isset($_POST['order_id']) and (int)$_POST['order_id'] > 0 ){
		$order_id = (int)$_POST['order_id'];
		$sql = "INSERT IGNORE INTO `" . TABLE_ADDON_REVIEW_REMINDER_LOG . "`
				(`orders_id`, `date_time`, `sent_by`)
				VALUES ('" . $order_id . "', '" . date("Y-m-d H:i:s") . "','" . zen_db_prepare_input((int)$_SESSION['admin_id']) . "')";
		$db->Execute($sql);
		zen_redirect(zen_href_link(FILENAME_ADDON_REVIEW_REMINDER, '', 'SSL'));
	}
?>
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
<head>
<?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
<style type="text/css">
	.infoBoxContent{
		padding:3px 10px;
	}
	.dataTableHeadingRow{
		background-color:#DDD;
	}
	.dataTableHeadingRow td{
		padding:5px;
	}
	.tblListOrders{
		width:100%;
		border:none;
	}
	.tblListOrders td{
		padding:5px;
	}
	.tblPackingList{
		width:100%;
		margin:5px 10px 10px 0;
		border-bottom:solid 1px silver;
	}
	.tblPackingList td{
		padding:7px;
	}
	.evenProductsRow{
		background-color:#FFFFFF;
	}
	.oddProductsRow{
		background-color:#F8F8F8;
	}
	.evenOrdersRow{
		background-color:#FFFFFF;
	}
	.oddOrdersRow{
		background-color:#F8F8F8;
	}
	#btnSendMail{
		margin-bottom:15px;
	}
	p.feedback{
		font-size:14px;
		margin:20px;
		color:#099;
	}
	.inspectionBox{padding:10px;margin-bottom:15px;background:#f5f5f5;border:1px solid #ccc}
	.inspectionBox label{display:block;margin-bottom:5px;font-weight:bold}
	.inspectionBox .btn{margin-top:8px;margin-right:5px}
	.inspectionFeedback{padding:10px;margin:10px 0;border-left:4px solid #198754;background:#eef8f1}
	.inspectionError{padding:10px;margin:10px 0;border-left:4px solid #b02a37;background:#fbecef}
	.inspectionWarning{padding:10px;margin:10px 0;border-left:4px solid #d39e00;background:#fff3cd}
	.inspectionStatus{margin-top:10px;font-weight:bold}
	.emailPreview{max-width:650px;margin:20px auto;padding:15px;border:1px solid #bbb;background:#fff}
	.emailPreview pre{white-space:pre-wrap;overflow-wrap:anywhere;font:14px/1.5 monospace}
</style>
</head>
<body>
<!-- header //-->
<?php require(DIR_WS_INCLUDES . 'header.php'); ?>
<!-- header_eof //-->
<!-- body //-->
<table border="0" width="100%" cellspacing="2" cellpadding="2">
	<tr>
		<td width="100%" valign="top">
			<table border="0" width="100%" cellspacing="0" cellpadding="0">
				<tr>
					<td class="pageHeading"><?php echo HEADING_TITLE; ?></td>
					<td class="pageHeading" align="right"><?php echo zen_draw_separator('pixel_trans.gif', HEADING_IMAGE_WIDTH, HEADING_IMAGE_HEIGHT); ?></td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td>
			<?php if ($inspection_feedback !== '') { ?><div class="inspectionFeedback"><?php echo htmlspecialchars($inspection_feedback, ENT_QUOTES, CHARSET); ?></div><?php } ?>
			<?php if ($inspection_error !== '') { ?><div class="inspectionError"><?php echo htmlspecialchars($inspection_error, ENT_QUOTES, CHARSET); ?></div><?php } ?>
			<div class="inspectionBox">
				<h2>Continue sending in batches</h2>
				<p>Start queues all currently eligible orders. A server cron task sends up to 10 reminders every 5 minutes. This continues after you close the page.</p>
				<h3>Set up the cron task</h3>
				<p>In your hosting control panel, add a cron task that runs every five minutes. Copy the command below, or send it to your hosting helpdesk. If your host uses a different PHP CLI command, ask them to replace <code>php</code> with its full path.</p>
				<p>Schedule: <code>*/5 * * * *</code> (every five minutes)</p>
				<?php if ($cron_command !== '') { ?><pre style="white-space:pre-wrap;overflow-wrap:anywhere;user-select:all;"><?php echo htmlspecialchars($cron_command, ENT_QUOTES, CHARSET); ?></pre><?php } else { ?><p class="inspectionError">The stable worker file is missing from the shop root. Copy products_review_reminder_worker.php from the package's files directory into the shop root.</p><?php } ?>
				<p class="<?php echo $worker_healthy ? 'inspectionFeedback' : 'inspectionError'; ?>">Worker heartbeat: <?php echo $worker_healthy ? 'active' : 'missing or over 11 minutes old'; ?><?php if ($queue_job && $queue_job['last_heartbeat_at']) { ?>. Last check: <?php echo htmlspecialchars((string)$queue_job['last_heartbeat_at'], ENT_QUOTES, CHARSET); ?> server time<?php } ?>.</p>
				<?php if (!$worker_healthy) { ?><p class="inspectionError">Scheduled sending is unavailable until the cron task checks in. You can still use Send selected below to send a manual batch whenever you choose. No cron task is needed for manual sending.</p><?php } ?>
				<?php if ($queue_job) { ?>
				<p>Status: <?php echo htmlspecialchars((string)$queue_job['state'], ENT_QUOTES, CHARSET); ?>.
				Sent: <?php echo (int)$queue_counts['sent']; ?>. Pending: <?php echo (int)$queue_counts['pending']; ?>.
				Skipped: <?php echo (int)$queue_counts['skipped']; ?>. Failed: <?php echo (int)$queue_counts['failed']; ?>.
				<?php if ($queue_job['next_run_at']) { ?>Next run: <?php echo htmlspecialchars((string)$queue_job['next_run_at'], ENT_QUOTES, CHARSET); ?> server time.<?php } ?></p>
				<?php if ($queue_job['last_error'] !== '') { ?><p class="inspectionError"><?php echo htmlspecialchars((string)$queue_job['last_error'], ENT_QUOTES, CHARSET); ?></p><?php } ?>
				<?php } ?>
				<?php if ($worker_healthy && in_array($queue_job['state'], ['idle', 'complete', 'stopped'], true)) { ?>
				<?php echo zen_draw_form('startReminderQueue', FILENAME_ADDON_REVIEW_REMINDER, '', 'post'); ?>
				<?php echo zen_draw_hidden_field('action', 'start_queue'); ?>
				<button type="submit" class="btn btn-primary">Start sending</button></form>
				<?php } elseif ($queue_job && $queue_job['state'] === 'running') { ?>
				<?php echo zen_draw_form('stopReminderQueue', FILENAME_ADDON_REVIEW_REMINDER, '', 'post'); ?>
				<?php echo zen_draw_hidden_field('action', 'stop_queue'); ?>
				<button type="submit" class="btn btn-warning">Stop sending</button></form>
				<?php } elseif ($queue_job && $queue_job['state'] === 'paused') { ?><p>The run is paused for review. Check the email error log and confirm delivery before sending again.</p><?php } else { ?><p>Start becomes available after the worker checks in. Manual sending remains available below.</p><?php } ?>
			</div>
			<table border="0" width="100%" cellspacing="0" cellpadding="0">
				<tr>
					<td valign="top">
<?php
	echo zen_draw_form('frmRequestReviews', FILENAME_ADDON_REVIEW_REMINDER, '', 'post');
	echo zen_draw_hidden_field('action', 'send');
	echo zen_image_submit('button_send_mail.gif', IMAGE_SEND_EMAIL, 'id="btnSendMail"' . ($queue_job && in_array($queue_job['state'], ['running', 'paused'], true) ? ' disabled="disabled"' : ''));

	$selected_order_id = 0;
	if( $order_id > 0 )
		$selected_order_id = $order_id;
	#LOAD ELIGIBLE ORDERS
	$sql = "SELECT DISTINCT o.`orders_id` AS oid,
				o.`customers_id`,
				DATE_FORMAT(o.`date_purchased`,'%m/%d/%y') AS date,
				os.`orders_status_name` AS status,
				o.`customers_name` AS customer,
				o.`customers_company` AS company,
				osh.`date_added`
			FROM `" . TABLE_ORDERS . "` AS o
				LEFT JOIN `" . TABLE_ORDERS_STATUS . "` AS os
					ON o.`orders_status` = os.`orders_status_id` AND os.`language_id` = " . (int)$_SESSION['languages_id'] . "
				LEFT JOIN `" . TABLE_CUSTOMERS . "` AS c
					ON o.`customers_id` = c.`customers_id`
				JOIN (SELECT `orders_id`, MAX(`date_added`) AS `date_added`
					FROM `" . TABLE_ORDERS_STATUS_HISTORY . "`
					WHERE `orders_status_id` = " . (int)$trigger_order_status . "
					GROUP BY `orders_id`) AS osh ON o.`orders_id` = osh.`orders_id`
			WHERE osh.`date_added` > '" . date("Y-m-d", $start_date ) . "'
				AND osh.`date_added` < '" . date("Y-m-d", $cooloff_period ) . " 23:59:59'
				AND o.`orders_status` = '" . zen_db_prepare_input( $trigger_order_status ) . "'
				AND o.`customers_id` NOT IN (
					SELECT `customers_id`
					FROM `" . TABLE_ADDON_REVIEW_REMINDER_OPTOUT . "`
				)
				AND o.`orders_id` NOT IN (
					SELECT `orders_id`
					FROM `" . TABLE_ADDON_REVIEW_REMINDER_LOG . "`
				)
			GROUP BY o.`orders_id`
			ORDER BY o.`orders_id` DESC
			LIMIT " . (int)$batch_limit;
	$rec = $db->Execute( $sql );
	if( !$rec->EOF ){ #START: IF ORDERS PENDING REQUEST REVIEW
?>
						<table class="tblListOrders">
							<tr class="dataTableHeadingRow">
								<td class="dataTableHeadingContent">#</td>
								<td class="dataTableHeadingContent" align="center"><input type="checkbox" id="checkAll" onchange="$('input:checkbox').prop('checked', this.checked);" checked="checked" /></td>
								<td class="dataTableHeadingContent"><?php echo TBL_ORDER_ID; ?></td>
								<td class="dataTableHeadingContent"><?php echo TBL_ORDER_DATE; ?></td>
								<td class="dataTableHeadingContent"><?php echo htmlspecialchars((string)$rec->fields['status'], ENT_QUOTES, CHARSET); ?></td>
								<td class="dataTableHeadingContent"><?php echo TBL_CUSTOMER_NAME; ?></td>
								<td class="dataTableHeadingContent"><?php echo TBL_COMPANY_NAME; ?></td>
								<td class="dataTableHeadingContent"><?php echo TBL_PRODUCT_COUNT; ?></td>
								<td class="dataTableHeadingContent" align="center"><?php echo TBL_INFO; ?></td>
							</tr>
<?php
		$row_class_orders = 'evenOrdersRow';
		$counter = 1;
		while( !$rec->EOF ){
			#CHECK IF ORDER HAS ANY PRODUCT NOT YET REVIEWED BY CUSTOMER
			$products_pending_review_ar = get_products_pending_review( $rec->fields['oid'] );

			if( count($products_pending_review_ar) > 0 ){ #START: IF ORDER HAS PRODUCTS PENDING REVIEW
				if( $selected_order_id == 0 ){ #USE FIRST VALID ORDER ID BY DEFAULT
					$selected_order_id = $rec->fields['oid'];
				}

				#HIGHLIGHT SELECTED TABLE ROW
				if( $selected_order_id == $rec->fields['oid'] ){
					$row_id_class = 'class="dataTableRowSelected" id="defaultSelected"';
				}else{
					$row_id_class = 'class="' . $row_class_orders . ' dataTableRow"';
				}
?>
							<tr <?php echo $row_id_class; ?>>
								<td class="dataTableContent" valign="top"><?php echo $counter++; ?></td>
								<td class="dataTableContent" valign="top" align="center"><?php echo zen_draw_checkbox_field("orders_id[]", $rec->fields['oid'], ' checked=checked'); ?></td>
								<td class="dataTableContent" valign="top"><?php echo $rec->fields['oid']; ?></td>
								<td class="dataTableContent" valign="top"><?php echo $rec->fields['date']; ?></td>
								<td class="dataTableContent" valign="top"><?php echo date("m/d/y", strtotime($rec->fields['date_added'])); ?></td>
								<td class="dataTableContent" valign="top"><?php echo htmlspecialchars((string)$rec->fields['customer'], ENT_QUOTES, CHARSET); ?></td>
								<td class="dataTableContent" valign="top"><?php echo htmlspecialchars((string)$rec->fields['company'], ENT_QUOTES, CHARSET); ?></td>
								<td class="dataTableContent" valign="top"><?php echo count( $products_pending_review_ar ); ?></td>
								<td class="dataTableContent" valign="top" align="center" onclick="document.location.href='?oid=<?php echo $rec->fields['oid']; ?>';" style="cursor:pointer;"><?php echo ( $selected_order_id == $rec->fields['oid'] ? zen_image(DIR_WS_IMAGES . 'icon_arrow_right.gif', '') : zen_image(DIR_WS_IMAGES . 'icon_info.gif', IMAGE_ICON_INFO)); ?></td>
							</tr>
<?php
				if( $row_class_orders == 'evenOrdersRow' ){
					$row_class_orders = 'oddOrdersRow';
				}else{
					$row_class_orders = 'evenOrdersRow';
				}
			} #END: IF ORDER HAS PRODUCTS PENDING REVIEW
			$rec->MoveNext();
		}
?>
						</table>
						</form>
<?php
	} #END: IF ORDERS PENDING REQUEST REVIEW

	if( $selected_order_id == 0 ){ #PROOF THAT NO ORDER IS PENDING REVIEW
?>
						<p class="feedback"><?php echo FEEDBACK_NO_RESULTS; ?></p>
<?php
	}
?>
					</td>
					<td width="25%" valign="top">
						<div class="inspectionBox">
							<?php echo zen_draw_form('frmInspectReminder', FILENAME_ADDON_REVIEW_REMINDER, '', 'post'); ?>
							<label for="inspection_order_id"><?php echo INSPECTION_ORDER_LABEL; ?></label>
							<?php echo zen_draw_input_field('inspection_order_id', $inspection_order_id > 0 ? (string)$inspection_order_id : (string)$selected_order_id, 'id="inspection_order_id" min="1" inputmode="numeric"', false, 'number'); ?>
							<div><?php echo INSPECTION_HELP; ?></div>
							<label for="test_format"><?php echo TEST_FORMAT_LABEL; ?></label>
							<?php echo zen_draw_pull_down_menu('test_format', [['id' => 'html', 'text' => TEST_FORMAT_HTML], ['id' => 'text', 'text' => TEST_FORMAT_TEXT]], $test_format, 'id="test_format"'); ?>
							<button type="submit" name="action" value="preview" class="btn btn-default"><?php echo BUTTON_PREVIEW_EMAIL; ?></button>
							<button type="submit" name="action" value="test" class="btn btn-primary"><?php echo BUTTON_SEND_TEST_EMAIL; ?></button>
							<div class="inspectionWarning"><?php echo LIVE_OPTOUT_TEST_WARNING; ?></div>
							<button type="submit" name="action" value="live_optout_test" class="btn btn-warning"><?php echo BUTTON_SEND_LIVE_OPTOUT_TEST; ?></button>
							<button type="submit" name="action" value="check_optout" class="btn btn-default"><?php echo BUTTON_CHECK_CUSTOMER_STATUS; ?></button>
							<button type="submit" name="action" value="restore_optin" class="btn btn-default"><?php echo BUTTON_RESTORE_CUSTOMER_OPTIN; ?></button>
							<?php if ($inspection_customer !== false) { ?>
								<div class="inspectionStatus"><?php echo sprintf(
									$inspection_customer_opted_out ? CUSTOMER_STATUS_OPTED_OUT : CUSTOMER_STATUS_OPTED_IN,
									htmlspecialchars($inspection_customer['name'], ENT_QUOTES, CHARSET),
									(int)$inspection_customer['id']
								); ?></div>
							<?php } ?>
							</form>
						</div>
<?php if ($selected_order_id > 0) { ?>
						<table border="0" width="100%" cellspacing="0" cellpadding="2">
							<tbody>
							<tr class="infoBoxHeading">
								<td class="infoBoxHeading"><b><?php echo TBL_INFO_HEADER; ?></b></td>
							</tr>
							</tbody>
						</table>
<?php
	echo get_packing_list( $selected_order_id, $limit_products );
	echo zen_draw_form('frmRemoveReminder', FILENAME_ADDON_REVIEW_REMINDER, '', 'post');
	echo zen_draw_hidden_field('action', 'remove');
	echo zen_draw_hidden_field('order_id', (string)$selected_order_id);
	echo zen_image_submit('button_remove.gif', IMAGE_DELETE, 'id="btnRemove"');
	echo '</form>' . "\n";
?>
<?php } ?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
<?php if ($preview_message) { ?>
	<div class="emailPreview">
		<h2><?php echo PREVIEW_HEADING; ?></h2>
		<?php if ($test_format === 'text') { ?>
			<pre><?php echo htmlspecialchars($preview_message['text'], ENT_QUOTES, CHARSET); ?></pre>
		<?php } else { ?>
			<?php echo $preview_message['html']; ?>
		<?php } ?>
	</div>
<?php } ?>
<!-- body_eof //-->
<!-- footer //-->
<?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
<!-- footer_eof //-->
<br>
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
