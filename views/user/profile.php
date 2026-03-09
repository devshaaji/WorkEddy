<?php
$pageTitle  = 'My Profile';
$activePage = 'profile';
ob_start();
?>
<div x-data="userProfilePage">

  <!-- Page Header -->
  <div class="page-header">
    <div>
      <h1 class="page-title">My Profile</h1>
      <p class="page-breadcrumb">Account / Profile</p>
    </div>
  </div>

  <!-- Loading spinner -->
  <div class="text-center py-5" x-show="loading" x-cloak>
    <div class="spinner-border text-primary"></div>
  </div>

  <div x-show="!loading" x-cloak>

    <div class="row g-4">

      <!-- Personal Information -->
      <div class="col-lg-8">
        <div class="card">
          <div class="card-header">
            <h6 class="card-title mb-0"><i class="bi bi-person me-2"></i>Personal Information</h6>
          </div>
          <div class="card-body">

            <div class="alert alert-success align-items-center gap-2 py-2"
                 x-show="saveSuccess" x-cloak x-transition style="display:none"
                 :style="saveSuccess ? 'display:flex' : 'display:none'">
              <i class="bi bi-check-circle-fill"></i>
              <span x-text="saveSuccess"></span>
            </div>
            <div class="alert alert-danger align-items-center gap-2 py-2"
                 x-show="saveError" x-cloak x-transition style="display:none"
                 :style="saveError ? 'display:flex' : 'display:none'"
                 x-text="saveError"></div>

            <div class="mb-3">
              <label class="form-label" for="profileName">Full Name <span class="text-danger">*</span></label>
              <input class="form-control" id="profileName" type="text"
                     x-model="form.name" placeholder="Your name">
            </div>
            <div class="mb-4">
              <label class="form-label" for="profileEmail">Email Address <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input class="form-control" id="profileEmail" type="email"
                       x-model="form.email" placeholder="your@email.com">
              </div>
            </div>

            <div class="d-flex gap-2">
              <button class="btn btn-primary" @click="saveProfile()" :disabled="saving">
                <span x-show="saving" x-cloak class="spinner-border spinner-border-sm me-1"></span>
                <span x-text="saving ? 'Saving…' : 'Save Changes'"></span>
              </button>
              <button class="btn btn-light" @click="resetForm()">Discard</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Account Details -->
      <div class="col-lg-4">
        <div class="card h-100">
          <div class="card-header">
            <h6 class="card-title mb-0">Account Details</h6>
          </div>
          <div class="card-body">
            <dl class="mb-0">
              <dt class="text-muted fw-normal text-sm">Role</dt>
              <dd class="mb-3">
                <span class="badge badge-soft-primary text-capitalize" x-text="profile.role || '—'"></span>
              </dd>
              <dt class="text-muted fw-normal text-sm">Organization ID</dt>
              <dd class="fw-semibold mb-3" x-text="profile.organization_id || '—'"></dd>
              <dt class="text-muted fw-normal text-sm">Email Verified</dt>
              <dd class="mb-3">
                <span class="badge"
                      :class="parseInt(profile.email_verified) ? 'badge-soft-success' : 'badge-soft-warning'"
                      x-text="parseInt(profile.email_verified) ? 'Verified' : 'Not verified'"></span>
              </dd>
              <dt class="text-muted fw-normal text-sm">Joined</dt>
              <dd class="fw-semibold mb-0" x-text="fmtDate(profile.created_at)"></dd>
            </dl>
          </div>
        </div>
      </div>

    </div><!-- /row -->

    <!-- Two-Factor Authentication -->
    <div class="card mt-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0"><i class="bi bi-shield-lock me-2"></i>Two-Factor Authentication</h6>
        <span class="badge" :class="twoFaEnabled ? 'badge-soft-success' : 'badge-soft-secondary'"
              x-text="twoFaEnabled ? 'Enabled' : 'Disabled'" x-show="!twoFaLoading"></span>
      </div>
      <div class="card-body">

        <!-- Loading state -->
        <div class="text-center py-3" x-show="twoFaLoading" x-cloak>
          <div class="spinner-border spinner-border-sm text-primary"></div>
        </div>

        <div x-show="!twoFaLoading" x-cloak>

          <!-- Status messages -->
          <div class="alert alert-success align-items-center gap-2 py-2"
               x-show="twoFaMsg" x-cloak x-transition style="display:none"
               :style="twoFaMsg ? 'display:flex' : 'display:none'"
               x-text="twoFaMsg"></div>
          <div class="alert alert-danger align-items-center gap-2 py-2"
               x-show="twoFaError" x-cloak x-transition style="display:none"
               :style="twoFaError ? 'display:flex' : 'display:none'"
               x-text="twoFaError"></div>

          <!-- ── Idle: 2FA not enabled ──────────────────────────────── -->
          <div x-show="!twoFaEnabled && setupStep === 'idle'">
            <p class="text-muted mb-3">
              Add an extra layer of security to your account. When enabled you'll need to enter
              a code from your authenticator app each time you sign in.
            </p>
            <div class="alert alert-light border mb-3">
              <h6 class="fw-semibold mb-2"><i class="bi bi-info-circle me-1"></i> How it works</h6>
              <ol class="mb-0 ps-3 text-sm text-muted">
                <li class="mb-1">Click <strong>"Enable Two-Factor Authentication"</strong> below.</li>
                <li class="mb-1">A QR code will appear. Scan it with an authenticator app such as
                  <strong>Google Authenticator</strong>, <strong>Authy</strong>, or <strong>1Password</strong>.</li>
                <li class="mb-1">Enter the 6-digit code shown in your authenticator app to confirm.</li>
                <li>From now on, you'll enter a new code from the app whenever you log in.</li>
              </ol>
            </div>
            <button class="btn btn-primary" @click="start2faSetup()" :disabled="twoFaSetupLoading">
              <span x-show="twoFaSetupLoading" x-cloak class="spinner-border spinner-border-sm me-1"></span>
              <i class="bi bi-shield-plus me-1" x-show="!twoFaSetupLoading"></i>
              <span x-text="twoFaSetupLoading ? 'Setting up…' : 'Enable Two-Factor Authentication'"></span>
            </button>
          </div>

          <!-- ── Step 1: QR code + verification ─────────────────────── -->
          <div x-show="setupStep === 'qr'">
            <div class="row g-4">
              <div class="col-md-6">
                <h6 class="fw-semibold mb-2">1. Scan QR Code</h6>
                <p class="text-muted text-sm mb-3">
                  Open your authenticator app and scan this QR code.
                </p>
                <div class="border rounded p-3 bg-white text-center mb-3" style="max-width:220px;">
                  <img :src="'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(twoFaQrUri)"
                       alt="2FA QR Code" class="img-fluid" width="200" height="200">
                </div>
                <details class="mb-3">
                  <summary class="text-sm text-muted fw-semibold" style="cursor:pointer">Can't scan? Enter code manually</summary>
                  <div class="mt-2">
                    <code class="d-block p-2 bg-light rounded text-break text-sm" x-text="twoFaSecret" style="letter-spacing:.15em;"></code>
                  </div>
                </details>
              </div>
              <div class="col-md-6">
                <h6 class="fw-semibold mb-2">2. Enter Verification Code</h6>
                <p class="text-muted text-sm mb-3">
                  Enter the 6-digit code shown in your authenticator app to confirm setup.
                </p>
                <div class="mb-3">
                  <input class="form-control form-control-lg text-center fw-bold"
                         type="text" inputmode="numeric" maxlength="6"
                         x-model="twoFaCode" placeholder="000000"
                         style="letter-spacing:.5em;font-size:1.3rem;max-width:220px;"
                         @keyup.enter="confirm2fa()">
                </div>
                <div class="d-flex gap-2">
                  <button class="btn btn-primary" @click="confirm2fa()" :disabled="twoFaCode.length < 6 || twoFaConfirmLoading">
                    <span x-show="twoFaConfirmLoading" x-cloak class="spinner-border spinner-border-sm me-1"></span>
                    <i class="bi bi-check-lg me-1" x-show="!twoFaConfirmLoading"></i>
                    <span x-text="twoFaConfirmLoading ? 'Verifying…' : 'Verify & Enable'"></span>
                  </button>
                  <button class="btn btn-light" @click="cancel2faSetup()" :disabled="twoFaConfirmLoading">Cancel</button>
                </div>
              </div>
            </div>
          </div>

          <!-- ── 2FA enabled: show disable button ───────────────────── -->
          <div x-show="twoFaEnabled && setupStep !== 'qr'">
            <div class="d-flex align-items-start gap-3">
              <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center"
                   style="width:48px;height:48px;min-width:48px;">
                <i class="bi bi-shield-check fs-4 text-success"></i>
              </div>
              <div>
                <p class="mb-1 fw-semibold">Two-factor authentication is active</p>
                <p class="text-muted text-sm mb-3">
                  Your account is protected with a TOTP authenticator app. You'll be asked for a verification code each time you sign in.
                </p>
                <button class="btn btn-outline-danger btn-sm" @click="disable2fa()" :disabled="twoFaDisableLoading">
                  <span x-show="twoFaDisableLoading" x-cloak class="spinner-border spinner-border-sm me-1"></span>
                  <i class="bi bi-shield-x me-1" x-show="!twoFaDisableLoading"></i>
                  <span x-text="twoFaDisableLoading ? 'Disabling…' : 'Disable 2FA'"></span>
                </button>
              </div>
            </div>
          </div>

        </div><!-- /!twoFaLoading -->
      </div>
    </div>

  </div><!-- /x-show -->
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
