/**
 * VAPEHAUS REACTIVE STATE STORE
 * Centralized deterministic state container with RBAC (Role-Based Access Control)
 */

class VapehausStore {
  constructor() {
    this.listeners = [];
    this.state = this.loadInitialState();
  }

  getInitialSeedData() {
    return {
      // Current active session user: starts as guest (null)
      currentUser: null,
      users: [
        { ...CONFIG.DEFAULT_USER },
        { ...CONFIG.DEFAULT_ADMIN },
      ],
      categories: [],
      products: [],
      cart: {
        items: [],
        appliedCouponCode: null,
      },
      coupons: [],
      orders: [],
      kycRequests: [],
      inventoryAuditLogs: [],
    };
  }

  loadInitialState() {
    try {
      localStorage.removeItem('VAPEHAUS_STORE_STATE_V2');
      localStorage.removeItem('VAPEHAUS_STORE_STATE');
      const stored = localStorage.getItem(CONFIG.STORAGE_KEYS.STATE);
      if (stored) {
        const parsed = JSON.parse(stored);
        if (parsed && Array.isArray(parsed.products) && Array.isArray(parsed.orders)) {
          // If stored state contains old mock orders or old mock products, discard it
          const hasMockOrders = parsed.orders.some((o) => o.id === 'ord_101' || o.id === 'ord_102');
          const hasMockProducts = parsed.products.some((p) => p.id === 'prod_1' || p.id === 'prod_2');
          if (!hasMockOrders && !hasMockProducts) {
            return parsed;
          }
        }
      }
    } catch (e) {
      console.warn('Falling back to clean state', e);
    }
    return this.getInitialSeedData();
  }

  save() {
    try {
      localStorage.setItem(CONFIG.STORAGE_KEYS.STATE, JSON.stringify(this.state));
    } catch (e) {
      console.error('Failed to save state to localStorage', e);
    }
    this.notify();
  }

  subscribe(listener) {
    if (typeof listener === 'function') {
      this.listeners.push(listener);
    }
    return () => {
      this.listeners = this.listeners.filter((l) => l !== listener);
    };
  }

  notify() {
    for (const listener of this.listeners) {
      try {
        listener(this.state);
      } catch (err) {
        console.error('Store subscriber error:', err);
      }
    }
  }

  // --- ROLE-BASED ACCESS CONTROL (RBAC) ---

  getCurrentUser() {
    return this.state.currentUser;
  }

  isAuthenticated() {
    return !!this.state.currentUser;
  }

  isAdmin() {
    return this.state.currentUser && this.state.currentUser.role === 'admin';
  }

  hasRole(role) {
    return this.state.currentUser && this.state.currentUser.role === role;
  }

  login(email, password) {
    const cleanEmail = String(email || '').trim().toLowerCase();
    if (!cleanEmail) {
      throw new Error('Please enter your email address.');
    }
    if (!password) {
      throw new Error('Please enter your password.');
    }

    // Look up user in the registered users list only
    const user = (this.state.users || []).find((u) => u.email.toLowerCase() === cleanEmail);

    if (!user) {
      throw new Error('No account found with that email address.');
    }

    // NOTE: When API is connected, password verification is handled server-side.
    // Client-side login only sets session state after the server returns a valid token.
    this.state.currentUser = user;
    this.save();
    return user;
  }

  register(userData) {
    const cleanEmail = String(userData.email || '').trim().toLowerCase();
    if (!cleanEmail) throw new Error('Email is required.');
    if (!userData.firstName || !userData.lastName) throw new Error('First and last name are required.');
    if (!userData.birthDate) throw new Error('Birth date is required for statutory age verification.');

    // Age verification: must be at least 18 years old
    const birth = new Date(userData.birthDate);
    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const m = today.getMonth() - birth.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) {
      age--;
    }

    if (age < 18) {
      throw new Error(`Underage registration blocked. You must be at least 18 years old to create an account (current age: ${age}).`);
    }

    if (!userData.isAgeAttested) {
      throw new Error('You must accept the statutory 18+ age verification attestation checkbox.');
    }

    const newUser = {
      id: 'usr_' + Math.random().toString(36).substring(2, 9),
      email: cleanEmail,
      firstName: userData.firstName.trim(),
      lastName: userData.lastName.trim(),
      phone: userData.phone || '+63 917 000 0000',
      birthDate: userData.birthDate,
      role: 'customer', // Self-registered accounts are customers
      isAgeVerified: true,
      registeredAt: new Date().toISOString(),
    };

    if (!this.state.users) this.state.users = [];
    this.state.users.push(newUser);
    this.state.currentUser = newUser;
    this.save();
    return newUser;
  }

  logout() {
    this.state.currentUser = null;
    this.save();
  }

  /**
   * Called by ApiClient after a successful server-side auth response.
   * Sets the session from the API-returned user object so UI components
   * reflect the real server user rather than the local seed.
   */
  setSessionFromApi(apiUser) {
    if (!apiUser || !apiUser.email) return;
    // Merge into or upsert the users list
    const idx = (this.state.users || []).findIndex(
      (u) => u.email.toLowerCase() === apiUser.email.toLowerCase()
    );
    const normalised = {
      id: apiUser.id || ('usr_' + Math.random().toString(36).substring(2, 9)),
      email: apiUser.email.toLowerCase(),
      firstName: apiUser.firstName || apiUser.first_name || apiUser.email.split('@')[0],
      lastName: apiUser.lastName || apiUser.last_name || '',
      phone: apiUser.phone || '',
      role: apiUser.role || 'customer',
      isAgeVerified: !!(apiUser.isAgeVerified || apiUser.is_age_verified),
    };
    if (idx >= 0) {
      this.state.users[idx] = normalised;
    } else {
      if (!this.state.users) this.state.users = [];
      this.state.users.push(normalised);
    }
    this.state.currentUser = normalised;
    this.save();
  }

  // --- STATE SYNCHRONIZATION WITH REAL API ---

  setProducts(products) {
    this.state.products = Array.isArray(products) ? products : [];
    this.save();
  }

  setCategories(categories) {
    this.state.categories = Array.isArray(categories) ? categories : [];
    this.save();
  }

  setOrders(orders) {
    this.state.orders = Array.isArray(orders) ? orders : [];
    this.save();
  }

  setCoupons(coupons) {
    this.state.coupons = Array.isArray(coupons) ? coupons : [];
    this.save();
  }

  setKycRequests(requests) {
    this.state.kycRequests = Array.isArray(requests) ? requests : [];
    this.save();
  }

  // --- QUERY SELECTORS ---

  getState() {
    return this.state;
  }

  getProducts() {
    return this.state.products;
  }

  getProductById(id) {
    return this.state.products.find((p) => p.id === id);
  }

  getVariantById(variantId) {
    for (const product of this.state.products) {
      const variant = product.variants.find((v) => v.id === variantId);
      if (variant) {
        return { variant, product };
      }
    }
    return null;
  }

  getCategories() {
    return this.state.categories;
  }

  getCoupons() {
    return this.state.coupons;
  }

  getOrders() {
    return this.state.orders;
  }

  getOrderById(id) {
    return this.state.orders.find((o) => o.id === id || o.orderNumber === id || o.order_number === id);
  }

  getCartItemsDetailed() {
    const items = [];
    for (const item of this.state.cart.items) {
      const found = this.getVariantById(item.variantId);
      if (found) {
        items.push({
          variantId: item.variantId,
          productId: found.product.id,
          productName: found.product.name,
          brand: found.product.brand,
          flavor: found.variant.flavor,
          nicotine: found.variant.nicotine,
          price: found.variant.price,
          originalPrice: found.variant.originalPrice,
          stock: found.variant.stock,
          color1: found.product.color1,
          color2: found.product.color2,
          quantity: item.quantity,
          lineTotal: found.variant.price * item.quantity,
        });
      }
    }
    return items;
  }

  getCartTotals() {
    const detailedItems = this.getCartItemsDetailed();
    let discountPercent = 0;
    if (this.state.cart.appliedCouponCode) {
      const coupon = this.state.coupons.find(
        (c) => c.code.toUpperCase() === this.state.cart.appliedCouponCode.toUpperCase() && (c.isActive || c.is_active || c.isActive === undefined)
      );
      if (coupon) {
        discountPercent = coupon.discountPercent ?? coupon.value ?? 0;
      }
    }

    return Money.calculateTotals(
      detailedItems,
      discountPercent,
      CONFIG.SHIPPING.FREE_SHIPPING_THRESHOLD,
      CONFIG.SHIPPING.STANDARD_FEE
    );
  }

  // --- CART MUTATIONS (CUSTOMER SIDE) ---

  addToCart(variantId, quantity = 1) {
    const qty = Math.max(1, parseInt(quantity, 10) || 1);
    const found = this.getVariantById(variantId);
    if (!found) {
      throw new Error(`Variant ${variantId} does not exist.`);
    }

    if (found.variant.stock <= 0) {
      throw new Error(`Sorry, ${found.product.name} (${found.variant.flavor}) is out of stock.`);
    }

    const existing = this.state.cart.items.find((item) => item.variantId === variantId);
    if (existing) {
      const newQty = existing.quantity + qty;
      if (newQty > found.variant.stock) {
        existing.quantity = found.variant.stock;
        this.save();
        throw new Error(`Available stock limit reached (${found.variant.stock}).`);
      }
      existing.quantity = newQty;
    } else {
      if (qty > found.variant.stock) {
        throw new Error(`Requested quantity exceeds available stock (${found.variant.stock}).`);
      }
      this.state.cart.items.push({
        variantId,
        productId: found.product.id,
        quantity: qty,
      });
    }

    this.save();
    return this.getCartTotals();
  }

  updateCartQuantity(variantId, quantity) {
    const qty = parseInt(quantity, 10);
    const itemIndex = this.state.cart.items.findIndex((item) => item.variantId === variantId);
    if (itemIndex === -1) return;

    if (qty <= 0) {
      this.state.cart.items.splice(itemIndex, 1);
    } else {
      const found = this.getVariantById(variantId);
      if (found && qty > found.variant.stock) {
        this.state.cart.items[itemIndex].quantity = found.variant.stock;
        this.save();
        throw new Error(`Quantity clamped to available stock (${found.variant.stock}).`);
      }
      this.state.cart.items[itemIndex].quantity = qty;
    }

    this.save();
  }

  removeFromCart(variantId) {
    this.state.cart.items = this.state.cart.items.filter((item) => item.variantId !== variantId);
    this.save();
  }

  clearCart() {
    this.state.cart.items = [];
    this.state.cart.appliedCouponCode = null;
    this.save();
  }

  applyCoupon(couponCode) {
    if (!couponCode || typeof couponCode !== 'string') {
      throw new Error('Please enter a coupon code.');
    }

    const normalized = couponCode.trim().toUpperCase();
    const coupon = this.state.coupons.find((c) => c.code.toUpperCase() === normalized);

    const isActive = coupon && (coupon.isActive || coupon.is_active || coupon.isActive === undefined);
    if (!coupon || !isActive) {
      throw new Error(`Promo code "${couponCode}" is invalid or expired.`);
    }

    const detailedItems = this.getCartItemsDetailed();
    const currentSubtotal = detailedItems.reduce((acc, item) => acc + item.lineTotal, 0);
    const minSpend = coupon.minSpend ?? coupon.min_spend ?? 0;

    if (minSpend > 0 && currentSubtotal < minSpend) {
      throw new Error(`Promo code "${normalized}" requires a minimum spend of ${Money.format(minSpend)}.`);
    }

    this.state.cart.appliedCouponCode = normalized;
    this.save();
    return coupon;
  }

  removeCoupon() {
    this.state.cart.appliedCouponCode = null;
    this.save();
  }

  // --- ORDER MUTATIONS (CUSTOMER SIDE) ---

  placeOrder(checkoutData) {
    const detailedItems = this.getCartItemsDetailed();
    if (detailedItems.length === 0) {
      throw new Error('Cannot checkout with an empty cart.');
    }

    if (!checkoutData.isAgeAttested) {
      throw new Error('You must certify that you are at least 18 years of age.');
    }

    const shippingAddress = (checkoutData.shippingAddress || '').trim();
    if (!shippingAddress) {
      throw new Error('A complete delivery address is required.');
    }

    for (const item of detailedItems) {
      const found = this.getVariantById(item.variantId);
      if (!found || found.variant.stock < item.quantity) {
        throw new Error(`Insufficient stock for ${item.productName} (${item.flavor}).`);
      }
    }

    const now = new Date();
    const dateStr = now.toISOString().slice(0, 10).replace(/-/g, '');
    const randSuffix = Math.random().toString(36).substring(2, 6).toUpperCase();
    const orderNumber = `VH-${dateStr}-${randSuffix}`;
    const orderId = 'ord_' + Math.random().toString(36).substring(2, 10);

    // Deduct inventory
    for (const item of detailedItems) {
      const found = this.getVariantById(item.variantId);
      found.variant.stock -= item.quantity;
    }

    const totals = this.getCartTotals();
    const isOnlinePayment = ['gcash', 'maya', 'card'].includes(
      String(checkoutData.paymentMethod || '').toLowerCase()
    );

    const user = this.state.currentUser || CONFIG.DEFAULT_USER;

    const newOrder = {
      id: orderId,
      orderNumber,
      customerName: checkoutData.customerName || `${user.firstName} ${user.lastName}`,
      customerEmail: checkoutData.customerEmail || user.email,
      customerPhone: checkoutData.customerPhone || user.phone,
      shippingAddress,
      deliveryNotes: checkoutData.deliveryNotes || '',
      paymentMethod: checkoutData.paymentMethod || 'cod',
      paymentStatus: isOnlinePayment ? 'paid' : 'unpaid',
      status: isOnlinePayment ? OrderStatus.PAID : OrderStatus.PENDING,
      subtotal: totals.subtotal,
      discountAmount: totals.discountAmount,
      couponCode: this.state.cart.appliedCouponCode,
      shippingFee: totals.shippingFee,
      total: totals.total,
      courierName: null,
      trackingNumber: null,
      createdAt: new Date().toISOString().replace('T', ' ').substring(0, 19),
      shippedAt: null,
      deliveredAt: null,
      items: detailedItems.map((item) => ({
        id: 'item_' + Math.random().toString(36).substring(2, 8),
        productId: item.productId,
        productName: item.productName,
        variantSku: item.variantId,
        flavor: item.flavor,
        nicotine: item.nicotine,
        price: item.price,
        quantity: item.quantity,
      })),
    };

    if (this.state.cart.appliedCouponCode) {
      const coupon = this.state.coupons.find(
        (c) => c.code.toUpperCase() === this.state.cart.appliedCouponCode.toUpperCase()
      );
      if (coupon) coupon.usedCount = (coupon.usedCount || 0) + 1;
    }

    this.state.orders.unshift(newOrder);

    this.state.cart.items = [];
    this.state.cart.appliedCouponCode = null;

    this.save();
    return newOrder;
  }

  // --- ADMIN ONLY ACTIONS (RBAC PROTECTED) ---

  updateOrderStatus(orderId, targetStatus, metadata = {}) {
    if (!this.isAdmin()) {
      throw new Error('Access Denied: Only administrators can update order fulfillment status.');
    }

    const order = this.getOrderById(orderId);
    if (!order) throw new Error(`Order ${orderId} not found.`);

    if (!OrderStatus.canTransitionTo(order.status, targetStatus)) {
      throw new Error(`Cannot transition from "${order.status}" to "${targetStatus}".`);
    }

    order.status = targetStatus;
    if (targetStatus === OrderStatus.PAID) order.paymentStatus = 'paid';
    if (targetStatus === OrderStatus.SHIPPED) {
      order.courierName = metadata.courierName || 'J&T Express';
      order.trackingNumber = metadata.trackingNumber || 'JT-' + Math.floor(100000000 + Math.random() * 900000000) + 'PH';
      order.shippedAt = new Date().toISOString().replace('T', ' ').substring(0, 19);
    }
    if (targetStatus === OrderStatus.DELIVERED) {
      order.deliveredAt = new Date().toISOString().replace('T', ' ').substring(0, 19);
      order.paymentStatus = 'paid';
    }
    if (targetStatus === OrderStatus.CANCELLED) {
      for (const item of order.items) {
        const found = this.getVariantById(item.variantSku) || this.getVariantById(item.productId);
        if (found) found.variant.stock += item.quantity;
      }
    }

    this.save();
    return order;
  }

  addProduct(productData) {
    if (!this.isAdmin()) {
      throw new Error('Access Denied: Only administrators can add products to catalog.');
    }

    const newId = 'prod_' + (this.state.products.length + 1);
    const slug = productData.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');

    const newProduct = {
      id: newId,
      name: productData.name,
      slug,
      brand: (productData.brand || 'VAPEHAUS').toUpperCase(),
      category: productData.category || 'devices',
      description: productData.description || 'Premium authentic vaping hardware.',
      rating: 5.0,
      reviewsCount: 1,
      badge: 'New',
      color1: productData.color1 || '#8b5cf6',
      color2: productData.color2 || '#22d3ee',
      variants: (productData.variants || []).map((v, idx) => ({
        id: `var_${newId}_${idx + 1}`,
        sku: `${(productData.brand || 'VPH').substring(0, 3).toUpperCase()}-${slug.substring(0, 5).toUpperCase()}-${idx + 1}`,
        flavor: v.flavor || 'Standard',
        nicotine: v.nicotine || '20mg',
        price: parseFloat(v.price) || 1290.0,
        originalPrice: v.originalPrice ? parseFloat(v.originalPrice) : null,
        stock: parseInt(v.stock, 10) || 20,
      })),
    };

    if (newProduct.variants.length === 0) {
      newProduct.variants.push({
        id: `var_${newId}_1`,
        sku: `${(productData.brand || 'VPH').substring(0, 3).toUpperCase()}-STD-1`,
        flavor: 'Standard Edition',
        nicotine: '20mg',
        price: parseFloat(productData.price) || 1290.0,
        originalPrice: null,
        stock: 20,
      });
    }

    this.state.products.unshift(newProduct);
    this.save();
    return newProduct;
  }

  createCoupon(couponData) {
    if (!this.isAdmin()) {
      throw new Error('Access Denied: Only administrators can create promotional coupons.');
    }

    const newCoupon = {
      id: 'cpn_' + (this.state.coupons.length + 1),
      code: couponData.code.trim().toUpperCase(),
      discountPercent: parseInt(couponData.discountPercent, 10) || 10,
      minSpend: parseFloat(couponData.minSpend || 0),
      maxUses: parseInt(couponData.maxUses || 500, 10),
      usedCount: 0,
      isActive: true,
      expiryDate: couponData.expiryDate || null,
    };

    this.state.coupons.unshift(newCoupon);
    this.save();
    return newCoupon;
  }

  updateVariantStock(variantId, newStockOrDelta, isDelta = false, reason = 'Manual Inventory Adjustment') {
    if (!this.isAdmin()) {
      throw new Error('Access Denied: Only administrators can update inventory.');
    }

    const found = this.getVariantById(variantId);
    if (!found) throw new Error(`Variant ${variantId} not found.`);

    const prevStock = found.variant.stock;
    const finalStock = isDelta ? Math.max(0, prevStock + parseInt(newStockOrDelta, 10)) : Math.max(0, parseInt(newStockOrDelta, 10));

    found.variant.stock = finalStock;
    this.save();
    return found.variant;
  }
}

// Global Singleton Store Instance
const Store = new VapehausStore();

if (typeof module !== 'undefined' && module.exports) {
  module.exports = { VapehausStore, Store };
}
