<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
header('Content-Type: application/json; charset=utf-8');

use Bitrix\Main\Loader;
use JetBrains\PhpStorm\NoReturn;

#[NoReturn]
function respond(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respond(
        ['success' => false, 'errors' => ['Метод не поддерживается']],
        405
    );
}

if (!Loader::includeModule('form')) {
    http_response_code(500);
    respond(
        ['success' => false, 'errors' => ['Модуль "Веб-формы" не установлен']],
        500
    );
}

$WEB_FORM_ID = (int)($_POST['WEB_FORM_ID'] ?? 0);
if ($WEB_FORM_ID <= 0) {
    respond(
        ['success' => false, 'errors' => ['Не указана форма']],
        500
    );
}

// Пример
//$formErrors = \CForm::Check($WEB_FORM_ID, [
//    'WEB_FORM_ID' => $WEB_FORM_ID,
//    'form_text_1' => 'Bvz vjt',
//    'form_text_2' => '1231233312',
//    'form_text_3' => 'Bvz vjt 1231233312',
//    'form_checkbox_SIMPLE_QUESTION_193' => [6],
//], false, 'Y', 'Y');

$formErrors = \CForm::Check($WEB_FORM_ID, $_REQUEST, false, 'Y', 'Y');
if (!empty($formErrors)) {
    respond(['success' => false, 'errors' => is_array($formErrors) ? $formErrors : [$formErrors]]);
}

$RESULT_ID = \CFormResult::Add($WEB_FORM_ID, $_REQUEST);

if (!$RESULT_ID) {
    global $strError;
    respond(['success' => false, 'errors' => [$strError ?: 'Не удалось сохранить заявку']]);
}

\CFormResult::SetEvent($RESULT_ID);

respond(['success' => true, 'resultId' => $RESULT_ID]);
