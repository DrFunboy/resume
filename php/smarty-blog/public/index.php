<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Category;
use App\Bootstrap;

$app = new Bootstrap();

$categoryList = new Category($app->getPdo());
$blocks = $categoryList->getCategoriesWithLatestArticles($app->getConfig()['site']['home_per_cat']);

$smarty = $app->getSmarty();
$smarty->assign('blocks', $blocks);
$smarty->display('home.tpl');