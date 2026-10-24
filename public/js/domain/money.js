/**
 * VAPEHAUS DOMAIN: MONEY VALUE OBJECT & UTILITIES
 * Deterministic integer-cents currency calculations
 */

const Money = {
  /**
   * Convert float/number to integer cents
   * @param {number|string} amount
   * @returns {number}
   */
  toCents(amount) {
    const val = typeof amount === 'string' ? parseFloat(amount.replace(/[^0-9.-]+/g, '')) : Number(amount);
    if (isNaN(val)) return 0;
    return Math.round(val * 100);
  },

  /**
   * Convert integer cents to float number
   * @param {number} cents
   * @returns {number}
   */
  fromCents(cents) {
    return Math.round(cents) / 100;
  },

  /**
   * Format cents or float to Philippine Peso string
   * @param {number} amount
   * @param {boolean} isCents
   * @returns {string} e.g. "₱1,290.00"
   */
  format(amount, isCents = false) {
    const val = isCents ? this.fromCents(amount) : Number(amount || 0);
    return '₱' + val.toLocaleString('en-PH', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  },

  /**
   * Compute deterministic cart summary
   * @param {Array<{price: number, quantity: number}>} items
   * @param {number} discountPercent e.g. 10 for 10%
   * @param {number} freeShippingThreshold e.g. 1500
   * @param {number} standardShippingFee e.g. 150
   * @returns {{subtotal: number, discountAmount: number, shippingFee: number, total: number}}
   */
  calculateTotals(items = [], discountPercent = 0, freeShippingThreshold = 1500, standardShippingFee = 150) {
    let subtotalCents = 0;
    for (const item of items) {
      const priceCents = this.toCents(item.price);
      const qty = Math.max(0, parseInt(item.quantity, 10) || 0);
      subtotalCents += priceCents * qty;
    }

    const subtotal = this.fromCents(subtotalCents);

    // Discount
    let discountCents = 0;
    if (discountPercent > 0) {
      discountCents = Math.round(subtotalCents * (discountPercent / 100));
    }
    const discountAmount = this.fromCents(discountCents);

    // Shipping fee
    let shippingFee = 0;
    if (subtotalCents > 0) {
      shippingFee = subtotal >= freeShippingThreshold ? 0 : standardShippingFee;
    }

    // Grand total
    const totalCents = Math.max(0, subtotalCents - discountCents + this.toCents(shippingFee));
    const total = this.fromCents(totalCents);

    return {
      subtotal,
      discountAmount,
      shippingFee,
      total,
      subtotalFormatted: this.format(subtotal),
      discountFormatted: this.format(discountAmount),
      shippingFormatted: shippingFee === 0 ? 'FREE' : this.format(shippingFee),
      totalFormatted: this.format(total),
    };
  },
};

Object.freeze(Money);

if (typeof module !== 'undefined' && module.exports) {
  module.exports = Money;
}
