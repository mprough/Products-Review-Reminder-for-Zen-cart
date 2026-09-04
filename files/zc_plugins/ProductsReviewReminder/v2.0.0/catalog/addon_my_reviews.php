<?php
/*
 * @package addon_review_reminder
 * @copyright Copyright 2003-2017 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: addon_my_reviews.php 2024/27/08 PRO-Webs.net v.1.2 $
 * @author Will Vasconcelos willvasconcelos@outlook.com $
*/
/* Using the following convention for status_id returning as json feedback:
 * 0 = Success
 * 1 = Fail: session expired
 * 2 = Fail: product never ordered
 */
	include "includes/application_top.php";
	
	if ( isset($_SESSION['customer_id']) and (int)$_SESSION['customer_id'] > 0 ) {
		$customer_id = zen_db_prepare_input( (int)$_SESSION['customer_id'] );
		$customer_record = $db->Execute('SELECT customers_firstname, customers_lastname FROM ' . TABLE_CUSTOMERS . ' WHERE customers_id = ' . (int)$customer_id . ' LIMIT 1');
		$customer_name = trim((string)($customer_record->fields['customers_firstname'] ?? '') . ' ' . (string)($customer_record->fields['customers_lastname'] ?? ''));
		$data = json_decode(file_get_contents('php://input'), true);
		#GET ACTION
		$action = false;
		if( isset($data['action']) ){
			$action = zen_db_prepare_input( $data['action'] );
		}
		#GET SANITIZED RATING
		$rating = false;
		if( isset($data['rating']) and (int)$data['rating'] > 0 and (int)$data['rating'] <= 5 ){
			$rating = zen_db_prepare_input( (int)$data['rating'] );
		}
		#GET SANITIZED REVIEW
		$review = '';
		if( isset($data['review']) and trim( $data['review'] ) != '' ){
			$review = zen_db_prepare_input( $data['review'] );
		}
		#GET SANITIZED PRODUCT ID
		$product_id = -1;
		if( isset($data['product_id']) and (int)$data['product_id'] > 0 ){
			$product_id = zen_db_prepare_input( (int)$data['product_id'] );
		}
		#GET REVIEW STATUS
		$review_active = 1;
		if( $review != '' ){
			$review_active = 0;
		}
		#PROCESS ADD or UPDATE REVIEW
		if( $action == 'add' and $product_id > 0 ){
			#CONFIRM THAT THE CUSTOMER HAS ACTUALLY BOUGHT THAT PRODUCT
			$sql = "SELECT op.`products_id`
					FROM `" . TABLE_ORDERS_PRODUCTS . "` AS op 
						JOIN `" . TABLE_ORDERS . "` AS o
						ON op.`orders_id` = o.`orders_id`
					WHERE op.`products_id` = '" . $product_id . "'
						AND o.`customers_id` = '" . $customer_id . "'";
			
			$rec = $db->Execute( $sql );
			if( !$rec->EOF ){ #START: IF CUSTOMER BOUGHT THAT PRODUCT
				#CHECK IF CUSTOMER ALREADY HAS A REVIEW FOR THAT PRODUCT
				$sql = "SELECT `reviews_id` 
						FROM `" . TABLE_REVIEWS . "`
						WHERE `customers_id` = '" . $customer_id . "'
							AND `products_id` = '" . $product_id . "'
						ORDER BY `date_added` DESC
						LIMIT 1";
				
				$rec = $db->Execute($sql);
				
				if( !$rec->EOF ){ #IF REVIEW EXISTS: UPDATE LAST REVIEW
					#UPDATE THE REVIEWS TABLE
					$sql = "UPDATE `" . TABLE_REVIEWS . "` 
							SET `last_modified` = '" . date("Y-m-d H:i:s") . "'";
					if( $rating ){
						$sql .= ", `reviews_rating` = '" . $rating . "'";
					}
					$sql .= ", status = " . $review_active;
					
					$sql .= " WHERE `reviews_id` = '" . zen_db_prepare_input( (int)$rec->fields['reviews_id'] ) . "'";
					
					$db->Execute($sql);
					
					#UPDATE THE REVIEWS_DESCRIPTION TABLE IF NEEDED
					$sql = "UPDATE `" . TABLE_REVIEWS_DESCRIPTION . "`
							SET `reviews_text` = :reviewText
							WHERE `reviews_id` = '" . (int)$rec->fields['reviews_id'] . "'";
					$sql = $db->bindVars($sql, ':reviewText', $review, 'string');
					$db->Execute($sql);
				}else{ #NEW: ADD REVIEW
					#TABLE: REVIEWS
					$sql = "INSERT INTO " . TABLE_REVIEWS . " (products_id, customers_id, customers_name, reviews_rating, date_added, status)
							VALUES (:productsID, :customersID, :customersName, :rating, now(), '" . $review_active . "')";
					$sql = $db->bindVars($sql, ':productsID', $data['product_id'], 'integer');
					$sql = $db->bindVars($sql, ':customersID', $customer_id, 'integer');
					$sql = $db->bindVars($sql, ':customersName', $customer_name, 'string');
					$sql = $db->bindVars($sql, ':rating', $rating, 'integer');
					$db->Execute($sql);
					$insert_id = $db->Insert_ID();
					
					#TABLE: REVIEWS_DESCRIPTION
					$sql = "INSERT INTO " . TABLE_REVIEWS_DESCRIPTION . " (reviews_id, languages_id, reviews_text)
							VALUES (:insertID, :languagesID, :reviewText)";

					$sql = $db->bindVars($sql, ':insertID', $insert_id, 'integer');
					$sql = $db->bindVars($sql, ':languagesID', $_SESSION['languages_id'], 'integer');
					$sql = $db->bindVars($sql, ':reviewText', $review, 'string');
					$db->Execute($sql);
				} #END: IF REVIEW EXISTS
			}else{ #CUSTOMER DID NOT BUY THAT PRODUCT
				echo 'You cannot review this product because we do not have a record of an order for this product under your account id.';
			} #END: IF CUSTOMER BOUGHT THAT PRODUCT
		} #END: ADD REVIEW
		
		#PROCESS UPDATE E-MAIL NOTIFICATION SUBSRCIPTION
		if( $action == 'optout' ){ #DO NOT SEND EMAIL
			$sql = "INSERT INTO `" . TABLE_ADDON_REVIEW_REMINDER_OPTOUT . "` (`customers_id`, `orders_id`, `date_time`) VALUES ('" . $customer_id . "', 0, NOW()) ON DUPLICATE KEY UPDATE `date_time` = NOW()";
			$db->Execute($sql);
		}
		
		if( $action == 'signup' ){ #SEND EMAIL
			$sql = "DELETE FROM `" . TABLE_ADDON_REVIEW_REMINDER_OPTOUT . "` WHERE `customers_id` = '" . $customer_id . "'";
			$db->Execute($sql);
		}
	}else{ #SESSION EXPIRED
		echo 'Your session expired. Please, login and try again.';
	}
