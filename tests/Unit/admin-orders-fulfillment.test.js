/**
 * FRONTEND TEST: Admin Orders & Fulfillment State Machine & DOM verification
 */

const fs = require('fs');
const path = require('path');
const assert = require('assert');

// 1. Verify admin.html does NOT contain the DB Ping component
const adminHtmlPath = path.resolve('public/admin.html');
const adminHtml = fs.readFileSync(adminHtmlPath, 'utf8');

assert.strictEqual(
  adminHtml.includes('id="admin-db-status"'),
  false,
  'FAIL: admin.html still contains id="admin-db-status"'
);
assert.strictEqual(
  adminHtml.includes('⚡ Ping'),
  false,
  'FAIL: admin.html still contains "⚡ Ping"'
);
assert.strictEqual(
  adminHtml.includes('onclick="AdminApp.testDatabaseConnection()"'),
  false,
  'FAIL: admin.html still contains onclick to testDatabaseConnection'
);
console.log('✓ PASS: admin.html DB Ping component is completely removed.');

// 2. Load order-status.js in sandbox
const orderStatusJs = fs.readFileSync(path.resolve('public/js/domain/order-status.js'), 'utf8');
const vm = require('vm');
const sandbox = { console };
vm.createContext(sandbox);
vm.runInContext(orderStatusJs + ';\nthis.OrderStatus = OrderStatus;', sandbox);
const OrderStatus = sandbox.OrderStatus;

assert(OrderStatus, 'OrderStatus object must exist');

// Test transitions
// Pending (e.g. COD orders)
assert.strictEqual(OrderStatus.canTransitionTo('pending', 'processing'), true, 'pending -> processing must be allowed');
assert.strictEqual(OrderStatus.canTransitionTo('pending', 'shipped'), true, 'pending -> shipped must be allowed');
assert.strictEqual(OrderStatus.canTransitionTo('pending', 'paid'), true, 'pending -> paid must be allowed');
assert.strictEqual(OrderStatus.canTransitionTo('pending', 'cancelled'), true, 'pending -> cancelled must be allowed');
assert.strictEqual(OrderStatus.canTransitionTo('pending', 'delivered'), false, 'pending -> delivered should not be allowed directly');

// Paid
assert.strictEqual(OrderStatus.canTransitionTo('paid', 'processing'), true, 'paid -> processing must be allowed');
assert.strictEqual(OrderStatus.canTransitionTo('paid', 'shipped'), true, 'paid -> shipped must be allowed');
assert.strictEqual(OrderStatus.canTransitionTo('paid', 'cancelled'), true, 'paid -> cancelled must be allowed');

// Processing
assert.strictEqual(OrderStatus.canTransitionTo('processing', 'shipped'), true, 'processing -> shipped must be allowed');
assert.strictEqual(OrderStatus.canTransitionTo('processing', 'delivered'), true, 'processing -> delivered must be allowed');
assert.strictEqual(OrderStatus.canTransitionTo('processing', 'cancelled'), true, 'processing -> cancelled must be allowed');

// Shipped
assert.strictEqual(OrderStatus.canTransitionTo('shipped', 'delivered'), true, 'shipped -> delivered must be allowed');
assert.strictEqual(OrderStatus.canTransitionTo('shipped', 'cancelled'), true, 'shipped -> cancelled must be allowed');

// Terminal states
assert.strictEqual(OrderStatus.canTransitionTo('delivered', 'shipped'), false, 'delivered cannot transition');
assert.strictEqual(OrderStatus.canTransitionTo('cancelled', 'processing'), false, 'cancelled cannot transition');

// Can cancel
assert.strictEqual(OrderStatus.canCancel('pending'), true, 'pending can be cancelled');
assert.strictEqual(OrderStatus.canCancel('paid'), true, 'paid can be cancelled');
assert.strictEqual(OrderStatus.canCancel('processing'), true, 'processing can be cancelled');
assert.strictEqual(OrderStatus.canCancel('shipped'), true, 'shipped can be cancelled');
assert.strictEqual(OrderStatus.canCancel('delivered'), false, 'delivered cannot be cancelled');
assert.strictEqual(OrderStatus.canCancel('cancelled'), false, 'cancelled cannot be cancelled');
console.log('✓ PASS: order-status.js transitions and cancellation invariants verified.');

// 3. Test Store loose ID matching
const stateCode = fs.readFileSync(path.resolve('public/js/store/state.js'), 'utf8');
const dummyStorage = {};
const mockLocalStorage = {
  getItem: (k) => dummyStorage[k] || null,
  setItem: (k, v) => { dummyStorage[k] = v; },
  removeItem: (k) => { delete dummyStorage[k]; }
};

const storeSandbox = {
  console,
  localStorage: mockLocalStorage,
  OrderStatus,
  CONFIG: { STORAGE_KEYS: { STORE_STATE: 'VAPEHAUS_STATE' } }
};
vm.createContext(storeSandbox);
vm.runInContext(stateCode + ';\nthis.Store = Store;', storeSandbox);
const Store = storeSandbox.Store;

// Ensure admin user is set
Store.state.currentUser = { id: 'admin_1', email: 'admin@vapehaus.ph', role: 'admin' };

// Insert test order with numeric ID
Store.state.orders = [
  {
    id: 101,
    orderNumber: 'VH-20261105-0101',
    customerName: 'Juan Dela Cruz',
    customerEmail: 'juan@example.ph',
    status: 'pending',
    paymentMethod: 'cod',
    paymentStatus: 'unpaid',
    items: [{ variantSku: 'POD-01-V', quantity: 1, price: 500 }]
  }
];

// Test getOrderById with string id '101'
const foundByString = Store.getOrderById('101');
assert(foundByString, 'Store.getOrderById("101") must resolve order with numeric id 101');
assert.strictEqual(foundByString.id, 101);

// Test getOrderById with orderNumber
const foundByNum = Store.getOrderById('VH-20261105-0101');
assert(foundByNum, 'Store.getOrderById("VH-20261105-0101") must resolve order by orderNumber');

// Test updateOrderStatus pending -> processing
const updatedProc = Store.updateOrderStatus('101', 'processing');
assert.strictEqual(updatedProc.status, 'processing');

// Test updateOrderStatus processing -> shipped
const updatedShip = Store.updateOrderStatus('101', 'shipped', { courierName: 'J&T Express', trackingNumber: 'JT-123456PH' });
assert.strictEqual(updatedShip.status, 'shipped');
assert.strictEqual(updatedShip.courierName, 'J&T Express');
assert.strictEqual(updatedShip.trackingNumber, 'JT-123456PH');

// Test updateOrderStatus shipped -> delivered
const updatedDeliv = Store.updateOrderStatus('101', 'delivered');
assert.strictEqual(updatedDeliv.status, 'delivered');
assert.strictEqual(updatedDeliv.paymentStatus, 'paid');

console.log('✓ PASS: Store order operations and loose ID resolution verified.');
console.log('\n============================================================');
console.log('ALL CLIENT-SIDE ADMIN ORDERS & FULFILLMENT TESTS PASSED!');
console.log('============================================================\n');
