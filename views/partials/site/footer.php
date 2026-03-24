  <footer class="bg-dark text-white py-5">
    <div class="container py-4">
      <div class="row g-4">
        <div class="col-lg-4">
          <h4 class="fw-bold mb-3"><img src="/assets/img/workeddy.png" alt="WorkEddy logo" class="auth-brand-logo" /> </h4>
          <p class="text-white-50 pe-lg-5">Automating ergonomic risk assessments to support MSD prevention, improve assessment consistency, and document prevention actions.</p>
        </div>
        <div class="col-6 col-lg-2 offset-lg-2">
          <h6 class="fw-bold text-uppercase mb-3">Product</h6>
          <ul class="list-unstyled text-white-50">
            <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Features</a></li>
            <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Pricing</a></li>
            <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Enterprise</a></li>
          </ul>
        </div>
        <div class="col-6 col-lg-2">
          <h6 class="fw-bold text-uppercase mb-3">Company</h6>
          <ul class="list-unstyled text-white-50">
            <li class="mb-2"><a href="/about-us" class="text-white-50 text-decoration-none">About Us</a></li>
            <li class="mb-2"><a href="/founder-story" class="text-white-50 text-decoration-none">From the Founder</a></li>
            <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Careers</a></li>
            <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Contact</a></li>
          </ul>
        </div>
        <div class="col-6 col-lg-2">
          <h6 class="fw-bold text-uppercase mb-3">Legal</h6>
          <ul class="list-unstyled text-white-50">
            <li class="mb-2"><a href="/privacy-policy" class="text-white-50 text-decoration-none">Privacy Policy</a></li>
            <li class="mb-2"><a href="/terms-of-service" class="text-white-50 text-decoration-none">Terms of Service</a></li>
          </ul>
        </div>
      </div>
      <div class="border-top border-secondary mt-5 pt-4 text-center text-white-50 small">
        &copy; <?= date('Y') ?> WorkEddy, Inc. All rights reserved.
      </div>
    </div>
  </footer>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function changeIndustry(industry, element) {
      document.querySelectorAll('.industry-tab').forEach(t => t.classList.remove('active'));
      element.classList.add('active');
      
      const title = document.getElementById('mockup-task-title');
      const action = document.getElementById('mockup-action-title');
      const insight = document.getElementById('mockup-insight');
      const score = document.getElementById('mockup-score');
      const actionDesc = document.getElementById('mockup-action-desc');
      
      if(industry === 'Warehouse') {
        title.innerText = 'Recent Analysis: Warehouse Lifting';
        action.innerText = 'Reduce trunk flexion by raising pallet height';
        insight.innerText = 'Trunk flexion > 60° detected.';
        score.innerText = '7.8';
        actionDesc.innerHTML = '<i class="bi bi-arrow-down"></i> -3.2 Score Impact';
      } else if(industry === 'Logistics') {
        title.innerText = 'Recent Analysis: Package Loading';
        action.innerText = 'Implement slide board for vehicle loading';
        insight.innerText = 'Extended reach > 45cm detected.';
        score.innerText = '6.5';
        actionDesc.innerHTML = '<i class="bi bi-arrow-down"></i> -2.4 Score Impact';
      } else if(industry === 'Manufacturing') {
        title.innerText = 'Recent Analysis: Assembly Line';
        action.innerText = 'Adjust workstation height to elbow level';
        insight.innerText = 'Prolonged shoulder abduction detected.';
        score.innerText = '8.1';
        actionDesc.innerHTML = '<i class="bi bi-arrow-down"></i> -4.0 Score Impact';
      } else if(industry === 'Healthcare') {
        title.innerText = 'Recent Analysis: Patient Transfer';
        action.innerText = 'Utilize mechanical lift for transfers';
        insight.innerText = 'High lumbar shear forces detected.';
        score.innerText = '9.2';
        actionDesc.innerHTML = '<i class="bi bi-arrow-down"></i> -5.1 Score Impact';
      } else if(industry === 'Construction') {
        title.innerText = 'Recent Analysis: Rebar Tying';
        action.innerText = 'Switch to rebar tying tool to reduce deep squat';
        insight.innerText = 'Sustained deep knee flexion detected.';
        score.innerText = '8.4';
        actionDesc.innerHTML = '<i class="bi bi-arrow-down"></i> -3.8 Score Impact';
      }
      
      // Reset toggle
      document.getElementById('beforeAfterToggle').checked = false;
      document.getElementById('mockup-score').nextElementSibling.className = 'badge bg-danger fs-6 align-middle ms-1 rounded-pill fw-bold';
      document.getElementById('mockup-score').nextElementSibling.innerText = 'High Risk';
      document.querySelector('.pose-overlay-svg').style.filter = 'none';
    }

    document.getElementById('beforeAfterToggle').addEventListener('change', function(e) {
        const isChecked = e.target.checked;
        const score = document.getElementById('mockup-score');
        const badge = score.nextElementSibling;
        const insight = document.getElementById('mockup-insight');
        const poseOverlay = document.querySelector('.pose-overlay-svg');
        
        if (isChecked) {
            let currentScore = parseFloat(score.innerText);
            score.innerText = (currentScore - 3.2).toFixed(1);
            badge.className = 'badge bg-success fs-6 align-middle ms-1 rounded-pill fw-bold';
            badge.innerText = 'Low Risk';
            insight.innerText = 'Task redesigned: Posture improved significantly.';
            poseOverlay.style.filter = 'hue-rotate(180deg)';
        } else {
            // Re-trigger the active industry to restore default text
            const activeTab = document.querySelector('.industry-tab.active');
            changeIndustry(activeTab.innerText, activeTab);
        }
    });

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
