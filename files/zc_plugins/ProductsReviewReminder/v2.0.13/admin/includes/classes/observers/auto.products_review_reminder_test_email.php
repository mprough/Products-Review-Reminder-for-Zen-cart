<?php

declare(strict_types=1);

class zcObserverProductsReviewReminderTestEmail extends base
{
    public function __construct()
    {
        $this->attach($this, [
            'NOTIFY_EMAIL_DETERMINING_EMAIL_FORMAT',
            'NOTIFY_EMAIL_REGISTER_ADDITIONAL_TEMPLATE_DIRS',
        ]);
    }

    public function notify_email_register_additional_template_dirs(
        &$class,
        $eventID,
        $params,
        &$extraEmailTemplatePaths
    ): void {
        if (($params['module'] ?? '') !== 'addon_review_reminder') {
            return;
        }

        $extraEmailTemplatePaths[] = dirname(__DIR__, 4) . '/catalog/email';
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
