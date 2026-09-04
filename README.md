# Products Review Reminder for Zen Cart

Products Review Reminder helps a shop owner find eligible completed orders and manually send customers a request for honest product feedback. Version 2.0.11 is maintained by Melanie Prough of [PRO-Webs, Inc.](https://pro-webs.net/).

## Features

- Lists orders after a configurable order status and waiting period.
- Excludes orders already contacted and customers who opted out.
- Sends reminders only when the administrator explicitly selects orders and submits the form.
- Limits each displayed and submitted batch to a configurable number of reminders, 10 by default.
- Lets the shop owner edit this plugin's subject, greeting, introduction, product question, review link text, and closing in Zen Cart admin.
- Provides HTML and plain-text email content.
- Previews a reminder using any real order without sending or recording it.
- Sends a test copy to the store owner without contacting the customer or recording the order.
- Includes secure review links and a signed one-click opt-out link.
- Installs through Zen Cart Plugin Manager without core-file changes.

## Compatibility

- Zen Cart 2.0.x, 2.1.x, and 2.2.x
- PHP 8.0 through 8.5, within the PHP versions supported by the installed Zen Cart release

## Installation

1. Back up the shop files and database.
2. Copy the `files` directory contents into the shop root.
3. In Zen Cart admin, open **Modules > Plugin Manager**.
4. Install the newest **Products Review Reminder** version.
5. Open **Configuration > Products Review Reminder** to choose eligibility rules and edit the reminder wording.
6. Open **Tools > Products Review Reminder** to review eligible orders and send selected reminders.

The email fields affect only review reminders sent by this plugin. They do not alter Zen Cart's other email templates.

## Email shortcodes

The Configuration page lists the available email shortcodes. The subject, greeting, introduction, and closing accept `{customer_name}`, `{store_name}`, and `{order_number}`. Copy them exactly, including the braces. Product information, review URLs, and opt-out URLs are protected functional content.

## Upgrade from 1.0.4

1. Back up the shop files and database.
2. Manually remove the old loose plugin files.
3. Do not run the legacy `uninstall.sql`.
4. Do not delete or empty the `addon_review_reminder_log` or `addon_review_reminder_optout` database tables.
5. Copy the new `files` directory contents into the shop root.
6. Install version 2.0.11 through **Modules > Plugin Manager**.
7. Confirm the settings under **Configuration > Products Review Reminder** before sending a reminder.

The installer reuses both existing database tables so previous send history and customer opt-outs remain effective. It recognizes the former configuration group, migrates supported waiting-period, date-window, and maximum-product settings, and replaces the legacy menu registrations. Existing MyISAM tables can remain MyISAM; new installations create the tables with InnoDB.

## Preview and test email

Open **Tools > Products Review Reminder**. In **Inspect an email**, enter any real order number. The order does not need to be inside the eligibility window.

- **Preview email** renders the message in admin without sending it.
- **Send test to store owner** sends the rendered message to `STORE_OWNER_EMAIL_ADDRESS`. In Zen Cart admin, this is **Configuration > Email Options > Email Address (sent FROM)**.

Preview and test actions do not contact the customer or add a reminder-log record. They use the order's customer name and products so the result matches a real reminder closely. The test email includes a safe opt-out test link that confirms the page works without changing any customer preferences.

The test panel includes an HTML or Plain text format switch. It changes both the on-page preview and the single test message sent to the store owner. A request-scoped email observer applies that choice only to this plugin's test message. The selected order customer's email preference applies only when a real reminder is sent to that customer.

## Sending batches

**Maximum reminders per batch** controls how many eligible orders appear and can be sent during one submission. The default is 10. After a successful batch, refresh **Tools > Products Review Reminder** to load the next eligible orders. This prevents a high-volume shop from attempting hundreds of messages in one browser request.

## Uninstall

Plugin Manager removes plugin registration and settings. Reminder history and customer opt-outs remain to prevent accidental repeat email and data loss.

## Support

Report reproducible bugs through the [PRO-Webs helpdesk](https://prowebsinc.zohodesk.com/portal/en/newticket). Installation, configuration, and customization are not included with free distribution.

See the [Zen Cart plugin listing](https://www.zen-cart.com/plugins/products-review-reminder-vb2148) and the [GitHub repository](https://github.com/mprough/Products-Review-Reminder-for-Zen-cart).

## License

GNU General Public License version 2. This software is provided without warranty.
