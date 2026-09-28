#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
version_root="$root/files/zc_plugins/ProductsReviewReminder/v2.0.22"

test -f "$root/files/products_review_reminder_worker.php"
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

grep -Fq "Set up the optional cron task" "$version_root/admin/addon_review_reminder.php"
grep -Fq 'External cron service URL' "$version_root/admin/addon_review_reminder.php"
grep -Fq 'products_review_reminder_worker.php' "$version_root/admin/addon_review_reminder.php"
grep -Fq 'curl -fsS --max-time 120' "$version_root/admin/addon_review_reminder.php"
if grep -Fq "' php ' . escapeshellarg(\$worker_path)" "$version_root/admin/addon_review_reminder.php"; then exit 1; fi
grep -Fq 'Schedule: <code>*/5 * * * *</code>' "$version_root/admin/addon_review_reminder.php"
grep -Fq "heartbeat_age'] > 660" "$version_root/catalog/includes/functions/products_review_reminder_queue.php"
grep -Fq 'zen_db_input($batchStarted)' "$version_root/catalog/reminder_worker.php"
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
    fixture="$(mktemp -d)"
    trap 'if [[ -n "${server_pid:-}" ]]; then kill "$server_pid" 2>/dev/null || true; fi; rm -rf "$fixture"' EXIT
    mkdir -p "$fixture/includes" "$fixture/zc_plugins/ProductsReviewReminder/v2.0.22/catalog"
    cp "$root/files/products_review_reminder_worker.php" "$fixture/"
    printf "<?php define('PLUGIN_PRODUCTS_REVIEW_REMINDER_VERSION', '2.0.22');\n" > "$fixture/includes/application_top.php"
    printf '<?php echo "correct worker";\n' > "$fixture/zc_plugins/ProductsReviewReminder/v2.0.22/catalog/reminder_worker.php"
    test "$(php "$fixture/products_review_reminder_worker.php")" = 'correct worker'
    if command -v curl >/dev/null 2>&1; then
        cat > "$fixture/includes/application_top.php" <<'PHP'
<?php
define('PLUGIN_PRODUCTS_REVIEW_REMINDER_VERSION', '2.0.22');
define('TABLE_CONFIGURATION', 'configuration');
class TestDb {
    public function Execute($sql) {
        return (object)['EOF' => false, 'fields' => ['configuration_value' => str_repeat('a', 48)]];
    }
}
$db = new TestDb();
PHP
        printf '<?php file_put_contents(__DIR__ . "/ran", "yes");\n' > "$fixture/zc_plugins/ProductsReviewReminder/v2.0.22/catalog/reminder_worker.php"
        port="$(python3 -c 'import socket; s=socket.socket(); s.bind(("127.0.0.1",0)); print(s.getsockname()[1]); s.close()')"
        php -S "127.0.0.1:$port" -t "$fixture" > "$fixture/server.log" 2>&1 &
        server_pid=$!
        for _ in {1..20}; do
            if curl -s -o /dev/null "http://127.0.0.1:$port/"; then break; fi
            sleep 0.1
        done
        test "$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$port/products_review_reminder_worker.php?token=invalid")" = '404'
        test ! -e "$fixture/zc_plugins/ProductsReviewReminder/v2.0.22/catalog/ran"
        test "$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$port/products_review_reminder_worker.php?token=$(printf 'a%.0s' {1..48})")" = '204'
        test -f "$fixture/zc_plugins/ProductsReviewReminder/v2.0.22/catalog/ran"
        kill "$server_pid"
        wait "$server_pid" 2>/dev/null || true
        server_pid=
    fi
else
    echo 'PHP is unavailable; PHP lint was not run locally.' >&2
fi

git -C "$root" diff --check
