<?php

declare(strict_types=1);

if (!defined('IS_ADMIN_FLAG') || IS_ADMIN_FLAG !== true) {
    die('Illegal Access');
}

if (!defined('FILENAME_ADDON_REVIEW_REMINDER')) {
    define('FILENAME_ADDON_REVIEW_REMINDER', 'addon_review_reminder');
}
