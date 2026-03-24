<?php
// views/partials/site/v2_footer.php
?>
  <!-- Footer -->
  <footer class="bg-black text-white py-5 mt-5 border-top border-secondary border-opacity-25">
    <div class="container py-4">
      <div class="row g-4">
        <div class="col-lg-4">
          <h4 class="fw-bold mb-3 d-flex align-items-center gap-2">
            <i class="bi bi-cpu text-cyan"></i> WorkEddy
          </h4>
          <p class="text-white-50 pe-lg-5">Automating ergonomic risk assessments to support MSD prevention, improve assessment consistency, and document prevention actions.</p>
        </div>
        <div class="col-6 col-lg-2 offset-lg-2">
          <h6 class="fw-bold text-uppercase mb-3 font-mono" style="font-size:0.8rem;">Product</h6>
          <ul class="list-unstyled text-white-50">
            <li class="mb-2"><a href="/index2#features" class="text-white-50 text-decoration-none hover-text-white">Features</a></li>
            <li class="mb-2"><a href="/index2#pricing" class="text-white-50 text-decoration-none hover-text-white">Pricing</a></li>
            <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none hover-text-white">Enterprise</a></li>
          </ul>
        </div>
        <div class="col-6 col-lg-2">
          <h6 class="fw-bold text-uppercase mb-3 font-mono" style="font-size:0.8rem;">Company</h6>
          <ul class="list-unstyled text-white-50">
            <li class="mb-2"><a href="/about-us2" class="text-white-50 text-decoration-none hover-text-white">About Us</a></li>
            <li class="mb-2"><a href="/founder-story2" class="text-white-50 text-decoration-none hover-text-white">From the Founder</a></li>
            <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none hover-text-white">Careers</a></li>
            <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none hover-text-white">Contact</a></li>
          </ul>
        </div>
        <div class="col-6 col-lg-2">
          <h6 class="fw-bold text-uppercase mb-3 font-mono" style="font-size:0.8rem;">Legal</h6>
          <ul class="list-unstyled text-white-50">
            <li class="mb-2"><a href="/privacy-policy2" class="text-white-50 text-decoration-none hover-text-white">Privacy Policy</a></li>
            <li class="mb-2"><a href="/terms-of-service" class="text-white-50 text-decoration-none hover-text-white">Terms of Service</a></li>
          </ul>
        </div>
      </div>
      <div class="border-top border-secondary border-opacity-50 mt-5 pt-4 text-center text-white-50 small font-mono" style="font-size:0.75rem;">
        &copy; <?= date('Y') ?> WorkEddy, Inc. All rights reserved.
      </div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
          <script>
          document.getElementById('feedbackForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn     = document.getElementById('feedbackSubmitBtn');
            const txt     = document.getElementById('feedbackBtnText');
            const spin    = document.getElementById('feedbackBtnSpinner');
            const alert   = document.getElementById('feedbackAlert');

            btn.disabled = true;
            txt.classList.add('d-none');
            spin.classList.remove('d-none');
            alert.className = 'd-none mb-3';

            try {
              const res = await fetch('/api/v1/feedback', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                  name:    document.getElementById('feedbackName').value.trim()    || null,
                  email:   document.getElementById('feedbackEmail').value.trim()   || null,
                  type:    document.getElementById('feedbackType').value,
                  message: document.getElementById('feedbackMessage').value.trim(),
                }),
              });
              if (!res.ok) {
                const err = await res.json();
                throw new Error(err.error ?? 'Submission failed');
              }
              // Success
              alert.className = 'mb-3 alert alert-success py-2 small';
              alert.innerText = '✓ Thank you! Your feedback was submitted successfully.';
              document.getElementById('feedbackForm').reset();
              setTimeout(() => {
                const m = bootstrap.Modal.getInstance(document.getElementById('feedbackFormModal'));
                if (m) m.hide();
              }, 2000);
            } catch (err) {
              alert.className = 'mb-3 alert alert-danger py-2 small';
              alert.innerText = '✗ ' + (err.message ?? 'Something went wrong. Please try again.');
            } finally {
              btn.disabled = false;
              txt.classList.remove('d-none');
              spin.classList.add('d-none');
            }
          });
        </script>
</body>
</html>