/**
 * VAPEHAUS AUTHENTICATION MODAL COMPONENT (WITH RBAC REDIRECTION & 18+ OTP)
 * Handles Sign In, 2-Step OTP Account Registration, Password Reset, and 18+ Statutory Checks
 */

const AuthModal = {
  overlayEl: null,
  activeTab: 'login',
  regOtpSent: false,
  regPendingData: null,
  forgotOtpSent: false,
  forgotEmail: '',

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
    this.regOtpSent = false;
    this.regPendingData = null;
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
    if (tab === 'register') {
      this.regOtpSent = false;
      this.regPendingData = null;
    }
    if (tab === 'forgot') {
      this.forgotOtpSent = false;
      this.forgotEmail = '';
    }
    this.render();
  },

  togglePasswordVisibility(inputId, toggleBtn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    if (input.type === 'password') {
      input.type = 'text';
      toggleBtn.innerHTML = typeof Icons !== 'undefined' ? Icons.eyeOff(16) : 'Hide';
    } else {
      input.type = 'password';
      toggleBtn.innerHTML = typeof Icons !== 'undefined' ? Icons.eye(16) : 'Show';
    }
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
      helperEl.innerHTML = `<span style="color: var(--danger); font-weight: 700;">(Underage: Must be at least 18 years old)</span>`;
    }
  },

  render() {
    const bodyEl = document.getElementById('auth-modal-body');
    if (!bodyEl) return;

    const isLogin = this.activeTab === 'login';
    const isRegister = this.activeTab === 'register';
    const isForgot = this.activeTab === 'forgot';

    bodyEl.innerHTML = `
      <div style="padding: 30px;">
        <div class="auth-tabs-bar">
          <button class="auth-tab-btn ${isLogin ? 'active' : ''}" onclick="AuthModal.setTab('login')">
            Sign In
          </button>
          <button class="auth-tab-btn ${isRegister ? 'active' : ''}" onclick="AuthModal.setTab('register')">
            Create Account
          </button>
          ${isForgot ? `
            <button class="auth-tab-btn active" style="cursor: default;">
              Reset Password
            </button>
          ` : ''}
        </div>

        ${isLogin ? this.renderLoginForm() : isForgot ? this.renderForgotForm() : this.renderRegisterForm()}
      </div>
    `;
  },

  renderLoginForm() {
    return `
      <form onsubmit="AuthModal.handleLoginSubmit(event)">
        <div class="input-group">
          <label class="input-label">Email Address <span class="required">*</span></label>
          <input type="email" id="auth-login-email" class="input-control" placeholder="you@domain.ph" required>
        </div>

        <div class="input-group">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <label class="input-label" style="margin: 0;">Password <span class="required">*</span></label>
            <a href="#" style="font-size: 0.78rem; color: var(--accent2);" onclick="event.preventDefault(); AuthModal.setTab('forgot')">Forgot password?</a>
          </div>
          <div style="position: relative;">
            <input type="password" id="auth-login-password" class="input-control" placeholder="Enter your password" required style="padding-right: 42px;">
            <button type="button" class="password-toggle-btn" onclick="AuthModal.togglePasswordVisibility('auth-login-password', this)">${typeof Icons !== 'undefined' ? Icons.eye(16) : 'Show'}</button>
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
    if (this.regOtpSent && this.regPendingData) {
      return `
        <form onsubmit="AuthModal.handleRegisterOtpSubmit(event)">
          <div style="text-align: center; margin-bottom: 20px;">
            <div style="margin-bottom: 8px; color: var(--accent2);">${typeof Icons !== 'undefined' ? Icons.mail(36) : ''}</div>
            <h3>Enter Verification Code</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
              A 6-digit code has been dispatched to <b>${this.regPendingData.email}</b>.
            </p>
          </div>

          <div class="input-group">
            <label class="input-label" style="text-align: center;">6-Digit OTP Code <span class="required">*</span></label>
            <input type="text" id="reg-otp-code" class="input-control" placeholder="123456" maxlength="6" style="text-align: center; font-size: 1.4rem; letter-spacing: 6px; font-weight: 700;" required autofocus>
          </div>

          <button type="submit" class="btn btn-lg btn-cyan btn-block" style="margin-bottom: 12px;">
            Verify & Create 18+ Account
          </button>

          <div style="display: flex; justify-content: space-between; font-size: 0.82rem; margin-top: 14px;">
            <a href="#" style="color: var(--text-muted);" onclick="event.preventDefault(); AuthModal.regOtpSent = false; AuthModal.render();">← Back to Details</a>
            <a href="#" style="color: var(--accent2);" onclick="event.preventDefault(); AuthModal.resendRegOtp();">Resend Code</a>
          </div>
        </form>
      `;
    }

    return `
      <form onsubmit="AuthModal.handleRegisterInitialSubmit(event)">
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
          Send Verification Code →
        </button>

        <div style="text-align: center; font-size: 0.85rem; color: var(--text-muted);">
          Already have an account? 
          <a href="#" style="color: var(--accent2); font-weight: 600;" onclick="AuthModal.setTab('login')">Sign in here</a>
        </div>
      </form>
    `;
  },

  renderForgotForm() {
    if (this.forgotOtpSent) {
      return `
        <form onsubmit="AuthModal.handleResetPasswordSubmit(event)">
          <div style="text-align: center; margin-bottom: 20px;">
            <div style="margin-bottom: 8px; color: var(--accent2);">${typeof Icons !== 'undefined' ? Icons.key(36) : ''}</div>
            <h3>Set New Password</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
              Enter the OTP sent to <b>${this.forgotEmail}</b> along with your new password.
            </p>
          </div>

          <div class="input-group">
            <label class="input-label">6-Digit Reset Code <span class="required">*</span></label>
            <input type="text" id="reset-otp-code" class="input-control" placeholder="123456" maxlength="6" style="text-align: center; font-size: 1.3rem; letter-spacing: 5px; font-weight: 700;" required autofocus>
          </div>

          <div class="input-group">
            <label class="input-label">New Password <span class="required">*</span></label>
            <input type="password" id="reset-new-pass" class="input-control" placeholder="Min. 8 characters" required>
          </div>

          <button type="submit" class="btn btn-lg btn-cyan btn-block" style="margin-bottom: 12px;">
            Update Password & Sign In
          </button>

          <div style="text-align: center; font-size: 0.85rem; margin-top: 14px;">
            <a href="#" style="color: var(--text-muted);" onclick="event.preventDefault(); AuthModal.setTab('login')">← Back to Sign In</a>
          </div>
        </form>
      `;
    }

    return `
      <form onsubmit="AuthModal.handleForgotPasswordSubmit(event)">
        <div style="text-align: center; margin-bottom: 20px;">
          <div style="margin-bottom: 8px; color: var(--accent2);">${typeof Icons !== 'undefined' ? Icons.lock(36) : ''}</div>
          <h3>Reset Your Password</h3>
          <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
            Enter your registered email address and we'll dispatch a 6-digit recovery OTP code.
          </p>
        </div>

        <div class="input-group">
          <label class="input-label">Registered Email <span class="required">*</span></label>
          <input type="email" id="forgot-email" class="input-control" placeholder="you@domain.ph" required>
        </div>

        <button type="submit" class="btn btn-lg btn-block" style="margin-bottom: 16px;">
          Send Password Reset Code →
        </button>

        <div style="text-align: center; font-size: 0.85rem; color: var(--text-muted);">
          Remembered your password? 
          <a href="#" style="color: var(--accent2); font-weight: 600;" onclick="AuthModal.setTab('login')">Sign in</a>
        </div>
      </form>
    `;
  },

  async handleLoginSubmit(e) {
    e.preventDefault();
    const email = document.getElementById('auth-login-email').value.trim();
    const password = document.getElementById('auth-login-password').value;

    const submitBtn = e.target.querySelector('[type=submit]');
    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Signing in…'; }

    try {
      const result = await Api.login(email, password);

      if (!result.success) {
        Toast.error(result.error?.message || 'Invalid email or password.');
        return;
      }

      const user = result.data.user;
      this.close();

      if (user.role === 'admin') {
        Toast.success(`Admin authorized: ${user.firstName}. Redirecting to Admin Dashboard…`);
        setTimeout(() => { window.location.href = 'admin.html'; }, 600);
      } else {
        Toast.success(`Welcome back, ${user.firstName}! Signed in as Customer.`);
        this.updateHeaderAuthStatus();
        if (typeof CheckoutModal !== 'undefined' && CheckoutModal.prefillFromSession) {
          CheckoutModal.prefillFromSession();
        }
        if (typeof Api !== 'undefined' && Api.getOrders) {
          Api.getOrders().catch(() => {});
        }
        if (window.location.pathname.includes('admin.html')) {
          window.location.href = 'index.html';
        }
      }
    } catch (err) {
      Toast.error(err.message || 'An unexpected error occurred. Please try again.');
    } finally {
      if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Sign In to VAPEHAUS →'; }
    }
  },

  async handleRegisterInitialSubmit(e) {
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

    const age = this.calculateAge(birthDate);
    if (age < 18) {
      Toast.error('You must be at least 18 years old to create an account (RA 11900).');
      return;
    }

    const submitBtn = e.target.querySelector('[type=submit]');
    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Sending OTP code…'; }

    try {
      this.regPendingData = {
        firstName, lastName, email, phone, birthDate, password, isAgeAttested,
      };

      const res = await Api.sendOtp(email, 'registration');
      if (res && res.success) {
        this.regOtpSent = true;
        // Store the HMAC token for stateless OTP verification
        this.regOtpToken = res.data?.otp_token || null;
        Toast.success('Verification code has been dispatched to your email.');
        this.render();
      } else {
        Toast.error(res?.error?.message || 'Failed to dispatch OTP email.');
      }
    } catch (err) {
      Toast.error(err.message || 'Error requesting verification code.');
    } finally {
      if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Send Verification Code →'; }
    }
  },

  async resendRegOtp() {
    if (!this.regPendingData?.email) return;
    try {
      const res = await Api.sendOtp(this.regPendingData.email, 'registration');
      if (res && res.success) {
        this.regOtpToken = res.data?.otp_token || null;
        Toast.success('New verification code has been dispatched to your email.');
      } else {
        Toast.error(res?.error?.message || 'Failed to resend code.');
      }
    } catch (err) {
      Toast.error(err.message);
    }
  },

  async handleRegisterOtpSubmit(e) {
    e.preventDefault();
    const otp = document.getElementById('reg-otp-code').value.trim();
    if (!otp || otp.length < 4) {
      Toast.error('Please enter the verification code.');
      return;
    }

    const submitBtn = e.target.querySelector('[type=submit]');
    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Verifying…'; }

    try {
      const payload = {
        ...this.regPendingData,
        otp,
        otp_token: this.regOtpToken || '',
      };

      const result = await Api.register(payload);
      if (!result.success) {
        Toast.error(result.error?.message || 'Registration failed.');
        return;
      }

      Toast.success(`Account registered! Welcome to VAPEHAUS, ${result.data.user.firstName}.`);
      this.close();
      this.updateHeaderAuthStatus();
      if (typeof CheckoutModal !== 'undefined' && CheckoutModal.prefillFromSession) {
        CheckoutModal.prefillFromSession();
      }
      if (typeof Api !== 'undefined' && Api.getOrders) {
        Api.getOrders().catch(() => {});
      }
    } catch (err) {
      Toast.error(err.message || 'An unexpected error occurred. Please try again.');
    } finally {
      if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Verify & Create 18+ Account'; }
    }
  },

  async handleForgotPasswordSubmit(e) {
    e.preventDefault();
    const email = document.getElementById('forgot-email').value.trim();
    const submitBtn = e.target.querySelector('[type=submit]');
    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Sending recovery code…'; }

    try {
      const res = await Api.forgotPassword(email);
      if (res && res.success) {
        this.forgotEmail = email;
        this.forgotOtpSent = true;
        this.forgotOtpToken = res.data?.otp_token || null;
        Toast.success('Password reset code has been dispatched to your email.');
        this.render();
      } else {
        Toast.error(res?.error?.message || 'Failed to send password reset code.');
      }
    } catch (err) {
      Toast.error(err.message);
    } finally {
      if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Send Password Reset Code →'; }
    }
  },

  async handleResetPasswordSubmit(e) {
    e.preventDefault();
    const otp = document.getElementById('reset-otp-code').value.trim();
    const newPassword = document.getElementById('reset-new-pass').value;

    if (newPassword.length < 6) {
      Toast.error('Password must be at least 6 characters.');
      return;
    }

    const submitBtn = e.target.querySelector('[type=submit]');
    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Resetting…'; }

    try {
      const res = await Api.resetPassword(this.forgotEmail, otp, newPassword, this.forgotOtpToken || '');
      if (res && res.success) {
        Toast.success('Password updated successfully! Please sign in with your new credentials.');
        this.setTab('login');
      } else {
        Toast.error(res?.error?.message || 'Failed to reset password. Check code.');
      }
    } catch (err) {
      Toast.error(err.message);
    } finally {
      if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Update Password & Sign In'; }
    }
  },

  logout() {
    if (typeof Api !== 'undefined' && Api.logout) {
      Api.logout().catch(() => {});
    }
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
          ${typeof Icons !== 'undefined' ? Icons.user(16) : ''}
          <b>Sign In</b>
        </button>
      `;
    } else if (user.role === 'admin') {
      authContainer.innerHTML = `
        <button class="btn-header-auth admin" onclick="window.location.href='admin.html'" title="Administrator Session - Go to Admin Dashboard">
          ${typeof Icons !== 'undefined' ? Icons.zap(16) : ''}
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
