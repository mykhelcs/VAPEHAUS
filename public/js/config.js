/**
 * VAPEHAUS APPLICATION CONFIGURATION
 * Production configuration with real API endpoints and Supabase / PayMongo credentials
 */

const CONFIG = {
  APP_NAME: 'VAPEHAUS',
  APP_VERSION: '2.0.0-PROD',
  /**
   * Production Mode: ENABLE_LOCAL_FALLBACK is FALSE.
   * All API calls interact with the live PHP backend and database.
   */
  ENABLE_LOCAL_FALLBACK: false,
  CURRENCY: {
    CODE: 'PHP',
    SYMBOL: '₱',
    DECIMALS: 2,
  },
  LEGAL: {
    MIN_AGE: 18,
    WARNING_TEXT: 'WARNING: This product contains nicotine. Nicotine is an addictive chemical. For adults 18+ only.',
  },
  SHIPPING: {
    STANDARD_FEE: 150.0,
    EXPRESS_FEE: 250.0,
    FREE_SHIPPING_THRESHOLD: 1500.0, // Free shipping if subtotal >= 1500 PHP
  },
  STORAGE_KEYS: {
    STATE: 'VAPEHAUS_STORE_STATE_PROD_V1',
    AGE_VERIFIED: 'VAPEHAUS_AGE_VERIFIED_V1',
    SESSION_TOKEN: 'VAPEHAUS_SESSION_TOKEN',
  },
  /**
   * API base URL — auto-detected from the current page's origin + path prefix.
   * Works whether served by Apache (/vapehaus/public/), PHP dev server (/), or Render.
   * Strip everything from /api/ onwards so we always resolve relative to the doc root.
   */
  API_BASE_URL: (function () {
    const pathname = window.location.pathname;
    // Find the deepest path component that is NOT a file or known route segment.
    // We walk back from the current path to find the public root by removing
    // known non-root segments like 'admin.html', 'index.html', etc.
    const cleaned = pathname.replace(/\/(index\.html|admin\.html|admin\/?)?$/, '/');
    const base = cleaned.replace(/\/$/, ''); // trim trailing slash
    return base + '/api/v1';
  })(),
  PAYMONGO_PUBLIC_KEY: 'pk_test_Y8qhFjBW4NWiu8A36T74Mix2',
  SUPABASE_URL: 'https://xpegspccnihndttvhfna.supabase.co',
  SUPABASE_ANON_KEY: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhwZWdzcGNjbmlobmR0dHZoZm5hIiwicm9sZSI6ImFub24iLCJpYXQiOjE3OTMzMzg3OTQsImV4cCI6MjEwODkxNDc5NH0.cfGRSxiLKArKi2NSMFIuD41-rjK6NArlF7BWRRcbdxo',
};

// Freeze configuration to guarantee determinism
Object.freeze(CONFIG);
Object.freeze(CONFIG.CURRENCY);
Object.freeze(CONFIG.LEGAL);
Object.freeze(CONFIG.SHIPPING);
Object.freeze(CONFIG.STORAGE_KEYS);

if (typeof module !== 'undefined' && module.exports) {
  module.exports = CONFIG;
}
