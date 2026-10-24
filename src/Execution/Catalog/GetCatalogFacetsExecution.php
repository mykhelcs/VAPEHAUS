<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Catalog;

use PDO;
use Vapehaus\Infrastructure\Database\DatabaseConnection;

final class GetCatalogFacetsExecution
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getPdo();
    }

    /**
     * @return array{
     *     categories: array<int, array{id: string, name: string, count: int}>,
     *     brands: array<int, array{brand: string, count: int}>,
     *     nicotine_strengths: array<int, array{nicotine: string, count: int}>,
     *     price_range: array{min: float, max: float}
     * }
     */
    public function execute(): array
    {
        // 1. Categories with active product counts
        $catStmt = $this->pdo->query('SELECT c.id, c.name, COUNT(p.id) as count
            FROM categories c
            LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
            WHERE c.is_active = 1
            GROUP BY c.id, c.name
            ORDER BY count DESC');
        $categories = array_map(function ($r) {
            return [
                'id' => (string) $r['id'],
                'name' => (string) $r['name'],
                'count' => (int) $r['count'],
            ];
        }, $catStmt->fetchAll());

        // 2. Brands with product counts
        $brandStmt = $this->pdo->query('SELECT brand, COUNT(*) as count
            FROM products
            WHERE is_active = 1
            GROUP BY brand
            ORDER BY count DESC');
        $brands = array_map(function ($r) {
            return [
                'brand' => (string) $r['brand'],
                'count' => (int) $r['count'],
            ];
        }, $brandStmt->fetchAll());

        // 3. Nicotine strengths with variant counts
        $nicStmt = $this->pdo->query('SELECT nicotine_strength, COUNT(*) as count
            FROM product_variants
            WHERE is_active = 1
            GROUP BY nicotine_strength
            ORDER BY count DESC');
        $nicotineStrengths = array_map(function ($r) {
            return [
                'nicotine' => (string) $r['nicotine_strength'],
                'count' => (int) $r['count'],
            ];
        }, $nicStmt->fetchAll());

        // 4. Price range
        $priceStmt = $this->pdo->query('SELECT COALESCE(MIN(price), 0) as min_p, COALESCE(MAX(price), 0) as max_p FROM product_variants WHERE is_active = 1');
        $pRange = $priceStmt->fetch();

        return [
            'categories' => $categories,
            'brands' => $brands,
            'nicotine_strengths' => $nicotineStrengths,
            'price_range' => [
                'min' => (float) ($pRange['min_p'] ?? 0),
                'max' => (float) ($pRange['max_p'] ?? 0),
            ],
        ];
    }
}
