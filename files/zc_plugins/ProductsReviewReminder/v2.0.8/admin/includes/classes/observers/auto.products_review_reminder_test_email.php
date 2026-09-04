<?php

declare(strict_types=1);

class zcObserverProductsReviewReminderTestEmail extends base
{
    public function __construct()
    {
        $this->attach($this, ['NOTIFY_EMAIL_DETERMINING_EMAIL_FORMAT']);
    }

    protected function update(&$class, $eventID, $toAddress, &$emailFormat, &$module): void
    {
        if (
            $module === 'addon_review_reminder'
            && isset($GLOBALS['products_review_reminder_test_format'])
        ) {
            $emailFormat = $GLOBALS['products_review_reminder_test_format'] === 'text' ? 'TEXT' : 'HTML';
        }
    }
}
