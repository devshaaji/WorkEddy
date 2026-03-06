<?php
$pageTitle = 'Reset Password';
ob_start();
?>
<div class="auth-wrapper" x-data="forgotPage">
  <div class="auth-card">

    <!-- Brand -->
    <div class="auth-brand">
      <div class="auth-brand-logo"><i class="bi bi-activity"></i></div>
      <span class="auth-brand-name">WorkEddy</span>
    </div>

    <h1 class="auth-title">Reset your password</h1>
    <p class="auth-subtitle">Enter your email and we'll send you a reset link.</p>

    <div class="alert py-2"
         x-show="message" x-cloak
         :class="isError ? 'alert-danger' : 'alert-success'">
      <i class="bi me-2" :class="isError ? 'bi-x-circle' : 'bi-check-circle'"></i>
      <span x-text="message"></span>
    </div>

    <form @submit.prevent="submit">
      <div class="mb-4">
        <label class="form-label" for="forgotEmail">Email address</label>
        <input class="form-control" id="forgotEmail" type="email"
               x-model="email" placeholder="you@example.com" required autocomplete="email">
      </div>

      <button class="btn btn-primary w-100 btn-lg mb-3" type="submit" :disabled="loading">
        <span class="spinner-border spinner-border-sm me-2" x-show="loading" x-cloak></span>
        <span x-text="loading ? 'Sending…' : 'Send reset link'"></span>
      </button>
    </form>

    <p class="text-center mb-0 small">
      <a href="/login" class="text-decoration-none text-primary">
        <i class="bi bi-arrow-left me-1"></i>Back to sign in
      </a>
    </p>

  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/auth.php';
