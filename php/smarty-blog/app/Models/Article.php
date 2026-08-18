<?php
namespace App\Models;

use PDO;
use App\Bootstrap;

class Article
{
    public function __construct(private PDO $db)
    {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM articles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $article = $stmt->fetch();

        return $article ?: null;
    }

    /**
     * Категории к которым принадлежит статья
     * @param int $articleId
     * @return array<array>
     */
    public function getCategoriesForArticle(int $articleId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.* FROM categories c
             INNER JOIN article_category ac ON ac.category_id = c.id
             WHERE ac.article_id = :aid
             ORDER BY c.name'
        );
        $stmt->execute(['aid' => $articleId]);

        return $stmt->fetchAll();
    }

    /**
     * Прибавляет кол-во просмотров
     * @param int $articleId
     * @return void
     */
    public function incrementViews(int $articleId): void
    {
        $stmt = $this->db->prepare('UPDATE articles SET views = views + 1 WHERE id = :id');
        $stmt->execute(['id' => $articleId]);
    }

    /**
     * Выбирает похожие статьи: с большим кол-во просмотров в указанных категориях
     * @param int $articleId
     * @param array $categoryIds
     * @return array
     */
    public function getSimilar(int $articleId, array $categoryIds): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        $sql = "SELECT DISTINCT a.*
                FROM articles a
                INNER JOIN article_category ac ON ac.article_id = a.id
                WHERE ac.category_id IN (:cid)
                  AND a.id != :aid
                ORDER BY a.published_at DESC
                LIMIT :lim";

        $stmt = $this->db->prepare($sql);

        $app = new Bootstrap();
        $stmt->execute([
            'cid' => implode(',', array_unique($categoryIds)),
            'aid' => $articleId,
            'lim' => $app->getConfig()['site']['similar_count']
        ]);

        return $stmt->fetchAll();
    }

    public function countTotal(int $categoryId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM article_category WHERE category_id = :cid'
        );
        $stmt->execute(['cid' => $categoryId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Статьи в категории постранично
     * @param int $categoryId
     * @param string $sort
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getByCategoryPaginated(
        int $categoryId,
        string $sort,
        int $page,
        int $perPage
    ): array {
        $orderBy = match ($sort) {
            'views' => 'a.views DESC',
            default => 'a.published_at DESC',
        };

        $offset = max(0, ($page - 1) * $perPage);

        $stmt = $this->db->prepare(
            "SELECT a.*
             FROM articles a
             INNER JOIN article_category ac ON ac.article_id = a.id
             WHERE ac.category_id = :cid
             ORDER BY {$orderBy}
             LIMIT :lim OFFSET :off"
        );
        $stmt->execute([
            ':cid' => $categoryId,
            ':lim' => $perPage,
            ':off' => $offset
        ]);

        return $stmt->fetchAll();
    }
}
