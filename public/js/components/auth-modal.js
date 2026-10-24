/**
 * VAPEHAUS AUTHENTICATION MODAL COMPONENT (WITH RBAC REDIRECTION)
 * Handles Sign In, Register, 18+ statutory age checks, and role-based redirects
 */

const AuthModal = {
  overlayEl: null,
  activeTab: 'login',

  init() {
    this.overlayEl = document.getElementById('auth-modal');
    if (!this.overlayEl) return;

    const closeBtn = document.getElementById('btn-close-auth');
    if (closeBtn) {
      closeBtn.addEventListener('click', () => this.close());
    }

    this.overlayEl.addEventListener('click', (e) => {
      if (e.target === this.overlayEl) {
        this.close();
      }
    });

    Store.subscribe(() => {
      this.updateHeaderAuthStatus();
    });

    this.updateHeaderAuthStatus();
  },

  openLogin() {
    this.activeTab = 'login';
    this.render();
    if (this.overlayEl) {
      this.overlayEl.classList.add('open');
    }
  },

  openRegister() {
    this.activeTab = 'register';
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

  setTab(tab) {
    this.activeTab = tab;
    this.render();
  },

  togglePasswordVisibility(inputId, toggleBtn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    if (input.type === 'password') {
      input.type = 'text';
      toggleBtn.textContent = '🙈';
    } else {
      input.type = 'password';
      toggleBtn.textContent = '👁️';
    }
  },

  fillDemo(type) {
    const emailEl = document.getElementById('auth-login-email');
    const passEl = document.getElementById('auth-login-password');
    if (type === 'admin') {
      if (emailEl) emailEl.value = 'admin@vapehaus.ph';
      if (passEl) passEl.value = 'AdminPass123!';
    } else {
      if (emailEl) emailEl.value = 'customer@vapehaus.ph';
      if (passEl) passEl.value = 'VapePass123!';
    }
    Toast.info(`Filled ${type.toUpperCase()} demo credentials.`);
  },

  calculateAge(birthDateStr) {
    if (!birthDateStr) return 0;
    const birth = new Date(birthDateStr);
    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const m = today.getMonth() - birth.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) {
      age--;
    }
    return age;
  },

  onBirthDateChange(val) {
    const helperEl = document.getElementById('auth-age-calc-helper');
    if (!helperEl) return;

    if (!val) {
      helperEl.innerHTML = `<span style="color: var(--text-muted);">Required for R.A. 11900 18+ compliance</span>`;
      return;
    }

    const age = this.calculateAge(val);
    if (age >= 18) {
      helperEl.innerHTML = `<span style="color: var(--success); font-weight: 700;">✓ Age: ${age} (Legal Adult 18+ Eligible)</span>`;
    } else {
      helperEl.innerHTML = `<span style="color: var(--danger); font-weight: 700;">⚠️ Age: ${age} (Underage: Must be at least 18 years old)</span>`;
    }
  },

  render() {
    const bodyEl = document.getElementById('auth-modal-body');
    if (!bodyEl) return;

    const isLogin = this.activeTab === 'login';

    bodyEl.innerHTML = `
      <div style="padding: 30px;">
        <div class="auth-tabs-bar">
          <button class="auth-tab-btn ${isLogin ? 'active' : ''}" onclick="AuthModal.setTab('login')">
            Sign In
          </button>
          <button class="auth-tab-btn ${!isLogin ? 'active' : ''}" onclick="AuthModal.setTab('register')">
            Create Account
          </button>
        </div>

        ${isLogin ? this.renderLoginForm() : this.renderRegisterForm()}
      </div>
    `;
  },

  renderLoginForm() {
    return `
      <form onsubmit="AuthModal.handleLoginSubmit(event)">
        <div class="demo-auth-pill-bar">
          <span style="font-size: 0.78rem; color: var(--text-muted);">Quick Demo Fill:</span>
          <button type="button" class="demo-pill-btn" onclick="AuthModal.fillDemo('customer')">👤 Customer Demo</button>
          <button type="button" class="demo-pill-btn" onclick="AuthModal.fillDemo('admin')">⚡ Admin Demo</button>
        </div>

        <div class="input-group">
          <label class="input-label">Email Address <span class="required">*</span></label>
          <input type="email" id="auth-login-email" class="input-control" placeholder="you@domain.ph" required>
        </div>

        <div class="input-group">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <label class="input-label" style="margin: 0;">Password <span class="required">*</span></label>
            <a href="#" style="font-size: 0.78rem; color: var(--accent2);" onclick="Toast.info('Password reset instructions will be sent to your email.')">Forgot password?</a>
          </div>
          <div style="position: relative;">
            <input type="password" id="auth-login-password" class="input-control" placeholder="Enter your password" required style="padding-right: 42px;">
            <button type="button" class="password-toggle-btn" onclick="AuthModal.togglePasswordVisibility('auth-login-password', this)">👁️</button>
          </div>
        </div>

        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 24px;">
          <input type="checkbox" id="auth-login-remember" checked style="accent-color: var(--accent);">
          <label for="auth-login-remember" style="font-size: 0.85rem; color: var(--text-muted); cursor: pointer;">
            Keep me signed in on this device
          </label>
        </div>

        <button type="submit" class="btn btn-lg btn-block" style="margin-bottom: 16px;">
          Sign In to VAPEHAUS →
        </button>

        <div style="text-align: center; font-size: 0.85rem; color: var(--text-muted);">
          New to VAPEHAUS? 
          <a href="#" style="color: var(--accent2); font-weight: 600;" onclick="AuthModal.setTab('register')">Create an account</a>
        </div>
      </form>
    `;
  },

  renderRegisterForm() {
    return `
      <form onsubmit="AuthModal.handleRegisterSubmit(event)">
        <div class="form-grid-2">
          <div class="input-group">
            <label class="input-label">First Name <span class="required">*</span></label>
            <input type="text" id="reg-fname" class="input-control" placeholder="e.g. Juan" required>
          </div>
          <div class="input-group">
            <label class="input-label">Last Name <span class="required">*</span></label>
            <input type="text" id="reg-lname" class="input-control" placeholder="e.g. Dela Cruz" required>
          </div>
        </div>

        <div class="form-grid-2">
          <div class="input-group">
            <label class="input-label">Email Address <span class="required">*</span></label>
            <input type="email" id="reg-email" class="input-control" placeholder="juan@domain.ph" required>
          </div>
          <div class="input-group">
            <label class="input-label">Mobile Number <span class="required">*</span></label>
            <input type="tel" id="reg-phone" class="input-control" placeholder="+63 9XX XXX XXXX" required>
          </div>
        </div>

        <div class="input-group">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <label class="input-label" style="margin: 0;">Date of Birth (18+ Mandatory) <span class="required">*</span></label>
            <span id="auth-age-calc-helper" style="font-size: 0.76rem;"></span>
          </div>
          <input type="date" id="reg-dob" class="input-control" onchange="AuthModal.onBirthDateChange(this.value)" required>
        </div>

        <div class="form-grid-2">
          <div class="input-group">
            <label class="input-label">Password <span class="required">*</span></label>
            <input type="password" id="reg-pass" class="input-control" placeholder="Min. 8 characters" required>
          </div>
          <div class="input-group">
            <label class="input-label">Confirm Password <span class="required">*</span></label>
            <input type="password" id="reg-confirm-pass" class="input-control" placeholder="Re-enter password" required>
          </div>
        </div>

        <label class="legal-checkbox-row" style="margin: 16px 0 20px;">
          <input type="checkbox" id="reg-age-attest" required>
          <div>
            <b>Statutory Adult Age Attestation (18+):</b>
            <div style="color: var(--text-muted); font-size: 0.78rem; margin-top: 2px;">
              I certify under penalty of law that I am at least 18 years old and legally permitted to purchase electronic cigarette products per Republic Act No. 11900.
            </div>
          </div>
        </label>

        <button type="submit" class="btn btn-lg btn-cyan btn-block" style="margin-bottom: 16px;">
          Create 18+ Verified Account
        </button>

        <div style="text-align: center; font-size: 0.85rem; color: var(--text-muted);">
          Already have an account? 
          <a href="#" style="color: var(--accent2); font-weight: 600;" onclick="AuthModal.setTab('login')">Sign in here</a>
        </div>
      </form>
    `;
  },

  handleLoginSubmit(e) {
    e.preventDefault();
    const email = document.getElementById('auth-login-email').value.trim();
    const password = document.getElementById('auth-login-password').value;

    try {
      const user = Store.login(email, password);
      this.close();

      // ROLE-BASED ACCESS CONTROL (RBAC) REDIRECTION:
      if (user.role === 'admin') {
        Toast.success(`Admin authorized: ${user.firstName}. Redirecting to Admin Dashboard...`);
        setTimeout(() => {
          window.location.href = 'admin.html';
        }, 600);
      } else {
        Toast.success(`Welcome back, ${user.firstName}! Signed in as Customer.`);
        this.updateHeaderAuthStatus();
        // If on admin page, redirect customer to storefront
        if (window.location.pathname.includes('admin.html')) {
          window.location.href = 'index.html';
        }
      }
    } catch (err) {
      Toast.error(err.message);
    }
  },

  handleRegisterSubmit(e) {
    e.preventDefault();
    const firstName = document.getElementById('reg-fname').value.trim();
    const lastName = document.getElementById('reg-lname').value.trim();
    const email = document.getElementById('reg-email').value.trim();
    const phone = document.getElementById('reg-phone').value.trim();
    const birthDate = document.getElementById('reg-dob').value;
    const password = document.getElementById('reg-pass').value;
    const confirmPassword = document.getElementById('reg-confirm-pass').value;
    const isAgeAttested = document.getElementById('reg-age-attest').checked;

    if (password !== confirmPassword) {
      Toast.error('Passwords do not match.');
      return;
    }

    try {
      const user = Store.register({
        firstName,
        lastName,
        email,
        phone,
        birthDate,
        password,
        isAgeAttested,
      });

      Toast.success(`Account registered! Welcome to VAPEHAUS, ${user.firstName}.`);
      this.close();
      this.updateHeaderAuthStatus();
    } catch (err) {
      Toast.error(err.message);
    }
  },

  logout() {
    Store.logout();
    Toast.info('Signed out.');
    this.updateHeaderAuthStatus();
    if (window.location.pathname.includes('admin.html')) {
      window.location.href = 'index.html';
    }
  },

  updateHeaderAuthStatus() {
    const user = Store.getCurrentUser();
    const authContainer = document.getElementById('header-auth-container');
    if (!authContainer) return;

    if (!user) {
      authContainer.innerHTML = `
        <button class="btn-header-auth" onclick="AuthModal.openLogin()" title="Sign In or Register">
          <span>👤</span>
          <b>Sign In</b>
        </button>
      `;
    } else if (user.role === 'admin') {
      authContainer.innerHTML = `
        <button class="btn-header-auth admin" onclick="window.location.href='admin.html'" title="Administrator Session - Go to Admin Dashboard">
          <span>⚡</span>
          <b>Admin Portal</b>
        </button>
      `;
    } else {
      authContainer.innerHTML = `
        <button class="btn-header-auth active" onclick="StorefrontApp.openAccountModal()" title="My Profile & Orders">
          <span class="user-avatar-badge">${(user.firstName[0] || 'U') + (user.lastName[0] || '')}</span>
          <b>${user.firstName}</b>
        </button>
      `;
    }
  },
};

if (typeof module !== 'undefined' && module.exports) {
  module.exports = AuthModal;
}
