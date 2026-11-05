/**
 * TDD Suite: Product Details Modal, COD Order Sync & Tracking, and Profile Exit Button
 */

const assert = require('assert');

// Minimal deterministic DOM mock for component tests
function createMockElement(id = '') {
  const listeners = {};
  const classes = new Set();
  return {
    id,
    innerHTML: '',
    textContent: '',
    value: '',
    disabled: false,
    style: {},
    classList: {
      add(c) { classes.add(c); },
      remove(c) { classes.delete(c); },
      toggle(c, force) {
        if (force === undefined) {
          if (classes.has(c)) { classes.delete(c); return false; }
          classes.add(c); return true;
        }
        if (force) classes.add(c); else classes.delete(c);
        return force;
      },
      contains(c) { return classes.has(c); },
    },
    addEventListener(type, fn) {
      if (!listeners[type]) listeners[type] = [];
      listeners[type].push(fn);
    },
    dispatchEvent(type, eventObj = {}) {
      (listeners[type] || []).forEach((fn) => fn({ target: this, ...eventObj }));
    },
    querySelector(sel) {
      if (sel === '.modal-close-btn') return global._dom['btn-close-pdp'] || null;
      return null;
    },
  };
}

global._dom = {};
global.document = {
  getElementById(id) {
    return global._dom[id] || null;
  },
  querySelectorAll() {
    return [];
  },
  addEventListener(type, fn) {
    if (!global._docListeners) global._docListeners = {};
    if (!global._docListeners[type]) global._docListeners[type] = [];
    global._docListeners[type].push(fn);
  },
};

global.window = {
  location: { origin: 'http://localhost:8000', pathname: '/index.html', search: '' },
  history: { replaceState() {} },
};

global.localStorage = {
  _store: {},
  getItem(k) { return this._store[k] || null; },
  setItem(k, v) { this._store[k] = String(v); },
  removeItem(k) { delete this._store[k]; },
  clear() { this._store = {}; },
};

global.CONFIG = {
  API_BASE_URL: 'http://localhost:8000/api/v1',
  ENABLE_LOCAL_FALLBACK: false,
  STORAGE_KEYS: {
    STATE: 'TEST_STORE_STATE',
    SESSION_TOKEN: 'TEST_SESSION_TOKEN',
  },
  SHIPPING: {
    FREE_SHIPPING_THRESHOLD: 1500,
    STANDARD_FEE: 150,
  },
};

global.Toast = {
  messages: [],
  success(m) { this.messages.push({ type: 'success', m }); },
  error(m) { this.messages.push({ type: 'error', m }); },
  info(m) { this.messages.push({ type: 'info', m }); },
};

global.Money = require('../../public/js/domain/money.js');
global.OrderStatus = require('../../public/js/domain/order-status.js');

const { VapehausStore } = require('../../public/js/store/state.js');
global.Store = new VapehausStore();

const { VapehausApiClient } = require('../../public/js/api/client.js');
global.Api = new VapehausApiClient();

const QuickViewModal = require('../../public/js/components/quick-view.js');
global.QuickViewModal = QuickViewModal;

const OrderTrackerModal = require('../../public/js/components/order-tracker.js');
global.OrderTrackerModal = OrderTrackerModal;

const CheckoutModal = require('../../public/js/components/checkout.js');
global.CheckoutModal = CheckoutModal;

const { StorefrontApp } = require('../../public/js/storefront.js');
global.StorefrontApp = StorefrontApp;

async function runTests() {
  // ── TEST 1: Store.upsertOrder works for regular customers & COD server orders ──
  global.Store.state.currentUser = {
    id: 'usr_cust_1',
    email: 'customer@vapehaus.ph',
    firstName: 'Juan',
    lastName: 'Cruz',
    role: 'customer',
    isAgeVerified: true,
  };
  global.Store.setOrders([]);

  assert.strictEqual(typeof global.Store.upsertOrder, 'function', 'Store.upsertOrder must be defined');

  const serverCodOrder = {
    id: 'ord_cod_999',
    orderNumber: 'VH-20261105-9999',
    userId: 'usr_cust_1',
    status: 'pending',
    subtotal: 1250,
    shippingFee: 150,
    total: 1400,
    shippingAddress: '123 Makati Ave',
    paymentMethod: 'cod',
    paymentStatus: 'unpaid',
    createdAt: '2026-11-05 11:00:00',
    items: [
      { variantId: 'var_1', name: 'OXVA Xlim Pro', flavor: 'Black', nicotine: '20mg', quantity: 1, totalPrice: 1250 },
    ],
  };

  // Mock fetch for POST /checkout and GET /orders/ord_cod_999 and GET /orders
  global.fetch = async (url, opts) => {
    if (url.endsWith('/checkout') && opts.method === 'POST') {
      return {
        status: 201,
        json: async () => ({ success: true, data: serverCodOrder, meta: {} }),
      };
    }
    if (url.endsWith('/orders/ord_cod_999') && opts.method === 'GET') {
      return {
        status: 200,
        json: async () => ({
          success: true,
          data: { ...serverCodOrder, status: 'processing' },
          meta: {},
        }),
      };
    }
    if (url.includes('/orders') && opts.method === 'GET') {
      return {
        status: 200,
        json: async () => ({
          success: true,
          data: [{ ...serverCodOrder, status: 'processing' }],
          meta: { total: 1 },
        }),
      };
    }
    if (url.includes('/reviews') && opts.method === 'GET') {
      return {
        status: 200,
        json: async () => ({
          success: true,
          data: [{ id: 'rev_1', rating: 5, title: 'Great pod!', comment: 'Smooth draw and zero leaks.' }],
          meta: {},
        }),
      };
    }
    return {
      status: 200,
      json: async () => ({ success: true, data: [], meta: {} }),
    };
  };

  // ── TEST 2: Api.checkout immediately syncs COD order into Store (My Orders > 0) ──
  const checkoutRes = await global.Api.checkout({
    customerName: 'Juan Cruz',
    customerEmail: 'customer@vapehaus.ph',
    paymentMethod: 'cod',
    isAgeAttested: true,
    items: [{ variantId: 'var_1', quantity: 1 }],
  });

  assert.strictEqual(checkoutRes.success, true);
  assert.strictEqual(global.Store.getOrders().length, 1, 'COD order must be stored in Store.getOrders()');
  assert.strictEqual(global.Store.getOrderById('ord_cod_999').orderNumber, 'VH-20261105-9999');

  // ── TEST 3: OrderTrackerModal renders COD order without "Order not found." ──
  global._dom['order-tracker-modal'] = createMockElement('order-tracker-modal');
  global._dom['btn-close-tracker'] = createMockElement('btn-close-tracker');
  global._dom['order-tracker-content'] = createMockElement('order-tracker-content');

  OrderTrackerModal.init();
  OrderTrackerModal.showOrder('ord_cod_999', serverCodOrder);
  OrderTrackerModal.stopPolling();

  assert.ok(
    !global._dom['order-tracker-content'].innerHTML.includes('Order not found'),
    'OrderTrackerModal must NOT display "Order not found." after COD confirmation'
  );
  assert.ok(
    global._dom['order-tracker-content'].innerHTML.includes('VH-20261105-9999'),
    'OrderTrackerModal must display the confirmed COD order number'
  );

  // Also test fetchLatestOrder() for non-admin customer updates status without RBAC error
  await OrderTrackerModal.fetchLatestOrder();
  assert.strictEqual(
    global.Store.getOrderById('ord_cod_999').status,
    'processing',
    'Non-admin customer live order polling must update order status in Store'
  );

  // ── TEST 4: Profile Modal Exit Button works WITHOUT #btn-account-toggle & syncs My Orders ──
  global._dom['account-modal'] = createMockElement('account-modal');
  global._dom['btn-close-account'] = createMockElement('btn-close-account');
  global._dom['account-modal-content'] = createMockElement('account-modal-content');
  delete global._dom['btn-account-toggle']; // Matches real index.html!

  StorefrontApp.setupAccountModal();
  await StorefrontApp.openAccountModal();

  assert.strictEqual(global._dom['account-modal'].classList.contains('open'), true, 'Account modal should open');
  assert.ok(
    global._dom['account-modal-content'].innerHTML.includes('My Orders (1)'),
    'Account modal must display My Orders (1) instead of My Orders (0)'
  );

  // Click exit button on profile modal
  global._dom['btn-close-account'].dispatchEvent('click');
  assert.strictEqual(
    global._dom['account-modal'].classList.contains('open'),
    false,
    'Clicking #btn-close-account exit button must close #account-modal'
  );

  // ── TEST 5: Product Details Modal & Product Card "View Details" Button ──
  global.Store.setProducts([
    {
      id: 'prod_xlim_1',
      name: 'OXVA Xlim Pro',
      slug: 'oxva-xlim-pro',
      brand: 'OXVA',
      category: 'pod-systems',
      description: null, // Verify null description does NOT render literal "null"
      variants: [
        { id: 'var_xlim_blk', sku: 'OXVA-BLK-20', flavor: 'Gleamy Black', nicotine: '20mg', price: 1250, stock: 15 },
      ],
    },
  ]);

  global._dom['products-grid-container'] = createMockElement('products-grid-container');
  global._dom['products-count-badge'] = createMockElement('products-count-badge');
  global._dom['pdp-modal'] = createMockElement('pdp-modal');
  global._dom['btn-close-pdp'] = createMockElement('btn-close-pdp');
  global._dom['pdp-modal-content'] = createMockElement('pdp-modal-content');

  StorefrontApp.renderProducts();
  assert.ok(
    global._dom['products-grid-container'].innerHTML.includes('View Details'),
    'Product card must include a visible "View Details" trigger for the Product Details modal'
  );

  QuickViewModal.init();
  await QuickViewModal.open('prod_xlim_1');

  assert.strictEqual(global._dom['pdp-modal'].classList.contains('open'), true, 'Product details modal must open');
  const pdpHtml = global._dom['pdp-modal-content'].innerHTML;
  assert.ok(pdpHtml.includes('OXVA Xlim Pro'), 'Modal must render product title');
  assert.ok(pdpHtml.includes('OXVA-BLK-20'), 'Modal must render variant SKU');
  assert.ok(!pdpHtml.includes('>null<') && !pdpHtml.includes('\n            null\n'), 'Modal must not render literal "null" when description is null');
  assert.ok(pdpHtml.includes('Specifications') || pdpHtml.includes('pdp-specs'), 'Modal must render product specifications section');

  global._dom['btn-close-pdp'].dispatchEvent('click');
  assert.strictEqual(global._dom['pdp-modal'].classList.contains('open'), false, 'Product details modal close button must close modal');

  console.log('✓ All Storefront COD, Profile Exit, My Orders & Product Details Modal tests passed!');
}

runTests().catch((err) => {
  console.error('✗ Test failed:', err.message);
  process.exit(1);
});
