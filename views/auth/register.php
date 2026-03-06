<?php
$pageTitle = 'Create Account';
ob_start();
?>
<div class="auth-wrapper" x-data="registerPage">
  <div class="auth-card" style="max-width:480px;">

    <!-- Brand -->
    <div class="auth-brand">
      <div class="auth-brand-logo"><i class="bi bi-activity"></i></div>
      <span class="auth-brand-name">WorkEddy</span>
    </div>

    <h1 class="auth-title">Create your workspace</h1>
    <p class="auth-subtitle">Free trial — no credit card required.</p>

    <div class="alert alert-danger align-items-center gap-2 py-2"
         x-show="error" x-text="error" x-cloak></div>

    <form @submit.prevent="submit">

      <div class="mb-3">
        <label class="form-label" for="regOrg">Organization name</label>
        <input class="form-control" id="regOrg" type="text"
               x-model="orgName" placeholder="Acme Logistics" required>
        <div class="form-text">This becomes your workspace identifier.</div>
      </div>

      <div class="mb-3">
        <label class="form-label" for="regName">Your full name</label>
        <input class="form-control" id="regName" type="text"
               x-model="name" placeholder="Jane Smith" required autocomplete="name">
      </div>

      <div class="mb-3">
        <label class="form-label" for="regEmail">Work email</label>
        <input class="form-control" id="regEmail" type="email"
               x-model="email" placeholder="jane@acme.com" required autocomplete="email">
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6">
          <label class="form-label" for="regPass">Password</label>
          <input class="form-control" id="regPass" type="password"
                 x-model="password" placeholder="Min 8 chars" required autocomplete="new-password">
        </div>
        <div class="col-6">
          <label class="form-label" for="regPass2">Confirm</label>
          <input class="form-control" id="regPass2" type="password"
                 x-model="password2" placeholder="••••••••" required autocomplete="new-password">
        </div>
      </div>

      <button class="btn btn-primary w-100 btn-lg mb-3" type="submit" :disabled="loading">
        <span class="spinner-border spinner-border-sm me-2" x-show="loading" x-cloak></span>
        <span x-text="loading ? 'Creating workspace…' : 'Create workspace'"></span>
      </button>
    </form>

    <p class="text-center mb-0 small">
      Already have an account?
      <a href="/login" class="text-decoration-none fw-semibold text-primary">Sign in</a>
    </p>

  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/auth.php';
