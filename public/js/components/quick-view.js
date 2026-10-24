/**
 * VAPEHAUS PRODUCT DETAIL & QUICK VIEW MODAL COMPONENT
 * Interactive variant matrix: flavor x nicotine strength with live pricing and stock clamping
 */

const QuickViewModal = {
  overlayEl: null,
  currentProduct: null,
  selectedVariant: null,
  quantity: 1,

  init() {
    this.overlayEl = document.getElementById('pdp-modal');
    if (!this.overlayEl) return;

    const closeBtn = this.overlayEl.querySelector('.modal-close-btn');
    if (closeBtn) {
      closeBtn.addEventListener('click', () => this.close());
    }

    this.overlayEl.addEventListener('click', (e) => {
      if (e.target === this.overlayEl) {
        this.close();
      }
    });
  },

  open(productId) {
    const product = Store.getProductById(productId);
    if (!product) return;

    this.currentProduct = product;
    this.selectedVariant = product.variants[0];
    this.quantity = 1;

    this.render();
    if (this.overlayEl) {
      this.overlayEl.classList.add('open');
    }
  },

  close() {
    if (this.overlayEl) {
      this.overlayEl.classList.remove('open');
    }
  },

  selectVariant(variantId) {
    const variant = this.currentProduct.variants.find((v) => v.id === variantId);
    if (variant) {
      this.selectedVariant = variant;
      if (this.quantity > variant.stock) {
        this.quantity = Math.max(1, variant.stock);
      }
      this.render();
    }
  },

  changeQuantity(delta) {
    if (!this.selectedVariant) return;
    const newQty = this.quantity + delta;
    if (newQty >= 1 && newQty <= this.selectedVariant.stock) {
      this.quantity = newQty;
      const qtyEl = document.getElementById('pdp-qty-val');
      if (qtyEl) qtyEl.textContent = this.quantity;
    } else if (newQty > this.selectedVariant.stock) {
      Toast.error(`Maximum available stock reached (${this.selectedVariant.stock}).`);
    }
  },

  render() {
    if (!this.overlayEl || !this.currentProduct || !this.selectedVariant) return;

    const p = this.currentProduct;
    const v = this.selectedVariant;

    const contentEl = document.getElementById('pdp-modal-content');
    if (!contentEl) return;

    const isOutOfStock = v.stock <= 0;
    const isLowStock = v.stock > 0 && v.stock < 10;

    contentEl.innerHTML = `
      <div class="pdp-grid">
        <div class="pdp-gallery">
          <div class="pdp-main-img">
            <div class="device-art" style="--c1: ${p.color1}; --c2: ${p.color2}"></div>
          </div>
          <div style="font-size: 0.78rem; color: var(--text-muted); text-align: center;">
            SKU: <b style="color: var(--text);">${v.sku}</b>
          </div>
        </div>

        <div class="pdp-info">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <div class="product-brand">${p.brand}</div>
            <span class="badge ${isOutOfStock ? 'badge-danger' : isLowStock ? 'badge-warning' : 'badge-success'}">
              ${isOutOfStock ? 'Out of Stock' : isLowStock ? `Low Stock (${v.stock} left)` : `In Stock (${v.stock} units)`}
            </span>
          </div>

          <h2>${p.name}</h2>
          
          <div class="product-rating">
            ★★★★★ <span>${p.rating} (${p.reviewsCount} verified reviews)</span>
          </div>

          <div class="pdp-price">
            ${Money.format(v.price)}
            ${v.originalPrice ? `<s style="font-size: 0.95rem; color: var(--text-muted); margin-left: 8px;">${Money.format(v.originalPrice)}</s>` : ''}
          </div>

          <p style="color: var(--text-muted); font-size: 0.9rem; line-height: 1.5; margin-bottom: 20px;">
            ${p.description}
          </p>

          <!-- Variant Selector: Flavors / Colors -->
          <div class="variant-picker-section">
            <div class="variant-picker-label">Select Flavor / Color:</div>
            <div class="variant-chips">
              ${p.variants.map((variant) => `
                <div class="variant-chip ${variant.id === v.id ? 'active' : ''}" 
                     onclick="QuickViewModal.selectVariant('${variant.id}')">
                  ${variant.flavor} (${variant.nicotine})
                </div>
              `).join('')}
            </div>
          </div>

          <!-- Quantity Selector -->
          <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px;">
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
        </div>
      </div>
    `;
  },

  handleAddToCart() {
    if (!this.selectedVariant) return;
    try {
      Store.addToCart(this.selectedVariant.id, this.quantity);
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
