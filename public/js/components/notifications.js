/**
 * VAPEHAUS TOAST NOTIFICATION COMPONENT
 */

const Toast = {
  container: null,

  init() {
    if (!this.container) {
      let el = document.getElementById('toast-container');
      if (!el) {
        el = document.createElement('div');
        el.id = 'toast-container';
        document.body.appendChild(el);
      }
      this.container = el;
    }
  },

  show(message, type = 'info', duration = 3500) {
    this.init();
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    let icon = 'ℹ️';
    if (type === 'success') icon = '✅';
    if (type === 'danger') icon = '⚠️';

    toast.innerHTML = `<span>${icon}</span><div style="flex:1">${message}</div>`;

    this.container.appendChild(toast);

    setTimeout(() => {
      toast.style.transition = 'opacity 0.3s, transform 0.3s';
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(12px) scale(0.95)';
      setTimeout(() => {
        if (toast.parentNode) {
          toast.parentNode.removeChild(toast);
        }
      }, 300);
    }, duration);
  },

  success(msg) {
    this.show(msg, 'success');
  },

  error(msg) {
    this.show(msg, 'danger', 4500);
  },

  info(msg) {
    this.show(msg, 'info');
  },
};

if (typeof module !== 'undefined' && module.exports) {
  module.exports = Toast;
}
