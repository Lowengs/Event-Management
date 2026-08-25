<?php
session_start();

// Redirect logged-in users to their respective dashboards
if (!empty($_SESSION['student_id'])) {
    header('Location: profile-dashboard.php');
    exit;
} elseif (!empty($_SESSION['admin_id'])) {
    header('Location: ../admin/dashboard.php');
    exit;
} elseif (!empty($_SESSION['osa_id'])) {
    header('Location: ../osa/dashboard_final.php');
    exit;
} elseif (!empty($_SESSION['org_id'])) {
    header('Location: ../organization/dashboard_org.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration – Event Portal</title>
    <meta name="description" content="Register your student account with facial recognition for the campus event management system.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" href="../../assets/img/philsca.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/student/register.css?v=<?= time() ?>">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <!-- face-api.js – loaded early (not deferred) so it's ready when step 3 is reached -->
    <script src="../../assets/js/lib/face-api.min.js"></script>
</head>
<body>
    <!-- Back link -->
    <a href="login.php" class="back-link" id="backToLogin">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
        </svg>
        Back to Login
    </a>

    <!-- ═══ STEP INDICATOR ═══════════════════════════════════════════ -->
    <div class="step-wrapper" id="stepWrapper">
        <div class="steps-track">
            <div class="step-item active" id="sitem1">
                <div class="step-circle"><span>1</span></div>
                <div class="step-label">Basic Info</div>
            </div>
            <div class="step-line" id="sline1"></div>
            <div class="step-item" id="sitem2">
                <div class="step-circle"><span>2</span></div>
                <div class="step-label">Account</div>
            </div>
            <div class="step-line" id="sline2"></div>
            <div class="step-item" id="sitem3">
                <div class="step-circle"><span>3</span></div>
                <div class="step-label">Face ID</div>
            </div>
            <div class="step-line" id="sline3"></div>
            <div class="step-item" id="sitem4">
                <div class="step-circle"><span>4</span></div>
                <div class="step-label">Documents</div>
            </div>
        </div>
    </div>

    <!-- ═══ MAIN ═════════════════════════════════════════════════════ -->
    <main>
        <section class="reg-section">
            <div class="reg-card" id="regCard">

                <!-- ── PANEL 1: Basic Information ────────────────────── -->
                <div class="reg-panel active" id="panel1">
                    <div class="panel-header">
                        <h2>Basic Information</h2>
                        <p class="panel-sub">Provide the official details used by the registrar. These will be verified against your COR.</p>
                    </div>

                    <div class="form-grid">
                        <label class="input-group span-full">
                            <span>Student ID Number <em>*</em></span>
                            <input type="text" id="f_student_id" placeholder="e.g. 2024MN-001234" maxlength="30" autocomplete="off">
                            <span class="field-error" id="e_student_id"></span>
                        </label>

                        <label class="input-group">
                            <span>First Name <em>*</em></span>
                            <input type="text" id="f_first_name" placeholder="Maria" autocomplete="given-name">
                            <span class="field-error" id="e_first_name"></span>
                        </label>

                        <label class="input-group">
                            <span>Middle Name</span>
                            <input type="text" id="f_middle_name" placeholder="Santos (optional)" autocomplete="additional-name">
                        </label>

                        <label class="input-group">
                            <span>Last Name <em>*</em></span>
                            <input type="text" id="f_last_name" placeholder="Dela Cruz" autocomplete="family-name">
                            <span class="field-error" id="e_last_name"></span>
                        </label>

                        <label class="input-group">
                            <span>Email Address <em>*</em></span>
                            <input type="email" id="f_email" placeholder="you@school.edu" autocomplete="email">
                            <span class="field-error" id="e_email"></span>
                        </label>

                        <label class="input-group span-full">
                            <span>Home Address <em>*</em></span>
                            <input type="text" id="f_address" placeholder="e.g. 123 Rizal St., Barangay San Jose, City" autocomplete="street-address">
                            <span class="field-error" id="e_address"></span>
                        </label>

                        <label class="input-group">
                            <span>Course / Program <em>*</em></span>
                            <select id="f_course">
                                <option value="" disabled selected>Select Course / Program</option>
                                <option value="BSAIT">BSAIT</option>
                                <option value="BSAIS">BSAIS</option>
                                <option value="AAMT">AAMT</option>
                                <option value="AAET">AAET</option>
                                <option value="BSAMT">BSAMT</option>
                                <option value="BSAEE">BSAEE</option>
                                <option value="BSAT">BSAT</option>
                                <option value="BSAVTOUR">BSAVTOUR</option>
                                <option value="BSAVCOMM">BSAVCOMM</option>
                                <option value="BSAET">BSAET</option>
                                <option value="BSAVLOG">BSAVLOG</option>
                                <option value="BSAVSEC">BSAVSEC</option>
                                <option value="BSAVSSM">BSAVSSM</option>
                            </select>
                            <span class="field-error" id="e_course"></span>
                        </label>

                        <label class="input-group">
                            <span>Year Level <em>*</em></span>
                            <select id="f_year_level">
                                <option value="" disabled selected>Select Year Level</option>
                                <option value="1st Year">1st Year</option>
                                <option value="2nd Year">2nd Year</option>
                                <option value="3rd Year">3rd Year</option>
                                <option value="4th Year">4th Year</option>
                            </select>
                            <span class="field-error" id="e_year_level"></span>
                        </label>

                        <label class="input-group">
                            <span>Section (optional)</span>
                            <select id="f_section">
                                <option value="" disabled selected>Select Section</option>
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </label>
                    </div>

                    <button class="btn-primary" id="next1">Next: Account Setup →</button>
                </div>

                <!-- ── PANEL 2: Account Information ──────────────────── -->
                <div class="reg-panel" id="panel2">
                    <div class="panel-header">
                        <h2> Account Information</h2>
                        <p class="panel-sub">Choose the credentials you will use every time you sign in.</p>
                    </div>

                    <div class="form-grid">
                        <label class="input-group span-full">
                            <span>Username <em>*</em></span>
                            <input type="text" id="f_username" placeholder="e.g. mdelacruz2024" autocomplete="username">
                            <span class="field-error" id="e_username"></span>
                        </label>

                        <label class="input-group">
                            <span>Password <em>*</em></span>
                            <div class="pw-wrap">
                                <input type="password" id="f_password" placeholder="Create a strong password" autocomplete="new-password">
                                <button type="button" class="pw-toggle" data-target="f_password" aria-label="Toggle password visibility">
                                    <ion-icon name="eye-outline"></ion-icon>
                                </button>
                            </div>
                            <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                            <span class="strength-label" id="strengthLabel"></span>
                            <span class="field-error" id="e_password"></span>
                        </label>

                        <label class="input-group">
                            <span>Confirm Password <em>*</em></span>
                            <div class="pw-wrap">
                                <input type="password" id="f_confirm_password" placeholder="Re-type your password" autocomplete="new-password">
                                <button type="button" class="pw-toggle" data-target="f_confirm_password" aria-label="Toggle password visibility">
                                    <ion-icon name="eye-outline"></ion-icon>
                                </button>
                            </div>
                            <span class="field-error" id="e_confirm_password"></span>
                        </label>
                    </div>

                    <div class="btn-row">
                        <button class="btn-secondary" id="back2">← Back</button>
                        <button class="btn-primary" id="next2">Next: Face ID →</button>
                    </div>
                </div>

                <!-- ── PANEL 3: Face Registration ─────────────────────── -->
                <div class="reg-panel" id="panel3">
                    <div class="panel-header">
                        <h2>Face Registration</h2>
                        <p class="panel-sub">Your face will be used for quick identity verification at campus events. Please ensure good lighting, look straight at the camera, and remove glasses or hats if possible.</p>
                    </div>

                    <!-- Camera Area -->
                    <div class="face-zone" id="faceZone">
                        <div id="cameraArea">
                            <div class="cam-container" id="camContainer">
                                <!-- Placeholder shown before camera starts -->
                                <div class="cam-placeholder" id="camPlaceholder">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24"
                                         fill="none" stroke="currentColor" stroke-width="1.2"
                                         stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                        <circle cx="12" cy="13" r="4"/>
                                    </svg>
                                    <span>Camera preview will appear here</span>
                                </div>
                                <video id="faceVideo" autoplay muted playsinline></video>
                                <canvas id="faceCanvas"></canvas>
                            </div>

                            <!-- Status bar -->
                            <div class="face-status-bar">
                                <span id="faceStatusText"> Click <strong>Open Camera</strong> to begin face registration</span>
                            </div>

                            <!-- Camera buttons -->
                            <div class="face-actions" id="faceActions">
                                <button class="btn-primary" id="openCamBtn"> Open Camera</button>
                                <button class="btn-secondary" id="retakeBtn" style="display:none;"> Retake</button>
                            </div>
                        </div>

                        <!-- Captured preview (shown after capture) -->
                        <div id="capturePreview" style="display:none;">
                            <div class="captured-img-wrap">
                                <img id="capturedImg" src="" alt="Your captured face">
                                <div class="captured-badge"> Face Registered</div>
                            </div>
                            <p class="captured-desc">Face successfully registered. You can retake if the photo is unclear.</p>
                            <div class="face-actions">
                                <button class="btn-secondary" id="retakeBtn2"> Retake Photo</button>
                            </div>
                        </div>
                    </div>

                    <!-- Model loading indicator -->
                    <div class="model-loading" id="modelLoading" style="display:none;">
                        <div class="loading-spinner"></div>
                        <span>Loading face detection models… (requires internet)</span>
                    </div>

                    <div class="btn-row">
                        <button class="btn-secondary" id="back3">← Back</button>
                        <button class="btn-primary" id="next3" disabled>Next: Documents →</button>
                    </div>
                </div>

                <!-- ── PANEL 4: Documents & Consent ───────────────────── -->
                <div class="reg-panel" id="panel4">
                    <div class="panel-header">
                        <h2> Verification & Documents</h2>
                        <p class="panel-sub">Upload your documents so the OSA can verify your identity and enrollment.</p>
                    </div>

                    <div class="form-grid">
                        <label class="input-group">
                            <span>Profile Photo <em>*</em></span>
                            <div class="file-upload-zone" id="photoZone" role="button" tabindex="0" aria-label="Upload profile photo">
                                <input type="file" id="f_profile_photo" accept="image/*" hidden>
                                <div class="file-upload-inner" id="photoInner">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"
                                         fill="none" stroke="#38bdf8" stroke-width="1.5"
                                         stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                        <polyline points="17 8 12 3 7 8"/>
                                        <line x1="12" y1="3" x2="12" y2="15"/>
                                    </svg>
                                    <span>Click to upload profile photo</span>
                                    <span class="file-hint">JPG, PNG – max 5 MB</span>
                                </div>
                            </div>
                            <span class="field-error" id="e_profile_photo"></span>
                        </label>

                        <label class="input-group">
                            <span>Phone Number <em>*</em></span>
                            <input type="tel" id="f_phone" placeholder="+63 912 345 6789" autocomplete="tel">
                            <span class="field-error" id="e_phone"></span>
                        </label>

                        <label class="input-group span-full">
                            <span>Certificate of Registration (COR) <em>*</em></span>
                            <div class="file-upload-zone" id="corZone" role="button" tabindex="0" aria-label="Upload COR">
                                <input type="file" id="f_cor" accept=".pdf,application/pdf" hidden>
                                <div class="file-upload-inner" id="corInner">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"
                                         fill="none" stroke="#38bdf8" stroke-width="1.5"
                                         stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14 2 14 8 20 8"/>
                                        <line x1="16" y1="13" x2="8" y2="13"/>
                                        <line x1="16" y1="17" x2="8" y2="17"/>
                                    </svg>
                                    <span>Click to upload COR document</span>
                                    <span class="file-hint">PDF format only – max 10 MB</span>
                                </div>
                            </div>
                            <span class="field-error" id="e_cor"></span>
                        </label>
                    </div>

                    <div class="consent-box">
                        <label class="consent-lbl">
                            <input type="checkbox" id="f_consent">
                            <span>I agree to the <a href="javascript:void(0)" id="openPrivacyBtn" class="link-accent">Data Privacy Agreement</a> and consent to the collection and processing of my personal data, including facial recognition data, in accordance with RA No. 10173.</span>
                        </label>
                        <span class="field-error" id="e_consent"></span>
                    </div>

                    <div class="btn-row">
                        <button class="btn-secondary" id="back4">← Back</button>
                        <button class="btn-primary" id="submitBtn"> Submit Registration</button>
                    </div>
                </div>

                <!-- ── PANEL 5: Success ───────────────────────────────── -->
                <div class="reg-panel" id="panel5">
                    <div class="success-panel">
                        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 2rem;">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        <h2>Registration Submitted!</h2>
                        <p>Your account is <strong>pending review</strong> by the Office of Student Affairs. You will receive an email notification once your account has been approved.</p>
                        <p class="success-note" style="margin-top: 1rem; color: #94a3b8; font-size: 0.9rem;">Review typically takes 1–2 business days.</p>
                        <a href="login.php" class="btn-primary full-width" style="margin-top:2rem; text-decoration:none;">Go to Login Page</a>
                    </div>
                </div>

            </div><!-- /.reg-card -->
        </section>
    </main>

    <!-- ═══ VALIDATION ERROR MODAL ═══════════════════════════════════ -->
    <div class="val-modal-overlay" id="valModalOverlay" role="dialog" aria-modal="true" aria-labelledby="valModalTitle">
        <div class="val-modal-card">
            <div class="val-icon-badge">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </div>
            <h3 id="valModalTitle">COR Validation Mismatch</h3>
            <div class="val-modal-body">
                <p id="valModalMsg"></p>
                <p class="val-modal-hint" id="valModalHint">Please ensure your inputted details exactly match your uploaded document.</p>
            </div>
            <div class="val-modal-footer">
                <button type="button" class="btn-primary full-width" id="valModalCloseBtn">OK, Got It</button>
            </div>
        </div>
    </div>

    <!-- ═══ PRIVACY MODAL ═════════════════════════════════════════════ -->
    <div class="priv-modal" id="privModal" role="dialog" aria-modal="true" aria-labelledby="privTitle">
        <div class="priv-card">
            <button class="priv-close" id="closePrivBtn" aria-label="Close">&times;</button>
            <h2 id="privTitle">Data Privacy Agreement</h2>
            <div class="priv-body">
                <p>In compliance with the Data Privacy Act of 2012 (Republic Act No. 10173), the system collects and processes personal information such as student ID, name, email address, academic information, and facial data for the purpose of student identification, attendance monitoring, and engagement analysis during school events.</p>
                <p>All collected data will be used solely for academic and administrative purposes and will be stored securely within the system. Access to this information will be limited to authorized personnel such as the Office of Student Affairs (OSA) and system administrators.</p>
                <p>The system ensures that all personal information is protected against unauthorized access, disclosure, or misuse. Facial data collected for recognition purposes will only be used for event participation verification and will not be shared with any third party.</p>
                <p>By registering and using this system, you acknowledge that you have read and understood this Data Privacy Agreement and consent to the collection and processing of your personal data in accordance with the Data Privacy Act of 2012.</p>
            </div>
            <button class="btn-primary full-width" id="agreePrivBtn"> I Agree & Close</button>
        </div>
    </div>

    <!-- ═══ TOAST ═════════════════════════════════════════════════════ -->
    <div id="toast" role="alert" aria-live="polite">
        <span class="toast-icon" id="toastIcon"></span>
        <span id="toastMsg"></span>
    </div>

    <script src="../../assets/js/student/register.js?v=<?= time() ?>"></script>
</body>
</html>
