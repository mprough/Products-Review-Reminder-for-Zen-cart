# Products Review Reminder 2.0.16 release review

September 28, 2026

## Changes prepared today

- Added an administrator-started queue with a PHP CLI worker. Each run snapshots eligible orders and sends at most ten messages every five minutes until its queue is empty.
- Displayed the shop-specific cron schedule and command in the admin page for the owner or hosting helpdesk.
- Added persistent progress, Stop, and a worker heartbeat. Start requires a heartbeat within three minutes, so an absent cron task cannot silently strand a new run.
- Paused uncertain sends rather than retrying automatically. Manual sends are blocked during running and paused queues.
- Corrected the worker's catalog paths and email template registration, so scheduled messages use the same template as manual messages.
- Rechecked order status, waiting window, opt-out, send history, and unreviewed products before each queued send. Applied the product limit after filtering previously reviewed products.
- Validated review submissions and escaped customer and product text in the storefront review page and admin results.
- Added manual batch result counts and retained previous reminder history and opt-outs.
- Made 2.0.16 a separate Plugin Manager version. Its installer migrates existing 2.0.15 queue tables and pauses an active run for delivery review.

## Release checks

The package structure check and whitespace check pass locally. PHP is unavailable in this workspace; GitHub Actions checks PHP syntax on 8.0 through 8.5. Email delivery, cron scheduling, and upgrade behavior require a Zen Cart staging shop before publishing to the Zen Cart catalog.

## Installation review

Install through Modules > Plugin Manager, set a once per minute CLI cron task using the path in README, then verify that Tools > Products Review Reminder shows an active worker heartbeat. Start sending queues currently eligible orders. If the heartbeat stops, check cron and server logs. Inspect a paused run before any further send to avoid duplicates.
