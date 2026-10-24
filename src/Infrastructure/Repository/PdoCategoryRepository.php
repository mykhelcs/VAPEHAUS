<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Repository;

use PDO;
use Vapehaus\Domain\Model\Category;
use Vapehaus\Domain\Repository\CategoryRepositoryInterface;

final class PdoCategoryRepository implements CategoryRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function save(Category $category): void
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM categories WHERE id = :id');
        $stmt->execute([':id' => $category->getId()]);
        $exists = (int) $stmt->fetchColumn() > 0;

        if ($exists) {
            $stmt = $this->pdo->prepare('UPDATE categories SET
                name = :name,
                slug = :slug,
                description = :desc,
                is_active = :active
                WHERE id = :id');
            $stmt->execute([
                ':id' => $category->getId(),
                ':name' => $category->getName(),
                ':slug' => $category->getSlug(),
                ':desc' => $category->getDescription(),
                ':active' => $category->isActive() ? 1 : 0,
            ]);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO categories (id, name, slug, description, is_active)
                VALUES (:id, :name, :slug, :desc, :active)');
            $stmt->execute([
                ':id' => $category->getId(),
                ':name' => $category->getName(),
                ':slug' => $category->getSlug(),
                ':desc' => $category->getDescription(),
                ':active' => $category->isActive() ? 1 : 0,
            ]);
        }
    }

    public function findById(string $id): ?Category
    {
        $stmt = $this->pdo->prepare('SELECT * FROM categories WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->mapRowToCategory($row) : null;
    }

    public function findBySlug(string $slug): ?Category
    {
        $stmt = $this->pdo->prepare('SELECT * FROM categories WHERE slug = :slug');
        $stmt->execute([':slug' => strtolower(trim($slug))]);
        $row = $stmt->fetch();
        return $row ? $this->mapRowToCategory($row) : null;
    }

    public function findAll(bool $onlyActive = true): array
    {
        $sql = 'SELECT * FROM categories';
        if ($onlyActive) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY name ASC';

        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll();

        return array_map(fn ($r) => $this->mapRowToCategory($r), $rows);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM categories WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRowToCategory(array $row): Category
    {
        return new Category(
            (string) $row['id'],
            (string) $row['name'],
            (string) $row['slug'],
            $row['description'] !== null ? (string) $row['description'] : null,
            (bool) $row['is_active']
        );
    }
}
