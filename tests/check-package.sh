#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
version_root="$root/files/zc_plugins/ProductsReviewReminder/v2.0.13"

test -f "$version_root/manifest.php"
test -f "$version_root/Installer/ScriptedInstaller.php"
test -f "$version_root/admin/addon_review_reminder.php"
test -f "$version_root/admin/includes/classes/observers/auto.products_review_reminder_test_email.php"
test -f "$version_root/catalog/addon_my_reviews.php"
test -f "$version_root/catalog/email/email_template_addon_review_reminder.html"
test -f "$version_root/catalog/includes/languages/english/lang.addon_my_reviews.php"
test -f "$version_root/catalog/includes/languages/english/lang.addon_reviews_reminder_optout.php"
test -f "$version_root/catalog/includes/classes/observers/auto.products_review_reminder_template_loader.php"

grep -q "value=\"live_optout_test\"" "$version_root/admin/addon_review_reminder.php"
grep -q "value=\"check_optout\"" "$version_root/admin/addon_review_reminder.php"
grep -q "value=\"restore_optin\"" "$version_root/admin/addon_review_reminder.php"
grep -q "if (\$selected_order_id > 0)" "$version_root/admin/addon_review_reminder.php"

if command -v php >/dev/null 2>&1; then
    find "$root/files" -type f -name '*.php' -print0 | xargs -0 -n1 php -l
else
    echo 'PHP is unavailable; PHP lint was not run locally.' >&2
fi

git -C "$root" diff --check
