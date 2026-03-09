<?php
$pageTitle  = 'System Settings';
$activePage = 'admin-settings';
ob_start();
?>
<div x-data="adminSettingsPage">

  <!-- Page Header -->
  <div class="page-header">
    <div>
      <h1 class="page-title">System Settings</h1>
      <p class="page-breadcrumb">Administration / Settings</p>
    </div>
  </div>

  <!-- Loading spinner -->
  <div class="text-center py-5" x-show="loading" x-cloak>
    <div class="spinner-border text-primary"></div>
  </div>

  <div x-show="!loading" x-cloak>

    <!-- Status messages -->
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

    <div class="row g-4">

      <!-- Platform Settings -->
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header">
            <h6 class="card-title mb-0"><i class="bi bi-globe2 me-2"></i>Platform</h6>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label" for="appName">Application Name</label>
              <input class="form-control" id="appName" type="text"
                     x-model="form.app_name" placeholder="WorkEddy">
            </div>
            <div class="mb-3">
              <label class="form-label" for="supportEmail">Support Email</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input class="form-control" id="supportEmail" type="email"
                       x-model="form.support_email" placeholder="support@workeddy.com">
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Registration & Security -->
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header">
            <h6 class="card-title mb-0"><i class="bi bi-shield-lock me-2"></i>Registration &amp; Security</h6>
          </div>
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
              <div>
                <p class="fw-semibold mb-1">Allow New Registrations</p>
                <p class="text-muted text-sm mb-0">When disabled, new organizations cannot sign up.</p>
              </div>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="allowReg"
                       x-model="form.allow_registrations" style="width:3em;height:1.5em;">
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Payment Gateway -->
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <h6 class="card-title mb-0"><i class="bi bi-credit-card me-2"></i>Payment Gateway Integration</h6>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label" for="payGateway">Gateway Provider</label>
                <select class="form-select" id="payGateway" x-model="form.payment_gateway">
                  <option value="">None (disabled)</option>
                  <option value="paystack">Paystack</option>
                  <option value="squad">Squad</option>
                  <option value="stripe">Stripe</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="payPubKey">Public Key</label>
                <input class="form-control" id="payPubKey" type="text"
                       x-model="form.payment_public_key" placeholder="pk_live_…">
              </div>
              <div class="col-md-4">
                <label class="form-label" for="paySecretKey">Secret Key</label>
                <div class="input-group">
                  <input class="form-control" id="paySecretKey"
                         :type="showSecret ? 'text' : 'password'"
                         x-model="form.payment_secret_key" placeholder="sk_live_…">
                  <button class="btn btn-outline-secondary" type="button"
                          @click="showSecret = !showSecret">
                    <i class="bi" :class="showSecret ? 'bi-eye-slash' : 'bi-eye'"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /row -->

    <!-- Save button -->
    <div class="d-flex gap-2 mt-4">
      <button class="btn btn-primary" @click="saveSettings()" :disabled="saving">
        <span x-show="saving" x-cloak class="spinner-border spinner-border-sm me-1"></span>
        <span x-text="saving ? 'Saving…' : 'Save System Settings'"></span>
      </button>
      <button class="btn btn-light" @click="resetForm()">Discard Changes</button>
    </div>
    </div>

    <!-- Broadcast Notification -->
    <div class="card mt-4" x-data="{
      nTitle: '', nMessage: '', nTarget: 'all', nLink: '',
      sending: false, sendSuccess: '', sendError: '',
      async sendNotification() {
        this.sendSuccess = ''; this.sendError = '';
        if (!this.nTitle.trim()) { this.sendError = 'Title is required.'; return; }
        this.sending = true;
        try {
          await api('/notifications/send', {
            method: 'POST',
            body: JSON.stringify({ title: this.nTitle, message: this.nMessage, target: this.nTarget, link: this.nLink })
          });
          this.sendSuccess = 'Notification broadcast sent!';
          this.nTitle = ''; this.nMessage = ''; this.nTarget = 'all'; this.nLink = '';
        } catch(e) { this.sendError = e.message; }
        finally { this.sending = false; }
      }
    }">
      <div class="card-header">
        <h6 class="card-title mb-0"><i class="bi bi-megaphone me-2"></i>Broadcast Notification</h6>
      </div>
      <div class="card-body">

        <div class="alert alert-success align-items-center gap-2 py-2"
             x-show="sendSuccess" x-cloak x-transition style="display:none"
             :style="sendSuccess ? 'display:flex' : 'display:none'">
          <i class="bi bi-check-circle-fill"></i>
          <span x-text="sendSuccess"></span>
        </div>
        <div class="alert alert-danger align-items-center gap-2 py-2"
             x-show="sendError" x-cloak x-transition style="display:none"
             :style="sendError ? 'display:flex' : 'display:none'"
             x-text="sendError"></div>

        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label" for="bTitle">Title <span class="text-danger">*</span></label>
            <input class="form-control" id="bTitle" type="text"
                   x-model="nTitle" placeholder="e.g. System maintenance window">
          </div>
          <div class="col-md-4">
            <label class="form-label" for="bTarget">Audience</label>
            <select class="form-select" id="bTarget" x-model="nTarget">
              <option value="all">All Users (org-wide)</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label" for="bMessage">Message <span class="text-muted">(optional)</span></label>
            <textarea class="form-control" id="bMessage" rows="3"
                      x-model="nMessage" placeholder="Provide more details…"></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="bLink">Link <span class="text-muted">(optional)</span></label>
            <input class="form-control" id="bLink" type="text"
                   x-model="nLink" placeholder="/admin/dashboard or https://…">
          </div>
        </div>

        <div class="d-flex gap-2 mt-3">
          <button class="btn btn-primary" @click="sendNotification()" :disabled="sending">
            <span x-show="sending" x-cloak class="spinner-border spinner-border-sm me-1"></span>
            <i class="bi bi-send me-1" x-show="!sending"></i>
            <span x-text="sending ? 'Sending…' : 'Broadcast Notification'"></span>
          </button>
        </div>
      </div>
    </div>

  </div><!-- /!loading -->
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
