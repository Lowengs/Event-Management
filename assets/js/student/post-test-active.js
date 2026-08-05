/**
 * Post-Test Active Assessment Interactive Logic
 * Handles pagination, radio selections, engagement tracking, timer countdown, and proctor webcam
 */

document.addEventListener('DOMContentLoaded', () => {
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

    // Update View
    function updateTestView() {
        document.querySelectorAll('.question-section').forEach(sec => {
            sec.classList.remove('active');
        });
        const activeSec = document.getElementById('question-' + currentQuestion);
        if (activeSec) activeSec.classList.add('active');

        if (progressBar && progressText) {
            const pct = (currentQuestion / totalQuestions) * 100;
            progressBar.style.width = pct + '%';
            progressText.textContent = `Question ${currentQuestion} of ${totalQuestions}`;
        }

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
    }

    window.markAnswered = function(qNum) {
        const dot = document.getElementById('dot-' + qNum);
        if (dot) dot.classList.add('answered');
    };

    window.goToQuestion = function(num) {
        if (num >= 1 && num <= totalQuestions) {
            currentQuestion = num;
            updateTestView();
        }
    };

    if (btnPrev) {
        btnPrev.addEventListener('click', () => {
            if (currentQuestion > 1) {
                currentQuestion--;
                updateTestView();
            }
        });
    }

    if (btnNext) {
        btnNext.addEventListener('click', () => {
            if (currentQuestion < totalQuestions) {
                currentQuestion++;
                updateTestView();
            }
        });
    }

    // Engagement Metrics Tracking
    let activeSeconds = 0;
    let totalSeconds = 0;
    let idleSeconds = 0;
    let interactionCount = 0;
    let lastInteractionTime = Date.now();

    function recordInteraction() {
        interactionCount++;
        lastInteractionTime = Date.now();
    }
    window.addEventListener('mousemove', recordInteraction);
    window.addEventListener('keypress', recordInteraction);
    window.addEventListener('scroll', recordInteraction);
    window.addEventListener('click', recordInteraction);

    setInterval(() => {
        totalSeconds++;
        const secondsSinceLastInteraction = (Date.now() - lastInteractionTime) / 1000;
        if (secondsSinceLastInteraction < 3) {
            activeSeconds++;
            idleSeconds = 0;
        } else {
            idleSeconds++;
        }

        let activeRatio = totalSeconds > 0 ? (activeSeconds / totalSeconds) : 1;
        let interactionDensity = Math.min(1.2, 0.5 + (interactionCount / (totalSeconds * 2 + 1)));
        let rawScore = Math.round(activeRatio * interactionDensity * 100);
        let finalScore = Math.max(0, rawScore - (tabSwitches * 25));
        finalScore = Math.min(100, finalScore);

        const elEngagement = document.getElementById('widgetEngagement');
        if (elEngagement) {
            elEngagement.textContent = `${finalScore}%`;
            if (finalScore >= 80) elEngagement.style.color = '#10b981';
            else if (finalScore >= 50) elEngagement.style.color = '#f59e0b';
            else elEngagement.style.color = '#ef4444';
        }
        const elHiddenEngagement = document.getElementById('hiddenEngagementScore');
        if (elHiddenEngagement) elHiddenEngagement.value = finalScore;
    }, 1000);

    // Timer Countdown
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

    // Visibility Monitoring
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            tabSwitches++;
            const elTabSwitches = document.getElementById('hiddenTabSwitches');
            if (elTabSwitches) elTabSwitches.value = tabSwitches;
            
            if (tabSwitches >= maxTabSwitches) {
                const elFlagged = document.getElementById('hiddenMonitoringFlagged');
                if (elFlagged) elFlagged.value = 1;
                showModal("Maximum tab switches detected! Submitting test now.", "warning", "Assessment Flagged", () => {
                    if (testForm) testForm.submit();
                });
            }
        }
    });

    // Proctor Webcam Feed
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

    updateTestView();
});
