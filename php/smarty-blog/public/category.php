<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Bootstrap;
use App\Models\Article;
use App\Models\Category;


$app = new Bootstrap();
$smarty = $app->getSmarty();
$id = trim((int)($_GET['id'] ?? ''));

if (empty($id)) {
    $app->render404();
}

$categoryModel = new Category($app->getPdo());
$category = $categoryModel->findById($id);

if (!$category) {
    $app->render404();
}

$allowedSorts = ['date', 'views'];
$sort = (string) ($_GET['sort'] ?? 'date');
if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'date';
}

$perPage = $app->getConfig()['site']['per_page'];
$page = max(1, (int) ($_GET['page'] ?? 1));

$articleModel = new Article($app->getPdo());
$total = $articleModel->countTotal((int) $category['id']);
$totalPages = max(1, (int) ceil($total / $perPage));
$page = min($page, $totalPages);

$articles = $articleModel->getByCategoryPaginated((int) $category['id'], $sort, $page, $perPage);

$smarty->assign([
    'category' => $category,
    'articles' => $articles,
    'sort' => $sort,
    'page' => $page,
    'total_pages' => $totalPages,
    'total_articles' => $total,
]);

$smarty->display('category.tpl');
