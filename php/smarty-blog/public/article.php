<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Bootstrap;
use App\Models\Article;

$app = new Bootstrap();
$smarty = $app->getSmarty();
$id = trim((int)($_GET['id'] ?? ''));

if (empty($id)) {
    $app->render404();
}

$articleModel = new Article($app->getPdo());
$article = $articleModel->findById($id);

if (!$article) {
    $app->render404();
}

// Прибавляет кол-во просмотров
$articleModel->incrementViews((int)$article['id']);
$article['views']++;

$categories = $articleModel->getCategoriesForArticle((int)$article['id']);
$categoryIds = array_column($categories, 'id');
$similar = $articleModel->getSimilar((int)$article['id'], $categoryIds);

$smarty->assign('article', $article);
$smarty->assign('categories', $categories);
$smarty->assign('similar', $similar);

$smarty->display('article.tpl');
