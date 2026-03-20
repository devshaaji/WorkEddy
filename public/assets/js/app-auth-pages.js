/**
 * WorkEddy feature module.
 */

document.addEventListener('alpine:init', () => {

  Alpine.data('loginPage', () => ({
    step: 'credentials',        // 'credentials' | '2fa'
    email: '', password: '', totpCode: '',
    tempToken: '',              // temporary JWT for 2FA verification
    error: '', loading: false,

    /* Step 1 - email & password */
    async submit() {
      this.error = ''; this.loading = true;
      try {
        const d = await api('/auth/login', {
          method: 'POST',
          body: JSON.stringify({ email: this.email, password: this.password })
        });

        if (d.requires_2fa) {
          // User has TOTP enabled - show 2FA code input
          this.tempToken = d.temp_token;
          this.step = '2fa';
          return;
        }

        // Normal login - save token and redirect
        _save(d.token);
        location.href = '/dashboard';
      } catch (e) { this.error = e.message; }
      finally { this.loading = false; }
    },

    /* Step 2 - verify TOTP code */
    async submit2fa() {
      this.error = ''; this.loading = true;
      try {
        const d = await fetch('/api/v1/auth/2fa/verify', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + this.tempToken,
          },
          body: JSON.stringify({ code: this.totpCode }),
        });
        const json = await d.json();
        if (!d.ok) throw new Error(json.error || 'Verification failed');

        _save(json.token);
        location.href = '/dashboard';
      } catch (e) { this.error = e.message; }
      finally { this.loading = false; }
    },

    backToLogin() {
      this.step = 'credentials';
      this.totpCode = '';
      this.tempToken = '';
      this.error = '';
    }
  }));

  /* Register page */

  Alpine.data('registerPage', () => ({
    orgName: '', name: '', email: '', password: '', password2: '', error: '', loading: false,
    async submit() {
      this.error = '';
      if (this.password !== this.password2) { this.error = 'Passwords do not match'; return; }
      this.loading = true;
      try {
        const d = await api('/auth/signup', {
          method: 'POST',
          body: JSON.stringify({ organization_name: this.orgName, name: this.name, email: this.email, password: this.password }),
        });
        _save(d.token);
        location.href = '/dashboard';
      } catch (e) { this.error = e.message; }
      finally { this.loading = false; }
    }
  }));

  /* Forgot-password page */

  Alpine.data('forgotPage', () => ({
    email: '', message: '', isError: false, loading: false,
    async submit() {
      this.message = '';
      if (!this.email.trim()) { this.message = 'Please enter your email.'; this.isError = true; return; }
      this.loading = true;
      this.message = 'If that address exists, a reset link has been sent.';
      this.isError = false;
      this.loading = false;
    }
  }));

  /*Dashboard page */

});
