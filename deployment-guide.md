# VAPEHAUS — Deployment Guide

> Full step-by-step guide for running VAPEHAUS locally (XAMPP + MySQL) and deploying to production (Render + Supabase PostgreSQL).

---

## Table of Contents
1. [Project Structure Overview](#project-structure-overview)
2. [Option A: Localhost (XAMPP + MySQL)](#option-a-localhost--xampp--mysql)
   - [Prerequisites](#a-prerequisites)
   - [Setup Steps](#a-setup-steps)
   - [MySQL Database Schema (SQL)](#mysql-database-schema)
   - [Environment Configuration (.env)](#a-environment-env)
3. [Option B: Render + Supabase (Production)](#option-b-render--supabase-production)
   - [Prerequisites](#b-prerequisites)
   - [Supabase Database Setup](#supabase-database-setup)
   - [PostgreSQL Schema (SQL)](#postgresql-schema-supabase)
   - [Render Web Service Setup](#render-web-service-setup)
   - [Environment Variables on Render](#environment-variables-on-render)
4. [Credentials Reference](#credentials-reference)
5. [Post-Deployment Checklist](#post-deployment-checklist)

---

## Project Structure Overview

```
VAPEHAUS/
├── public/                  ← Frontend (HTML/CSS/JS — serve as web root)
│   ├── index.html           ← Customer Storefront
│   ├── admin.html           ← Admin Dashboard (RBAC-guarded)
│   ├── css/
│   │   ├── theme.css
│   │   ├── storefront.css
│   │   └── admin.css
│   └── js/
│       ├── config.js
│       ├── domain/
│       ├── store/
│       ├── api/
│       └── components/
├── src/                     ← PHP Backend (Domain / Execution / Infrastructure / Presentation)
│   ├── Domain/
│   ├── Execution/
│   ├── Infrastructure/
│   └── Presentation/
├── framework.md
├── .env.example             ← (create this — see below)
└── vapehaus-ui-sample.html
```

---

## Option A: Localhost — XAMPP + MySQL

### A. Prerequisites

| Tool | Version | Download |
|------|---------|---------|
| XAMPP | 8.2+ | https://www.apachefriends.org |
| PHP | 8.2+ | bundled in XAMPP |
| MySQL | 8.0+ | bundled in XAMPP |
| Composer | 2.x | https://getcomposer.org |
| Node.js | 20 LTS | https://nodejs.org (optional, for tooling only) |

---

### A. Setup Steps

#### Step 1 — Clone / Copy Project into XAMPP htdocs

```bash
# Open XAMPP → Shell  OR  Git Bash

# Navigate to htdocs
cd C:/xampp/htdocs

# Copy or clone your project
git clone https://github.com/YOUR_ORG/vapehaus.git vapehaus
# — OR — manually copy the VAPEHAUS folder here
```

Your storefront will be at: **http://localhost/vapehaus/public/**

---

#### Step 2 — Start Apache and MySQL in XAMPP Control Panel

1. Open **XAMPP Control Panel**.
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL**.
4. Both status lights should turn green.

---

#### Step 3 — Create the Database in phpMyAdmin

1. Open **http://localhost/phpmyadmin**
2. Click **New** (left sidebar).
3. Database name: `vapehaus_db`
4. Collation: `utf8mb4_unicode_ci`
5. Click **Create**.
6. Select `vapehaus_db` → click **SQL** tab → paste the schema below → click **Go**.

---

### MySQL Database Schema

> **Paste this entire block into phpMyAdmin → SQL tab for the `vapehaus_db` database.**

```sql
-- =====================================================================
-- VAPEHAUS — MySQL Schema
-- Database: vapehaus_db
-- Encoding: utf8mb4_unicode_ci
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+08:00'; -- Philippine Standard Time (PST)

-- ─────────────────────────────────────────────────
-- TABLE: users
-- ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `id`               CHAR(36)      NOT NULL DEFAULT (UUID()) PRIMARY KEY,
  `email`            VARCHAR(255)  NOT NULL UNIQUE,
  `password_hash`    VARCHAR(255)  NOT NULL,
  `first_name`       VARCHAR(100)  NOT NULL,
  `last_name`        VARCHAR(100)  NOT NULL,
  `phone`            VARCHAR(30)       NULL,
  `role`             ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  `is_age_verified`  TINYINT(1)    NOT NULL DEFAULT 0,
  `age_verified_at`  DATETIME          NULL,
  `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_email` (`email`),
  INDEX `idx_users_role`  (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────
-- TABLE: categories
-- ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `categories` (
  `id`         CHAR(36)     NOT NULL DEFAULT (UUID()) PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `slug`       VARCHAR(100) NOT NULL UNIQUE,
  `icon`       VARCHAR(10)      NULL,
  `sort_order` INT          NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────
-- TABLE: products
-- ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `products` (
  `id`            CHAR(36)      NOT NULL DEFAULT (UUID()) PRIMARY KEY,
  `category_id`   CHAR(36)          NULL,
  `name`          VARCHAR(255)  NOT NULL,
  `slug`          VARCHAR(255)  NOT NULL UNIQUE,
  `brand`         VARCHAR(100)  NOT NULL,
  `description`   TEXT              NULL,
  `rating`        DECIMAL(3,2)  NOT NULL DEFAULT 0.00,
  `reviews_count` INT           NOT NULL DEFAULT 0,
  `badge`         VARCHAR(50)       NULL,
  `color1`        VARCHAR(20)       NULL DEFAULT '#8b5cf6',
  `color2`        VARCHAR(20)       NULL DEFAULT '#22d3ee',
  `is_active`     TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
  INDEX `idx_products_slug`     (`slug`),
  INDEX `idx_products_brand`    (`brand`),
  INDEX `idx_products_category` (`category_id`),
  INDEX `idx_products_active`   (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────
-- TABLE: product_variants
-- ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `product_variants` (
  `id`             CHAR(36)      NOT NULL DEFAULT (UUID()) PRIMARY KEY,
  `product_id`     CHAR(36)      NOT NULL,
  `sku`            VARCHAR(100)  NOT NULL UNIQUE,
  `flavor`         VARCHAR(100)      NULL,
  `nicotine`       VARCHAR(20)       NULL DEFAULT '0mg',
  `price`          DECIMAL(10,2) NOT NULL,
  `original_price` DECIMAL(10,2)     NULL,
  `stock`          INT           NOT NULL DEFAULT 0,
  `is_active`      TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  INDEX `idx_variants_product` (`product_id`),
  INDEX `idx_variants_sku`     (`sku`),
  INDEX `idx_variants_stock`   (`stock`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────
-- TABLE: coupons
-- ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `coupons` (
  `id`              CHAR(36)      NOT NULL DEFAULT (UUID()) PRIMARY KEY,
  `code`            VARCHAR(50)   NOT NULL UNIQUE,
  `type`            ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  `value`           DECIMAL(10,2) NOT NULL,
  `min_order_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `max_uses`        INT               NULL,
  `used_count`      INT           NOT NULL DEFAULT 0,
  `is_active`       TINYINT(1)    NOT NULL DEFAULT 1,
  `expires_at`      DATETIME          NULL,
  `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_coupons_code`   (`code`),
  INDEX `idx_coupons_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────
-- TABLE: orders
-- ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `orders` (
  `id`               CHAR(36)      NOT NULL DEFAULT (UUID()) PRIMARY KEY,
  `order_number`     VARCHAR(30)   NOT NULL UNIQUE,
  `user_id`          CHAR(36)          NULL,
  `coupon_id`        CHAR(36)          NULL,
  `status`           ENUM(
                       'pending',
                       'processing',
                       'shipped',
                       'out_for_delivery',
                       'delivered',
                       'cancelled'
                     ) NOT NULL DEFAULT 'pending',
  -- Customer snapshot (denormalized for order history integrity)
  `customer_name`    VARCHAR(200)  NOT NULL,
  `customer_email`   VARCHAR(255)  NOT NULL,
  `customer_phone`   VARCHAR(30)       NULL,
  -- Shipping address (Philippine address format)
  `shipping_address` VARCHAR(255)  NOT NULL,
  `shipping_city`    VARCHAR(100)  NOT NULL,
  `shipping_province` VARCHAR(100) NOT NULL,
  `shipping_zip`     VARCHAR(10)       NULL,
  -- Financials
  `subtotal`         DECIMAL(10,2) NOT NULL,
  `discount_amount`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `shipping_fee`     DECIMAL(10,2) NOT NULL DEFAULT 150.00,
  `total`            DECIMAL(10,2) NOT NULL,
  -- Logistics
  `courier`          VARCHAR(50)       NULL,
  `payment_method`   VARCHAR(50)   NOT NULL DEFAULT 'cod',
  `tracking_number`  VARCHAR(100)      NULL,
  -- Legal / compliance
  `age_attested`     TINYINT(1)    NOT NULL DEFAULT 0,
  -- Timestamps
  `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)   ON DELETE SET NULL,
  FOREIGN KEY (`coupon_id`)  REFERENCES `coupons`(`id`) ON DELETE SET NULL,
  INDEX `idx_orders_user`    (`user_id`),
  INDEX `idx_orders_status`  (`status`),
  INDEX `idx_orders_number`  (`order_number`),
  INDEX `idx_orders_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────
-- TABLE: order_items
-- ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `order_items` (
  `id`             CHAR(36)      NOT NULL DEFAULT (UUID()) PRIMARY KEY,
  `order_id`       CHAR(36)      NOT NULL,
  `variant_id`     CHAR(36)          NULL,
  -- Snapshot data (denormalized — must not change even if product is edited)
  `product_name`   VARCHAR(255)  NOT NULL,
  `variant_sku`    VARCHAR(100)  NOT NULL,
  `variant_flavor` VARCHAR(100)      NULL,
  `nicotine`       VARCHAR(20)       NULL,
  `unit_price`     DECIMAL(10,2) NOT NULL,
  `quantity`       INT           NOT NULL,
  `line_total`     DECIMAL(10,2) NOT NULL,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`)   REFERENCES `orders`(`id`)           ON DELETE CASCADE,
  FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE SET NULL,
  INDEX `idx_order_items_order`   (`order_id`),
  INDEX `idx_order_items_variant` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────
-- TABLE: order_status_log
-- ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `order_status_log` (
  `id`          CHAR(36)   NOT NULL DEFAULT (UUID()) PRIMARY KEY,
  `order_id`    CHAR(36)   NOT NULL,
  `from_status` VARCHAR(30)    NULL,
  `to_status`   VARCHAR(30) NOT NULL,
  `changed_by`  CHAR(36)       NULL,
  `note`        TEXT           NULL,
  `created_at`  DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  INDEX `idx_status_log_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────
-- TABLE: kyc_requests (18+ ID verification)
-- ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `kyc_requests` (
  `id`          CHAR(36)      NOT NULL DEFAULT (UUID()) PRIMARY KEY,
  `user_id`     CHAR(36)      NOT NULL,
  `id_type`     VARCHAR(50)       NULL,
  `id_image_url` VARCHAR(500)     NULL,
  `selfie_url`  VARCHAR(500)      NULL,
  `status`      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` CHAR(36)          NULL,
  `reviewed_at` DATETIME          NULL,
  `note`        TEXT              NULL,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_kyc_user`   (`user_id`),
  INDEX `idx_kyc_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────
-- TABLE: sessions (server-side token store)
-- ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `sessions` (
  `token`       VARCHAR(128)  NOT NULL PRIMARY KEY,
  `user_id`     CHAR(36)      NOT NULL,
  `ip_address`  VARCHAR(45)       NULL,
  `user_agent`  TEXT              NULL,
  `expires_at`  DATETIME      NOT NULL,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_sessions_user`    (`user_id`),
  INDEX `idx_sessions_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SEED DATA — Default categories, demo users, and starter coupon
-- =====================================================================

-- Categories
INSERT INTO `categories` (`id`, `name`, `slug`, `icon`, `sort_order`) VALUES
  ('cat_all',          'All Products',      'all',         '🔥', 0),
  ('cat_disposables',  'Disposables',       'disposables', '🗑️', 1),
  ('cat_pod_systems',  'Pod Systems',       'pod-systems', '🧃', 2),
  ('cat_devices',      'Devices & Mods',    'devices',     '🔋', 3),
  ('cat_eliquids',     'E-Liquids & Salts', 'eliquids',    '💧', 4),
  ('cat_coils',        'Coils & Pods',      'coils',       '🔧', 5);

-- Demo Users (passwords are bcrypt hashes)
-- customer: VapePass123!  |  admin: AdminPass123!
INSERT INTO `users` (`id`, `email`, `password_hash`, `first_name`, `last_name`, `phone`, `role`, `is_age_verified`, `age_verified_at`) VALUES
  ('usr_customer_default',
   'customer@vapehaus.ph',
   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- VapePass123!
   'Juan', 'Dela Cruz', '+63 917 888 9999', 'customer', 1, NOW()),
  ('usr_admin_default',
   'admin@vapehaus.ph',
   '$2y$12$TmGQCvbq.Q7o1NN4MmECyeNYhQVX9.x7L0V3qEuPD3gFdiqx/7pQy', -- AdminPass123!
   'Vapehaus', 'Administrator', NULL, 'admin', 1, NOW());

-- Default coupon: HAUS10 (10% off)
INSERT INTO `coupons` (`id`, `code`, `type`, `value`, `min_order_value`, `is_active`) VALUES
  ('cpn_haus10', 'HAUS10', 'percent', 10.00, 0.00, 1);
```

> [!IMPORTANT]
> The password hashes above are **placeholder bcrypt hashes**. Before launching production, generate real hashes with PHP: `password_hash('YourPassword', PASSWORD_BCRYPT, ['cost' => 12])` and replace them.

---

#### Step 4 — Configure `.env`

Create a file named `.env` in the project root (`C:/xampp/htdocs/vapehaus/.env`):

```ini
# ── Application ──────────────────────────────────
APP_NAME=VAPEHAUS
APP_ENV=local
APP_URL=http://localhost/vapehaus/public
APP_DEBUG=true
APP_SECRET_KEY=change-this-to-a-random-64-char-string

# ── Database (MySQL via XAMPP) ────────────────────
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vapehaus_db
DB_USERNAME=root
DB_PASSWORD=          # empty by default in XAMPP

# ── Session ───────────────────────────────────────
SESSION_LIFETIME_HOURS=24

# ── Shipping Fees (PHP) ───────────────────────────
SHIPPING_STANDARD_FEE=150
SHIPPING_EXPRESS_FEE=250
SHIPPING_FREE_THRESHOLD=1500
```

---

#### Step 5 — Install PHP Dependencies

```bash
# In project root
cd C:/xampp/htdocs/vapehaus
composer install
```

---

#### Step 6 — Configure Apache Virtual Host (Optional but Recommended)

Open `C:\xampp\apache\conf\extra\httpd-vhosts.conf` and add:

```apache
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/vapehaus/public"
    ServerName vapehaus.local
    <Directory "C:/xampp/htdocs/vapehaus/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Add to `C:\Windows\System32\drivers\etc\hosts`:

```
127.0.0.1   vapehaus.local
```

Restart Apache. Access: **http://vapehaus.local**

---

#### Step 7 — Verify

| URL | Expected |
|-----|---------|
| `http://localhost/vapehaus/public/` | Customer Storefront (18+ gate appears) |
| `http://localhost/vapehaus/public/admin.html` | Bounced to storefront (no session) |
| `http://localhost/vapehaus/public/api/v1/products` | JSON response `{ success: true, data: [...] }` |

---

---

## Option B: Render + Supabase (Production)

### B. Prerequisites

| Account | URL |
|---------|-----|
| GitHub | https://github.com — push your project here first |
| Render | https://dashboard.render.com |
| Supabase | https://supabase.com |

---

### Supabase Database Setup

#### Step 1 — Create a Supabase Project

1. Go to **https://supabase.com** → **Start your project**.
2. Click **New Project**.
3. Fill in:
   - **Organization**: your org (or create new)
   - **Name**: `vapehaus-prod`
   - **Database Password**: generate a strong password (save it!)
   - **Region**: `Southeast Asia (Singapore)` — closest to PH
4. Click **Create new project**. Wait ~2 minutes for provisioning.

---

#### Step 2 — Get Connection String

1. In your Supabase project → left sidebar → **Settings** → **Database**.
2. Scroll to **Connection string** → select **URI** tab.
3. Copy the URI — it looks like:
   ```
   postgresql://postgres:[YOUR-PASSWORD]@db.xxxxxxxxxxxx.supabase.co:5432/postgres
   ```
4. Save this — you'll paste it into Render's env vars.

---

### PostgreSQL Schema (Supabase)

#### Step 3 — Run Schema in Supabase SQL Editor

1. In your Supabase project → left sidebar → **SQL Editor** → **New query**.
2. Paste the entire block below → click **Run**.

```sql
-- =====================================================================
-- VAPEHAUS — PostgreSQL Schema (Supabase)
-- =====================================================================

-- Enable UUID generation
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- ─────────────────────────────────────────────────
-- TABLE: users
-- ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id               UUID         NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  email            VARCHAR(255) NOT NULL UNIQUE,
  password_hash    VARCHAR(255) NOT NULL,
  first_name       VARCHAR(100) NOT NULL,
  last_name        VARCHAR(100) NOT NULL,
  phone            VARCHAR(30),
  role             VARCHAR(20)  NOT NULL DEFAULT 'customer'
                   CHECK (role IN ('customer', 'admin')),
  is_age_verified  BOOLEAN      NOT NULL DEFAULT FALSE,
  age_verified_at  TIMESTAMPTZ,
  created_at       TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
  updated_at       TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_users_email ON users (email);
CREATE INDEX IF NOT EXISTS idx_users_role  ON users (role);

-- ─────────────────────────────────────────────────
-- TABLE: categories
-- ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS categories (
  id         UUID        NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  slug       VARCHAR(100) NOT NULL UNIQUE,
  icon       VARCHAR(10),
  sort_order INT          NOT NULL DEFAULT 0,
  created_at TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

-- ─────────────────────────────────────────────────
-- TABLE: products
-- ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS products (
  id            UUID          NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  category_id   UUID          REFERENCES categories(id) ON DELETE SET NULL,
  name          VARCHAR(255)  NOT NULL,
  slug          VARCHAR(255)  NOT NULL UNIQUE,
  brand         VARCHAR(100)  NOT NULL,
  description   TEXT,
  rating        NUMERIC(3,2)  NOT NULL DEFAULT 0.00,
  reviews_count INT           NOT NULL DEFAULT 0,
  badge         VARCHAR(50),
  color1        VARCHAR(20)   DEFAULT '#8b5cf6',
  color2        VARCHAR(20)   DEFAULT '#22d3ee',
  is_active     BOOLEAN       NOT NULL DEFAULT TRUE,
  created_at    TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
  updated_at    TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_products_slug     ON products (slug);
CREATE INDEX IF NOT EXISTS idx_products_brand    ON products (brand);
CREATE INDEX IF NOT EXISTS idx_products_category ON products (category_id);
CREATE INDEX IF NOT EXISTS idx_products_active   ON products (is_active);

-- ─────────────────────────────────────────────────
-- TABLE: product_variants
-- ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS product_variants (
  id             UUID          NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  product_id     UUID          NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  sku            VARCHAR(100)  NOT NULL UNIQUE,
  flavor         VARCHAR(100),
  nicotine       VARCHAR(20)   DEFAULT '0mg',
  price          NUMERIC(10,2) NOT NULL,
  original_price NUMERIC(10,2),
  stock          INT           NOT NULL DEFAULT 0,
  is_active      BOOLEAN       NOT NULL DEFAULT TRUE,
  created_at     TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
  updated_at     TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_variants_product ON product_variants (product_id);
CREATE INDEX IF NOT EXISTS idx_variants_sku     ON product_variants (sku);
CREATE INDEX IF NOT EXISTS idx_variants_stock   ON product_variants (stock);

-- ─────────────────────────────────────────────────
-- TABLE: coupons
-- ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS coupons (
  id              UUID          NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  code            VARCHAR(50)   NOT NULL UNIQUE,
  type            VARCHAR(10)   NOT NULL DEFAULT 'percent'
                  CHECK (type IN ('percent', 'fixed')),
  value           NUMERIC(10,2) NOT NULL,
  min_order_value NUMERIC(10,2) NOT NULL DEFAULT 0.00,
  max_uses        INT,
  used_count      INT           NOT NULL DEFAULT 0,
  is_active       BOOLEAN       NOT NULL DEFAULT TRUE,
  expires_at      TIMESTAMPTZ,
  created_at      TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_coupons_code   ON coupons (code);
CREATE INDEX IF NOT EXISTS idx_coupons_active ON coupons (is_active);

-- ─────────────────────────────────────────────────
-- TABLE: orders
-- ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS orders (
  id                UUID          NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  order_number      VARCHAR(30)   NOT NULL UNIQUE,
  user_id           UUID          REFERENCES users(id) ON DELETE SET NULL,
  coupon_id         UUID          REFERENCES coupons(id) ON DELETE SET NULL,
  status            VARCHAR(30)   NOT NULL DEFAULT 'pending'
                    CHECK (status IN (
                      'pending','processing','shipped',
                      'out_for_delivery','delivered','cancelled'
                    )),
  customer_name     VARCHAR(200)  NOT NULL,
  customer_email    VARCHAR(255)  NOT NULL,
  customer_phone    VARCHAR(30),
  shipping_address  VARCHAR(255)  NOT NULL,
  shipping_city     VARCHAR(100)  NOT NULL,
  shipping_province VARCHAR(100)  NOT NULL,
  shipping_zip      VARCHAR(10),
  subtotal          NUMERIC(10,2) NOT NULL,
  discount_amount   NUMERIC(10,2) NOT NULL DEFAULT 0.00,
  shipping_fee      NUMERIC(10,2) NOT NULL DEFAULT 150.00,
  total             NUMERIC(10,2) NOT NULL,
  courier           VARCHAR(50),
  payment_method    VARCHAR(50)   NOT NULL DEFAULT 'cod',
  tracking_number   VARCHAR(100),
  age_attested      BOOLEAN       NOT NULL DEFAULT FALSE,
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
  updated_at        TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_orders_user    ON orders (user_id);
CREATE INDEX IF NOT EXISTS idx_orders_status  ON orders (status);
CREATE INDEX IF NOT EXISTS idx_orders_number  ON orders (order_number);
CREATE INDEX IF NOT EXISTS idx_orders_created ON orders (created_at);

-- ─────────────────────────────────────────────────
-- TABLE: order_items
-- ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS order_items (
  id             UUID          NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  order_id       UUID          NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  variant_id     UUID          REFERENCES product_variants(id) ON DELETE SET NULL,
  product_name   VARCHAR(255)  NOT NULL,
  variant_sku    VARCHAR(100)  NOT NULL,
  variant_flavor VARCHAR(100),
  nicotine       VARCHAR(20),
  unit_price     NUMERIC(10,2) NOT NULL,
  quantity       INT           NOT NULL,
  line_total     NUMERIC(10,2) NOT NULL,
  created_at     TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_order_items_order   ON order_items (order_id);
CREATE INDEX IF NOT EXISTS idx_order_items_variant ON order_items (variant_id);

-- ─────────────────────────────────────────────────
-- TABLE: order_status_log
-- ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS order_status_log (
  id          UUID        NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  order_id    UUID        NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  from_status VARCHAR(30),
  to_status   VARCHAR(30) NOT NULL,
  changed_by  UUID        REFERENCES users(id) ON DELETE SET NULL,
  note        TEXT,
  created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_status_log_order ON order_status_log (order_id);

-- ─────────────────────────────────────────────────
-- TABLE: kyc_requests
-- ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS kyc_requests (
  id           UUID        NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  user_id      UUID        NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  id_type      VARCHAR(50),
  id_image_url VARCHAR(500),
  selfie_url   VARCHAR(500),
  status       VARCHAR(20)  NOT NULL DEFAULT 'pending'
               CHECK (status IN ('pending','approved','rejected')),
  reviewed_by  UUID         REFERENCES users(id) ON DELETE SET NULL,
  reviewed_at  TIMESTAMPTZ,
  note         TEXT,
  created_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_kyc_user   ON kyc_requests (user_id);
CREATE INDEX IF NOT EXISTS idx_kyc_status ON kyc_requests (status);

-- ─────────────────────────────────────────────────
-- TABLE: sessions
-- ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sessions (
  token      VARCHAR(128)  NOT NULL PRIMARY KEY,
  user_id    UUID          NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  ip_address VARCHAR(45),
  user_agent TEXT,
  expires_at TIMESTAMPTZ   NOT NULL,
  created_at TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_sessions_user    ON sessions (user_id);
CREATE INDEX IF NOT EXISTS idx_sessions_expires ON sessions (expires_at);

-- ─────────────────────────────────────────────────
-- AUTO-UPDATE updated_at trigger helper
-- ─────────────────────────────────────────────────
CREATE OR REPLACE FUNCTION trigger_set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = NOW();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE TRIGGER set_updated_at_users
  BEFORE UPDATE ON users
  FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();

CREATE OR REPLACE TRIGGER set_updated_at_products
  BEFORE UPDATE ON products
  FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();

CREATE OR REPLACE TRIGGER set_updated_at_product_variants
  BEFORE UPDATE ON product_variants
  FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();

CREATE OR REPLACE TRIGGER set_updated_at_orders
  BEFORE UPDATE ON orders
  FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();

-- ─────────────────────────────────────────────────
-- SEED DATA
-- ─────────────────────────────────────────────────
INSERT INTO categories (id, name, slug, icon, sort_order) VALUES
  ('a0000000-0000-0000-0000-000000000001', 'All Products',      'all',         '🔥', 0),
  ('a0000000-0000-0000-0000-000000000002', 'Disposables',       'disposables', '🗑️', 1),
  ('a0000000-0000-0000-0000-000000000003', 'Pod Systems',       'pod-systems', '🧃', 2),
  ('a0000000-0000-0000-0000-000000000004', 'Devices & Mods',    'devices',     '🔋', 3),
  ('a0000000-0000-0000-0000-000000000005', 'E-Liquids & Salts', 'eliquids',    '💧', 4),
  ('a0000000-0000-0000-0000-000000000006', 'Coils & Pods',      'coils',       '🔧', 5)
ON CONFLICT (slug) DO NOTHING;

-- Demo users (bcrypt hashes — replace with real hashes in production)
INSERT INTO users (id, email, password_hash, first_name, last_name, phone, role, is_age_verified, age_verified_at) VALUES
  ('b0000000-0000-0000-0000-000000000001',
   'customer@vapehaus.ph',
   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
   'Juan', 'Dela Cruz', '+63 917 888 9999', 'customer', TRUE, NOW()),
  ('b0000000-0000-0000-0000-000000000002',
   'admin@vapehaus.ph',
   '$2y$12$TmGQCvbq.Q7o1NN4MmECyeNYhQVX9.x7L0V3qEuPD3gFdiqx/7pQy',
   'Vapehaus', 'Administrator', NULL, 'admin', TRUE, NOW())
ON CONFLICT (email) DO NOTHING;

-- Default coupon
INSERT INTO coupons (id, code, type, value, min_order_value, is_active) VALUES
  ('c0000000-0000-0000-0000-000000000001', 'HAUS10', 'percent', 10.00, 0.00, TRUE)
ON CONFLICT (code) DO NOTHING;
```

---

### Render Web Service Setup

#### Step 4 — Push Project to GitHub

```bash
# In your project root
git init
git add .
git commit -m "feat: initial VAPEHAUS production build"
git remote add origin https://github.com/YOUR_USERNAME/vapehaus.git
git push -u origin main
```

---

#### Step 5 — Create a Render Web Service

1. Go to **https://dashboard.render.com** → **New +** → **Web Service**.
2. Connect your GitHub account if not done yet.
3. Select the `vapehaus` repository.
4. Configure the service:

| Field | Value |
|-------|-------|
| **Name** | `vapehaus-prod` |
| **Region** | Singapore (closest to PH) |
| **Branch** | `main` |
| **Root Directory** | *(leave blank)* |
| **Runtime** | `PHP` |
| **Build Command** | `composer install --no-dev --optimize-autoloader` |
| **Start Command** | `php -S 0.0.0.0:$PORT -t public` |
| **Instance Type** | Free (or Starter for production) |

> [!NOTE]
> The `-t public` flag sets `public/` as the web root so `index.html`, `admin.html`, and the PHP API routes are all served from one origin.

---

#### Step 6 — Add a `render.yaml` (Infrastructure as Code — Recommended)

Create `render.yaml` in the project root:

```yaml
services:
  - type: web
    name: vapehaus-prod
    runtime: php
    region: singapore
    branch: main
    buildCommand: composer install --no-dev --optimize-autoloader
    startCommand: php -S 0.0.0.0:$PORT -t public
    envVars:
      - key: APP_ENV
        value: production
      - key: APP_DEBUG
        value: false
      - key: APP_SECRET_KEY
        generateValue: true          # Render auto-generates a secure secret
      - key: DATABASE_URL
        sync: false                  # You'll paste the Supabase URI manually
      - key: DB_DRIVER
        value: pgsql
      - key: SESSION_LIFETIME_HOURS
        value: 24
      - key: SHIPPING_STANDARD_FEE
        value: 150
      - key: SHIPPING_EXPRESS_FEE
        value: 250
      - key: SHIPPING_FREE_THRESHOLD
        value: 1500
```

Commit and push this file. Render detects it automatically.

---

### Environment Variables on Render

#### Step 7 — Set Environment Variables

In Render dashboard → your service → **Environment** tab → add:

| Key | Value |
|-----|-------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://vapehaus-prod.onrender.com` |
| `APP_SECRET_KEY` | *(generate: `openssl rand -hex 32`)* |
| `DATABASE_URL` | `postgresql://postgres:[PASSWORD]@db.xxx.supabase.co:5432/postgres` |
| `DB_DRIVER` | `pgsql` |
| `SESSION_LIFETIME_HOURS` | `24` |
| `SHIPPING_STANDARD_FEE` | `150` |
| `SHIPPING_EXPRESS_FEE` | `250` |
| `SHIPPING_FREE_THRESHOLD` | `1500` |

> [!IMPORTANT]
> `DATABASE_URL` is the full Supabase connection URI from Step 2. Never commit this to Git.

---

#### Step 8 — Deploy

1. In Render dashboard → your service → click **Manual Deploy** → **Deploy latest commit**.
2. Watch the **Logs** tab — the build runs `composer install`, then starts the PHP dev server.
3. Once status shows **Live**, your app is accessible at:
   - **Storefront**: `https://vapehaus-prod.onrender.com/`
   - **Admin**: `https://vapehaus-prod.onrender.com/admin.html`
   - **API Health**: `https://vapehaus-prod.onrender.com/api/v1/products`

> [!TIP]
> Render's free tier **spins down after 15 minutes of inactivity**. Upgrade to the **Starter ($7/mo)** plan for always-on uptime.

---

---

## Credentials Reference

### Demo Login Credentials

| Role | Email | Password |
|------|-------|---------|
| Customer | `customer@vapehaus.ph` | `VapePass123!` |
| Admin | `admin@vapehaus.ph` | `AdminPass123!` |

### RBAC Flow

```
Sign In → customer@vapehaus.ph  →  Stays on storefront (/index.html)
Sign In → admin@vapehaus.ph     →  Redirected to Admin Dashboard (/admin.html)
Direct URL /admin.html (no session) → Bounced back to /index.html
```

### Active Promo Code

| Code | Discount |
|------|---------|
| `HAUS10` | 10% off any order |

---

## Post-Deployment Checklist

- [ ] Age gate appears on first visit to `/`
- [ ] Sign In modal opens from top-right header button
- [ ] Customer login stays on storefront; admin login redirects to `/admin.html`
- [ ] Direct access to `/admin.html` without admin session bounces to `/`
- [ ] Cart drawer opens, items add/remove correctly
- [ ] Coupon code `HAUS10` applies 10% discount
- [ ] Checkout wizard completes all 3 steps and shows order confirmation
- [ ] Admin dashboard shows KPI cards (Dashboard tab)
- [ ] `+ Add Catalog Product` and `+ Create Promo Code` buttons are visible only in admin
- [ ] `GET /api/v1/products` returns `{ "success": true, "data": [...] }`
- [ ] `POST /api/v1/auth/login` returns valid session token and role
- [ ] HTTPS is active (Render provides free SSL automatically)
- [ ] Replace placeholder bcrypt password hashes in seed data with real ones

---

> **Next Phase**: Wire [`public/js/api/client.js`](file:///C:/Users/ghjgjhgjgjg/Documents/SCHOOL/Kairus/VAPEHAUS/public/js/api/client.js) `fetch()` transport to actual PHP routes, replacing the local state adapter. Each method already maps 1:1 to the expected API endpoints.
