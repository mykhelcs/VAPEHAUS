/**
 * Tests for Vapehaus Reactive State Store O(1) Indexing
 */

const assert = require('assert');

// Mock localStorage and CONFIG for node environment
global.localStorage = {
  _store: {},
  getItem(k) { return this._store[k] || null; },
  setItem(k, v) { this._store[k] = String(v); },
  removeItem(k) { delete this._store[k]; },
  clear() { this._store = {}; },
};

global.CONFIG = {
  STORAGE_KEYS: {
    STATE: 'TEST_STORE_STATE',
    SESSION_TOKEN: 'TEST_SESSION_TOKEN',
  },
  SHIPPING: {
    FREE_SHIPPING_THRESHOLD: 1500,
    STANDARD_FEE: 150,
  },
};

global.Money = {
  calculateTotals() { return { total: 0 }; },
  format(v) { return '₱' + Number(v).toFixed(2); },
};

global.OrderStatus = {
  PENDING: 'pending',
  PAID: 'paid',
  PROCESSING: 'processing',
  SHIPPED: 'shipped',
  DELIVERED: 'delivered',
  CANCELLED: 'cancelled',
  canTransitionTo() { return true; },
};

const { VapehausStore } = require('../../public/js/store/state.js');

function runTests() {
  const store = new VapehausStore();

  // Set as admin to perform catalog mutations
  store.state.currentUser = { id: 'usr_admin', email: 'admin@vapehaus.ph', role: 'admin' };

  // 1. Test addProduct O(1) indexing
  const added = store.addProduct({
    name: 'Aegis Legend 3',
    brand: 'Geekvape',
    category: 'devices',
    price: 2950,
    imageUrl: 'https://xpegspccnihndttvhfna.supabase.co/storage/v1/object/public/products/prod_l3.webp',
    variants: [
      { id: 'var_test_1', flavor: 'Titanium', price: 2950, stock: 20 },
      { id: 'var_test_2', flavor: 'Gunmetal', price: 2950, stock: 15 },
    ],
  });

  assert.strictEqual(added.name, 'Aegis Legend 3');
  assert.strictEqual(added.slug, 'aegis-legend-3');

  // 2. Test getProductById is O(1) from Map
  const byId = store.getProductById(added.id);
  assert.strictEqual(byId.id, added.id);
  assert.strictEqual(byId.brand, 'GEEKVAPE');

  // 3. Test getProductBySlug is O(1) from Map
  const bySlug = store.getProductBySlug('aegis-legend-3');
  assert.strictEqual(bySlug.id, added.id);

  // 4. Test getVariantById is O(1) from Map
  const variantFound1 = store.getVariantById('var_test_1');
  assert.ok(variantFound1 !== null);
  assert.strictEqual(variantFound1.variant.flavor, 'Titanium');
  assert.strictEqual(variantFound1.product.name, 'Aegis Legend 3');

  const variantFound2 = store.getVariantById('var_test_2');
  assert.ok(variantFound2 !== null);
  assert.strictEqual(variantFound2.variant.flavor, 'Gunmetal');

  // 5. Non-existent returns null
  assert.strictEqual(store.getProductById('prod_does_not_exist'), null);
  assert.strictEqual(store.getVariantById('var_does_not_exist'), null);

  // 6. Test deleteProduct removes from O(1) indices
  store.deleteProduct(added.id);
  assert.strictEqual(store.getProductById(added.id), null);
  assert.strictEqual(store.getProductBySlug('aegis-legend-3'), null);
  assert.strictEqual(store.getVariantById('var_test_1'), null);

  console.log('✓ All VapehausStore O(1) State tests passed successfully!');
}

runTests();
