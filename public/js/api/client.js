/**
 * VAPEHAUS API CLIENT & LOCAL ADAPTER
 * Adheres strictly to framework.md standardized response envelopes:
 *  Success: { success: true, data: ..., meta: ... }
 *  Error:   { success: false, error: { code, message, details } }
 */

class VapehausApiClient {
  constructor(baseUrl = CONFIG.API_BASE_URL) {
    this.baseUrl = baseUrl;
    this.sessionToken = localStorage.getItem(CONFIG.STORAGE_KEYS.SESSION_TOKEN) || 'sess_' + Math.random().toString(36).substring(2);
    localStorage.setItem(CONFIG.STORAGE_KEYS.SESSION_TOKEN, this.sessionToken);
  }

  getHeaders() {
    return {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Session-Token': this.sessionToken,
    };
  }

  // Helper to wrap local execution in framework.md deterministic envelope
  wrapSuccess(data, meta = {}) {
    return {
      success: true,
      data,
      meta: {
        timestamp: Date.now(),
        ...meta,
      },
    };
  }

  wrapError(code, message, details = []) {
    return {
      success: false,
      error: {
        code,
        message,
        details,
      },
    };
  }

  // --- CATALOG API ---

  async getCategories() {
    return this.wrapSuccess(Store.getCategories());
  }

  async getProducts(filters = {}) {
    let products = Store.getProducts();

    if (filters.category && filters.category !== 'all') {
      products = products.filter((p) => p.category === filters.category);
    }

    if (filters.brand) {
      const brands = Array.isArray(filters.brand) ? filters.brand : [filters.brand];
      products = products.filter((p) => brands.includes(p.brand));
    }

    if (filters.nicotine) {
      products = products.filter((p) => p.variants.some((v) => v.nicotine === filters.nicotine));
    }

    if (filters.query) {
      const q = filters.query.toLowerCase();
      products = products.filter(
        (p) => p.name.toLowerCase().includes(q) || p.brand.toLowerCase().includes(q) || p.description.toLowerCase().includes(q)
      );
    }

    if (filters.sort) {
      if (filters.sort === 'price_asc') {
        products.sort((a, b) => a.variants[0].price - b.variants[0].price);
      } else if (filters.sort === 'price_desc') {
        products.sort((a, b) => b.variants[0].price - a.variants[0].price);
      } else if (filters.sort === 'rating') {
        products.sort((a, b) => b.rating - a.rating);
      }
    }

    return this.wrapSuccess(products, { total: products.length });
  }

  async getProductDetail(identifier) {
    const product = Store.getProducts().find((p) => p.id === identifier || p.slug === identifier);
    if (!product) {
      return this.wrapError('NOT_FOUND', `Product "${identifier}" was not found.`);
    }
    return this.wrapSuccess(product);
  }

  // --- CART API ---

  async getCart() {
    return this.wrapSuccess({
      items: Store.getCartItemsDetailed(),
      totals: Store.getCartTotals(),
      appliedCoupon: Store.getState().cart.appliedCouponCode,
    });
  }

  async addToCart(variantId, quantity = 1) {
    try {
      Store.addToCart(variantId, quantity);
      return this.getCart();
    } catch (err) {
      return this.wrapError('CART_ERROR', err.message);
    }
  }

  async updateCartItem(variantId, quantity) {
    try {
      Store.updateCartQuantity(variantId, quantity);
      return this.getCart();
    } catch (err) {
      return this.wrapError('CART_ERROR', err.message);
    }
  }

  async removeCartItem(variantId) {
    Store.removeFromCart(variantId);
    return this.getCart();
  }

  async applyCoupon(code) {
    try {
      const coupon = Store.applyCoupon(code);
      return this.wrapSuccess({ coupon, totals: Store.getCartTotals() });
    } catch (err) {
      return this.wrapError('COUPON_INVALID', err.message);
    }
  }

  async removeCoupon() {
    Store.removeCoupon();
    return this.getCart();
  }

  // --- CHECKOUT & ORDERS API ---

  async checkout(orderPayload) {
    try {
      const order = Store.placeOrder(orderPayload);
      return this.wrapSuccess(order, {}, 201);
    } catch (err) {
      return this.wrapError('CHECKOUT_FAILED', err.message);
    }
  }

  async getOrders(filters = {}) {
    let orders = Store.getOrders();
    if (filters.status && filters.status !== 'all') {
      orders = orders.filter((o) => o.status === filters.status);
    }
    if (filters.search) {
      const q = filters.search.toLowerCase();
      orders = orders.filter(
        (o) => o.orderNumber.toLowerCase().includes(q) || o.customerName.toLowerCase().includes(q)
      );
    }
    return this.wrapSuccess(orders, { total: orders.length });
  }

  async getOrderDetail(id) {
    const order = Store.getOrderById(id);
    if (!order) {
      return this.wrapError('NOT_FOUND', `Order "${id}" not found.`);
    }
    return this.wrapSuccess(order);
  }

  async updateOrderStatus(orderId, status, metadata = {}) {
    try {
      const order = Store.updateOrderStatus(orderId, status, metadata);
      return this.wrapSuccess(order);
    } catch (err) {
      return this.wrapError('TRANSITION_INVALID', err.message);
    }
  }

  // --- ADMIN INVENTORY API ---

  async updateStock(variantId, stockValue, isDelta = false) {
    try {
      const variant = Store.updateVariantStock(variantId, stockValue, isDelta);
      return this.wrapSuccess(variant);
    } catch (err) {
      return this.wrapError('STOCK_UPDATE_FAILED', err.message);
    }
  }

  async addProduct(productPayload) {
    try {
      const product = Store.addProduct(productPayload);
      return this.wrapSuccess(product, {}, 201);
    } catch (err) {
      return this.wrapError('PRODUCT_CREATE_FAILED', err.message);
    }
  }

  async getDashboardMetrics() {
    const orders = Store.getOrders();
    const products = Store.getProducts();
    const kyc = Store.getState().kycRequests;

    let totalRevenue = 0;
    let pendingOrders = 0;
    let lowStockCount = 0;

    for (const order of orders) {
      if (order.status !== OrderStatus.CANCELLED) {
        totalRevenue += order.total;
      }
      if (order.status === OrderStatus.PENDING || order.status === OrderStatus.PROCESSING) {
        pendingOrders++;
      }
    }

    for (const prod of products) {
      for (const v of prod.variants) {
        if (v.stock < 10) {
          lowStockCount++;
        }
      }
    }

    const pendingKyc = kyc.filter((k) => k.status === 'pending').length;

    return this.wrapSuccess({
      total_revenue: totalRevenue,
      total_orders: orders.length,
      pending_orders: pendingOrders,
      active_products: products.length,
      low_stock_skus: lowStockCount,
      pending_kyc: pendingKyc,
    });
  }
}

// Global API Client Instance
const Api = new VapehausApiClient();

if (typeof module !== 'undefined' && module.exports) {
  module.exports = { VapehausApiClient, Api };
}
