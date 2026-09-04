--UNINSTALL: PRODUCT REVIEW REMINDER

--DELETE ANY PREVIOUS INSTALATION
SET @configuration_group_id = 0;
SELECT (@configuration_group_id:=configuration_group_id) FROM configuration_group WHERE configuration_group_title = 'Product\'s Review Reminder' LIMIT 1;
DELETE FROM configuration WHERE configuration_group_id = @configuration_group_id AND @configuration_group_id != 0;
DELETE FROM configuration_group WHERE configuration_group_id = @configuration_group_id AND @configuration_group_id != 0;
DELETE FROM admin_pages WHERE page_key = 'configProductReviewReminder';
DELETE FROM admin_pages WHERE page_key = 'toolsProductReviewReminder';
