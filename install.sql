SET @configuration_group_id = 0;
SELECT (@configuration_group_id:=configuration_group_id) FROM configuration_group WHERE configuration_group_title = 'Product\'s Review Reminder' LIMIT 1;
DELETE FROM configuration WHERE configuration_group_id = @configuration_group_id AND @configuration_group_id != 0;
DELETE FROM configuration_group WHERE configuration_group_id = @configuration_group_id;
DELETE FROM admin_pages WHERE page_key = 'configProductReviewReminder';
DELETE FROM admin_pages WHERE page_key = 'toolsProductReviewReminder';

INSERT INTO configuration_group (configuration_group_id, configuration_group_title, configuration_group_description, sort_order, visible) VALUES (NULL, 'Product\'s Review Reminder', 'Configure automatic product review requests.', '1', '1');
SET @configuration_group_id=last_insert_id();
UPDATE configuration_group SET sort_order = @configuration_group_id WHERE configuration_group_id = @configuration_group_id;

INSERT INTO configuration 
(configuration_id, 
configuration_title, 
configuration_key, 
configuration_value, 
configuration_description, 
configuration_group_id, 
sort_order, 
date_added, 
use_function, 
set_function)
VALUES
(NULL, 
'Trigger Order Status', 
'REVIEWS_REMINDER_TRIGGER_ORDER_STATUS', 
3, 
'Which order status should trigger the countdown to send a request for product review? The recommended status is Shipped. However, you can change that value, case your store has additional custom status options.', 
@configuration_group_id, 
1, 
CURRENT_TIMESTAMP,
'zen_get_order_status_name', 
'zen_cfg_pull_down_order_statuses('),

(NULL, 
'Cool-Off Period', 
'REVIEWS_REMINDER_COOLOFF_PERIOD', 
'Two Weeks', 
'Amount of time after the order ships to wait before the plugin will trigger the submission of a request for a product review.', 
@configuration_group_id, 
2, 
CURRENT_TIMESTAMP, 
NULL, 
'zen_cfg_select_option(array(\'Now\', \'One Week\', \'Two Weeks\', \'Three Weeks\', \'Four Weeks\', \'One Months\', \'Two Months\', \'Three Months\'),');

INSERT INTO configuration 
(configuration_id, 
configuration_title, 
configuration_key, 
configuration_value, 
configuration_description, 
configuration_group_id, 
sort_order, 
date_added, 
use_function, 
set_function)
VALUES

(NULL, 
'Date Window',
'REVIEWS_REMINDER_TIME_WINDOW', 
'One Week', 
'Considers all orders shipped a number of days before the cool-off period kicks in. What that means is, orders placed a long time ago will be ignored. As an example, if the cool-off period is set to one week and the order date window is set to one week, all orders shipped two weeks ago (up to the cool-off period) will be selected as candidates for a product reminder e-mail.',
@configuration_group_id, 
3, 
CURRENT_TIMESTAMP, 
NULL, 
'zen_cfg_select_option(array(\'One Week\', \'Two Weeks\', \'Three Weeks\', \'One Month\', \'Two Months\', \'Three Months\', \'Six Months\', \'One Year\'),'),

(NULL, 
'Maximum Number of Products', 
'REVIEWS_REMINDER_MAX_PRODUCTS', 
'No Limit', 
'Limit the number of products in a given order to include in the request for review.', 
@configuration_group_id, 
4, 
CURRENT_TIMESTAMP, 
NULL, 
'zen_cfg_select_option(array(\'No Limit\', \'One Product\', \'Two Products\', \'Four Products\', \'Eight Products\', \'Sixteen Products\', \'Thirty Two Products\'),');

INSERT IGNORE INTO admin_pages (page_key,language_key,main_page,page_params,menu_key,display_on_menu,sort_order) VALUES ('configProductReviewReminder','BOX_CONFIGURATION_REVIEW_REMINDER','FILENAME_CONFIGURATION',CONCAT('gID=',@configuration_group_id),'configuration','Y',@configuration_group_id);

INSERT IGNORE INTO admin_pages (page_key,language_key,main_page,page_params,menu_key,display_on_menu,sort_order) VALUES ('toolsProductReviewReminder','BOX_CONFIGURATION_REVIEW_REMINDER','FILENAME_ADDON_REVIEW_REMINDER','','tools','Y',@configuration_group_id);

CREATE TABLE IF NOT EXISTS addon_review_reminder_log (
 `orders_id` int(11) NOT NULL,
 `date_time` datetime NOT NULL,
 `sent_by` varchar(16) NOT NULL DEFAULT 'web',
 PRIMARY KEY (`orders_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS addon_review_reminder_optout (
 `customers_id` int(11) NOT NULL,
 `orders_id` int(11) NOT NULL,
 `date_time` datetime NOT NULL,
 PRIMARY KEY (`customers_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;