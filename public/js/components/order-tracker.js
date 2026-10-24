/**
 * VAPEHAUS LIVE ORDER TRACKER & CONFIRMATION COMPONENT
 * 5-Stage visual progress timeline and order status inspector
 */

const OrderTrackerModal = {
  overlayEl: null,
  currentOrderId: null,

  init() {
    this.overlayEl = document.getElementById('order-tracker-modal');
    if (!this.overlayEl) return;

    const closeBtn = document.getElementById('btn-close-tracker');
    if (closeBtn) {
      closeBtn.addEventListener('click', () => this.close());
    }

    this.overlayEl.addEventListener('click', (e) => {
      if (e.target === this.overlayEl) {
        this.close();
      }
    });

    Store.subscribe(() => {
      if (this.currentOrderId && this.overlayEl && this.overlayEl.classList.contains('open')) {
        this.render();
      }
    });
  },

  showOrder(orderId) {
    this.currentOrderId = orderId;
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

  cancelOrder(orderId) {
    if (!confirm('Are you sure you want to cancel this order?')) return;
    try {
      Store.updateOrderStatus(orderId, OrderStatus.CANCELLED);
      Toast.info(`Order #${orderId} was cancelled.`);
    } catch (err) {
      Toast.error(err.message);
    }
  },

  render() {
    const contentEl = document.getElementById('order-tracker-content');
    if (!contentEl || !this.currentOrderId) return;

    const order = Store.getOrderById(this.currentOrderId);
    if (!order) {
      contentEl.innerHTML = `<div style="padding: 40px; text-align: center;">Order not found.</div>`;
      return;
    }

    // Determine stepper status index
    const statusMap = {
      pending: 1,
      paid: 2,
      processing: 3,
      shipped: 4,
      delivered: 5,
      cancelled: 0,
    };
    const currentStage = statusMap[order.status] || 1;
    const isCancelled = order.status === OrderStatus.CANCELLED;
    const canCancel = OrderStatus.canCancel(order.status);

    contentEl.innerHTML = `
      <div class="order-success-body">
        <div class="order-success-icon">${isCancelled ? '❌' : '📦'}</div>
        <h2 style="font-size: 1.6rem;">${isCancelled ? 'Order Cancelled' : 'Order Confirmed & In Progress'}</h2>
        <div class="order-number-banner">Order #${order.orderNumber}</div>

        <!-- 5-Stage Stepper -->
        ${!isCancelled ? `
          <div class="tracking-stepper">
            <div class="stepper-stage ${currentStage >= 1 ? (currentStage === 1 ? 'active' : 'completed') : ''}">
              <div class="stepper-node">${currentStage > 1 ? '✓' : '1'}</div>
              <span>Placed</span>
            </div>
            <div class="stepper-stage ${currentStage >= 2 ? (currentStage === 2 ? 'active' : 'completed') : ''}">
              <div class="stepper-node">${currentStage > 2 ? '✓' : '2'}</div>
              <span>Payment</span>
            </div>
            <div class="stepper-stage ${currentStage >= 3 ? (currentStage === 3 ? 'active' : 'completed') : ''}">
              <div class="stepper-node">${currentStage > 3 ? '✓' : '3'}</div>
              <span>Packing</span>
            </div>
            <div class="stepper-stage ${currentStage >= 4 ? (currentStage === 4 ? 'active' : 'completed') : ''}">
              <div class="stepper-node">${currentStage > 4 ? '✓' : '4'}</div>
              <span>Shipped</span>
            </div>
            <div class="stepper-stage ${currentStage >= 5 ? 'completed' : ''}">
              <div class="stepper-node">${currentStage === 5 ? '✓' : '5'}</div>
              <span>Delivered</span>
            </div>
          </div>
        ` : `
          <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); border-radius: var(--r-md); padding: 14px; margin: 20px 0; color: var(--danger);">
            This order has been cancelled and inventory was returned to stock.
          </div>
        `}

        <!-- Shipping & Tracking Details -->
        <div style="background: var(--surface2); border: 1px solid var(--line); border-radius: var(--r-lg); padding: 20px; text-align: left; margin-bottom: 24px;">
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 14px;">
            <div>
              <p style="font-size: 0.78rem; color: var(--text-muted);">Current Status</p>
              <b style="color: var(--accent2); text-transform: uppercase;">${OrderStatus.getLabel(order.status)}</b>
            </div>
            <div>
              <p style="font-size: 0.78rem; color: var(--text-muted);">Payment Method</p>
              <b>${order.paymentMethod.toUpperCase()} (${order.paymentStatus.toUpperCase()})</b>
            </div>
          </div>

          ${order.courierName ? `
            <div style="border-top: 1px solid var(--line); padding-top: 12px; margin-top: 12px; display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
              <div>
                <p style="font-size: 0.78rem; color: var(--text-muted);">Courier Partner</p>
                <b>🚚 ${order.courierName}</b>
              </div>
              <div>
                <p style="font-size: 0.78rem; color: var(--text-muted);">Waybill / Tracking No.</p>
                <b style="color: var(--accent);">${order.trackingNumber || 'Pending Assignment'}</b>
              </div>
            </div>
          ` : ''}

          <div style="border-top: 1px solid var(--line); padding-top: 12px; margin-top: 12px;">
            <p style="font-size: 0.78rem; color: var(--text-muted);">Delivery Address</p>
            <div style="font-size: 0.88rem; color: var(--text);">${order.shippingAddress}</div>
          </div>
        </div>

        <!-- Ordered Items Summary -->
        <div style="background: var(--surface2); border: 1px solid var(--line); border-radius: var(--r-lg); padding: 20px; text-align: left; margin-bottom: 24px;">
          <div style="font-weight: 700; font-size: 0.95rem; margin-bottom: 12px;">Items in this Shipment</div>
          <div style="display: flex; flex-direction: column; gap: 10px;">
            ${order.items.map((it) => `
              <div style="display: flex; justify-content: space-between; font-size: 0.88rem; border-bottom: 1px solid var(--line); padding-bottom: 8px;">
                <div>
                  <b>${it.quantity}x ${it.productName}</b>
                  <div style="font-size: 0.78rem; color: var(--text-muted);">${it.flavor || ''} · ${it.nicotine || ''}</div>
                </div>
                <div style="font-weight: 700; color: var(--accent2);">
                  ${Money.format(it.price * it.quantity)}
                </div>
              </div>
            `).join('')}
          </div>
          <div style="display: flex; justify-content: space-between; font-size: 1.05rem; font-weight: 800; margin-top: 14px;">
            <span>Total Paid</span>
            <span>${Money.format(order.total)}</span>
          </div>
        </div>

        <!-- Actions -->
        <div style="display: flex; justify-content: center; gap: 14px;">
          <button class="btn btn-secondary" onclick="OrderTrackerModal.close()">Continue Shopping</button>
          ${canCancel ? `
            <button class="btn btn-danger" onclick="OrderTrackerModal.cancelOrder('${order.id}')">Cancel Order</button>
          ` : ''}
        </div>
      </div>
    `;
  },
};

if (typeof module !== 'undefined' && module.exports) {
  module.exports = OrderTrackerModal;
}
