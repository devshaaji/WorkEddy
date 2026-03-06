<?php
$pageTitle = 'Sign In';
ob_start();
?>
<div class="auth-wrapper" x-data="loginPage">
  <div class="auth-card">

    <!-- Brand -->
    <div class="auth-brand">
      <div class="auth-brand-logo"><i class="bi bi-activity"></i></div>
      <span class="auth-brand-name">WorkEddy</span>
    </div>

    <h1 class="auth-title">Welcome back</h1>
    <p class="auth-subtitle">Sign in to your workspace to continue.</p>

    <!-- Error alert -->
    <div class="alert alert-danger align-items-center gap-2 py-2"
         x-show="error" x-text="error" x-cloak>
    </div>

    <form @submit.prevent="submit">
      <div class="mb-3">
        <label class="form-label" for="loginEmail">Email address</label>
        <input class="form-control" id="loginEmail" type="email"
               x-model="email" placeholder="you@company.com" required autocomplete="email">
      </div>

      <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <label class="form-label mb-0" for="loginPassword">Password</label>
          <a href="/forgot-password" class="text-decoration-none text-sm text-primary">Forgot password?</a>
        </div>
        <input class="form-control" id="loginPassword" type="password"
               x-model="password" placeholder="••••••••" required autocomplete="current-password">
      </div>

      <button class="btn btn-primary w-100 btn-lg mb-3" type="submit" :disabled="loading">
        <span class="spinner-border spinner-border-sm me-2" x-show="loading" x-cloak></span>
        <span x-text="loading ? 'Signing in…' : 'Sign in'"></span>
      </button>
    </form>

    <p class="text-center mb-0 small">
      Don't have an account?
      <a href="/register" class="text-decoration-none fw-semibold text-primary">Create workspace</a>
    </p>

  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/auth.php';
