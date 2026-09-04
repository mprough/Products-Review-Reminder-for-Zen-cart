# Products Review Reminder for Zen Cart

Products Review Reminder helps a shop owner find eligible completed orders and manually send customers a request for honest product feedback. Version 2.0.1 is maintained by Melanie Prough of [PRO-Webs, Inc.](https://pro-webs.net/).

## Features

- Lists orders after a configurable order status and waiting period.
- Excludes orders already contacted and customers who opted out.
- Sends reminders only when the administrator explicitly selects orders and submits the form.
- Lets the shop owner edit this plugin's subject, greeting, introduction, product question, review link text, and closing in Zen Cart admin.
- Provides HTML and plain-text email content.
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

## Email tokens

The subject, greeting, introduction, and closing accept `{customer_name}`, `{store_name}`, and `{order_number}`. Product information, review URLs, and opt-out URLs are protected functional content.

## Upgrade from 1.0.4

1. Back up the shop files and database.
2. Manually remove the old loose plugin files.
3. Do not run the legacy `uninstall.sql`.
4. Do not delete or empty the `addon_review_reminder_log` or `addon_review_reminder_optout` database tables.
5. Copy the new `files` directory contents into the shop root.
6. Install version 2.0.1 through **Modules > Plugin Manager**.
7. Confirm the settings under **Configuration > Products Review Reminder** before sending a reminder.

The installer reuses both existing database tables so previous send history and customer opt-outs remain effective. It recognizes the former configuration group, migrates supported waiting-period, date-window, and maximum-product settings, and replaces the legacy menu registrations. Existing MyISAM tables can remain MyISAM; new installations create the tables with InnoDB.

## Uninstall

Plugin Manager removes plugin registration and settings. Reminder history and customer opt-outs remain to prevent accidental repeat email and data loss.

## Support

Report reproducible bugs through the [PRO-Webs helpdesk](https://prowebsinc.zohodesk.com/portal/en/newticket). Installation, configuration, and customization are not included with free distribution.

See the [Zen Cart plugin listing](https://www.zen-cart.com/plugins/products-review-reminder-vb2148) and the [GitHub repository](https://github.com/mprough/Products-Review-Reminder-for-Zen-cart).

## License

GNU General Public License version 2. This software is provided without warranty.
