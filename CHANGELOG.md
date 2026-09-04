# Change history

## 2.0.12

- Replaced the newer page-body bridge with a Zen Cart 2.x-compatible observer that supplies the encapsulated My Reviews and opt-out template paths after normal template resolution.
- Added a 12px email-safe gap between the displayed subject and the test notice or message body.
- Normalized HTML entities in all editable email fields for both the configuration editor and generated HTML or plain-text messages, including values expanded by earlier saves.
- Preserved normal Enter-key line breaks from every editable body field in HTML and plain-text messages while keeping entered HTML safely escaped.

## 2.0.11

- Added Plugin Manager page-body bridges so Zen Cart 2.x loads the encapsulated My Reviews and opt-out templates instead of looking for core template files.
- Registered the plugin email template directory with Zen Cart's email service.
- Retained the configured store email logo while changing the review-reminder wrapper to a responsive 600px white layout.

## 2.0.10

- Decoded Zen Cart's HTML-safe URL separators before placing links in plain-text email bodies.
- Escaped HTML email URLs exactly once so query parameters such as `test=1` and signed opt-out tokens reach the storefront correctly.
- Added an explicit white message background and email-safe full-width wrapper around the left-aligned review content.

## 2.0.9

- Added an Available email shortcodes row directly above the editable email settings.
- Listed each shortcode and the fields where it works.
- Updated the documentation to use the same shortcode terminology shown in admin.
- Left-aligned the review content within Zen Cart's branded email wrapper and added email-safe product/image widths.
- Made emailed opt-out tokens independent of an order's historical email address so valid links work without a customer login.

## 2.0.8

- Made the test format switch change both the on-page preview and the sent test message.
- Replaced the admin-copy suffix with a request-scoped Zen Cart email-format observer.
- Send exactly one test message in the selected HTML or Plain text format.
- Leave real customer reminder format selection unchanged.

## 2.0.7

- Send store-owner test messages through Zen Cart's admin-copy email path.
- Make test sends honor the configured admin HTML email format instead of looking for a customer preference belonging to the store-owner address.
- Keep real reminders tied to the actual recipient customer's saved email preference.
- Added an HTML or Plain text switch to the store-owner test-send controls.

## 2.0.6

- Added modern array-based storefront language files for the review and opt-out pages.
- Fixed the undefined `NAVBAR_TITLE` fatal error on supported Zen Cart versions that require array language files.
- Kept the legacy language files for compatibility with earlier supported Zen Cart versions.

## 2.0.5

- Replaced the omitted test opt-out text with a safe, clickable opt-out test URL.
- Added a test response on the real opt-out page that confirms routing without changing customer preferences.
- Prevented mail clients from combining the review URL with adjacent opt-out test text.

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
