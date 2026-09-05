<?php
/** @var CMain $APPLICATION */

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');
$APPLICATION->IncludeComponent(
    'bitrix:form.result.new',
    'callback_popup',
    Array(
        'WEB_FORM_ID' => 1,
        'IGNORE_CUSTOM_TEMPLATE' => 'N',
        'AJAX_MODE' => 'N',
        'CACHE_TYPE' => 'N',
    )
);
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php');