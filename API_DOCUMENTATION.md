# Vapehaus Full-Scale E-Commerce API Reference

Base URL: `http://localhost:8000/api/v1`

---

## Standard Deterministic Response Envelope

### Success Response (`200 OK`, `201 Created`):
```json
{
  "success": true,
  "data": { ... },
  "meta": {
    "timestamp": 1729675200,
    "page": 1,
    "limit": 20,
    "total": 100
  }
}
```

### Error Response (`400`, `401`, `403`, `404`, `422`, `429`):
```json
{
  "success": false,
  "error": {
    "code": "UNDERAGE_RESTRICTION",
    "message": "Customer must be at least 18 years old to purchase vape products.",
    "details": []
  }
}
```

---

## 1. Authentication & Identity Endpoints

### Register Customer (with 18+ Age Verification)
- **Method**: `POST`
- **Path**: `/auth/register`
- **Access**: Public
- **Body**:
  ```json
  {
    "email": "customer@vapehaus.ph",
    "password": "SecurePassword123!",
    "first_name": "Juan",
    "last_name": "Dela Cruz",
    "phone": "+639171234567",
    "birth_date": "2000-05-15",
    "is_age_verified": true
  }
  ```

### Login
- **Method**: `POST`
- **Path**: `/auth/login`
- **Access**: Public
- **Body**:
  ```json
  {
    "email": "customer@vapehaus.ph",
    "password": "SecurePassword123!"
  }
  ```
- **Pre-Seeded Credentials**:
  - **Admin**: `admin@vapehaus.ph` / `AdminPass123!`
  - **Customer**: `customer@vapehaus.ph` / `VapePass123!`

### Current Session Profile
- **Method**: `GET`
- **Path**: `/auth/me`
- **Access**: Authenticated (`Authorization: Bearer <token>`)

---

## 2. Multi-Address Book Endpoints

### List Customer Addresses
- **Method**: `GET`
- **Path**: `/user/addresses`
- **Access**: Authenticated

### Add Delivery Address
- **Method**: `POST`
- **Path**: `/user/addresses`
- **Access**: Authenticated
- **Body**:
  ```json
  {
    "label": "Office",
    "recipient_name": "Juan Dela Cruz",
    "phone": "+639171234567",
    "address_line1": "Unit 502, Tower 1, High Street South",
    "address_line2": "BGC",
    "city": "Taguig City",
    "province": "Metro Manila",
    "postal_code": "1634",
    "is_default": true
  }
  ```

### Set Default Address
- **Method**: `PATCH`
- **Path**: `/user/addresses/{id}/default`
- **Access**: Authenticated

### Delete Address
- **Method**: `DELETE`
- **Path**: `/user/addresses/{id}`
- **Access**: Authenticated

---

## 3. Product Catalog & Faceted Search Endpoints

### List Categories
- **Method**: `GET`
- **Path**: `/categories`
- **Access**: Public

### Search & Filter Products
- **Method**: `GET`
- **Path**: `/products`
- **Access**: Public
- **Query Parameters**:
  - `category_id`: Filter by category UUID (e.g. `cat_disposables`)
  - `brand`: Filter by brand (e.g. `Geek Bar`, `Oxva`, `Nasty Juice`)
  - `flavor`: Filter by flavor string (e.g. `Watermelon`, `Mint`)
  - `nicotine`: Filter by strength (e.g. `0mg`, `20mg`, `50mg`)
  - `in_stock`: `1` or `0`
  - `query`: Text search across title, brand, description
  - `page` & `limit`: Pagination parameters

### Get Catalog Facet Counts
- **Method**: `GET`
- **Path**: `/products/facets`
- **Access**: Public
- **Returns**: Categories, brands, nicotine strengths with real-time product counts, and min/max prices.

### Product Details
- **Method**: `GET`
- **Path**: `/products/{slug_or_id}`
- **Access**: Public

---

## 4. Verified Buyer Reviews & Ratings

### List Product Reviews
- **Method**: `GET`
- **Path**: `/products/{productId}/reviews`
- **Access**: Public
- **Returns**: Approved reviews and summary statistics (average rating, total review count).

### Submit Product Review
- **Method**: `POST`
- **Path**: `/products/{productId}/reviews`
- **Access**: Authenticated
- **Body**:
  ```json
  {
    "rating": 5,
    "title": "Outstanding flavor & battery life!",
    "comment": "The dual mesh coil delivers consistent taste until the last puff."
  }
  ```

---

## 5. Shopping Cart & Coupons

### View Cart
- **Method**: `GET`
- **Path**: `/cart`
- **Access**: User (`Authorization: Bearer <token>`) or Guest (`X-Session-Token: <token>`)

### Add Item to Cart
- **Method**: `POST`
- **Path**: `/cart/items`
- **Body**:
  ```json
  {
    "variant_id": "var_gb_watermelon",
    "quantity": 2
  }
  ```

### Update Item Quantity
- **Method**: `PATCH`
- **Path**: `/cart/items/{itemId}`
- **Body**:
  ```json
  {
    "quantity": 3
  }
  ```

### Remove Item from Cart
- **Method**: `DELETE`
- **Path**: `/cart/items/{itemId}`

### Clear Cart
- **Method**: `DELETE`
- **Path**: `/cart`

### Apply Promotional Coupon
- **Method**: `POST`
- **Path**: `/cart/coupon`
- **Body**:
  ```json
  {
    "code": "WELCOME10"
  }
  ```
- **Pre-Seeded Coupons**:
  - `WELCOME10`: 10% discount (min spend: 500 PHP, max discount: 200 PHP)
  - `FREESHIP`: Free shipping (min spend: 800 PHP)
  - `VAPE100`: 100 PHP fixed discount (min spend: 1,200 PHP)

### Remove Coupon
- **Method**: `DELETE`
- **Path**: `/cart/coupon`

---

## 6. Checkout & Payment Gateway Webhooks

### Checkout
- **Method**: `POST`
- **Path**: `/checkout`
- **Access**: Authenticated Customer
- **Body**:
  ```json
  {
    "address_id": "addr_customer_default",
    "payment_method": "gcash"
  }
  ```

### Payment Gateway Webhook Receiver
- **Method**: `POST`
- **Path**: `/webhooks/payments`
- **Access**: Public (Called asynchronously by payment gateway e.g. PayMongo / Stripe / GCash)
- **Body**:
  ```json
  {
    "event": "payment.paid",
    "gateway": "gcash",
    "data": {
      "order_id": "ord_123456",
      "transaction_reference": "gc_ref_987654321",
      "amount": 1300.00
    }
  }
  ```

---

## 7. Customer Orders, Returns (RMA) & Wishlist

### List Orders
- **Method**: `GET`
- **Path**: `/user/orders`

### Order Details
- **Method**: `GET`
- **Path**: `/user/orders/{id}`

### Cancel Order
- **Method**: `POST`
- **Path**: `/user/orders/{id}/cancel`

### File Defective Vape Warranty Claim (RMA)
- **Method**: `POST`
- **Path**: `/user/orders/{orderId}/returns`
- **Access**: Authenticated
- **Body**:
  ```json
  {
    "order_id": "ord_123456",
    "variant_id": "var_oxva_black",
    "reason": "dead_on_arrival",
    "description": "Device screen is unresponsive out of the box.",
    "photo_url": "https://vapehaus.test/uploads/proof.jpg"
  }
  ```

### List Customer RMA Claims
- **Method**: `GET`
- **Path**: `/user/returns`

### Wishlist Endpoints
- **List Wishlist**: `GET /user/wishlist`
- **Toggle Wishlist**: `POST /user/wishlist/toggle/{productId}`
- **Back-in-Stock Alert**: `POST /products/variants/{variantId}/restock-alert`

---

## 8. Regulatory Vape KYC (Government ID Verification)

### View KYC Status
- **Method**: `GET`
- **Path**: `/user/kyc`

### Submit Government ID Document
- **Method**: `POST`
- **Path**: `/user/kyc/submit`
- **Access**: Authenticated
- **Body**:
  ```json
  {
    "id_type": "drivers_license",
    "id_number": "N01-23-456789",
    "front_image_url": "https://vapehaus.test/uploads/license_front.jpg",
    "back_image_url": "https://vapehaus.test/uploads/license_back.jpg"
  }
  ```

---

## 9. Admin Control Panel (`Role: admin`)

### Dashboard Metrics
- **Method**: `GET`
- **Path**: `/admin/dashboard`
- **Returns**: Revenue, total orders, pending orders, delivered orders, total products, total customers.

### Catalog Management
- **Create Category**: `POST /admin/categories`
- **Create Product**: `POST /admin/products`
- **Create Product Variant**: `POST /admin/products/{id}/variants`
- **Adjust Variant Stock**: `PATCH /admin/variants/{id}/stock` (`set`, `increment`, `decrement`)

### Order Dispatch & Shipping
- **List All Orders**: `GET /admin/orders?status=pending`
- **Audit Single Order**: `GET /admin/orders/{id}`
- **Transition Status**: `PATCH /admin/orders/{id}/status`
- **Ship Order with Tracking**: `PATCH /admin/orders/{id}/ship`
  ```json
  {
    "tracking_number": "JT63918273645",
    "courier_name": "J&T Express",
    "tracking_url": "https://jtexpress.ph/track?billcode=JT63918273645"
  }
  ```
- **Refund Order & Restore Stock**: `POST /admin/orders/{id}/refund`

### Audit & Compliance Queues
- **Inventory Audit Logs**: `GET /admin/inventory/logs?page=1&limit=50`
- **KYC Review Queue**: `GET /admin/kyc?status=pending`
- **Approve/Reject KYC**: `PATCH /admin/kyc/{id}/review` (`decision`: `approved` | `rejected`)
- **RMA Warranty Claims**: `GET /admin/returns`
- **Update RMA Status**: `PATCH /admin/returns/{id}/status` (`status`: `approved` | `replaced` | `refunded`)
- **Coupon CRUD**: `GET /admin/coupons`, `POST /admin/coupons`, `PATCH /admin/coupons/{id}/toggle`
- **Review Moderation**: `GET /admin/reviews`, `PATCH /admin/reviews/{id}/status`
