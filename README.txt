Products Review Reminder   -   Version: 1.0.4
TESTED ON A FRESH INSTALL OF ZEN CART VERSION 2.0.1 & PHP 8.3

*** PROBLEM DEFINITION ***
Customers tend not to rate and reviews products unless they have a problem. For that reason, reviews tend to be negative. 
By proactively requesting product reviews, we intend to achieve two goals:
1) Increase the number of product reviews, 
2) Increase the rate of positive product reviews.

*** PLUGIN OVERVIEW ***
This plugin provides your web store with the ability to request product reviews like those from Amazon.com, on demand.
The plugin does not overwrite any core file. That way, you can easily try the Product Review Reminder plugin and easily uninstall it, if you find that it does not fit your needs.
Since it does not touch any core file, the plugin will not affect any future Zen Cart updates.
Please, follow the instructions provided in the readme.txt file on how to install the plugin.
For peace of mind, remember to backup any production website and live database prior to installing the plugin.
Plugin developed and tested on Zen Cart v.1.5.5e running on Apache/2.4.23 with PHP 5.6.24 and MySQL 5.5.5-10.1.16-MariaDB

*** INSTALLATION ***
1. Unzip the contents of this file in an empty folder
2. Rename the following two sub-folders:
./products_review_reminder/YOUR_ADMIN
./products_review_reminder/includes/templates/MY_TEMPLATE
3. Upload all contents of the ./products_review_reminder/ folder into your web store.
4. Login to the administrative area of your web store and go to: Tools > Install SQL Patches. Click the [Choose File] button, select the install.sql file provided, click [Open] and then [upload].

*** FINE TUNING ***
1. For a better e-mail presentation, make sure HTML e-mails are enabled at:
Admin: Configuration > E-Mail Options > Enable HTML Emails? True
2. If your store logo is not showing on your HTML e-mail, edit following file:
./email/email_template_addon_review_reminder.html
Replace 'includes/templates/responsive_classic/images/logo.gif' with the location of your store logo (on line 13).

*** USING YOUR NEW PLUGIN ***
1. Go to Admin: Configuration > Product Review Reminder. Change any setting you want based on your preferences.
2. Go to Admin: Tools > Product Review Reminder. If you have any eligible order based on your settings in the previous interface, those should show here.
3. Select all orders you want to send a request for review and click the [send mail] button.

*** TESTING YOUR NEW PLUGIN ***
1. Place a test order on your web store
2. In admin, go to Customers > Orders, locate your order and update it by changing its status from Pending to the "Trigger Order Status" defined in your plugin configuration (Admin: Configuration > Product Review Reminder)
3. In Admin: Configuration > Product Review Reminder > Cool-Off Period, select the Now option. That should remove any waiting period and make your newly placed order eligible for a product review.
4. In Admin: Tools > Product Review Reminder, uncheck all orders by clicking the checkbox in the table's header. Locate your order, click the checkbox to select that order and then click the [send mail] button.

*** ADDING A LINK TO THE REVIEWS PAGE INTO THE CUSTOMER'S MY ACCOUNT PAGE ***
To add a link to the My Reviews page into customers' My Account interface, change the following two files:

1. File Name and Location
./includes/templates/[YOUR_TEMPLATE]/templates/tpl_account_default.php

Add
<li><?php echo ' <a href="/myreviews">' . MY_ACCOUNT_PRODUCT_REVIEWS . '</a>'; ?></li>

After
<li><?php echo ' <a href="' . zen_href_link(FILENAME_ACCOUNT_PASSWORD, '', 'SSL') . '">' . MY_ACCOUNT_PASSWORD . '</a>'; ?></li>

2. File Name and Location
./includes/languages/english/account.php

Add:
define('MY_ACCOUNT_PRODUCT_REVIEWS', 'My Product Reviews');

---
DISCLAIMER: THE PRODUCT REVIEW REMINDER PLUGIN IS PROVIDED "AS IS", WITH NO WARRANTY OF ANY KIND, EXPRESS OR IMPLIED. IN NO EVENT SHALL THE DEVELOPER BE LIABLE FOR ANY DAMAGES OR OTHER LIABILITY ARISING FROM, OUT OF OR IN CONNECTION WITH THE PRODUCT REVIEW REMINDER PLUGIN. 

FINAL NOTE: PRIOR TO INSTALLING THIS EXTENSION ON ANY PRODUCTION WEBSITE, WE RECOMMEND THAT YOU CREATE A FRESH BACKUP OF YOUR WEBSITE (SCRIPT FILES AND DATABASE).

Products Review Reminder   -   Version: 1.0.4 updated for PHP 8 & Zen Cart 2.0.1 by PRO-Webs.net
