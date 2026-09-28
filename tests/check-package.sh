#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
version_root="$root/files/zc_plugins/ProductsReviewReminder/v2.0.17"

test -f "$version_root/manifest.php"
test -f "$version_root/Installer/ScriptedInstaller.php"
test -f "$version_root/admin/addon_review_reminder.php"
test -f "$version_root/admin/includes/classes/observers/auto.products_review_reminder_test_email.php"
test -f "$version_root/catalog/addon_my_reviews.php"
test -f "$version_root/catalog/reminder_worker.php"
test -f "$version_root/catalog/includes/classes/observers/auto.products_review_reminder_test_email.php"
test -f "$version_root/catalog/includes/functions/products_review_reminder_queue.php"
test -f "$version_root/catalog/includes/functions/products_review_reminder_mail.php"
test -f "$version_root/catalog/email/email_template_addon_review_reminder.html"
test -f "$version_root/catalog/includes/languages/english/lang.addon_my_reviews.php"
test -f "$version_root/catalog/includes/languages/english/lang.addon_reviews_reminder_optout.php"
test -f "$version_root/catalog/includes/classes/observers/auto.products_review_reminder_template_loader.php"

grep -Fq "Set up the cron task" "$version_root/admin/addon_review_reminder.php"
grep -Fq 'escapeshellarg($worker_path)' "$version_root/admin/addon_review_reminder.php"
grep -q "value=\"live_optout_test\"" "$version_root/admin/addon_review_reminder.php"
grep -q "value=\"check_optout\"" "$version_root/admin/addon_review_reminder.php"
grep -q "value=\"restore_optin\"" "$version_root/admin/addon_review_reminder.php"
grep -q "if (\$selected_order_id > 0)" "$version_root/admin/addon_review_reminder.php"
grep -Fq '"security_token":' "$version_root/catalog/includes/modules/pages/addon_my_reviews/jscript_main.php"
grep -Fq "hash_equals((string)\$_SESSION['securityToken'], \$securityToken)" "$version_root/catalog/addon_my_reviews.php"
grep -Fq "include \"includes/application_top.php\"" "$version_root/catalog/addon_my_reviews.php"

for guarded_file in \
    "$version_root/catalog/includes/extra_datafiles/addon_my_reviews.php" \
    "$version_root/catalog/includes/classes/observers/auto.products_review_reminder_template_loader.php" \
    "$version_root/catalog/includes/modules/pages/addon_reviews_reminder_optout/header_php.php" \
    "$version_root/catalog/includes/modules/pages/addon_my_reviews/jscript_main.php" \
    "$version_root/catalog/includes/modules/pages/addon_my_reviews/header_php.php"
do
    grep -Fq "defined('IS_ADMIN_FLAG')" "$guarded_file"
done

if command -v php >/dev/null 2>&1; then
    find "$root/files" -type f -name '*.php' -print0 | xargs -0 -n1 php -l
else
    echo 'PHP is unavailable; PHP lint was not run locally.' >&2
fi

git -C "$root" diff --check
