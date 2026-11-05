/**
 * VAPEHAUS PRODUCT DETAILS & QUICK VIEW MODAL COMPONENT
 * Interactive variant matrix, specifications grid, verified reviews, live pricing & stock clamping
 */

const QuickViewModal = {
  overlayEl: null,
  currentProduct: null,
  selectedVariant: null,
  reviews: [],
  quantity: 1,
  _escBound: false,

  init() {
    this.overlayEl = document.getElementById('pdp-modal');
    if (!this.overlayEl) return;

    const closeBtn = document.getElementById('btn-close-pdp') || this.overlayEl.querySelector('.modal-close-btn');
    if (closeBtn) {
      closeBtn.addEventListener('click', () => this.close());
    }

    this.overlayEl.addEventListener('click', (e) => {
      if (e.target === this.overlayEl) {
        this.close();
      }
    });

    if (!this._escBound && typeof document !== 'undefined' && document.addEventListener) {
      this._escBound = true;
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && this.overlayEl && this.overlayEl.classList.contains('open')) {
          this.close();
        }
      });
    }
  },

  async open(productId) {
    if (!this.overlayEl) {
      this.overlayEl = document.getElementById('pdp-modal');
    }

    let product = Store.getProductById(productId) || (Store.getProductBySlug ? Store.getProductBySlug(productId) : null);
    if (!product && typeof Api !== 'undefined' && Api.getProductDetail) {
      try {
        const res = await Api.getProductDetail(productId);
        if (res && res.success && res.data) {
          product = res.data;
        }
      } catch (err) {
        console.warn('Failed to fetch product detail:', err);
      }
    }

    if (!product) {
      if (typeof Toast !== 'undefined') Toast.error('Product details could not be found.');
      return;
    }

    this.currentProduct = product;
    const variants = Array.isArray(product.variants) ? product.variants : [];
    this.selectedVariant = variants.find((v) => (v.stock ?? v.stock_quantity ?? 0) > 0) || variants[0] || null;
    this.reviews = [];
    this.quantity = 1;

    this.render();
    if (this.overlayEl) {
      this.overlayEl.classList.add('open');
    }

    // Fetch verified reviews asynchronously if API client supports it
    if (typeof Api !== 'undefined' && Api.getProductReviews) {
      try {
        const revRes = await Api.getProductReviews(product.id);
        if (revRes && revRes.success && Array.isArray(revRes.data) && this.currentProduct && this.currentProduct.id === product.id) {
          this.reviews = revRes.data;
          this.render();
        }
      } catch {
        // Keep empty reviews list deterministically
      }
    }
  },

  close() {
    if (this.overlayEl) {
      this.overlayEl.classList.remove('open');
    }
  },

  selectVariant(variantId) {
    if (!this.currentProduct || !Array.isArray(this.currentProduct.variants)) return;
    const variant = this.currentProduct.variants.find((v) => v.id === variantId);
    if (variant) {
      const availStock = variant.stock ?? variant.stock_quantity ?? 0;
      this.selectedVariant = variant;
      if (this.quantity > availStock) {
        this.quantity = Math.max(1, availStock);
      }
      this.render();
    }
  },

  changeQuantity(delta) {
    if (!this.selectedVariant) return;
    const availStock = this.selectedVariant.stock ?? this.selectedVariant.stock_quantity ?? 0;
    const newQty = this.quantity + delta;
    if (newQty >= 1 && newQty <= availStock) {
      this.quantity = newQty;
      const qtyEl = document.getElementById('pdp-qty-val');
      if (qtyEl) qtyEl.textContent = this.quantity;
    } else if (newQty > availStock) {
      Toast.error(`Maximum available stock reached (${availStock}).`);
    }
  },

  _formatCategoryName(cat) {
    const raw = String(cat || 'vape-hardware').replace(/^cat_/, '').replace(/[-_]/g, ' ');
    return raw.replace(/\b\w/g, (ch) => ch.toUpperCase());
  },

  render() {
    if (!this.overlayEl || !this.currentProduct) return;

    const p = this.currentProduct;
    const variants = Array.isArray(p.variants) ? p.variants : [];
    const v = this.selectedVariant || {
      id: '',
      sku: 'N/A',
      flavor: 'Standard',
      nicotine: '0mg',
      price: 0,
      stock: 0,
    };

    const contentEl = document.getElementById('pdp-modal-content');
    if (!contentEl) return;

    const stockQty = v.stock ?? v.stock_quantity ?? 0;
    const nicotineLabel = v.nicotine || v.nicotine_strength || '0mg';
    const isOutOfStock = stockQty <= 0 || !v.id;
    const isLowStock = stockQty > 0 && stockQty < 10;
    const rawImg = p.imageUrl || p.image_url;
    const imgUrl = typeof resolveMediaUrl === 'function' ? resolveMediaUrl(rawImg) : rawImg;
    const fallbackArt = `<div class="device-art" style="--c1: ${p.color1 || '#8b5cf6'}; --c2: ${p.color2 || '#22d3ee'}"></div>`;
    const mediaHtml = imgUrl
      ? `<img src="${imgUrl}" alt="${p.name}" style="width: 100%; height: 100%; max-height: 280px; object-fit: contain; border-radius: var(--r-md);" onerror="this.onerror=null; this.outerHTML=\`${fallbackArt.replace(/"/g, '&quot;')}\`;">`
      : fallbackArt;

    const categoryLabel = this._formatCategoryName(p.category || p.category_id);
    const descriptionText = (p.description && String(p.description).trim() !== '' && String(p.description) !== 'null')
      ? p.description
      : `Authentic ${p.brand} ${p.name} engineered for smooth vapor production, consistent flavor delivery, and strict quality compliance.`;

    const reviewCount = this.reviews.length > 0
      ? this.reviews.length
      : (p.reviewsCount ?? p.review_count ?? 0);
    const ratingVal = Number(p.rating ?? p.average_rating ?? 5.0).toFixed(1);

    const reviewsHtml = this.reviews.length > 0
      ? `
        <div style="margin-top: 22px; padding-top: 18px; border-top: 1px solid var(--line);">
          <div style="font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: 10px;">
            Verified Customer Reviews (${this.reviews.length})
          </div>
          <div style="display: flex; flex-direction: column; gap: 10px; max-height: 160px; overflow-y: auto;">
            ${this.reviews.slice(0, 4).map((rev) => `
              <div style="background: var(--surface2); border: 1px solid var(--line); border-radius: var(--r-md); padding: 10px 12px; font-size: 0.84rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                  <b style="color: var(--text);">${rev.title || 'Verified Buyer'}</b>
                  <span style="color: #fbbf24; font-size: 0.78rem;">${'★'.repeat(Math.max(1, Math.min(5, Number(rev.rating) || 5)))}</span>
                </div>
                <div style="color: var(--text-muted); line-height: 1.4;">${rev.comment || rev.body || ''}</div>
              </div>
            `).join('')}
          </div>
        </div>
      `
      : '';

    contentEl.innerHTML = `
      <div class="pdp-grid">
        <div class="pdp-gallery">
          <div class="pdp-main-img">
            ${mediaHtml}
          </div>
          <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.78rem; color: var(--text-muted); background: var(--surface2); padding: 8px 12px; border-radius: var(--r-md); border: 1px solid var(--line);">
            <span>SKU: <b style="color: var(--text);">${v.sku || 'N/A'}</b></span>
            <span class="badge badge-cyan" style="font-size: 0.7rem;">${categoryLabel}</span>
          </div>

          <!-- Specifications Summary -->
          <div class="pdp-specs" style="background: var(--surface2); border: 1px solid var(--line); border-radius: var(--r-md); padding: 12px 14px; font-size: 0.8rem;">
            <div style="font-weight: 700; color: var(--text); margin-bottom: 8px; text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.06em;">
              Product Specifications
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px 12px; color: var(--text-muted);">
              <div>Brand: <b style="color: var(--text);">${p.brand}</b></div>
              <div>Category: <b style="color: var(--text);">${categoryLabel}</b></div>
              <div>Edition: <b style="color: var(--text);">${v.flavor || 'Standard'}</b></div>
              <div>Strength: <b style="color: var(--text);">${nicotineLabel}</b></div>
              <div>Stock: <b style="color: var(--text);">${stockQty} units</b></div>
              <div>Compliance: <b style="color: var(--accent2);">18+ R.A. 11900</b></div>
            </div>
          </div>
        </div>

        <div class="pdp-info">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <div class="product-brand">${p.brand}</div>
            <span class="badge ${isOutOfStock ? 'badge-danger' : isLowStock ? 'badge-warning' : 'badge-success'}">
              ${isOutOfStock ? 'Out of Stock' : isLowStock ? `Low Stock (${stockQty} left)` : `In Stock (${stockQty} units)`}
            </span>
          </div>

          <h2>${p.name}</h2>

          <div class="product-rating">
            ★★★★★ <span>${ratingVal} (${reviewCount} verified reviews)</span>
          </div>

          <div class="pdp-price">
            ${Money.format(v.price)}
            ${v.originalPrice ? `<s style="font-size: 0.95rem; color: var(--text-muted); margin-left: 8px;">${Money.format(v.originalPrice)}</s>` : ''}
          </div>

          <p style="color: var(--text-muted); font-size: 0.9rem; line-height: 1.55; margin-bottom: 18px;">
            ${descriptionText}
          </p>

          ${variants.length > 0 ? `
            <!-- Variant Selector: Flavors / Colors -->
            <div class="variant-picker-section">
              <div class="variant-picker-label">Select Flavor / Edition (${variants.length} available):</div>
              <div class="variant-chips">
                ${variants.map((variant) => {
                  const varStock = variant.stock ?? variant.stock_quantity ?? 0;
                  const varNic = variant.nicotine || variant.nicotine_strength || '0mg';
                  return `
                    <div class="variant-chip ${variant.id === v.id ? 'active' : ''}" 
                         style="${varStock <= 0 ? 'opacity: 0.55;' : ''}"
                         onclick="QuickViewModal.selectVariant('${variant.id}')">
                      ${variant.flavor} (${varNic})${varStock <= 0 ? ' · Sold Out' : ''}
                    </div>
                  `;
                }).join('')}
              </div>
            </div>
          ` : ''}

          <!-- Quantity Selector -->
          <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 22px;">
            <div class="input-label" style="margin: 0;">Quantity:</div>
            <div class="qty-control" style="padding: 4px 12px;">
              <button class="qty-btn" onclick="QuickViewModal.changeQuantity(-1)" ${isOutOfStock ? 'disabled' : ''}>−</button>
              <span class="qty-value" id="pdp-qty-val" style="min-width: 28px; font-size: 1rem;">${this.quantity}</span>
              <button class="qty-btn" onclick="QuickViewModal.changeQuantity(1)" ${isOutOfStock ? 'disabled' : ''}>+</button>
            </div>
          </div>

          <!-- Action Buttons -->
          <div style="display: flex; gap: 12px;">
            <button class="btn btn-lg btn-block" onclick="QuickViewModal.handleAddToCart()" ${isOutOfStock ? 'disabled' : ''}>
              🛒 Add to Cart
            </button>
            <button class="btn btn-lg btn-cyan btn-block" onclick="QuickViewModal.handleBuyNow()" ${isOutOfStock ? 'disabled' : ''}>
              ⚡ Buy Now
            </button>
          </div>

          ${reviewsHtml}
        </div>
      </div>
    `;
  },

  handleAddToCart() {
    if (!this.selectedVariant) return;
    try {
      Store.addToCart(this.selectedVariant.id, this.quantity);
      if (typeof Api !== 'undefined' && Api.addToCart) {
        Api.addToCart(this.selectedVariant.id, this.quantity).catch(() => {});
      }
      Toast.success(`Added ${this.quantity}x ${this.currentProduct.name} to cart.`);
      this.close();
      if (typeof CartDrawer !== 'undefined') {
        CartDrawer.open();
      }
    } catch (err) {
      Toast.error(err.message);
    }
  },

  handleBuyNow() {
    if (!this.selectedVariant) return;
    try {
      Store.addToCart(this.selectedVariant.id, this.quantity);
      if (typeof Api !== 'undefined' && Api.addToCart) {
        Api.addToCart(this.selectedVariant.id, this.quantity).catch(() => {});
      }
      this.close();
      if (typeof CheckoutModal !== 'undefined') {
        CheckoutModal.open();
      }
    } catch (err) {
      Toast.error(err.message);
    }
  },
};

if (typeof module !== 'undefined' && module.exports) {
  module.exports = QuickViewModal;
}
