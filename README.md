# Products Review Reminder for Zen Cart

Products Review Reminder helps a shop owner find eligible completed orders and send customers a request for honest product feedback. Version 2.0.17 is maintained by Melanie Prough of [PRO-Webs, Inc.](https://pro-webs.net/).

Sending starts only when an administrator chooses a manual batch or starts a scheduled run. Scheduled runs require a server cron task.

## Features

- Lists orders after a configurable order status and waiting period.
- Excludes orders already contacted and customers who opted out.
- Sends selected orders with the manual button, or continues a scheduled run only after an administrator clicks Start sending.
- Can queue the currently eligible orders after an administrator clicks Start sending, then send up to 10 every 5 minutes until the queue is empty.
- Limits each displayed and manually submitted batch to a configurable number of reminders, 10 by default.
- Lets the shop owner edit this plugin's subject, greeting, introduction, product question, review link text, and closing in Zen Cart admin.
- Provides HTML and plain-text email content.
- Previews a reminder using any real order without sending or recording it.
- Sends a test copy to the store owner without contacting the customer or recording the order.
- Sends an optional live opt-out test to the store owner using a selected customer's signed link.
- Checks that customer's opt-out status and restores the customer after testing.
- Includes secure review links and a signed one-click opt-out link.
- Protects review and reminder-preference changes with the customer's Zen Cart session security token.
- Installs through Zen Cart Plugin Manager without core-file changes.

## Compatibility

- Zen Cart 2.0.x, 2.1.x, and 2.2.x
- PHP 8.0 through 8.5, within the PHP versions supported by the installed Zen Cart release

## Installation

1. Back up the shop files and database.
2. If using the production ZIP, copy its `zc_plugins` directory into the shop root. If using the GitHub source, copy the contents of its `files` directory into the shop root.
3. In Zen Cart admin, open **Modules > Plugin Manager**.
4. Install the newest **Products Review Reminder** version.
5. Open **Configuration > Products Review Reminder** to choose eligibility rules and edit the reminder wording.
6. Open **Tools > Products Review Reminder** to review eligible orders and send selected reminders.
7. To use Start sending, configure the server cron task described below. The manual button works without cron. If the worker heartbeat is missing, the admin page shows a notice explaining that scheduled sending is unavailable while manual sending remains available.

The email fields affect only review reminders sent by this plugin. They do not alter Zen Cart's other email templates.

## Email shortcodes

The Configuration page lists the available email shortcodes. The subject, greeting, introduction, and closing accept `{customer_name}`, `{store_name}`, and `{order_number}`. Copy them exactly, including the braces. Product information, review URLs, and opt-out URLs are protected functional content.

Press Enter in any editable body-text field to create a line break in both HTML and plain-text email. Do not enter `<br>`; HTML entered in these fields is intentionally displayed as text. Email subjects cannot contain line breaks, so returns in the subject are converted to spaces.

## Upgrade from 1.0.4

1. Back up the shop files and database.
2. Manually remove the old loose plugin files.
3. Do not run the legacy `uninstall.sql`.
4. Do not delete or empty the `addon_review_reminder_log` or `addon_review_reminder_optout` database tables.
5. Copy the new `files` directory contents into the shop root.
6. Install version 2.0.17 through **Modules > Plugin Manager**.
7. Confirm the settings under **Configuration > Products Review Reminder** before sending a reminder.

The installer reuses both existing database tables so previous send history and customer opt-outs remain effective. It recognizes the former configuration group, migrates supported waiting-period, date-window, and maximum-product settings, and replaces the legacy menu registrations. Existing MyISAM tables can remain MyISAM; new installations create the tables with InnoDB.

## Configuration

- **Trigger order status** starts the waiting period when an order reaches the selected status.
- **Waiting period** is the number of days the plugin waits after that status change.
- **How far back to look** is the additional eligibility window after the waiting period. A waiting period of 14 days and a lookback of 30 days includes orders that reached the trigger status 14 to 44 days ago.
- **Maximum products per email** limits the products shown in each reminder. Use `0` for no limit.
- **Maximum reminders per batch** limits the eligible orders displayed and processed at once. The default is 10.
- The six email wording fields control only this plugin's reminder content.

## Preview and test email

Open **Tools > Products Review Reminder**. In **Inspect an email**, enter any real order number. The order does not need to be inside the eligibility window.

- **Preview email** renders the message in admin without sending it.
- **Send test to store owner** sends the rendered message to `STORE_OWNER_EMAIL_ADDRESS`. In Zen Cart admin, this is **Configuration > Email Options > Email Address (sent FROM)**.

Preview and test actions do not contact the customer or add a reminder-log record. They use the order's customer name and products so the result matches a real reminder closely. The test email includes a safe opt-out test link that confirms the page works without changing any customer preferences.

For a complete opt-out test, choose an order for a customer record you can safely change and click **Send live opt-out test**. The message is sent only to the store owner, but its signed link represents the selected order's customer. Clicking the link adds that customer to the reminder opt-out table. Return to the same admin panel and click **Check customer status** to confirm the database change, then click **Restore customer opt-in** to remove only that customer's opt-out record.

The test panel includes an HTML or Plain text format switch. It changes both the on-page preview and the single test message sent to the store owner. A request-scoped email observer applies that choice only to this plugin's test message. The selected order customer's email preference applies only when a real reminder is sent to that customer.

## Sending batches

**Maximum reminders per batch** controls how many eligible orders appear and can be sent during one manual submission. The default is 10. After a successful manual batch, the next eligible orders appear. The scheduled run sends at most 10 per interval.

## Scheduled sending

Open **Tools > Products Review Reminder** to see and copy the cron schedule and command for the installed shop. The shop owner can give that command to the hosting helpdesk. Set a cron task to invoke the PHP CLI worker every minute. For the Homesteader Supply installation, the command is:

```sh
PRR_STORE_URL=https://www.homesteadersupply.com php /home/home4new/public_html/zc_plugins/ProductsReviewReminder/v2.0.17/catalog/reminder_worker.php
```

Use the server's PHP CLI path if `php` is unavailable in cron. The worker refuses HTTP requests. Cron must run once per minute, but the stored due time limits sending to one batch of up to 10 every 5 minutes. Starting a run queues only orders eligible at that moment. New orders require another Start after the current run finishes. Stop prevents further batches, although a batch already underway may finish. The page shows the latest worker heartbeat and sent, pending, skipped, and failed counts. Start is enabled only when the worker has checked in within three minutes. If the heartbeat stops, inspect the cron task and server logs. A send failure or interrupted send pauses the run for investigation, so an uncertain delivery is never retried automatically. The existing manual Send selected action remains available.

Upgrading from 2.0.15 migrates the queue table in place. An active run is paused for delivery review during upgrade. The queue tables hold run state. The existing reminder log and customer opt-out tables are preserved during upgrades. Uninstall removes the queue tables but preserves the reminder log and opt-outs. Remove the cron task before uninstalling the plugin.

## Uninstall

Plugin Manager removes plugin registration and settings. Reminder history and customer opt-outs remain to prevent accidental repeat email and data loss.

The retained tables are:

| Table | Purpose | Removed on uninstall |
| --- | --- | --- |
| `addon_review_reminder_log` | Records orders successfully sent a reminder | No |
| `addon_review_reminder_optout` | Records customers who declined future reminders | No |
| `addon_review_reminder_job` | Holds the current scheduled run | Yes |
| `addon_review_reminder_queue` | Holds queued orders and progress | Yes |

The actual table names include the shop's configured database prefix. Remove these tables manually only when their history is no longer needed.

## Troubleshooting

- If neither admin menu appears after installation, sign out of Zen Cart admin and sign back in. The plugin also repairs missing menu registrations during admin startup.
- If a test message is plain text, confirm the format selected in the inspection panel. Real reminders continue to honor the recipient customer's Zen Cart email preference.
- If an opt-out test link includes `test=1`, it is the safe routing test and cannot change a customer record. Use **Send live opt-out test** for the complete database test.
- If the eligible-order list is empty, confirm the trigger status, waiting period, lookback window, prior reminder history, and customer opt-out status.

## Documentation

- [Added features](docs/ADDED_FEATURES.md)
- [Work completed on September 4, 2026](docs/WORK_COMPLETED_2026-09-04.md)
- [2.0.17 release review](docs/RELEASE_2.0.17.md)
- [Change history](CHANGELOG.md)
- [Security policy](SECURITY.md)
- [License](LICENSE)

## Support

Report reproducible bugs through the [PRO-Webs helpdesk](https://prowebsinc.zohodesk.com/portal/en/newticket). Installation, configuration, and customization are not included with free distribution.

See the [Zen Cart plugin listing](https://www.zen-cart.com/plugins/products-review-reminder-vb2148) and the [GitHub repository](https://github.com/mprough/Products-Review-Reminder-for-Zen-cart).

## License

GNU General Public License version 2. See [LICENSE](LICENSE). This free software is provided without warranty. Installation, configuration, and customization are not included.

## Credits

Originally created by Will Vasconcelos. Modern Plugin Manager packaging, maintenance, compatibility work, testing tools, documentation, and current releases are by Melanie Prough, [PRO-Webs, Inc.](https://pro-webs.net/).
