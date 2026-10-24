/**
 * VAPEHAUS API CLIENT
 * ---------------------------------------------------------------------------
 * Adheres strictly to framework.md standardized response envelopes:
 *   Success: { success: true,  data: ..., meta: ... }
 *   Error:   { success: false, error: { code, message, details } }
 *
 * Transport Strategy (deterministic, no undefined states):
 *   1. Always attempt a real HTTP call to CONFIG.API_BASE_URL.
 *   2. If the server responds with the standard envelope, return it directly.
 *   3. If the server is unreachable (network error / 502 / 503) AND
 *      CONFIG.ENABLE_LOCAL_FALLBACK is true, fall back to the local Store
 *      adapter so the UI stays usable during development.
 *   4. All errors — network, server, or validation — are returned as the
 *      standard error envelope; nothing throws to callers.
 * ---------------------------------------------------------------------------
 */

class VapehausApiClient {
  constructor(baseUrl = CONFIG.API_BASE_URL) {
    this.baseUrl = baseUrl;
    this._token = localStorage.getItem(CONFIG.STORAGE_KEYS.SESSION_TOKEN) || null;
  }

  // ── Private Transport ──────────────────────────────────────────────────

  _getHeaders() {
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    if (this._token) {
      headers['Authorization'] = `Bearer ${this._token}`;
    }
    return headers;
  }

  /**
   * Persist the Bearer token returned by the auth endpoints.
   * Called after a successful login or register.
   */
  _setToken(token) {
    this._token = token || null;
    if (token) {
      localStorage.setItem(CONFIG.STORAGE_KEYS.SESSION_TOKEN, token);
    } else {
      localStorage.removeItem(CONFIG.STORAGE_KEYS.SESSION_TOKEN);
    }
  }

  /**
   * Core HTTP request helper.
   * Returns the parsed JSON body on 2xx, or throws a structured error object
   * so _request callers can detect server vs. network failures cleanly.
   */
  async _http(method, path, body = null) {
    const url = `${this.baseUrl}${path}`;
    const opts = {
      method,
      headers: this._getHeaders(),
    };
    if (body !== null) {
      opts.body = JSON.stringify(body);
    }

    const response = await fetch(url, opts);

    // Parse body regardless of status code so error envelopes are readable
    let json;
    try {
      json = await response.json();
    } catch {
      // Non-JSON response (e.g. 502 HTML error page)
      const err = new Error(`HTTP ${response.status}: non-JSON response from server.`);
      err.isNetworkError = true;
      throw err;
    }

    return json; // Return envelope as-is; caller decides pass/fail
  }

  /**
   * Safe wrapper around _http that:
   *  - Returns the server envelope if the call succeeds.
   *  - Falls through to localFallback() if network/server is unavailable
   *    and CONFIG.ENABLE_LOCAL_FALLBACK is enabled.
   *  - Wraps any unexpected JS error in the standard error envelope.
   */
  async _request(method, path, body, localFallback) {
    try {
      const envelope = await this._http(method, path, body);

      // Standard envelope — return directly to caller
      if (typeof envelope.success === 'boolean') {
        return envelope;
      }

      // Non-standard response — treat as server error
      return this._wrapError('INVALID_RESPONSE', 'Server returned a non-standard response.');
    } catch (err) {
      // Network failure or non-JSON 5xx — fall back to local adapter if enabled
      const useLocal = typeof CONFIG !== 'undefined' && CONFIG.ENABLE_LOCAL_FALLBACK === true;
      if (useLocal && localFallback) {
        console.warn(`[ApiClient] Server unreachable (${err.message}). Using local fallback.`);
        try {
          return await localFallback();
        } catch (localErr) {
          return this._wrapError('LOCAL_ERROR', localErr.message);
        }
      }

      return this._wrapError('NETWORK_ERROR', `Cannot reach server: ${err.message}`);
    }
  }

  // ── Envelope Helpers ──────────────────────────────────────────────────

  _wrapSuccess(data, meta = {}) {
    return {
      success: true,
      data,
      meta: { timestamp: Date.now(), ...meta },
    };
  }

  _wrapError(code, message, details = []) {
    return {
      success: false,
      error: { code, message, details },
    };
  }

  // ── AUTH ──────────────────────────────────────────────────────────────

  /**
   * POST /api/v1/auth/login
   * On success: persists Bearer token; updates Store session.
   */
  async login(email, password) {
    const result = await this._request(
      'POST',
      '/auth/login',
      { email, password },
      async () => {
        // Local fallback: delegate to Store's credential check
        const user = Store.login(email, password); // throws on bad creds
        return this._wrapSuccess({ user, token: 'local_token' });
      }
    );

    if (result.success && result.data.token) {
      this._setToken(result.data.token);
      // Sync the Store session so all components read the correct user
      if (result.data.user) {
        Store.setSessionFromApi(result.data.user);
      }
    }

    return result;
  }

  /**
   * POST /api/v1/auth/register
   */
  async register(payload) {
    const result = await this._request(
      'POST',
      '/auth/register',
      payload,
      async () => {
        const user = Store.register(payload);
        return this._wrapSuccess({ user, token: 'local_token' });
      }
    );

    if (result.success && result.data.token) {
      this._setToken(result.data.token);
      if (result.data.user) {
        Store.setSessionFromApi(result.data.user);
      }
    }

    return result;
  }

  /**
   * POST /api/v1/auth/logout
   */
  async logout() {
    await this._request('POST', '/auth/logout', null, async () => {
      Store.logout();
      return this._wrapSuccess({});
    });
    this._setToken(null);
    Store.logout();
    return this._wrapSuccess({});
  }

  /**
   * GET /api/v1/auth/me
   */
  async me() {
    return this._request('GET', '/auth/me', null, async () => {
      const user = Store.getCurrentUser();
      if (!user) return this._wrapError('UNAUTHENTICATED', 'No active session.');
      return this._wrapSuccess({ user });
    });
  }

  // ── CATALOG ───────────────────────────────────────────────────────────

  async getCategories() {
    return this._request('GET', '/categories', null, async () =>
      this._wrapSuccess(Store.getCategories())
    );
  }

  async getProducts(filters = {}) {
    const params = new URLSearchParams();
    if (filters.category && filters.category !== 'all') params.set('category', filters.category);
    if (filters.brand) params.set('brand', filters.brand);
    if (filters.nicotine) params.set('nicotine', filters.nicotine);
    if (filters.query) params.set('q', filters.query);
    if (filters.sort) params.set('sort', filters.sort);
    const qs = params.toString();

    return this._request('GET', `/products${qs ? '?' + qs : ''}`, null, async () => {
      let products = Store.getProducts();
      if (filters.category && filters.category !== 'all') {
        products = products.filter((p) => p.category === filters.category);
      }
      if (filters.query) {
        const q = filters.query.toLowerCase();
        products = products.filter(
          (p) => p.name.toLowerCase().includes(q) || p.brand.toLowerCase().includes(q)
        );
      }
      if (filters.sort === 'price_asc') products.sort((a, b) => a.variants[0].price - b.variants[0].price);
      if (filters.sort === 'price_desc') products.sort((a, b) => b.variants[0].price - a.variants[0].price);
      if (filters.sort === 'rating') products.sort((a, b) => b.rating - a.rating);
      return this._wrapSuccess(products, { total: products.length });
    });
  }

  async getProductDetail(identifier) {
    return this._request('GET', `/products/${identifier}`, null, async () => {
      const product = Store.getProducts().find(
        (p) => p.id === identifier || p.slug === identifier
      );
      if (!product) return this._wrapError('NOT_FOUND', `Product "${identifier}" not found.`);
      return this._wrapSuccess(product);
    });
  }

  // ── CART ──────────────────────────────────────────────────────────────

  async getCart() {
    return this._request('GET', '/cart', null, async () =>
      this._wrapSuccess({
        items: Store.getCartItemsDetailed(),
        totals: Store.getCartTotals(),
        appliedCoupon: Store.getState().cart.appliedCouponCode,
      })
    );
  }

  async addToCart(variantId, quantity = 1) {
    return this._request('POST', '/cart/items', { variantId, quantity }, async () => {
      Store.addToCart(variantId, quantity);
      return this._cartEnvelope();
    });
  }

  async updateCartItem(variantId, quantity) {
    return this._request('PATCH', `/cart/items/${variantId}`, { quantity }, async () => {
      Store.updateCartQuantity(variantId, quantity);
      return this._cartEnvelope();
    });
  }

  async removeCartItem(variantId) {
    return this._request('DELETE', `/cart/items/${variantId}`, null, async () => {
      Store.removeFromCart(variantId);
      return this._cartEnvelope();
    });
  }

  async applyCoupon(code) {
    return this._request('POST', '/cart/coupon', { code }, async () => {
      const coupon = Store.applyCoupon(code);
      return this._wrapSuccess({ coupon, totals: Store.getCartTotals() });
    });
  }

  async removeCoupon() {
    return this._request('DELETE', '/cart/coupon', null, async () => {
      Store.removeCoupon();
      return this._cartEnvelope();
    });
  }

  _cartEnvelope() {
    return this._wrapSuccess({
      items: Store.getCartItemsDetailed(),
      totals: Store.getCartTotals(),
      appliedCoupon: Store.getState().cart.appliedCouponCode,
    });
  }

  // ── CHECKOUT & ORDERS ─────────────────────────────────────────────────

  async checkout(orderPayload) {
    return this._request('POST', '/checkout', orderPayload, async () => {
      const order = Store.placeOrder(orderPayload);
      return this._wrapSuccess(order, {}, 201);
    });
  }

  async getOrders(filters = {}) {
    const params = new URLSearchParams();
    if (filters.status && filters.status !== 'all') params.set('status', filters.status);
    if (filters.search) params.set('q', filters.search);
    const qs = params.toString();

    return this._request('GET', `/orders${qs ? '?' + qs : ''}`, null, async () => {
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
      return this._wrapSuccess(orders, { total: orders.length });
    });
  }

  async getOrderDetail(id) {
    return this._request('GET', `/orders/${id}`, null, async () => {
      const order = Store.getOrderById(id);
      if (!order) return this._wrapError('NOT_FOUND', `Order "${id}" not found.`);
      return this._wrapSuccess(order);
    });
  }

  async cancelOrder(orderId) {
    return this._request('POST', `/orders/${orderId}/cancel`, null, async () => {
      const order = Store.updateOrderStatus(orderId, OrderStatus.CANCELLED);
      return this._wrapSuccess(order);
    });
  }

  // ── ADMIN: ORDER FULFILLMENT ──────────────────────────────────────────

  async updateOrderStatus(orderId, status, metadata = {}) {
    return this._request(
      'PATCH',
      `/admin/orders/${orderId}/status`,
      { status, ...metadata },
      async () => {
        const order = Store.updateOrderStatus(orderId, status, metadata);
        return this._wrapSuccess(order);
      }
    );
  }

  // ── ADMIN: INVENTORY ──────────────────────────────────────────────────

  async updateStock(variantId, stockValue, isDelta = false) {
    return this._request(
      'PATCH',
      `/admin/inventory/${variantId}`,
      { stock: stockValue, isDelta },
      async () => {
        const variant = Store.updateVariantStock(variantId, stockValue, isDelta);
        return this._wrapSuccess(variant);
      }
    );
  }

  async addProduct(productPayload) {
    return this._request('POST', '/admin/products', productPayload, async () => {
      const product = Store.addProduct(productPayload);
      return this._wrapSuccess(product, {}, 201);
    });
  }

  async createCoupon(couponPayload) {
    return this._request('POST', '/admin/coupons', couponPayload, async () => {
      const coupon = Store.createCoupon(couponPayload);
      return this._wrapSuccess(coupon, {}, 201);
    });
  }

  // ── ADMIN: DASHBOARD METRICS ─────────────────────────────────────────

  async getDashboardMetrics() {
    return this._request('GET', '/admin/dashboard', null, async () => {
      const orders = Store.getOrders();
      const products = Store.getProducts();
      const kyc = Store.getState().kycRequests || [];

      let totalRevenue = 0;
      let pendingOrders = 0;
      let lowStockCount = 0;

      for (const order of orders) {
        if (order.status !== OrderStatus.CANCELLED) totalRevenue += order.total;
        if (
          order.status === OrderStatus.PENDING ||
          order.status === OrderStatus.PROCESSING
        ) {
          pendingOrders++;
        }
      }

      for (const prod of products) {
        for (const v of prod.variants) {
          if (v.stock < 10) lowStockCount++;
        }
      }

      return this._wrapSuccess({
        total_revenue: totalRevenue,
        total_orders: orders.length,
        pending_orders: pendingOrders,
        active_products: products.length,
        low_stock_skus: lowStockCount,
        pending_kyc: kyc.filter((k) => k.status === 'pending').length,
      });
    });
  }

  // ── REGULATORY KYC (RA 11900) ────────────────────────────────────────

  async submitKyc(kycPayload) {
    return this._request('POST', '/kyc', kycPayload, async () => {
      const id = 'kyc_' + Math.random().toString(36).substring(2, 8);
      const req = {
        id,
        userId: Store.getCurrentUser()?.id || 'usr_customer_default',
        customerName: `${Store.getCurrentUser()?.firstName || 'Juan'} ${Store.getCurrentUser()?.lastName || 'Dela Cruz'}`,
        customerEmail: Store.getCurrentUser()?.email || 'customer@vapehaus.ph',
        birthDate: kycPayload.birthDate || '1998-05-15',
        idType: kycPayload.idType || 'drivers_license',
        idNumber: kycPayload.idNumber || '',
        frontImageUrl: kycPayload.frontImageUrl || '',
        status: 'pending',
      };
      if (!Store.getState().kycRequests) Store.getState().kycRequests = [];
      Store.getState().kycRequests.unshift(req);
      Store.save();
      return this._wrapSuccess(req, {}, 201);
    });
  }

  async getKycStatus() {
    return this._request('GET', '/kyc', null, async () => {
      const user = Store.getCurrentUser();
      const kyc = (Store.getState().kycRequests || []).find((k) => k.userId === user?.id) || null;
      return this._wrapSuccess(kyc);
    });
  }

  async getAdminKyc(filters = {}) {
    return this._request('GET', '/admin/kyc', null, async () => {
      const kyc = Store.getState().kycRequests || [];
      return this._wrapSuccess(kyc);
    });
  }

  async reviewKyc(kycId, decision, reason = null) {
    return this._request('PATCH', `/admin/kyc/${kycId}/review`, { decision, reason }, async () => {
      const req = (Store.getState().kycRequests || []).find((k) => k.id === kycId);
      if (req) {
        req.status = decision;
        Store.save();
      }
      return this._wrapSuccess(req);
    });
  }
}

// ── Global Singleton ──────────────────────────────────────────────────────

const Api = new VapehausApiClient();

if (typeof module !== 'undefined' && module.exports) {
  module.exports = { VapehausApiClient, Api };
}
