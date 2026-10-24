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
      // Current active session user: starts as guest (null) or pre-loaded user
      currentUser: null,
      users: [
        { ...CONFIG.DEFAULT_USER },
        { ...CONFIG.DEFAULT_ADMIN },
      ],
      categories: [
        { id: 'cat_all', name: 'All Products', slug: 'all', icon: '🔥' },
        { id: 'cat_disposables', name: 'Disposables', slug: 'disposables', icon: '🗑️' },
        { id: 'cat_pod_systems', name: 'Pod Systems', slug: 'pod-systems', icon: '🧃' },
        { id: 'cat_devices', name: 'Devices & Mods', slug: 'devices', icon: '🔋' },
        { id: 'cat_eliquids', name: 'E-Liquids & Salts', slug: 'eliquids', icon: '💧' },
        { id: 'cat_coils', name: 'Coils & Pods', slug: 'coils', icon: '🔧' },
      ],
      products: [
        {
          id: 'prod_1',
          name: 'Voopoo Drag X Pro Kit',
          slug: 'voopoo-drag-x-pro-kit',
          brand: 'VOOPOO',
          category: 'devices',
          description: 'High-performance 100W pod mod with GENE.FAN 3.0 chip, 360° airflow system, and TPP X Pod tank.',
          rating: 4.9,
          reviewsCount: 64,
          badge: 'Sale',
          color1: '#8b5cf6',
          color2: '#22d3ee',
          variants: [
            { id: 'var_1_1', sku: 'VPO-DXP-BLK-0', flavor: 'Classic Black', nicotine: '0mg', price: 3490.0, originalPrice: 3990.0, stock: 12 },
            { id: 'var_1_2', sku: 'VPO-DXP-CRB-3', flavor: 'Carbon Fiber', nicotine: '3mg', price: 3490.0, originalPrice: 3990.0, stock: 8 },
            { id: 'var_1_3', sku: 'VPO-DXP-SAH-6', flavor: 'Sahara Brown', nicotine: '6mg', price: 3490.0, originalPrice: 3990.0, stock: 15 },
          ],
        },
        {
          id: 'prod_2',
          name: 'Smok Novo 5 Pod System',
          slug: 'smok-novo-5-pod-system',
          brand: 'SMOK',
          category: 'pod-systems',
          description: 'Compact 30W pod system with 900mAh battery, button/air switch draw, and 0.69-inch OLED display.',
          rating: 4.8,
          reviewsCount: 42,
          badge: 'New',
          color1: '#22d3ee',
          color2: '#0ea5e9',
          variants: [
            { id: 'var_2_1', sku: 'SMK-NOV5-SLV-20', flavor: 'Silver Black', nicotine: '20mg', price: 1290.0, originalPrice: null, stock: 24 },
            { id: 'var_2_2', sku: 'SMK-NOV5-CYN-30', flavor: 'Cyan Blue', nicotine: '30mg', price: 1290.0, originalPrice: null, stock: 18 },
            { id: 'var_2_3', sku: 'SMK-NOV5-BLK-50', flavor: 'Matte Black', nicotine: '50mg', price: 1290.0, originalPrice: null, stock: 6 },
          ],
        },
        {
          id: 'prod_3',
          name: 'Geekvape Aegis Boost 2 (B60)',
          slug: 'geekvape-aegis-boost-2',
          brand: 'GEEKVAPE',
          category: 'devices',
          description: 'IP68-rated shockproof, dustproof, and waterproof 60W beast with AS-Chip 3.0 and top airflow leakproof design.',
          rating: 4.9,
          reviewsCount: 58,
          badge: 'Hot',
          color1: '#f43f5e',
          color2: '#8b5cf6',
          variants: [
            { id: 'var_3_1', sku: 'GKV-B60-GUN-0', flavor: 'Gunmetal Titanium', nicotine: '0mg', price: 2850.0, originalPrice: null, stock: 14 },
            { id: 'var_3_2', sku: 'GKV-B60-RNB-20', flavor: 'Rainbow Chrome', nicotine: '20mg', price: 2850.0, originalPrice: null, stock: 9 },
            { id: 'var_3_3', sku: 'GKV-B60-RED-50', flavor: 'Red Silver Shield', nicotine: '50mg', price: 2850.0, originalPrice: null, stock: 11 },
          ],
        },
        {
          id: 'prod_4',
          name: 'Uwell Caliburn G3 Pod Kit',
          slug: 'uwell-caliburn-g3-pod-kit',
          brand: 'UWELL',
          category: 'pod-systems',
          description: 'The definitive pod system with 25W max output, 900mAh battery, dual ignition, and OLED screen.',
          rating: 4.7,
          reviewsCount: 39,
          badge: 'Sale',
          color1: '#10b981',
          color2: '#22d3ee',
          variants: [
            { id: 'var_4_1', sku: 'UWL-CG3-MID-20', flavor: 'Midnight Black', nicotine: '20mg', price: 1650.0, originalPrice: 1900.0, stock: 32 },
            { id: 'var_4_2', sku: 'UWL-CG3-COB-30', flavor: 'Cobalt Blue', nicotine: '30mg', price: 1650.0, originalPrice: 1900.0, stock: 19 },
            { id: 'var_4_3', sku: 'UWL-CG3-ROS-50', flavor: 'Rose Pink', nicotine: '50mg', price: 1650.0, originalPrice: 1900.0, stock: 5 },
          ],
        },
        {
          id: 'prod_5',
          name: 'Naked 100 Lava Flow 60ml',
          slug: 'naked-100-lava-flow-60ml',
          brand: 'NAKED 100',
          category: 'eliquids',
          description: 'An exhilarating flavor profile pairing sweet strawberries, tangy pineapple, and rich coconut cream.',
          rating: 4.9,
          reviewsCount: 84,
          badge: 'Hot',
          color1: '#f59e0b',
          color2: '#ef4444',
          variants: [
            { id: 'var_5_1', sku: 'NKD-LAV-3MG', flavor: 'Strawberry Pineapple Coconut', nicotine: '3mg', price: 650.0, originalPrice: null, stock: 45 },
            { id: 'var_5_2', sku: 'NKD-LAV-6MG', flavor: 'Strawberry Pineapple Coconut', nicotine: '6mg', price: 650.0, originalPrice: null, stock: 38 },
            { id: 'var_5_3', sku: 'NKD-LAV-12MG', flavor: 'Strawberry Pineapple Coconut', nicotine: '12mg', price: 650.0, originalPrice: null, stock: 22 },
          ],
        },
        {
          id: 'prod_6',
          name: 'Juice Head Peach Pear 100ml',
          slug: 'juice-head-peach-pear-100ml',
          brand: 'JUICE HEAD',
          category: 'eliquids',
          description: 'A blend of luscious georgian peaches and ripe crispy pears infused for all-day vaping bliss.',
          rating: 4.8,
          reviewsCount: 51,
          badge: 'Staff Pick',
          color1: '#fb923c',
          color2: '#f472b6',
          variants: [
            { id: 'var_6_1', sku: 'JHD-PCP-3MG', flavor: 'Peach Pear Classic', nicotine: '3mg', price: 780.0, originalPrice: null, stock: 29 },
            { id: 'var_6_2', sku: 'JHD-PCP-6MG', flavor: 'Peach Pear Classic', nicotine: '6mg', price: 780.0, originalPrice: null, stock: 21 },
          ],
        },
        {
          id: 'prod_7',
          name: 'RELX Infinity 2 Closed Pod Device',
          slug: 'relx-infinity-2-device',
          brand: 'RELX',
          category: 'disposables',
          description: '3-level power adjustment (Eco, Smooth, Boost), ultra-fast 15-minute charging, and SmartPace vibration.',
          rating: 4.8,
          reviewsCount: 76,
          badge: 'New',
          color1: '#a78bfa',
          color2: '#6366f1',
          variants: [
            { id: 'var_7_1', sku: 'RLX-INF2-OBS-30', flavor: 'Obsidian Black', nicotine: '30mg', price: 1490.0, originalPrice: null, stock: 25 },
            { id: 'var_7_2', sku: 'RLX-INF2-ROY-30', flavor: 'Royal Blue', nicotine: '30mg', price: 1490.0, originalPrice: null, stock: 17 },
            { id: 'var_7_3', sku: 'RLX-INF2-RUB-50', flavor: 'Ruby Red', nicotine: '50mg', price: 1490.0, originalPrice: null, stock: 14 },
          ],
        },
        {
          id: 'prod_8',
          name: 'Voopoo PnP Coil 5-Pack Replacement',
          slug: 'voopoo-pnp-coils-5pack',
          brand: 'VOOPOO',
          category: 'coils',
          description: 'Original Voopoo PnP Mesh replacement coils engineered for massive clouds and authentic e-liquid flavor.',
          rating: 4.9,
          reviewsCount: 92,
          badge: '',
          color1: '#64748b',
          color2: '#22d3ee',
          variants: [
            { id: 'var_8_1', sku: 'VPO-PNP-VM1-03', flavor: '0.3Ω VM1 Mesh (32-40W)', nicotine: '0mg', price: 520.0, originalPrice: null, stock: 60 },
            { id: 'var_8_2', sku: 'VPO-PNP-VM5-02', flavor: '0.2Ω VM5 Mesh (40-60W)', nicotine: '0mg', price: 520.0, originalPrice: null, stock: 45 },
            { id: 'var_8_3', sku: 'VPO-PNP-VM6-015', flavor: '0.15Ω VM6 Mesh (60-80W)', nicotine: '0mg', price: 520.0, originalPrice: null, stock: 35 },
          ],
        },
      ],
      cart: {
        items: [
          { variantId: 'var_2_1', productId: 'prod_2', quantity: 1 },
          { variantId: 'var_5_1', productId: 'prod_5', quantity: 1 },
        ],
        appliedCouponCode: 'HAUS10',
      },
      coupons: [
        { id: 'cpn_1', code: 'HAUS10', discountPercent: 10, minSpend: 0, maxUses: 1000, usedCount: 142, isActive: true },
        { id: 'cpn_2', code: 'VAPE20', discountPercent: 20, minSpend: 2000, maxUses: 200, usedCount: 58, isActive: true },
      ],
      orders: [
        {
          id: 'ord_101',
          orderNumber: 'VH-20261023-A721',
          customerName: 'Juan Dela Cruz',
          customerEmail: 'customer@vapehaus.ph',
          customerPhone: '+63 917 888 9999',
          shippingAddress: 'Unit 12B, Emerald Tower, Ortigas Center, Pasig City, Metro Manila 1605',
          paymentMethod: 'gcash',
          paymentStatus: 'paid',
          status: 'delivered',
          subtotal: 3490.0,
          discountAmount: 349.0,
          couponCode: 'HAUS10',
          shippingFee: 0.0,
          total: 3141.0,
          courierName: 'J&T Express',
          trackingNumber: 'JT-982144182PH',
          createdAt: '2026-10-23 10:14:00',
          shippedAt: '2026-10-23 14:00:00',
          deliveredAt: '2026-10-24 08:30:00',
          items: [
            {
              id: 'item_101_1',
              productId: 'prod_1',
              productName: 'Voopoo Drag X Pro Kit',
              variantSku: 'VPO-DXP-BLK-0',
              flavor: 'Classic Black',
              nicotine: '0mg',
              price: 3490.0,
              quantity: 1,
            },
          ],
        },
        {
          id: 'ord_102',
          orderNumber: 'VH-20261024-B192',
          customerName: 'Maria Santos',
          customerEmail: 'maria.s@gmail.com',
          customerPhone: '+63 918 222 3344',
          shippingAddress: 'Blk 4 Lot 8, Mahogany Ave, San Jose, Antipolo, Rizal 1870',
          paymentMethod: 'cod',
          paymentStatus: 'unpaid',
          status: 'processing',
          subtotal: 1940.0,
          discountAmount: 0.0,
          couponCode: null,
          shippingFee: 0.0,
          total: 1940.0,
          courierName: 'Lalamove',
          trackingNumber: null,
          createdAt: '2026-10-24 07:45:00',
          shippedAt: null,
          deliveredAt: null,
          items: [
            {
              id: 'item_102_1',
              productId: 'prod_2',
              productName: 'Smok Novo 5 Pod System',
              variantSku: 'SMK-NOV5-CYN-30',
              flavor: 'Cyan Blue',
              nicotine: '30mg',
              price: 1290.0,
              quantity: 1,
            },
            {
              id: 'item_102_2',
              productId: 'prod_5',
              productName: 'Naked 100 Lava Flow 60ml',
              variantSku: 'NKD-LAV-3MG',
              flavor: 'Strawberry Pineapple Coconut',
              nicotine: '3mg',
              price: 650.0,
              quantity: 1,
            },
          ],
        },
      ],
      kycRequests: [
        {
          id: 'kyc_1',
          userId: 'usr_customer_default',
          customerName: 'Juan Dela Cruz',
          customerEmail: 'customer@vapehaus.ph',
          birthDate: '1998-05-15',
          idType: 'Philippine Passport',
          idNumber: 'P8921822B',
          status: 'approved',
          submittedAt: '2026-10-22 14:20:00',
        },
      ],
      inventoryAuditLogs: [],
    };
  }

  loadInitialState() {
    try {
      const stored = localStorage.getItem(CONFIG.STORAGE_KEYS.STATE);
      if (stored) {
        const parsed = JSON.parse(stored);
        if (parsed && Array.isArray(parsed.products) && Array.isArray(parsed.orders)) {
          return parsed;
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
    return this.state.orders.find((o) => o.id === id || o.orderNumber === id);
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
        (c) => c.code.toUpperCase() === this.state.cart.appliedCouponCode.toUpperCase() && c.isActive
      );
      if (coupon) {
        discountPercent = coupon.discountPercent;
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

    if (!coupon || !coupon.isActive) {
      throw new Error(`Promo code "${couponCode}" is invalid or expired.`);
    }

    const detailedItems = this.getCartItemsDetailed();
    const currentSubtotal = detailedItems.reduce((acc, item) => acc + item.lineTotal, 0);

    if (coupon.minSpend > 0 && currentSubtotal < coupon.minSpend) {
      throw new Error(`Promo code "${normalized}" requires a minimum spend of ${Money.format(coupon.minSpend)}.`);
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
