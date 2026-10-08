<?php
/**
 * Shared site footer (copied from app/index.php) with the About / Privacy /
 * Terms modal and its script. Set $footerAssetBase to the relative path of
 * the assets/ folder from the including page before including this file.
 */
$footerAssetBase = $footerAssetBase ?? '../assets/';
?>
    <footer class="site-footer" id="footer">
        <div class="footer-content">
            <div class="footer-card footer-card-brand">
                <div class="footer-logo">
                    <span class="footer-logo-badge"><img src="<?= $footerAssetBase ?>img/osa logo.jpg" alt="OSA Logo"></span>
                    <h3>NAAP Student Hub</h3>
                </div>
                <p>Centralized hub for student organizations. Connect with program-based communities, discover upcoming events, and participate in campus life.</p>
                <div class="footer-badge-item">
                    <ion-icon name="shield-checkmark"></ion-icon>
                    <span>Office of Student Affairs Official Portal</span>
                </div>
            </div>

            <div class="footer-card footer-card-links">
                <h3>About &amp; Policies</h3>
                <ul class="footer-nav-list">
                    <li><button type="button" class="footer-nav-btn" onclick="openLegalModal('about')"><ion-icon name="information-circle-outline"></ion-icon> About Us</button></li>
                    <li><button type="button" class="footer-nav-btn" onclick="openLegalModal('privacy')"><ion-icon name="shield-checkmark-outline"></ion-icon> Privacy Policy</button></li>
                    <li><button type="button" class="footer-nav-btn" onclick="openLegalModal('terms')"><ion-icon name="document-text-outline"></ion-icon> Terms of Service</button></li>
                </ul>
            </div>

            <div class="footer-card footer-card-contact">
                <h3>Contact OSA Office</h3>
                <ul>
                    <li><ion-icon name="location-outline"></ion-icon><span>Ground Floor, Building A, Piccio Garden, Villamor, Pasay City, Philippines, 1309</span></li>
                    <li><ion-icon name="mail-outline"></ion-icon><span>naaporganization@gmail.com</span></li>
                    <li><ion-icon name="call-outline"></ion-icon><span>0962 342 7991</span></li>
                    <li><ion-icon name="logo-facebook"></ion-icon><a href="https://www.facebook.com/naaposavillamorcampus" target="_blank" rel="noopener noreferrer">/naaposavillamorcampus</a></li>
                </ul>
            </div>

            <div class="footer-card footer-card-social">
                <h3>Follow Us</h3>
                <div class="social-links">
                    <a href="https://www.facebook.com/naaposavillamorcampus" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><ion-icon name="logo-facebook"></ion-icon></a>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="footer-bottom-inner">
                <p class="footer-copyright">&copy; <?= date('Y') ?> NAAP Student Organization Portal &bull; Office of Student Affairs. All rights reserved.</p>
                <div class="footer-bottom-links">
                    <button type="button" class="footer-bottom-btn" onclick="openLegalModal('about')">About Us</button>
                    <span class="footer-bottom-dot">&bull;</span>
                    <button type="button" class="footer-bottom-btn" onclick="openLegalModal('privacy')">Privacy Policy</button>
                    <span class="footer-bottom-dot">&bull;</span>
                    <button type="button" class="footer-bottom-btn" onclick="openLegalModal('terms')">Terms of Service</button>
                </div>
            </div>
        </div>
    </footer>

    <!-- ═══ Privacy Policy, Terms of Service & About Us Modal ═════════ -->
    <div id="legalInfoModal" class="legal-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="legalModalTitle" onclick="if(event.target===this)closeLegalModal()">
      <div class="legal-modal-box">
        <div class="legal-modal-header">
          <div class="legal-modal-top">
            <div class="legal-modal-brand">
              <div class="legal-modal-brand-icon">
                <ion-icon id="legalModalIcon" name="information-circle-outline"></ion-icon>
              </div>
              <div class="legal-modal-title-wrap">
                <h3 id="legalModalTitle" class="legal-modal-title">About NAAP Student Hub</h3>
                <p class="legal-modal-subtitle">Office of Student Affairs &bull; Villamor Campus, Pasay City</p>
              </div>
            </div>
            <button type="button" onclick="closeLegalModal()" class="legal-modal-close-btn" aria-label="Close modal">&times;</button>
          </div>
          <div class="legal-modal-tabs" role="tablist">
            <button type="button" class="legal-tab-btn active" data-tab="about" onclick="switchLegalTab('about')" role="tab" aria-selected="true">
              <ion-icon name="information-circle-outline"></ion-icon>
              <span>About Us</span>
            </button>
            <button type="button" class="legal-tab-btn" data-tab="privacy" onclick="switchLegalTab('privacy')" role="tab" aria-selected="false">
              <ion-icon name="shield-checkmark-outline"></ion-icon>
              <span>Privacy Policy</span>
            </button>
            <button type="button" class="legal-tab-btn" data-tab="terms" onclick="switchLegalTab('terms')" role="tab" aria-selected="false">
              <ion-icon name="document-text-outline"></ion-icon>
              <span>Terms of Service</span>
            </button>
          </div>
        </div>

        <div class="legal-modal-body">
          <!-- ── Tab: About Us ── -->
          <div id="pane-about" class="legal-tab-pane active">
            <div class="legal-banner-card">
              <span class="legal-banner-badge"><ion-icon name="airplane"></ion-icon> Official Student Hub</span>
              <h4 class="legal-banner-title">Empowering the NAAP Aviation Community</h4>
              <p class="legal-banner-text">The NAAP Student Organization Portal is the centralized digital ecosystem designed and administered by the Office of Student Affairs (OSA) to foster collegiate leadership, student welfare, and extracurricular excellence.</p>
            </div>

            <div class="legal-section-block">
              <h4 class="legal-block-title"><ion-icon name="flag-outline"></ion-icon> Our Mission &amp; Purpose</h4>
              <p>Our mission is to bridge academic training with collaborative student engagement. We provide every student at NAAP with seamless access to accredited academic and co-curricular organizations, events, workshops, and recognition records.</p>
              <div class="legal-cards-grid">
                <div class="legal-card-mini">
                  <ion-icon name="school-outline" class="legal-card-mini-icon"></ion-icon>
                  <h4>Academic Growth</h4>
                  <p>Seminars, technical aviation clinics, research symposiums, and tutorials.</p>
                </div>
                <div class="legal-card-mini">
                  <ion-icon name="people-circle-outline" class="legal-card-mini-icon"></ion-icon>
                  <h4>Leadership &amp; Teamwork</h4>
                  <p>Student governance, mentorship, event organizing, and community outreach.</p>
                </div>
                <div class="legal-card-mini">
                  <ion-icon name="ribbon-outline" class="legal-card-mini-icon"></ion-icon>
                  <h4>Accredited Recognition</h4>
                  <p>Verified certificates of participation and authenticated membership records.</p>
                </div>
              </div>
            </div>

            <div class="legal-section-block">
              <h4 class="legal-block-title"><ion-icon name="grid-outline"></ion-icon> Recognized Program Organizations</h4>
              <p>The platform hosts officially chartered organizations across aviation disciplines:</p>
              <div class="legal-orgs-table">
                <div class="legal-org-badge"><span class="code">AISERS</span><span>Aviation Institute Students' Educational Research Society</span></div>
                <div class="legal-org-badge"><span class="code">AMTSO</span><span>Aircraft Maintenance Technology Student Organization</span></div>
                <div class="legal-org-badge"><span class="code">AEROATSO</span><span>Aerospace &amp; Air Traffic Service Officers</span></div>
                <div class="legal-org-badge"><span class="code">AETSO</span><span>Aviation Electronics Technology Student Organization</span></div>
                <div class="legal-org-badge"><span class="code">ELITECH</span><span>Electronics &amp; IT Community Hub</span></div>
                <div class="legal-org-badge"><span class="code">ILAS</span><span>International Language &amp; Arts Society</span></div>
              </div>
            </div>

            <div class="legal-section-block">
              <h4 class="legal-block-title"><ion-icon name="business-outline"></ion-icon> Office of Student Affairs (OSA)</h4>
              <p>The Office of Student Affairs oversees the governance, operations, and sanctioning of all student organizations and campus-wide extra-curricular engagements.</p>
              <div class="legal-callout-info">
                <strong>Visit Us:</strong> Ground Floor, Building A, Piccio Garden, Villamor, Pasay City, Philippines 1309<br>
                <strong>Inquiries &amp; Support:</strong> naaporganization@gmail.com &bull; 0962 342 7991<br>
                <strong>Office Hours:</strong> Monday &ndash; Friday, 8:00 AM &ndash; 5:00 PM
              </div>
            </div>
          </div>

          <!-- ── Tab: Privacy Policy ── -->
          <div id="pane-privacy" class="legal-tab-pane">
            <div class="legal-banner-card">
              <span class="legal-banner-badge"><ion-icon name="shield-checkmark"></ion-icon> RA 10173 Compliance</span>
              <h4 class="legal-banner-title">Data Privacy Agreement &amp; Policy</h4>
              <p class="legal-banner-text">In strict compliance with Republic Act No. 10173 (Data Privacy Act of 2012 of the Philippines), this policy explains how your personal data is collected, processed, and safeguarded when using the NAAP Student Organization Portal.</p>
            </div>

            <div class="legal-section-block">
              <h4 class="legal-block-title"><ion-icon name="finger-print-outline"></ion-icon> 1. Personal Information We Collect</h4>
              <p>To deliver portal services and authenticate student activity, we collect:</p>
              <ul class="legal-item-list">
                <li><strong>Identity &amp; Academic Data:</strong> Full name, Student ID number, institutional email address, program/course, year level, section, and contact number.</li>
                <li><strong>Event &amp; Membership Records:</strong> Organization affiliations, pre-registration entries, event check-in records, attendance timestamps, and evaluation feedback.</li>
                <li><strong>Biometric Verification Data:</strong> Optional facial recognition templates and uploaded profile photos utilized exclusively for identity verification and fast-track attendance during authorized campus events.</li>
              </ul>
            </div>

            <div class="legal-section-block">
              <h4 class="legal-block-title"><ion-icon name="checkbox-outline"></ion-icon> 2. Purpose of Data Processing</h4>
              <p>All data collected is utilized solely for academic and institutional functions:</p>
              <ul class="legal-item-list">
                <li>Verifying active student enrollment and membership eligibility.</li>
                <li>Managing event capacities, attendance logging, and preventing proxy attendance.</li>
                <li>Generating authenticated digital certificates of participation and completion.</li>
                <li>Providing analytics to the Office of Student Affairs to enhance campus programs and student services.</li>
              </ul>
            </div>

            <div class="legal-section-block">
              <h4 class="legal-block-title"><ion-icon name="lock-closed-outline"></ion-icon> 3. Security &amp; Confidentiality</h4>
              <p>We enforce strict organizational, physical, and technical safeguards:</p>
              <ul class="legal-item-list">
                <li>Access is restricted strictly to authorized OSA personnel, system administrators, and designated organization advisers with appropriate role permissions.</li>
                <li>Passwords are hashed using modern cryptographic algorithms (`bcrypt`), and data in transit is protected via TLS encryption.</li>
                <li><strong>Zero Commercial Sharing:</strong> Personal data is never sold, leased, or distributed to third-party commercial entities or advertisers.</li>
              </ul>
            </div>

            <div class="legal-section-block">
              <h4 class="legal-block-title"><ion-icon name="person-circle-outline"></ion-icon> 4. Your Rights as a Data Subject</h4>
              <p>Under RA 10173, students possess the right to be informed, right to access their personal records, right to rectify inaccuracies, and the right to file grievances regarding improper data handling. For inquiries, contact the OSA Data Protection Officer at <a href="mailto:naaporganization@gmail.com" style="color:#0284c7;text-decoration:none;font-weight:600;">naaporganization@gmail.com</a>.</p>
            </div>
          </div>

          <!-- ── Tab: Terms of Service ── -->
          <div id="pane-terms" class="legal-tab-pane">
            <div class="legal-banner-card">
              <span class="legal-banner-badge"><ion-icon name="document-text"></ion-icon> Rules of Governance</span>
              <h4 class="legal-banner-title">Terms of Service &amp; Code of Conduct</h4>
              <p class="legal-banner-text">Welcome to the NAAP Student Organization Portal. By accessing, browsing, or registering an account, you agree to comply with these terms, the Student Handbook, and campus regulations.</p>
            </div>

            <div class="legal-section-block">
              <h4 class="legal-block-title"><ion-icon name="shield-outline"></ion-icon> 1. Acceptance &amp; Account Responsibility</h4>
              <p>Every student and organization officer is responsible for:</p>
              <ul class="legal-item-list">
                <li>Providing truthful, accurate, and current student identification details upon registration.</li>
                <li>Maintaining the secrecy of login credentials and passwords. Sharing student accounts is strictly prohibited.</li>
                <li>Promptly notifying OSA or administrators if any unauthorized access or security compromise is detected.</li>
              </ul>
            </div>

            <div class="legal-section-block">
              <h4 class="legal-block-title"><ion-icon name="calendar-outline"></ion-icon> 2. Event Pre-Registration &amp; Attendance</h4>
              <p>To ensure fair access to limited seating and workshop slots:</p>
              <ul class="legal-item-list">
                <li>Pre-registration reserves your event slot. If unable to attend, cancellation should be made at least 24 hours in advance to release slots to peers.</li>
                <li>Event badges, registration QR codes, and facial check-ins are non-transferable. Attempting proxy check-ins or manipulating attendance records is a disciplinary offense subject to Student Affairs sanction.</li>
              </ul>
            </div>

            <div class="legal-section-block">
              <h4 class="legal-block-title"><ion-icon name="megaphone-outline"></ion-icon> 3. Organization Conduct &amp; Publishing</h4>
              <p>Recognized student organizations and their officers must:</p>
              <ul class="legal-item-list">
                <li>Ensure all published announcements, event posters, and documentation conform to the OSA Guidelines and collegiate standards.</li>
                <li>Avoid publishing deceptive, defamatory, discriminatory, or unauthorized promotional content.</li>
                <li>Respect member privacy and use member rosters strictly for official club affairs.</li>
              </ul>
            </div>

            <div class="legal-section-block">
              <h4 class="legal-block-title"><ion-icon name="ban-outline"></ion-icon> 4. Prohibited Activities</h4>
              <p>Users shall not:</p>
              <ul class="legal-item-list">
                <li>Attempt to gain unauthorized access to administrative or organizational dashboards.</li>
                <li>Interfere with system integrity, reverse-engineer endpoints, or submit malicious scripts.</li>
                <li>Falsify documents, certificate proofs, or organizational leadership titles.</li>
              </ul>
            </div>

            <div class="legal-section-block">
              <h4 class="legal-block-title"><ion-icon name="alert-circle-outline"></ion-icon> 5. Suspension &amp; Modifications</h4>
              <p>The Office of Student Affairs reserves the right to suspend or revoke portal privileges for users who violate institutional regulations. These terms may be updated periodically; continued usage constitutes acceptance of current guidelines.</p>
            </div>
          </div>
        </div>

        <div class="legal-modal-footer">
          <div class="legal-modal-footer-info">
            <ion-icon name="shield-checkmark"></ion-icon>
            <span>Official Portal Document &bull; Active Academic Year <?= date('Y') ?></span>
          </div>
          <button type="button" class="legal-modal-close-action" onclick="closeLegalModal()">Close</button>
        </div>
      </div>
    </div>

    <script>
    function openLegalModal(tab) {
        const modal = document.getElementById('legalInfoModal');
        if (!modal) return;
        switchLegalTab(tab || 'about');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeLegalModal() {
        const modal = document.getElementById('legalInfoModal');
        if (!modal) return;
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    function switchLegalTab(tabName) {
        const validTabs = ['about', 'privacy', 'terms'];
        if (!validTabs.includes(tabName)) tabName = 'about';

        // Update tab buttons
        document.querySelectorAll('.legal-tab-btn').forEach(function(btn) {
            const isMatch = btn.dataset.tab === tabName;
            btn.classList.toggle('active', isMatch);
            btn.setAttribute('aria-selected', isMatch ? 'true' : 'false');
        });

        // Update tab panes
        document.querySelectorAll('.legal-tab-pane').forEach(function(pane) {
            pane.classList.toggle('active', pane.id === 'pane-' + tabName);
        });

        // Update header title and icon
        const headerTitle = document.getElementById('legalModalTitle');
        const headerIcon  = document.getElementById('legalModalIcon');
        if (headerTitle && headerIcon) {
            if (tabName === 'privacy') {
                headerTitle.textContent = 'Data Privacy Policy';
                headerIcon.setAttribute('name', 'shield-checkmark-outline');
            } else if (tabName === 'terms') {
                headerTitle.textContent = 'Terms of Service';
                headerIcon.setAttribute('name', 'document-text-outline');
            } else {
                headerTitle.textContent = 'About NAAP Student Hub';
                headerIcon.setAttribute('name', 'information-circle-outline');
            }
        }
    }

    window.openLegalModal   = openLegalModal;
    window.closeLegalModal  = closeLegalModal;
    window.switchLegalTab   = switchLegalTab;
    window.openAboutModal   = function() { openLegalModal('about'); };
    window.openPrivacyModal = function() { openLegalModal('privacy'); };
    window.openTermsModal   = function() { openLegalModal('terms'); };

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeLegalModal();
        }
    });
    </script>
