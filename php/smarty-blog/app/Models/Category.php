<?php
namespace App\Models;
use PDO;

class Category
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Возвращает перечень категорий со статьями
     * @return array<int, array{category: array, articles: array}>
     */
    public function getCategoriesWithLatestArticles(int $limitPerCategory = 3): array
    {
        $categories = $this->db->query(
            'SELECT DISTINCT c.*
             FROM categories c
             INNER JOIN article_category ac ON ac.category_id = c.id
             ORDER BY c.name ASC'
        )->fetchAll();

        $result = [];

        foreach ($categories as $category) {
            $stmt = $this->db->prepare(
                'SELECT a.*
                 FROM articles a
                 INNER JOIN article_category ac ON ac.article_id = a.id
                 WHERE ac.category_id = :cid
                 ORDER BY a.published_at DESC
                 LIMIT :lim'
            );
            $stmt->execute([
                ':cid' => $category['id'],
                ':lim' => $limitPerCategory
            ]);
            $articles = $stmt->fetchAll();

            if (count($articles) > 0) {
                $result[] = [
                    'category' => $category,
                    'articles' => $articles,
                ];
            }
        }

        return $result;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM categories WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $category = $stmt->fetch();

        return $category ?: null;
    }

    public function countArticles(int $categoryId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM article_category WHERE category_id = :cid'
        );
        $stmt->execute(['cid' => $categoryId]);

        return (int) $stmt->fetchColumn();
    }
}
