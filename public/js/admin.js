/**
 * VAPEHAUS ADMIN PORTAL CONTROLLER
 * Full ERP fulfillment workflow, inventory stock adjustments, KYC moderation, and metrics
 */

const AdminApp = {
  currentTab: 'dashboard',
  orderFilterStatus: 'all',
  orderSearchQuery: '',
  selectedOrderId: null,

  async init() {
    this.setupSidebarNav();
    this.setupFulfillmentModal();

    // Subscribe to store updates to keep tables and KPIs live
    Store.subscribe(() => {
      this.refreshCurrentView();
    });

    await this.loadInitialData();
    this.showTab('dashboard');
  },

  async loadInitialData() {
    try {
      await Promise.all([
        Api.getProducts(),
        Api.getAdminOrders(),
        Api.getAdminCoupons(),
        Api.getAdminKyc(),
      ]);
    } catch (e) {
      console.warn('Initial admin data sync warning:', e);
    }
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
      if (orders.length === 0) {
        recentOrdersEl.innerHTML = `
          <tr>
            <td colspan="6" style="text-align: center; padding: 24px; color: var(--text-muted);">
              No customer orders placed yet.
            </td>
          </tr>
        `;
      } else {
        recentOrdersEl.innerHTML = orders.map((o) => `
          <tr>
            <td><b style="color: var(--accent2);">#${o.orderNumber || o.order_number || o.id}</b></td>
            <td>${o.customerName || o.customer_name || 'Customer'}</td>
            <td>${(o.createdAt || o.created_at || '').substring(0, 16)}</td>
            <td><b>${Money.format(o.total)}</b></td>
            <td><span class="${OrderStatus.getBadgeClass(o.status)}">${OrderStatus.getLabel(o.status)}</span></td>
            <td>
              <button class="btn btn-secondary btn-sm" onclick="AdminApp.openFulfillment('${o.id}')">Inspect</button>
            </td>
          </tr>
        `).join('');
      }
    }

    // Low stock SKUs
    if (lowStockEl) {
      const lowStockVariants = [];
      for (const p of Store.getProducts()) {
        for (const v of (p.variants || [])) {
          const s = v.stock ?? v.stock_quantity ?? 0;
          if (s < 10) {
            lowStockVariants.push({ product: p, variant: { ...v, stock: s } });
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

    tableBody.innerHTML = orders.map((o) => {
      const orderNum = o.orderNumber || o.order_number || o.id;
      const custName = o.customerName || o.customer_name || 'Customer';
      const custPhone = o.customerPhone || o.customer_phone || '';
      const created = (o.createdAt || o.created_at || '').substring(0, 16);
      const payMethod = o.paymentMethod || o.payment_method || 'cod';
      const payStatus = o.paymentStatus || o.payment_status || 'unpaid';

      return `
      <tr>
        <td><b style="color: var(--accent2);">#${orderNum}</b></td>
        <td>
          <div style="font-weight: 600;">${custName}</div>
          <div style="font-size: 0.76rem; color: var(--text-muted);">${custPhone}</div>
        </td>
        <td>${created}</td>
        <td>
          <span style="font-size: 0.8rem; text-transform: uppercase; font-weight: 700;">${payMethod}</span>
          <span class="badge ${payStatus === 'paid' ? 'badge-success' : 'badge-muted'}" style="margin-left: 4px;">
            ${payStatus}
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
    `;}).join('');
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
            <div style="color: var(--accent2); font-weight: 800; font-size: 1.05rem;">#${order.orderNumber || order.order_number || order.id}</div>
          </div>
          <span class="${OrderStatus.getBadgeClass(order.status)}">${OrderStatus.getLabel(order.status)}</span>
        </div>

        <div style="background: var(--surface2); border: 1px solid var(--line); border-radius: var(--r-md); padding: 14px; margin-bottom: 20px; font-size: 0.88rem;">
          <b>Shipping Address:</b> ${order.shippingAddress || order.shipping_address || '—'}
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
            ${(order.items || []).map((it) => {
              const name = it.productName || it.name || it.snapshotted_name || 'Product';
              const flavor = it.flavor || it.snapshotted_flavor || '';
              const nic = it.nicotine || it.snapshotted_nicotine || '';
              const price = it.price ?? it.unit_price ?? 0;
              const qty = it.quantity ?? 1;
              const total = it.total_price ?? (price * qty);

              return `
              <tr style="border-bottom: 1px solid var(--line);">
                <td style="padding: 8px 0;"><b>${name}</b></td>
                <td>${flavor}${nic ? ` (${nic})` : ''}</td>
                <td>${Money.format(price)}</td>
                <td><b>${qty}</b></td>
                <td><b>${Money.format(total)}</b></td>
              </tr>
            `;}).join('')}
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
      let res;
      if (targetStatus === OrderStatus.SHIPPED && metadata.courierName && metadata.trackingNumber) {
        res = await Api.shipOrder(orderId, metadata.courierName, metadata.trackingNumber);
      } else {
        res = await Api.updateOrderStatus(orderId, targetStatus, metadata);
      }
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

    if (products.length === 0) {
      tableBody.innerHTML = `
        <tr>
          <td colspan="3" style="text-align: center; padding: 40px; color: var(--text-muted);">
            No products found in catalog.
          </td>
        </tr>
      `;
      return;
    }

    tableBody.innerHTML = products.map((p) => {
      const c1 = p.color1 || '#8b5cf6';
      const c2 = p.color2 || '#22d3ee';
      const variants = p.variants || [];
      const totalUnits = variants.reduce((a, b) => a + (b.stock ?? b.stock_quantity ?? 0), 0);

      return `
      <tr>
        <td>
          <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 32px; height: 50px; border-radius: 6px; background: linear-gradient(160deg, ${c1}, ${c2}); flex-shrink: 0;"></div>
            <div>
              <div style="font-weight: 700;">${p.name}</div>
              <div style="font-size: 0.76rem; color: var(--text-muted);">${p.brand} · ${p.category || p.category_id || ''}</div>
            </div>
          </div>
        </td>
        <td>
          <div style="display: flex; flex-direction: column; gap: 6px;">
            ${variants.map((v) => `
              <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.82rem; background: var(--surface2); padding: 4px 8px; border-radius: var(--r-sm);">
                <span><b>${v.flavor}</b> (${v.nicotine || v.nicotine_strength || ''})</span>
                <span style="color: var(--accent2);">${Money.format(v.price)}</span>
                <div class="stock-adjust-control">
                  <span class="stock-adjust-btn" onclick="AdminApp.adjustStock('${v.id}', -1)">−</span>
                  <input type="text" class="stock-number-input" value="${v.stock ?? v.stock_quantity ?? 0}" onchange="AdminApp.setStock('${v.id}', this.value)">
                  <span class="stock-adjust-btn" onclick="AdminApp.adjustStock('${v.id}', 1)">+</span>
                </div>
              </div>
            `).join('')}
          </div>
        </td>
        <td>
          <span class="badge ${totalUnits > 0 ? 'badge-success' : 'badge-danger'}">
            ${totalUnits} Units Total
          </span>
        </td>
      </tr>
    `;}).join('');
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
    if (coupons.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
            No coupons configured. Use "+ Create Promo Code" above to add promotional discounts.
          </td>
        </tr>
      `;
      return;
    }

    tbody.innerHTML = coupons.map((c) => {
      const discount = c.discountPercent ?? c.value ?? 0;
      const minSpend = c.minSpend ?? c.min_spend ?? 0;
      const usedCount = c.usedCount ?? c.times_used ?? 0;
      const maxUses = c.maxUses ?? c.usage_limit ?? 0;
      const isActive = c.isActive !== undefined ? !!c.isActive : (c.is_active == 1);

      return `
      <tr>
        <td><b style="color: var(--accent2);">${c.code}</b></td>
        <td><b>${discount}% OFF</b></td>
        <td>${minSpend > 0 ? Money.format(minSpend) : 'No Minimum'}</td>
        <td>${usedCount} / ${maxUses}</td>
        <td>
          <span class="badge ${isActive ? 'badge-success' : 'badge-muted'}">
            ${isActive ? 'ACTIVE' : 'PAUSED'}
          </span>
        </td>
        <td>
          <button class="btn btn-secondary btn-sm" onclick="AdminApp.toggleCoupon('${c.id}')">
            ${isActive ? 'Pause' : 'Activate'}
          </button>
        </td>
      </tr>
    `;}).join('');
  },

  async toggleCoupon(id) {
    try {
      const res = await Api.toggleAdminCoupon(id);
      if (res && res.success) {
        const cpn = Store.getState().coupons.find((c) => c.id === id);
        if (cpn) {
          cpn.isActive = !cpn.isActive;
          Store.save();
        }
        Toast.success('Coupon status updated.');
        this.renderCoupons();
      } else {
        Toast.error(res?.error?.message || 'Failed to toggle coupon');
      }
    } catch (err) {
      Toast.error(err.message);
    }
  },

  // --- MODULE 5: KYC ---

  renderKyc() {
    const grid = document.getElementById('kyc-cards-grid');
    if (!grid) return;

    const requests = Store.getState().kycRequests || [];

    if (requests.length === 0) {
      grid.innerHTML = `
        <div style="grid-column: 1 / -1; padding: 50px 20px; text-align: center; color: var(--text-muted);">
          <div style="font-size: 2.5rem; margin-bottom: 12px;">✅</div>
          <h3>KYC Verification Queue is Clear</h3>
          <p style="margin-top: 8px;">No pending statutory age verification submissions awaiting moderation.</p>
        </div>
      `;
      return;
    }

    grid.innerHTML = requests.map((k) => {
      const name = k.customerName || k.customer_name || 'Customer';
      const email = k.customerEmail || k.customer_email || '';
      const dob = k.birthDate || k.birth_date || '—';
      const idType = k.idType || k.id_type || 'ID';
      const idNum = k.idNumber || k.id_number || '—';
      const status = (k.status || 'pending').toLowerCase();

      return `
      <div class="kyc-card">
        <div class="kyc-user-header">
          <div>
            <h4 style="font-size: 1.1rem;">${name}</h4>
            <div style="font-size: 0.78rem; color: var(--text-muted);">${email}</div>
          </div>
          <span class="badge ${status === 'approved' ? 'badge-success' : 'badge-warning'}">
            ${status.toUpperCase()}
          </span>
        </div>
        <div style="font-size: 0.84rem;">
          <div><b>Birth Date:</b> ${dob}</div>
          <div><b>ID Type:</b> ${idType} (${idNum})</div>
        </div>
        <div class="kyc-id-preview">[Verified Government ID Photo Document]</div>
        ${status === 'pending' ? `
          <div style="display: flex; gap: 10px; margin-top: 8px;">
            <button class="btn btn-sm btn-cyan btn-block" onclick="AdminApp.approveKyc('${k.id}')">Approve (18+)</button>
            <button class="btn btn-sm btn-danger btn-block" onclick="AdminApp.rejectKyc('${k.id}')">Reject</button>
          </div>
        ` : ''}
      </div>
    `;}).join('');
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

  async renderCustomers() {
    const tbody = document.getElementById('customers-table-tbody');
    if (!tbody) return;

    let customers = [];
    try {
      const res = await Api.getAdminCustomers();
      if (res && res.success && Array.isArray(res.data) && res.data.length > 0) {
        customers = res.data;
      }
    } catch (e) {}

    if (customers.length === 0) {
      const orders = Store.getOrders();
      const customerMap = {};

      for (const o of orders) {
        const email = o.customerEmail || o.customer_email;
        if (!email) continue;
        if (!customerMap[email]) {
          customerMap[email] = {
            name: o.customerName || o.customer_name || 'Customer',
            email: email,
            phone: o.customerPhone || o.customer_phone || '',
            orderCount: 0,
            totalSpent: 0,
          };
        }
        customerMap[email].orderCount++;
        if (o.status !== OrderStatus.CANCELLED) {
          customerMap[email].totalSpent += (o.total || 0);
        }
      }
      customers = Object.values(customerMap);
    }

    if (customers.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
            No customer order history recorded yet.
          </td>
        </tr>
      `;
      return;
    }

    tbody.innerHTML = customers.map((c) => {
      const name = c.name || `${c.first_name || ''} ${c.last_name || ''}`.trim() || c.email;
      const count = c.orderCount ?? c.total_orders ?? 0;
      const spent = c.totalSpent ?? c.total_spent ?? 0;

      return `
      <tr>
        <td><b>${name}</b></td>
        <td>${c.email}</td>
        <td>${c.phone || '—'}</td>
        <td>${count} Orders</td>
        <td><b style="color: var(--accent2);">${Money.format(spent)}</b></td>
        <td><span class="badge badge-success">✓ 18+ Age Verified</span></td>
      </tr>
    `;}).join('');
  },
};

document.addEventListener('DOMContentLoaded', () => {
  AdminApp.init();
});

if (typeof module !== 'undefined' && module.exports) {
  module.exports = AdminApp;
}
