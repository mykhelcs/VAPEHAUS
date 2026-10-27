-- ============================================================
-- VAPEHAUS — Supabase PostgreSQL Schema + Row Level Security (RLS)
-- Paste this entire script into:
-- Supabase Dashboard -> SQL Editor -> New query -> Run
-- ============================================================

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- ── 1. USERS ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id              VARCHAR(64)  PRIMARY KEY,
    email           VARCHAR(191) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100) NOT NULL,
    phone           VARCHAR(50),
    role            VARCHAR(20)  NOT NULL DEFAULT 'customer',
    birth_date      DATE,
    is_age_verified INTEGER      NOT NULL DEFAULT 0,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
ALTER TABLE users ENABLE ROW LEVEL SECURITY;
-- Direct anon/authenticated client API access denied. The PHP backend connects via DATABASE_URL (postgres user), which bypasses RLS.
CREATE POLICY "deny_anon_users" ON users FOR ALL TO anon, authenticated USING (false);

-- ── 2. CATEGORIES ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS categories (
    id          VARCHAR(64)  PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    is_active   INTEGER      NOT NULL DEFAULT 1
);
ALTER TABLE categories ENABLE ROW LEVEL SECURITY;
CREATE POLICY "public_read_categories" ON categories FOR SELECT TO anon, authenticated USING (is_active = 1);
CREATE POLICY "deny_write_categories" ON categories FOR INSERT TO anon, authenticated WITH CHECK (false);

-- ── 3. PRODUCTS ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS products (
    id             VARCHAR(64)   PRIMARY KEY,
    category_id    VARCHAR(64)   NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
    name           VARCHAR(200)  NOT NULL,
    slug           VARCHAR(200)  NOT NULL UNIQUE,
    brand          VARCHAR(100)  NOT NULL,
    description    TEXT,
    average_rating DECIMAL(3,2)  NOT NULL DEFAULT 0.00,
    review_count   INTEGER       NOT NULL DEFAULT 0,
    is_active      INTEGER       NOT NULL DEFAULT 1,
    created_at     TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);
ALTER TABLE products ENABLE ROW LEVEL SECURITY;
CREATE POLICY "public_read_products" ON products FOR SELECT TO anon, authenticated USING (is_active = 1);
CREATE POLICY "deny_write_products" ON products FOR INSERT TO anon, authenticated WITH CHECK (false);

-- ── 4. PRODUCT VARIANTS ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS product_variants (
    id                VARCHAR(64)   PRIMARY KEY,
    product_id        VARCHAR(64)   NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    sku               VARCHAR(100)  NOT NULL UNIQUE,
    flavor            VARCHAR(100)  NOT NULL,
    nicotine_strength VARCHAR(50)   NOT NULL,
    price             DECIMAL(10,2) NOT NULL,
    stock_quantity    INTEGER       NOT NULL DEFAULT 0,
    is_active         INTEGER       NOT NULL DEFAULT 1
);
ALTER TABLE product_variants ENABLE ROW LEVEL SECURITY;
CREATE POLICY "public_read_variants" ON product_variants FOR SELECT TO anon, authenticated USING (is_active = 1);
CREATE POLICY "deny_write_variants" ON product_variants FOR INSERT TO anon, authenticated WITH CHECK (false);

-- ── 5. CARTS ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS carts (
    id                  VARCHAR(64)  PRIMARY KEY,
    user_id             VARCHAR(64),
    session_token       VARCHAR(128),
    applied_coupon_code VARCHAR(64),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
ALTER TABLE carts ENABLE ROW LEVEL SECURITY;
CREATE POLICY "deny_anon_carts" ON carts FOR ALL TO anon, authenticated USING (false);

-- ── 6. CART ITEMS ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cart_items (
    id         VARCHAR(64) PRIMARY KEY,
    cart_id    VARCHAR(64) NOT NULL REFERENCES carts(id) ON DELETE CASCADE,
    variant_id VARCHAR(64) NOT NULL REFERENCES product_variants(id) ON DELETE CASCADE,
    quantity   INTEGER     NOT NULL DEFAULT 1
);
ALTER TABLE cart_items ENABLE ROW LEVEL SECURITY;
CREATE POLICY "deny_anon_cart_items" ON cart_items FOR ALL TO anon, authenticated USING (false);

-- ── 7. ORDERS ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS orders (
    id               VARCHAR(64)   PRIMARY KEY,
    order_number     VARCHAR(64)   NOT NULL UNIQUE,
    user_id          VARCHAR(64)   NOT NULL REFERENCES users(id),
    status           VARCHAR(30)   NOT NULL DEFAULT 'pending',
    subtotal         DECIMAL(10,2) NOT NULL,
    discount_amount  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    coupon_code      VARCHAR(64),
    shipping_fee     DECIMAL(10,2) NOT NULL,
    total            DECIMAL(10,2) NOT NULL,
    shipping_address TEXT          NOT NULL,
    payment_method   VARCHAR(50)   NOT NULL DEFAULT 'cod',
    payment_status   VARCHAR(30)   NOT NULL DEFAULT 'unpaid',
    tracking_number  VARCHAR(100),
    courier_name     VARCHAR(100),
    tracking_url     TEXT,
    shipped_at       TIMESTAMPTZ,
    delivered_at     TIMESTAMPTZ,
    created_at       TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);
ALTER TABLE orders ENABLE ROW LEVEL SECURITY;
CREATE POLICY "deny_anon_orders" ON orders FOR ALL TO anon, authenticated USING (false);

-- ── 8. ORDER ITEMS ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS order_items (
    id                   VARCHAR(64)   PRIMARY KEY,
    order_id             VARCHAR(64)   NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    variant_id           VARCHAR(64)   NOT NULL,
    snapshotted_name     VARCHAR(200)  NOT NULL,
    snapshotted_sku      VARCHAR(100)  NOT NULL,
    snapshotted_flavor   VARCHAR(100)  NOT NULL,
    snapshotted_nicotine VARCHAR(50)   NOT NULL,
    unit_price           DECIMAL(10,2) NOT NULL,
    quantity             INTEGER       NOT NULL,
    total_price          DECIMAL(10,2) NOT NULL
);
ALTER TABLE order_items ENABLE ROW LEVEL SECURITY;
CREATE POLICY "deny_anon_order_items" ON order_items FOR ALL TO anon, authenticated USING (false);

-- ── 9. USER ADDRESSES ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS user_addresses (
    id             VARCHAR(64)  PRIMARY KEY,
    user_id        VARCHAR(64)  NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    label          VARCHAR(50)  NOT NULL DEFAULT 'Home',
    recipient_name VARCHAR(100) NOT NULL,
    phone          VARCHAR(50)  NOT NULL,
    address_line1  TEXT         NOT NULL,
    address_line2  TEXT,
    city           VARCHAR(100) NOT NULL,
    province       VARCHAR(100) NOT NULL,
    postal_code    VARCHAR(20)  NOT NULL,
    is_default     INTEGER      NOT NULL DEFAULT 0,
    created_at     TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
ALTER TABLE user_addresses ENABLE ROW LEVEL SECURITY;
CREATE POLICY "deny_anon_addresses" ON user_addresses FOR ALL TO anon, authenticated USING (false);

-- ── 10. COUPONS ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS coupons (
    id           VARCHAR(64)   PRIMARY KEY,
    code         VARCHAR(64)   NOT NULL UNIQUE,
    type         VARCHAR(30)   NOT NULL,
    value        DECIMAL(10,2) NOT NULL,
    min_spend    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    max_discount DECIMAL(10,2),
    usage_limit  INTEGER       NOT NULL DEFAULT 0,
    times_used   INTEGER       NOT NULL DEFAULT 0,
    expires_at   TIMESTAMPTZ,
    is_active    INTEGER       NOT NULL DEFAULT 1,
    created_at   TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);
ALTER TABLE coupons ENABLE ROW LEVEL SECURITY;
CREATE POLICY "public_read_active_coupons" ON coupons FOR SELECT TO anon, authenticated USING (is_active = 1);
CREATE POLICY "deny_write_coupons" ON coupons FOR INSERT TO anon, authenticated WITH CHECK (false);

CREATE TABLE IF NOT EXISTS coupon_redemptions (
    id              VARCHAR(64)   PRIMARY KEY,
    coupon_id       VARCHAR(64)   NOT NULL REFERENCES coupons(id) ON DELETE CASCADE,
    user_id         VARCHAR(64)   NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    order_id        VARCHAR(64)   NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    discount_amount DECIMAL(10,2) NOT NULL,
    redeemed_at     TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);
ALTER TABLE coupon_redemptions ENABLE ROW LEVEL SECURITY;
CREATE POLICY "deny_anon_redemptions" ON coupon_redemptions FOR ALL TO anon, authenticated USING (false);

-- ── 11. PRODUCT REVIEWS ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS product_reviews (
    id                VARCHAR(64)  PRIMARY KEY,
    product_id        VARCHAR(64)  NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    user_id           VARCHAR(64)  NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    rating            INTEGER      NOT NULL CHECK (rating BETWEEN 1 AND 5),
    title             VARCHAR(150) NOT NULL,
    comment           TEXT         NOT NULL,
    is_verified_buyer INTEGER      NOT NULL DEFAULT 0,
    status            VARCHAR(30)  NOT NULL DEFAULT 'approved',
    created_at        TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
ALTER TABLE product_reviews ENABLE ROW LEVEL SECURITY;
CREATE POLICY "public_read_reviews" ON product_reviews FOR SELECT TO anon, authenticated USING (status = 'approved');
CREATE POLICY "deny_write_reviews" ON product_reviews FOR INSERT TO anon, authenticated WITH CHECK (false);

-- ── 12. KYC VERIFICATIONS ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS kyc_verifications (
    id               VARCHAR(64)  PRIMARY KEY,
    user_id          VARCHAR(64)  NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    id_type          VARCHAR(50)  NOT NULL,
    id_number        VARCHAR(100) NOT NULL,
    front_image_url  TEXT         NOT NULL,
    back_image_url   TEXT,
    status           VARCHAR(30)  NOT NULL DEFAULT 'pending',
    rejection_reason TEXT,
    verified_at      TIMESTAMPTZ,
    created_at       TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
ALTER TABLE kyc_verifications ENABLE ROW LEVEL SECURITY;
CREATE POLICY "deny_anon_kyc" ON kyc_verifications FOR ALL TO anon, authenticated USING (false);

-- ── 13. RETURN REQUESTS (RMA) ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS return_requests (
    id          VARCHAR(64) PRIMARY KEY,
    order_id    VARCHAR(64) NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    user_id     VARCHAR(64) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    variant_id  VARCHAR(64) NOT NULL REFERENCES product_variants(id) ON DELETE CASCADE,
    reason      VARCHAR(50) NOT NULL,
    description TEXT        NOT NULL,
    photo_url   TEXT,
    status      VARCHAR(30) NOT NULL DEFAULT 'requested',
    admin_notes TEXT,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
ALTER TABLE return_requests ENABLE ROW LEVEL SECURITY;
CREATE POLICY "deny_anon_rma" ON return_requests FOR ALL TO anon, authenticated USING (false);

-- ── 14. WISHLISTS ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS wishlists (
    id         VARCHAR(64) PRIMARY KEY,
    user_id    VARCHAR(64) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    product_id VARCHAR(64) NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (user_id, product_id)
);
ALTER TABLE wishlists ENABLE ROW LEVEL SECURITY;
CREATE POLICY "deny_anon_wishlists" ON wishlists FOR ALL TO anon, authenticated USING (false);

CREATE TABLE IF NOT EXISTS restock_subscriptions (
    id          VARCHAR(64) PRIMARY KEY,
    user_id     VARCHAR(64) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    variant_id  VARCHAR(64) NOT NULL REFERENCES product_variants(id) ON DELETE CASCADE,
    notified_at TIMESTAMPTZ,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
ALTER TABLE restock_subscriptions ENABLE ROW LEVEL SECURITY;
CREATE POLICY "deny_anon_restock" ON restock_subscriptions FOR ALL TO anon, authenticated USING (false);

-- ── 15. PAYMENT TRANSACTIONS ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS payment_transactions (
    id                    VARCHAR(64)   PRIMARY KEY,
    order_id              VARCHAR(64)   NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    gateway               VARCHAR(50)   NOT NULL,
    transaction_reference VARCHAR(150)  NOT NULL UNIQUE,
    amount                DECIMAL(10,2) NOT NULL,
    status                VARCHAR(30)   NOT NULL,
    raw_payload           TEXT,
    created_at            TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);
ALTER TABLE payment_transactions ENABLE ROW LEVEL SECURITY;
CREATE POLICY "deny_anon_payment_tx" ON payment_transactions FOR ALL TO anon, authenticated USING (false);

-- ── 16. INVENTORY AUDIT LOGS ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inventory_audit_logs (
    id             VARCHAR(64) PRIMARY KEY,
    variant_id     VARCHAR(64) NOT NULL REFERENCES product_variants(id) ON DELETE CASCADE,
    previous_stock INTEGER     NOT NULL,
    new_stock      INTEGER     NOT NULL,
    delta          INTEGER     NOT NULL,
    reason         VARCHAR(50) NOT NULL,
    actor_id       VARCHAR(64),
    created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
ALTER TABLE inventory_audit_logs ENABLE ROW LEVEL SECURITY;
CREATE POLICY "deny_anon_audit" ON inventory_audit_logs FOR ALL TO anon, authenticated USING (false);

-- ── 17. NOTIFICATIONS LOG ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications_log (
    id        VARCHAR(64)  PRIMARY KEY,
    recipient VARCHAR(150) NOT NULL,
    channel   VARCHAR(30)  NOT NULL,
    subject   VARCHAR(200) NOT NULL,
    body      TEXT         NOT NULL,
    sent_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
ALTER TABLE notifications_log ENABLE ROW LEVEL SECURITY;
CREATE POLICY "deny_anon_notif" ON notifications_log FOR ALL TO anon, authenticated USING (false);

-- ── 18. SESSIONS (Bearer tokens) ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sessions (
    token      VARCHAR(128) PRIMARY KEY,
    user_id    VARCHAR(64)  NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    expires_at TIMESTAMPTZ  NOT NULL,
    created_at TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
ALTER TABLE sessions ENABLE ROW LEVEL SECURITY;
CREATE POLICY "deny_anon_sessions" ON sessions FOR ALL TO anon, authenticated USING (false);

-- ── INDEXES ───────────────────────────────────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_products_category  ON products(category_id);
CREATE INDEX IF NOT EXISTS idx_products_active    ON products(is_active);
CREATE INDEX IF NOT EXISTS idx_variants_product   ON product_variants(product_id);
CREATE INDEX IF NOT EXISTS idx_orders_user        ON orders(user_id);
CREATE INDEX IF NOT EXISTS idx_orders_status      ON orders(status, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_sessions_user      ON sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_sessions_expires   ON sessions(expires_at);
CREATE INDEX IF NOT EXISTS idx_reviews_product    ON product_reviews(product_id, status);

-- ── SUPABASE STORAGE (KYC ID Images) ─────────────────────────────────────
-- Create bucket for regulatory government ID uploads
INSERT INTO storage.buckets (id, name, public)
VALUES ('kyc-documents', 'kyc-documents', false)
ON CONFLICT (id) DO NOTHING;

-- Allow authenticated and guest checkout users to upload to kyc-documents bucket
CREATE POLICY "allow_kyc_upload" ON storage.objects
    FOR INSERT TO anon, authenticated
    WITH CHECK (bucket_id = 'kyc-documents');

-- Prevent public downloads of private government IDs
CREATE POLICY "deny_public_kyc_download" ON storage.objects
    FOR SELECT TO anon, authenticated
    USING (false);
