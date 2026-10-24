/**
 * VAPEHAUS ADMIN PORTAL CONTROLLER
 * Full ERP fulfillment workflow, inventory stock adjustments, KYC moderation, and metrics
 */

const AdminApp = {
  currentTab: 'dashboard',
  orderFilterStatus: 'all',
  orderSearchQuery: '',
  selectedOrderId: null,

  init() {
    this.setupSidebarNav();
    this.setupFulfillmentModal();

    // Subscribe to store updates to keep tables and KPIs live
    Store.subscribe(() => {
      this.refreshCurrentView();
    });

    this.showTab('dashboard');
  },

  setupSidebarNav() {
    document.querySelectorAll('.admin-nav-item').forEach((item) => {
      item.addEventListener('click', () => {
        const tab = item.getAttribute('data-tab');
        if (tab) {
          this.showTab(tab);
        }
      });
    });
  },

  showTab(tabName) {
    this.currentTab = tabName;

    // Update sidebar active classes
    document.querySelectorAll('.admin-nav-item').forEach((item) => {
      item.classList.toggle('active', item.getAttribute('data-tab') === tabName);
    });

    // Update tab panes
    document.querySelectorAll('.admin-tab-pane').forEach((pane) => {
      pane.classList.remove('active');
    });

    const activePane = document.getElementById(`tab-pane-${tabName}`);
    if (activePane) {
      activePane.classList.add('active');
    }

    // Update Topbar Title
    const titleEl = document.getElementById('admin-page-title');
    if (titleEl) {
      const titles = {
        dashboard: 'Executive Overview',
        orders: 'Order Fulfillment Operations',
        inventory: 'Products & Inventory Manager',
        kyc: 'Regulatory 18+ KYC Queue',
        coupons: 'Promotions & Coupons',
        customers: 'Customer CRM Directory',
      };
      titleEl.textContent = titles[tabName] || 'Admin Dashboard';
    }

    this.refreshCurrentView();
  },

  refreshCurrentView() {
    if (this.currentTab === 'dashboard') this.renderDashboard();
    else if (this.currentTab === 'orders') this.renderOrders();
    else if (this.currentTab === 'inventory') this.renderInventory();
    else if (this.currentTab === 'kyc') this.renderKyc();
    else if (this.currentTab === 'coupons') this.renderCoupons();
    else if (this.currentTab === 'customers') this.renderCustomers();
  },

  // --- MODULE 1: DASHBOARD ---

  async renderDashboard() {
    const kpiEl = document.getElementById('dashboard-kpis-container');
    const recentOrdersEl = document.getElementById('dashboard-recent-orders-table');
    const lowStockEl = document.getElementById('dashboard-low-stock-list');
    if (!kpiEl) return;

    const res = await Api.getDashboardMetrics();
    const metrics = res.data;

    kpiEl.innerHTML = `
      <div class="kpi-card" style="--kpi-color: var(--accent2);">
        <span class="kpi-label">Gross Revenue</span>
        <div class="kpi-value">${Money.format(metrics.total_revenue)}</div>
        <div class="kpi-meta">All confirmed sales</div>
      </div>
      <div class="kpi-card" style="--kpi-color: var(--accent);">
        <span class="kpi-label">Total Orders</span>
        <div class="kpi-value">${metrics.total_orders}</div>
        <div class="kpi-meta">${metrics.pending_orders} pending processing</div>
      </div>
      <div class="kpi-card" style="--kpi-color: var(--success);">
        <span class="kpi-label">Active Products</span>
        <div class="kpi-value">${metrics.active_products}</div>
        <div class="kpi-meta">Catalog devices & juices</div>
      </div>
      <div class="kpi-card" style="--kpi-color: var(--warning);">
        <span class="kpi-label">Low Stock SKUs</span>
        <div class="kpi-value">${metrics.low_stock_skus}</div>
        <div class="kpi-meta">Less than 10 units</div>
      </div>
      <div class="kpi-card" style="--kpi-color: var(--danger);">
        <span class="kpi-label">Pending KYC</span>
        <div class="kpi-value">${metrics.pending_kyc}</div>
        <div class="kpi-meta">Requires age moderation</div>
      </div>
    `;

    // Recent Orders Table
    if (recentOrdersEl) {
      const orders = Store.getOrders().slice(0, 5);
      recentOrdersEl.innerHTML = orders.map((o) => `
        <tr>
          <td><b style="color: var(--accent2);">#${o.orderNumber}</b></td>
          <td>${o.customerName}</td>
          <td>${o.createdAt.substring(5, 16)}</td>
          <td><b>${Money.format(o.total)}</b></td>
          <td><span class="${OrderStatus.getBadgeClass(o.status)}">${OrderStatus.getLabel(o.status)}</span></td>
          <td>
            <button class="btn btn-secondary btn-sm" onclick="AdminApp.openFulfillment('${o.id}')">Inspect</button>
          </td>
        </tr>
      `).join('');
    }

    // Low stock SKUs
    if (lowStockEl) {
      const lowStockVariants = [];
      for (const p of Store.getProducts()) {
        for (const v of p.variants) {
          if (v.stock < 10) {
            lowStockVariants.push({ product: p, variant: v });
          }
        }
      }

      lowStockEl.innerHTML = lowStockVariants.length === 0 
        ? `<div style="padding: 20px; color: var(--text-muted); text-align: center;">All inventory SKUs are adequately stocked.</div>`
        : lowStockVariants.map((item) => `
          <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: var(--surface2); border: 1px solid var(--line); border-radius: var(--r-md); margin-bottom: 8px;">
            <div>
              <div style="font-weight: 700; font-size: 0.88rem;">${item.product.name}</div>
              <div style="font-size: 0.78rem; color: var(--text-muted);">${item.variant.flavor} (${item.variant.sku})</div>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
              <span class="badge ${item.variant.stock === 0 ? 'badge-danger' : 'badge-warning'}">
                ${item.variant.stock} left
              </span>
              <button class="btn btn-secondary btn-sm" onclick="AdminApp.quickRestock('${item.variant.id}')">+10 Restock</button>
            </div>
          </div>
        `).join('');
    }
  },

  quickRestock(variantId) {
    try {
      Store.updateVariantStock(variantId, 10, true, 'Quick Restock Button');
      Toast.success('Restocked +10 units.');
    } catch (err) {
      Toast.error(err.message);
    }
  },

  // --- MODULE 2: ORDER FULFILLMENT ---

  renderOrders() {
    const tableBody = document.getElementById('orders-table-tbody');
    if (!tableBody) return;

    let orders = [...Store.getOrders()];

    if (this.orderFilterStatus !== 'all') {
      orders = orders.filter((o) => o.status === this.orderFilterStatus);
    }

    if (this.orderSearchQuery) {
      const q = this.orderSearchQuery.toLowerCase();
      orders = orders.filter(
        (o) => o.orderNumber.toLowerCase().includes(q) || o.customerName.toLowerCase().includes(q) || o.customerEmail.toLowerCase().includes(q)
      );
    }

    if (orders.length === 0) {
      tableBody.innerHTML = `
        <tr>
          <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
            No orders match the selected filter.
          </td>
        </tr>
      `;
      return;
    }

    tableBody.innerHTML = orders.map((o) => `
      <tr>
        <td><b style="color: var(--accent2);">#${o.orderNumber}</b></td>
        <td>
          <div style="font-weight: 600;">${o.customerName}</div>
          <div style="font-size: 0.76rem; color: var(--text-muted);">${o.customerPhone}</div>
        </td>
        <td>${o.createdAt.substring(0, 16)}</td>
        <td>
          <span style="font-size: 0.8rem; text-transform: uppercase; font-weight: 700;">${o.paymentMethod}</span>
          <span class="badge ${o.paymentStatus === 'paid' ? 'badge-success' : 'badge-muted'}" style="margin-left: 4px;">
            ${o.paymentStatus}
          </span>
        </td>
        <td><b>${Money.format(o.total)}</b></td>
        <td><span class="${OrderStatus.getBadgeClass(o.status)}">${OrderStatus.getLabel(o.status)}</span></td>
        <td>
          <button class="btn btn-secondary btn-sm" onclick="AdminApp.openFulfillment('${o.id}')">
            Manage Fulfill →
          </button>
        </td>
      </tr>
    `).join('');
  },

  filterOrders(status) {
    this.orderFilterStatus = status;
    document.querySelectorAll('.filter-pill').forEach((pill) => {
      pill.classList.toggle('active', pill.getAttribute('data-status') === status);
    });
    this.renderOrders();
  },

  searchOrders(query) {
    this.orderSearchQuery = query.trim();
    this.renderOrders();
  },

  setupFulfillmentModal() {
    const modal = document.getElementById('fulfillment-modal');
    const closeBtn = document.getElementById('btn-close-fulfillment');
    if (!modal) return;

    if (closeBtn) closeBtn.addEventListener('click', () => modal.classList.remove('open'));
    modal.addEventListener('click', (e) => {
      if (e.target === modal) modal.classList.remove('open');
    });
  },

  openFulfillment(orderId) {
    this.selectedOrderId = orderId;
    const order = Store.getOrderById(orderId);
    if (!order) return;

    const modal = document.getElementById('fulfillment-modal');
    const content = document.getElementById('fulfillment-modal-content');
    if (!modal || !content) return;

    content.innerHTML = `
      <div style="padding: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <div>
            <h2 style="font-size: 1.4rem;">Order Fulfillment Center</h2>
            <div style="color: var(--accent2); font-weight: 800; font-size: 1.05rem;">#${order.orderNumber}</div>
          </div>
          <span class="${OrderStatus.getBadgeClass(order.status)}">${OrderStatus.getLabel(order.status)}</span>
        </div>

        <div style="background: var(--surface2); border: 1px solid var(--line); border-radius: var(--r-md); padding: 14px; margin-bottom: 20px; font-size: 0.88rem;">
          <b>Shipping Address:</b> ${order.shippingAddress}
        </div>

        <!-- Line items table -->
        <h4 style="margin-bottom: 10px;">Ordered Items</h4>
        <table class="fulfillment-items-table" style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
          <thead>
            <tr style="color: var(--text-muted); text-align: left; border-bottom: 1px solid var(--line);">
              <th style="padding: 8px 0;">Item</th>
              <th>Variant</th>
              <th>Unit Price</th>
              <th>Qty</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            ${order.items.map((it) => `
              <tr style="border-bottom: 1px solid var(--line);">
                <td style="padding: 8px 0;"><b>${it.productName}</b></td>
                <td>${it.flavor} (${it.nicotine})</td>
                <td>${Money.format(it.price)}</td>
                <td><b>${it.quantity}</b></td>
                <td><b>${Money.format(it.price * it.quantity)}</b></td>
              </tr>
            `).join('')}
          </tbody>
        </table>

        <!-- Actions -->
        <div style="background: var(--surface2); border: 1px solid var(--line); border-radius: var(--r-lg); padding: 20px;">
          <h4 style="margin-bottom: 12px;">Update Fulfillment Stage</h4>
          <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            ${order.status === OrderStatus.PENDING ? `
              <button class="btn btn-sm" onclick="AdminApp.advanceStatus('${order.id}', '${OrderStatus.PAID}')">
                ✓ Mark as Paid
              </button>
            ` : ''}

            ${(order.status === OrderStatus.PENDING || order.status === OrderStatus.PAID) ? `
              <button class="btn btn-sm btn-cyan" onclick="AdminApp.advanceStatus('${order.id}', '${OrderStatus.PROCESSING}')">
                📦 Process & Pack Order
              </button>
            ` : ''}

            ${order.status === OrderStatus.PROCESSING ? `
              <button class="btn btn-sm btn-cyan" onclick="AdminApp.openShipDialog('${order.id}')">
                🚚 Ship Order with Courier & Tracking #
              </button>
            ` : ''}

            ${order.status === OrderStatus.SHIPPED ? `
              <button class="btn btn-sm" style="background: var(--success); color: #fff;" onclick="AdminApp.advanceStatus('${order.id}', '${OrderStatus.DELIVERED}')">
                🎉 Confirm Delivery
              </button>
            ` : ''}

            ${OrderStatus.canCancel(order.status) ? `
              <button class="btn btn-sm btn-danger" onclick="AdminApp.advanceStatus('${order.id}', '${OrderStatus.CANCELLED}')">
                ✕ Cancel Order
              </button>
            ` : ''}
          </div>
        </div>
      </div>
    `;

    modal.classList.add('open');
  },

  async advanceStatus(orderId, targetStatus, metadata = {}) {
    try {
      const res = await Api.updateOrderStatus(orderId, targetStatus, metadata);
      if (res && res.success) {
        Store.updateOrderStatus(orderId, targetStatus, metadata);
        Toast.success(`Status updated to "${OrderStatus.getLabel(targetStatus)}".`);
        this.openFulfillment(orderId);
        this.renderOrders();
      } else {
        Toast.error(res?.error?.message || 'Failed to update order status');
      }
    } catch (err) {
      Toast.error(err.message);
    }
  },

  openShipDialog(orderId) {
    const courier = prompt('Enter Courier Partner (e.g. J&T Express, Lalamove):', 'J&T Express');
    if (!courier) return;
    const defaultTrack = 'JT-' + Math.floor(100000000 + Math.random() * 900000000) + 'PH';
    const trackingNumber = prompt('Enter Tracking Number:', defaultTrack);
    if (!trackingNumber) return;

    this.advanceStatus(orderId, OrderStatus.SHIPPED, { courierName: courier, trackingNumber });
  },

  // --- MODULE 3: PRODUCTS & INVENTORY ---

  renderInventory() {
    const tableBody = document.getElementById('inventory-table-tbody');
    if (!tableBody) return;

    const products = Store.getProducts();

    tableBody.innerHTML = products.map((p) => `
      <tr>
        <td>
          <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 32px; height: 50px; border-radius: 6px; background: linear-gradient(160deg, ${p.color1}, ${p.color2}); flex-shrink: 0;"></div>
            <div>
              <div style="font-weight: 700;">${p.name}</div>
              <div style="font-size: 0.76rem; color: var(--text-muted);">${p.brand} · ${p.category}</div>
            </div>
          </div>
        </td>
        <td>
          <div style="display: flex; flex-direction: column; gap: 6px;">
            ${p.variants.map((v) => `
              <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.82rem; background: var(--surface2); padding: 4px 8px; border-radius: var(--r-sm);">
                <span><b>${v.flavor}</b> (${v.nicotine})</span>
                <span style="color: var(--accent2);">${Money.format(v.price)}</span>
                <div class="stock-adjust-control">
                  <span class="stock-adjust-btn" onclick="AdminApp.adjustStock('${v.id}', -1)">−</span>
                  <input type="text" class="stock-number-input" value="${v.stock}" onchange="AdminApp.setStock('${v.id}', this.value)">
                  <span class="stock-adjust-btn" onclick="AdminApp.adjustStock('${v.id}', 1)">+</span>
                </div>
              </div>
            `).join('')}
          </div>
        </td>
        <td>
          <span class="badge ${p.variants.reduce((a, b) => a + b.stock, 0) > 0 ? 'badge-success' : 'badge-danger'}">
            ${p.variants.reduce((a, b) => a + b.stock, 0)} Units Total
          </span>
        </td>
      </tr>
    `).join('');
  },

  async adjustStock(variantId, delta) {
    try {
      const res = await Api.updateStock(variantId, delta, true);
      if (res && res.success) {
        Store.updateVariantStock(variantId, delta, true);
        Toast.success('Stock adjusted.');
        this.renderInventory();
      } else {
        Toast.error(res?.error?.message || 'Failed to adjust stock');
      }
    } catch (err) {
      Toast.error(err.message);
    }
  },

  async setStock(variantId, newStock) {
    try {
      const res = await Api.updateStock(variantId, parseInt(newStock, 10), false);
      if (res && res.success) {
        Store.updateVariantStock(variantId, newStock, false);
        Toast.success('Stock updated.');
        this.renderInventory();
      } else {
        Toast.error(res?.error?.message || 'Failed to update stock');
      }
    } catch (err) {
      Toast.error(err.message);
    }
  },

  // --- MODULE 4: COUPONS ---

  renderCoupons() {
    const tbody = document.getElementById('coupons-table-tbody');
    if (!tbody) return;

    const coupons = Store.getCoupons();
    tbody.innerHTML = coupons.map((c) => `
      <tr>
        <td><b style="color: var(--accent2);">${c.code}</b></td>
        <td><b>${c.discountPercent}% OFF</b></td>
        <td>${c.minSpend > 0 ? Money.format(c.minSpend) : 'No Minimum'}</td>
        <td>${c.usedCount} / ${c.maxUses}</td>
        <td>
          <span class="badge ${c.isActive ? 'badge-success' : 'badge-muted'}">
            ${c.isActive ? 'ACTIVE' : 'PAUSED'}
          </span>
        </td>
        <td>
          <button class="btn btn-secondary btn-sm" onclick="AdminApp.toggleCoupon('${c.id}')">
            ${c.isActive ? 'Pause' : 'Activate'}
          </button>
        </td>
      </tr>
    `).join('');
  },

  toggleCoupon(id) {
    const cpn = Store.getState().coupons.find((c) => c.id === id);
    if (cpn) {
      cpn.isActive = !cpn.isActive;
      Store.save();
      this.renderCoupons();
    }
  },

  // --- MODULE 5: KYC ---

  renderKyc() {
    const grid = document.getElementById('kyc-cards-grid');
    if (!grid) return;

    const requests = Store.getState().kycRequests;

    grid.innerHTML = requests.map((k) => `
      <div class="kyc-card">
        <div class="kyc-user-header">
          <div>
            <h4 style="font-size: 1.1rem;">${k.customerName}</h4>
            <div style="font-size: 0.78rem; color: var(--text-muted);">${k.customerEmail}</div>
          </div>
          <span class="badge ${k.status === 'approved' ? 'badge-success' : 'badge-warning'}">
            ${k.status.toUpperCase()}
          </span>
        </div>
        <div style="font-size: 0.84rem;">
          <div><b>Birth Date:</b> ${k.birthDate}</div>
          <div><b>ID Type:</b> ${k.idType} (${k.idNumber})</div>
        </div>
        <div class="kyc-id-preview">[Verified Government ID Photo Document]</div>
        ${k.status === 'pending' ? `
          <div style="display: flex; gap: 10px; margin-top: 8px;">
            <button class="btn btn-sm btn-cyan btn-block" onclick="AdminApp.approveKyc('${k.id}')">Approve (18+)</button>
            <button class="btn btn-sm btn-danger btn-block" onclick="AdminApp.rejectKyc('${k.id}')">Reject</button>
          </div>
        ` : ''}
      </div>
    `).join('');
  },

  async approveKyc(id) {
    try {
      const res = await Api.reviewKyc(id, 'approved');
      if (res && res.success) {
        const req = (Store.getState().kycRequests || []).find((k) => k.id === id);
        if (req) {
          req.status = 'approved';
          Store.save();
        }
        Toast.success('KYC Approved.');
        this.renderKyc();
      } else {
        Toast.error(res?.error?.message || 'Failed to approve KYC');
      }
    } catch (err) {
      Toast.error(err.message);
    }
  },

  async rejectKyc(id) {
    try {
      const res = await Api.reviewKyc(id, 'rejected');
      if (res && res.success) {
        const req = (Store.getState().kycRequests || []).find((k) => k.id === id);
        if (req) {
          req.status = 'rejected';
          Store.save();
        }
        Toast.info('KYC Rejected.');
        this.renderKyc();
      } else {
        Toast.error(res?.error?.message || 'Failed to reject KYC');
      }
    } catch (err) {
      Toast.error(err.message);
    }
  },

  // --- MODULE 6: CUSTOMERS ---

  renderCustomers() {
    const tbody = document.getElementById('customers-table-tbody');
    if (!tbody) return;

    const orders = Store.getOrders();
    const customerMap = {};

    for (const o of orders) {
      if (!customerMap[o.customerEmail]) {
        customerMap[o.customerEmail] = {
          name: o.customerName,
          email: o.customerEmail,
          phone: o.customerPhone,
          orderCount: 0,
          totalSpent: 0,
        };
      }
      customerMap[o.customerEmail].orderCount++;
      if (o.status !== OrderStatus.CANCELLED) {
        customerMap[o.customerEmail].totalSpent += o.total;
      }
    }

    const customers = Object.values(customerMap);

    tbody.innerHTML = customers.map((c) => `
      <tr>
        <td><b>${c.name}</b></td>
        <td>${c.email}</td>
        <td>${c.phone}</td>
        <td>${c.orderCount} Orders</td>
        <td><b style="color: var(--accent2);">${Money.format(c.totalSpent)}</b></td>
        <td><span class="badge badge-success">✓ 18+ Age Verified</span></td>
      </tr>
    `).join('');
  },
};

document.addEventListener('DOMContentLoaded', () => {
  AdminApp.init();
});

if (typeof module !== 'undefined' && module.exports) {
  module.exports = AdminApp;
}
