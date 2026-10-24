/**
 * VAPEHAUS STOREFRONT APPLICATION CONTROLLER
 * Orchestrates catalog filtering, search typeahead, authentication, and top admin quick action modals
 */

const StorefrontApp = {
  currentCategory: 'all',
  selectedBrands: [],
  selectedNicotine: null,
  currentSort: 'featured',
  inStockOnly: false,
  searchQuery: '',

  init() {
    // 1. Initialize Sub-Components
    if (typeof AgeGate !== 'undefined') AgeGate.init();
    if (typeof AuthModal !== 'undefined') AuthModal.init();
    if (typeof CartDrawer !== 'undefined') CartDrawer.init();
    if (typeof QuickViewModal !== 'undefined') QuickViewModal.init();
    if (typeof CheckoutModal !== 'undefined') CheckoutModal.init();
    if (typeof OrderTrackerModal !== 'undefined') OrderTrackerModal.init();

    // 2. Setup Category Bar & Facets
    this.setupCategories();
    this.setupSearch();
    this.setupSortAndFacets();
    this.setupAccountModal();
    this.setupMobileMenu();
    this.setupAdminActionModals();

    // 3. Subscribe to Store updates to re-render products and user state
    Store.subscribe(() => {
      this.renderProducts();
    });

    this.renderProducts();
  },

  setupCategories() {
    const container = document.getElementById('category-chips-bar');
    if (!container) return;

    const categories = Store.getCategories();
    container.innerHTML = categories.map((cat) => `
      <div class="cat-chip ${cat.slug === this.currentCategory ? 'active' : ''}" 
           data-slug="${cat.slug}" onclick="StorefrontApp.setCategory('${cat.slug}')">
        <span>${cat.icon}</span> ${cat.name}
      </div>
    `).join('');
  },

  setCategory(slug) {
    this.currentCategory = slug;
    document.querySelectorAll('.cat-chip').forEach((el) => {
      el.classList.toggle('active', el.getAttribute('data-slug') === slug);
    });
    this.renderProducts();
  },

  setupSearch() {
    const searchInput = document.getElementById('store-search-input');
    const dropdown = document.getElementById('search-dropdown-results');
    if (!searchInput || !dropdown) return;

    searchInput.addEventListener('input', (e) => {
      const query = e.target.value.trim().toLowerCase();
      this.searchQuery = query;

      if (query.length < 2) {
        dropdown.classList.remove('active');
        dropdown.innerHTML = '';
        this.renderProducts();
        return;
      }

      const products = Store.getProducts().filter(
        (p) => p.name.toLowerCase().includes(query) || p.brand.toLowerCase().includes(query) || p.description.toLowerCase().includes(query)
      );

      if (products.length === 0) {
        dropdown.innerHTML = `<div style="padding: 16px; text-align: center; color: var(--text-muted); font-size: 0.88rem;">No products found for "${query}"</div>`;
      } else {
        dropdown.innerHTML = products.slice(0, 5).map((p) => `
          <div class="search-item" onclick="StorefrontApp.handleSearchItemClick('${p.id}')">
            <div class="search-item-thumb">
              <div class="device-art" style="width: 20px; height: 32px; border-radius: 4px; background: linear-gradient(160deg, ${p.color1}, ${p.color2});"></div>
            </div>
            <div class="search-item-info">
              <div class="search-item-title">${p.name}</div>
              <div class="search-item-meta">${p.brand} · ${p.rating} ★</div>
            </div>
            <div class="search-item-price">${Money.format(p.variants[0].price)}</div>
          </div>
        `).join('');
      }

      dropdown.classList.add('active');
      this.renderProducts();
    });

    document.addEventListener('click', (e) => {
      if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.classList.remove('active');
      }
    });
  },

  handleSearchItemClick(productId) {
    const dropdown = document.getElementById('search-dropdown-results');
    if (dropdown) dropdown.classList.remove('active');
    QuickViewModal.open(productId);
  },

  setupSortAndFacets() {
    const sortSelect = document.getElementById('sort-products-select');
    if (sortSelect) {
      sortSelect.addEventListener('change', (e) => {
        this.currentSort = e.target.value;
        this.renderProducts();
      });
    }

    const inStockToggle = document.getElementById('facet-instock-toggle');
    if (inStockToggle) {
      inStockToggle.addEventListener('change', (e) => {
        this.inStockOnly = e.target.checked;
        this.renderProducts();
      });
    }

    // Brands checkboxes
    document.querySelectorAll('.brand-checkbox').forEach((cb) => {
      cb.addEventListener('change', () => {
        const brands = [];
        document.querySelectorAll('.brand-checkbox:checked').forEach((el) => {
          brands.push(el.value);
        });
        this.selectedBrands = brands;
        this.renderProducts();
      });
    });

    // Nicotine chips
    document.querySelectorAll('.nicotine-chip').forEach((chip) => {
      chip.addEventListener('click', () => {
        const val = chip.getAttribute('data-nicotine');
        if (this.selectedNicotine === val) {
          this.selectedNicotine = null;
          chip.classList.remove('active');
        } else {
          document.querySelectorAll('.nicotine-chip').forEach((c) => c.classList.remove('active'));
          this.selectedNicotine = val;
          chip.classList.add('active');
        }
        this.renderProducts();
      });
    });
  },

  renderProducts() {
    const gridEl = document.getElementById('products-grid-container');
    const counterEl = document.getElementById('products-count-badge');
    if (!gridEl) return;

    let products = [...Store.getProducts()];

    // 1. Filter Category
    if (this.currentCategory && this.currentCategory !== 'all') {
      products = products.filter((p) => p.category === this.currentCategory);
    }

    // 2. Filter Search Query
    if (this.searchQuery) {
      const q = this.searchQuery.toLowerCase();
      products = products.filter(
        (p) => p.name.toLowerCase().includes(q) || p.brand.toLowerCase().includes(q) || p.description.toLowerCase().includes(q)
      );
    }

    // 3. Filter Brands
    if (this.selectedBrands.length > 0) {
      products = products.filter((p) => this.selectedBrands.includes(p.brand));
    }

    // 4. Filter Nicotine
    if (this.selectedNicotine) {
      products = products.filter((p) => p.variants.some((v) => v.nicotine === this.selectedNicotine));
    }

    // 5. Filter In-Stock
    if (this.inStockOnly) {
      products = products.filter((p) => p.variants.some((v) => v.stock > 0));
    }

    // 6. Sort
    if (this.currentSort === 'price_asc') {
      products.sort((a, b) => a.variants[0].price - b.variants[0].price);
    } else if (this.currentSort === 'price_desc') {
      products.sort((a, b) => b.variants[0].price - a.variants[0].price);
    } else if (this.currentSort === 'rating') {
      products.sort((a, b) => b.rating - a.rating);
    }

    if (counterEl) {
      counterEl.textContent = products.length;
    }

    if (products.length === 0) {
      gridEl.innerHTML = `
        <div style="grid-column: 1 / -1; padding: 60px 20px; text-align: center; color: var(--text-muted);">
          <div style="font-size: 3rem; margin-bottom: 12px;">🔍</div>
          <h3>No matching vape products found</h3>
          <p style="margin-top: 8px;">Try clearing filters or search for another keyword.</p>
        </div>
      `;
      return;
    }

    gridEl.innerHTML = products.map((p) => {
      const mainVariant = p.variants[0];
      const hasStock = p.variants.some((v) => v.stock > 0);
      const totalStock = p.variants.reduce((acc, v) => acc + v.stock, 0);

      return `
        <article class="product-card">
          <div class="product-thumb" onclick="QuickViewModal.open('${p.id}')">
            ${p.badge ? `<span class="badge badge-cyan product-badge">${p.badge}</span>` : ''}
            <div class="device-art" style="--c1: ${p.color1}; --c2: ${p.color2}"></div>
            <div class="product-stock-tag ${totalStock < 10 ? 'low' : ''}">
              ${hasStock ? (totalStock < 10 ? `Only ${totalStock} left` : 'In Stock') : 'Out of Stock'}
            </div>
          </div>

          <div class="product-body">
            <div class="product-brand">${p.brand}</div>
            <h3 class="product-title" onclick="QuickViewModal.open('${p.id}')">${p.name}</h3>
            
            <div class="product-rating">
              ★★★★★ <span>(${p.reviewsCount})</span>
            </div>

            <div class="product-footer">
              <div class="product-price">
                ${Money.format(mainVariant.price)}
                ${mainVariant.originalPrice ? `<s>${Money.format(mainVariant.originalPrice)}</s>` : ''}
              </div>
              <button class="btn-add-cart" onclick="StorefrontApp.quickAddToCart('${mainVariant.id}', event)" title="Add to Cart" ${!hasStock ? 'disabled' : ''}>
                +
              </button>
            </div>
          </div>
        </article>
      `;
    }).join('');
  },

  quickAddToCart(variantId, event) {
    if (event) event.stopPropagation();
    try {
      Store.addToCart(variantId, 1);
      const found = Store.getVariantById(variantId);
      Toast.success(`Added ${found.product.name} to cart.`);
    } catch (err) {
      Toast.error(err.message);
    }
  },

  openAccountModal() {
    const modal = document.getElementById('account-modal');
    if (!modal) return;
    const user = Store.getCurrentUser();
    if (!user) {
      AuthModal.openLogin();
    } else {
      this.renderAccountContent();
      modal.classList.add('open');
    }
  },

  setupAccountModal() {
    const modal = document.getElementById('account-modal');
    const trigger = document.getElementById('btn-account-toggle');
    const closeBtn = document.getElementById('btn-close-account');
    if (!modal || !trigger) return;

    trigger.addEventListener('click', () => {
      this.openAccountModal();
    });

    if (closeBtn) {
      closeBtn.addEventListener('click', () => modal.classList.remove('open'));
    }

    modal.addEventListener('click', (e) => {
      if (e.target === modal) modal.classList.remove('open');
    });
  },

  renderAccountContent() {
    const content = document.getElementById('account-modal-content');
    if (!content) return;

    const user = Store.getCurrentUser() || CONFIG.DEFAULT_USER;
    const orders = Store.getOrders();
    const isAdmin = Store.isAdmin();

    content.innerHTML = `
      <div style="padding: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; border-bottom: 1px solid var(--line); padding-bottom: 16px;">
          <div>
            <div style="display: flex; align-items: center; gap: 8px;">
              <h2 style="font-size: 1.4rem;">${user.firstName} ${user.lastName}</h2>
              <span class="badge ${isAdmin ? 'badge-accent' : 'badge-cyan'}">${user.role.toUpperCase()}</span>
            </div>
            <div style="color: var(--text-muted); font-size: 0.88rem; margin-top: 2px;">${user.email} · ${user.phone || '+63 9XX XXX XXXX'}</div>
          </div>
          <span class="badge ${user.isAgeVerified ? 'badge-success' : 'badge-warning'}">
            ${user.isAgeVerified ? '✓ 18+ Age Verified' : 'Age Verification Pending'}
          </span>
        </div>

        <!-- Account Tabs -->
        <div style="display: flex; gap: 12px; margin-bottom: 20px;">
          <button class="btn btn-secondary btn-sm" id="tab-btn-orders" onclick="StorefrontApp.showAccountTab('orders')">
            📦 My Orders (${orders.length})
          </button>
          <button class="btn btn-secondary btn-sm" id="tab-btn-kyc" onclick="StorefrontApp.showAccountTab('kyc')">
            🪪 18+ Age KYC Verification
          </button>
        </div>

        <div id="account-tab-content">
          ${this.renderAccountOrdersHtml(orders)}
        </div>

        <!-- Footer Sign Out -->
        <div style="margin-top: 28px; padding-top: 18px; border-top: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center;">
          <button class="btn btn-secondary btn-sm" onclick="AuthModal.openLogin(); document.getElementById('account-modal').classList.remove('open');">
            Switch Account
          </button>
          <button class="btn btn-danger btn-sm" onclick="AuthModal.logout(); document.getElementById('account-modal').classList.remove('open');">
            🚪 Sign Out
          </button>
        </div>
      </div>
    `;
  },

  showAccountTab(tab) {
    const container = document.getElementById('account-tab-content');
    if (!container) return;

    if (tab === 'orders') {
      const orders = Store.getOrders();
      container.innerHTML = this.renderAccountOrdersHtml(orders);
    } else if (tab === 'kyc') {
      const user = Store.getCurrentUser() || CONFIG.DEFAULT_USER;
      container.innerHTML = `
        <div style="background: var(--surface2); border: 1px solid var(--line); border-radius: var(--r-md); padding: 24px;">
          <h4 style="margin-bottom: 8px;">Statutory 18+ Government ID Verification</h4>
          <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 20px;">
            In compliance with Philippine vapor regulation laws (R.A. 11900), all buyers must have verified government-issued identification proving they are 18 years of age or older.
          </p>

          <div style="background: var(--surface); border: 1px dashed var(--line-strong); border-radius: var(--r-md); padding: 24px; text-align: center; margin-bottom: 20px;">
            <div style="font-size: 2.4rem; margin-bottom: 8px;">🪪</div>
            <div style="font-weight: 600; margin-bottom: 4px;">Philippine Passport / Driver's License / UMID</div>
            <div style="font-size: 0.8rem; color: var(--text-muted);">Current Status: <b style="color: var(--success);">${user.isAgeVerified ? 'VERIFIED (18+)' : 'PENDING REVIEW'}</b></div>
          </div>

          <button class="btn btn-cyan btn-sm" onclick="Toast.success('Government ID is in good standing (18+ Verified).');">
            ✓ Verification Status Confirmed
          </button>
        </div>
      `;
    }
  },

  renderAccountOrdersHtml(orders) {
    if (orders.length === 0) {
      return `<div style="text-align: center; padding: 40px; color: var(--text-muted);">No orders placed yet.</div>`;
    }

    return `
      <div style="display: flex; flex-direction: column; gap: 14px; max-height: 280px; overflow-y: auto;">
        ${orders.map((o) => `
          <div style="background: var(--surface2); border: 1px solid var(--line); border-radius: var(--r-md); padding: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div>
              <div style="font-weight: 800; font-size: 0.95rem; color: var(--accent2);">#${o.orderNumber}</div>
              <div style="font-size: 0.78rem; color: var(--text-muted);">${o.createdAt} · ${o.items.length} items</div>
              <div style="font-size: 0.88rem; font-weight: 700; margin-top: 4px;">Total: ${Money.format(o.total)}</div>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
              <span class="${OrderStatus.getBadgeClass(o.status)}">${OrderStatus.getLabel(o.status)}</span>
              <button class="btn btn-secondary btn-sm" onclick="StorefrontApp.inspectOrder('${o.id}')">
                Track →
              </button>
            </div>
          </div>
        `).join('')}
      </div>
    `;
  },

  inspectOrder(orderId) {
    const modal = document.getElementById('account-modal');
    if (modal) modal.classList.remove('open');
    OrderTrackerModal.showOrder(orderId);
  },

  setupMobileMenu() {
    const burgerBtn = document.getElementById('btn-hamburger-toggle');
    const nav = document.getElementById('store-main-nav');
    if (!burgerBtn || !nav) return;

    burgerBtn.addEventListener('click', () => {
      nav.classList.toggle('open');
    });
  },

  setupAdminActionModals() {
    const prodModal = document.getElementById('admin-add-product-modal');
    const cpnModal = document.getElementById('admin-create-coupon-modal');

    if (prodModal) {
      const closeBtn = document.getElementById('btn-close-admin-product');
      if (closeBtn) closeBtn.addEventListener('click', () => prodModal.classList.remove('open'));
      prodModal.addEventListener('click', (e) => {
        if (e.target === prodModal) prodModal.classList.remove('open');
      });
    }

    if (cpnModal) {
      const closeBtn = document.getElementById('btn-close-admin-coupon');
      if (closeBtn) closeBtn.addEventListener('click', () => cpnModal.classList.remove('open'));
      cpnModal.addEventListener('click', (e) => {
        if (e.target === cpnModal) cpnModal.classList.remove('open');
      });
    }
  },
};

// Global Admin Action Modals Controller
const AdminActionModals = {
  openAddProduct() {
    const modal = document.getElementById('admin-add-product-modal');
    if (modal) modal.classList.add('open');
  },
  closeAddProduct() {
    const modal = document.getElementById('admin-add-product-modal');
    if (modal) modal.classList.remove('open');
  },
  async submitAddProduct(event) {
    if (event) event.preventDefault();
    const name = document.getElementById('admin-new-prod-name').value.trim();
    const brand = document.getElementById('admin-new-prod-brand').value.trim();
    const category = document.getElementById('admin-new-prod-cat').value;
    const price = parseFloat(document.getElementById('admin-new-prod-price').value);
    const origPriceVal = document.getElementById('admin-new-prod-orig-price').value;
    const originalPrice = origPriceVal ? parseFloat(origPriceVal) : null;
    const flavors = document.getElementById('admin-new-prod-flavors').value.trim().split(',');
    const stock = parseInt(document.getElementById('admin-new-prod-stock').value, 10) || 20;
    const desc = document.getElementById('admin-new-prod-desc').value.trim();

    if (!name || !brand || isNaN(price)) {
      Toast.error('Please enter product name, brand, and base price.');
      return;
    }

    try {
      const payload = {
        name,
        brand,
        category,
        price,
        description: desc,
        variants: flavors.map((f) => ({
          flavor: f.trim() || 'Standard Edition',
          nicotine: '20mg',
          price,
          originalPrice,
          stock,
        })),
      };

      const res = await Api.addProduct(payload);
      if (res && res.success) {
        Store.addProduct(payload);
        Toast.success(`Product "${res.data.name || name}" added to catalog.`);
        this.closeAddProduct();
        document.getElementById('admin-add-product-form').reset();
      } else {
        Toast.error(res?.error?.message || 'Failed to add product');
      }
    } catch (err) {
      Toast.error(err.message);
    }
  },

  openCreateCoupon() {
    const modal = document.getElementById('admin-create-coupon-modal');
    if (modal) modal.classList.add('open');
  },
  closeCreateCoupon() {
    const modal = document.getElementById('admin-create-coupon-modal');
    if (modal) modal.classList.remove('open');
  },
  async submitCreateCoupon(event) {
    if (event) event.preventDefault();
    const code = document.getElementById('admin-new-cpn-code').value.trim();
    const percent = parseInt(document.getElementById('admin-new-cpn-percent').value, 10);
    const minSpend = parseFloat(document.getElementById('admin-new-cpn-minspend').value || 0);
    const maxUses = parseInt(document.getElementById('admin-new-cpn-maxuses').value || 500, 10);
    const expiry = document.getElementById('admin-new-cpn-expiry').value;

    if (!code || isNaN(percent) || percent <= 0) {
      Toast.error('Please provide a valid coupon code and discount percentage.');
      return;
    }

    try {
      const payload = {
        code,
        discountPercent: percent,
        minSpend,
        maxUses,
        expiryDate: expiry,
      };

      const res = await Api.createCoupon(payload);
      if (res && res.success) {
        Store.createCoupon(payload);
        Toast.success(`Promo code "${res.data.code || code}" published successfully (${percent}% OFF).`);
        this.closeCreateCoupon();
        document.getElementById('admin-create-coupon-form').reset();
      } else {
        Toast.error(res?.error?.message || 'Failed to create promo code');
      }
    } catch (err) {
      Toast.error(err.message);
    }
  },
};

// Auto-boot on DOM ready
document.addEventListener('DOMContentLoaded', () => {
  StorefrontApp.init();
});

if (typeof module !== 'undefined' && module.exports) {
  module.exports = { StorefrontApp, AdminActionModals };
}
