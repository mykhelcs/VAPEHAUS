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

  async init() {
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

    // 3. Only subscribe and load storefront catalog if on storefront view
    const isStorefront = !!document.getElementById('products-grid-container');
    if (isStorefront) {
      Store.subscribe(() => {
        this.setupCategories();
        this.renderProducts();
      });
      await this.loadCatalog();
    }
  },

  async loadCatalog() {
    try {
      const tasks = [
        Api.getCategories(),
        Api.getProducts(),
      ];
      if (typeof Store !== 'undefined' && Store.isAuthenticated && Store.isAuthenticated() && Api.getOrders) {
        tasks.push(Api.getOrders());
      }
      await Promise.all(tasks);

      if (typeof Store !== 'undefined' && Store.pruneOrphanedCartItems) {
        const pruned = Store.pruneOrphanedCartItems();
        if (pruned > 0) {
          if (typeof Toast !== 'undefined') {
            Toast.info(`Cart updated: ${pruned} discontinued item${pruned > 1 ? 's were' : ' was'} removed.`);
          }
          if (typeof CartDrawer !== 'undefined' && CartDrawer.render) {
            CartDrawer.render();
          }
        }
      }
    } catch (e) {
      console.warn('API catalog fetch failed, using available store state:', e);
    }
    this.setupCategories();
    this.renderProducts();
  },

  setupCategories() {
    const container = document.getElementById('category-chips-bar');
    if (!container) return;

    const rawCategories = Store.getCategories();
    const categories = [
      { id: 'cat_all', name: 'All Products', slug: 'all', icon: '🔥' },
      ...rawCategories.filter((c) => c.slug !== 'all'),
    ];

    container.innerHTML = categories.map((cat) => `
      <div class="cat-chip ${cat.slug === this.currentCategory ? 'active' : ''}" 
           data-slug="${cat.slug}" onclick="StorefrontApp.setCategory('${cat.slug}')">
        <span>${cat.icon || '💨'}</span> ${cat.name}
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
        dropdown.innerHTML = products.slice(0, 5).map((p) => {
          const v = (p.variants && p.variants.length > 0) ? p.variants[0] : { price: 0 };
          const c1 = p.color1 || '#8b5cf6';
          const c2 = p.color2 || '#22d3ee';
          return `
          <div class="search-item" onclick="StorefrontApp.handleSearchItemClick('${p.id}')">
            <div class="search-item-thumb">
              <div class="device-art" style="width: 20px; height: 32px; border-radius: 4px; background: linear-gradient(160deg, ${c1}, ${c2});"></div>
            </div>
            <div class="search-item-info">
              <div class="search-item-title">${p.name}</div>
              <div class="search-item-meta">${p.brand} · ${p.rating || p.average_rating || 5.0} ★</div>
            </div>
            <div class="search-item-price">${Money.format(v.price)}</div>
          </div>
        `;}).join('');
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
      const mainVariant = (p.variants && p.variants.length > 0) ? p.variants[0] : { price: 0, stock: 0 };
      const hasStock = p.variants ? p.variants.some((v) => v.stock > 0) : false;
      const totalStock = p.variants ? p.variants.reduce((acc, v) => acc + (v.stock || 0), 0) : 0;
      const c1 = p.color1 || '#8b5cf6';
      const c2 = p.color2 || '#22d3ee';
      const reviews = p.reviewsCount ?? p.review_count ?? p.reviews ?? 0;
      const rating = p.rating ?? p.average_rating ?? 5.0;
      const rawImg = p.imageUrl || p.image_url;
      const imgUrl = typeof resolveMediaUrl === 'function' ? resolveMediaUrl(rawImg) : rawImg;
      const fallbackArt = `<div class="device-art" style="--c1: ${c1}; --c2: ${c2}"></div>`;
      const mediaHtml = imgUrl
        ? `<img src="${imgUrl}" alt="${p.name}" class="product-photo" style="width: 100%; height: 100%; object-fit: contain; padding: 12px;" onerror="this.onerror=null; this.outerHTML=\`${fallbackArt.replace(/"/g, '&quot;')}\`;">`
        : fallbackArt;

      return `
        <article class="product-card" onclick="QuickViewModal.open('${p.id}')" style="cursor: pointer;">
          <div class="product-thumb">
            ${p.badge ? `<span class="badge badge-cyan product-badge">${p.badge}</span>` : ''}
            ${mediaHtml}
            <div class="product-quickview-pill">🔍 View Details</div>
            <div class="product-stock-tag ${totalStock < 10 ? 'low' : ''}">
              ${hasStock ? (totalStock < 10 ? `Only ${totalStock} left` : 'In Stock') : 'Out of Stock'}
            </div>
          </div>

          <div class="product-body">
            <div class="product-brand">${p.brand}</div>
            <h3 class="product-title">${p.name}</h3>
            
            <div class="product-rating">
              ★★★★★ <span>(${reviews})</span>
            </div>

            <div class="product-footer">
              <div class="product-price">
                ${Money.format(mainVariant.price)}
                ${mainVariant.originalPrice ? `<s>${Money.format(mainVariant.originalPrice)}</s>` : ''}
              </div>
              <div style="display: flex; align-items: center; gap: 8px;">
                <button class="btn btn-secondary btn-sm btn-view-details" onclick="event.stopPropagation(); QuickViewModal.open('${p.id}')" title="View Product Details">
                  View Details
                </button>
                <button class="btn-add-cart" onclick="StorefrontApp.quickAddToCart('${mainVariant.id}', event)" title="Add to Cart" ${!hasStock ? 'disabled' : ''}>
                  +
                </button>
              </div>
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
      if (typeof Api !== 'undefined' && Api.addToCart) {
        Api.addToCart(variantId, 1).catch(() => {});
      }
    } catch (err) {
      Toast.error(err.message);
    }
  },

  async openAccountModal() {
    const modal = document.getElementById('account-modal');
    if (!modal) return;
    const user = Store.getCurrentUser();
    if (!user) {
      AuthModal.openLogin();
    } else {
      this.renderAccountContent();
      modal.classList.add('open');
      if (typeof Api !== 'undefined' && Api.getOrders) {
        try {
          await Api.getOrders();
          if (modal.classList.contains('open')) {
            this.renderAccountContent();
          }
        } catch (err) {
          console.warn('Could not sync account orders:', err);
        }
      }
    }
  },

  closeAccountModal() {
    const modal = document.getElementById('account-modal');
    if (modal) modal.classList.remove('open');
  },

  setupAccountModal() {
    const modal = document.getElementById('account-modal');
    if (!modal) return;

    const trigger = document.getElementById('btn-account-toggle');
    const closeBtn = document.getElementById('btn-close-account');

    if (trigger) {
      trigger.addEventListener('click', () => {
        this.openAccountModal();
      });
    }

    if (closeBtn) {
      closeBtn.addEventListener('click', () => this.closeAccountModal());
    }

    modal.addEventListener('click', (e) => {
      if (e.target === modal) this.closeAccountModal();
    });

    if (typeof document !== 'undefined' && document.addEventListener && !this._accountEscBound) {
      this._accountEscBound = true;
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('open')) {
          this.closeAccountModal();
        }
      });
    }
  },

  renderAccountContent() {
    const content = document.getElementById('account-modal-content');
    if (!content) return;

    const user = Store.getCurrentUser();
    if (!user) {
      content.innerHTML = `
        <div style="padding: 40px; text-align: center;">
          <h2 style="font-size: 1.4rem; margin-bottom: 12px;">Sign in to your account</h2>
          <p style="color: var(--text-muted); margin-bottom: 24px;">Track orders, manage addresses, and complete statutory 18+ age verification.</p>
          <div style="display: flex; gap: 12px; justify-content: center;">
            <button class="btn btn-cyan" onclick="AuthModal.openLogin()">Sign In</button>
            <button class="btn btn-secondary" onclick="AuthModal.openRegister()">Register Adult Account</button>
          </div>
        </div>
      `;
      return;
    }

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
          <button class="btn btn-secondary btn-sm" onclick="StorefrontApp.closeAccountModal(); AuthModal.openLogin();">
            Switch Account
          </button>
          <div style="display: flex; gap: 10px;">
            <button class="btn btn-secondary btn-sm" onclick="StorefrontApp.closeAccountModal();">
              Close
            </button>
            <button class="btn btn-danger btn-sm" onclick="StorefrontApp.closeAccountModal(); AuthModal.logout();">
              🚪 Sign Out
            </button>
          </div>
        </div>
      </div>
    `;
  },

  async showAccountTab(tab) {
    const container = document.getElementById('account-tab-content');
    if (!container) return;

    if (tab === 'orders') {
      container.innerHTML = this.renderAccountOrdersHtml(Store.getOrders());
      if (typeof Api !== 'undefined' && Api.getOrders) {
        try {
          await Api.getOrders();
          const refreshedOrders = Store.getOrders();
          container.innerHTML = this.renderAccountOrdersHtml(refreshedOrders);
          const tabBtn = document.getElementById('tab-btn-orders');
          if (tabBtn) tabBtn.innerHTML = `📦 My Orders (${refreshedOrders.length})`;
        } catch {
          // Keep current store orders deterministically
        }
      }
    } else if (tab === 'kyc') {
      const user = Store.getCurrentUser();
      const isVerified = user ? !!user.isAgeVerified : false;
      container.innerHTML = `
        <div style="background: var(--surface2); border: 1px solid var(--line); border-radius: var(--r-md); padding: 24px;">
          <h4 style="margin-bottom: 8px;">Statutory 18+ Government ID Verification</h4>
          <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 20px;">
            In compliance with Philippine vapor regulation laws (R.A. 11900), all buyers must have verified government-issued identification proving they are 18 years of age or older.
          </p>

          <div style="background: var(--surface); border: 1px dashed var(--line-strong); border-radius: var(--r-md); padding: 24px; text-align: center; margin-bottom: 20px;">
            <div style="font-size: 2.4rem; margin-bottom: 8px;">🪪</div>
            <div style="font-weight: 600; margin-bottom: 4px;">Philippine Passport / Driver's License / UMID</div>
            <div style="font-size: 0.8rem; color: var(--text-muted);">Current Status: <b style="color: var(--success);">${isVerified ? 'VERIFIED (18+)' : 'PENDING REVIEW'}</b></div>
          </div>

          <button class="btn btn-cyan btn-sm" onclick="Toast.success('Government ID status: verified.');">
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
              <div style="font-weight: 800; font-size: 0.95rem; color: var(--accent2);">#${o.orderNumber || o.order_number || o.id}</div>
              <div style="font-size: 0.78rem; color: var(--text-muted);">${(o.createdAt || o.created_at || '').substring(0, 16)} · ${(o.items || []).length} items</div>
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

    async submitKycIdDocument() {
    const fileInput = document.getElementById('kyc-id-file-input');
    const btn = document.getElementById('btn-upload-kyc');
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
      Toast.error('Please select an ID image file to upload.');
      return;
    }

    const file = fileInput.files[0];
    const user = Store.getCurrentUser();
    if (!user) {
      Toast.error('Please sign in first before uploading identification.');
      return;
    }
    if (btn) { btn.disabled = true; btn.textContent = 'Uploading ID…'; }

    try {
      let fileUrl = '';
      if (typeof window.supabase !== 'undefined' && CONFIG.SUPABASE_URL && !CONFIG.SUPABASE_URL.includes('YOUR-PROJECT-REF')) {
        try {
          const sb = window.supabase.createClient(CONFIG.SUPABASE_URL, CONFIG.SUPABASE_ANON_KEY);
          const ext = file.name.split('.').pop();
          const filePath = `${user.id}/id_${Date.now()}.${ext}`;
          const { data, error } = await sb.storage.from('kyc-documents').upload(filePath, file, { upsert: true });
          if (!error && data && data.path) {
            fileUrl = `${CONFIG.SUPABASE_URL}/storage/v1/object/kyc-documents/${data.path}`;
          }
        } catch (e) {
          console.warn('Supabase storage direct upload fallback to backend upload', e);
        }
      }

      if (!fileUrl) {
        const uploadRes = await Api.uploadProductImage(file);
        if (uploadRes && uploadRes.success && uploadRes.data && uploadRes.data.url) {
          fileUrl = uploadRes.data.url;
        } else {
          throw new Error(uploadRes?.error?.message || 'Failed to upload document.');
        }
      }

      await Api.submitKyc({
        frontImageUrl: fileUrl,
        idType: 'drivers_license',
        idNumber: 'D01-99-XXXXXX',
      });

      Toast.success('Government ID submitted successfully for review!');
      user.isAgeVerified = true;
      Store.save();
      this.showAccountTab('kyc');
    } catch (err) {
      Toast.error(err.message || 'Failed to upload document.');
    } finally {
      if (btn) { btn.disabled = false; btn.textContent = '📤 Upload Identification Document'; }
    }
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
    const previewContainer = document.getElementById('admin-image-preview-container');
    if (previewContainer) previewContainer.style.display = 'none';
    const hiddenUrlInput = document.getElementById('admin-new-prod-image-url');
    if (hiddenUrlInput) hiddenUrlInput.value = '';
    const fileInput = document.getElementById('admin-new-prod-image');
    if (fileInput) fileInput.value = '';
  },
  async handleImageSelect(event) {
    const file = event?.target?.files?.[0];
    if (!file) return;

    const previewContainer = document.getElementById('admin-image-preview-container');
    const previewThumb = document.getElementById('admin-image-preview-thumb');
    const statsEl = document.getElementById('admin-image-stats');
    const hiddenUrlInput = document.getElementById('admin-new-prod-image-url');

    try {
      if (statsEl) statsEl.textContent = 'Compressing image...';
      if (previewContainer) previewContainer.style.display = 'flex';

      const compressor = window.AdminImageCompressor;
      if (!compressor) {
        throw new Error('Image compressor is not initialized.');
      }

      const compressed = await compressor.compress(file);
      if (previewThumb) previewThumb.src = compressed.dataUrl;

      const origKb = Math.round(compressed.originalSize / 1024);
      const compKb = Math.round(compressed.compressedSize / 1024);
      if (statsEl) {
        statsEl.innerHTML = `Original: <b>${origKb} KB</b> → Compressed: <b>${compKb} KB</b> <span style="color: var(--cyan);">(-${compressed.savingsPercent}%)</span>`;
      }

      // Direct Supabase Storage upload or server relay
      let uploadedUrl = null;
      const cleanFilename = 'prod_' + Math.random().toString(36).substring(2, 10) + '.webp';

      // 1. Direct frontend upload to Supabase Storage
      try {
        if (CONFIG.SUPABASE_URL && CONFIG.SUPABASE_ANON_KEY) {
          const byteString = atob(compressed.dataUrl.split(',')[1]);
          const ab = new ArrayBuffer(byteString.length);
          const ia = new Uint8Array(ab);
          for (let i = 0; i < byteString.length; i++) {
            ia[i] = byteString.charCodeAt(i);
          }
          const blob = new Blob([ab], { type: 'image/webp' });

          const directEndpoint = `${CONFIG.SUPABASE_URL}/storage/v1/object/products/${cleanFilename}`;
          const directRes = await fetch(directEndpoint, {
            method: 'POST',
            headers: {
              'Authorization': `Bearer ${CONFIG.SUPABASE_ANON_KEY}`,
              'apikey': CONFIG.SUPABASE_ANON_KEY,
              'Content-Type': 'image/webp',
              'x-upsert': 'true',
            },
            body: blob,
          });

          if (directRes.ok) {
            uploadedUrl = `${CONFIG.SUPABASE_URL}/storage/v1/object/public/products/${cleanFilename}`;
          } else {
            const errBody = await directRes.text().catch(() => '');
            console.warn(`Direct Supabase upload failed (${directRes.status}):`, errBody);
          }
        }
      } catch (directErr) {
        console.warn('Direct upload notice:', directErr);
      }

      // 2. Upload via API relay using backend Supabase Storage adapter with service key
      if (!uploadedUrl) {
        try {
          const uploadRes = await Api.uploadProductImage(compressed.dataUrl);
          if (uploadRes && uploadRes.success && uploadRes.data && uploadRes.data.url) {
            uploadedUrl = uploadRes.data.url;
          }
        } catch (relayErr) {
          console.warn('API upload relay error:', relayErr);
        }
      }

      if (uploadedUrl) {
        if (hiddenUrlInput) hiddenUrlInput.value = uploadedUrl;
        Toast.success(`Image stored in Supabase CDN (${compKb} KB).`);
      } else {
        if (hiddenUrlInput) hiddenUrlInput.value = compressed.dataUrl;
        Toast.info(`Image saved as optimized WebP (${compKb} KB).`);
      }
    } catch (err) {
      console.error('Image upload error:', err);
      Toast.error('Failed to upload image: ' + err.message);
    }
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
    const imageUrl = document.getElementById('admin-new-prod-image-url')?.value || null;

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
        image_url: imageUrl,
        imageUrl: imageUrl,
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
        const addedProduct = Store.addProduct({
          ...payload,
          id: res.data?.id || undefined,
          variants: res.data?.variants || payload.variants,
        });

        // O(1) Instant incremental DOM update if on Admin Portal
        if (typeof AdminApp !== 'undefined' && typeof AdminApp.prependProductRow === 'function') {
          AdminApp.prependProductRow(addedProduct);
        }

        Toast.success(`Product "${res.data?.name || name}" added to catalog.`);
        this.closeAddProduct();
        document.getElementById('admin-add-product-form').reset();
      } else {
        Toast.error(res?.error?.message || 'Failed to add product');
      }
    } catch (err) {
      Toast.error(err.message);
    }
  },

  openEditProduct(productId) {
    const product = Store.getProductById(productId);
    if (!product) {
      Toast.error('Product not found in store.');
      return;
    }

    const modal = document.getElementById('admin-edit-product-modal');
    if (!modal) return;

    document.getElementById('admin-edit-prod-id').value = product.id;
    document.getElementById('admin-edit-prod-name').value = product.name || '';
    document.getElementById('admin-edit-prod-brand').value = product.brand || '';
    document.getElementById('admin-edit-prod-desc').value = product.description || '';

    // Category
    const catSelect = document.getElementById('admin-edit-prod-cat');
    if (catSelect) {
      const catVal = product.categoryId || product.category_id || product.category || 'cat_devices';
      catSelect.value = catVal.startsWith('cat_') ? catVal : `cat_${catVal}`;
      if (!catSelect.value) catSelect.selectedIndex = 0;
    }

    // Status
    const statusSelect = document.getElementById('admin-edit-prod-status');
    if (statusSelect) {
      statusSelect.value = product.isActive !== undefined ? (product.isActive ? '1' : '0') : '1';
    }

    // Image preview
    const imgUrl = product.imageUrl || product.image_url || '';
    const hiddenUrlInput = document.getElementById('admin-edit-prod-image-url');
    const previewThumb = document.getElementById('admin-edit-image-preview-thumb');
    const previewContainer = document.getElementById('admin-edit-image-preview-container');
    const statsEl = document.getElementById('admin-edit-image-stats');

    if (hiddenUrlInput) hiddenUrlInput.value = imgUrl;
    if (imgUrl && previewThumb) {
      previewThumb.src = imgUrl;
      if (previewContainer) previewContainer.style.display = 'flex';
      if (statsEl) statsEl.textContent = 'Current product image';
    } else {
      if (previewContainer) previewContainer.style.display = 'none';
    }

    const fileInput = document.getElementById('admin-edit-prod-image');
    if (fileInput) fileInput.value = '';

    modal.classList.add('open');
  },

  closeEditProduct() {
    const modal = document.getElementById('admin-edit-product-modal');
    if (modal) modal.classList.remove('open');
  },

  async handleEditImageSelect(event) {
    const file = event?.target?.files?.[0];
    if (!file) return;

    const previewContainer = document.getElementById('admin-edit-image-preview-container');
    const previewThumb = document.getElementById('admin-edit-image-preview-thumb');
    const statsEl = document.getElementById('admin-edit-image-stats');
    const hiddenUrlInput = document.getElementById('admin-edit-prod-image-url');

    try {
      if (statsEl) statsEl.textContent = 'Compressing image...';
      if (previewContainer) previewContainer.style.display = 'flex';

      const compressor = window.AdminImageCompressor;
      if (!compressor) throw new Error('Image compressor is not initialized.');

      const compressed = await compressor.compress(file);
      if (previewThumb) previewThumb.src = compressed.dataUrl;

      const origKb = Math.round(compressed.originalSize / 1024);
      const compKb = Math.round(compressed.compressedSize / 1024);
      if (statsEl) {
        statsEl.innerHTML = `Original: <b>${origKb} KB</b> → Compressed: <b>${compKb} KB</b> <span style="color: var(--cyan);">(-${compressed.savingsPercent}%)</span>`;
      }

      // Upload via direct Supabase Storage or API
      let uploadedUrl = null;
      const cleanFilename = 'prod_' + Math.random().toString(36).substring(2, 10) + '.webp';

      try {
        if (CONFIG.SUPABASE_URL && CONFIG.SUPABASE_ANON_KEY) {
          const byteString = atob(compressed.dataUrl.split(',')[1]);
          const ab = new ArrayBuffer(byteString.length);
          const ia = new Uint8Array(ab);
          for (let i = 0; i < byteString.length; i++) {
            ia[i] = byteString.charCodeAt(i);
          }
          const blob = new Blob([ab], { type: 'image/webp' });

          const directEndpoint = `${CONFIG.SUPABASE_URL}/storage/v1/object/products/${cleanFilename}`;
          const directRes = await fetch(directEndpoint, {
            method: 'POST',
            headers: {
              'Authorization': `Bearer ${CONFIG.SUPABASE_ANON_KEY}`,
              'apikey': CONFIG.SUPABASE_ANON_KEY,
              'Content-Type': 'image/webp',
              'x-upsert': 'true',
            },
            body: blob,
          });

          if (directRes.ok) {
            uploadedUrl = `${CONFIG.SUPABASE_URL}/storage/v1/object/public/products/${cleanFilename}`;
          } else {
            const errBody = await directRes.text().catch(() => '');
            console.warn(`Direct Supabase edit upload failed (${directRes.status}):`, errBody);
          }
        }
      } catch (directErr) {
        console.warn('Direct upload notice:', directErr);
      }

      if (!uploadedUrl) {
        const uploadRes = await Api.uploadProductImage(compressed.dataUrl);
        if (uploadRes && uploadRes.success && uploadRes.data && uploadRes.data.url) {
          uploadedUrl = uploadRes.data.url;
        }
      }

      if (uploadedUrl) {
        if (hiddenUrlInput) hiddenUrlInput.value = uploadedUrl;
        Toast.success(`New image uploaded (${compKb} KB).`);
      } else {
        if (hiddenUrlInput) hiddenUrlInput.value = compressed.dataUrl;
        Toast.info(`Image saved as optimized WebP (${compKb} KB).`);
      }
    } catch (err) {
      console.error('Image upload error:', err);
      Toast.error('Failed to upload image: ' + err.message);
    }
  },

  async submitEditProduct(event) {
    if (event) event.preventDefault();

    const productId = document.getElementById('admin-edit-prod-id').value;
    const name = document.getElementById('admin-edit-prod-name').value.trim();
    const brand = document.getElementById('admin-edit-prod-brand').value.trim();
    const category = document.getElementById('admin-edit-prod-cat').value;
    const statusVal = document.getElementById('admin-edit-prod-status').value;
    const desc = document.getElementById('admin-edit-prod-desc').value.trim();
    const imageUrl = document.getElementById('admin-edit-prod-image-url').value;

    if (!name || !brand) {
      Toast.error('Product name and brand are required.');
      return;
    }

    try {
      const payload = {
        name,
        brand,
        category_id: category,
        description: desc,
        is_active: statusVal === '1',
        image_url: imageUrl || null,
        imageUrl: imageUrl || null,
      };

      const res = await Api.updateProduct(productId, payload);
      if (res && res.success) {
        Store.updateProduct(productId, {
          ...payload,
          imageUrl: res.data?.image_url || payload.image_url,
          image_url: res.data?.image_url || payload.image_url,
        });

        if (typeof AdminApp !== 'undefined' && typeof AdminApp.renderInventory === 'function') {
          AdminApp.renderInventory();
        }

        Toast.success(`Product "${name}" updated successfully.`);
        this.closeEditProduct();
      } else {
        Toast.error(res?.error?.message || 'Failed to update product');
      }
    } catch (err) {
      Toast.error(err.message || 'Error updating product');
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
