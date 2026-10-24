/**
 * VAPEHAUS SLIDE-OUT CART DRAWER COMPONENT
 * Interactive side drawer with line item adjustments, coupon validation, and checkout trigger
 */

const CartDrawer = {
  overlayEl: null,

  init() {
    this.overlayEl = document.getElementById('cart-drawer-overlay');
    if (!this.overlayEl) return;

    const closeBtn = document.getElementById('btn-close-cart');
    if (closeBtn) {
      closeBtn.addEventListener('click', () => this.close());
    }

    this.overlayEl.addEventListener('click', (e) => {
      if (e.target === this.overlayEl) {
        this.close();
      }
    });

    const triggerBtn = document.getElementById('btn-cart-toggle');
    if (triggerBtn) {
      triggerBtn.addEventListener('click', () => this.open());
    }

    // Subscribe to store updates to keep drawer and badge synchronized
    Store.subscribe(() => {
      this.render();
      this.updateBadge();
    });

    this.render();
    this.updateBadge();
  },

  open() {
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

  updateBadge() {
    const badgeEl = document.getElementById('cart-badge-counter');
    if (!badgeEl) return;
    const items = Store.getState().cart.items;
    const totalQty = items.reduce((acc, it) => acc + it.quantity, 0);
    badgeEl.textContent = totalQty;
    badgeEl.style.display = totalQty > 0 ? 'grid' : 'none';
  },

  render() {
    const itemsContainer = document.getElementById('cart-items-container');
    const summaryContainer = document.getElementById('cart-summary-container');
    const freeShippingEl = document.getElementById('free-shipping-tracker');
    if (!itemsContainer || !summaryContainer) return;

    const items = Store.getCartItemsDetailed();
    const totals = Store.getCartTotals();
    const appliedCoupon = Store.getState().cart.appliedCouponCode;

    // Free Shipping Progress calculation
    if (freeShippingEl) {
      const threshold = CONFIG.SHIPPING.FREE_SHIPPING_THRESHOLD;
      const subtotal = totals.subtotal;
      const percent = Math.min(100, Math.round((subtotal / threshold) * 100));
      const remaining = Math.max(0, threshold - subtotal);

      freeShippingEl.innerHTML = `
        <div class="free-shipping-text">
          <span>${remaining === 0 ? '🎉 You unlocked <b>FREE Shipping!</b>' : `Add <b>${Money.format(remaining)}</b> more for FREE Shipping!`}</span>
          <span>${percent}%</span>
        </div>
        <div class="progress-bar-bg">
          <div class="progress-bar-fill" style="width: ${percent}%;"></div>
        </div>
      `;
    }

    // Render items list
    if (items.length === 0) {
      itemsContainer.innerHTML = `
        <div class="cart-empty-state">
          <span>🛒</span>
          <h3>Your cart is empty</h3>
          <p style="font-size: 0.88rem; margin: 8px 0 20px;">Discover authentic devices & premium e-liquids.</p>
          <button class="btn btn-secondary" onclick="CartDrawer.close()">Start Shopping</button>
        </div>
      `;
      summaryContainer.innerHTML = '';
      return;
    }

    itemsContainer.innerHTML = items.map((item) => `
      <div class="cart-item-row">
        <div class="cart-item-thumb">
          <div class="device-art" style="width: 22px; height: 38px; border-radius: 6px; background: linear-gradient(160deg, ${item.color1 || '#8b5cf6'}, ${item.color2 || '#22d3ee'});"></div>
        </div>
        <div class="cart-item-details">
          <div class="cart-item-title">${item.productName}</div>
          <div class="cart-item-variant">${item.flavor} · ${item.nicotine}</div>
          <div class="cart-item-actions">
            <div class="qty-control">
              <button class="qty-btn" onclick="CartDrawer.changeQty('${item.variantId}', ${item.quantity - 1})">−</button>
              <span class="qty-value">${item.quantity}</span>
              <button class="qty-btn" onclick="CartDrawer.changeQty('${item.variantId}', ${item.quantity + 1})">+</button>
            </div>
            <div style="display: flex; align-items: center;">
              <span class="cart-item-price">${Money.format(item.lineTotal)}</span>
              <span class="btn-remove-item" onclick="CartDrawer.removeItem('${item.variantId}')" title="Remove item">✕</span>
            </div>
          </div>
        </div>
      </div>
    `).join('');

    // Render summary & coupon form
    summaryContainer.innerHTML = `
      <div class="coupon-row">
        <input type="text" id="cart-coupon-input" placeholder="Promo code (e.g. HAUS10)" value="${appliedCoupon || ''}" ${appliedCoupon ? 'disabled' : ''}>
        ${appliedCoupon 
          ? `<button class="btn btn-secondary btn-sm" onclick="CartDrawer.removeCoupon()">Remove</button>`
          : `<button class="btn btn-secondary btn-sm" onclick="CartDrawer.applyCoupon()">Apply</button>`
        }
      </div>

      <div class="cart-summary-line">
        <span>Subtotal</span>
        <span>${totals.subtotalFormatted}</span>
      </div>

      ${totals.discountAmount > 0 ? `
        <div class="cart-summary-line" style="color: var(--success);">
          <span>Promo Discount (${appliedCoupon})</span>
          <span>-${totals.discountFormatted}</span>
        </div>
      ` : ''}

      <div class="cart-summary-line">
        <span>Shipping Fee</span>
        <span>${totals.shippingFormatted}</span>
      </div>

      <div class="cart-summary-line total">
        <span>Estimated Total</span>
        <span>${totals.totalFormatted}</span>
      </div>

      <button class="btn btn-lg btn-block" style="margin-top: 16px;" onclick="CartDrawer.proceedToCheckout()">
        Proceed to Checkout →
      </button>
    `;
  },

  changeQty(variantId, newQty) {
    try {
      Store.updateCartQuantity(variantId, newQty);
    } catch (err) {
      Toast.error(err.message);
    }
  },

  removeItem(variantId) {
    Store.removeFromCart(variantId);
    Toast.info('Item removed from cart.');
  },

  applyCoupon() {
    const input = document.getElementById('cart-coupon-input');
    if (!input) return;
    const code = input.value.trim();
    try {
      const coupon = Store.applyCoupon(code);
      Toast.success(`Coupon "${coupon.code}" applied! ${coupon.discountPercent}% OFF.`);
    } catch (err) {
      Toast.error(err.message);
    }
  },

  removeCoupon() {
    Store.removeCoupon();
    Toast.info('Coupon removed.');
  },

  proceedToCheckout() {
    this.close();
    if (typeof CheckoutModal !== 'undefined') {
      CheckoutModal.open();
    }
  },
};

if (typeof module !== 'undefined' && module.exports) {
  module.exports = CartDrawer;
}
