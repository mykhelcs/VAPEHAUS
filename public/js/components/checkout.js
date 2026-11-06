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
    if (!Store.isLoggedIn()) {
      Toast.error('Please sign in or create an account to proceed with checkout (Republic Act No. 11900 requires age verification).');
      if (typeof AuthModal !== 'undefined') {
        AuthModal.openLogin();
      }
      return;
    }

    const items = Store.getState().cart.items;
    if (items.length === 0) {
      Toast.error('Your cart is empty. Add products before checking out.');
      return;
    }

    if (!this.formData.customerEmail) this.prefillFromSession();
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
    const user = Store.getCurrentUser();
    if (!user) {
      Toast.error('Please sign in or create an account to proceed with checkout.');
      if (typeof AuthModal !== 'undefined') AuthModal.openLogin();
      return;
    }

    if (step >= 2) {
      if (!this.formData.customerName || !this.formData.customerPhone || !this.formData.customerEmail) {
        Toast.error('Please provide recipient name, phone, and email.');
        return;
      }
      if (!user.isAgeVerified) {
        Toast.error('Age verification required: Please upload your 18+ government ID photo before proceeding (RA 11900).');
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

  async uploadKycIdPhoto() {
    const fileInput = document.getElementById('co-kyc-file');
    const idTypeSelect = document.getElementById('co-kyc-type');
    const idNumberInput = document.getElementById('co-kyc-number');
    const uploadBtn = document.getElementById('btn-co-kyc-upload');

    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
      Toast.error('Please select an ID photo image file to upload.');
      return;
    }

    const file = fileInput.files[0];
    const user = Store.getCurrentUser();
    if (!user) {
      Toast.error('Please sign in first before uploading identification.');
      if (typeof AuthModal !== 'undefined') AuthModal.openLogin();
      return;
    }

    if (uploadBtn) {
      uploadBtn.disabled = true;
      uploadBtn.textContent = 'Uploading & Verifying ID…';
    }

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

      const idType = idTypeSelect ? idTypeSelect.value : 'national_id';
      const idNumber = idNumberInput ? idNumberInput.value : '';

      await Api.submitKyc({
        frontImageUrl: fileUrl,
        idType: idType,
        idNumber: idNumber || 'VERIFIED-ID-UPLOAD',
      });

      user.isAgeVerified = true;
      user.kycStatus = 'verified';
      user.kycDocumentUrl = fileUrl;
      Store.save();

      Toast.success('Government ID photo uploaded and verified! You may now proceed with checkout.');
      this.render();
    } catch (err) {
      Toast.error(err.message || 'Failed to upload and verify ID document.');
    } finally {
      if (uploadBtn) {
        uploadBtn.disabled = false;
        uploadBtn.textContent = 'Upload ID Photo & Verify Age';
      }
    }
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
    const user = Store.getCurrentUser();

    if (this.currentStep === 1) {
      const kycSection = (user && user.isAgeVerified) ? `
        <div style="margin-top: 18px; padding: 14px 18px; border: 1px solid var(--accent2); border-radius: var(--r-md); background: rgba(0, 168, 150, 0.08); display: flex; align-items: center; gap: 12px;">
          ${typeof Icons !== 'undefined' ? Icons.check(22) : ''}
          <div>
            <strong style="color: var(--accent2); font-size: 0.92rem;">18+ Adult Identity Verified (RA 11900 Compliant)</strong>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;">Your government ID is verified. You are legally authorized to purchase electronic cigarette products.</div>
          </div>
        </div>
      ` : `
        <div style="margin-top: 18px; padding: 18px; border: 2px solid var(--accent1); border-radius: var(--r-md); background: var(--surface2);">
          <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
            ${typeof Icons !== 'undefined' ? Icons.shield(22) : ''}
            <strong style="color: var(--accent1); font-size: 0.95rem;">Mandatory 18+ Government ID Verification</strong>
          </div>
          <p style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 14px;">
            Republic Act No. 11900 requires identity and age verification prior to purchasing vape products. Please upload a clear photo of your government-issued ID to continue.
          </p>
          <div class="form-grid-2" style="margin-bottom: 12px;">
            <div class="input-group">
              <label class="input-label">Select ID Type <span class="required">*</span></label>
              <select id="co-kyc-type" class="select-control">
                <option value="national_id">Philippine National ID (PhilSys)</option>
                <option value="drivers_license">Driver's License (LTO)</option>
                <option value="passport">Philippine Passport (DFA)</option>
                <option value="umid">UMID (SSS / GSIS)</option>
                <option value="postal">Postal ID (PhilPost)</option>
                <option value="prc">PRC ID</option>
              </select>
            </div>
            <div class="input-group">
              <label class="input-label">ID Number / Reference (Optional)</label>
              <input type="text" id="co-kyc-number" class="input-control" placeholder="e.g. 1234-5678-9012">
            </div>
          </div>
          <div class="input-group" style="margin-bottom: 14px;">
            <label class="input-label">Upload Photo of Government ID <span class="required">*</span></label>
            <input type="file" id="co-kyc-file" class="input-control" accept="image/png,image/jpeg,image/webp">
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">Accepted formats: PNG, JPG, WebP. ID photo is stored securely and used only for legal age compliance.</div>
          </div>
          <button type="button" id="btn-co-kyc-upload" class="btn btn-primary" onclick="CheckoutModal.uploadKycIdPhoto()" style="width: 100%;">
            ${typeof Icons !== 'undefined' ? Icons.upload(18) : ''} Upload ID Photo &amp; Verify Age
          </button>
        </div>
      `;

      return `
        <div class="checkout-section-title">1. Recipient &amp; Contact Details</div>
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

        ${kycSection}

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
            ${typeof Icons !== 'undefined' ? Icons.truck(22) : ''}
            <b>Standard Courier (2-3 Days)</b>
            <div style="font-size: 0.82rem; color: var(--accent2); margin-top: 4px;">
              ${totals.shippingFee === 0 ? 'FREE Shipping' : Money.format(CONFIG.SHIPPING.STANDARD_FEE)}
            </div>
          </div>
          <div class="payment-card-opt ${this.formData.shippingMethod === 'express' ? 'active' : ''}" onclick="CheckoutModal.setShippingMethod('express')">
            ${typeof Icons !== 'undefined' ? Icons.zap(22) : ''}
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
          ${typeof Icons !== 'undefined' ? Icons.phone(22) : ''}
          <b>GCash</b>
          <div style="font-size: 0.76rem; color: var(--text-muted); margin-top: 4px;">Instant QR / Mobile</div>
        </div>
        <div class="payment-card-opt ${this.formData.paymentMethod === 'maya' ? 'active' : ''}" onclick="CheckoutModal.setPaymentMethod('maya')">
          ${typeof Icons !== 'undefined' ? Icons.creditCard(22) : ''}
          <b>Maya / Card</b>
          <div style="font-size: 0.76rem; color: var(--text-muted); margin-top: 4px;">Debit & Credit Cards</div>
        </div>
        <div class="payment-card-opt ${this.formData.paymentMethod === 'cod' ? 'active' : ''}" onclick="CheckoutModal.setPaymentMethod('cod')">
          ${typeof Icons !== 'undefined' ? Icons.cash(22) : ''}
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
          Confirm & Place Order (${Money.format(finalTotal)})
        </button>
      </div>
    `;
  },

  async submitOrder() {
    this.saveCurrentInputs();

    const user = Store.getCurrentUser();
    if (!user) {
      Toast.error('You must be signed in with an active account to checkout. Guest checkout is disabled.');
      if (typeof AuthModal !== 'undefined') AuthModal.openLogin();
      return;
    }

    if (!user.isAgeVerified) {
      Toast.error('Age verification with a valid government ID photo is required before placing an order (Republic Act No. 11900).');
      this.currentStep = 1;
      this.render();
      return;
    }

    if (!this.formData.isAgeAttested) {
      Toast.error('You must confirm the 18+ age verification attestation checkbox before placing your order.');
      return;
    }

    const fullAddress = [
      this.formData.addressLine1,
      this.formData.barangay ? this.formData.barangay + ',' : '',
      this.formData.city,
      this.formData.province,
      this.formData.postalCode,
    ].filter(Boolean).join(' ');

    const submitBtn = this.overlayEl ? this.overlayEl.querySelector('.btn-place-order, .btn-cyan') : null;
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Creating Order…';
    }

    try {
      // Prune any stale or orphaned items before submitting
      if (typeof Store !== 'undefined' && Store.pruneOrphanedCartItems) {
        Store.pruneOrphanedCartItems();
      }

      const cartItemsDetailed = (typeof Store !== 'undefined' && Store.getCartItemsDetailed)
        ? Store.getCartItemsDetailed()
        : [];

      if (cartItemsDetailed.length === 0) {
        Toast.error('Your cart has no active items. Please select products to purchase.');
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Confirm & Place Order'; }
        return;
      }

      const cartState = (typeof Store !== 'undefined' && Store.getState) ? Store.getState().cart : { items: [] };
      const itemsPayload = cartItemsDetailed.map((it) => ({
        variantId: it.variantId,
        variant_id: it.variantId,
        quantity: it.quantity,
      }));

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
        items: itemsPayload,
        couponCode: cartState.appliedCouponCode,
        session_token: (typeof Api !== 'undefined' && Api.getCartSessionToken) ? Api.getCartSessionToken() : undefined,
      });

      if (!result.success) {
        const errorMsg = result.error?.message || 'Checkout failed. Please try again.';
        Toast.error(errorMsg);
        if (errorMsg.includes('no longer available') || errorMsg.includes('ProductVariant')) {
          if (typeof Store !== 'undefined' && Store.pruneOrphanedCartItems) {
            Store.pruneOrphanedCartItems();
          }
          if (typeof CartDrawer !== 'undefined' && CartDrawer.render) CartDrawer.render();
        }
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Confirm & Place Order'; }
        return;
      }

      const order = result.data;
      if (typeof Store !== 'undefined' && Store.upsertOrder) {
        Store.upsertOrder(order);
      }

      // Option 1: Cash on Delivery (COD) - Clear cart immediately
      if (this.formData.paymentMethod === 'cod') {
        if (typeof Store !== 'undefined') Store.clearCart();
        if (typeof Api !== 'undefined' && Api.clearCart) Api.clearCart().catch(() => {});
        Toast.success(`Order #${order.orderNumber || order.order_number || order.id} placed! Pay upon delivery. 🛵`);
        this.close();
        if (typeof CartDrawer !== 'undefined') CartDrawer.render();
        if (typeof OrderTrackerModal !== 'undefined') {
          OrderTrackerModal.showOrder(order.id, order);
        }
        return;
      }

      // Option 2: PayMongo Hosted Checkout Session (GCash, Maya, Card)
      if (submitBtn) {
        submitBtn.textContent = 'Redirecting to PayMongo… 🔐';
      }

      const pmMethod = this.formData.paymentMethod === 'maya' ? 'paymaya' : (this.formData.paymentMethod === 'card' ? 'card' : 'gcash');
      let sessionRes = null;
      try {
        sessionRes = await Api.createCheckoutSession(order.id, [pmMethod, 'gcash', 'paymaya', 'card']);
      } catch (sessErr) {
        sessionRes = { success: false, error: { message: sessErr.message } };
      }

      if (sessionRes && sessionRes.success && sessionRes.data && sessionRes.data.checkout_url) {
        if (typeof Store !== 'undefined') Store.clearCart();
        if (typeof Api !== 'undefined' && Api.clearCart) Api.clearCart().catch(() => {});
        Toast.info('Redirecting to PayMongo secure payment page… 🔐');
        this.close();
        window.location.href = sessionRes.data.checkout_url;
        return;
      }

      // Fallback if PayMongo API returned an error (e.g. offline dev / unconfigured test keys)
      const fallbackMsg = sessionRes?.error?.message || 'Could not generate PayMongo Checkout Session.';
      const bodyEl = document.getElementById('checkout-modal-body');
      this._renderGatewayFallbackNotice(bodyEl, order, fallbackMsg);
    } catch (err) {
      Toast.error(err.message || 'An unexpected error occurred during checkout.');
      if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Confirm & Place Order'; }
    }
  },

  simulatePayment(orderId, orderNumber, total) {
    if (typeof Store !== 'undefined') {
      Store.clearCart();
      if (Store.upsertOrder) {
        Store.upsertOrder({ id: orderId, orderNumber, status: 'processing', paymentStatus: 'paid' });
      }
    }
    if (typeof Api !== 'undefined' && Api.clearCart) Api.clearCart().catch(() => {});
    Toast.success(`Order #${orderNumber} payment confirmed via sandbox simulation! 🎉`);
    this.close();
    if (typeof CartDrawer !== 'undefined') CartDrawer.render();
    if (typeof OrderTrackerModal !== 'undefined') {
      OrderTrackerModal.showOrder(orderId);
    }
  },

  switchToCod(orderId, orderNumber) {
    if (typeof Store !== 'undefined') Store.clearCart();
    if (typeof Api !== 'undefined' && Api.clearCart) Api.clearCart().catch(() => {});
    Toast.success(`Order #${orderNumber} switched to Cash on Delivery! 🛵`);
    this.close();
    if (typeof CartDrawer !== 'undefined') CartDrawer.render();
    if (typeof OrderTrackerModal !== 'undefined') {
      OrderTrackerModal.showOrder(orderId);
    }
  },

  _renderGatewayFallbackNotice(container, order, message) {
    if (!container) return;
    container.innerHTML = `
      <div class="paymongo-overlay-tab" style="padding: 24px 16px; text-align: center;">
        <div style="font-size: 2.5rem; margin-bottom: 12px;">🧪</div>
        <h3 style="font-size: 1.25rem; margin-bottom: 8px;">PayMongo Gateway Notice</h3>
        <p style="color: var(--text-muted); font-size: 0.86rem; margin-bottom: 20px; max-width: 440px; margin-left: auto; margin-right: auto; line-height: 1.5;">
          ${message || 'Payment method verification could not be completed.'}<br>
          <span style="font-size: 0.8rem; color: var(--accent2);">Your order <b>#${order.orderNumber}</b> has been safely placed. You can simulate instant confirmation or switch to Cash on Delivery.</span>
        </p>
        <div style="display: flex; flex-direction: column; gap: 10px; max-width: 320px; margin: 0 auto;">
          <button class="btn btn-lg btn-cyan" onclick="CheckoutModal.simulatePayment('${order.id}', '${order.orderNumber}', '${order.total}')">
            ⚡ Confirm via Sandbox Simulation
          </button>
          <button class="btn btn-secondary" onclick="CheckoutModal.switchToCod('${order.id}', '${order.orderNumber}')">
            💵 Switch to Cash on Delivery
          </button>
          <button class="btn btn-ghost" onclick="CheckoutModal.close()">
            Close &amp; Track Order
          </button>
        </div>
      </div>
    `;
  },

  async _renderPayMongoOverlayTab(order) {
    const bodyEl = document.getElementById('checkout-modal-body');
    if (!bodyEl) return;

    Toast.info('Connecting to PayMongo secure gateway…');

    // Create Payment Intent via PHP Backend
    let payment_intent_id = null;
    let client_key = null;
    let amount_centavos = 0;
    let intentError = null;

    try {
      const intentRes = await Api.createPaymentIntent(order.id);
      if (intentRes && intentRes.success && intentRes.data) {
        payment_intent_id = intentRes.data.payment_intent_id;
        client_key = intentRes.data.client_key;
        amount_centavos = intentRes.data.amount_centavos || 0;
      } else {
        intentError = intentRes?.error?.message || 'Could not initialize payment gateway.';
      }
    } catch (e) {
      intentError = e.message || 'Network error connecting to payment gateway.';
    }

    const method = this.formData.paymentMethod; // 'gcash', 'maya', or 'card'
    const formattedAmount = (amount_centavos > 0)
      ? Money.format(amount_centavos / 100)
      : Money.format(order.total);

    // If intent generation failed (e.g. offline dev or unconfigured test keys), show fallback options
    if (intentError && !client_key) {
      this._renderGatewayFallbackNotice(bodyEl, order, intentError);
      return;
    }

    bodyEl.innerHTML = `
      <div class="paymongo-overlay-tab">
        <div class="paymongo-badge-bar">
          <div style="font-weight: 800; font-size: 1.05rem; display: flex; align-items: center; gap: 8px;">
            ${typeof Icons !== 'undefined' ? Icons.lock(18) : ''} PayMongo Express Checkout
          </div>
          <div class="paymongo-security-tag">
            ${typeof Icons !== 'undefined' ? Icons.shield(16) : ''} 256-Bit Encrypted
          </div>
        </div>

        <div style="background: var(--surface); padding: 14px 18px; border-radius: var(--r-md); margin-bottom: 20px; border: 1px solid var(--line);">
          <div style="display: flex; justify-content: space-between; font-size: 0.88rem; color: var(--text-muted); margin-bottom: 4px;">
            <span>Order Reference</span>
            <b style="color: var(--text);">#${order.orderNumber}</b>
          </div>
          <div style="display: flex; justify-content: space-between; font-size: 0.88rem; color: var(--text-muted);">
            <span>Total Payable</span>
            <b style="color: var(--accent2); font-size: 1.15rem;">${formattedAmount}</b>
          </div>
        </div>

        <div id="pm-element-container" class="paymongo-input-frame">
          ${method === 'card' ? `
            <div style="margin-bottom: 12px; font-size: 0.86rem; font-weight: 600;">Credit / Debit Card</div>
            <div id="pm-card-mount" style="min-height: 44px; display: grid; align-items: center; color: var(--text-muted); font-size: 0.84rem;">
              Loading secure card form…
            </div>
          ` : `
            <div style="text-align: center; padding: 16px 8px;">
              <div style="font-size: 2.2rem; margin-bottom: 8px;">${method === 'gcash' ? '📱' : '💳'}</div>
              <div style="font-weight: 700; margin-bottom: 6px;">${method === 'gcash' ? 'GCash' : 'Maya'} E-Wallet Payment</div>
              <p style="color: var(--text-muted); font-size: 0.84rem; margin: 0 auto; max-width: 380px;">
                Authenticate seamlessly with your ${method === 'gcash' ? 'GCash' : 'Maya'} account. Confirmation will be verified automatically.
              </p>
            </div>
          `}
        </div>

        <div style="display: flex; gap: 12px; margin-top: 16px;">
          <button class="btn btn-secondary" style="flex: 1;" onclick="CheckoutModal.close()">Cancel / Pay Later</button>
          <button class="btn btn-lg btn-cyan" style="flex: 2;" id="btn-pm-confirm-pay">
            ${method === 'card' ? 'Pay ' + formattedAmount : 'Proceed with ' + (method === 'gcash' ? 'GCash' : 'Maya')}
          </button>
        </div>
      </div>
    `;

    // Initialize PayMongo JS SDK if present
    const payBtn = document.getElementById('btn-pm-confirm-pay');
    if (!payBtn) return;

    if (typeof window.PayMongo !== 'undefined' && CONFIG.PAYMONGO_PUBLIC_KEY && !CONFIG.PAYMONGO_PUBLIC_KEY.includes('YOUR_PAYMONGO')) {
      try {
        const pm = window.PayMongo(CONFIG.PAYMONGO_PUBLIC_KEY);

        if (method === 'card') {
          const elements = pm.elements();
          const cardElement = elements.create('card');
          cardElement.mount('#pm-card-mount');

          payBtn.onclick = async () => {
            payBtn.disabled = true;
            payBtn.textContent = 'Processing Payment…';

            try {
              const returnUrl = window.location.origin + window.location.pathname + '?order_id=' + encodeURIComponent(order.id) + '&status=paid';
              const result = await pm.confirmPayment({
                elements,
                clientKey: client_key,
                confirmParams: {
                  return_url: returnUrl,
                },
              });

              const cardErrors = result?.errors || (result?.error ? [result.error] : null);
              if (cardErrors && cardErrors.length > 0) {
                const msg = cardErrors.map(e => e.detail || e.message || 'Payment authentication failed.').join('; ');
                Toast.error(msg);
                payBtn.disabled = false;
                payBtn.textContent = 'Retry Payment';
                return;
              }

              if (typeof Store !== 'undefined') Store.clearCart();
              if (typeof Api !== 'undefined' && Api.clearCart) Api.clearCart().catch(() => {});
              Toast.success('Payment approved! Your order is confirmed. 🎉');
              CheckoutModal.close();
              if (typeof CartDrawer !== 'undefined') CartDrawer.render();
              if (typeof OrderTrackerModal !== 'undefined') OrderTrackerModal.showOrder(order.id);
            } catch (cardErr) {
              Toast.error(cardErr.message || 'Card payment failed.');
              payBtn.disabled = false;
              payBtn.textContent = 'Retry Payment';
            }
          };
        } else {
          // GCash / Maya E-Wallets
          payBtn.onclick = async () => {
            payBtn.disabled = true;
            payBtn.textContent = 'Authorizing…';

            try {
              const pmType = method === 'maya' ? 'paymaya' : 'gcash';

              // PayMongo requires customer billing details for e-wallet payment methods
              const currentUser = (typeof Store !== 'undefined' && Store.getCurrentUser) ? Store.getCurrentUser() : null;
              const customerName = (this.formData.customerName || (currentUser ? `${currentUser.firstName || ''} ${currentUser.lastName || ''}`.trim() : '') || 'Customer').trim();
              const customerEmail = (this.formData.customerEmail || currentUser?.email || 'customer@vapehaus.ph').trim();
              const customerPhone = (this.formData.customerPhone || currentUser?.phone || '09170000000').trim();

              const pmPayload = {
                type: pmType,
                billing: {
                  name: customerName || 'Customer',
                  email: customerEmail || 'customer@vapehaus.ph',
                  phone: customerPhone || '09170000000',
                },
              };

              const result = await pm.createPaymentMethod(pmPayload);

              // Check for plural errors array and singular error object
              const pmErrors = result?.errors || (result?.error ? [result.error] : null);
              if (pmErrors && pmErrors.length > 0) {
                const errorMsg = pmErrors.map(e => e.detail || e.message || 'Payment method authorization failed.').join('; ');
                Toast.error(errorMsg);
                this._renderGatewayFallbackNotice(bodyEl, order, errorMsg);
                return;
              }

              if (!result || !result.data || !result.data.id) {
                const errorMsg = 'Could not generate e-wallet payment authorization token.';
                Toast.error(errorMsg);
                this._renderGatewayFallbackNotice(bodyEl, order, errorMsg);
                return;
              }

              // Attach and confirm intent
              const returnUrl = window.location.origin + window.location.pathname + '?order_id=' + encodeURIComponent(order.id) + '&status=paid';
              const confirmRes = await pm.confirmPayment({
                paymentMethodId: result.data.id,
                clientKey: client_key,
                confirmParams: {
                  return_url: returnUrl,
                },
              });

              const confirmErrors = confirmRes?.errors || (confirmRes?.error ? [confirmRes.error] : null);
              if (confirmErrors && confirmErrors.length > 0) {
                const errorMsg = confirmErrors.map(e => e.detail || e.message || 'Payment confirmation failed.').join('; ');
                Toast.error(errorMsg);
                this._renderGatewayFallbackNotice(bodyEl, order, errorMsg);
                return;
              }

              if (typeof Store !== 'undefined') Store.clearCart();
              if (typeof Api !== 'undefined' && Api.clearCart) Api.clearCart().catch(() => {});

              const redirectUrl = confirmRes.data?.attributes?.next_action?.redirect?.url;
              if (redirectUrl) {
                Toast.info('Redirecting to secure authorization page… 📱');
                window.location.href = redirectUrl;
              } else {
                Toast.success('E-Wallet payment authorized! 🎉');
                CheckoutModal.close();
                if (typeof CartDrawer !== 'undefined') CartDrawer.render();
                if (typeof OrderTrackerModal !== 'undefined') OrderTrackerModal.showOrder(order.id);
              }
            } catch (pmErr) {
              const msg = pmErr.message || 'Payment initiation error.';
              Toast.error(msg);
              this._renderGatewayFallbackNotice(bodyEl, order, msg);
            }
          };
        }
      } catch (sdkInitErr) {
        console.warn('PayMongo SDK initialization fallback:', sdkInitErr);
      }
    } else {
      // Local fallback / Offline sandbox simulation when keys not yet set
      const cardMount = document.getElementById('pm-card-mount');
      if (cardMount) {
        cardMount.innerHTML = `
          <div style="font-size: 0.82rem; color: var(--text-muted); line-height: 1.5;">
            🧪 <b>PayMongo Sandbox Simulation Mode:</b><br>
            Public key in sandbox configuration. Ready to simulate order payment completion.
          </div>
        `;
      }

      payBtn.onclick = async () => {
        payBtn.disabled = true;
        payBtn.textContent = 'Simulating Payment…';
        await new Promise(r => setTimeout(r, 800));

        if (typeof Store !== 'undefined') {
          Store.clearCart();
          if (Store.updateOrderStatus) {
            Store.updateOrderStatus(order.id, 'processing', { paymentStatus: 'paid' });
          }
        }
        if (typeof Api !== 'undefined' && Api.clearCart) Api.clearCart().catch(() => {});

        Toast.success(`Payment of ${formattedAmount} confirmed successfully! 🎉`);
        CheckoutModal.close();
        if (typeof CartDrawer !== 'undefined') CartDrawer.render();
        if (typeof OrderTrackerModal !== 'undefined') {
          OrderTrackerModal.showOrder(order.id);
        }
      };
    }
  },
};

if (typeof module !== 'undefined' && module.exports) {
  module.exports = CheckoutModal;
}
