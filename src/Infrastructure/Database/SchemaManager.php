<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Database;

use PDO;
use Vapehaus\Infrastructure\Security\PasswordHasher;

final class SchemaManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function createTables(): void
    {
        $queries = [
            // 1. Users
            "CREATE TABLE IF NOT EXISTS users (
                id VARCHAR(64) PRIMARY KEY,
                email VARCHAR(191) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                phone VARCHAR(50),
                role VARCHAR(20) NOT NULL DEFAULT 'customer',
                birth_date DATE,
                is_age_verified INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL
            );",

            // 2. Categories
            "CREATE TABLE IF NOT EXISTS categories (
                id VARCHAR(64) PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(100) NOT NULL UNIQUE,
                description TEXT,
                is_active INTEGER NOT NULL DEFAULT 1
            );",

            // 3. Products
            "CREATE TABLE IF NOT EXISTS products (
                id VARCHAR(64) PRIMARY KEY,
                category_id VARCHAR(64) NOT NULL,
                name VARCHAR(200) NOT NULL,
                slug VARCHAR(200) NOT NULL UNIQUE,
                brand VARCHAR(100) NOT NULL,
                description TEXT,
                average_rating DECIMAL(3,2) NOT NULL DEFAULT 0.00,
                review_count INTEGER NOT NULL DEFAULT 0,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
            );",

            // 4. Product Variants
            "CREATE TABLE IF NOT EXISTS product_variants (
                id VARCHAR(64) PRIMARY KEY,
                product_id VARCHAR(64) NOT NULL,
                sku VARCHAR(100) NOT NULL UNIQUE,
                flavor VARCHAR(100) NOT NULL,
                nicotine_strength VARCHAR(50) NOT NULL,
                price DECIMAL(10,2) NOT NULL,
                stock_quantity INTEGER NOT NULL DEFAULT 0,
                is_active INTEGER NOT NULL DEFAULT 1,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            );",

            // 5. Carts
            "CREATE TABLE IF NOT EXISTS carts (
                id VARCHAR(64) PRIMARY KEY,
                user_id VARCHAR(64),
                session_token VARCHAR(128),
                applied_coupon_code VARCHAR(64),
                updated_at DATETIME NOT NULL
            );",

            // 6. Cart Items
            "CREATE TABLE IF NOT EXISTS cart_items (
                id VARCHAR(64) PRIMARY KEY,
                cart_id VARCHAR(64) NOT NULL,
                variant_id VARCHAR(64) NOT NULL,
                quantity INTEGER NOT NULL DEFAULT 1,
                FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
                FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
            );",

            // 7. Orders
            "CREATE TABLE IF NOT EXISTS orders (
                id VARCHAR(64) PRIMARY KEY,
                order_number VARCHAR(64) NOT NULL UNIQUE,
                user_id VARCHAR(64) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'pending',
                subtotal DECIMAL(10,2) NOT NULL,
                discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                coupon_code VARCHAR(64),
                shipping_fee DECIMAL(10,2) NOT NULL,
                total DECIMAL(10,2) NOT NULL,
                shipping_address TEXT NOT NULL,
                payment_method VARCHAR(50) NOT NULL DEFAULT 'cod',
                payment_status VARCHAR(30) NOT NULL DEFAULT 'unpaid',
                tracking_number VARCHAR(100),
                courier_name VARCHAR(100),
                tracking_url TEXT,
                shipped_at DATETIME,
                delivered_at DATETIME,
                created_at DATETIME NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id)
            );",

            // 8. Order Items
            "CREATE TABLE IF NOT EXISTS order_items (
                id VARCHAR(64) PRIMARY KEY,
                order_id VARCHAR(64) NOT NULL,
                variant_id VARCHAR(64) NOT NULL,
                snapshotted_name VARCHAR(200) NOT NULL,
                snapshotted_sku VARCHAR(100) NOT NULL,
                snapshotted_flavor VARCHAR(100) NOT NULL,
                snapshotted_nicotine VARCHAR(50) NOT NULL,
                unit_price DECIMAL(10,2) NOT NULL,
                quantity INTEGER NOT NULL,
                total_price DECIMAL(10,2) NOT NULL,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
            );",

            // 9. User Addresses (Multi-Address Book)
            "CREATE TABLE IF NOT EXISTS user_addresses (
                id VARCHAR(64) PRIMARY KEY,
                user_id VARCHAR(64) NOT NULL,
                label VARCHAR(50) NOT NULL DEFAULT 'Home',
                recipient_name VARCHAR(100) NOT NULL,
                phone VARCHAR(50) NOT NULL,
                address_line1 TEXT NOT NULL,
                address_line2 TEXT,
                city VARCHAR(100) NOT NULL,
                province VARCHAR(100) NOT NULL,
                postal_code VARCHAR(20) NOT NULL,
                is_default INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );",

            // 10. Coupons & Discounts Engine
            "CREATE TABLE IF NOT EXISTS coupons (
                id VARCHAR(64) PRIMARY KEY,
                code VARCHAR(64) NOT NULL UNIQUE,
                type VARCHAR(30) NOT NULL,
                value DECIMAL(10,2) NOT NULL,
                min_spend DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                max_discount DECIMAL(10,2),
                usage_limit INTEGER NOT NULL DEFAULT 0,
                times_used INTEGER NOT NULL DEFAULT 0,
                expires_at DATETIME,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL
            );",

            "CREATE TABLE IF NOT EXISTS coupon_redemptions (
                id VARCHAR(64) PRIMARY KEY,
                coupon_id VARCHAR(64) NOT NULL,
                user_id VARCHAR(64) NOT NULL,
                order_id VARCHAR(64) NOT NULL,
                discount_amount DECIMAL(10,2) NOT NULL,
                redeemed_at DATETIME NOT NULL,
                FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
            );",

            // 11. Verified Buyer Reviews
            "CREATE TABLE IF NOT EXISTS product_reviews (
                id VARCHAR(64) PRIMARY KEY,
                product_id VARCHAR(64) NOT NULL,
                user_id VARCHAR(64) NOT NULL,
                rating INTEGER NOT NULL,
                title VARCHAR(150) NOT NULL,
                comment TEXT NOT NULL,
                is_verified_buyer INTEGER NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT 'approved',
                created_at DATETIME NOT NULL,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );",

            // 12. Regulatory KYC / Govt ID Verification
            "CREATE TABLE IF NOT EXISTS kyc_verifications (
                id VARCHAR(64) PRIMARY KEY,
                user_id VARCHAR(64) NOT NULL,
                id_type VARCHAR(50) NOT NULL,
                id_number VARCHAR(100) NOT NULL,
                front_image_url TEXT NOT NULL,
                back_image_url TEXT,
                status VARCHAR(30) NOT NULL DEFAULT 'pending',
                rejection_reason TEXT,
                verified_at DATETIME,
                created_at DATETIME NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );",

            // 13. Warranty & Returns (RMA)
            "CREATE TABLE IF NOT EXISTS return_requests (
                id VARCHAR(64) PRIMARY KEY,
                order_id VARCHAR(64) NOT NULL,
                user_id VARCHAR(64) NOT NULL,
                variant_id VARCHAR(64) NOT NULL,
                reason VARCHAR(50) NOT NULL,
                description TEXT NOT NULL,
                photo_url TEXT,
                status VARCHAR(30) NOT NULL DEFAULT 'requested',
                admin_notes TEXT,
                created_at DATETIME NOT NULL,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
            );",

            // 14. Wishlists & Restock Alerts
            "CREATE TABLE IF NOT EXISTS wishlists (
                id VARCHAR(64) PRIMARY KEY,
                user_id VARCHAR(64) NOT NULL,
                product_id VARCHAR(64) NOT NULL,
                created_at DATETIME NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            );",

            "CREATE TABLE IF NOT EXISTS restock_subscriptions (
                id VARCHAR(64) PRIMARY KEY,
                user_id VARCHAR(64) NOT NULL,
                variant_id VARCHAR(64) NOT NULL,
                notified_at DATETIME,
                created_at DATETIME NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
            );",

            // 15. Payment Transactions Log
            "CREATE TABLE IF NOT EXISTS payment_transactions (
                id VARCHAR(64) PRIMARY KEY,
                order_id VARCHAR(64) NOT NULL,
                gateway VARCHAR(50) NOT NULL,
                transaction_reference VARCHAR(150) NOT NULL UNIQUE,
                amount DECIMAL(10,2) NOT NULL,
                status VARCHAR(30) NOT NULL,
                raw_payload TEXT,
                created_at DATETIME NOT NULL,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
            );",

            // 16. Inventory Audit Trail
            "CREATE TABLE IF NOT EXISTS inventory_audit_logs (
                id VARCHAR(64) PRIMARY KEY,
                variant_id VARCHAR(64) NOT NULL,
                previous_stock INTEGER NOT NULL,
                new_stock INTEGER NOT NULL,
                delta INTEGER NOT NULL,
                reason VARCHAR(50) NOT NULL,
                actor_id VARCHAR(64),
                created_at DATETIME NOT NULL,
                FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
            );",

            // 17. Notification Log
            "CREATE TABLE IF NOT EXISTS notifications_log (
                id VARCHAR(64) PRIMARY KEY,
                recipient VARCHAR(150) NOT NULL,
                channel VARCHAR(30) NOT NULL,
                subject VARCHAR(200) NOT NULL,
                body TEXT NOT NULL,
                sent_at DATETIME NOT NULL
            );",

            // 18. Server Sessions (Bearer tokens)
            "CREATE TABLE IF NOT EXISTS sessions (
                token VARCHAR(128) PRIMARY KEY,
                user_id VARCHAR(64) NOT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );"
        ];

        foreach ($queries as $sql) {
            $this->pdo->exec($sql);
        }
    }

    public function seedDefaults(): void
    {
        $hasher = new PasswordHasher();
        $now = date('Y-m-d H:i:s');

        // 1. Seed Admin
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $stmt->execute([':email' => 'admin@vapehaus.ph']);
        if ($stmt->fetchColumn() == 0) {
            $stmt = $this->pdo->prepare('INSERT INTO users (id, email, password_hash, first_name, last_name, phone, role, birth_date, is_age_verified, created_at)
                VALUES (:id, :email, :hash, :first, :last, :phone, :role, :dob, :verified, :created)');
            $stmt->execute([
                ':id' => 'usr_admin_default',
                ':email' => 'admin@vapehaus.ph',
                ':hash' => $hasher->hash('AdminPass123!'),
                ':first' => 'Vapehaus',
                ':last' => 'Administrator',
                ':phone' => '+639170000001',
                ':role' => 'admin',
                ':dob' => '1990-01-01',
                ':verified' => 1,
                ':created' => $now,
            ]);
        }

        // 2. Seed Customer
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $stmt->execute([':email' => 'customer@vapehaus.ph']);
        if ($stmt->fetchColumn() == 0) {
            $stmt = $this->pdo->prepare('INSERT INTO users (id, email, password_hash, first_name, last_name, phone, role, birth_date, is_age_verified, created_at)
                VALUES (:id, :email, :hash, :first, :last, :phone, :role, :dob, :verified, :created)');
            $stmt->execute([
                ':id' => 'usr_customer_default',
                ':email' => 'customer@vapehaus.ph',
                ':hash' => $hasher->hash('VapePass123!'),
                ':first' => 'Juan',
                ':last' => 'Dela Cruz',
                ':phone' => '+639178889999',
                ':role' => 'customer',
                ':dob' => '1998-05-15',
                ':verified' => 1,
                ':created' => $now,
            ]);

            // Seed default address for customer
            $addrStmt = $this->pdo->prepare('INSERT INTO user_addresses (id, user_id, label, recipient_name, phone, address_line1, address_line2, city, province, postal_code, is_default, created_at)
                VALUES (:id, :uid, :lbl, :name, :ph, :a1, :a2, :city, :prov, :zip, 1, :created)');
            $addrStmt->execute([
                ':id' => 'addr_customer_default',
                ':uid' => 'usr_customer_default',
                ':lbl' => 'Home',
                ':name' => 'Juan Dela Cruz',
                ':ph' => '+639178889999',
                ':a1' => 'Unit 12B, Emerald Tower, Ortigas Center',
                ':a2' => 'San Antonio',
                ':city' => 'Pasig City',
                ':prov' => 'Metro Manila',
                ':zip' => '1605',
                ':created' => $now,
            ]);
        }

        // 3. Seed Categories
        $categories = [
            ['id' => 'cat_disposables', 'name' => 'Disposable Vapes', 'slug' => 'disposable-vapes', 'desc' => 'All-in-one prefilled high-puff vape devices'],
            ['id' => 'cat_pod_systems', 'name' => 'Pod Systems', 'slug' => 'pod-systems', 'desc' => 'Refillable and closed-pod compact vape devices'],
            ['id' => 'cat_eliquids', 'name' => 'E-Liquids & Nic Salts', 'slug' => 'eliquids', 'desc' => 'Premium freebase and salt nicotine juices'],
        ];

        foreach ($categories as $cat) {
            $check = $this->pdo->prepare('SELECT COUNT(*) FROM categories WHERE id = :id');
            $check->execute([':id' => $cat['id']]);
            if ($check->fetchColumn() == 0) {
                $ins = $this->pdo->prepare('INSERT INTO categories (id, name, slug, description, is_active) VALUES (:id, :name, :slug, :desc, 1)');
                $ins->execute([
                    ':id' => $cat['id'],
                    ':name' => $cat['name'],
                    ':slug' => $cat['slug'],
                    ':desc' => $cat['desc'],
                ]);
            }
        }

        // 4. Seed Products and Variants
        $products = [
            [
                'id' => 'prd_geek_pulse',
                'category_id' => 'cat_disposables',
                'name' => 'Geek Bar Pulse 15000 Puffs',
                'slug' => 'geek-bar-pulse-15000',
                'brand' => 'Geek Bar',
                'description' => 'World first full screen disposable vape with dual mesh coil and pulse mode.',
                'rating' => 4.85,
                'reviews' => 12,
                'variants' => [
                    ['id' => 'var_gb_watermelon', 'sku' => 'GB-PULSE-WTRMLN-50MG', 'flavor' => 'Watermelon Ice', 'nicotine' => '50mg', 'price' => 650.00, 'stock' => 25],
                    ['id' => 'var_gb_blowpop', 'sku' => 'GB-PULSE-BLOWPOP-50MG', 'flavor' => 'Blow Pop', 'nicotine' => '50mg', 'price' => 650.00, 'stock' => 18],
                ]
            ],
            [
                'id' => 'prd_oxva_xlim',
                'category_id' => 'cat_pod_systems',
                'name' => 'Oxva Xlim Pro Pod System',
                'slug' => 'oxva-xlim-pro',
                'brand' => 'Oxva',
                'description' => 'Top fill cartridge pod system with 1000mAh battery and RGB light display.',
                'rating' => 4.90,
                'reviews' => 8,
                'variants' => [
                    ['id' => 'var_oxva_black', 'sku' => 'OXVA-XLIM-BLK-0MG', 'flavor' => 'Black Carbon Edition', 'nicotine' => '0mg', 'price' => 1250.00, 'stock' => 12],
                    ['id' => 'var_oxva_gleamy_cyan', 'sku' => 'OXVA-XLIM-CYAN-0MG', 'flavor' => 'Gleamy Cyan Edition', 'nicotine' => '0mg', 'price' => 1250.00, 'stock' => 8],
                ]
            ],
            [
                'id' => 'prd_nasty_slow_blow',
                'category_id' => 'cat_eliquids',
                'name' => 'Nasty Juice Slow Blow Salt Nic',
                'slug' => 'nasty-juice-slow-blow-salt',
                'brand' => 'Nasty Juice',
                'description' => 'The flavor of pineapple layered with lime soda.',
                'rating' => 4.75,
                'reviews' => 15,
                'variants' => [
                    ['id' => 'var_nasty_35mg', 'sku' => 'NASTY-SB-35MG', 'flavor' => 'Pineapple Lime Soda', 'nicotine' => '35mg', 'price' => 380.00, 'stock' => 30],
                    ['id' => 'var_nasty_50mg', 'sku' => 'NASTY-SB-50MG', 'flavor' => 'Pineapple Lime Soda', 'nicotine' => '50mg', 'price' => 380.00, 'stock' => 20],
                ]
            ]
        ];

        foreach ($products as $p) {
            $check = $this->pdo->prepare('SELECT COUNT(*) FROM products WHERE id = :id');
            $check->execute([':id' => $p['id']]);
            if ($check->fetchColumn() == 0) {
                $ins = $this->pdo->prepare('INSERT INTO products (id, category_id, name, slug, brand, description, average_rating, review_count, is_active, created_at)
                    VALUES (:id, :cid, :name, :slug, :brand, :desc, :rating, :revs, 1, :created)');
                $ins->execute([
                    ':id' => $p['id'],
                    ':cid' => $p['category_id'],
                    ':name' => $p['name'],
                    ':slug' => $p['slug'],
                    ':brand' => $p['brand'],
                    ':desc' => $p['description'],
                    ':rating' => $p['rating'] ?? 0.0,
                    ':revs' => $p['reviews'] ?? 0,
                    ':created' => $now,
                ]);

                foreach ($p['variants'] as $v) {
                    $vins = $this->pdo->prepare('INSERT INTO product_variants (id, product_id, sku, flavor, nicotine_strength, price, stock_quantity, is_active)
                        VALUES (:id, :pid, :sku, :flavor, :nic, :price, :stock, 1)');
                    $vins->execute([
                        ':id' => $v['id'],
                        ':pid' => $p['id'],
                        ':sku' => $v['sku'],
                        ':flavor' => $v['flavor'],
                        ':nic' => $v['nicotine'],
                        ':price' => $v['price'],
                        ':stock' => $v['stock'],
                    ]);
                }
            }
        }

        // 5. Seed Promotional Coupons
        $coupons = [
            [
                'id' => 'cpn_haus10',
                'code' => 'HAUS10',
                'type' => 'percentage',
                'value' => 10.00,
                'min_spend' => 0.00,
                'max_discount' => null,
                'usage_limit' => 10000,
            ],
            [
                'id' => 'cpn_welcome10',
                'code' => 'WELCOME10',
                'type' => 'percentage',
                'value' => 10.00,
                'min_spend' => 500.00,
                'max_discount' => 200.00,
                'usage_limit' => 1000,
            ],
            [
                'id' => 'cpn_freeship',
                'code' => 'FREESHIP',
                'type' => 'free_shipping',
                'value' => 50.00,
                'min_spend' => 800.00,
                'max_discount' => 50.00,
                'usage_limit' => 500,
            ],
            [
                'id' => 'cpn_vape100',
                'code' => 'VAPE100',
                'type' => 'fixed',
                'value' => 100.00,
                'min_spend' => 1200.00,
                'max_discount' => 100.00,
                'usage_limit' => 200,
            ],
        ];

        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

        foreach ($coupons as $cpn) {
            $check = $this->pdo->prepare('SELECT COUNT(*) FROM coupons WHERE code = :code');
            $check->execute([':code' => $cpn['code']]);
            if ($check->fetchColumn() == 0) {
                $ins = $this->pdo->prepare('INSERT INTO coupons (id, code, type, value, min_spend, max_discount, usage_limit, times_used, expires_at, is_active, created_at)
                    VALUES (:id, :code, :type, :val, :min, :max, :limit, 0, :exp, 1, :created)');
                $ins->execute([
                    ':id' => $cpn['id'],
                    ':code' => $cpn['code'],
                    ':type' => $cpn['type'],
                    ':val' => $cpn['value'],
                    ':min' => $cpn['min_spend'],
                    ':max' => $cpn['max_discount'],
                    ':limit' => $cpn['usage_limit'],
                    ':exp' => $expiresAt,
                    ':created' => $now,
                ]);
            }
        }
    }
}
