# Products Review Reminder for Zen Cart

Products Review Reminder helps a shop owner find eligible completed orders and manually send customers a request for honest product feedback. Version 2.0.0 is maintained by Melanie Prough of [PRO-Webs, Inc.](https://pro-webs.net/).

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
4. Install **Products Review Reminder**.
5. Open **Configuration > Products Review Reminder** to choose eligibility rules and edit the reminder wording.
6. Open **Tools > Products Review Reminder** to review eligible orders and send selected reminders.

The email fields affect only review reminders sent by this plugin. They do not alter Zen Cart's other email templates.

## Email tokens

The subject, greeting, introduction, and closing accept `{customer_name}`, `{store_name}`, and `{order_number}`. Product information, review URLs, and opt-out URLs are protected functional content.

## Upgrade from 1.0.4

Install version 2.0.0 through Plugin Manager. Existing reminder logs, customer opt-outs, and recognized settings are retained. After testing, remove remaining loose 1.0.4 files and do not rerun the archived SQL installer.

## Uninstall

Plugin Manager removes plugin registration and settings. Reminder history and customer opt-outs remain to prevent accidental repeat email and data loss.

## Support

Report reproducible bugs through the [PRO-Webs helpdesk](https://prowebsinc.zohodesk.com/portal/en/newticket). Installation, configuration, and customization are not included with free distribution.

See the [Zen Cart plugin listing](https://www.zen-cart.com/plugins/products-review-reminder-vb2148) and the [GitHub repository](https://github.com/mprough/Products-Review-Reminder-for-Zen-cart).

## License

GNU General Public License version 2. This software is provided without warranty.
