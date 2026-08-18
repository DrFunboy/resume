<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;

$pdo = Database::getConnection();

$fresh = in_array('--fresh', $argv, true);

if ($fresh) {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('TRUNCATE TABLE article_category');
    $pdo->exec('TRUNCATE TABLE articles');
    $pdo->exec('TRUNCATE TABLE categories');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "Existing data truncated.\n";
}

$categoriesSeed = [
    [
        'name' => 'Fashion',
        'description' => 'Тренды, образы и советы стилистов из мира моды.',
    ],
    [
        'name' => 'Beauty',
        'description' => 'Уход за кожей, макияж и всё о красоте.',
    ],
    [
        'name' => 'Coffee',
        'description' => 'Рецепты, обжарка и культура кофе со всего мира.',
    ],
    [
        'name' => 'Lifestyle',
        'description' => 'Заметки о путешествиях, привычках и повседневной жизни.',
    ],
];

$imagesByCategory = [
    'Fashion' => '/images/Fachion.jpg',
    'Beauty' => '/images/Beauty.jpg',
    'Coffee' => '/images/Coffee.jpg',
    'Lifestyle' => '/images/Lifestyle.jpg',
];

$titleTemplates = [
    'Fashion' => [
        'Как собрать капсульный гардероб на сезон',
        'Главные тренды подиумов этой осенью',
        'Аксессуары, которые оживят любой образ',
        'Гид по деним-трендам',
        'Что носить в офис: 5 базовых образов',
        'История одной культовой сумки',
        'Секреты стиля от уличных модников',
        'Как выбрать пальто на все случаи жизни',
    ],
    'Beauty' => [
        'Утренний уход за кожей за 5 минут',
        'Макияж, который держится весь день',
        'Тренды в окрашивании волос этого года',
        'Как выбрать тональный крем под тон кожи',
        'SPF зимой: миф или необходимость',
        'Уход за руками: базовый ритуал',
        'Ароматы, которые запоминаются',
        'Секреты стойкого маникюра дома',
    ],
    'Coffee' => [
        'Как правильно молоть зерна для эспрессо',
        'Разница между арабикой и робустой',
        'Домашний рецепт капучино без кофемашины',
        'Гид по альтернативным способам заваривания',
        'Как выбрать кофемолку для дома',
        'Что такое спешелти кофе',
        'История появления флэт уайта',
        'Как хранить кофейные зерна правильно',
    ],
    'Lifestyle' => [
        'Утренние привычки продуктивных людей',
        'Как организовать рабочее пространство дома',
        'Мини-путеводитель по выходным без плана',
        'Простые способы снизить уровень стресса',
        'Как вести дневник благодарности',
        'Осознанное потребление: с чего начать',
        'Идеи для семейного вечера без гаджетов',
        'Как планировать неделю без выгорания',
    ],
];

$lead = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Quo sunt tempora audio '
    . 'laudantium sed optio, explicabo dolorem impedit facilis fugit recusandae illo, aliquid.';

$bodyParagraphs = [
    'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt '
    . 'ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation.',
    'Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla '
    . 'pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia.',
    'Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque '
    . 'laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi.',
];

$categoryIds = [];

$insertCategory = $pdo->prepare(
    'INSERT INTO categories (name, description) VALUES (:name, :description)
     ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)'
);

foreach ($categoriesSeed as $cat) {
    $insertCategory->execute([
        'name' => $cat['name'],
        'description' => $cat['description'],
    ]);

    $insertedId = $pdo->lastInsertId();
    $categoryIds[$cat['name']] = (int)$insertedId;
}

echo 'Categories ready: ' . implode(', ', array_keys($categoryIds)) . "\n";

$insertArticle = $pdo->prepare(
    'INSERT INTO articles (title, description, content, image, views, published_at)
     VALUES (:title, :description, :content, :image, :views, :published_at)
     ON DUPLICATE KEY UPDATE title = VALUES(title)'
);
$linkArticle = $pdo->prepare(
    'INSERT IGNORE INTO article_category (article_id, category_id) VALUES (:aid, :cid)'
);

$now = new DateTimeImmutable();
$totalArticles = 0;
$imgCounter = 1;

foreach ($titleTemplates as $categoryName => $titles) {
    foreach ($titles as $i => $title) {
        $daysAgo = random_int(1, 240);
        $publishedAt = $now->modify("-{$daysAgo} days")->format('Y-m-d H:i:s');
        $views = random_int(5, 4500);
        $content = implode("\n\n", $bodyParagraphs);
        $image = sprintf($imagesByCategory[$categoryName], $imgCounter++);

        $insertArticle->execute([
            'title' => $title,
            'description' => $lead,
            'content' => $content,
            'image' => $image,
            'views' => $views,
            'published_at' => $publishedAt,
        ]);
        $insertedId = $pdo->lastInsertId();
        $articleId = (int)$insertedId;

        $linkArticle->execute([
            'aid' => $articleId,
            'cid' => $categoryIds[$categoryName],
        ]);

        // Каждая 4ая статья будет относиться к двум категориям
        if ($i % 4 === 0 && $categoryName === 'Lifestyle') {
            $linkArticle->execute([
                'aid' => $articleId,
                'cid' => $categoryIds['Coffee'],
            ]);
        }
        if ($i % 4 === 0 && $categoryName === 'Fashion') {
            $linkArticle->execute([
                'aid' => $articleId,
                'cid' => $categoryIds['Beauty'],
            ]);
        }

        $totalArticles++;
    }
}

echo "Seeded {$totalArticles} articles across " . count($categoryIds) . " categories.\n";
echo "Done.\n";
