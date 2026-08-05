/**
 * Pre-Test Active Assessment Interactive Logic
 * Handles pagination, radio selections, anti-cheating tab monitoring, timer countdown, and proctor webcam
 */

document.addEventListener('DOMContentLoaded', () => {
    // Read total questions and time limit from dataset or initial variables
    const activeContainer = document.querySelector('.active-test-container');
    const totalQuestions = parseInt(document.getElementById('hiddenTotalQuestions')?.value || '1', 10);
    const initialTimeLimit = parseInt(document.getElementById('hiddenTimeLimit')?.value || '30', 10);
    
    let currentQuestion = 1;
    let timeRemaining = initialTimeLimit * 60;
    let tabSwitches = 0;
    const maxTabSwitches = 3;
    let engagementScore = 100;
    
    const testForm = document.getElementById('testForm');
    const progressBar = document.getElementById('progress-bar');
    const progressText = document.getElementById('progress-text');
    const btnPrev = document.getElementById('btnPrev');
    const btnNext = document.getElementById('btnNext');
    const btnSubmit = document.getElementById('btnSubmit');

    // Radio button label selection styling
    document.querySelectorAll('.option-label input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const container = this.closest('.options-list');
            if (container) {
                container.querySelectorAll('.option-label').forEach(lbl => {
                    lbl.classList.remove('selected');
                });
            }
            if (this.checked) {
                this.closest('.option-label')?.classList.add('selected');
            }
        });
    });

    // Update Progress and active question view
    window.updateProgress = function() {
        if (!progressBar || !progressText) return;
        const pct = (currentQuestion / totalQuestions) * 100;
        progressBar.style.width = pct + '%';
        progressText.textContent = `Question ${currentQuestion} of ${totalQuestions}`;
        
        document.querySelectorAll('.question-section').forEach((el, idx) => {
            el.classList.toggle('active', (idx + 1) === currentQuestion);
        });

        document.querySelectorAll('.q-dot').forEach((dot, idx) => {
            if ((idx + 1) === currentQuestion) {
                dot.classList.add('current');
            } else {
                dot.classList.remove('current');
            }
        });

        if (btnPrev) btnPrev.disabled = (currentQuestion === 1);
        if (currentQuestion === totalQuestions) {
            if (btnNext) btnNext.style.display = 'none';
            if (btnSubmit) btnSubmit.style.display = 'inline-flex';
        } else {
            if (btnNext) btnNext.style.display = 'inline-flex';
            if (btnSubmit) btnSubmit.style.display = 'none';
        }
    };

    window.changeQuestion = function(dir) {
        const next = currentQuestion + dir;
        if (next >= 1 && next <= totalQuestions) {
            currentQuestion = next;
            window.updateProgress();
        }
    };

    window.goToQuestion = function(num) {
        if (num >= 1 && num <= totalQuestions) {
            currentQuestion = num;
            window.updateProgress();
        }
    };

    window.markAnswered = function(qNum) {
        const dot = document.getElementById('dot-' + qNum);
        if (dot) dot.classList.add('answered');
    };

    // Attach button click listeners
    if (btnPrev) {
        btnPrev.addEventListener('click', () => window.changeQuestion(-1));
    }
    if (btnNext) {
        btnNext.addEventListener('click', () => window.changeQuestion(1));
    }

    // Countdown Timer
    const timerElement = document.getElementById('countdown-timer');
    if (timerElement) {
        const timerInterval = setInterval(() => {
            if (timeRemaining <= 0) {
                clearInterval(timerInterval);
                if (testForm) testForm.submit();
                return;
            }
            timeRemaining--;
            const mins = Math.floor(timeRemaining / 60);
            const secs = timeRemaining % 60;
            timerElement.textContent = 
                String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
        }, 1000);
    }

    // Anti-cheating Visibility & Tab Switch Monitoring
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            tabSwitches++;
            engagementScore = Math.max(0, engagementScore - 20);
            
            const elTabSwitches = document.getElementById('hiddenTabSwitches');
            const elEngagement = document.getElementById('hiddenEngagementScore');
            const elFlagged = document.getElementById('hiddenMonitoringFlagged');

            if (elTabSwitches) elTabSwitches.value = tabSwitches;
            if (elEngagement) elEngagement.value = engagementScore;
            
            if (tabSwitches >= maxTabSwitches) {
                if (elFlagged) elFlagged.value = 1;
                showModal("Maximum tab switches detected! Your assessment will now be automatically submitted.", "warning", "Assessment Flagged", () => {
                    if (testForm) testForm.submit();
                });
            }
        }
    });

    // Anti-Spoofing Proctoring Webcam Stream Initialization
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia({ video: { width: 320, height: 240 } })
            .then(stream => {
                const video = document.getElementById('proctorWebcam');
                if (video) video.srcObject = stream;
            })
            .catch(err => {
                console.warn("Anti-spoofing proctor camera stream unavailable:", err);
                const dot = document.getElementById('camStatusDot');
                if (dot) dot.style.background = '#eab308';
            });
    }

    // Initial View Run
    window.updateProgress();
});