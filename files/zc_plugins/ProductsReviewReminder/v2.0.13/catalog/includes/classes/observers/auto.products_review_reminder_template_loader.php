<?php

declare(strict_types=1);

class zcObserverProductsReviewReminderTemplateLoader extends base
{
    public function __construct()
    {
        $this->attach($this, ['NOTIFY_MAIN_TEMPLATE_VARS_END']);
    }

    protected function update(&$class, $eventID, $templateDirectory, &$bodyCode): void
    {
        $templates = [
            'addon_my_reviews' => 'tpl_addon_my_reviews_default.php',
            'addon_reviews_reminder_optout' => 'tpl_addon_reviews_reminder_optout_default.php',
        ];
        $currentPage = $GLOBALS['current_page_base'] ?? '';
        if (!isset($templates[$currentPage])) {
            return;
        }

        $bodyCode = dirname(__DIR__, 4)
            . '/catalog/includes/templates/template_default/templates/'
            . $templates[$currentPage];
    }
}
