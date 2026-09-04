# Work completed on September 4, 2026

Products Review Reminder was converted from a legacy loose-file add-on into a self-contained Zen Cart Plugin Manager release and advanced through version 2.0.13. The work corrected installation, admin menus, email generation, storefront review links, customer opt-outs, compatibility warnings, test behavior, layout problems, documentation, and PHP compatibility.

## Upgrade and installation

- Converted the plugin to an encapsulated `zc_plugins/ProductsReviewReminder` package.
- Added install, upgrade, and uninstall handling through Plugin Manager.
- Added resilient Configuration and Tools menu registration.
- Preserved legacy configuration values where practical.
- Preserved the existing reminder log and customer opt-out tables.
- Documented removal of old loose files and retention of database history.
- Avoided the Windows path-length problem found in an earlier package.

## Reminder administration

- Kept reminder delivery administrator-driven.
- Added configurable trigger status, waiting period, lookback window, product limit, and batch limit.
- Set the default batch limit to 10.
- Reworded the eligibility-window setting so its behavior is understandable.
- Converted every editable email field to a multiline editor.
- Added an admin list of supported shortcodes.
- Removed the empty packing-list panel and orphaned remove button when no order is selected.

## Email content and testing

- Added editable wording dedicated to this plugin.
- Added correct HTML and plain-text message bodies.
- Added real-order preview without sending or recording a reminder.
- Added store-owner test sending and an HTML or plain-text format selector.
- Corrected Zen Cart's successful-send result handling.
- Corrected reminder logging so only successful sends are recorded.
- Added email-safe spacing, a white background, responsive widths, and left-aligned content.
- Preserved the configured Zen Cart email logo and wrapper.
- Corrected entity handling so ampersands are not repeatedly expanded.
- Preserved Enter-key line breaks in both email formats while safely escaping entered HTML.

## Review and opt-out links

- Corrected duplicated `main_page` parameters and encoded ampersands in generated links.
- Added signed one-click opt-out links that do not require customer login.
- Added a safe `test=1` routing check that never changes customer data.
- Added a live customer-bound opt-out test sent only to the store owner.
- Added an admin status check to verify the opt-out table was updated.
- Added a restore action that removes only the selected test customer's opt-out record.
- Corrected storefront language loading and template resolution for supported Zen Cart releases.

## Verification

- Added repeatable package checks.
- Added GitHub Actions PHP lint coverage for PHP 8.0 through 8.5.
- Verified each production ZIP for archive integrity and an exact match with the published repository copy.

See the [complete change history](../CHANGELOG.md) for the version-by-version record.
