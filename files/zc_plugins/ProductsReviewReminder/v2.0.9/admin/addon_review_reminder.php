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
	$preview_message = false;
	$inspection_order_id = isset($_POST['inspection_order_id']) ? (int)$_POST['inspection_order_id'] : 0;
	$test_format = isset($_POST['test_format']) && $_POST['test_format'] === 'text' ? 'text' : 'html';

	#PREVIEW OR TEST USING ANY REAL ORDER. THESE ACTIONS NEVER WRITE TO THE REMINDER LOG.
	if (($action === 'preview' || $action === 'test') && $inspection_order_id > 0) {
		$sql = "SELECT `customers_id`, `customers_name`, `customers_company`, `customers_email_address`
				FROM `" . TABLE_ORDERS . "`
				WHERE `orders_id` = '" . $inspection_order_id . "'
				LIMIT 1";
		$inspection_order = $db->Execute($sql);
		if ($inspection_order->EOF) {
			$inspection_error = sprintf(ERROR_INSPECTION_ORDER_NOT_FOUND, $inspection_order_id);
		} else {
			$inspection_name = trim((string)$inspection_order->fields['customers_name']);
			if ($inspection_name === '') {
				$inspection_name = (string)$inspection_order->fields['customers_company'];
			}
			$inspection_tokens = ['{customer_name}' => $inspection_name, '{store_name}' => $store_name, '{order_number}' => (string)$inspection_order_id];
			$inspection_subject = strtr(defined('REVIEWS_REMINDER_EMAIL_SUBJECT') ? REVIEWS_REMINDER_EMAIL_SUBJECT : EMAIL_REVIEW_REMINDER_SUBJECT, $inspection_tokens);
			$preview_message = get_request_for_review_content(
				$inspection_order_id,
				$limit_products,
				(string)$inspection_order->fields['customers_email_address'],
				$inspection_name,
				(int)$inspection_order->fields['customers_id'],
				true
			);
			if (!$preview_message) {
				$inspection_error = sprintf(ERROR_INSPECTION_NO_PRODUCTS, $inspection_order_id);
			} elseif ($action === 'test') {
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
					$inspection_feedback = sprintf(SUCCESS_TEST_EMAIL_SENT, STORE_OWNER_EMAIL_ADDRESS, $inspection_order_id);
				} else {
					$inspection_error = ERROR_TEST_EMAIL_NOT_SENT;
				}
			} else {
				$inspection_feedback = sprintf(SUCCESS_PREVIEW_READY, $inspection_order_id);
			}
		}
	} elseif (($action === 'preview' || $action === 'test') && $inspection_order_id < 1) {
		$inspection_error = ERROR_INSPECTION_ORDER_REQUIRED;
	}

	#PROCESS SEND REVIEW REQUEST IF ANY
	if($action === 'send' && isset($_POST['orders_id']) and is_array($_POST['orders_id']) and count($_POST['orders_id']) > 0 ){
		$orders_to_send = array_slice($_POST['orders_id'], 0, $batch_limit);
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
				$subject = strtr(defined('REVIEWS_REMINDER_EMAIL_SUBJECT') ? REVIEWS_REMINDER_EMAIL_SUBJECT : EMAIL_REVIEW_REMINDER_SUBJECT, $tokens);
			} else {
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
				}
			}
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
			<table border="0" width="100%" cellspacing="0" cellpadding="0">
				<tr>
					<td valign="top">
<?php
	echo zen_draw_form('frmRequestReviews', FILENAME_ADDON_REVIEW_REMINDER, '', 'post');
	echo zen_draw_hidden_field('action', 'send');
	echo zen_image_submit('button_send_mail.gif', IMAGE_SEND_EMAIL, 'id="btnSendMail"');

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
					ON o.`orders_status` = os.`orders_status_id`
				LEFT JOIN `" . TABLE_CUSTOMERS . "` AS c
					ON o.`customers_id` = c.`customers_id`
				LEFT JOIN `" . TABLE_ORDERS_STATUS_HISTORY . "` AS osh
					ON (o.`orders_id` = osh.`orders_id`
						AND osh.`orders_status_id` = '" . zen_db_prepare_input( $trigger_order_status ) . "')
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
								<td class="dataTableHeadingContent"><?php echo $rec->fields['status']; ?></td>
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
								<td class="dataTableContent" valign="top"><?php echo $rec->fields['customer']; ?></td>
								<td class="dataTableContent" valign="top"><?php echo $rec->fields['company']; ?></td>
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
							</form>
						</div>
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

<?php
	function review_reminder_mail_succeeded($mail_result){
		return $mail_result === '' || $mail_result === true;
	}

	function get_packing_list( $order_id, $limit_products ){
		global $db;

		$packing_list = '
						<table class="tblPackingList">
							<tbody>
							<tr class="dataTableHeadingRow">
								<td class="dataTableHeadingContent">'. TBL_PRODUCT_NAME . '</td>
								<td class="dataTableHeadingContent" align="center" width="40">' . TBL_PRODUCT_HAS_REVIEWS . '</td>
							</tr>' . "\n";
		#LOAD DATA
		$sql = "SELECT op.`products_id`, op.`products_name`, op.`products_quantity`, opa.`products_options`, opa.`products_options_values`, m.`manufacturers_name`
				FROM `" . TABLE_ORDERS_PRODUCTS . "` AS op
					LEFT JOIN `" . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . "` AS opa
						ON opa.`orders_products_id` = op.`orders_products_id`
					LEFT JOIN `" . TABLE_PRODUCTS . "` AS p
						ON op.`products_id` = p.`products_id`
					LEFT JOIN `" . TABLE_MANUFACTURERS . "` AS m
						ON p.`manufacturers_id` = m.`manufacturers_id`
					LEFT JOIN `" . TABLE_PRODUCTS_ATTRIBUTES . "` AS pa
						ON opa.`orders_products_attributes_id` = pa.`products_attributes_id`
				WHERE op.`orders_id` = '" . zen_db_prepare_input( (int)$order_id ) . "'
				GROUP BY op.`products_id`";
		if( (int)$limit_products > 0 ){
			$sql .= ' LIMIT ' . zen_db_prepare_input( (int)$limit_products );
		}
		$rec = $db->Execute( $sql );
		$row_class = 'evenProductsRow';
		while( !$rec->EOF ){
			#PRODUCT ATTRIBUTE INFORMATION
			$option = '';
			if( $rec->fields['products_options'] != null ){
				$option = ' - ' . $rec->fields['products_options'] . ': ' . $rec->fields['products_options_values'];
			}
			#MANUFACTURER
			$manufacturer = '';
			if($rec->fields['manufacturers_name'] != null)
				$manufacturer = ' by ' . $rec->fields['manufacturers_name'];
			#REVIEWS
			$reviews = get_reviews_count( $rec->fields['products_id'] );

			#OUTPUT
			$packing_list .= '
							<tr class="dataTableRow ' . $row_class . '">
								<td>' . $rec->fields['products_name'] . $option . $manufacturer . '</td>
								<td align="center">' . $reviews . '</td>
							</tr>' . "\n";

			#TABLE DECORATION
			if( $row_class == 'evenProductsRow' )
				$row_class = 'oddProductsRow';
			else
				$row_class = 'evenProductsRow';
			$rec->MoveNext();
		}
		$packing_list .= '
							</tbody>
						</table>';
		return $packing_list;
	}

	function get_status_history_id( $order_id, $trigger_order_status ){
		global $db;

		$status_history_id = 0;
		$sql = "SELECT `orders_status_history_id`
				FROM `" . TABLE_ORDERS_STATUS_HISTORY . "`
				WHERE `orders_id` = '" . zen_db_prepare_input( (int)$order_id ) . "'
				AND `orders_status_id` = '" . zen_db_prepare_input( (int)$trigger_order_status ) . "'
				ORDER BY orders_status_history_id DESC
				LIMIT 1";
		$rec = $db->Execute($sql);
		if( !$rec->EOF ){
			$status_history_id = $rec->fields['orders_status_history_id'];
		}

		return $status_history_id;
	}

	function get_reviews_count( $pid ){
		global $db;

		$count = 0;
		$sql = "SELECT COUNT(*) AS count
				FROM `" . TABLE_REVIEWS . "`
				WHERE `products_id` = '" . zen_db_prepare_input( (int)$pid ) . "'";
		$rec = $db->Execute( $sql );
		if( !$rec->EOF ){
			$count = $rec->fields['count'];
		}
		if( $count == 0 )
			$count = '-';

		return $count;
	}

	function get_request_for_review_content($oid, $limit_products, $mailto, $customer_name, $customer_id, $is_test = false){
		global $db, $store_name, $store_email, $images_folder, $template_images_folder, $store_address;

		#INITIALIZE VARIABLES
		$product_list_ar = get_products_pending_review( $oid );
		$at_least_one_product = false;

		#LOAD PRODUCT INFORMATION
		$sql = "SELECT DISTINCT p.`products_id`, p.`products_image`
				FROM `" . TABLE_ORDERS_PRODUCTS . "` AS op
					JOIN `" . TABLE_PRODUCTS . "` AS p
						on op.`products_id` = p.`products_id`
				WHERE `orders_id` = '" . zen_db_prepare_input( (int)$oid ) . "'";
		if( (int)$limit_products > 0 ){
			$sql .= ' LIMIT ' . zen_db_prepare_input( (int)$limit_products );
		}
		$rec = $db->Execute( $sql );

		#INITIALIZE PRODUCT LIST VARIABLES
		$tokens = ['{customer_name}' => $customer_name, '{store_name}' => $store_name, '{order_number}' => (string)$oid];
		$greeting = strtr(defined('REVIEWS_REMINDER_EMAIL_GREETING') ? REVIEWS_REMINDER_EMAIL_GREETING : 'Hello {customer_name},', $tokens);
		$intro = strtr(defined('REVIEWS_REMINDER_EMAIL_INTRO') ? REVIEWS_REMINDER_EMAIL_INTRO : 'Thank you for shopping with {store_name}.', $tokens);
		$question = defined('REVIEWS_REMINDER_EMAIL_QUESTION') ? REVIEWS_REMINDER_EMAIL_QUESTION : EMAIL_EXPECTATION_QUESTION;
		$cta = defined('REVIEWS_REMINDER_EMAIL_CTA') ? REVIEWS_REMINDER_EMAIL_CTA : EMAIL_ACTION_CALL;
		$closing = strtr(defined('REVIEWS_REMINDER_EMAIL_CLOSING') ? REVIEWS_REMINDER_EMAIL_CLOSING : 'Thank you for sharing your experience.', $tokens);
		$test_html = $is_test ? '<div style="padding:10px;margin-bottom:15px;background:#fff3cd;border:1px solid #ffecb5"><strong>Test preview:</strong> No customer was emailed and this order was not recorded as contacted.</div>' : '';
		$test_text = $is_test ? "TEST PREVIEW: No customer was emailed and this order was not recorded as contacted.\r\n\r\n" : '';
		$html = '<div style="width:100%;max-width:600px;margin:0;font-family:Arial,sans-serif;color:#333;text-align:left">' . $test_html . '<p>' . htmlspecialchars($greeting, ENT_QUOTES, CHARSET) . '</p><p>' . nl2br(htmlspecialchars($intro, ENT_QUOTES, CHARSET)) . '</p><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;border-collapse:collapse"><tbody>' . "\r\n";
		$text = $test_text . $greeting . "\r\n\r\n" . $intro . "\r\n\r\n";

		while( !$rec->EOF ){
			if( in_array( $rec->fields['products_id'], $product_list_ar ) ){
				$at_least_one_product = true;
				$review_page_link = zen_catalog_href_link('addon_my_reviews', 'pid=' . (int)$rec->fields['products_id'], 'SSL');
				$product_name = zen_get_products_name((int)$rec->fields['products_id']);
				#LOAD HTML VERSION OF THE MESSAGE
				$html .= '
	<tr>
		<td colspan="2">
			<div style="font-size:18px; color:#000000; margin:10px 0; font-family:arial,helvetica,verdana,sans-serif">' . htmlspecialchars($question, ENT_QUOTES, CHARSET) . '</div>
		</td>
	</tr>
	<tr>
		<td width="170" valign="top" style="width:170px;padding:0 20px 15px 0">
			<a href="' . $review_page_link . '" target="_blank">
				<img src="' . $images_folder . $rec->fields['products_image'] . '" width="150" alt="' . htmlspecialchars((string)$product_name, ENT_QUOTES, CHARSET) . '" style="display:block;width:150px;max-width:150px;height:auto;margin:10px 0;border:0" />
			</a>
		</td>
		<td valign="top" style="padding:10px 0 15px 0;text-align:left">
			<div style="font-size:14px;color:#666666;font-family:arial,helvetica,verdana,sans-serif">' . htmlspecialchars((string)$product_name, ENT_QUOTES, CHARSET) . '</div>
			<div style="font-size:20px; color:#124c90; font-family:arial,helvetica,verdana,sans-serif; margin:20px 0 5px 0;">' . htmlspecialchars($cta, ENT_QUOTES, CHARSET) . '</div>
			<a href="' . $review_page_link . '" target="_blank" rel="noopener" style="font-size:28px;color:#e0a100;text-decoration:none" aria-label="' . htmlspecialchars($cta, ENT_QUOTES, CHARSET) . '">&#9733;&#9733;&#9733;&#9733;&#9733;</a>
		</td>
	</tr>' . "\r\n";
				#LOAD PLAIN TEXT VERSION OF THE MESSAGE
				$text .= $question . "\r\n" . $product_name . "\r\n" . $cta . "\r\n" . $review_page_link . "\r\n\r\n";
			}
			$rec->MoveNext();
		}

		#ASSEMBLYING THE HTML MESSAGE
		$reviews_url = zen_catalog_href_link('addon_my_reviews', '', 'SSL');
		$test_optout_url = zen_catalog_href_link('addon_reviews_reminder_optout', 'test=1', 'SSL');
		$optout_html = '<p style="font-size:11px;color:#777"><a href="' . htmlspecialchars($test_optout_url, ENT_QUOTES, CHARSET) . '">Test the opt-out page</a>. Test mode will not change any customer preferences.</p>';
		$optout_text = "Test the opt-out page (no customer preferences will change):\r\n" . $test_optout_url;
		if (!$is_test) {
			$optout_secret = (string)STORE_OWNER_EMAIL_ADDRESS . '|' . (defined('DB_SERVER_PASSWORD') ? (string)DB_SERVER_PASSWORD : '');
			$optout_token = hash_hmac('sha256', (string)$customer_id, $optout_secret);
			$optout_url = zen_catalog_href_link('addon_reviews_reminder_optout', 'customer_id=' . $customer_id . '&token=' . $optout_token, 'SSL');
			$optout_html = '<p style="font-size:11px;color:#777">To stop product review reminders, <a href="' . htmlspecialchars($optout_url, ENT_QUOTES, CHARSET) . '">opt out here</a>.</p>';
			$optout_text = 'Opt out: ' . $optout_url;
		}
		$html .= '</tbody></table><p>' . nl2br(htmlspecialchars($closing, ENT_QUOTES, CHARSET)) . '</p><p><a href="' . htmlspecialchars($reviews_url, ENT_QUOTES, CHARSET) . '">Review your purchases</a></p>' . $optout_html . '</div>' . "\r\n";
		$email_content['html'] = $html;

		#ASSEMBLYING THE TEXT MESSAGE
		$email_content['text'] = $text . $closing . "\r\n\r\nReview your purchases: " . $reviews_url . "\r\n" . $optout_text;

		if( $at_least_one_product ){
			return $email_content;
		}else{
			return false;
		}
	}

	function get_products_pending_review( $oid ){
		global $db;
		$products = array();
		$sql = "SELECT op.`products_id`
				FROM `" . TABLE_ORDERS_PRODUCTS . "` AS op
					JOIN `" . TABLE_ORDERS . "` AS o
						ON op.`orders_id` = o.`orders_id`
					LEFT JOIN `" . TABLE_REVIEWS . "` AS r
						ON (o.`customers_id` = r.`customers_id` AND op.`products_id` = r.`products_id`)
				WHERE op.`orders_id` = '" . zen_db_prepare_input( (int)$oid ) . "'
					AND `reviews_id` IS NULL";
		$rec = $db->Execute($sql);
		while( !$rec->EOF ){
			$products[] = (int)$rec->fields['products_id'];
			$rec->MoveNext();
		}

		return $products;
	}
?>
