<?php

declare(strict_types=1);

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

return [
    'pluginVersion' => 'v2.0.22',
    'pluginName' => 'Products Review Reminder',
    'pluginDescription' => 'Send customizable product review requests manually. Optional scheduling uses a separate connector and cron task.',
    'pluginAuthor' => 'PRO-Webs, Inc.',
    'pluginId' => 2148,
    'zcVersions' => ['v200', 'v210', 'v220'],
    'changelog' => 'https://github.com/mprough/Products-Review-Reminder-for-Zen-cart/blob/main/CHANGELOG.md',
    'github_repo' => 'https://github.com/mprough/Products-Review-Reminder-for-Zen-cart',
    'pluginGroups' => [],
];
