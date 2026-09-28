# Products Review Reminder 2.0.21 release review

September 28, 2026

## Summary of today's plugin changes

### Sending and queue behavior

- Added an administrator-started queue. Start snapshots currently eligible orders, and the worker sends at most ten reminders per run when called every five minutes. Later eligible orders require another Start.
- When the queue is empty, the run becomes complete and stops. With an active heartbeat, the shop owner can click Start again to build a new list of orders eligible at that time. The prior run does not restart automatically.
- Added persistent sent, pending, skipped, and failed counts, a Stop control, and a heartbeat. Start is unavailable when the worker has not checked in within eleven minutes. Manual Send selected remains available without cron.
- Paused the queue on failed or uncertain delivery. A previously attempted email is never retried automatically; manual sends are blocked while a queue is running or paused.
- Rechecked current order status, date window, prior reminder history, customer opt-out, and remaining unreviewed products immediately before each queued send.
- Applied the maximum products setting after filtering previously reviewed products, so an already reviewed item cannot consume the limit.
- Set the next due time from the start of a batch. This lets the next five minute cron tick run the following batch even when the prior batch took time to send.

### Installation and administration

- Added the cron schedule and a shop-specific, copyable command to Tools > Products Review Reminder. The page explains the missing heartbeat and the manual sending option.
- Added `products_review_reminder_worker.php` to the shop root. This stable entry file loads the version installed through Plugin Manager. The stable entry point remains the same for later versions. An external cron service can use the private HTTPS URL, while cPanel can call the same URL with curl.
- Added an in-place migration for earlier queue tables. An active run using the original queue schema is paused on upgrade for delivery review. The installer preserves reminder history, customer opt-outs, and existing message settings.
- Added a manual batch result message showing messages accepted by the mail service and orders skipped or failed.

### Email and review page repairs

- Corrected catalog worker image and template paths and registered the plugin email template for scheduled mail.
- Escaped customer and product text in admin, email image attributes, and the storefront review page. Validated review request types, product ID, rating, and text length before writing a review.

## Verification status

- The ZIP passed its archive and package structure checks. GitHub Actions passed PHP lint and stable worker version selection on PHP 8.0 through 8.5.
- A shop installation must confirm the stable cron path, heartbeat, scheduled sends, and upgrade behavior before publishing to the Zen Cart plugin catalog.

## Install and acceptance review

1. Copy both the ZIP's `zc_plugins` directory and `products_review_reminder_worker.php` into the shop root. Install 2.0.21 through Modules > Plugin Manager.
2. Choose either the private HTTPS GET URL for an external cron service or the cPanel curl command displayed in Tools > Products Review Reminder. Set the schedule to every five minutes (`*/5 * * * *`) and keep the URL access key confidential.
3. After the next cron tick, confirm the heartbeat becomes active. Do not click Start until the command and heartbeat are correct.
4. In a staging shop, test one controlled queued batch and the following five minute batch. Confirm sent counts and reminder log entries, plus the email content and opt-out link.
5. Verify Stop, a paused failure, manual sending without cron, and upgrading from the already installed version while preserving settings and send history.

The draft pull request remains unmerged for review. No Zen Cart catalog submission has been made.
