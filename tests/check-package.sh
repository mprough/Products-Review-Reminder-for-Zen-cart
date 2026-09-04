#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
version_root="$root/files/zc_plugins/ProductsReviewReminder/v2.0.3"

test -f "$version_root/manifest.php"
test -f "$version_root/Installer/ScriptedInstaller.php"
test -f "$version_root/admin/addon_review_reminder.php"
test -f "$version_root/catalog/addon_my_reviews.php"
test -f "$version_root/catalog/email/email_template_addon_review_reminder.html"

if command -v php >/dev/null 2>&1; then
    find "$root/files" -type f -name '*.php' -print0 | xargs -0 -n1 php -l
else
    echo 'PHP is unavailable; PHP lint was not run locally.' >&2
fi

git -C "$root" diff --check
