/**
 * VAPEHAUS DOMAIN: ORDER STATUS VALUE OBJECT & STATE MACHINE
 * Strictly mirrors the domain invariants in src/Domain/ValueObject/OrderStatus.php
 */

const OrderStatus = {
  PENDING: 'pending',
  PAID: 'paid',
  PROCESSING: 'processing',
  SHIPPED: 'shipped',
  DELIVERED: 'delivered',
  CANCELLED: 'cancelled',

  ALLOWED: ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled'],

  /**
   * Validate whether a status string is a recognized valid order status
   * @param {string} status
   * @returns {boolean}
   */
  isValid(status) {
    if (typeof status !== 'string') return false;
    return this.ALLOWED.includes(status.trim().toLowerCase());
  },

  /**
   * Check whether an order in currentStatus can legally transition to targetStatus
   * Enforces the exact business rules from OrderStatus.php:
   *  - pending -> paid, cancelled
   *  - paid -> processing, cancelled
   *  - processing -> shipped, cancelled
   *  - shipped -> delivered
   *  - delivered, cancelled -> no transitions
   * @param {string} currentStatus
   * @param {string} targetStatus
   * @returns {boolean}
   */
  canTransitionTo(currentStatus, targetStatus) {
    const from = String(currentStatus || '').trim().toLowerCase();
    const to = String(targetStatus || '').trim().toLowerCase();

    if (!this.isValid(from) || !this.isValid(to)) {
      return false;
    }

    if (from === to) {
      return true;
    }

    switch (from) {
      case this.PENDING:
        return to === this.PAID || to === this.CANCELLED;
      case this.PAID:
        return to === this.PROCESSING || to === this.CANCELLED;
      case this.PROCESSING:
        return to === this.SHIPPED || to === this.CANCELLED;
      case this.SHIPPED:
        return to === this.DELIVERED;
      case this.DELIVERED:
      case this.CANCELLED:
        return false;
      default:
        return false;
    }
  },

  /**
   * Check whether an order can be cancelled in its current state
   * @param {string} status
   * @returns {boolean}
   */
  canCancel(status) {
    const s = String(status || '').trim().toLowerCase();
    return s === this.PENDING || s === this.PAID;
  },

  /**
   * Return human-friendly label
   * @param {string} status
   * @returns {string}
   */
  getLabel(status) {
    const s = String(status || '').trim().toLowerCase();
    switch (s) {
      case this.PENDING:
        return 'Pending Payment';
      case this.PAID:
        return 'Payment Received';
      case this.PROCESSING:
        return 'Processing in Warehouse';
      case this.SHIPPED:
        return 'Shipped / In Transit';
      case this.DELIVERED:
        return 'Delivered';
      case this.CANCELLED:
        return 'Cancelled';
      default:
        return status;
    }
  },

  /**
   * Return visual badge class
   * @param {string} status
   * @returns {string}
   */
  getBadgeClass(status) {
    const s = String(status || '').trim().toLowerCase();
    return `status-pill status-${s}`;
  },
};

Object.freeze(OrderStatus);

if (typeof module !== 'undefined' && module.exports) {
  module.exports = OrderStatus;
}
