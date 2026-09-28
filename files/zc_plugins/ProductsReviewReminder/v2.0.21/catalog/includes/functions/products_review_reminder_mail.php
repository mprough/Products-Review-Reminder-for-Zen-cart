<?php
if (!defined('IS_ADMIN_FLAG')) {
    exit;
}

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
								<td>' . htmlspecialchars((string)$rec->fields['products_name'] . $option . $manufacturer, ENT_QUOTES, CHARSET) . '</td>
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

	function get_request_for_review_content($oid, $limit_products, $mailto, $customer_name, $customer_id, $is_test = false, $live_optout_test = false){
		global $db, $store_name, $store_email, $images_folder, $template_images_folder, $store_address;

		#INITIALIZE VARIABLES
		$product_list_ar = get_products_pending_review( $oid );
		$at_least_one_product = false;

		#LOAD PRODUCT INFORMATION
		$sql = "SELECT DISTINCT p.`products_id`, p.`products_image`
				FROM `" . TABLE_ORDERS_PRODUCTS . "` AS op
					JOIN `" . TABLE_PRODUCTS . "` AS p
						on op.`products_id` = p.`products_id`
					LEFT JOIN `" . TABLE_REVIEWS . "` AS r
						ON r.`products_id` = p.`products_id` AND r.`customers_id` = '" . (int)$customer_id . "'
				WHERE op.`orders_id` = '" . (int)$oid . "'
					AND r.`reviews_id` IS NULL";
		if( (int)$limit_products > 0 ){
			$sql .= ' LIMIT ' . zen_db_prepare_input( (int)$limit_products );
		}
		$rec = $db->Execute( $sql );

		#INITIALIZE PRODUCT LIST VARIABLES
		$tokens = ['{customer_name}' => $customer_name, '{store_name}' => $store_name, '{order_number}' => (string)$oid];
		$greeting = strtr(products_review_reminder_decode_text(defined('REVIEWS_REMINDER_EMAIL_GREETING') ? REVIEWS_REMINDER_EMAIL_GREETING : 'Hello {customer_name},'), $tokens);
		$intro = strtr(products_review_reminder_decode_text(defined('REVIEWS_REMINDER_EMAIL_INTRO') ? REVIEWS_REMINDER_EMAIL_INTRO : 'Thank you for shopping with {store_name}.'), $tokens);
		$question = products_review_reminder_decode_text(defined('REVIEWS_REMINDER_EMAIL_QUESTION') ? REVIEWS_REMINDER_EMAIL_QUESTION : 'How did this item meet your expectations?');
		$cta = products_review_reminder_decode_text(defined('REVIEWS_REMINDER_EMAIL_CTA') ? REVIEWS_REMINDER_EMAIL_CTA : 'Rate and review this item');
		$closing = strtr(products_review_reminder_decode_text(defined('REVIEWS_REMINDER_EMAIL_CLOSING') ? REVIEWS_REMINDER_EMAIL_CLOSING : 'Thank you for sharing your experience.'), $tokens);
		$test_notice = $live_optout_test
			? 'LIVE OPT-OUT TEST: No customer was emailed and this order was not recorded as contacted. Clicking the opt-out link will opt out the customer represented by this order.'
			: 'TEST PREVIEW: No customer was emailed and this order was not recorded as contacted.';
		$test_html = $is_test ? '<div style="padding:10px;margin-bottom:15px;background:#fff3cd;border:1px solid #ffecb5"><strong>' . ($live_optout_test ? 'Live opt-out test:' : 'Test preview:') . '</strong> ' . htmlspecialchars(substr($test_notice, strpos($test_notice, ':') + 2), ENT_QUOTES, CHARSET) . '</div>' : '';
		$test_text = $is_test ? $test_notice . "\r\n\r\n" : '';
		$html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="width:100%;border-collapse:collapse;background:#ffffff"><tr><td align="left" bgcolor="#ffffff" style="background:#ffffff;text-align:left"><div style="width:100%;max-width:600px;margin:0;font-family:Arial,sans-serif;color:#333;text-align:left">' . $test_html . '<p>' . nl2br(htmlspecialchars($greeting, ENT_QUOTES, CHARSET)) . '</p><p>' . nl2br(htmlspecialchars($intro, ENT_QUOTES, CHARSET)) . '</p><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;border-collapse:collapse"><tbody>' . "\r\n";
		$text = $test_text . $greeting . "\r\n\r\n" . $intro . "\r\n\r\n";

		while( !$rec->EOF ){
			if( in_array( $rec->fields['products_id'], $product_list_ar ) ){
				$at_least_one_product = true;
				$review_page_link = zen_catalog_href_link('addon_my_reviews', 'pid=' . (int)$rec->fields['products_id'], 'SSL');
				$review_page_text_link = html_entity_decode($review_page_link, ENT_QUOTES | ENT_HTML5, CHARSET);
				$review_page_html_link = htmlspecialchars($review_page_text_link, ENT_QUOTES, CHARSET);
				$product_name = zen_get_products_name((int)$rec->fields['products_id']);
				#LOAD HTML VERSION OF THE MESSAGE
				$html .= '
	<tr>
		<td colspan="2">
			<div style="font-size:18px; color:#000000; margin:10px 0; font-family:arial,helvetica,verdana,sans-serif">' . nl2br(htmlspecialchars($question, ENT_QUOTES, CHARSET)) . '</div>
		</td>
	</tr>
	<tr>
		<td width="170" valign="top" style="width:170px;padding:0 20px 15px 0">
			<a href="' . $review_page_html_link . '" target="_blank">
				<img src="' . htmlspecialchars($images_folder . (string)$rec->fields['products_image'], ENT_QUOTES, CHARSET) . '" width="150" alt="' . htmlspecialchars((string)$product_name, ENT_QUOTES, CHARSET) . '" style="display:block;width:150px;max-width:150px;height:auto;margin:10px 0;border:0" />
			</a>
		</td>
		<td valign="top" style="padding:10px 0 15px 0;text-align:left">
			<div style="font-size:14px;color:#666666;font-family:arial,helvetica,verdana,sans-serif">' . htmlspecialchars((string)$product_name, ENT_QUOTES, CHARSET) . '</div>
			<div style="font-size:20px; color:#124c90; font-family:arial,helvetica,verdana,sans-serif; margin:20px 0 5px 0;">' . nl2br(htmlspecialchars($cta, ENT_QUOTES, CHARSET)) . '</div>
			<a href="' . $review_page_html_link . '" target="_blank" rel="noopener" style="font-size:28px;color:#e0a100;text-decoration:none" aria-label="' . htmlspecialchars($cta, ENT_QUOTES, CHARSET) . '">&#9733;&#9733;&#9733;&#9733;&#9733;</a>
		</td>
	</tr>' . "\r\n";
				#LOAD PLAIN TEXT VERSION OF THE MESSAGE
				$text .= $question . "\r\n" . $product_name . "\r\n" . $cta . "\r\n" . $review_page_text_link . "\r\n\r\n";
			}
			$rec->MoveNext();
		}

		#ASSEMBLYING THE HTML MESSAGE
		$reviews_url = zen_catalog_href_link('addon_my_reviews', '', 'SSL');
		$reviews_text_url = html_entity_decode($reviews_url, ENT_QUOTES | ENT_HTML5, CHARSET);
		$reviews_html_url = htmlspecialchars($reviews_text_url, ENT_QUOTES, CHARSET);
		$test_optout_url = zen_catalog_href_link('addon_reviews_reminder_optout', 'test=1', 'SSL');
		$test_optout_text_url = html_entity_decode($test_optout_url, ENT_QUOTES | ENT_HTML5, CHARSET);
		$test_optout_html_url = htmlspecialchars($test_optout_text_url, ENT_QUOTES, CHARSET);
		$optout_html = '<p style="font-size:11px;color:#777"><a href="' . $test_optout_html_url . '">Test the opt-out page</a>. Test mode will not change any customer preferences.</p>';
		$optout_text = "Test the opt-out page (no customer preferences will change):\r\n" . $test_optout_text_url;
		if (!$is_test || $live_optout_test) {
			$optout_secret = (string)STORE_OWNER_EMAIL_ADDRESS . '|' . (defined('DB_SERVER_PASSWORD') ? (string)DB_SERVER_PASSWORD : '');
			$optout_token = hash_hmac('sha256', (string)$customer_id, $optout_secret);
			$optout_url = zen_catalog_href_link('addon_reviews_reminder_optout', 'customer_id=' . $customer_id . '&token=' . $optout_token, 'SSL');
			$optout_text_url = html_entity_decode($optout_url, ENT_QUOTES | ENT_HTML5, CHARSET);
			$optout_html_url = htmlspecialchars($optout_text_url, ENT_QUOTES, CHARSET);
			$optout_html = '<p style="font-size:11px;color:#777">To stop product review reminders, <a href="' . $optout_html_url . '">opt out here</a>.</p>';
			$optout_text = 'Opt out: ' . $optout_text_url;
		}
		$html .= '</tbody></table><p>' . nl2br(htmlspecialchars($closing, ENT_QUOTES, CHARSET)) . '</p><p><a href="' . $reviews_html_url . '">Review your purchases</a></p>' . $optout_html . '</div></td></tr></table>' . "\r\n";
		$email_content['html'] = $html;

		#ASSEMBLYING THE TEXT MESSAGE
		$email_content['text'] = $text . $closing . "\r\n\r\nReview your purchases: " . $reviews_text_url . "\r\n" . $optout_text;

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
