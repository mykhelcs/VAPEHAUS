/**
 * VAPEHAUS REGULATORY 18+ AGE GATE COMPONENT
 * Enforces mandatory age barrier before site entry in compliance with vape laws
 */

const AgeGate = {
  overlayEl: null,

  init() {
    this.overlayEl = document.getElementById('age-gate-modal');
    if (!this.overlayEl) return;

    const isVerified = localStorage.getItem(CONFIG.STORAGE_KEYS.AGE_VERIFIED) === 'true';

    if (!isVerified) {
      this.overlayEl.classList.remove('hidden');
    } else {
      this.overlayEl.classList.add('hidden');
    }

    const btnConfirm = document.getElementById('btn-age-confirm');
    if (btnConfirm) {
      btnConfirm.addEventListener('click', () => {
        this.verifyAndEnter();
      });
    }
  },

  verifyAndEnter() {
    try {
      localStorage.setItem(CONFIG.STORAGE_KEYS.AGE_VERIFIED, 'true');
    } catch (e) {}

    if (this.overlayEl) {
      this.overlayEl.classList.add('hidden');
    }

    if (typeof Toast !== 'undefined') {
      Toast.success('Age confirmed (18+). Welcome to VAPEHAUS.');
    }
  },

  reset() {
    try {
      localStorage.removeItem(CONFIG.STORAGE_KEYS.AGE_VERIFIED);
    } catch (e) {}
    if (this.overlayEl) {
      this.overlayEl.classList.remove('hidden');
    }
  },
};

if (typeof module !== 'undefined' && module.exports) {
  module.exports = AgeGate;
}
