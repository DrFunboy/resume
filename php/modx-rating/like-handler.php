<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

/**
 * Упрощённый response
 * @param array $payload
 * @param int $httpCode
 * @return void
 */
function like_handler_respond(array $payload, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($modx) || !is_object($modx)) {
    like_handler_respond([
        'success' => false,
        'error' => 'ModX недоступен',
    ], 500);
}

$rawId = $_REQUEST['id'] ?? null;
$rawAction = $_REQUEST['action'] ?? null;

// Проверка ID
if (!is_scalar($rawId) || !ctype_digit((string)$rawId) || (int)$rawId <= 0) {
    like_handler_respond([
        'success' => false,
        'error' => 'Неверный ID',
    ], 400);
}
$resourceId = (int)$rawId;

$allowedActions = ['like', 'unlike'];
if (!is_string($rawAction) || !in_array($rawAction, $allowedActions, true)) {
    like_handler_respond([
        'success' => false,
        'error' => 'Неверное действие',
    ], 400);
}
$requestedAction = $rawAction;

$resource = $modx->getObject('modResource', $resourceId);

if (!$resource) {
    like_handler_respond([
        'success' => false,
        'error' => 'Неверный ID',
    ], 404);
}

// Защита от накрутки
if (!isset($_SESSION['liked_items']) || !is_array($_SESSION['liked_items'])) {
    $_SESSION['liked_items'] = [];
}

// Лайк и анлайк
$alreadyLiked = in_array($resourceId, $_SESSION['liked_items'], true);

if ($requestedAction === 'like' && $alreadyLiked) {
    like_handler_respond([
        'success' => true,
        'count' => (int)$resource->getTVValue('likes_count'),
        'liked' => true,
        'note' => 'Не получилось поставить оценку',
    ]);
}

if ($requestedAction === 'unlike' && !$alreadyLiked) {
    like_handler_respond([
        'success' => true,
        'count' => (int)$resource->getTVValue('likes_count'),
        'liked' => false,
        'note' => 'Не получилось убрать оценку',
    ]);
}

// Обновление счётчика
$currentCount = (int)$resource->getTVValue('likes_count');

if ($requestedAction === 'like') {
    $newCount = $currentCount + 1;
    $_SESSION['liked_items'][] = $resourceId;
    $liked = true;
} else {
    $newCount = max(0, $currentCount - 1);
    $_SESSION['liked_items'] = array_values(array_diff($_SESSION['liked_items'], [$resourceId]));
    $liked = false;
}

$saved = $resource->setTVValue('likes_count', $newCount);

if (!$saved || !$resource->save()) {
    like_handler_respond([
        'success' => false,
        'error' => 'Не удалось изменить счётчик',
    ], 500);
}

like_handler_respond([
    'success' => true,
    'count' => $newCount,
    'liked' => $liked,
]);