/**
 * VAPEHAUS CANVA-STYLE VECTOR ICON SYSTEM
 * Crisp, approachable, standardized 24x24 vector icons
 * Completely replaces mixed OS emojis across the storefront & admin
 */

const Icons = {
  _svg(pathD, size = 20, cls = 'canva-icon', extraAttr = '') {
    return `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="${cls}" ${extraAttr}>${pathD}</svg>`;
  },

  cart(size = 20, cls = '') {
    return this._svg(
      `<circle cx="9" cy="21" r="1.5"/><circle cx="19" cy="21" r="1.5"/><path d="M2.5 3h3l2.6 12.3a2 2 0 0 0 2 1.7h9.8a2 2 0 0 0 2-1.6L23 7H6.5"/>`,
      size,
      cls
    );
  },

  search(size = 20, cls = '') {
    return this._svg(
      `<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16" y2="16"/>`,
      size,
      cls
    );
  },

  user(size = 20, cls = '') {
    return this._svg(
      `<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>`,
      size,
      cls
    );
  },

  age18(size = 24, cls = '') {
    return `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" class="canva-icon ${cls}">
      <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
      <path d="M7.5 9.5 L8.5 8 L8.5 16 M7 16 L10 16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
      <circle cx="13.5" cy="10" r="2" stroke="currentColor" stroke-width="1.6"/>
      <circle cx="13.5" cy="14" r="2" stroke="currentColor" stroke-width="1.6"/>
      <path d="M17.5 9v4M15.5 11h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
    </svg>`;
  },

  truck(size = 20, cls = '') {
    return this._svg(
      `<rect x="2" y="5" width="13" height="11" rx="1.5"/><path d="M15 9h4l3 3.5V16h-7V9z"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>`,
      size,
      cls
    );
  },

  verified(size = 20, cls = '') {
    return this._svg(
      `<path d="M12 2l2.6 2.3 3.4-.6 1.4 3.1 3.1 1.4-.6 3.4 2.3 2.6-2.3 2.6.6 3.4-3.1 1.4-1.4 3.1-3.4-.6L12 22l-2.6-2.3-3.4.6-1.4-3.1-3.1-1.4.6-3.4-2.3-2.6 2.3-2.6-.6-3.4 3.1-1.4 1.4-3.1 3.4.6L12 2z"/><path d="m9 12 2 2 4-4"/>`,
      size,
      cls
    );
  },

  shield(size = 20, cls = '') {
    return this._svg(
      `<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>`,
      size,
      cls
    );
  },

  lock(size = 20, cls = '') {
    return this._svg(
      `<rect x="4" y="11" width="16" height="11" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>`,
      size,
      cls
    );
  },

  chat(size = 20, cls = '') {
    return this._svg(
      `<path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7A8.5 8.5 0 1 1 21 11.5z"/>`,
      size,
      cls
    );
  },

  zap(size = 20, cls = '') {
    return this._svg(
      `<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>`,
      size,
      cls
    );
  },

  box(size = 20, cls = '') {
    return this._svg(
      `<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>`,
      size,
      cls
    );
  },

  tag(size = 20, cls = '') {
    return this._svg(
      `<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>`,
      size,
      cls
    );
  },

  ticket(size = 20, cls = '') {
    return this._svg(
      `<rect x="2" y="6" width="20" height="12" rx="2"/><path d="M12 6v3m0 6v3M2 12h2m16 0h2"/>`,
      size,
      cls
    );
  },

  idCard(size = 20, cls = '') {
    return this._svg(
      `<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2.5"/><path d="M5.5 16a3.5 3.5 0 0 1 7 0M15 9h4M15 13h3"/>`,
      size,
      cls
    );
  },

  users(size = 20, cls = '') {
    return this._svg(
      `<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>`,
      size,
      cls
    );
  },

  chart(size = 20, cls = '') {
    return this._svg(
      `<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>`,
      size,
      cls
    );
  },

  store(size = 20, cls = '') {
    return this._svg(
      `<path d="M3 9l2-6h14l2 6"/><path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/><path d="M10 21v-5h4v5"/>`,
      size,
      cls
    );
  },

  eye(size = 20, cls = '') {
    return this._svg(
      `<path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6z"/><circle cx="12" cy="12" r="3"/>`,
      size,
      cls
    );
  },

  eyeOff(size = 20, cls = '') {
    return this._svg(
      `<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" y1="2" x2="22" y2="22"/>`,
      size,
      cls
    );
  },

  close(size = 20, cls = '') {
    return this._svg(
      `<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>`,
      size,
      cls
    );
  },

  menu(size = 20, cls = '') {
    return this._svg(
      `<line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/>`,
      size,
      cls
    );
  },

  logout(size = 20, cls = '') {
    return this._svg(
      `<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>`,
      size,
      cls
    );
  },

  creditCard(size = 20, cls = '') {
    return this._svg(
      `<rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>`,
      size,
      cls
    );
  },

  cash(size = 20, cls = '') {
    return this._svg(
      `<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><line x1="6" y1="12" x2="6.01" y2="12"/><line x1="18" y1="12" x2="18.01" y2="12"/>`,
      size,
      cls
    );
  },

  phone(size = 20, cls = '') {
    return this._svg(
      `<rect x="5" y="2" width="14" height="20" rx="3"/><line x1="12" y1="18" x2="12.01" y2="18"/>`,
      size,
      cls
    );
  },

  alert(size = 20, cls = '') {
    return this._svg(
      `<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>`,
      size,
      cls
    );
  },

  mail(size = 20, cls = '') {
    return this._svg(
      `<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>`,
      size,
      cls
    );
  },

  key(size = 20, cls = '') {
    return this._svg(
      `<path d="m21 2-2 2m-1.5 1.5L16 7l1.5 1.5-1.5 1.5L14 9.5l-3.3 3.3a5.5 5.5 0 1 1-1.4-1.4L18 2h3v2l-1.5 1.5z"/><circle cx="7.5" cy="16.5" r="1.5"/>`,
      size,
      cls
    );
  },

  check(size = 18, cls = '') {
    return this._svg(
      `<polyline points="20 6 9 17 4 12"/>`,
      size,
      cls
    );
  },

  plus(size = 18, cls = '') {
    return this._svg(
      `<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>`,
      size,
      cls
    );
  },

  trash(size = 18, cls = '') {
    return this._svg(
      `<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>`,
      size,
      cls
    );
  },

  edit(size = 18, cls = '') {
    return this._svg(
      `<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>`,
      size,
      cls
    );
  },

  star(filled = true, size = 16, cls = '') {
    const fill = filled ? 'currentColor' : 'none';
    return `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 24 24" fill="${fill}" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="canva-icon ${cls}">
      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
    </svg>`;
  }
};

if (typeof window !== 'undefined') {
  window.Icons = Icons;
}
if (typeof module !== 'undefined' && module.exports) {
  module.exports = Icons;
}
