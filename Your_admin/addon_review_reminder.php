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
	if( isset($_GET['action']) and $_GET['action'] != '' ){
		$action = zen_db_prepare_input( $_GET['action'] );
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
		$automatic_mode = false;	#DEFAULT: DO NOT ACTIVATE CRON JOB
		$trigger_order_status = 3;	#DEFAULT: SHIPPING [3]
		$cooloff_period = strtotime("-1 week");	#DEFAULT: ONE WEEK
		$cooloff_selection = 'One Week';		#DEFAULT: ONE WEEK
		$start_date = strtotime("-1 weeks", $cooloff_period);	#DEFAULT: ONE WEEK BEFORE THE COOLOFF DATE
		$max_products = 'No Limit';	#DEFAULT: LIST ALL PRODUCTS ORDERED
		$limit_products = -1;		#DEFAULT: LIST ALL PRODUCTS ORDERED
		
		$sql = "SELECT configuration_title, configuration_key, configuration_value
					FROM `" . TABLE_CONFIGURATION . "`
					WHERE `configuration_group_id` = '" . zen_db_prepare_input( $gid ) . "'
					ORDER BY `sort_order` ASC";
		$rec = $db->Execute($sql);
		while(!$rec->EOF){
			switch( $rec->fields['configuration_key'] ){
				case 'REVIEWS_REMINDER_AUTOMATIC':
					if( $rec->fields['configuration_value'] = 'Yes' )
						$automatic_mode = true;
					break;
				case 'REVIEWS_REMINDER_TRIGGER_ORDER_STATUS':
					$trigger_order_status = $rec->fields['configuration_value'];
					break;
				case 'REVIEWS_REMINDER_COOLOFF_PERIOD':
					$cooloff_selection = $rec->fields['configuration_value'];
					switch( $cooloff_selection ){
						case 'Now':
							$cooloff_period = strtotime(date("Y-m-d H:i:s"));
							break;
						case 'One Week':
							$cooloff_period = strtotime("-1 week");
							break;
						case 'Two Weeks':
							$cooloff_period = strtotime("-2 weeks");
							break;
						case 'Three Weeks':
							$cooloff_period = strtotime("-3 weeks");
							break;
						case 'One Month':
							$cooloff_period = strtotime("-1 month");
							break;
						case 'Two Months':
							$cooloff_period = strtotime("-2 months");
							break;
						case 'Three Months':
							$cooloff_period = strtotime("-3 months");
							break;
						case 'Six Months':
							$cooloff_period = strtotime("-6 months");
							break;
						default:
							$cooloff_period = strtotime("-1 year");
							break;
					}
					break;
				case 'REVIEWS_REMINDER_TIME_WINDOW':
					switch( $rec->fields['configuration_value'] ){
						case 'One Week':
							$start_date = strtotime("-1 weeks", $cooloff_period);
							break;
						case 'Two Weeks':
							$start_date = strtotime("-2 weeks", $cooloff_period);
							break;
						case 'Three Weeks':
							$start_date = strtotime("-3 weeks", $cooloff_period);
							break;
						case 'One Month':
							$start_date = strtotime("-1 month", $cooloff_period);
							break;
						case 'Two Months':
							$start_date = strtotime("-2 months", $cooloff_period);
							break;
						case 'Three Months':
							$start_date = strtotime("-3 months", $cooloff_period);
							break;
						case 'Six Months':
							$start_date = strtotime("-6 months", $cooloff_period);
							break;
						default:
							$start_date = strtotime("-1 year", $cooloff_period);
							break;
					}
					break;
				case 'REVIEWS_REMINDER_MAX_PRODUCTS':
					$max_products = $rec->fields['configuration_value'];
					if( $max_products != 'No Limit' ){
					switch( $max_products ){
						case 'One Product':
							$limit_products = 1;
							break;
						case 'Two Products':
							$limit_products = 2;
							break;
						case 'Four Products':
							$limit_products = 4;
							break;
						case 'Eight Products':
							$limit_products = 8;
							break;
						case 'Sixteen Products':
							$limit_products = 16;
							break;
						case 'Thirty Two Products':
							$limit_products = 32;
							break;
						default:
							$limit_products = -1;
							break;
					}
				}
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
	
	#PROCESS SEND REVIEW REQUEST IF ANY
	if( isset($_POST['orders_id']) and is_array($_POST['orders_id']) and count($_POST['orders_id']) > 0 ){
		foreach( $_POST['orders_id'] as $oid ){
			$oid = zen_db_prepare_input( $oid );
			#LOAD ORDER INFORMATION
			$sql = "SELECT `customers_name`, `customers_company`, `customers_email_address`
					FROM `" . TABLE_ORDERS . "`
					WHERE `orders_id`='" . $oid . "'";
			$rec = $db->Execute($sql);
			if(!$rec->EOF){
				$mailto = $rec->fields['customers_email_address'];
				if( trim($rec->fields['customers_name']) != '' )
					$customer_name = $rec->fields['customers_name'];
				else 
					$customer_name = $rec->fields['customers_company'];
				$subject = sprintf(EMAIL_REVIEW_REMINDER_SUBJECT, $customer_name, $store_name);
			}
			
			#SEND EMAIL
			$message = get_request_for_review_content( $oid, $limit_products, $mailto, $customer_name );
			if( $message ){
				$block['EMAIL_MESSAGE_HTML'] = $message['html'];
				$block['EMAIL_TO_ADDRESS'] = $mailto;
				zen_mail($customer_name, $mailto, $subject, $message['text'], STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, $block, 'addon_review_reminder' );
				#UPDATE THE DATABASE
				$sql = "INSERT INTO `" . TABLE_ADDON_REVIEW_REMINDER_LOG . "` (`orders_id`, `date_time`, `sent_by`)
						VALUES ('" . $oid . "', '" . date("Y-m-d H:i:s") . "','" . zen_db_prepare_input((int)$_SESSION['admin_id']) . "')";
				$db->Execute($sql);
			}
		}
	}
	#PROCESS REMOVE ORDER FROM THE LIST IF ANY
	if( $action == 'remove' and $order_id > 0 ){
		$sql = "INSERT INTO `" . TABLE_ADDON_REVIEW_REMINDER_LOG . "`
				(`orders_id`, `date_time`, `sent_by`)
				VALUES ('" . $order_id . "', '" . date("Y-m-d H:i:s") . "','" . zen_db_prepare_input((int)$_SESSION['admin_id']) . "')";
		$db->Execute($sql);
		zen_redirect(FILENAME_ADDON_REVIEW_REMINDER . ".php", 'SSL');
	}
?>
<!doctype html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html <?php echo HTML_PARAMS; ?>>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
<title><?php echo TITLE; ?></title>
<link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
<link rel="stylesheet" type="text/css" href="includes/cssjsmenuhover.css" media="all" id="hoverJS">
<script language="javascript" src="includes/menu.js"></script>
<script language="javascript" src="includes/general.js"></script>
<script type="text/javascript">
  <!--
  function init()
  {
    cssjsmenu('navbar');
    if (document.getElementById)
    {
      var kill = document.getElementById('hoverJS');
      kill.disabled = true;
    }
  }
  // -->
</script>
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
</style>
</head>
<body onLoad="init()">
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
			<table border="0" width="100%" cellspacing="0" cellpadding="0">
				<tr>
					<td valign="top">
<?php
	echo zen_draw_form('frmRequestReviews', FILENAME_ADDON_REVIEW_REMINDER, '', 'post');
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
			ORDER BY o.`orders_id` DESC";
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
						<table border="0" width="100%" cellspacing="0" cellpadding="2">
							<tbody>
							<tr class="infoBoxHeading">
								<td class="infoBoxHeading"><b><?php echo TBL_INFO_HEADER; ?></b></td>
							</tr>
							</tbody>
						</table>
<?php
	echo get_packing_list( $selected_order_id, $limit_products );
	echo '<a href="' . FILENAME_ADDON_REVIEW_REMINDER . '.php?oid=' . $selected_order_id . '&action=remove">' . zen_image_submit('button_remove.gif', remove, 'id="btnRemove"') . '</a>' . "\n";
?>
					</td>
				</tr>
			</table>
		</td>	
	</tr>
</table>
<!-- body_eof //-->
<!-- footer //-->
<?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
<!-- footer_eof //-->
<br>
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>

<?php
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
	
	function get_request_for_review_content( $oid, $limit_products, $mailto, $customer_name ){
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
		$html = ' <table style="text-align:left; border-bottom:solid #cccccc 1px; border-spacing:0px; border-collapse:collapse; margin:0 auto;" width="460" cellspacing="0" cellpadding="0"> <tbody>' . "\r\n";
		$text = '';
		
		while( !$rec->EOF ){
			if( in_array( $rec->fields['products_id'], $product_list_ar ) ){
				$at_least_one_product = true;
				$review_page_link = HTTP_CATALOG_SERVER . '/index.php?main_page=addon_my_reviews&pid=' . $rec->fields['products_id'];
				$product_name = zen_get_products_name((int)$rec->fields['products_id']);
				#LOAD HTML VERSION OF THE MESSAGE
				$html .= '
	<tr>
		<td colspan="2">
			<div style="font-size:18px; color:#000000; margin:10px 0; font-family:arial,helvetica,verdana,sans-serif">' . EMAIL_EXPECTATION_QUESTION . '</div>
		</td>
	</tr>
	<tr>
		<td valign="top">
			<a href="' . $review_page_link . '" target="_blank">
				<img src="' . $images_folder . $rec->fields['products_image'] . '" style="width:150px; margin:10px 0;" />
			</a>
		</td>
		<td valign="top">
			<div style="font-size:14px;color:#666666;font-family:arial,helvetica,verdana,sans-serif">' . $product_name . '</div>
			<div nowrap="" style="font-size:20px; color:#e47911; font-family:arial,helvetica,verdana,sans-serif; margin:20px 0 5px 0;">' . EMAIL_ACTION_CALL . '</div>
			<a href="' . $review_page_link . '" target="_blank">
				<img src="' . $template_images_folder . 'rating_star.png" style="position:relative; float:left;" />
				<img src="' . $template_images_folder . 'rating_star.png" style="position:relative; float:left;" />
				<img src="' . $template_images_folder . 'rating_star.png" style="position:relative; float:left;" />
				<img src="' . $template_images_folder . 'rating_star.png" style="position:relative; float:left;" />
				<img src="' . $template_images_folder . 'rating_star.png" style="position:relative; float:left;" />
			</a>
		</td>
	</tr>' . "\r\n";
				#LOAD PLAIN TEXT VERSION OF THE MESSAGE
				$text .= EMAIL_EXPECTATION_QUESTION . "\r\n" . $product_name . "\r\n" . EMAIL_ACTION_CALL . "\r\n" . $review_page_link . "\r\n\n";
			}
			$rec->MoveNext();
		}
		
		#ASSEMBLYING THE HTML MESSAGE
		$html .= '</tbody></table>' . "\r\n";
		$email_content['html'] = $html;
		
		#ASSEMBLYING THE TEXT MESSAGE
		$head = sprintf(EMAIL_REVIEW_TEXT_HEAD, $store_name);
		$foot = sprintf(EMAIL_REVIEW_TEXT_FOOTER, $mailto, $store_name, $store_address);
		$email_content['text'] = $head . $text . $foot;
		
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
