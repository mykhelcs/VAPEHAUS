<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Repository;

use PDO;
use Vapehaus\Domain\Model\Product;
use Vapehaus\Domain\Model\ProductVariant;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;
use Vapehaus\Domain\ValueObject\Money;
use Vapehaus\Domain\ValueObject\NicotineStrength;
use Vapehaus\Domain\ValueObject\Sku;

final class PdoProductRepository implements ProductRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function save(Product $product): void
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM products WHERE id = :id');
        $stmt->execute([':id' => $product->getId()]);
        $exists = (int) $stmt->fetchColumn() > 0;

        if ($exists) {
            $stmt = $this->pdo->prepare('UPDATE products SET
                category_id = :cid,
                name = :name,
                slug = :slug,
                brand = :brand,
                description = :desc,
                is_active = :active
                WHERE id = :id');
            $stmt->execute([
                ':id' => $product->getId(),
                ':cid' => $product->getCategoryId(),
                ':name' => $product->getName(),
                ':slug' => $product->getSlug(),
                ':brand' => $product->getBrand(),
                ':desc' => $product->getDescription(),
                ':active' => $product->isActive() ? 1 : 0,
            ]);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO products (id, category_id, name, slug, brand, description, is_active, created_at)
                VALUES (:id, :cid, :name, :slug, :brand, :desc, :active, :created)');
            $stmt->execute([
                ':id' => $product->getId(),
                ':cid' => $product->getCategoryId(),
                ':name' => $product->getName(),
                ':slug' => $product->getSlug(),
                ':brand' => $product->getBrand(),
                ':desc' => $product->getDescription(),
                ':active' => $product->isActive() ? 1 : 0,
                ':created' => $product->getCreatedAt(),
            ]);
        }
    }

    public function findById(string $id): ?Product
    {
        $stmt = $this->pdo->prepare('SELECT * FROM products WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $variants = $this->loadVariantsForProduct($id);
        return $this->mapRowToProduct($row, $variants);
    }

    public function findBySlug(string $slug): ?Product
    {
        $stmt = $this->pdo->prepare('SELECT * FROM products WHERE slug = :slug');
        $stmt->execute([':slug' => strtolower(trim($slug))]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $variants = $this->loadVariantsForProduct($row['id']);
        return $this->mapRowToProduct($row, $variants);
    }

    public function findVariantById(string $variantId): ?ProductVariant
    {
        $stmt = $this->pdo->prepare('SELECT * FROM product_variants WHERE id = :id');
        $stmt->execute([':id' => $variantId]);
        $row = $stmt->fetch();
        return $row ? $this->mapRowToVariant($row) : null;
    }

    public function findVariantBySku(string $sku): ?ProductVariant
    {
        $stmt = $this->pdo->prepare('SELECT * FROM product_variants WHERE sku = :sku');
        $stmt->execute([':sku' => strtoupper(trim($sku))]);
        $row = $stmt->fetch();
        return $row ? $this->mapRowToVariant($row) : null;
    }

    public function saveVariant(ProductVariant $variant): void
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM product_variants WHERE id = :id');
        $stmt->execute([':id' => $variant->getId()]);
        $exists = (int) $stmt->fetchColumn() > 0;

        if ($exists) {
            $stmt = $this->pdo->prepare('UPDATE product_variants SET
                product_id = :pid,
                sku = :sku,
                flavor = :flavor,
                nicotine_strength = :nic,
                price = :price,
                stock_quantity = :stock,
                is_active = :active
                WHERE id = :id');
            $stmt->execute([
                ':id' => $variant->getId(),
                ':pid' => $variant->getProductId(),
                ':sku' => $variant->getSku()->getValue(),
                ':flavor' => $variant->getFlavor(),
                ':nic' => $variant->getNicotineStrength()->getValue(),
                ':price' => $variant->getPrice()->toFloat(),
                ':stock' => $variant->getStockQuantity(),
                ':active' => $variant->isActive() ? 1 : 0,
            ]);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO product_variants (id, product_id, sku, flavor, nicotine_strength, price, stock_quantity, is_active)
                VALUES (:id, :pid, :sku, :flavor, :nic, :price, :stock, :active)');
            $stmt->execute([
                ':id' => $variant->getId(),
                ':pid' => $variant->getProductId(),
                ':sku' => $variant->getSku()->getValue(),
                ':flavor' => $variant->getFlavor(),
                ':nic' => $variant->getNicotineStrength()->getValue(),
                ':price' => $variant->getPrice()->toFloat(),
                ':stock' => $variant->getStockQuantity(),
                ':active' => $variant->isActive() ? 1 : 0,
            ]);
        }
    }

    public function search(array $criteria = [], int $limit = 20, int $offset = 0): array
    {
        $sql = 'SELECT DISTINCT p.* FROM products p';
        $joins = [];
        $where = ['p.is_active = 1'];
        $params = [];

        if (!empty($criteria['flavor']) || !empty($criteria['nicotine']) || !empty($criteria['in_stock'])) {
            $joins[] = 'JOIN product_variants v ON v.product_id = p.id';
        }

        if (!empty($criteria['category_id'])) {
            $where[] = 'p.category_id = :cid';
            $params[':cid'] = $criteria['category_id'];
        }

        if (!empty($criteria['brand'])) {
            $where[] = 'p.brand = :brand';
            $params[':brand'] = $criteria['brand'];
        }

        if (!empty($criteria['query'])) {
            $where[] = '(p.name LIKE :q OR p.brand LIKE :q OR p.description LIKE :q)';
            $params[':q'] = '%' . $criteria['query'] . '%';
        }

        if (!empty($criteria['flavor'])) {
            $where[] = 'v.flavor LIKE :flavor';
            $params[':flavor'] = '%' . $criteria['flavor'] . '%';
        }

        if (!empty($criteria['nicotine'])) {
            $where[] = 'v.nicotine_strength = :nic';
            $params[':nic'] = strtolower(trim($criteria['nicotine']));
        }

        if (isset($criteria['in_stock']) && $criteria['in_stock']) {
            $where[] = 'v.stock_quantity > 0';
        }

        if (!empty($joins)) {
            $sql .= ' ' . implode(' ', array_unique($joins));
        }

        $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY p.created_at DESC LIMIT :lim OFFSET :off';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $products = [];
        foreach ($rows as $row) {
            $variants = $this->loadVariantsForProduct($row['id']);
            $products[] = $this->mapRowToProduct($row, $variants);
        }

        return $products;
    }

    public function countSearch(array $criteria = []): int
    {
        $sql = 'SELECT COUNT(DISTINCT p.id) FROM products p';
        $joins = [];
        $where = ['p.is_active = 1'];
        $params = [];

        if (!empty($criteria['flavor']) || !empty($criteria['nicotine']) || !empty($criteria['in_stock'])) {
            $joins[] = 'JOIN product_variants v ON v.product_id = p.id';
        }

        if (!empty($criteria['category_id'])) {
            $where[] = 'p.category_id = :cid';
            $params[':cid'] = $criteria['category_id'];
        }

        if (!empty($criteria['brand'])) {
            $where[] = 'p.brand = :brand';
            $params[':brand'] = $criteria['brand'];
        }

        if (!empty($criteria['query'])) {
            $where[] = '(p.name LIKE :q OR p.brand LIKE :q OR p.description LIKE :q)';
            $params[':q'] = '%' . $criteria['query'] . '%';
        }

        if (!empty($criteria['flavor'])) {
            $where[] = 'v.flavor LIKE :flavor';
            $params[':flavor'] = '%' . $criteria['flavor'] . '%';
        }

        if (!empty($criteria['nicotine'])) {
            $where[] = 'v.nicotine_strength = :nic';
            $params[':nic'] = strtolower(trim($criteria['nicotine']));
        }

        if (isset($criteria['in_stock']) && $criteria['in_stock']) {
            $where[] = 'v.stock_quantity > 0';
        }

        if (!empty($joins)) {
            $sql .= ' ' . implode(' ', array_unique($joins));
        }

        $sql .= ' WHERE ' . implode(' AND ', $where);

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM products WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * @return array<int, ProductVariant>
     */
    private function loadVariantsForProduct(string $productId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM product_variants WHERE product_id = :pid AND is_active = 1 ORDER BY price ASC');
        $stmt->execute([':pid' => $productId]);
        $rows = $stmt->fetchAll();
        return array_map(fn ($r) => $this->mapRowToVariant($r), $rows);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, ProductVariant> $variants
     */
    private function mapRowToProduct(array $row, array $variants = []): Product
    {
        return new Product(
            (string) $row['id'],
            (string) $row['category_id'],
            (string) $row['name'],
            (string) $row['slug'],
            (string) $row['brand'],
            $row['description'] !== null ? (string) $row['description'] : null,
            (bool) $row['is_active'],
            (string) $row['created_at'],
            $variants
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRowToVariant(array $row): ProductVariant
    {
        return new ProductVariant(
            (string) $row['id'],
            (string) $row['product_id'],
            new Sku((string) $row['sku']),
            (string) $row['flavor'],
            new NicotineStrength((string) $row['nicotine_strength']),
            Money::fromFloat((float) $row['price']),
            (int) $row['stock_quantity'],
            (bool) $row['is_active']
        );
    }
}
