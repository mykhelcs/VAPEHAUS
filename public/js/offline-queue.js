/**
 * VAPEHAUS OFFLINE ACTION QUEUE & SYNC MANAGER
 * Persists mutations (Cart, Orders, KYC) when offline or network fails
 * and automatically drains & synchronizes with the server when connection returns.
 */

const OfflineQueue = {
  STORAGE_KEY: 'VAPEHAUS_OFFLINE_ACTION_QUEUE_V1',
  isSyncing: false,

  init() {
    window.addEventListener('online', () => {
      this.updateOfflineBanner(false);
      if (typeof Toast !== 'undefined') {
        Toast.success('Network connection restored. Syncing pending actions…');
      }
      this.processQueue();
    });

    window.addEventListener('offline', () => {
      this.updateOfflineBanner(true);
      if (typeof Toast !== 'undefined') {
        Toast.warning('Network connection lost. Offline mode activated; actions will be queued.');
      }
    });

    // Check initial online status
    if (!navigator.onLine) {
      this.updateOfflineBanner(true);
    }

    // Periodic heartbeat to retry queued actions if online
    setInterval(() => {
      if (navigator.onLine && this.getQueue().length > 0 && !this.isSyncing) {
        this.processQueue();
      }
    }, 15000);
  },

  isOnline() {
    return navigator.onLine;
  },

  getQueue() {
    try {
      const raw = localStorage.getItem(this.STORAGE_KEY);
      return raw ? JSON.parse(raw) : [];
    } catch {
      return [];
    }
  },

  saveQueue(queue) {
    try {
      localStorage.setItem(this.STORAGE_KEY, JSON.stringify(queue));
    } catch (e) {
      console.error('Failed to save offline queue', e);
    }
  },

  enqueue(action) {
    const queue = this.getQueue();
    const item = {
      id: 'queue_' + Date.now() + '_' + Math.random().toString(36).substring(2, 7),
      type: action.type || 'MUTATION',
      url: action.url,
      method: action.method || 'POST',
      body: action.body || null,
      headers: action.headers || {},
      timestamp: Date.now(),
      label: action.label || 'Action',
    };
    queue.push(item);
    this.saveQueue(queue);

    if (typeof Toast !== 'undefined') {
      Toast.info(`Offline: "${item.label}" has been queued. It will sync automatically when online.`);
    }

    return item;
  },

  async processQueue() {
    if (this.isSyncing) return;
    const queue = this.getQueue();
    if (queue.length === 0) return;

    this.isSyncing = true;
    const remaining = [];
    let syncedCount = 0;

    for (const item of queue) {
      try {
        const fetchOptions = {
          method: item.method,
          headers: {
            'Content-Type': 'application/json',
            ...item.headers,
          },
        };
        if (item.body) {
          fetchOptions.body = JSON.stringify(item.body);
        }

        const res = await fetch(item.url, fetchOptions);
        if (res.ok) {
          syncedCount++;
        } else {
          // If server error or validation, keep in queue or discard based on status
          if (res.status >= 500) {
            remaining.push(item);
          }
        }
      } catch (err) {
        // Still offline, retain remaining items
        remaining.push(item);
      }
    }

    this.saveQueue(remaining);
    this.isSyncing = false;

    if (syncedCount > 0 && typeof Toast !== 'undefined') {
      Toast.success(`Successfully synchronized ${syncedCount} queued action(s) with the server!`);
    }
  },

  updateOfflineBanner(isOffline) {
    let banner = document.getElementById('vapehaus-offline-banner');
    if (isOffline) {
      if (!banner) {
        banner = document.createElement('div');
        banner.id = 'vapehaus-offline-banner';
        banner.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; background: var(--warning, #f59e0b); color: #000; text-align: center; font-size: 0.82rem; font-weight: 700; padding: 6px 12px; z-index: 10000; display: flex; align-items: center; justify-content: center; gap: 8px;';
        banner.innerHTML = `<span>⚠️</span> Offline Mode: Browsing cached catalog. Actions are safely queued and will sync when connection returns.`;
        document.body.prepend(banner);
      }
    } else if (banner) {
      banner.remove();
    }
  }
};

if (typeof window !== 'undefined') {
  window.OfflineQueue = OfflineQueue;
  document.addEventListener('DOMContentLoaded', () => OfflineQueue.init());
}
if (typeof module !== 'undefined' && module.exports) {
  module.exports = OfflineQueue;
}
