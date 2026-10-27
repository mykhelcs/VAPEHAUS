/**
 * VAPEHAUS APPLICATION CONFIGURATION
 * Constants, API routes, currency formatting, and default configurations
 */

const CONFIG = {
  APP_NAME: 'VAPEHAUS',
  APP_VERSION: '2.0.0-PROD',
  /**
   * Set to true during local development (no PHP backend running).
   * The ApiClient will fall back to the in-memory Store adapter.
   * MUST be false in production — set via server-rendered meta tag or
   * swap this file for a production build with the flag off.
   */
  ENABLE_LOCAL_FALLBACK: true,
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
    STATE: 'VAPEHAUS_STORE_STATE_V3',
    AGE_VERIFIED: 'VAPEHAUS_AGE_VERIFIED_V1',
    SESSION_TOKEN: 'VAPEHAUS_SESSION_TOKEN',
  },
  API_BASE_URL: '/api/v1',
  PAYMONGO_PUBLIC_KEY: 'pk_test_YOUR_PAYMONGO_PUBLIC_KEY',
  SUPABASE_URL: 'https://YOUR-PROJECT-REF.supabase.co',
  SUPABASE_ANON_KEY: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...',
  DEFAULT_USER: {
    id: 'usr_customer_default',
    email: 'customer@vapehaus.ph',
    firstName: 'Juan',
    lastName: 'Dela Cruz',
    phone: '+63 917 888 9999',
    role: 'customer',
    isAgeVerified: true,
  },
  DEFAULT_ADMIN: {
    id: 'usr_admin_default',
    email: 'admin@vapehaus.ph',
    firstName: 'Vapehaus',
    lastName: 'Administrator',
    role: 'admin',
  },
};

// Freeze configuration to guarantee determinism
Object.freeze(CONFIG);
Object.freeze(CONFIG.CURRENCY);
Object.freeze(CONFIG.LEGAL);
Object.freeze(CONFIG.SHIPPING);
Object.freeze(CONFIG.STORAGE_KEYS);
// Note: DEFAULT_USER / DEFAULT_ADMIN intentionally NOT deep-frozen so
// setSessionFromApi can extend them safely via spread.

if (typeof module !== 'undefined' && module.exports) {
  module.exports = CONFIG;
}
