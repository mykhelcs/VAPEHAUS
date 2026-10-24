/**
 * VAPEHAUS MULTI-STEP CHECKOUT WIZARD COMPONENT
 * Complete end-to-end checkout with Philippine address handling, courier selection, payment, and age attestation
 */

const CheckoutModal = {
  overlayEl: null,
  currentStep: 1,
  formData: {
    customerName: '',
    customerEmail: '',
    customerPhone: '',
    addressLine1: '',
    barangay: '',
    city: '',
    province: '',
    postalCode: '',
    deliveryNotes: '',
    shippingMethod: 'standard',
    paymentMethod: 'gcash',
    isAgeAttested: false,
  },

  init() {
    this.overlayEl = document.getElementById('checkout-modal');
    if (!this.overlayEl) return;

    const closeBtn = document.getElementById('btn-close-checkout');
    if (closeBtn) {
      closeBtn.addEventListener('click', () => this.close());
    }

    this.overlayEl.addEventListener('click', (e) => {
      if (e.target === this.overlayEl) {
        this.close();
      }
    });

    // Pre-fill contact info from session user if available
    this.prefillFromSession();
  },

  prefillFromSession() {
    const user = Store.getCurrentUser();
    if (!user) return;
    this.formData.customerName = `${user.firstName} ${user.lastName}`;
    this.formData.customerEmail = user.email;
    this.formData.customerPhone = user.phone || '';
  },

  open() {
    const items = Store.getState().cart.items;
    if (items.length === 0) {
      Toast.error('Your cart is empty. Add products before checking out.');
      return;
    }

    this.currentStep = 1;
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

  setPaymentMethod(method) {
    this.formData.paymentMethod = method;
    this.render();
  },

  setShippingMethod(method) {
    this.formData.shippingMethod = method;
    this.render();
  },

  goToStep(step) {
    this.saveCurrentInputs();
    if (step === 2) {
      if (!this.formData.customerName || !this.formData.customerPhone || !this.formData.customerEmail) {
        Toast.error('Please provide recipient name, phone, and email.');
        return;
      }
    }
    if (step === 3) {
      if (!this.formData.addressLine1 || !this.formData.city || !this.formData.province) {
        Toast.error('Please complete street address, city, and province.');
        return;
      }
    }
    this.currentStep = step;
    this.render();
  },

  saveCurrentInputs() {
    const getVal = (id) => {
      const el = document.getElementById(id);
      return el ? el.value : '';
    };

    if (document.getElementById('co-name')) this.formData.customerName = getVal('co-name');
    if (document.getElementById('co-email')) this.formData.customerEmail = getVal('co-email');
    if (document.getElementById('co-phone')) this.formData.customerPhone = getVal('co-phone');
    if (document.getElementById('co-addr1')) this.formData.addressLine1 = getVal('co-addr1');
    if (document.getElementById('co-brgy')) this.formData.barangay = getVal('co-brgy');
    if (document.getElementById('co-city')) this.formData.city = getVal('co-city');
    if (document.getElementById('co-prov')) this.formData.province = getVal('co-prov');
    if (document.getElementById('co-zip')) this.formData.postalCode = getVal('co-zip');
    if (document.getElementById('co-notes')) this.formData.deliveryNotes = getVal('co-notes');
    const ageChk = document.getElementById('co-age-attest');
    if (ageChk) this.formData.isAgeAttested = ageChk.checked;
  },

  render() {
    const bodyEl = document.getElementById('checkout-modal-body');
    if (!bodyEl) return;

    const totals = Store.getCartTotals();
    const items = Store.getCartItemsDetailed();

    // Adjust shipping if Express
    const isExpress = this.formData.shippingMethod === 'express';
    const finalShipping = isExpress ? CONFIG.SHIPPING.EXPRESS_FEE : totals.shippingFee;
    const finalTotal = totals.subtotal - totals.discountAmount + finalShipping;

    bodyEl.innerHTML = `
      <div class="checkout-header">
        <div style="display: flex; justify-content: center; align-items: center;">
          <h2 style="font-size: 1.4rem;">Checkout &amp; Delivery</h2>
        </div>

        <!-- Step Indicator Bar -->
        <div class="checkout-steps-bar">
          <div class="step-indicator ${this.currentStep === 1 ? 'active' : this.currentStep > 1 ? 'completed' : ''}">
            <div class="step-num">1</div> Contact
          </div>
          <div class="step-indicator ${this.currentStep === 2 ? 'active' : this.currentStep > 2 ? 'completed' : ''}">
            <div class="step-num">2</div> Shipping Address
          </div>
          <div class="step-indicator ${this.currentStep === 3 ? 'active' : ''}">
            <div class="step-num">3</div> Payment & Place Order
          </div>
        </div>
      </div>

      <div class="checkout-body">
        ${this.renderStepContent(totals, finalShipping, finalTotal, items)}
      </div>
    `;
  },

  renderStepContent(totals, shippingFee, finalTotal, items) {
    if (this.currentStep === 1) {
      return `
        <div class="checkout-section-title">1. Recipient & Contact Details</div>
        <div class="input-group">
          <label class="input-label">Full Name <span class="required">*</span></label>
          <input type="text" id="co-name" class="input-control" value="${this.formData.customerName}" placeholder="e.g. Juan Dela Cruz" required>
        </div>
        <div class="form-grid-2">
          <div class="input-group">
            <label class="input-label">Mobile Number <span class="required">*</span></label>
            <input type="tel" id="co-phone" class="input-control" value="${this.formData.customerPhone}" placeholder="+63 9XX XXX XXXX" required>
          </div>
          <div class="input-group">
            <label class="input-label">Email Address <span class="required">*</span></label>
            <input type="email" id="co-email" class="input-control" value="${this.formData.customerEmail}" placeholder="you@domain.ph" required>
          </div>
        </div>
        <div style="display: flex; justify-content: flex-end; margin-top: 24px;">
          <button class="btn btn-lg" onclick="CheckoutModal.goToStep(2)">Continue to Shipping Address →</button>
        </div>
      `;
    }

    if (this.currentStep === 2) {
      return `
        <div class="checkout-section-title">2. Delivery Address (Philippines)</div>
        <div class="input-group">
          <label class="input-label">Street Address / House or Unit # <span class="required">*</span></label>
          <input type="text" id="co-addr1" class="input-control" value="${this.formData.addressLine1}" placeholder="Unit / House No., Building Name, Street Name" required>
        </div>
        <div class="form-grid-2">
          <div class="input-group">
            <label class="input-label">Barangay</label>
            <input type="text" id="co-brgy" class="input-control" value="${this.formData.barangay}" placeholder="e.g. San Antonio">
          </div>
          <div class="input-group">
            <label class="input-label">City / Municipality <span class="required">*</span></label>
            <input type="text" id="co-city" class="input-control" value="${this.formData.city}" placeholder="e.g. Pasig City" required>
          </div>
        </div>
        <div class="form-grid-2">
          <div class="input-group">
            <label class="input-label">Province <span class="required">*</span></label>
            <input type="text" id="co-prov" class="input-control" value="${this.formData.province}" placeholder="e.g. Metro Manila" required>
          </div>
          <div class="input-group">
            <label class="input-label">Postal Code</label>
            <input type="text" id="co-zip" class="input-control" value="${this.formData.postalCode}" placeholder="e.g. 1605">
          </div>
        </div>
        <div class="input-group">
          <label class="input-label">Delivery Notes / Landmark</label>
          <textarea id="co-notes" class="textarea-control" placeholder="Special instructions for the courier...">${this.formData.deliveryNotes}</textarea>
        </div>

        <!-- Shipping Method -->
        <div class="checkout-section-title" style="margin-top: 20px;">Shipping Option</div>
        <div class="payment-method-cards" style="grid-template-columns: 1fr 1fr;">
          <div class="payment-card-opt ${this.formData.shippingMethod === 'standard' ? 'active' : ''}" onclick="CheckoutModal.setShippingMethod('standard')">
            <span>🚚</span>
            <b>Standard Courier (2-3 Days)</b>
            <div style="font-size: 0.82rem; color: var(--accent2); margin-top: 4px;">
              ${totals.shippingFee === 0 ? 'FREE Shipping' : Money.format(CONFIG.SHIPPING.STANDARD_FEE)}
            </div>
          </div>
          <div class="payment-card-opt ${this.formData.shippingMethod === 'express' ? 'active' : ''}" onclick="CheckoutModal.setShippingMethod('express')">
            <span>⚡</span>
            <b>Express Same-Day Dispatch</b>
            <div style="font-size: 0.82rem; color: var(--accent2); margin-top: 4px;">
              ${Money.format(CONFIG.SHIPPING.EXPRESS_FEE)} (Metro Manila)
            </div>
          </div>
        </div>

        <div style="display: flex; justify-content: space-between; margin-top: 24px;">
          <button class="btn btn-secondary" onclick="CheckoutModal.goToStep(1)">← Back</button>
          <button class="btn btn-lg" onclick="CheckoutModal.goToStep(3)">Continue to Payment →</button>
        </div>
      `;
    }

    // Step 3: Payment & Summary
    return `
      <div class="checkout-section-title">3. Select Payment Method</div>
      <div class="payment-method-cards">
        <div class="payment-card-opt ${this.formData.paymentMethod === 'gcash' ? 'active' : ''}" onclick="CheckoutModal.setPaymentMethod('gcash')">
          <span>📱</span>
          <b>GCash</b>
          <div style="font-size: 0.76rem; color: var(--text-muted); margin-top: 4px;">Instant QR / Mobile</div>
        </div>
        <div class="payment-card-opt ${this.formData.paymentMethod === 'maya' ? 'active' : ''}" onclick="CheckoutModal.setPaymentMethod('maya')">
          <span>💳</span>
          <b>Maya / Card</b>
          <div style="font-size: 0.76rem; color: var(--text-muted); margin-top: 4px;">Debit & Credit Cards</div>
        </div>
        <div class="payment-card-opt ${this.formData.paymentMethod === 'cod' ? 'active' : ''}" onclick="CheckoutModal.setPaymentMethod('cod')">
          <span>💵</span>
          <b>Cash on Delivery</b>
          <div style="font-size: 0.76rem; color: var(--text-muted); margin-top: 4px;">Pay upon delivery</div>
        </div>
      </div>

      <!-- Order Review Box -->
      <div style="background: var(--surface2); border: 1px solid var(--line); border-radius: var(--r-md); padding: 18px; margin-bottom: 20px;">
        <div style="font-weight: 700; font-size: 0.95rem; margin-bottom: 10px;">Order Summary (${items.length} items)</div>
        <div style="max-height: 120px; overflow-y: auto; margin-bottom: 12px; font-size: 0.84rem;">
          ${items.map((it) => `
            <div style="display: flex; justify-content: space-between; padding: 4px 0;">
              <span>${it.quantity}x ${it.productName} (${it.flavor})</span>
              <b>${Money.format(it.lineTotal)}</b>
            </div>
          `).join('')}
        </div>
        <div class="cart-summary-line">
          <span>Subtotal</span>
          <span>${totals.subtotalFormatted}</span>
        </div>
        ${totals.discountAmount > 0 ? `
          <div class="cart-summary-line" style="color: var(--success);">
            <span>Discount (${Store.getState().cart.appliedCouponCode})</span>
            <span>-${totals.discountFormatted}</span>
          </div>
        ` : ''}
        <div class="cart-summary-line">
          <span>Shipping Fee (${this.formData.shippingMethod === 'express' ? 'Express' : 'Standard'})</span>
          <span>${shippingFee === 0 ? 'FREE' : Money.format(shippingFee)}</span>
        </div>
        <div class="cart-summary-line total">
          <span>Total Payable</span>
          <span>${Money.format(finalTotal)}</span>
        </div>
      </div>

      <!-- Statutory 18+ Checkbox -->
      <label class="legal-checkbox-row">
        <input type="checkbox" id="co-age-attest" ${this.formData.isAgeAttested ? 'checked' : ''} onchange="CheckoutModal.formData.isAgeAttested = this.checked">
        <div>
          <b>Legal Age Verification Attestation (18+):</b>
          <div style="color: var(--text-muted); font-size: 0.78rem; margin-top: 2px;">
            I hereby certify under penalty of law that I am at least 18 years old and legally permitted to purchase electronic cigarette products in accordance with Republic Act No. 11900.
          </div>
        </div>
      </label>

      <div style="display: flex; justify-content: space-between; margin-top: 20px;">
        <button class="btn btn-secondary" onclick="CheckoutModal.goToStep(2)">← Back</button>
        <button class="btn btn-lg btn-cyan" onclick="CheckoutModal.submitOrder()">
          🔒 Confirm & Place Order (${Money.format(finalTotal)})
        </button>
      </div>
    `;
  },

  async submitOrder() {
    this.saveCurrentInputs();

    if (!this.formData.isAgeAttested) {
      Toast.error('You must confirm the 18+ age verification attestation checkbox before placing your order.');
      return;
    }

    const fullAddress = `${this.formData.addressLine1}, ${this.formData.barangay ? this.formData.barangay + ', ' : ''}${this.formData.city}, ${this.formData.province} ${this.formData.postalCode}`;

    const submitBtn = document.querySelector('.btn-place-order');
    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Placing order…'; }

    try {
      const result = await Api.checkout({
        customerName: this.formData.customerName,
        customerEmail: this.formData.customerEmail,
        customerPhone: this.formData.customerPhone,
        shippingAddress: fullAddress,
        shippingCity: this.formData.city,
        shippingProvince: this.formData.province,
        shippingZip: this.formData.postalCode,
        deliveryNotes: this.formData.deliveryNotes,
        shippingMethod: this.formData.shippingMethod,
        paymentMethod: this.formData.paymentMethod,
        isAgeAttested: this.formData.isAgeAttested,
      });

      if (!result.success) {
        Toast.error(result.error.message || 'Checkout failed. Please try again.');
        return;
      }

      const order = result.data;
      Toast.success(`Order #${order.orderNumber} placed successfully!`);
      this.close();

      if (typeof OrderTrackerModal !== 'undefined') {
        OrderTrackerModal.showOrder(order.id);
      }
    } catch (err) {
      Toast.error(err.message || 'An unexpected error occurred during checkout.');
    } finally {
      if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = '🔒 Confirm & Place Order'; }
    }
  },
};

if (typeof module !== 'undefined' && module.exports) {
  module.exports = CheckoutModal;
}
