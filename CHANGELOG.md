# Change history

## 2.0.4

- Corrected Zen Cart email-result handling so successful test sends display success.
- Corrected reminder logging so a successfully sent customer email is recorded and a failed send is not.
- Added a configurable maximum reminders per batch, defaulting to 10, for high-volume shops.
- Corrected generated review and opt-out URLs so Zen Cart does not create a duplicated `main_page` parameter.
- Guarded the customer review page when no customer session exists.

## 2.0.3

- Added an admin preview that renders a reminder from any real order without sending it.
- Added a test send to the store owner address without contacting the customer or recording the order as contacted.
- Removed the working customer opt-out link from previews and test messages.
- Added clear success and error feedback for email inspection actions.

## 2.0.2

- Renamed the confusing Eligible date window setting to How far back to look.
- Rewrote its description to explain exactly how it combines with the waiting period.
- Changed every editable email field from a single-line input to a multiline textarea.
- Replaced the deprecated hand-built admin page head with Zen Cart's current admin_html_head.php loader.

## 2.0.1

- Added Zen Cart 2.2 language-array files for the Tools page and both admin menu labels.
- Added the proven admin extra-datafile filename fallback.
- Hardened runtime self-repair for missing Configuration and Tools menu registrations.
- Removes obsolete menu registrations during upgrade before confirming the current pages.

## 2.0.0

- Converted the loose-file package to a self-contained Plugin Manager release.
- Added editable review-reminder wording in the plugin configuration.
- Added signed one-click opt-out links that do not require customer login.
- Simplified the administrator workflow to review and send selected reminders.
- Added runtime repair for missing Configuration and Tools menu registrations.
- Preserved existing send history and opt-out records during migration.
- Added current documentation and PHP compatibility checks.

## 1.0.4

- Updated the legacy package for PHP 8 and Zen Cart 2.0.1.
- Legacy documentation and SQL are retained under `docs/archive` for reference only.
